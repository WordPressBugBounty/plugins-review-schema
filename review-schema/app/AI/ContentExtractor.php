<?php
/**
 * Content Extractor - Extracts structured content signals from WordPress posts/pages.
 *
 * Analyzes post content across 4 signal layers:
 * Primary (title, body, author), Secondary (custom fields, media),
 * Structural (FAQ patterns, steps), and Contextual (site info, breadcrumbs).
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;

defined( 'ABSPATH' ) || exit;

class ContentExtractor {

	/**
	 * Maximum word count for AI payload to optimize token usage.
	 *
	 * @var int
	 */
	const MAX_WORDS = 3000;

	/**
	 * Post object being analyzed.
	 *
	 * @var \WP_Post|null
	 */
	private $post;

	/**
	 * Set the post object for extraction.
	 *
	 * Used by Pro plugin to call extraction methods via filter hooks.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function set_post( $post ) {
		$this->post = $post;
	}

	/**
	 * Extract all content signals from a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to extract content from.
	 *
	 * @return array|\WP_Error Structured payload array or WP_Error on failure.
	 */
	public function extract( $post_id ) {
		$this->post = get_post( $post_id );

		if ( ! $this->post ) {
			return new \WP_Error( 'invalid_post', __( 'Post not found.', 'review-schema' ) );
		}

		$payload = [
			'primary'    => $this->extract_primary_signals(),
			'secondary'  => $this->extract_secondary_signals(),
			'structural' => $this->extract_structural_signals(),
			'contextual' => $this->extract_contextual_signals(),
		];

		/**
		 * Filter the complete extraction payload before AI processing.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $payload Extracted content payload.
		 * @param \WP_Post $post    The post object.
		 */
		return apply_filters( 'rtrs_ai_extraction_payload', $payload, $this->post );
	}

	/**
	 * Extract primary content signals (high priority).
	 *
	 * @since 1.0.0
	 *
	 * @return array Primary signal data.
	 */
	private function extract_primary_signals() {
		$author_id = $this->post->post_author;

		return [
			'title'          => get_the_title( $this->post ),
			'slug'           => $this->post->post_name,
			'body'           => $this->get_clean_body(),
			'excerpt'        => $this->get_excerpt(),
			'post_type'      => $this->post->post_type,
			'author'         => [
				'name'   => get_the_author_meta( 'display_name', $author_id ),
				'bio'    => get_the_author_meta( 'description', $author_id ),
				'url'    => get_author_posts_url( $author_id ),
				'avatar' => get_avatar_url( $author_id, [ 'size' => 96 ] ),
			],
			'publish_date'   => get_the_date( 'c', $this->post ),
			'modified_date'  => get_the_modified_date( 'c', $this->post ),
			'featured_image' => $this->get_featured_image_data(),
			'permalink'      => get_permalink( $this->post ),
		];
	}

	/**
	 * Extract secondary content signals (medium priority).
	 *
	 * @since 1.0.0
	 *
	 * @return array Secondary signal data.
	 */
	private function extract_secondary_signals() {
		$taxonomies = $this->get_post_type_taxonomies();

		$signals = [
			'categories'     => $this->get_term_names( $taxonomies['category'] ),
			'tags'           => $this->get_term_names( $taxonomies['tag'] ),
			'custom_fields'  => $this->get_relevant_custom_fields(),
			'comment_count'  => (int) $this->post->comment_count,
			'has_reviews'    => $this->detect_reviews(),
			'word_count'     => str_word_count( wp_strip_all_tags( $this->post->post_content ) ),
			'embedded_media' => $this->extract_embedded_media(),
		];

		/**
		 * Product data extraction (WooCommerce/EDD).
		 * Delegated via filter so Pro can provide full product data.
		 * Free version defaults to null (no product data extraction).
		 */
		$product_data = apply_filters( 'rtrs_ai_extract_wc_product_data', null, $this->post );

		if ( $product_data ) {
			$signals['wc_product'] = $product_data;
		}

		$edd_data = apply_filters( 'rtrs_ai_extract_edd_product_data', null, $this->post );

		if ( $edd_data ) {
			$signals['edd_product'] = $edd_data;
		}

		// RTCL Classified Listing rating data.
		if ( 'rtcl_listing' === $this->post->post_type ) {
			$rtcl_rating = $this->extract_rtcl_rating_data();
			if ( $rtcl_rating ) {
				$signals['rtcl_rating'] = $rtcl_rating;
			}
		}

		// Course data extraction — delegated to Pro via filter.
		$course_data = apply_filters( 'rtrs_ai_extract_course_data', null, $this->post );

		if ( $course_data ) {
			$signals['tutor_course'] = $course_data;
		}

		/**
		 * FluentCart product data extraction.
		 * Delegated via filter so Pro can provide full product data.
		 * Free version defaults to null (no FluentCart data extraction).
		 */
		$fluentcart_data = apply_filters( 'rtrs_ai_extract_fluentcart_product_data', null, $this->post );

		if ( $fluentcart_data ) {
			$signals['fluentcart_product'] = $fluentcart_data;
		}

		/**
		 * SureCart product data extraction.
		 * Delegated via filter so Pro can provide full product data.
		 * Free version defaults to null (no SureCart data extraction).
		 */
		$surecart_data = apply_filters( 'rtrs_ai_extract_surecart_product_data', null, $this->post );

		if ( $surecart_data ) {
			$signals['surecart_product'] = $surecart_data;
		}

		// Review-schema plugin's own ratings for any post type with review enabled.
		if ( Functions::isEnableReviewByPostType( $this->post->post_type ) ) {
			$avg   = ReviewFns::getAvgRatings( $this->post->ID, true );
			$total = ReviewFns::getTotalRatings( $this->post->ID );

			if ( $avg && $total ) {
				$signals['review_rating'] = [
					'average_rating' => (float) $avg,
					'review_count'   => (int) $total,
					'rating_count'   => (int) $total,
				];
			}
		}

		return $signals;
	}

	/**
	 * Extract structural patterns from the content.
	 *
	 * @since 1.0.0
	 *
	 * @return array Structural signal data.
	 */
	private function extract_structural_signals() {
		$content = $this->post->post_content;

		return [
			'headings'            => $this->extract_heading_hierarchy( $content ),
			'faq_patterns'        => $this->detect_faq_patterns( $content ),
			'has_faq_block'       => $this->has_faq_block( $content ),
			'has_faq_meta'        => $this->has_faq_meta(),
			'howto_patterns'      => $this->detect_howto_patterns( $content ),
			'has_tables'          => (bool) preg_match( '/<table[\s>]/i', $content ),
			'has_ordered_list'    => (bool) preg_match( '/<ol[\s>]/i', $content ),
			'has_recipe_markers'  => $this->detect_recipe_markers( $content ),
			'has_event_markers'   => $this->detect_event_markers( $content ),
			'has_product_markers' => $this->detect_product_markers(),
			'internal_links'      => $this->count_internal_links( $content ),
			'external_links'      => $this->count_external_links( $content ),
		];
	}

	/**
	 * Extract contextual signals (site-wide data).
	 *
	 * @since 1.0.0
	 *
	 * @return array Contextual signal data.
	 */
	private function extract_contextual_signals() {
		$custom_logo_id = get_theme_mod( 'custom_logo' );

		return [
			'site_name'     => get_bloginfo( 'name' ),
			'site_url'      => home_url( '/' ),
			'site_logo'     => $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '',
			'language'      => get_locale(),
			'breadcrumbs'   => $this->build_breadcrumbs(),
			'page_template' => get_page_template_slug( $this->post ) ?: 'default',
			'is_front_page' => ( (int) get_option( 'page_on_front' ) === $this->post->ID ),
		];
	}

	/**
	 * Get cleaned post body content with HTML stripped and word count limited.
	 *
	 * @since 1.0.0
	 *
	 * @return string Cleaned body text.
	 */
	private function get_clean_body() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "the_content" is a WP core filter; we apply it to render post content.
		$content = apply_filters( 'the_content', $this->post->post_content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		$words = explode( ' ', $content );

		if ( count( $words ) > self::MAX_WORDS ) {
			$words   = array_slice( $words, 0, self::MAX_WORDS );
			$content = implode( ' ', $words ) . '...';
		}

		return $content;
	}

	/**
	 * Get the post excerpt or auto-generate from content.
	 *
	 * @since 1.0.0
	 *
	 * @return string Post excerpt.
	 */
	private function get_excerpt() {
		$excerpt = $this->post->post_excerpt
			? $this->post->post_excerpt
			: wp_trim_words( wp_strip_all_tags( $this->post->post_content ), 55 );

		$excerpt = html_entity_decode( $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$excerpt = preg_replace( '/\s+/', ' ', $excerpt );

		return trim( $excerpt );
	}

	/**
	 * Get featured image data including URL, alt text, and dimensions.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Image data array or null if no featured image.
	 */
	private function get_featured_image_data() {
		$thumbnail_id = get_post_thumbnail_id( $this->post );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $thumbnail_id, 'full' );

		if ( ! $image ) {
			return null;
		}

		return [
			'url'    => $image[0],
			'width'  => $image[1],
			'height' => $image[2],
			'alt'    => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
		];
	}

	private function get_term_names( $taxonomy ) {
		if ( ! $taxonomy ) {
			return [];
		}

		$terms = get_the_terms( $this->post, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return [];
		}

		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Get the primary category and tag taxonomy for the current post type.
	 *
	 * Falls back to 'category' / 'post_tag' for standard post types.
	 *
	 * @return array { category: string|null, tag: string|null }
	 */
	private function get_post_type_taxonomies() {
		$post_type  = $this->post->post_type;
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		$category = null;
		$tag      = null;

		// Standard post types.
		if ( isset( $taxonomies['category'] ) ) {
			$category = 'category';
		}
		if ( isset( $taxonomies['post_tag'] ) ) {
			$tag = 'post_tag';
		}

		// For CPTs, find the first hierarchical (category-like) and non-hierarchical (tag-like).
		if ( ! $category || ! $tag ) {
			foreach ( $taxonomies as $tax ) {
				if ( ! $tax->public ) {
					continue;
				}
				if ( ! $category && $tax->hierarchical ) {
					$category = $tax->name;
				} elseif ( ! $tag && ! $tax->hierarchical ) {
					$tag = $tax->name;
				}

				if ( $category && $tag ) {
					break;
				}
			}
		}

		return [
			'category' => $category,
			'tag'      => $tag,
		];
	}

	private function get_relevant_custom_fields() {
		$all_meta = get_post_meta( $this->post->ID );
		$relevant = [];

		$skip_prefixes = [ '_', 'aise_', 'rank_math', 'yoast', '_elementor' ];
		$useful_keys   = [ 'price', 'rating', 'location', 'address', 'phone', 'email', 'duration', 'event_date', 'sku', 'brand' ];

		foreach ( $all_meta as $key => $values ) {
			$should_skip = false;

			foreach ( $skip_prefixes as $prefix ) {
				if ( 0 === strpos( $key, $prefix ) ) {
					$should_skip = true;
					break;
				}
			}

			if ( $should_skip ) {
				continue;
			}

			foreach ( $useful_keys as $useful ) {
				if ( false !== stripos( $key, $useful ) ) {
					$relevant[ $key ] = maybe_unserialize( $values[0] );
					break;
				}
			}
		}

		return $relevant;
	}

	private function detect_reviews() {
		$rating = get_post_meta( $this->post->ID, 'rating', true )
			?: get_post_meta( $this->post->ID, '_rating', true );

		if ( $rating ) {
			return true;
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $this->post->ID );

			if ( $product && $product->get_review_count() > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract rating data for RTCL Classified Listing posts.
	 *
	 * Checks two sources:
	 * 1. RTCL native ratings (stored in _rtcl_* post meta)
	 * 2. Review-schema plugin's own ratings (via ReviewFns)
	 *
	 * @return array|null Rating data or null if no ratings exist.
	 */
	private function extract_rtcl_rating_data() {
		$post_id = $this->post->ID;

		// Source 1: Review-schema plugin's own ratings.
		if ( Functions::isEnableReviewByPostType( $this->post->post_type ) ) {
			$avg   = ReviewFns::getAvgRatings( $post_id, true );
			$total = ReviewFns::getTotalRatings( $post_id );

			if ( $avg && $total ) {
				return [
					'average_rating' => (float) $avg,
					'review_count'   => (int) $total,
					'rating_count'   => (int) $total,
				];
			}
		} else {
			// Source 2: RTCL native ratings.
			$average_rating = (float) get_post_meta( $post_id, '_rtcl_average_rating', true );
			$review_count   = (int) get_post_meta( $post_id, '_rtcl_review_count', true );
			$rating_count   = get_post_meta( $post_id, '_rtcl_rating_count', true );
			$total_ratings  = is_array( $rating_count ) ? (int) array_sum( $rating_count ) : $review_count;

			if ( $average_rating > 0 && $review_count > 0 ) {
				return [
					'average_rating' => $average_rating,
					'review_count'   => $review_count,
					'rating_count'   => $total_ratings,
				];
			}
		}

		return null;
	}

	/**
	 * Extract Tutor LMS course data for schema generation.
	 *
	 * @return array|null Course data or null if not a Tutor course.
	 */
	public function extract_tutor_course_data() {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return null;
		}

		$post_id = $this->post->ID;
		$utils   = tutor_utils();

		// Duration.
		$duration = get_post_meta( $post_id, '_course_duration', true );

		// Level.
		$level = get_post_meta( $post_id, '_tutor_course_level', true );

		// Price.
		$price_type = get_post_meta( $post_id, '_tutor_course_price_type', true );
		$price      = '';
		$currency   = '';

		if ( 'paid' === $price_type ) {
			$product_id = get_post_meta( $post_id, '_tutor_course_product_id', true );

			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$product  = wc_get_product( $product_id );
				$price    = $product ? $product->get_price() : '';
				$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
			}

			if ( ! $price ) {
				$price = get_post_meta( $post_id, 'tutor_course_price', true );
			}
		}

		// Instructor.
		$instructors = $utils->get_instructors_by_course( $post_id );
		$instructor  = [];

		if ( ! empty( $instructors ) && is_array( $instructors ) ) {
			$inst = is_object( $instructors[0] ) ? $instructors[0] : null;

			if ( $inst ) {
				$instructor = [
					'name' => $inst->display_name ?? '',
					'url'  => get_author_posts_url( $inst->ID ?? 0 ),
				];
			}
		}

		// Rating — check review-schema's own ratings first, then Tutor native.
		$rating = [];

		if ( Functions::isEnableReviewByPostType( $this->post->post_type ) ) {
			$avg   = ReviewFns::getAvgRatings( $post_id, true );
			$total = ReviewFns::getTotalRatings( $post_id );

			if ( $avg && $total ) {
				$rating = [
					'average_rating' => (float) $avg,
					'review_count'   => (int) $total,
					'rating_count'   => (int) $total,
				];
			}
		} else {
			$rating_data = $utils->get_course_rating( $post_id );

			if ( $rating_data && ! empty( $rating_data->rating_avg ) && $rating_data->rating_avg > 0 ) {
				$rating = [
					'average_rating' => (float) $rating_data->rating_avg,
					'rating_count'   => (int) ( $rating_data->rating_count ?? 0 ),
				];
			}
		}

		// Enrolled students.
		$enrolled = method_exists( $utils, 'count_enrolled_users_by_course' )
			? (int) $utils->count_enrolled_users_by_course( $post_id )
			: 0;

		// Course benefits / requirements.
		$benefits     = get_post_meta( $post_id, '_tutor_course_benefits', true );
		$requirements = get_post_meta( $post_id, '_tutor_course_requirements', true );

		return array_filter(
			[
				'duration'     => $duration,
				'level'        => $level,
				'price_type'   => $price_type,
				'price'        => $price,
				'currency'     => $currency,
				'instructor'   => $instructor,
				'rating'       => $rating,
				'enrolled'     => $enrolled,
				'benefits'     => $benefits,
				'requirements' => $requirements,
			]
		);
	}

	/**
	 * Extract LearnPress course data for schema generation.
	 *
	 * @return array|null Course data or null if not a LearnPress course.
	 */
	public function extract_learnpress_course_data() {
		if ( ! function_exists( 'learn_press_get_course' ) ) {
			return null;
		}

		$post_id = $this->post->ID;

		// Price.
		$price    = '';
		$currency = '';

		$lp_course = learn_press_get_course( $post_id );

		if ( $lp_course && method_exists( $lp_course, 'get_price' ) ) {
			$price = $lp_course->get_price();
		}

		if ( ! $price ) {
			$price = get_post_meta( $post_id, '_lp_regular_price', true )
				?: get_post_meta( $post_id, '_lp_price', true );
		}

		$price_type = ( $price && (float) $price > 0 ) ? 'paid' : 'free';

		if ( 'paid' === $price_type ) {
			$currency = function_exists( 'learn_press_get_currency' ) ? learn_press_get_currency() : 'USD';
		}

		$sale_price = get_post_meta( $post_id, '_lp_sale_price', true );

		// Level.
		$level = get_post_meta( $post_id, '_lp_level', true );

		// Duration.
		$duration = get_post_meta( $post_id, '_lp_duration', true );

		// Instructor.
		$author_id  = $this->post->post_author;
		$instructor = [];

		if ( $author_id ) {
			$instructor = [
				'name' => get_the_author_meta( 'display_name', $author_id ),
				'url'  => get_author_posts_url( $author_id ),
			];
		}

		// Rating — check review-schema's own ratings first, then LearnPress native.
		$rating = [];

		if ( Functions::isEnableReviewByPostType( $this->post->post_type ) ) {
			$avg   = ReviewFns::getAvgRatings( $post_id, true );
			$total = ReviewFns::getTotalRatings( $post_id );

			if ( $avg && $total ) {
				$rating = [
					'average_rating' => (float) $avg,
					'review_count'   => (int) $total,
					'rating_count'   => (int) $total,
				];
			}
		} else {
			// Fallback to LearnPress native ratings (learnpress-course-review plugin).
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating native ratings via JOIN; caching is not applicable per-request.
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(cm.meta_value) AS rating_count, AVG(cm.meta_value) AS rating_avg
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
					WHERE c.comment_post_ID = %d
					AND c.comment_approved = 1
					AND cm.meta_key = '_lpr_rating'",
					$post_id
				)
			);

			if ( $result && $result->rating_count > 0 && (float) $result->rating_avg > 0 ) {
				$rating = [
					'average_rating' => round( (float) $result->rating_avg, 2 ),
					'review_count'   => (int) $result->rating_count,
					'rating_count'   => (int) $result->rating_count,
				];
			}
		}

		return array_filter(
			[
				'duration'   => $duration,
				'level'      => $level,
				'price_type' => $price_type,
				'price'      => $price,
				'sale_price' => $sale_price,
				'currency'   => $currency,
				'instructor' => $instructor,
				'rating'     => $rating,
			]
		);
	}

	private function extract_embedded_media() {
		$content = $this->post->post_content;
		$media   = [];

		if ( preg_match_all( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $vid_id ) {
				$media[] = [
					'type'     => 'video',
					'platform' => 'youtube',
					'url'      => "https://www.youtube.com/watch?v={$vid_id}",
				];
			}
		}

		if ( preg_match_all( '/vimeo\.com\/(\d+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $vid_id ) {
				$media[] = [
					'type'     => 'video',
					'platform' => 'vimeo',
					'url'      => "https://vimeo.com/{$vid_id}",
				];
			}
		}

		if ( preg_match_all( '/<audio[^>]*src=["\']([^"\']+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$media[] = [
					'type' => 'audio',
					'url'  => $url,
				];
			}
		}

		return $media;
	}

	private function extract_heading_hierarchy( $content ) {
		$headings = [];

		if ( preg_match_all( '/<h([2-6])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headings[] = [
					'level' => (int) $match[1],
					'text'  => wp_strip_all_tags( $match[2] ),
				];
			}
		}

		return $headings;
	}

	private function detect_faq_patterns( $content ) {
		$faqs = [];

		$pattern = '/<h[2-4][^>]*>(.*?(?:what|how|why|when|where|who|which|can|do|does|is|are|will|should|\?)[^<]*)<\/h[2-4]>\s*<p>(.*?)<\/p>/is';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$faqs[] = [
					'question' => wp_strip_all_tags( $match[1] ),
					'answer'   => wp_strip_all_tags( $match[2] ),
				];
			}
		}

		return $faqs;
	}

	/**
	 * Check if the post content contains an rtrs/faq block with schema enabled.
	 *
	 * @param string $content Post content.
	 *
	 * @return bool
	 */
	private function has_faq_block( $content ) {
		$blocks = parse_blocks( $content );

		return $this->check_blocks_for_faq( $blocks );
	}

	/**
	 * Recursively check parsed blocks for rtrs/faq with enableSchema.
	 *
	 * @param array $blocks Parsed block array.
	 *
	 * @return bool
	 */
	/**
	 * Check if the post has existing FAQ data in the dedicated meta key.
	 *
	 * The _rtrs_faqpage_data meta stores FAQ Q&A pairs for the classic editor.
	 * When data exists, FAQPage schema is already handled by FaqPageFrontend.
	 *
	 * @return bool
	 */
	private function has_faq_meta() {
		$faq_data = get_post_meta( $this->post->ID, '_rtrs_faqpage_data', true );

		if ( empty( $faq_data ) || ! is_array( $faq_data ) ) {
			return false;
		}

		// Check that at least one entry has a non-empty question.
		foreach ( $faq_data as $item ) {
			if ( ! empty( $item['question'] ) ) {
				return true;
			}
		}

		return false;
	}

	private function check_blocks_for_faq( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'rtrs/faq' === $block['blockName'] ) {
				$enable_schema = $block['attrs']['enableSchema'] ?? true;

				if ( $enable_schema ) {
					return true;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && 'rtrs/faq' !== $block['blockName'] ) {
				if ( $this->check_blocks_for_faq( $block['innerBlocks'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function detect_howto_patterns( $content ) {
		$steps = [];

		if ( preg_match_all( '/step\s*(\d+)\s*[:\-–]\s*(.+?)(?=step\s*\d+|$)/is', wp_strip_all_tags( $content ), $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$steps[] = [
					'position' => (int) $match[1],
					'text'     => trim( $match[2] ),
				];
			}
		}

		if ( empty( $steps ) && preg_match_all( '/<ol[^>]*>(.*?)<\/ol>/is', $content, $ol_matches ) ) {
			$pos = 1;

			foreach ( $ol_matches[1] as $ol_html ) {
				if ( preg_match_all( '/<li>(.*?)<\/li>/is', $ol_html, $li_matches ) ) {
					foreach ( $li_matches[1] as $item ) {
						$steps[] = [
							'position' => $pos++,
							'text'     => wp_strip_all_tags( $item ),
						];
					}
				}
			}
		}

		return $steps;
	}

	private function detect_recipe_markers( $content ) {
		$plain   = strtolower( wp_strip_all_tags( $content ) );
		$markers = [ 'ingredient', 'tablespoon', 'teaspoon', 'cup of', 'preheat', 'bake at', 'cook time', 'prep time', 'servings' ];

		$found = 0;

		foreach ( $markers as $marker ) {
			if ( false !== strpos( $plain, $marker ) ) {
				$found++;
			}
		}

		return $found >= 3;
	}

	private function detect_event_markers( $content ) {
		$plain   = strtolower( wp_strip_all_tags( $content ) );
		$markers = [ 'event date', 'start time', 'end time', 'venue', 'ticket', 'register', 'registration', 'rsvp', 'attend' ];

		$found = 0;

		foreach ( $markers as $marker ) {
			if ( false !== strpos( $plain, $marker ) ) {
				$found++;
			}
		}

		return $found >= 2;
	}

	private function detect_product_markers() {
		/** WooCommerce product. */
		if ( 'product' === $this->post->post_type ) {
			return true;
		}

		/** Easy Digital Downloads. */
		if ( 'download' === $this->post->post_type ) {
			return true;
		}

		/** FluentCart product. */
		if ( 'fluent-products' === $this->post->post_type ) {
			return true;
		}

		/** SureCart product. */
		if ( 'sc_product' === $this->post->post_type ) {
			return true;
		}

		$price = get_post_meta( $this->post->ID, 'price', true )
			?: get_post_meta( $this->post->ID, '_price', true )
			?: get_post_meta( $this->post->ID, 'edd_price', true );

		return ! empty( $price );
	}

	private function count_internal_links( $content ) {
		$home_url = home_url();
		$count    = 0;

		if ( preg_match_all( '/href=["\']([^"\']+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( 0 === strpos( $url, $home_url ) || 0 === strpos( $url, '/' ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	private function count_external_links( $content ) {
		$home_url = home_url();
		$count    = 0;

		if ( preg_match_all( '/href=["\']([^"\']+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( 0 === strpos( $url, 'http' ) && 0 !== strpos( $url, $home_url ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	public function extract_woocommerce_product_data() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $this->post->ID );

		if ( ! $product ) {
			return null;
		}

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

		$pricing = array_filter(
			[
				'currency'      => $currency,
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'on_sale'       => $product->is_on_sale(),
			]
		);

		$sale_to = $product->get_date_on_sale_to();

		if ( $sale_to ) {
			$pricing['sale_date_to'] = $sale_to->date( 'c' );
		}

		$variation_count = 0;

		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_prices' ) ) {
			$prices = $product->get_variation_prices( true );

			if ( ! empty( $prices['price'] ) ) {
				$pricing['min_price'] = min( $prices['price'] );
				$pricing['max_price'] = max( $prices['price'] );
			}

			$variation_count = count( $product->get_children() );
		}

		$stock_status     = $product->get_stock_status();
		$availability_map = [
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/BackOrder',
		];

		$sku = $product->get_sku();
		// Fallback to product ID if SKU is not set.
		if ( empty( $sku ) ) {
			$sku = (string) $product->get_id();
		}
		$gtin = method_exists( $product, 'get_global_unique_id' ) ? $product->get_global_unique_id() : '';

		$brand            = '';
		$brand_taxonomies = [ 'product_brand', 'pwb-brand', 'pa_brand' ];

		foreach ( $brand_taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = get_the_terms( $this->post->ID, $tax );

				if ( $terms && ! is_wp_error( $terms ) ) {
					$brand = $terms[0]->name;
					break;
				}
			}
		}

		$attributes = [];

		foreach ( $product->get_attributes() as $attr ) {
			if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
				$name   = wc_attribute_label( $attr->get_name() );
				$values = $attr->is_taxonomy()
					? wc_get_product_terms( $this->post->ID, $attr->get_name(), [ 'fields' => 'names' ] )
					: $attr->get_options();

				$attributes[ $name ] = is_array( $values ) ? implode( ', ', $values ) : (string) $values;
			}
		}

		$categories = [];
		$cat_terms  = get_the_terms( $this->post->ID, 'product_cat' );

		if ( $cat_terms && ! is_wp_error( $cat_terms ) ) {
			foreach ( $cat_terms as $term ) {
				$categories[] = $term->name;
			}
		}

		$reviews = [];

		if ( $product->get_reviews_allowed() ) {
			$reviews = [
				'average_rating' => (float) $product->get_average_rating(),
				'review_count'   => (int) $product->get_review_count(),
				'rating_counts'  => $product->get_rating_counts(),
			];

			$review_comments = get_comments(
				[
					'post_id'  => $this->post->ID,
					'type'     => 'review',
					'status'   => 'approve',
					'number'   => 2,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary meta query for plugin feature.
					'meta_key' => 'rating',
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
				]
			);

			$review_items = [];

			foreach ( $review_comments as $comment ) {
				$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );

				$review_items[] = array_filter(
					[
						'author' => $comment->comment_author,
						'date'   => get_comment_date( 'c', $comment ),
						'rating' => $rating ?: null,
						'text'   => wp_strip_all_tags( $comment->comment_content ),
					]
				);
			}

			if ( ! empty( $review_items ) ) {
				$reviews['items'] = $review_items;
			}
		}

		$gallery = [];

		foreach ( $product->get_gallery_image_ids() as $img_id ) {
			$src = wp_get_attachment_image_src( $img_id, 'full' );

			if ( $src ) {
				$gallery[] = [
					'url'    => $src[0],
					'width'  => $src[1],
					'height' => $src[2],
				];
			}
		}

		$weight     = $product->get_weight();
		$dimensions = array_filter(
			[
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			]
		);

		$weight_unit = function_exists( 'get_option' ) ? get_option( 'woocommerce_weight_unit', 'kg' ) : 'kg';
		$dim_unit    = function_exists( 'get_option' ) ? get_option( 'woocommerce_dimension_unit', 'cm' ) : 'cm';

		$prices_include_tax = 'yes' === get_option( 'woocommerce_prices_include_tax', 'no' );

		return array_filter(
			[
				'product_type'       => $product->get_type(),
				'pricing'            => $pricing,
				'stock_status'       => $stock_status,
				'availability'       => $availability_map[ $stock_status ] ?? '',
				'sku'                => $sku,
				'gtin'               => $gtin,
				'brand'              => $brand,
				'categories'         => $categories,
				'attributes'         => $attributes,
				'reviews'            => $reviews,
				'gallery'            => $gallery,
				'weight'             => $weight ? $weight . ' ' . $weight_unit : '',
				'dimensions'         => ! empty( $dimensions ) ? $dimensions : null,
				'dimension_unit'     => ! empty( $dimensions ) ? $dim_unit : '',
				'is_virtual'         => $product->is_virtual(),
				'is_downloadable'    => $product->is_downloadable(),
				'total_sales'        => (int) $product->get_total_sales(),
				'variation_count'    => $variation_count,
				'variations'         => $this->extract_wc_variations( $product, $currency, $availability_map ),
				'prices_include_tax' => $prices_include_tax,
			]
		);
	}

	/**
	 * Extract WooCommerce product variation details for ProductGroup schema.
	 *
	 * Only extracts when review-schema-pro is active.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Product $product          The parent product.
	 * @param string      $currency         Store currency code.
	 * @param array       $availability_map Stock status to schema.org URL map.
	 *
	 * @return array|null Variation data or null.
	 */
	public function extract_wc_variations( $product, $currency, $availability_map ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return null;
		}

		$children  = $product->get_children();
		$varies_by = [];
		$variants  = [];

		foreach ( $children as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}

			$attrs         = $variation->get_variation_attributes();
			$variant_attrs = [];

			foreach ( $attrs as $attr_key => $attr_value ) {
				$taxonomy = str_replace( 'attribute_', '', $attr_key );
				$label    = wc_attribute_label( $taxonomy, $product );

				if ( $attr_value && taxonomy_exists( $taxonomy ) ) {
					$term       = get_term_by( 'slug', $attr_value, $taxonomy );
					$attr_value = $term ? $term->name : $attr_value;
				}

				$variant_attrs[ $label ] = $attr_value ?: __( 'Any', 'review-schema' );

				if ( ! in_array( $label, $varies_by, true ) ) {
					$varies_by[] = $label;
				}
			}

			$stock  = $variation->get_stock_status();
			$img_id = $variation->get_image_id();
			$image  = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';

			$variants[] = array_filter(
				[
					'variation_id'  => $variation_id,
					'sku'           => $variation->get_sku(),
					'name'          => $variation->get_name(),
					'description'   => $variation->get_description(),
					'attributes'    => $variant_attrs,
					'price'         => $variation->get_price(),
					'regular_price' => $variation->get_regular_price(),
					'sale_price'    => $variation->get_sale_price(),
					'currency'      => $currency,
					'stock_status'  => $stock,
					'availability'  => $availability_map[ $stock ] ?? '',
					'image'         => $image,
				]
			);
		}

		if ( empty( $variants ) ) {
			return null;
		}

		return [
			'varies_by' => $varies_by,
			'items'     => $variants,
		];
	}

	/**
	 * Extract Easy Digital Downloads product data.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null EDD product data or null.
	 */
	public function extract_edd_product_data() {
		if ( ! function_exists( 'edd_get_download' ) ) {
			return null;
		}

		if ( 'download' !== $this->post->post_type ) {
			return null;
		}

		$download = edd_get_download( $this->post->ID );

		if ( ! $download ) {
			return null;
		}

		$currency = function_exists( 'edd_get_currency' )
			? edd_get_currency()
			: ( function_exists( 'edd_get_option' ) ? edd_get_option( 'currency', 'USD' ) : 'USD' );
		$price    = $download->get_price();

		$categories = [];
		$cat_terms  = get_the_terms( $this->post->ID, 'download_category' );

		if ( $cat_terms && ! is_wp_error( $cat_terms ) ) {
			foreach ( $cat_terms as $term ) {
				$categories[] = $term->name;
			}
		}

		$tags      = [];
		$tag_terms = get_the_terms( $this->post->ID, 'download_tag' );

		if ( $tag_terms && ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = $term->name;
			}
		}

		$sku = $download->get_sku();
		// Fallback to download ID if SKU is not set.
		if ( empty( $sku ) ) {
			$sku = (string) $download->get_ID();
		}

		$data = [
			'product_type' => $download->has_variable_prices() ? 'variable' : 'simple',
			'pricing'      => [
				'currency' => $currency,
				'price'    => $price,
			],
			'sku'          => $sku,
			'categories'   => $categories,
			'tags'         => $tags,
			'sales'        => (int) $download->get_sales(),
			'is_free'      => ! (bool) $price,
			'is_bundled'   => $download->is_bundled_download(),
		];

		/** Include variable pricing details when pro is active. */
		$variations = $this->extract_edd_variations( $download, $currency );

		if ( $variations ) {
			$data['variations'] = $variations;
		}

		return array_filter( $data );
	}

	/**
	 * Extract EDD variable pricing tiers for product offers schema.
	 *
	 * EDD "variations" are pricing tiers (e.g. Personal, Business, Developer),
	 * not actual product variants — they become multiple Offer objects.
	 *
	 * @since 1.0.0
	 *
	 * @param \EDD_Download $download The EDD download object.
	 * @param string        $currency Store currency code.
	 *
	 * @return array|null Variable pricing data or null.
	 */
	public function extract_edd_variations( $download, $currency ) {
		if ( ! $download->has_variable_prices() ) {
			return null;
		}

		$prices   = $download->get_prices();
		$variants = [];

		if ( empty( $prices ) || ! is_array( $prices ) ) {
			return null;
		}

		foreach ( $prices as $price_id => $price_option ) {
			$name   = $price_option['name'] ?? '';
			$amount = isset( $price_option['amount'] ) ? $price_option['amount'] : 0;

			$variants[] = array_filter(
				[
					'price_id' => $price_id,
					'name'     => $name,
					'price'    => $amount,
					'currency' => $currency,
				]
			);
		}

		if ( empty( $variants ) ) {
			return null;
		}

		return [
			'varies_by' => [ 'price_option' ],
			'items'     => $variants,
		];
	}

	private function build_breadcrumbs() {
		$crumbs = [
			[
				'name' => __( 'Home', 'review-schema' ),
				'url'  => home_url( '/' ),
			],
		];

		$post_type = $this->post->post_type;

		if ( 'product' === $post_type && function_exists( 'wc_get_page_id' ) ) {
			$crumbs = $this->build_product_breadcrumbs( $crumbs );
		} elseif ( 'page' === $post_type ) {
			$crumbs = $this->build_page_breadcrumbs( $crumbs );
		} elseif ( 'post' === $post_type ) {
			$crumbs = $this->build_post_breadcrumbs( $crumbs );
		} else {
			$crumbs = $this->build_cpt_breadcrumbs( $crumbs );
		}

		$crumbs[] = [
			'name' => get_the_title( $this->post ),
		];

		return $crumbs;
	}

	private function build_product_breadcrumbs( $crumbs ) {
		$shop_id = wc_get_page_id( 'shop' );

		if ( $shop_id > 0 ) {
			$crumbs[] = [
				'name' => get_the_title( $shop_id ),
				'url'  => get_permalink( $shop_id ),
			];
		}

		$terms = get_the_terms( $this->post->ID, 'product_cat' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return $crumbs;
		}

		$deepest   = $terms[0];
		$max_depth = 0;

		foreach ( $terms as $term ) {
			$ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
			$depth     = count( $ancestors );

			if ( $depth > $max_depth ) {
				$max_depth = $depth;
				$deepest   = $term;
			}
		}

		$ancestors = get_ancestors( $deepest->term_id, 'product_cat', 'taxonomy' );
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );

			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$crumbs[] = [
					'name' => $ancestor->name,
					'url'  => get_term_link( $ancestor ),
				];
			}
		}

		$crumbs[] = [
			'name' => $deepest->name,
			'url'  => get_term_link( $deepest ),
		];

		return $crumbs;
	}

	private function build_page_breadcrumbs( $crumbs ) {
		if ( ! $this->post->post_parent ) {
			return $crumbs;
		}

		$ancestors = get_post_ancestors( $this->post );
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor_id ) {
			$crumbs[] = [
				'name' => get_the_title( $ancestor_id ),
				'url'  => get_permalink( $ancestor_id ),
			];
		}

		return $crumbs;
	}

	private function build_post_breadcrumbs( $crumbs ) {
		$categories = get_the_category( $this->post->ID );

		if ( empty( $categories ) ) {
			return $crumbs;
		}

		$deepest   = $categories[0];
		$max_depth = 0;

		foreach ( $categories as $cat ) {
			$ancestors = get_ancestors( $cat->term_id, 'category', 'taxonomy' );
			$depth     = count( $ancestors );

			if ( $depth > $max_depth ) {
				$max_depth = $depth;
				$deepest   = $cat;
			}
		}

		$ancestors = get_ancestors( $deepest->term_id, 'category', 'taxonomy' );
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'category' );

			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$crumbs[] = [
					'name' => $ancestor->name,
					'url'  => get_category_link( $ancestor ),
				];
			}
		}

		$crumbs[] = [
			'name' => $deepest->name,
			'url'  => get_category_link( $deepest ),
		];

		return $crumbs;
	}

	private function build_cpt_breadcrumbs( $crumbs ) {
		$post_type     = $this->post->post_type;
		$post_type_obj = get_post_type_object( $post_type );

		if ( $post_type_obj && $post_type_obj->has_archive ) {
			$archive_url = get_post_type_archive_link( $post_type );

			if ( $archive_url ) {
				$crumbs[] = [
					'name' => $post_type_obj->labels->name ?? $post_type_obj->label,
					'url'  => $archive_url,
				];
			}
		}

		$taxonomy = $this->get_primary_taxonomy( $post_type );

		if ( ! $taxonomy ) {
			return $crumbs;
		}

		$terms = get_the_terms( $this->post->ID, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return $crumbs;
		}

		$deepest   = $terms[0];
		$max_depth = 0;

		foreach ( $terms as $term ) {
			$ancestors = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );
			$depth     = count( $ancestors );

			if ( $depth > $max_depth ) {
				$max_depth = $depth;
				$deepest   = $term;
			}
		}

		$ancestors = get_ancestors( $deepest->term_id, $taxonomy, 'taxonomy' );
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );

			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$crumbs[] = [
					'name' => $ancestor->name,
					'url'  => get_term_link( $ancestor ),
				];
			}
		}

		$crumbs[] = [
			'name' => $deepest->name,
			'url'  => get_term_link( $deepest ),
		];

		return $crumbs;
	}

	private function get_primary_taxonomy( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		foreach ( $taxonomies as $tax ) {
			if ( $tax->public && $tax->hierarchical ) {
				return $tax->name;
			}
		}

		foreach ( $taxonomies as $tax ) {
			if ( $tax->public ) {
				return $tax->name;
			}
		}

		return null;
	}

	/**
	 * Build a compact summary payload for the classification step.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array|\WP_Error Compact payload or WP_Error.
	 */
	public function extract_for_classification( $post_id ) {
		$this->post = get_post( $post_id );

		if ( ! $this->post ) {
			return new \WP_Error( 'invalid_post', __( 'Post not found.', 'review-schema' ) );
		}

		$taxonomies = $this->get_post_type_taxonomies();

		return [
			'title'         => get_the_title( $this->post ),
			'post_type'     => $this->post->post_type,
			'excerpt'       => $this->get_excerpt(),
			'categories'    => $this->get_term_names( $taxonomies['category'] ),
			'headings'      => $this->extract_heading_hierarchy( $this->post->post_content ),
			'has_faq'       => ! empty( $this->detect_faq_patterns( $this->post->post_content ) ),
			'has_faq_block' => $this->has_faq_block( $this->post->post_content ),
			'has_howto'     => ! empty( $this->detect_howto_patterns( $this->post->post_content ) ),
			'has_recipe'    => $this->detect_recipe_markers( $this->post->post_content ),
			'has_event'     => $this->detect_event_markers( $this->post->post_content ),
			'has_product'   => $this->detect_product_markers(),
			'has_video'     => ! empty( $this->extract_embedded_media() ),
			'word_count'    => str_word_count( wp_strip_all_tags( $this->post->post_content ) ),
		];
	}
}
