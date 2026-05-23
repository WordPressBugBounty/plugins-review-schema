<?php
/**
 * @wordpress-plugin
 * Plugin Name: Schema Engine AI – AI Schema Markup, Reviews & Rich Snippets for SEO
 * Plugin URI: https://wordpress.org/plugins/review-schema/
 * Description: AI-Powered schema markup plugin for WordPress. Generate JSON-LD schema and FAQs, validate Rich Results, and audit your structured data.
 * Version: 3.0.1
 * Author: RadiusTheme
 * Author URI: https://radiustheme.com
 * Text Domain: review-schema
 * Domain Path: /languages
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define PLUGIN_FILE.
if ( ! defined( 'RTRS_PLUGIN_FILE' ) ) {
	define( 'RTRS_PLUGIN_FILE', __FILE__ );
}

// Define VERSION.
if ( ! defined( 'RTRS_VERSION' ) ) {
	define( 'RTRS_VERSION', '3.0.1' );
}

if ( ! defined( 'RTRS_PATH' ) ) {
	define( 'RTRS_PATH', plugin_dir_path( __FILE__ ) );
}

require_once RTRS_PATH . 'vendor/autoload.php';

// Clear AI batch cron on deactivation.
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'rtrs_ai_batch_generate' );
		delete_option( '_rtrs_ai_batch_progress' );
		delete_option( '_rtrs_ai_batch_done' );
	}
);
use Rtrs\Rtrs;
/**
 * Rivew Schema
 *
 * @return bool|SingletonTrait|Rtrs
 */
function rtrs() {
	return Rtrs::getInstance();
}
rtrs(); // Run Rtrs Plugin.
