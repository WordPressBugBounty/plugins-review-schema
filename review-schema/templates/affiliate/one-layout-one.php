<?php
/**
 * Review summary layout one template
 *
 * @author      RadiusTheme
 * @package     review-schema/templates/review
 * @version     1.0.0
 *
 * @var use Rtrs\Helpers\Functions
 */

use Rtrs\Modules\Review\Helpers\ReviewFns;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="rtrs-layout-one">
	<div class="rtrs-summary rtrs-image-area-wrapper">
		<div class="rtrs-summary-box">
			<div class="rtrs-rating-item">
				<div class="rtrs-product-img">
					<img src="<?php echo esc_url( $p_meta['featured_image'] ); ?>" alt="">
				</div>
			</div>
		</div>

		<?php
		$rtrs_avg_rating = $p_meta['avg_rating'];
		$rtrs_criteria   = $p_meta['criteria'];
		if ( $rtrs_avg_rating || $rtrs_criteria ) {
			?>
			<div class="rtrs-summary-box rtrs-rating-box-wrapper ">
				<?php rtrs()->get_partial_path( 'title', [ 'p_meta' => $p_meta ] ); ?>
				<?php if ( $rtrs_criteria ) { ?>
					<div class="rtrs-rating-box">
						<div class="rtrs-rating-number">
							<span class="rtrs-rating"><?php echo esc_html( $rtrs_avg_rating ); ?></span>
							<span class="rtrs-rating-out">/5</span>
						</div>
						<div class="rtrs-rating-icon">
							<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
							<?php echo ReviewFns::review_stars( $rtrs_avg_rating ); ?>
							<div class="rtrs-rating-text">
								<?php
								$rtrs_total_rating = $p_meta['total_rating'];
								printf(
									/* translators: %d: total number of ratings */
									esc_html( _n( 'Based on %d rating', 'Based on %d ratings', $rtrs_total_rating, 'review-schema' ) ),
									esc_html( $rtrs_total_rating )
								);
								?>
							</div>
						</div>
					</div>
				<?php } if ( $rtrs_criteria ) { ?>
					<div class="rtrs-progress-wrap">
						<?php
						foreach ( $rtrs_criteria as $rtrs_value ) {
							if ( ! $rtrs_value['avg'] ) {
								continue;
							}
							$rtrs_avg_value = ( $rtrs_value['type'] == 'percent' ) ? $rtrs_value['avg'] : $rtrs_value['avg'] * 20;
							?>
							<div class="rtrs-progress">
								<label><?php echo esc_html( $rtrs_value['title'] ); ?></label>
								<progress class="rtrs-progress-bar service-preogress" value="<?php echo esc_html( $rtrs_avg_value ); ?>" max="100"></progress>
								<span class="progress-percent"><?php echo esc_html( $rtrs_avg_value ); ?>%</span>
							</div>
						<?php } ?>
					</div>
				<?php } ?>
			</div>
		<?php } ?>

	</div>

	<div class="rtrs-summary">
		<div class="rtrs-summary-box">
			<?php if ( $rtrs_summary = $p_meta['summary'] ) { ?>
				<div class="rtrs-summary-text">
					<h3 class="rtrs-summary-ttile"><?php esc_html_e( 'Summary', 'review-schema' ); ?></h3>
					<p><?php echo wp_kses_post( $rtrs_summary ); ?></p>
				</div>
			<?php } ?>
		</div>
	</div>

	<div class="rtrs-summary">
		<?php rtrs()->get_partial_path( 'buy-btn', [ 'p_meta' => $p_meta ] ); ?>
	</div>

</div>