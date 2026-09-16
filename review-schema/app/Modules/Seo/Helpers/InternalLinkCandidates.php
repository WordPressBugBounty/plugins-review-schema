<?php
/**
 * Database-first internal-link candidate discovery for "Improve with AI".
 *
 * Flow:
 *   current content
 *     → database candidate discovery (shared taxonomy + keyword/title search)
 *     → global exclusions / ignore list / already-linked / duplicates / self
 *     → cheap PHP relevance scoring (exact + word-boundary phrase, keyword
 *       overlap, shared taxonomy, recency)
 *     → Top N candidates
 *     → natural-anchor detection (exact / word-boundary / n-gram)
 *     → link opportunities (anchor exists) vs content recommendations (no anchor)
 *
 * The database/PHP layer reduces a potentially large site to the most promising
 * candidates; the AI performs the deeper semantic decision on that shortlist.
 * The XML sitemap, when configured, is used only as a public-URL validation
 * layer — never as the primary ranking mechanism.
 *
 * @package Rtrs\Modules\Seo\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InternalLinkCandidates
 */
class InternalLinkCandidates {

	/** Maximum scored candidates sent to the AI (Top N). */
	const LIMIT = 30;

	/** Of the Top N, at most this many link opportunities and recommendations. */
	const LINK_LIMIT    = 15;
	const RELATED_LIMIT = 15;

	/** Candidate pool size fetched from the DB before scoring (bounded). */
	const POOL_CAP = 150;

	/** Excerpt word cap per recommendation (helps the AI judge relevance). */
	const EXCERPT_WORDS = 30;

