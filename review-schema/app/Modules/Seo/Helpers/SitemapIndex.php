<?php
/**
 * XML sitemap reader for internal-link candidate sourcing.
 *
 * Fetches and parses the site's configured XML sitemap (or sitemap index),
 * extracts every linkable page URL, derives a meaningful slug for each, and
 * generates a small, conservative set of candidate anchor phrases from the slug.
 * The parsed result is cached in a transient so a large sitemap is fetched and
 * parsed once, not per request — keeping internal-link generation off the
 * database entirely when a sitemap is configured.
 *
 * @package Rtrs\Modules\Seo\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SitemapIndex
 */
class SitemapIndex {

	/** Option holding the SEO Report settings. */
	const OPTION = 'rtrs_seo_report_settings';

	/** Cache lifetime for the parsed sitemap (12 hours). */
	const CACHE_TTL = 43200;

	/** Hard caps to keep large/nested sitemaps bounded. */
	const MAX_CHILD_SITEMAPS = 50;
	const MAX_URLS           = 5000;
	const MAX_DEPTH          = 2;

	/**
	 * Child-sitemap "types" that are archive/taxonomy listings, not content, and
	 * so must never become internal-link targets. Matched against the type token
	 * in a `{type}-sitemap.xml` child URL (Yoast / Rank Math / core naming).
	 * Centralized + filterable so new taxonomy types are one line to add.
	 */
	const EXCLUDED_SITEMAP_TYPES = [ 'category', 'tag', 'post_tag', 'author', 'post_format', 'product_cat', 'product_tag', 'product_brand', 'product_shipping_class' ];

	/** Common words that never form a meaningful single-word phrase. */
	const STOPWORDS = [ 'the', 'a', 'an', 'and', 'or', 'of', 'for', 'to', 'in', 'on', 'with', 'your', 'my', 'best', 'top', 'how', 'why', 'what', 'is', 'are', 'this', 'that', 'plugin', 'page', 'post', 'guide' ];

	/**
	 * The configured sitemap URL, or '' when none is set.
	 *
	 * @return string
	 */
	public static function url() {
		$url = \rtrs()->get_options( self::OPTION, [ 'xml_sitemap_url', '' ] );
		return is_string( $url ) ? trim( $url ) : '';
	}

	/**
	 * Whether a usable sitemap URL is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::url();
	}

	/**
	 * Parsed sitemap entries: [ ['url'=>, 'slug'=>, 'phrases'=>[]], ... ].
	 *
	 * Cached; returns an empty array on fetch/parse failure so callers fall back
	 * to the built-in scan.
	 *
	 * @return array<int,array{url:string,slug:string,phrases:string[]}>
	 */
	public static function entries() {
		$url = self::url();
		if ( '' === $url ) {
			return [];
		}

		$cache_key = 'rtrs_sitemap_entries_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$visited = [];
		$host    = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$urls    = self::collect_urls( $url, 0, $visited, $host );
		$entries = [];
		$seen    = [];
		foreach ( $urls as $loc ) {
			$norm = self::normalize_url( $loc );
			if ( '' === $norm || isset( $seen[ $norm ] ) ) {
				continue;
			}
			$slug = self::slug_from_url( $norm );
			if ( '' === $slug ) {
				continue; // homepage / non-slugged URL.
			}
			$phrases = self::phrases_from_slug( $slug );
			if ( empty( $phrases ) ) {
				continue;
			}
			$seen[ $norm ] = true;
			$entries[]     = [
				'url'     => $norm,
				'slug'    => $slug,
				'phrases' => $phrases,
			];
		}

		// Cache even an empty result briefly to avoid re-fetching a broken URL on
		// every keystroke, but keep it short so a fixed sitemap recovers quickly.
		set_transient( $cache_key, $entries, empty( $entries ) ? MINUTE_IN_SECONDS * 10 : self::CACHE_TTL );

		return $entries;
	}

