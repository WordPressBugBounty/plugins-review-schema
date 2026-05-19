<?php
/**
 * SERP Preview data extraction helper.
 *
 * Extracts and normalizes schema data into a structure
 * suitable for rendering a Google Search preview card.
 *
 * @package Rtrs\Modules\Schema\Helpers
 */

namespace Rtrs\Modules\Schema\Helpers;

use Rtrs\AI\AIInit;
use Rtrs\Modules\Schema\Models\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SerpPreviewHelper {

	/**
	 * Schema types that carry aggregate rating data.
	 *
	 * @var array
	 */
	private static $rating_types = [
		'Product',
		'Recipe',
		'LocalBusiness',
		'Restaurant',
		'Course',
		'Book',
		'Movie',
		'SoftwareApplication',
		'Service',
	];

	/**
	 * Extract SERP preview data for a given post.
	 *
	 * Tries AI schema first, then traditional schema, then basic post data.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Normalized SERP preview data.
	 */
	public static function extract( $post_id ) {
		$base = self::build_base_data( $post_id );

		// Path 1: AI schema (priority).
		$ai_data = self::extract_from_ai_schema( $post_id );
		if ( $ai_data ) {
			return array_merge( $base, $ai_data );
		}

		// Path 2: Schema model — uses header_schema_data() which produces
		// the complete JSON-LD output (custom + auto schemas with computed values).
		$model_data = self::extract_from_schema_model( $post_id );
		if ( $model_data ) {
			return array_merge( $base, $model_data );
		}

		// Path 3: Basic post data fallback.
		$base['schemaSource'] = 'post';

		return $base;
	}

	/**
	 * Build base SERP data from post metadata.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Base SERP data array.
	 */
	private static function build_base_data( $post_id ) {
		$post      = get_post( $post_id );
		$permalink = get_permalink( $post_id );
		$parsed    = wp_parse_url( $permalink );
		$host      = $parsed['host'] ?? '';
		$path      = trim( $parsed['path'] ?? '', '/' );

		$breadcrumb_parts = array_filter( explode( '/', $path ) );
		$display_url      = $host;
		if ( ! empty( $breadcrumb_parts ) ) {
			$display_url .= ' › ' . implode( ' › ', $breadcrumb_parts );
		}

		$description = '';
		if ( $post ) {
			$description = $post->post_excerpt;
			if ( empty( $description ) ) {
				$description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
			}
		}

		return [
			'title'        => get_the_title( $post_id ),
			'description'  => $description,
			'url'          => $permalink,
			'displayUrl'   => $display_url,
			'favicon'      => get_site_icon_url( 16 ),
			'siteName'     => get_bloginfo( 'name' ),
			'schemaType'   => '',
			'schemaSource' => '',
			'richElements' => [],
		];
	}

	/**
	 * Extract SERP data from AI-generated schema (post meta).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null Null if no AI schema found.
	 */
	private static function extract_from_ai_schema( $post_id ) {
		if ( ! class_exists( '\Rtrs\AI\AIInit' ) ) {
			return null;
		}

		$raw = get_post_meta( $post_id, AIInit::META_KEY, true );
		if ( empty( $raw ) ) {
			return null;
		}

		$schemas = AIInit::normalizeSchemaData( $raw );
		if ( empty( $schemas ) ) {
			return null;
		}

		$schema_type   = get_post_meta( $post_id, AIInit::TYPE_META_KEY, true );
		$rich_elements = [];

		foreach ( $schemas as $node ) {
			if ( ! isset( $node['@type'] ) ) {
				continue;
			}

			$type          = self::normalize_type( $node['@type'] );
			$elements      = self::extract_rich_elements( $type, $node );
			$rich_elements = array_merge( $rich_elements, $elements );

			// Use first content schema type if type meta is empty.
			if ( empty( $schema_type ) && ! in_array( $type, [ 'WebPage', 'WebSite', 'BreadcrumbList', 'Organization', 'Person' ], true ) ) {
				$schema_type = $type;
			}
		}

		return [
			'schemaType'   => $schema_type ?: 'WebPage',
			'schemaSource' => 'ai',
			'richElements' => $rich_elements,
		];
	}

	/**
	 * Extract SERP data using the Schema model (handles custom + auto schemas).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null Null if no schema data generated.
	 */
	private static function extract_from_schema_model( $post_id ) {
		$schema  = new Schema( $post_id );
		$json_ld = $schema->header_schema_data();

		if ( empty( $json_ld ) ) {
			return null;
		}

		// Strip <script> tags to get raw JSON.
		$json_str = $schema->remove_script_wrappers( $json_ld );
		$data     = json_decode( $json_str, true );

		if ( empty( $data ) || ! isset( $data['@graph'] ) || ! is_array( $data['@graph'] ) ) {
			return null;
		}

		$rich_elements = [];
		$schema_type   = '';
		$skip_types    = [ 'WebPage', 'WebSite', 'BreadcrumbList', 'Organization', 'Person', 'CollectionPage' ];

		foreach ( $data['@graph'] as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
				continue;
			}

			$type          = self::normalize_type( $node['@type'] );
			$elements      = self::extract_rich_elements( $type, $node );
			$rich_elements = array_merge( $rich_elements, $elements );

			if ( empty( $schema_type ) && ! in_array( $type, $skip_types, true ) ) {
				$schema_type = $type;
			}
		}

		if ( empty( $schema_type ) ) {
			return null;
		}

		return [
			'schemaType'   => $schema_type,
			'schemaSource' => 'schema',
			'richElements' => $rich_elements,
		];
	}

	/**
	 * Extract rich elements from a schema node based on its type.
	 *
	 * @param string $type Schema type.
	 * @param array  $node Schema node data.
	 *
	 * @return array Rich element data.
	 */
	private static function extract_rich_elements( $type, $node ) {
		$elements = [];

		// Rating (common across many types).
		if ( isset( $node['aggregateRating'] ) ) {
			$rating             = $node['aggregateRating'];
			$elements['rating'] = [
				'value' => floatval( $rating['ratingValue'] ?? 0 ),
				'count' => intval( $rating['reviewCount'] ?? $rating['ratingCount'] ?? 0 ),
				'best'  => floatval( $rating['bestRating'] ?? 5 ),
			];
		}

		// Price (Product, Service, etc.).
		if ( isset( $node['offers'] ) ) {
			$offers = $node['offers'];
			// Handle single offer or array of offers.
			if ( isset( $offers['@type'] ) ) {
				$offer = $offers;
			} elseif ( isset( $offers[0] ) ) {
				$offer = $offers[0];
			} else {
				$offer = $offers;
			}

			$availability = $offer['availability'] ?? '';
			$availability = str_replace( 'https://schema.org/', '', $availability );

			$elements['price'] = [
				'value'        => $offer['price'] ?? $offer['lowPrice'] ?? '',
				'currency'     => $offer['priceCurrency'] ?? '',
				'availability' => $availability,
			];
		}

		// FAQ items.
		if ( 'FAQPage' === $type && isset( $node['mainEntity'] ) ) {
			$items    = [];
			$faq_list = is_array( $node['mainEntity'] ) ? $node['mainEntity'] : [];
			foreach ( array_slice( $faq_list, 0, 3 ) as $faq ) {
				$question = $faq['name'] ?? '';
				$answer   = '';
				if ( isset( $faq['acceptedAnswer']['text'] ) ) {
					$answer = $faq['acceptedAnswer']['text'];
				}
				if ( $question ) {
					$items[] = [
						'question' => $question,
						'answer'   => $answer,
					];
				}
			}
			if ( ! empty( $items ) ) {
				$elements['faqItems'] = $items;
			}
		}

		// HowTo steps.
		if ( 'HowTo' === $type && isset( $node['step'] ) ) {
			$steps = [];
			foreach ( array_slice( (array) $node['step'], 0, 4 ) as $step ) {
				$steps[] = [
					'name' => $step['name'] ?? $step['text'] ?? '',
					'text' => $step['text'] ?? '',
				];
			}
			if ( ! empty( $steps ) ) {
				$elements['howToSteps'] = $steps;
			}
		}

		// Event.
		if ( 'Event' === $type || false !== strpos( $type, 'Event' ) ) {
			$location = '';
			if ( isset( $node['location']['name'] ) ) {
				$location = $node['location']['name'];
			} elseif ( isset( $node['location']['address']['addressLocality'] ) ) {
				$location = $node['location']['address']['addressLocality'];
			}

			$elements['event'] = [
				'startDate' => self::format_serp_date( $node['startDate'] ?? '' ),
				'location'  => $location,
			];
		}

		// Recipe.
		if ( 'Recipe' === $type ) {
			$elements['recipe'] = [
				'totalTime' => $node['totalTime'] ?? '',
				'calories'  => $node['nutrition']['calories'] ?? '',
			];
		}

		return $elements;
	}

	/**
	 * Normalize a schema @type value to a simple string.
	 *
	 * @param mixed $type The @type value (string or array).
	 *
	 * @return string Normalized type string.
	 */
	private static function normalize_type( $type ) {
		if ( is_array( $type ) ) {
			return $type[0] ?? 'Thing';
		}

		return (string) $type;
	}

	/**
	 * Format a date string for SERP display (e.g. "Mar 5, 2026").
	 *
	 * @param string $date_string Date string in any parseable format.
	 *
	 * @return string Formatted date or empty string.
	 */
	private static function format_serp_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

		$timestamp = strtotime( $date_string );
		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( 'M j, Y', $timestamp );
	}
}
