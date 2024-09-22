<?php  
/**
 * Review summary layout one template
 * @author      RadiusTheme
 * @package     review-schema/templates/review
 * @version     1.0.0
 * 
 * @var use Rtrs\Helpers\Functions 
 * 
 */  
use Rtrs\Models\Review; 
use Rtrs\Helpers\Functions;  
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
        $avg_rating = $p_meta['avg_rating'];
        $criteria = $p_meta['criteria'];
        if ( $avg_rating || $criteria ) { ?>
            <div class="rtrs-summary-box rtrs-rating-box-wrapper ">
	            <?php rtrs()->get_partial_path('title', array( 'p_meta' => $p_meta ) ); ?>
                <?php if ( $criteria ) { ?>
                    <div class="rtrs-rating-box">
                        <div class="rtrs-rating-number">
                            <span class="rtrs-rating"><?php echo esc_html( $avg_rating ); ?></span>
                            <span class="rtrs-rating-out">/5</span>
                        </div>
                        <div class="rtrs-rating-icon">
                            <?php echo Functions::review_stars( $avg_rating ); ?>
                            <div class="rtrs-rating-text">
                                <?php
                                $total_rating = $p_meta['total_rating'];
                                printf(
                                    esc_html( _n( 'Based on %d rating', 'Based on %d ratings', $total_rating, 'review-schema' ) ),
                                    esc_html( $total_rating )
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                <?php } if ( $criteria ) { ?>
                    <div class="rtrs-progress-wrap">
                        <?php foreach( $criteria as $value ) {
                            if ( !$value['avg'] ) continue;
                            $avg_value = ( $value['type'] == 'percent' ) ? $value['avg'] : $value['avg']*20;
                            ?>
                            <div class="rtrs-progress">
                                <label><?php echo esc_html($value['title']); ?></label>
                                <progress class="rtrs-progress-bar service-preogress" value="<?php echo esc_html($avg_value); ?>" max="100"></progress>
                                <span class="progress-percent"><?php echo esc_html($avg_value); ?>%</span>
                            </div>
                        <?php } ?>
                    </div>
	            <?php } ?>
            </div>
	    <?php }  ?>

    </div>

    <div class="rtrs-summary">
        <div class="rtrs-summary-box">
            <?php if ( $summary = $p_meta['summary'] ) { ?>
                <div class="rtrs-summary-text">
                    <h3 class="rtrs-summary-ttile"><?php esc_html_e( 'Summary', 'review-schema' ); ?></h3>
                    <p><?php echo wp_kses_post( $summary ); ?></p>
                </div>
		    <?php } ?>
        </div>
    </div>

    <div class="rtrs-summary">
        <?php rtrs()->get_partial_path('buy-btn', array('p_meta' => $p_meta)); ?>
    </div>

</div>