	/**
	 * Recursively collect <loc> page URLs from a sitemap or sitemap index.
	 *
	 * @param string $sitemap_url Sitemap URL to fetch.
	 * @param int    $depth       Current recursion depth.
	 * @param array  $visited     Already-fetched sitemap URLs (loop guard).
	 * @param string $host        Allowed host — child sitemaps on other hosts are
	 *                            never fetched (no external-domain crawling).
	 * @return string[] Page URLs (raw, not yet normalized).
	 */
	private static function collect_urls( $sitemap_url, $depth, array &$visited, $host ) {
		if ( $depth > self::MAX_DEPTH || isset( $visited[ $sitemap_url ] ) ) {
			return [];
		}
		$visited[ $sitemap_url ] = true;

		$xml = self::fetch_xml( $sitemap_url );
		if ( null === $xml ) {
			return [];
		}

		// Sitemap index → recurse into child sitemaps.
		if ( isset( $xml->sitemap ) ) {
			$urls  = [];
			$count = 0;
			foreach ( $xml->sitemap as $child ) {
				if ( $count >= self::MAX_CHILD_SITEMAPS || count( $urls ) >= self::MAX_URLS ) {
					break;
				}
				$loc = isset( $child->loc ) ? trim( (string) $child->loc ) : '';
				if ( '' === $loc ) {
					continue;
				}
				// Never crawl a child sitemap on a different host.
				if ( strtolower( (string) wp_parse_url( $loc, PHP_URL_HOST ) ) !== $host ) {
					continue;
				}
				// Skip archive/taxonomy child sitemaps entirely (not fetched, and
				// they don't consume the child budget) so only real content URLs
				// become link targets. Custom post types are kept.
				if ( self::is_excluded_sitemap( $loc ) ) {
					continue;
				}
				$count++;
				$urls = array_merge( $urls, self::collect_urls( $loc, $depth + 1, $visited, $host ) );
			}
			return $urls;
		}

		// urlset → page URLs.
		$urls = [];
		if ( isset( $xml->url ) ) {
			foreach ( $xml->url as $entry ) {
				if ( count( $urls ) >= self::MAX_URLS ) {
					break;
				}
				$loc = isset( $entry->loc ) ? trim( (string) $entry->loc ) : '';
				if ( '' !== $loc ) {
					$urls[] = $loc;
				}
			}
		}
		return $urls;
	}

	/**
	 * Whether a child-sitemap URL is an archive/taxonomy listing to skip.
	 *
	 * Reads the type token from a `{type}-sitemap[N].xml` filename (Yoast /
	 * Rank Math / core naming) and checks it against the excluded-types list.
	 * A URL that does not follow the `{type}-sitemap.xml` convention is NOT
	 * excluded — custom-post-type sitemaps stay eligible.
	 *
	 * @param string $url Child sitemap URL.
	 * @return bool
	 */
	public static function is_excluded_sitemap( $url ) {
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $path || ! preg_match( '#([a-z0-9_\-]+)-sitemap\d*\.xml$#', $path, $m ) ) {
			return false;
		}
		$type = str_replace( '-', '_', $m[1] );

		/**
		 * Filter the child-sitemap types excluded from internal-link targeting.
		 *
		 * @param string[] $types Excluded type tokens (e.g. 'category', 'author').
		 * @param string   $url   The child sitemap URL under consideration.
		 */
		$excluded = apply_filters( 'rtrs_internal_link_excluded_sitemaps', self::EXCLUDED_SITEMAP_TYPES, $url );

