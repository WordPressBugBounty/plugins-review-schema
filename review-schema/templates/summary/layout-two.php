<?php
/**
 * ;;Review summary layout two template
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

<div class="rtrs-summary-2">
	<div class="rtrs-rating-summary">
		<?php if ( $rtrs_avg_rating = ReviewFns::getAvgRatings( get_the_ID(), true ) ) { ?>
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

		if ( isset( $p_meta['criteria'] ) && $p_meta['criteria'][0] == 'multi' ) {
			?>
				<div class="rtrs-rating-item">
			<ul class="rtrs-rating-category">
				<?php
				foreach ( ReviewFns::getCriteriaAvgRatings( get_the_ID() ) as $rtrs_value ) {
					if ( ! $rtrs_value['avg'] ) {
						continue;
					}
					?>
										<li>
						<label><?php echo esc_html( $rtrs_value['title'] ); ?></label>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
						<?php echo ReviewFns::review_stars( $rtrs_value['avg'] ); ?>
					</li>
				<?php } ?>  
			</ul>
		</div> 
		<?php } ?>
	</div> 
</div>