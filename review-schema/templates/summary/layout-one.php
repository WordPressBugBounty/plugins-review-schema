<?php
/**
 * Review summary summary layout one template
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
<div class="rtrs-summary"> 
	<?php if ( $rtrs_avg_rating = ReviewFns::getAvgRatings( get_the_ID(), true ) ) { ?>
	<div class="rtrs-summary-box">
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
						printf(
							/* translators: %d: total number of ratings */
							esc_html( _n( 'Based on %d rating', 'Based on %d ratings', $total_rating, 'review-schema' ) ),
							esc_html( $total_rating )
						);
					?>
									</div>
			</div>
		</div>
	</div>
		<?php
	}

	if ( isset( $p_meta['recommendation'] ) && $p_meta['recommendation'][0] == '1' ) {
		?>
	<div class="rtrs-summary-box">
		<div class="rtrs-rating-box">
			<div class="rtrs-recomnded-icon">
				<i class="rtrs-thumbs-up"></i>
			</div>
			<div class="rtrs-recomnded-content">
				<span class="rtrs-recomnded-number"> 
					<?php
						$rtrs_total_recommended = ReviewFns::getTotalRecommendation( get_the_ID() );
						printf(
							/* translators: %s: number of users who recommended this item */
							wp_kses( _n( '<span>%s</span>User', '<span>%s</span>Users', $rtrs_total_recommended, 'review-schema' ), [ 'span' => [] ] ),
							esc_html( $rtrs_total_recommended )
						);
					?>
									</span>
				<p class="rtrs-recomnded-text"><?php echo esc_html_e( 'Recommended this item', 'review-schema' ); ?></p>
			</div>
		</div>
	</div> 
		<?php
	} //end recommendation

	if ( isset( $p_meta['criteria'] ) && $p_meta['criteria'][0] == 'multi' ) {
		?>
		<div class="rtrs-summary-box">
		<div class="rtrs-progress-wrap">
			<?php
			foreach ( ReviewFns::getCriteriaAvgRatings( get_the_ID() ) as $rtrs_value ) {
				if ( ! $rtrs_value['avg'] ) {
					continue;
				}
				?>
								<div class="rtrs-progress">
					<label><?php echo esc_html( $rtrs_value['title'] ); ?></label>
					<progress class="rtrs-progress-bar service-preogress" value="<?php echo esc_html( $rtrs_value['avg'] * 20 ); ?>" max="100"></progress>
					<span class="progress-percent"><?php echo esc_html( $rtrs_value['avg'] * 20 ); ?>%</span>
				</div> 
			<?php } ?>  
		</div>
	</div> 
	<?php } ?>
</div>