		return in_array( $type, (array) $excluded, true );
	}

	/**
	 * Fetch and parse a sitemap URL into a SimpleXML element.
	 *
	 * Handles gzip-encoded sitemaps and never emits libxml warnings or throws.
	 *
	 * @param string $url Sitemap URL.
	 * @return \SimpleXMLElement|null
	 */
	private static function fetch_xml( $url ) {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'SchemaEngineAI/1.0; ' . home_url(),
			]
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return null;
		}

		// Decode gzip when the server didn't (e.g. .xml.gz sitemaps).
		if ( 0 === strncmp( $body, "\x1f\x8b", 2 ) && function_exists( 'gzdecode' ) ) {
			$decoded = @gzdecode( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $decoded ) {
				$body = $decoded;
			}
		}

		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return ( false === $xml ) ? null : $xml;
	}

	/**
	 * Normalize a URL for de-duplication and self-comparison: same-host only,
	 * lower-case host, no fragment or query, unified trailing slash.
	 *
	 * @param string $url Raw URL.
	 * @return string Normalized URL, or '' when off-site / invalid.
	 */
	public static function normalize_url( $url ) {
		$url   = trim( (string) $url );
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return '';
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( strtolower( $parts['host'] ) !== strtolower( (string) $site_host ) ) {
			return ''; // Only link within the same site.
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$path = '/' . trim( $path, '/' );
		if ( '/' !== $path ) {
			$path .= '/';
		}

		return strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] ) . $path;
	}

	/**
	 * Derive a meaningful slug from a normalized URL: the last path segment,
	 * with date parts and trailing numeric IDs stripped.
	 *
	 * @param string $url Normalized URL.
	 * @return string Slug, or '' when there is none (homepage, numeric-only).
	 */
	public static function slug_from_url( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = trim( $path, '/' );
		if ( '' === $path ) {
			return '';
		}

		$segments = explode( '/', $path );
		// Drop trailing date segments (/2024/01/12/) and pure numeric IDs.
		while ( ! empty( $segments ) ) {
			$last = end( $segments );
			if ( preg_match( '/^\d+$/', $last ) ) {
				array_pop( $segments );
				continue;
			}
			break;
		}

		$slug = ! empty( $segments ) ? end( $segments ) : '';
		$slug = rawurldecode( (string) $slug );

		// A slug that is purely numeric or too short carries no topic.
		if ( '' === $slug || preg_match( '/^\d+$/', $slug ) || strlen( $slug ) < 3 ) {
			return '';
		}

		return $slug;
	}

	/**
	 * Convert a slug into a small, conservative set of candidate phrases.
	 *
	 * Full phrase first, then trailing sub-phrases formed by dropping leading
	 * words (e.g. "best wordpress seo plugin" → "wordpress seo plugin" → "seo
	 * plugin"), down to a two-word tail. Broad or meaningless phrases are
	 * filtered so the local matcher stays strict, and the list is capped so the
	 * most-specific variants win.
	 *
	 * @param string $slug Slug.
	 * @return string[] Up to 3 phrases, most-specific first.
	 */
	public static function phrases_from_slug( $slug ) {
		$text = trim( preg_replace( '/[-_]+/', ' ', $slug ) );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( '' === $text ) {
			return [];
		}

		$words   = explode( ' ', $text );
		$count   = count( $words );
		$phrases = [];

		if ( 1 === $count ) {
			$phrases[] = $words[0];
		} else {
			// Trailing sub-phrases: full, then drop one leading word each step,
			// stopping at a two-word tail (most-specific first).
			for ( $start = 0; $start <= $count - 2; $start++ ) {
				$phrases[] = implode( ' ', array_slice( $words, $start ) );
			}
		}

		// Keep only phrases specific enough to be safe (conservative), capped.
		$clean = [];
		foreach ( $phrases as $phrase ) {
			if ( self::is_meaningful_phrase( $phrase ) && ! in_array( $phrase, $clean, true ) ) {
				$clean[] = $phrase;
			}
		}

		return array_slice( $clean, 0, 3 );
	}

	/**
	 * Whether a phrase is specific enough to use as a link candidate.
	 *
	 * Requires ≥2 words, OR a single word of ≥6 chars that is not a stopword.
	 *
	 * @param string $phrase Candidate phrase.
	 * @return bool
	 */
	private static function is_meaningful_phrase( $phrase ) {
		$phrase = trim( $phrase );
		if ( strlen( $phrase ) < 4 ) {
			return false;
		}
		$words = explode( ' ', $phrase );

		if ( count( $words ) >= 2 ) {
			// Reject if every word is a stopword.
			foreach ( $words as $w ) {
				if ( ! in_array( strtolower( $w ), self::STOPWORDS, true ) ) {
					return true;
				}
			}
			return false;
		}

		$word = strtolower( $words[0] );
		return strlen( $word ) >= 6 && ! in_array( $word, self::STOPWORDS, true );
	}

	/**
	 * Sanitize the SEO Report settings on save (URL field).
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function sanitize_settings( $settings ) {
		if ( isset( $settings['xml_sitemap_url'] ) ) {
			$settings['xml_sitemap_url'] = esc_url_raw( trim( (string) $settings['xml_sitemap_url'] ), [ 'http', 'https' ] );
		}
		if ( isset( $settings['ignore_post_types'] ) ) {
			$settings['ignore_post_types'] = is_array( $settings['ignore_post_types'] )
				? array_values( array_filter( array_map( 'sanitize_key', $settings['ignore_post_types'] ) ) )
				: [];
		}
		return $settings;
	}

	/**
	 * Clear the cached sitemap parse when the SEO Report settings are saved.
	 *
	 * @param string $section Saved section (option name without the rtrs_ prefix).
	 * @return void
	 */
	public static function on_settings_saved( $section ) {
		if ( 'seo_report_settings' !== $section ) {
			return;
		}
		$url = self::url();
		if ( '' !== $url ) {
			delete_transient( 'rtrs_sitemap_entries_' . md5( $url ) );
		}
	}
}