	/**
	 * Discover internal-link candidates for a post (database-first).
	 *
	 * @param int         $post_id   Current post ID.
	 * @param int         $limit     Top-N candidates to hand to the AI.
	 * @param string|null $live_html Live editor HTML, when available, so freshly
	 *                               applied links are respected before saving.
	 * @return array{mode:string,candidates:array,semantic:array,content:string}
	 */
	public static function discover( $post_id, $limit = self::LIMIT, $live_html = null ) {
		$post_id = (int) $post_id;
		$empty   = [ 'mode' => 'db', 'candidates' => [], 'semantic' => [], 'content' => '' ];
		if ( $post_id <= 0 ) {
			return $empty;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $empty;
		}

		// ── Current content ────────────────────────────────────────────────────
		$context = new \Rtrs\Modules\Seo\Analysis\AnalysisContext( $post_id, ( is_string( $live_html ) && '' !== trim( $live_html ) ) ? $live_html : null );
		$html    = (string) $context->content();
		// Search text with existing hyperlinks removed, so an already-linked phrase
		// (including one just applied) is never suggested again.
		$plain = trim( (string) wp_strip_all_tags( self::strip_anchors( $html ) ) );
		if ( '' === $plain ) {
			return $empty;
		}

		$matcher   = new PhraseMatcher( $plain );
		$words     = self::word_set( $plain );
		$linked    = self::linked_urls( $html );
		$post_types = self::candidate_post_types();

		// ── Database candidate discovery (bounded, ranked-source queries) ───────
		$pool_cap    = (int) apply_filters( 'rtrs_internal_link_pool', self::POOL_CAP );
		$related     = self::related_ids( $post, $post_types, $pool_cap );          // shared taxonomy
		$related_set = array_flip( $related );
		$ids         = $related;
		if ( count( $ids ) < $pool_cap ) {
			$ids = array_merge( $ids, self::keyword_ids( $post, $post_types, $pool_cap - count( $ids ), array_merge( $ids, [ $post_id ] ) ) );
		}
		// Modest recent top-up so topic-less posts still have something to rank.
		if ( count( $ids ) < 20 ) {
			$ids = array_merge( $ids, self::recent_ids( $post_types, 20 - count( $ids ), array_merge( $ids, [ $post_id ] ) ) );
		}
		$ids = array_values( array_diff( array_unique( array_map( 'intval', $ids ) ), [ $post_id ] ) );
		if ( empty( $ids ) ) {
			return [ 'mode' => 'db', 'candidates' => [], 'semantic' => [], 'content' => trim( wp_trim_words( $plain, 250, '' ) ) ];
		}

		// Load the candidate objects in ONE query (no N+1 for title/slug).
		$posts = get_posts(
			[
				'post__in'               => $ids,
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => count( $ids ),
				'orderby'                => 'post__in',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Optional: validate against the sitemap (public/indexable URLs) when set.
		$sitemap_set = self::sitemap_url_set();

		// ── Cheap PHP relevance scoring + natural-anchor detection ─────────────
		$scored = [];
		foreach ( $posts as $cand ) {
			// Global ignore list (shared with the SEO Report).
			if ( \Rtrs\Helpers\ContentIgnore::is_ignored( $cand->post_type ) ) {
				continue;
			}
			$url = get_permalink( $cand->ID );
			if ( ! $url ) {
				continue;
			}
			$norm = SitemapIndex::normalize_url( $url );
			$key  = '' !== $norm ? $norm : $url;
			// Already linked from this content → skip (no duplicate link).
			if ( '' !== $norm && isset( $linked[ $norm ] ) ) {
				continue;
			}
			// Sitemap validation: only keep public/indexable URLs when a sitemap exists.
			if ( null !== $sitemap_set && '' !== $norm && ! isset( $sitemap_set[ $norm ] ) ) {
				continue;
			}

			$title   = trim( wp_strip_all_tags( (string) $cand->post_title ) );
			$slug    = (string) $cand->post_name;
			$phrases = self::candidate_phrases( $title, $slug );

			// Natural-anchor detection: exact / word-boundary (via PhraseMatcher),
			// most-specific phrase first. Higher confidence for longer phrases.
			$anchor      = null;
			$offset      = 0;
			$match_score = 0;
			foreach ( $phrases as $phrase ) {
				$hit = $matcher->match( $phrase );
				if ( null !== $hit ) {
					$anchor      = $hit['phrase'];
					$offset      = $hit['offset'];
					$match_score = 100 + ( count( explode( ' ', $hit['phrase'] ) ) * 10 );
					break;
				}
			}

			// Keyword overlap (title + slug words present in the article).
			$overlap = 0;
			foreach ( self::word_set( $title . ' ' . str_replace( '-', ' ', $slug ) ) as $w => $_ ) {
				if ( isset( $words[ $w ] ) ) {
					$overlap++;
				}
			}
			$tax_score = isset( $related_set[ $cand->ID ] ) ? 20 : 0;
			$score     = $match_score + ( $overlap * 4 ) + $tax_score;

			// Reduce to promising candidates: require a natural anchor, OR a real
			// keyword overlap, OR a shared taxonomy. Weak matches are rejected here.
			if ( 0 === $match_score && $overlap < 2 && 0 === $tax_score ) {
				continue;
			}

			$scored[] = [
				'id'         => (int) $cand->ID,
				'url'        => $key,
				'title'      => $title,
				'slug'       => $slug,
				'anchor'     => $anchor,
				'offset'     => $offset,
				'has_anchor' => $match_score > 0,
				'score'      => $score,
				'post'       => $cand,
			];
		}

		// Exact/word-boundary matches score highest, so they sort to the top.
		usort( $scored, function ( $a, $b ) { return $b['score'] - $a['score']; } );
		$scored = array_slice( $scored, 0, max( 1, (int) $limit ) );

		// Split into link opportunities (anchor exists) vs content recommendations.
		$link_out    = [];
		$related_out = [];
		foreach ( $scored as $c ) {
			if ( $c['has_anchor'] ) {
				if ( count( $link_out ) < self::LINK_LIMIT ) {
					$link_out[] = [
						'url'     => $c['url'],
						'title'   => $c['title'],
						'slug'    => $c['slug'],
						'phrase'  => $c['anchor'],
						'context' => $matcher->sentence( $c['offset'] ),
					];
				}
			} elseif ( count( $related_out ) < self::RELATED_LIMIT ) {
				$related_out[] = [
					'url'     => $c['url'],
					'title'   => $c['title'],
					'slug'    => $c['slug'],
					'excerpt' => self::excerpt( $c['post'] ),
				];
			}
		}

		return [
			'mode'       => 'db',
			'candidates' => $link_out,
			'semantic'   => $related_out,
			'content'    => trim( wp_trim_words( $plain, 250, '' ) ),
		];
	}

	/**
	 * Candidate anchor phrases from a post's title and slug (most-specific first).
	 *
	 * @param string $title Post title.
	 * @param string $slug  Post slug.
	 * @return string[]
	 */
	private static function candidate_phrases( $title, $slug ) {
		$clean_title   = preg_replace( '/[^\p{L}\p{N}\s-]/u', ' ', (string) $title );
		$title_phrases = SitemapIndex::phrases_from_slug( (string) $clean_title );
		$slug_phrases  = SitemapIndex::phrases_from_slug( (string) $slug );

		$merged = [];
		$seen   = [];
		foreach ( array_merge( $title_phrases, $slug_phrases ) as $phrase ) {
			$k = strtolower( $phrase );
			if ( '' !== $phrase && ! isset( $seen[ $k ] ) ) {
				$seen[ $k ] = true;
				$merged[]   = $phrase;
			}
		}

		return array_slice( $merged, 0, 5 );
	}

	/**
	 * Normalized set of sitemap URLs for public-URL validation, or null when no
	 * usable sitemap is configured (validation then skipped).
	 *
	 * @return array<string,bool>|null
	 */
	private static function sitemap_url_set() {
		if ( ! SitemapIndex::is_configured() ) {
			return null;
		}
		$entries = SitemapIndex::entries();
		if ( empty( $entries ) ) {
			return null; // Broken/empty sitemap → don't block DB discovery.
		}
		$set = [];
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['url'] ) ) {
				$set[ $entry['url'] ] = true;
			}
		}
		return $set;
	}

	/**
	 * Lower-cased set of meaningful words in a text (for keyword-overlap scoring).
	 *
	 * @param string $text Plain text.
	 * @return array<string,bool>
	 */
	private static function word_set( $text ) {
		$stop = array_flip( SitemapIndex::STOPWORDS );
		$set  = [];
		foreach ( preg_split( '/[^\p{L}\p{N}]+/u', strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
			if ( strlen( $w ) >= 4 && ! isset( $stop[ $w ] ) ) {
				$set[ $w ] = true;
			}
		}
		return $set;
	}

	/**
	 * Remove existing <a>…</a> spans from HTML so their text is not matchable.
	 *
	 * @param string $html Content HTML.
	 * @return string
	 */
	private static function strip_anchors( $html ) {
		return (string) preg_replace( '#<a\b[^>]*>.*?</a>#is', ' ', (string) $html );
	}

	/**
	 * Normalized set of destinations already linked in the content.
	 *
	 * @param string $html Content HTML.
	 * @return array<string,bool>
	 */
	private static function linked_urls( $html ) {
		$linked = [];
		if ( preg_match_all( '#<a\b[^>]*href=["\']([^"\']+)["\']#i', (string) $html, $m ) ) {
			foreach ( $m[1] as $href ) {
				$norm = SitemapIndex::normalize_url( $href );
				if ( '' !== $norm ) {
					$linked[ $norm ] = true;
				}
			}
		}
		return $linked;
	}

	/**
	 * Public, non-ignored post types eligible as internal-link targets.
	 *
	 * @return string[]
	 */
	private static function candidate_post_types() {
		// Shared global rule: public post types minus globally-ignored ones.
		$types = \Rtrs\Helpers\ContentIgnore::allowed_post_types();

		/**
		 * Filter the post types offered as internal-link targets.
		 *
		 * @param string[] $types Allowed (non-ignored) post type names.
		 */
		$types = apply_filters( 'rtrs_internal_link_post_types', array_values( $types ) );

		return array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
	}

	/**
	 * IDs of published posts sharing a category/tag (or any term) with the post.
	 *
	 * @param \WP_Post        $post       Current post.
	 * @param string|string[] $post_types Post type(s) to search within.
	 * @param int             $limit      Maximum IDs to return.
	 * @return int[]
	 */
	private static function related_ids( $post, $post_types, $limit ) {
		$tax_query = self::shared_term_query( $post );
		if ( empty( $tax_query ) ) {
			return [];
		}

		$query = new \WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'post__not_in'           => [ $post->ID ],
				'posts_per_page'         => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
				'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			]
		);

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * IDs of published content most relevant to the post by keyword/title search.
	 *
	 * @param \WP_Post        $post       Current post.
	 * @param string|string[] $post_types Post type(s) to search within.
	 * @param int             $limit      Maximum IDs to return.
	 * @param int[]           $exclude    IDs to exclude.
	 * @return int[]
	 */
	private static function keyword_ids( $post, $post_types, $limit, array $exclude ) {
		if ( $limit < 1 ) {
			return [];
		}

		$term = trim( (string) SeoMeta::get_focus_keyword( $post->ID ) );
		if ( '' === $term ) {
			$term = trim( wp_strip_all_tags( (string) $post->post_title ) );
		}
		if ( '' === $term ) {
			return [];
		}

		$query = new \WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				's'                      => $term,
				'post__not_in'           => array_map( 'intval', $exclude ),
				'posts_per_page'         => $limit,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			]
		);

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * IDs of the most recent published posts, excluding given IDs.
	 *
	 * @param string|string[] $post_types Post type(s).
	 * @param int             $limit      Maximum IDs to return.
	 * @param int[]           $exclude    IDs to exclude.
	 * @return int[]
	 */
	private static function recent_ids( $post_types, $limit, array $exclude ) {
		if ( $limit < 1 ) {
			return [];
		}

		$query = new \WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'post__not_in'           => array_map( 'intval', $exclude ),
				'posts_per_page'         => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			]
		);

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * tax_query matching any term the post shares across its public taxonomies.
	 *
	 * @param \WP_Post $post Current post.
	 * @return array
	 */
	private static function shared_term_query( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		if ( empty( $taxonomies ) ) {
			return [];
		}

		$clauses = [];
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$clauses[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => array_map( 'intval', $terms ),
			];
		}

		if ( empty( $clauses ) ) {
			return [];
		}
		if ( count( $clauses ) > 1 ) {
			$clauses['relation'] = 'OR';
		}

		return $clauses;
	}

	/**
	 * Short plain-text excerpt for a candidate, preferring the manual excerpt.
	 *
	 * @param \WP_Post $post Candidate post.
	 * @return string
	 */
	private static function excerpt( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$text = ( '' !== (string) $post->post_excerpt )
			? $post->post_excerpt
			: wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );

		return trim( wp_trim_words( $text, self::EXCERPT_WORDS, '' ) );
	}
}
