<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Review Schema
 * Plugin URI:        https://wordpress.org/plugins/review-schema/
 * Description:       The most comprehensive multi-criteria Review & Rating with JSON-LD based Structure Data Schema solution for WordPress website. Support Review Rating and auto generated schema markup for page, post, WooCommerce & custom post type.
 * Version:           2.2.7
 * Author:            RadiusTheme
 * Author URI:        https://radiustheme.com
 * Text Domain:       review-schema
 * Domain Path:       /languages
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
	define( 'RTRS_VERSION', '2.2.7' );
}

if ( ! defined( 'RTRS_PATH' ) ) {
	define( 'RTRS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! class_exists( 'Rtrs' ) ) {
	require_once 'app/Rtrs.php';
}
