<?php

namespace Rtrs\Modules\Review\Admin\Meta;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewMeta {

	private $prefix = 'rtrs_';
	/**
	 * SingleTon
	 */
	use SingletonTrait;

	/**
	 * Retrieves the review fields for the current post type section.
	 *
	 * The method generates an array of review fields, including information about
	 * the total rating, average rating, best rating, and worst rating for the
	 * current post type. Filters are applied to allow modification of the review fields.
	 *
	 * @return array The filtered array of review fields containing type, label, doc, and data for each field.
	 */
	public function sectionReviewFields() {
		$post_type     = get_post_type();
		$review_fields = [
			[
				'type'  => 'info',
				'label' => esc_html__( 'Total rating', 'review-schema' ),
				'doc'   => esc_html__( 'Total rating of this', 'review-schema' ) . ' ' . $post_type,
				'data'  => 'total_rating',
			],
			[
				'type'  => 'info',
				'label' => esc_html__( 'Average rating', 'review-schema' ),
				'doc'   => esc_html__( 'Average rating of this', 'review-schema' ) . ' ' . $post_type,
				'data'  => 'avg_rating',
			],
			[
				'type'  => 'info',
				'label' => esc_html__( 'Best rating', 'review-schema' ),
				'doc'   => esc_html__( 'Best rating of this', 'review-schema' ) . ' ' . $post_type,
				'data'  => 'best_rating',
			],
			[
				'type'  => 'info',
				'label' => esc_html__( 'Worst rating', 'review-schema' ),
				'doc'   => esc_html__( 'Worst rating of this', 'review-schema' ) . ' ' . $post_type,
				'data'  => 'worst_rating',
			],
		];

		return apply_filters( 'rtrs_affiliate_review_fields', $review_fields );
	}
}
