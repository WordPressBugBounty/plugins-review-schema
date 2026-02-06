<?php
/**
 * Black Friday promotional notice controller.
 *
 * @package RadiusTheme\SB
 */

namespace Rtrs\Controllers\Marketing;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Black Friday promotional notice class.
 *
 * Displays a dismissible Black Friday promotional banner in WordPress admin
 * from September 19 to January 5 each year.
 */
class BlackFridayV2 {

	/**
	 * Initialize the Black Friday notice.
	 *
	 * Sets up the promotional period and hooks the notice display.
	 */
	public function __construct() {
		$current = time();
		// Check if current date is within promotional period (Sept 19 - Jan 5).
		$start_date      = strtotime( '2025-11-12 00:00:00' );
		$end_date        = strtotime( '2026-01-05 23:59:59' );
		$is_promo_period = $start_date <= $current && $current <= $end_date;
		if ( $is_promo_period ) {
			add_action( 'admin_init', [ $this, 'black_friday_notice' ] );
		}
		add_action( 'admin_notices', [ $this, 'common_css_for_admin_notice' ], 1 );
	}
	/**
	 * Outputs inline CSS in the admin area to manage black-friday notices.
	 *
	 * Hides all notices with a class containing 'black-friday-notice' example (rtsb-black-friday-notice, rtrs-black-friday-notice, tpg-black-friday-notice etc ) and
	 * ensures only the first one is displayed.
	 *
	 * @return void
	 */
	public function common_css_for_admin_notice() {
		?>
		<style>
			/* Hide all black-friday notices */
			div[class*="black-friday-notice"] {
				display: none !important;
			}
			/* Show only the first one */
			div[class*="black-friday-notice"]:first-of-type {
				display: block !important;
			}
		</style>
		<?php
	}
	/**
	 * Initialize Black Friday admin notice.
	 *
	 * Displays the notice only if it hasn't been dismissed for the current year.
	 *
	 * @return void
	 */
	public function black_friday_notice() {
		$current_year = gmdate( 'Y' );
		$notice_key   = 'dismiss_bf_review_schema_' . $current_year;

		// Check if notice is already dismissed for this year.
		if ( get_option( $notice_key ) === '1' || isset( $GLOBALS['woobundle_notice'] ) ) {
			return;
		}

		$GLOBALS['woobundle_notice'] = 'woobundle_notice';
		self::display_notice();
	}

	/**
	 * Display Black Friday promotional notice.
	 *
	 * Renders the admin notice banner with dismiss functionality and AJAX handler.
	 *
	 * @return void
	 */
	public static function display_notice() {
		// Enqueue jQuery for dismiss functionality.
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'jquery' );
			}
		);

		// Render the notice banner.
		add_action(
			'admin_notices',
			function () {
				$plugin_name   = 'Review Schema';
				$download_link = 'https://www.radiustheme.com/downloads/wordpress-review-structure-data-schema-plugin/#pricing';
				?>
				<div class="notice notice-info is-dismissible rtrs-black-friday-notice"
					 data-rtrs-dismissable="review_schema"
					 style="padding: 0!important;border: 0;">
					<a href="<?php echo esc_url( $download_link ); ?>" style="display: block" target="_blank">
						<img alt="<?php echo esc_attr( $plugin_name ); ?>"
							 style="width: 100%;display: block;min-height: 30px;object-fit: cover"
							 src="<?php echo esc_url( rtrs()->get_assets_uri( 'imgs/all-offer-banner.svg' ) ); ?>"/>
					</a>
				</div>
				<?php
			}
		);

		// Add dismiss handler script.
		add_action(
			'admin_footer',
			function () {
				?>
				<script type="text/javascript">
					(function ($) {
						$(function () {
							setTimeout(function () {
								$('div[data-rtrs-dismissable] .notice-dismiss, div[data-rtrs-dismissable] .button-dismiss').on('click', function (e) {
									e.preventDefault();
									$.post(ajaxurl, {
										'action': 'ajax_review_schema',
										'nonce': <?php echo wp_json_encode( wp_create_nonce( 'rtrs-bf--dismissible-notice' ) ); ?>
									});
									$(e.target).closest('.is-dismissible').remove();
								});
							}, 1000);
						});
					})(jQuery);
				</script>
				<?php
			}
		);

		// Register AJAX handler for dismiss action.
		add_action(
			'wp_ajax_ajax_review_schema',
			function () {
				// Check user permissions.
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error(
						new \WP_Error( 'rtrs_block_user_permission', __( 'User permission error', 'shopbuilder' ) )
					);
				}
				// Verify nonce.
				check_ajax_referer( 'rtrs-bf--dismissible-notice', 'nonce' );
				// Save dismissal state for current year.
				$current_year = gmdate( 'Y' );
				update_option( 'dismiss_bf_review_schema_' . $current_year, '1' );

				wp_die();
			}
		);
	}
}