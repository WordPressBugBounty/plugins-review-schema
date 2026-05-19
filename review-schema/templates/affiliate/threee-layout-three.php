<?php
/**
 * Review layout one template
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
<div class="rtrs-summary-3">
	<?php rtrs()->get_partial_path( 'title', [ 'p_meta' => $p_meta ] ); ?> 

	<div class="rtrs-rating-summary">
		<div class="rtrs-rating-item grid-span-2">
			<ul class="rtrs-rating-category">
				<?php
				foreach ( $p_meta['criteria'] as $rtrs_value ) {
					if ( ! $rtrs_value['avg'] ) {
						continue;
					}
					$rtrs_avg_value = ( $rtrs_value['type'] == 'percent' ) ? $rtrs_value['avg'] / 20 : $rtrs_value['avg'];
					?>
										<li>
						<label><?php echo esc_html( $rtrs_value['title'] ); ?></label>
						<div class="rating-icon">
							<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
							<?php echo ReviewFns::review_stars( $rtrs_avg_value ); ?>
						</div>
						<div class="rating-number">
							<span class="total-number"><?php echo esc_html( $rtrs_avg_value ); ?> /</span>
							<span class="outof-number">5</span>
						</div>
					</li>
				<?php } ?> 
			</ul>
		</div> 
		
		<?php if ( $rtrs_avg_rating = $p_meta['avg_rating'] ) { ?>
		<div class="rtrs-rating-item">
			<div class="rtrs-rating-overall">
				<div class="rating-percent"><?php echo esc_html( $rtrs_avg_rating ); ?></div>
				<div class="rating-text"><?php esc_html_e( 'OVERALL', 'review-schema' ); ?></div>
				<div class="rating-icon">
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
					<?php echo ReviewFns::review_stars( $rtrs_avg_rating ); ?>
				</div>
				<p>
					<?php
						$rtrs_total_rating = $p_meta['total_rating'];
						printf(
							/* translators: %d: total number of ratings */
							esc_html( _n( 'Based on %d rating', 'Based on %d ratings', $rtrs_total_rating, 'review-schema' ) ),
							esc_html( $rtrs_total_rating )
						);
					?>
									</p>
			</div>
		</div>
		<?php } ?> 
		
		<?php
		if ( $rtrs_summary = $p_meta['summary'] ) {
			?>
		<div class="rtrs-rating-item grid-span-1">
			<div class="rtrs-feedback-text">
				<h3 class="rtrs-feedback-ttile"><?php esc_html_e( 'Summary', 'review-schema' ); ?></h3>
				<p><?php echo wp_kses_post( $rtrs_summary ); ?></p>
			</div>
		</div> 
		<?php } ?>

	</div>

	<?php rtrs()->get_partial_path( 'buy-btn', [ 'p_meta' => $p_meta ] ); ?> 
</div>