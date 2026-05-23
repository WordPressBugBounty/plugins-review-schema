<?php
/**
 * Recommended-plugin installer AJAX handler.
 *
 * Triggered from the Power Up wizard step's "Finish Setup" action.
 * For each selected plugin, the handler downloads the plugin ZIP from
 * the wp.org repo, installs it into the plugins directory, and activates
 * it.
 *
 * Endpoint: wp_ajax_rtrs_install_recommended_plugin
 *
 * @package Rtrs\Controllers\Ajax
 */

namespace Rtrs\Controllers\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InstallRecommendedPlugin
 */
class InstallRecommendedPlugin {

	/**
	 * Whitelist of plugin slugs that can be installed via this endpoint.
	 *
	 * @var array
	 */
	protected static $allowed_slugs = [
		'radius-booking',
		'review-schema',
		'classified-listing',
		'shopbuilder',
		'tlp-food-menu',
		'tlp-team',
		'testimonial-slider-and-showcase',
		'woo-product-variation-gallery',
		'woo-product-variation-swatches',
	];

	/**
	 * Register the AJAX endpoint.
	 */
	public function __construct() {
		add_action( 'wp_ajax_rtrs_install_recommended_plugin', [ $this, 'handle' ] );
	}

	/**
	 * Handle the install + activate request.
	 *
	 * @return void
	 */
	public function handle() {
		// Capability check.
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to install plugins.', 'review-schema' ) ], 403 );
		}

		// Nonce check.
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid security token. Please reload the page.', 'review-schema' ) ], 400 );
		}

		// Slug check.
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		if ( ! $slug || ! in_array( $slug, self::$allowed_slugs, true ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Unsupported plugin slug.', 'review-schema' ) ], 400 );
		}

		// Already-active short-circuit.
		$installed_file = $this->locate_installed_plugin( $slug );
		if ( $installed_file && is_plugin_active( $installed_file ) ) {
			wp_send_json_success(
				[
					'slug'    => $slug,
					'file'    => $installed_file,
					'state'   => 'active',
					'message' => esc_html__( 'Plugin is already active.', 'review-schema' ),
				]
			);
		}

		// Already-installed → just activate.
		if ( $installed_file ) {
			$activated = activate_plugin( $installed_file );
			if ( is_wp_error( $activated ) ) {
				wp_send_json_error(
					[
						'slug'    => $slug,
						'message' => $activated->get_error_message(),
					],
					500
				);
			}
			wp_send_json_success(
				[
					'slug'    => $slug,
					'file'    => $installed_file,
					'state'   => 'active',
					'message' => esc_html__( 'Plugin activated.', 'review-schema' ),
				]
			);
		}

		// Not installed → download + install + activate.
		$installed_file = $this->install_from_wp_org( $slug );
		if ( is_wp_error( $installed_file ) ) {
			wp_send_json_error(
				[
					'slug'    => $slug,
					'message' => $installed_file->get_error_message(),
				],
				500
			);
		}

		$activated = activate_plugin( $installed_file );
		if ( is_wp_error( $activated ) ) {
			wp_send_json_error(
				[
					'slug'    => $slug,
					'file'    => $installed_file,
					'message' => $activated->get_error_message(),
				],
				500
			);
		}

		wp_send_json_success(
			[
				'slug'    => $slug,
				'file'    => $installed_file,
				'state'   => 'active',
				'message' => esc_html__( 'Plugin installed and activated.', 'review-schema' ),
			]
		);
	}

	/**
	 * Look up the main plugin file for an installed plugin by slug.
	 *
	 * @param string $slug Plugin folder slug.
	 *
	 * @return string Plugin file relative to /plugins/, or '' if not installed.
	 */
	protected function locate_installed_plugin( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		foreach ( array_keys( $installed ) as $plugin_file ) {
			if ( 0 === strpos( $plugin_file, $slug . '/' ) ) {
				return $plugin_file;
			}
		}

		return '';
	}

	/**
	 * Download and install a plugin from wp.org by slug.
	 *
	 * @param string $slug Plugin slug on WordPress.org.
	 *
	 * @return string|\WP_Error Installed plugin file path, or WP_Error on failure.
	 */
	protected function install_from_wp_org( $slug ) {
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		if ( ! class_exists( '\\Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$api = plugins_api(
			'plugin_information',
			[
				'slug'   => $slug,
				'fields' => [
					'sections'     => false,
					'requires_php' => true,
					'requires'     => true,
				],
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( empty( $api->download_link ) ) {
			return new \WP_Error( 'no_download_link', esc_html__( 'No download link returned from WordPress.org.', 'review-schema' ) );
		}

		// Silent upgrader skin (no HTML output, just collect feedback).
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'install_failed', esc_html__( 'Plugin installation failed. Check filesystem permissions.', 'review-schema' ) );
		}

		// Locate the installed file (Plugin_Upgrader exposes plugin_info()).
		$plugin_file = $upgrader->plugin_info();
		if ( $plugin_file ) {
			return $plugin_file;
		}

		// Fallback: re-scan installed plugins.
		$plugin_file = $this->locate_installed_plugin( $slug );
		if ( $plugin_file ) {
			return $plugin_file;
		}

		return new \WP_Error( 'plugin_file_unknown', esc_html__( 'Installed plugin file could not be located.', 'review-schema' ) );
	}
}
