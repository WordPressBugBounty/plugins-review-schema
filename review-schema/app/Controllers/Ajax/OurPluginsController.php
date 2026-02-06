<?php

namespace Rtrs\Controllers\Ajax;

use WP_Query;
use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;
/**
 * Handles AJAX actions for installing and activating plugins
 * inside the RTRS admin interface.
 */
class OurPluginsController {

	/**
	 * Constructor: Registers hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_plugin_scripts' ] );
		add_action( 'wp_ajax_rtrs_activate_plugin', [ $this, 'ajax_activate_plugin' ] );
		add_action( 'wp_ajax_rtrs_install_plugin', [ $this, 'ajax_install_plugin' ] );
	}

	/**
	 * Enqueue plugin handler script.
	 *
	 * @return void
	 */
	public function enqueue_plugin_scripts() {
		wp_enqueue_script(
			'rtrs-plugin-handler',
			rtrs()->get_assets_uri( 'js/plugin-handler.js' ),
			[ 'jquery' ],
			'1.0',
			true
		);
		wp_localize_script(
			'rtrs-plugin-handler',
			'rtrsOurPlugins',
			[
				'nonce'   => wp_create_nonce( 'rtrs_plugin_action' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			]
		);
	}

	/**
	 * AJAX: Activate plugin by slug.
	 *
	 * @return void
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'rtrs_plugin_action', 'nonce' );
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
			exit; // Add this.
		}
		$slug        = sanitize_text_field( wp_unslash( $_POST['slug'] ?? '' ) );
		$plugin_file = $slug . '/' . $slug . '.php';
		$result      = activate_plugin( $plugin_file );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			exit;
		}
		wp_send_json_success( [ 'message' => 'Activated' ] );
		exit;
	}

	/**
	 * AJAX: Install plugin from WordPress.org repo.
	 *
	 * @return void
	 */
	public function ajax_install_plugin() {
		check_ajax_referer( 'rtrs_plugin_action', 'nonce' );
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}
		$slug = sanitize_text_field( wp_unslash( $_POST['slug'] ?? '' ) );
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$api = plugins_api( 'plugin_information', [ 'slug' => $slug ] );
		if ( is_wp_error( $api ) ) {
			wp_send_json_error( [ 'message' => 'Plugin not found' ] );
			exit; // Add this.
		}
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			exit; // Add this.
		}
		wp_send_json_success( [ 'message' => 'Installed' ] );
	}
}
