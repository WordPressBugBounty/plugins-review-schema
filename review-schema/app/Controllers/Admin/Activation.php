<?php

namespace Rtrs\Controllers\Admin;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activation {

	public function __construct() {
		register_activation_hook( RTRS_PLUGIN_FILE, [ $this, 'plugin_activate' ] );
		add_action( 'admin_init', [ $this, 'plugin_redirect' ] );
	}
	/**
	 * Plugin activation callback.
	 */
	public function plugin_activate() {
		$this->reGenerateCss();
		update_option( 'rtrs_activation_redirect', true );
	}
	/**
	 * Redirect after plugin activation.
	 */
	public function plugin_redirect() {
		// Set only if plugin was never activated before.
		$prevActivated = get_option( 'rtrs_plugin_activation_time' );
		if ( ! $prevActivated ) {
			$get_activation_time = strtotime( 'now' );
			update_option( 'rtrs_plugin_activation_time', $get_activation_time );
		}

		if ( ! $prevActivated && ! get_option( 'rtrs_activation_setup_wizard_done' ) ) {
			update_option( 'rtrs_activation_setup_wizard_done', true );
			wp_safe_redirect(
				admin_url( 'admin.php?page=review-schema#/setup-wizard' )
			);
			exit;
		}
		if ( get_option( 'rtrs_activation_redirect', false ) ) {
			update_option( 'rtrs_activation_redirect', false );
			wp_safe_redirect(
				admin_url( 'admin.php?page=review-schema' )
			);
			exit;
		}
	}
	function reGenerateCss() {
		// review post type
		$scPostIds = get_posts(
			[
				'post_type'      => rtrs()->getPostType(),
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);

		if ( is_array( $scPostIds ) && ! empty( $scPostIds ) ) {
			foreach ( $scPostIds as $scPostId ) {
				Functions::generatorShortCodeCss( $scPostId, 'review' );
			}
		}
		wp_reset_postdata();

		// rtrs_affiliate post type
		$scPostIds = get_posts(
			[
				'post_type'      => rtrs()->getPostTypeAffiliate(),
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);

		if ( is_array( $scPostIds ) && ! empty( $scPostIds ) ) {
			foreach ( $scPostIds as $scPostId ) {
				Functions::generatorShortCodeCss( $scPostId, 'affiliate' );
			}
		}
		wp_reset_postdata();
	}
}
