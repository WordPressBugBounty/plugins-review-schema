<?php
/**
 * Dynamic AggregateRating Injector.
 *
 * Injects aggregateRating into AI-generated schemas at render time
 * using live review data, so ratings stay current even when the
 * schema was originally generated without reviews.
 *
 * Supports:
 * - Review Schema plugin's own review system
 * - WooCommerce native product reviews
 *
 * @package Rtrs\Modules\Schema\Hooks
 * @since   1.3.0
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\AI\SchemaExtractor;
use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AggregateRatingInjector {

	use SingletonTrait;

	/**
	 * Initialize hooks.
	 */
	private function __instance() {
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'inject_aggregate_rating' ], 8, 2 );
		add_filter( 'rtrs_schema_graph_data', [ $this, 'inject_aggregate_rating' ], 8, 2 );
	}

	/**
	 * Inject aggregateRating into content schemas that support it.
	 *
	 * Only injects when the schema does not already have a valid
	 * aggregateRating and the post has review ratings.
	 *
	 * @param array $schemas Schema graph list.
	 * @param int   $post_id Current post ID.
	 * @return array Modified schema graph list.
	 */
	public function inject_aggregate_rating( $schemas, $post_id ) {
		if ( empty( $schemas ) || ! is_array( $schemas ) || ! $post_id ) {
			return $schemas;
		}

		// Collect rating data once per request.
		static $rating_cache = [];

		if ( ! isset( $rating_cache[ $post_id ] ) ) {
			$rating_cache[ $post_id ] = $this->get_rating_data( $post_id );
		}

		if ( ! $rating_cache[ $post_id ] ) {
			return $schemas;
		}

		$supported_types = SchemaExtractor::AGGREGATE_RATING_TYPES;

		foreach ( $schemas as &$schema ) {
			$type = $schema['@type'] ?? '';

			if ( ! in_array( $type, $supported_types, true ) ) {
				continue;
			}

			// Skip if already has a valid aggregateRating.
			if ( ! empty( $schema['aggregateRating'] )
				&& is_array( $schema['aggregateRating'] )
				&& ! empty( $schema['aggregateRating']['ratingValue'] )
				&& (float) $schema['aggregateRating']['ratingValue'] > 0
			) {
				continue;
			}

			$schema['aggregateRating'] = $rating_cache[ $post_id ];
		}
		unset( $schema );

		return $schemas;
	}

	/**
	 * Get aggregateRating data from available sources.
	 *
	 * Priority:
	 * 1. WooCommerce native product reviews
	 * 2. Review Schema plugin's own review system
	 *
	 * @param int $post_id Post ID.
	 * @return array|false AggregateRating schema data or false.
	 */
	private function get_rating_data( $post_id ) {
		// 1. WooCommerce product reviews.
		$wc_rating = $this->get_woocommerce_rating( $post_id );
		if ( $wc_rating ) {
			return $wc_rating;
		}

		// 2. Review Schema plugin reviews.
		if ( Functions::isEnableReviewByPostType( get_post_type( $post_id ) ) ) {
			$avg   = ReviewFns::getAvgRatings( $post_id, true );
			$total = ReviewFns::getTotalRatings( $post_id );

			if ( $avg && $total ) {
				return [
					'@type'       => 'AggregateRating',
					'ratingValue' => round( (float) $avg, 1 ),
					'bestRating'  => 5,
					'worstRating' => 1,
					'ratingCount' => (int) $total,
					'reviewCount' => (int) $total,
				];
			}
		}

		return false;
	}

	/**
	 * Get rating data from WooCommerce product meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|false AggregateRating schema data or false.
	 */
	private function get_woocommerce_rating( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		if ( 'product' !== get_post_type( $post_id ) ) {
			return false;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return false;
		}

		$avg   = (float) $product->get_average_rating();
		$count = (int) $product->get_review_count();

		if ( $avg <= 0 || $count <= 0 ) {
			return false;
		}

		$rating_counts = $product->get_rating_counts();
		$rating_count  = array_sum( $rating_counts );

		return [
			'@type'       => 'AggregateRating',
			'ratingValue' => round( $avg, 1 ),
			'bestRating'  => 5,
			'worstRating' => 1,
			'ratingCount' => $rating_count ?: $count,
			'reviewCount' => $count,
		];
	}
}
