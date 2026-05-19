<?php
/**
 * Review grid one template
 *
 * @author      RadiusTheme
 * @package     review-schema/templates
 * @version     1.0.0
 */

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_get_post_type = get_post_type( get_the_ID() );
$rtrs_p_meta        = Functions::getMetaByPostType( get_post_type() );

if ( ! empty( $rtrs_p_meta['review-summary-hide'][0] ) && ! empty( $rtrs_p_meta['review-list-hide'][0] ) && ! empty( $rtrs_p_meta['review-form-hide'][0] ) ) {
	return '';
}

$rtrs_parent_class = isset( $rtrs_p_meta['parent_class'] ) ? $rtrs_p_meta['parent_class'][0] : '';

?>
<div class="rtrs-review-wrap reviews_tab <?php echo esc_attr( $rtrs_parent_class ); ?> rtrs-review-post-type-<?php echo esc_attr( $rtrs_get_post_type ); ?> rtrs-review-sc-<?php echo esc_attr( $rtrs_p_meta['sc_id'] ); ?>" id="comments">
	<?php if ( have_comments() ) : ?> 
		<?php
		$rtrs_total_rating           = ReviewFns::getTotalRatings( get_the_ID() );
		$rtrs_total_forbidden_review = ReviewFns::getTotalGDPRForbiddenReview( get_the_ID() );
		if ( empty( $rtrs_p_meta['review-summary-hide'][0] ) ) {
			$rtrs_layout      = isset( $rtrs_p_meta['summary_layout'] ) ? $rtrs_p_meta['summary_layout'][0] : 'one';
			$rtrs_s_affiliate = ( get_post_meta( get_the_ID(), 'rtrs_affiliate', true ) == '1' );
			$rtrs_s_layout    = get_post_meta( get_the_ID(), 'rtrs_summary_layout', true );
			if ( $rtrs_s_affiliate && $rtrs_s_layout ) {
				$rtrs_layout = $rtrs_s_layout;
			}
			if ( $rtrs_total_rating ) {
				Functions::get_template_part(
					'summary/layout-' . $rtrs_layout,
					[
						'total_rating' => $rtrs_total_rating,
						'p_meta'       => $rtrs_p_meta,
					]
				);
			}
		}
		?>

		<?php if ( $rtrs_total_rating && empty( $rtrs_p_meta['review-list-hide'][0] ) ) { ?>
			<div class="rtrs-sorting-bar">
				<h3 class="rtrs-sorting-title">
				<?php
				$rtrs_review_title = esc_html(
					sprintf(
						/* translators: %d: total number of reviewers */
						_n( 'Reviewed by %d user', 'Reviewed by %d users', $rtrs_total_rating, 'review-schema' ),
						$rtrs_total_rating
					)
				);
				echo wp_kses_post( apply_filters( 'rtrs_review_sorting_title', $rtrs_review_title, $rtrs_total_rating, get_the_ID() ) );
				?>
				</h3>
				<div class="rtrs-sorting-select">
					<?php
						$rtrs_filter = ( isset( $rtrs_p_meta['filter'] ) && $rtrs_p_meta['filter'][0] == '1' );
					if ( $rtrs_filter ) {
						$rtrs_filter_option = isset( $rtrs_p_meta['filter_option'] ) ? $rtrs_p_meta['filter_option'] : '';
						?>
					<div>
						<label><i class="rtrs-sort"></i> <?php esc_html_e( 'Sort:', 'review-schema' ); ?></label>
						<select class="rtrs_review_filter rtrs-sort-filter" name="rtrs_review_sort_filter" data-type="sort">
							<option value="all"><?php esc_html_e( 'All Review', 'review-schema' ); ?></option>
						<?php if ( in_array( 'top_rated', $rtrs_filter_option ) ) { ?>
							<option value="top_rated"><?php esc_html_e( 'Top Rated', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'low_rated', $rtrs_filter_option ) ) { ?>
							<option value="low_rated"><?php esc_html_e( 'Low Rated', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'recommended', $rtrs_filter_option ) ) { ?>
							<option value="recommended"><?php esc_html_e( 'Recommended', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'highlighted', $rtrs_filter_option ) ) { ?>
							<option value="highlighted"><?php esc_html_e( 'Highlighted', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'latest_first', $rtrs_filter_option ) ) { ?>
							<option value="latest_first"><?php esc_html_e( 'Latest First', 'review-schema' ); ?></option>
							<?php } if ( in_array( 'oldest_first', $rtrs_filter_option ) ) { ?>
							<option value="oldest_first"><?php esc_html_e( 'Oldest First', 'review-schema' ); ?></option>
							<?php } ?>
						</select>
					</div>
					<?php } ?>

					<?php
						$rtrs_filter = ( isset( $rtrs_p_meta['filter'] ) && $rtrs_p_meta['filter'][0] == '1' );
					if ( $rtrs_filter ) {
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

			<div class="rtrs-review-box">
				<ul class="rtrs-review-list">
					<?php
						// TODO:: Sticky Comment will display here
						do_action( 'rtrs_before_review_comments_list', $rtrs_p_meta, get_the_ID() );
						$rtrs_args = [
							'post_id' => get_the_ID(),
							'type'    => 'review',
							'status'  => 'approve', // Change this to the type of comments to be displayed
						];
						$comments  = get_comments( apply_filters( 'rtrs_comments_query_args', $rtrs_args, $rtrs_p_meta ) );
						wp_list_comments(
							[
								'style'      => 'li',
								'short_ping' => true,
								'callback'   => [ ReviewFns::class, 'comment_list' ],
							],
							$comments
						);
					?>
				</ul>
			</div>
            <?php if ( $rtrs_total_forbidden_review > 0 ){ ?>
                <h3 class="rtrs-gdpr-consent-title">
                    <?php
                    $rtrs_gdpr_consent_title = esc_html(
                            sprintf(
                                    /* translators: %d: number of users who opted out of public review display */
                                    _n( '%d more user is not interested in showing their review publicly.', '%d more users are not interested in showing their reviews publicly.', $rtrs_total_forbidden_review, 'review-schema' ),
                                    $rtrs_total_forbidden_review
                            )
                    );
                    echo wp_kses_post( apply_filters( 'rtrs_gdpr_consent_review_title', $rtrs_gdpr_consent_title, $rtrs_total_forbidden_review, get_the_ID() ) );
                    ?>
                </h3>
            <?php } ?>
			<?php
			$rtrs_pagination_type = isset( $rtrs_p_meta['pagination_type'] ) ? $rtrs_p_meta['pagination_type'][0] : 'number';
			if ( $rtrs_pagination_type == 'number' ) {
				?>
				<?php if ( get_the_comments_pagination() ) { ?>
				<div class="rtrs-paginate">
					<?php
					paginate_comments_links(
						[
							'prev_text' => '<i class="rtrs-angle-left"></i>',
							'next_text' => '<i class="rtrs-angle-right"></i>',
						]
					);
					?>
				</div>
				<?php } ?>
			<?php } elseif ( $rtrs_pagination_type == 'number-ajax' ) { ?>
				<?php if ( get_the_comments_pagination() ) { ?>
				<div class="rtrs-paginate rtrs-paginate-ajax" data-max="<?php echo esc_attr( get_comment_pages_count() ); ?>">
					<?php
					paginate_comments_links(
						[
							'prev_text' => '<i class="rtrs-angle-left"></i>',
							'next_text' => '<i class="rtrs-angle-right"></i>',
						]
					);
					?>
				</div>
				<?php } ?>
			<?php } elseif ( $rtrs_pagination_type == 'load-more' ) { ?>
				<div class="rtrs-paginate rtrs-paginate-load-more rtrs-align-center">
					<a href="#" id="rtrs-load-more" data-current_page="1"  data-max="<?php echo esc_attr( get_comment_pages_count() ); ?>"><?php echo esc_html_e( 'Load More', 'review-schema' ); ?></a>
				</div>
			<?php } elseif ( $rtrs_pagination_type == 'auto-scroll' ) { ?>
				<div class="rtrs-paginate rtrs-paginate-onscroll" data-current_page="1"  data-max="<?php echo esc_attr( get_comment_pages_count() ); ?>"></div>
			<?php } ?>
			<?php
		}
	endif;
	if ( empty( $rtrs_p_meta['review-form-hide'][0] ) ) {
		if ( is_user_logged_in() ) {
			global $current_user;
			$rtrs_is_commented    = get_comments(
				[
					'user_id'    => $current_user->ID,
					'post_id'    => get_the_ID(),
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary to detect whether the current user has already left a rating on this post.
					'meta_query' => [
						[
							'key'     => 'rating',
							'value'   => [ 1, 5 ],
							'compare' => 'BETWEEN',
						],
					],
				]
			);
			$rtrs_multiple_review = rtrs()->get_options( 'rtrs_review_settings', [ 'multiple_review', 'no' ] );
			if ( $rtrs_is_commented && $rtrs_multiple_review == 'no' ) {
				echo '<div class="rtrs-multiple-comment">';
					ReviewFns::the_comment_form(); // comment_form();
				echo '</div>';
			} else {
				ReviewFns::the_comment_form();
			}
		} else {
			ReviewFns::the_comment_form();
		}
		?>
		<script>
			jQuery( document ).ready(function($) {
				$('#comment_form').removeAttr('novalidate');
			});
		</script>
	<?php } ?>
</div> 
