<?php
namespace Rtrs\Shortcodes;

use Rtrs\Helpers\Functions;
use Rtrs\Models\Review;

/**
 * ReviewAvgRating
 */
class ReviewAvgRating {
	/**
	 * @param array $atts
	 * @return void
	 */
	public static function star_output( $atts ) {
		$sc_meta   = shortcode_atts(
			[
				'post_id'            => get_the_ID(),
				'hide_for_no_rating' => 'show', // hide, show
			],
			$atts
		);
		$post_id   = $sc_meta['post_id'] ?? get_the_ID();
		$no_rating = $sc_meta['hide_for_no_rating'] ?? 'show';
		// Get the average ratings for the specified post.
		$avg_rating = Review::getAvgRatings( $post_id );
		if ( 'hide' === strtolower( $no_rating ) ) {
			return;
		}
		echo '<div class="shortcode-rtrs-rating-icon rtrs-rating-icon-global">';
		echo Functions::review_stars( $avg_rating );
		echo '</div>';
	}

	/**
	 * @param array $atts
	 * @return void
	 */
	public static function avg_rating_count( $atts ) {
		$sc_meta   = shortcode_atts(
			[
				'post_id'            => get_the_ID(),
				'hide_for_no_rating' => 'show', // hide, show.
			],
			$atts
		);
		$post_id   = $sc_meta['post_id'] ?? get_the_ID();
		$no_rating = $sc_meta['hide_for_no_rating'] ?? 'show';
		// Get the average ratings for the specified post.
		$avg_rating = Review::getAvgRatings( $post_id );
		if ( 'hide' === strtolower( $no_rating ) ) {
			return;
		}
		echo '<div class="shortcode-rtrs-rating-count">';
		echo esc_html( $avg_rating );
		echo '</div>';
	}
}
