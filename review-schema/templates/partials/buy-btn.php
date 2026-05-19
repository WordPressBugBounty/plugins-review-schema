<?php
/**
 * Affiliate Buy btn template
 *
 * @author      RadiusTheme
 * @package     review-schema/templates
 * @version     1.0.0
 *
 * @var use Rtrs\Helpers\Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>  
<?php
$rtrs_btn_txt = $p_meta['btn_txt'] ?? '';
if ( $rtrs_btn_txt ) {
	$rtrs_open_in_new_tab = $p_meta['open_in_new_tab'] ?? false;
	?>
	<a target="<?php echo esc_attr( $rtrs_open_in_new_tab ? '_blank' : '_self' ); ?>" href="<?php echo esc_url( $p_meta['btn_url'] ); ?>" class="rtrs-buy-btn"><?php echo esc_html( $rtrs_btn_txt ); ?></a>
<?php } ?>
