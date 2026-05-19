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
<div class="rtrs-summary-2 rtrs-affiliate rtrs-affiliate-two">

	<div class="rtrs-rating-summary">
		<?php if ( $rtrs_avg_rating = $p_meta['avg_rating'] ) { ?>
		<div class="rtrs-rating-item">
			<div class="rtrs-circle">
				<div class="rtrs-circle-bar">
					<svg>
						<circle cx="70" cy="70" r="80" style="stroke-dashoffset: <?php echo esc_attr( 490 - ( 490 * ( $rtrs_avg_rating * 20 ) ) / 100 ); ?>;"></circle>
						<circle cx="70" cy="70" r="80" style="stroke-dashoffset: <?php echo esc_attr( 490 - ( 490 * ( $rtrs_avg_rating * 20 ) ) / 100 ); ?>;"></circle>
					</svg>
				</div>
				<div class="rtrs-circle-content">
					<div class="rating-percent"><?php echo esc_html( $rtrs_avg_rating * 20 ); ?>%</div>
					<div class="rating-text"><?php esc_html_e( 'OVERALL', 'review-schema' ); ?></div>
					<div class="rating-icon">
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
						<?php echo ReviewFns::review_stars( $rtrs_avg_rating ); ?>
					</div>
				</div> 
			</div>
		</div>
			<?php
		}

		if ( $rtrs_criteria = $p_meta['criteria'] ) {
			?>
				<div class="rtrs-rating-item rtrs-criteria-wrapper">
			<ul class="rtrs-rating-category">
				<?php
				foreach ( $rtrs_criteria as $rtrs_value ) {
					if ( ! $rtrs_value['avg'] ) {
						continue;
					}
					$rtrs_avg_value = ( $rtrs_value['type'] == 'percent' ) ? $rtrs_value['avg'] / 20 : $rtrs_value['avg'];
					?>
										<li>
						<label><?php echo esc_html( $rtrs_value['title'] ); ?></label>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
						<?php echo ReviewFns::review_stars( $rtrs_avg_value ); ?>
					</li>
				<?php } ?>  
			</ul>
		</div> 
			<?php
		}

		if ( $rtrs_summary = $p_meta['summary'] ) {
			?>
		<div class="rtrs-rating-item rtrs-summery-wrapper">
			<?php rtrs()->get_partial_path( 'title', [ 'p_meta' => $p_meta ] ); ?>

			<div class="rtrs-feedback-text">
				<h3 class="rtrs-feedback-ttile"><?php esc_html_e( 'Summary', 'review-schema' ); ?></h3>
				<p><?php echo wp_kses_post( $rtrs_summary ); ?></p>
			</div>
		</div> 
		<?php } ?>

	</div>

	<div class="rtrs-feedback-summary">
		<?php
		if ( $rtrs_pros = $p_meta['pros'] ) {
			?>
		<div class="rtrs-feedback-box">
			<h3 class="rtrs-feedback-title">
				<span class="item-icon like-icon"><i class="rtrs-thumbs-up"></i></span>
				<span class="item-text"><?php esc_html_e( 'Pros', 'review-schema' ); ?></span>
			</h3>
			<ul class="rtrs-feedback-list">
				<?php foreach ( $rtrs_pros as $rtrs_value ) { ?>
				<li><?php echo esc_html( $value ); ?></li>
				<?php } ?>
			</ul>
		</div>
		<?php } ?>
		
		<?php

		if ( $rtrs_cons = $p_meta['cons'] ) {
			?>
		<div class="rtrs-feedback-box">
			<h3 class="rtrs-feedback-title">
				<span class="item-icon unlike-icon"><i class="rtrs-thumbs-down"></i></span>
				<span class="item-text"><?php esc_html_e( 'Cons', 'review-schema' ); ?></span>
			</h3>
			<ul class="rtrs-feedback-list">
				<?php foreach ( $rtrs_cons as $rtrs_value ) { ?>
				<li><?php echo esc_html( $value ); ?></li>
				<?php } ?>
			</ul>
		</div>
		<?php } ?>
	</div>

	<?php rtrs()->get_partial_path( 'buy-btn', [ 'p_meta' => $p_meta ] ); ?> 

</div>