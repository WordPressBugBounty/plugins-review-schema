<?php

namespace Rtrs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Rtrs\Controllers\Admin\Activation;
use Rtrs\Controllers\Admin\AdminController;
use Rtrs\Controllers\Ajax\AjaxController;
use Rtrs\Controllers\MigrationV3;
use Rtrs\Helpers\Functions;
use Rtrs\Modules\ModulesInit;
use Rtrs\Traits\SingletonTrait;

/**
 * Class Rtrs.
 */
final class Rtrs {
	use SingletonTrait;

	private $post_type = 'rtrs';

	private $post_type_affiliate = 'rtrs_affiliate';

	private $nonceId = '__rtrs_wpnonce';

	private $nonceText = 'rtrs_nonce_kx2T6dYRXSxD';

	/**
	 * Review Schema Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
		new Activation();
		ModulesInit::getInstance();
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ], -1 );
		add_action( 'init', [ $this, 'init' ], 1 );
	}

	public function init() {
		do_action( 'rtrs_before_init' );
		$this->maybe_auto_enable_review();
		new AdminController();
		new AjaxController();
		new MigrationV3();

		do_action( 'rtrs_init' );
	}

	/**
	 * Auto-enable review when rtrs posts exist but review_enabled is not set.
	 *
	 * @return void
	 */
	private function maybe_auto_enable_review() {
		$general_options = get_option( 'rtrs_general_settings', [] );

		if ( ! empty( $general_options['review_enabled'] ) ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation check; caching is unnecessary.
		$has_posts = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
				$this->post_type
			)
		);

		if ( $has_posts ) {
			$general_options['review_enabled'] = 'yes';
			update_option( 'rtrs_general_settings', $general_options );
		}
	}

	/**
	 * Plugin Loaded
	 */
	public function on_plugins_loaded() {
		do_action( 'rtrs_ai_loaded' );
	}

	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 *
	 * @return bool
	 */
	public function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	private function define_constants() {
		$this->define( 'RTRS_URL', plugins_url( '', RTRS_PLUGIN_FILE ) );
		$this->define( 'RTRS_SLUG', basename( dirname( RTRS_PLUGIN_FILE ) ) );
		$this->define( 'RTRS_TEMPLATE_DEBUG_MODE', false );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	public function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- Helper that defines a constant whose name is supplied by the caller (always RTRS_*).
			define( $name, $value );
		}
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( RTRS_PLUGIN_FILE ) );
	}

	/**
	 * @return string
	 */
	public function getPostType() {
		return $this->post_type;
	}

	/**
	 * @return string
	 */
	public function getPostTypeAffiliate() {
		return $this->post_type_affiliate;
	}

	/**
	 * @return string
	 */
	public function getNonceId() {
		return $this->nonceId;
	}

	/**
	 * @return string
	 */
	public function getNonceText() {
		return $this->nonceText;
	}

	/**
	 * Get the template path.
	 *
	 * @return string
	 */
	public function get_template_path() {
		return apply_filters( 'rtrs_template_path', 'review-schema/' );
	}

	/**
	 * Output a template partial.
	 *
	 * Loads `templates/partials/{$path}.php` and echoes it.
	 *
	 * @param string|null $path Partial slug relative to `partials/`.
	 * @param array       $args Variables to extract into the partial scope.
	 *
	 * @return void
	 */
	public function get_partial_path( $path = null, $args = [] ) {
		Functions::get_template_part( 'partials/' . $path, $args );
	}

	/**
	 * @param $file
	 *
	 * @return string
	 */
	public function get_assets_uri( $file ) {
		$file = ltrim( $file, '/' );

		return trailingslashit( RTRS_URL . '/assets' ) . $file;
	}

	/**
	 * @param $file
	 *
	 * @return string
	 */
	public function render( $viewName, $args = [], $return = false ) {
		$path     = str_replace( '.', '/', $viewName );
		$viewPath = RTRS_PATH . '/views/' . $path . '.php';
		if ( ! file_exists( $viewPath ) ) {
			return;
		}

		if ( $args ) {
			extract( $args, EXTR_SKIP ); // @codingStandardsIgnoreLine
		}

		if ( $return ) {
			ob_start();
			include $viewPath;

			return ob_get_clean();
		}
		include $viewPath;
	}

	/**
	 * @param $file
	 * Get all optoins field value
	 *
	 * @return mixed
	 */
	public function get_options() {
		$option_field = func_get_args()[0];
		$result       = get_option( $option_field );
		$func_args    = func_get_args();
		array_shift( $func_args );

		foreach ( $func_args as $arg ) {
			if ( is_array( $arg ) ) {
				if ( ! empty( $result[ $arg[0] ] ) ) {
					$result = $result[ $arg[0] ];
				} else {
					$result = $arg[1];
				}
			} else {
				if ( ! empty( $result[ $arg ] ) ) {
					$result = $result[ $arg ];
				} else {
					$result = null;
				}
			}
		}

		return $result;
	}
}
