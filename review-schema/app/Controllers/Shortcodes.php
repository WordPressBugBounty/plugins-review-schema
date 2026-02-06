<?php

namespace Rtrs\Controllers;

use Rtrs\Models\Review;
use Rtrs\Helpers\Functions;
use Rtrs\Shortcodes\ReviewAvgRating;
use Rtrs\Shortcodes\ReviewSchema;

class Shortcodes {

	public static function init_short_code() {
		$shortcodes = [
			'rtrs-affiliate'            => __CLASS__ . '::review_schema',
			'rtrs-average-rating-stars' => __CLASS__ . '::average_stars',
			'rtrs-average-rating-count' => __CLASS__ . '::average_rating_count',
			'rtrs-review-list'          => __CLASS__ . '::review_list',
			'rtrs-review-form'          => __CLASS__ . '::review_form',
			'rtrs-review-summary'       => __CLASS__ . '::review_summary',
		];
		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( apply_filters( "{$shortcode}_shortcode_tag", $shortcode ), $function );
		}
	}

	/**
	 * @param $function
	 * @param $atts
	 * @param $wrapper
	 * @return false|string
	 */
	public static function shortcode_wrapper(
		$function,
		$atts = [],
		$wrapper = [
			'class'  => 'rtrs',
			'before' => null,
			'after'  => null,
		]
	) {
		ob_start();
        // @codingStandardsIgnoreStart
        echo empty($wrapper['before']) ? '<div class="' . esc_attr($wrapper['class']) . '">' : $wrapper['before'];
        call_user_func($function, $atts);
        echo empty($wrapper['after']) ? '</div>' : $wrapper['after'];

        // @codingStandardsIgnoreEnd

		return ob_get_clean();
	}

	/**
	 * All shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public static function review_schema( $atts ) {
		return self::shortcode_wrapper( [ ReviewSchema::class, 'output' ], $atts );
	}

	/**
	 * All shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public static function average_stars( $atts ) {
		return self::shortcode_wrapper( [ ReviewAvgRating::class, 'star_output' ], $atts );
	}

	/**
	 * All shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public static function average_rating_count( $atts ) {
		return self::shortcode_wrapper( [ ReviewAvgRating::class, 'avg_rating_count' ], $atts );
	}
	/**
	 * All shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public static function review_list( $atts ) {
		$p_meta       = Functions::getMetaByPostType( get_post_type() );
		$total_rating = Review::getTotalRatings( get_the_ID() );
		ob_start();
		if ( $total_rating ) {
			?>
		<div class="rtrs-sorting-bar">
			<h3 class="rtrs-sorting-title">
				<?php
				$review_title = esc_html(
					sprintf(
						_n( 'Reviewed by %d user', 'Reviewed by %d users', $total_rating, 'review-schema' ),
						$total_rating
					)
				);
				echo apply_filters( 'rtrs_review_sorting_title', $review_title, $total_rating, get_the_ID() );
				?>
			</h3>
			<div class="rtrs-sorting-select">
				<?php
				$filter = '1' == ( $p_meta['filter'][0] ?? '' );
				if ( $filter ) {
					$filter_option = isset( $p_meta['filter_option'] ) ? $p_meta['filter_option'] : '';
					?>
					<div>
						<label><i class="rtrs-sort"></i> <?php esc_html_e( 'Sort:', 'review-schema' ); ?></label>
						<select class="rtrs_review_filter rtrs-sort-filter" name="rtrs_review_sort_filter" data-type="sort">
							<option value="all"><?php esc_html_e( 'All Review', 'review-schema' ); ?></option>
							<?php if ( in_array( 'top_rated', $filter_option, true ) ) { ?>
								<option value="top_rated"><?php esc_html_e( 'Top Rated', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'low_rated', $filter_option, true ) ) { ?>
								<option value="low_rated"><?php esc_html_e( 'Low Rated', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'recommended', $filter_option, true ) ) { ?>
								<option value="recommended"><?php esc_html_e( 'Recommended', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'highlighted', $filter_option, true ) ) { ?>
								<option value="highlighted"><?php esc_html_e( 'Highlighted', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'latest_first', $filter_option, true ) ) { ?>
								<option value="latest_first"><?php esc_html_e( 'Latest First', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'oldest_first', $filter_option, true ) ) { ?>
								<option value="oldest_first"><?php esc_html_e( 'Oldest First', 'review-schema' ); ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } ?>
				<?php
				if ( $filter ) {
					?>
					<div>
						<label><i class="rtrs-filter"></i> <?php esc_html_e( 'Filter:', 'review-schema' ); ?></label>
						<select class="rtrs_review_filter" name="rtrs_review_rating_filter" data-type="rating">
							<option value=""><?php esc_html_e( 'All Star', 'review-schema' ); ?></option>
							<option value="5"><?php esc_html_e( '5 Star', 'review-schema' ); ?></option>
							<option value="4"><?php esc_html_e( '4 Star', 'review-schema' ); ?></option>
							<option value="3"><?php esc_html_e( '3 Star', 'review-schema' ); ?></option>
							<option value="2"><?php esc_html_e( '2 Star', 'review-schema' ); ?></option>
							<option value="1"><?php esc_html_e( '1 Star', 'review-schema' ); ?></option>
						</select>
					</div>
				<?php } ?>
			</div>
		</div>
	<?php } ?>
	<div class="rtrs-review-box">
			<ul class="rtrs-review-list">
				<?php
				do_action( 'rtrs_before_review_comments_list', $p_meta, get_the_ID() );
				$args     = [
					'post_id' => get_the_ID(),
                    'type'    => 'review',
					'status'  => 'approve', // Change this to the type of comments to be displayed.
				];
				$comments = get_comments( apply_filters( 'rtrs_comments_query_args', $args, $p_meta ) );
				wp_list_comments(
					[
						'style'      => 'li',
						'short_ping' => true,
						'callback'   => [ Review::class, 'comment_list' ],
					],
					$comments
				);
				?>
			</ul>
		</div>
		<?php
		$pagination_type = isset( $p_meta['pagination_type'] ) ? $p_meta['pagination_type'][0] : 'number';
		$per_page        = (int) get_option( 'comments_per_page', 10 );
		$max_pages       = (int) ceil( $total_rating / $per_page );
		if ( 'number' === $pagination_type || 'number-ajax' === $pagination_type ) {
			if ( $max_pages > 1 ) :
				?>
				<div class="rtrs-paginate rtrs-paginate-ajax" data-max="<?php echo esc_attr( $max_pages ); ?>">
					<?php
					paginate_comments_links(
						[
							'total'     => $max_pages,
							'prev_text' => '<i class="rtrs-angle-left"></i>',
							'next_text' => '<i class="rtrs-angle-right"></i>',
						]
					);
					?>
				</div>
			<?php endif; ?>
		<?php } elseif ( 'load-more' === $pagination_type ) { ?>
			<div class="rtrs-paginate rtrs-paginate-load-more rtrs-align-center">
				<a href="#" id="rtrs-load-more" data-current_page="1" data-max="<?php echo esc_attr( $max_pages ); ?>"><?php echo esc_html__( 'Load More', 'review-schema' ); ?></a>
			</div>
		<?php } elseif ( 'auto-scroll' === $pagination_type ) { ?>
			<div class="rtrs-paginate rtrs-paginate-onscroll" data-current_page="1" data-max="<?php echo esc_attr( $max_pages ); ?>"></div>
		<?php } ?>
		<?php
		return ob_get_clean();
	}
	/**
	 * All shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public static function review_form( $atts ) {
		ob_start();
		$get_post_type = get_post_type( get_the_ID() );
		$p_meta        = Functions::getMetaByPostType( get_post_type() );
		$parent_class  = isset( $p_meta['parent_class'] ) ? $p_meta['parent_class'][0] : '';
		?>
		<div class="rtrs-review-wrap reviews_tab <?php echo esc_attr( $parent_class ); ?> rtrs-review-post-type-<?php echo esc_attr( $get_post_type ); ?> rtrs-review-sc-<?php echo esc_attr( $p_meta['sc_id'] ); ?>" id="comments">
			<?php
			if ( is_user_logged_in() ) {
				global $current_user;
				$is_commented = get_comments(
					[
						'user_id'    => $current_user->ID,
						'post_id'    => get_the_ID(),
						'meta_query' => [
							[
								'key'     => 'rating',
								'value'   => [ 1, 5 ],
								'compare' => 'BETWEEN',
							],
						],
					]
				);

				$multiple_review = rtrs()->get_options( 'rtrs_review_settings', [ 'multiple_review', 'no' ] );

				if ( $is_commented && $multiple_review == 'no' ) {
					echo '<div class="rtrs-multiple-comment">';
					Functions::the_comment_form();
					echo '</div>';
				} else {
					Functions::the_comment_form();
				}
			} else {
				Functions::the_comment_form();
			}
			?>
			<script>
				jQuery( document ).ready(function($) {
					$('#comment_form').removeAttr('novalidate');
				});
			</script>
		</div>
		<?php
		return ob_get_clean();
	}
	/**
	 * Review summary shortcode output.
	 *
	 * @return string
	 */
	public static function review_summary() {
		$post_id = get_the_ID();
		// Shortcode can run during save / preview.
		if ( ! $post_id ) {
			return '';
		}
		$total_rating = Review::getTotalRatings( $post_id );
		if ( ! $total_rating ) {
			return '';
		}
		// No reviews → no output.
		if ( get_comments_number( $post_id ) === 0 ) {
			return '';
		}
		$get_post_type = get_post_type( $post_id );
		$p_meta        = Functions::getMetaByPostType( $get_post_type );
		$parent_class  = $p_meta['parent_class'][0] ?? '';
		$layout        = $p_meta['summary_layout'][0] ?? 'one';
		$s_affiliate   = get_post_meta( $post_id, 'rtrs_affiliate', true ) === '1';
		$s_layout      = get_post_meta( $post_id, 'rtrs_summary_layout', true );
		if ( $s_affiliate && $s_layout ) {
			$layout = $s_layout;
		}
		ob_start();
		?>
		<div id="comments" class="rtrs-review-wrap reviews_tab <?php echo esc_attr( $parent_class ); ?> rtrs-review-post-type-<?php echo esc_attr( $get_post_type ); ?> rtrs-review-sc-<?php echo esc_attr( $p_meta['sc_id'] ?? '' ); ?>" >
			<?php
			Functions::get_template_part(
				'summary/layout-' . $layout,
				[
					'total_rating' => $total_rating,
					'p_meta'       => $p_meta,
				]
			);
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}