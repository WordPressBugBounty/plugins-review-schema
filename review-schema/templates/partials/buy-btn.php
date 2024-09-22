<?php  
/**
 * Affiliate Buy btn template
 * @author      RadiusTheme
 * @package     review-schema/templates
 * @version     1.0.0
 * 
 * @var use Rtrs\Helpers\Functions 
 * 
 */ 
?>  
<?php if ( $btn_txt = $p_meta['btn_txt'] ) {
	$open_in_new_tab = $p_meta['open_in_new_tab'] ?? false;
    ?>
    <a target="<?php echo esc_attr( $open_in_new_tab ? '_blank' : '_self' ); ?>" href="<?php echo esc_url( $p_meta['btn_url'] ); ?>" class="rtrs-buy-btn"><?php echo esc_html( $btn_txt ); ?></a>
<?php } ?>

