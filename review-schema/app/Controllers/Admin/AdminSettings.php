<?php

namespace Rtrs\Controllers\Admin;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminSettings {
	protected $tabs = [];

	protected $active_tab;

	protected $current_section;

	protected $subtabs;

	protected $option = '';

	/**
	 * Hello.
	 */
	public function __construct() {
		add_action( 'admin_head', [ $this, 'remove_admin_notices_on_settings_page' ], 99 );
		add_action( 'init', [ $this, 'edd_comments' ], 999 );
		add_action( 'admin_init', [ $this, 'setTabs' ] );
		add_action( 'admin_menu', [ $this, 'add_rtrs_menu' ], 10 );
		add_action( 'admin_menu', [ $this, 'add_settings_menu' ], 30 );
		add_filter( 'plugin_action_links_' . plugin_basename( RTRS_PLUGIN_FILE ), [ $this, 'marketing_links' ] );
		add_action( 'wp_ajax_rtrs_update_options', [ $this, 'handle_update_options' ] );
		add_action( 'wp_ajax_rtrs_get_options', [ $this, 'handle_get_options' ] );
	}
	/**
	 * Remove all admin notices on plugin settings pages.
	 */
	public function remove_admin_notices_on_settings_page() {
		$screen = get_current_screen();
		if ( ! empty( $screen ) && strpos( $screen->id, 'page_review-schema' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}
	/**
	 * Handle updating plugin options.
	 */
	public function handle_update_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
		}
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$options = json_decode( wp_unslash( $_POST['payload'] ?? '{}' ), true );
		if ( empty( $options ) || ! is_array( $options ) ) {
			wp_send_json_error( [ 'message' => 'Invalid Data' ] );
		}

		// Remove action & nonce.
		unset( $options['action'], $options['security'] );

		// Whitelist allowed option names to prevent arbitrary option overwrites.
		$allowed_prefixes = [ 'rtrs_' ];
		foreach ( $options as $option_name => $settings ) {
			$is_allowed = false;
			foreach ( $allowed_prefixes as $prefix ) {
				if ( strpos( $option_name, $prefix ) === 0 ) {
					$is_allowed = true;
					break;
				}
			}
			if ( ! $is_allowed ) {
				continue;
			}

			$sanitized_new_settings = apply_filters( 'rtrs_settings_api_sanitized_fields_' . $option_name, $settings, $this );
			do_action( 'rtrs_admin_settings_before_ddd_' . $option_name, $sanitized_new_settings, $settings, $this );
			update_option( $option_name, $sanitized_new_settings );
			// Fire the same hook the legacy save() uses so Pro can activate licences, etc.
			$section = str_replace( 'rtrs_', '', $option_name );
			do_action( 'rtrs_admin_settings_saved', $section, $this );
		}
		wp_send_json_success(
			[
				'message' => 'Updated successfully',
			]
		);
	}
	/**
	 * Handle retrieving plugin options.
	 */
	public function handle_get_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
		}
		$settingsTabs   = json_decode( sanitize_text_field( wp_unslash( $_REQUEST['settingsTabs'] ?? '' ) ), true ) ?: [];
		$settingSubTabs = json_decode( sanitize_text_field( wp_unslash( $_REQUEST['settingSubTabs'] ?? '' ) ), true ) ?: [];
		$allFields      = $this->get_all_fields();
		// Retrieve requested keys or all.
		$options = [];
		$prefix  = 'rtrs_';
		if ( ! empty( $settingsTabs ) && is_array( $settingsTabs ) ) {
			foreach ( array_keys( $settingsTabs ) as $option_key ) {
				$rootDefaultSettings           = $this->extract_defaults( $allFields[ $option_key ] ?? [] );
				$root_key                      = $prefix . $option_key;
				$root_options_name             = $root_key . '_settings';
				$saved_value                   = get_option( $root_options_name, [] );
				$options[ $root_options_name ] = wp_parse_args( is_array( $saved_value ) ? $saved_value : [], $rootDefaultSettings );
				if ( ! empty( $settingSubTabs[ $option_key ] ) && is_array( $settingSubTabs[ $option_key ] ) ) {
					$sub_settings = array_filter( array_keys( $settingSubTabs[ $option_key ] ) );
					foreach ( $sub_settings  as $sub_key ) {
						$subDefaultSettings           = $this->extract_defaults( $allFields[ $option_key . '/' . $sub_key ] ?? [] );
						$sub_options_name             = $root_key . '_' . $sub_key . '_settings';
						$sub_saved_value              = get_option( $sub_options_name, [] );
						$options[ $sub_options_name ] = wp_parse_args( is_array( $sub_saved_value ) ? $sub_saved_value : [], $subDefaultSettings );
					}
				}
			}
		}
		wp_send_json_success( [ 'options' => $options ] );
	}
	/**
	 * Support EDD for review system.
	 *
	 * @param string
	 *
	 * @return void
	 */
	public function edd_comments() {
		add_post_type_support( 'download', 'comments' );
	}

	public function add_rtrs_menu() {
		add_menu_page(
			esc_html__( 'SchemaEngine AI', 'review-schema' ),
			esc_html__( 'SchemaEngine AI', 'review-schema' ),
			'manage_options',
			'review-schema',
			false,
			RTRS_URL . '/assets/imgs/icon-20x20.svg',
			20
		);
	}

	public function add_settings_menu() {
		add_submenu_page(
			'review-schema',
			esc_html__( 'Settings', 'review-schema' ),
			esc_html__( 'Settings', 'review-schema' ),
			'manage_options',
			'review-schema',
			[ $this, 'display_settings_form_react' ],
			20
		);

		add_submenu_page(
			'review-schema',
			esc_html__( 'Get Help', 'review-schema' ),
			esc_html__( 'Get Help', 'review-schema' ),
			'manage_options',
			'rtrs-reviews-get-help',
			[ $this, 'get_help_page' ],
			30
		);
		add_submenu_page(
			'review-schema',
			esc_html__( 'Our Plugins', 'review-schema' ),
			esc_html__( 'Our Plugins', 'review-schema' ),
			'manage_options',
			'rtrs-our-plugins',
			[ $this, 'our_plugins' ]
		);
	}
	/**
	 * get Help
	 */
	public function our_plugins() {
		require_once RTRS_PATH . 'views/pages/our-plugins.php';
	}

	/**
	 * get Help
	 */
	public function get_help_page() {
		require_once RTRS_PATH . 'views/pages/get-help.php';
	}

	/**
	 * Get all subtabs for each main tab.
	 *
	 * @return array Subtabs grouped by tab key.
	 */
	private function get_subtabs(): array {
		$subTabs = [];
		if ( empty( $this->tabs ) || ! is_array( $this->tabs ) ) {
			return $subTabs;
		}
		foreach ( $this->tabs as $tabKey => $tabLabel ) {
			$tabSubsections = [];
			// Call dynamic method if it exists.
			$method = $tabKey . '_add_subsections';
			if ( method_exists( $this, $method ) ) {
				$tabSubsections = $this->$method();
				// Ensure it's an array.
				if ( ! is_array( $tabSubsections ) ) {
					$tabSubsections = [];
				}
			}
			// Merge with any filtered sub-sections.
			$filtered = apply_filters( "rtrs_{$tabKey}_sub_sections", $tabSubsections );
			if ( is_array( $filtered ) && ! empty( $filtered ) ) {
				$tabSubsections = array_merge( $tabSubsections, $filtered );
			}
			if ( ! empty( $tabSubsections ) ) {
				$subTabs[ $tabKey ] = $tabSubsections;
			}
		}
		return $subTabs;
	}
	/**
	 * Extract default values from a fields array.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return array Associative array of field_id => default_value.
	 */
	private function extract_defaults( array $fields ): array {
		$defaults = [];
		foreach ( $fields as $id => $field ) {
			if ( is_array( $field ) && array_key_exists( 'default', $field ) ) {
				$defaults[ $id ] = $field['default'];
			}
		}
		return $defaults;
	}
	/**
	 * Get all Fields
	 * return array
	 */
	private function get_settings_value() {
		$allFields      = $this->get_all_fields();
		$settingsTabs   = $this->tabs;
		$settingSubTabs = $this->get_subtabs();
		// Retrieve requested keys or all.
		$options = [];
		$prefix  = 'rtrs_';
		if ( ! empty( $this->tabs ) && is_array( $settingsTabs ) ) {
			foreach ( array_keys( $settingsTabs ) as $option_key ) {
				$rootDefaultSettings           = $this->extract_defaults( $allFields[ $option_key ] ?? [] );
				$root_key                      = $prefix . $option_key;
				$root_options_name             = $root_key . '_settings';
				$saved_value                   = get_option( $root_options_name, [] );
				$options[ $root_options_name ] = wp_parse_args( is_array( $saved_value ) ? $saved_value : [], $rootDefaultSettings );

				if ( ! empty( $settingSubTabs[ $option_key ] ) && is_array( $settingSubTabs[ $option_key ] ) ) {
					$sub_settings = array_filter( array_keys( $settingSubTabs[ $option_key ] ) );
					foreach ( $sub_settings  as $sub_key ) {
						$subDefaultSettings           = $this->extract_defaults( $allFields[ $option_key . '/' . $sub_key ] ?? [] );
						$sub_options_name             = $root_key . '_' . $sub_key . '_settings';
						$sub_saved_value              = get_option( $sub_options_name, [] );
						$options[ $sub_options_name ] = wp_parse_args( is_array( $sub_saved_value ) ? $sub_saved_value : [], $subDefaultSettings );
					}
				}
			}
		}
		return $options;
	}
	/**
	 * Get all Fields
	 */
	private function get_all_fields() {
		$fields = [];
		if ( ! empty( $this->tabs ) ) {
			$subTabs = $this->get_subtabs();
			foreach ( $this->tabs as $tabKey => $tabLabel ) {
				$file_name = RTRS_PATH . "views/settings/{$tabKey}-settings.php";
				if ( file_exists( $file_name ) ) {
					$field             = include $file_name;
					$fields[ $tabKey ] = apply_filters( 'rtrs_settings_option_fields', $field, $tabKey, null );
				}
				if ( empty( $subTabs[ $tabKey ] ) || ! is_array( $subTabs[ $tabKey ] ) ) {
					continue;
				}
				foreach ( $subTabs[ $tabKey ] as $subTabKey => $sabTabLabel ) {
					if ( empty( $subTabKey ) ) {
						continue;
					}
					$sub_file_name = RTRS_PATH . "views/settings/{$tabKey}-{$subTabKey}-settings.php";
					if ( file_exists( $sub_file_name ) ) {
						$subfield                             = include $sub_file_name;
						$fields[ $tabKey . '/' . $subTabKey ] = apply_filters( 'rtrs_settings_option_fields', $subfield, $tabKey, $subTabKey );
					}
				}
			}
		}

		return apply_filters( 'rtrs_settings_all_option_fields', $fields );
	}

	/**
	 * Display the settings form for the plugin using React.
	 *
	 * This method enqueues the necessary styles and scripts for the settings form,
	 * localizes the script with the required parameters, and renders the settings
	 * form container.
	 *
	 * @return void
	 */
	public function display_settings_form_react() {
		// Change this to your actual page slug
		wp_enqueue_style( 'rtrs-settings' );
		wp_enqueue_script( 'rtrs-settings' );
		wp_localize_script(
			'rtrs-settings',
			'rtrsParams',
			[
				'logoUrl'            => esc_url( rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ) ),
				'admin_nonce'        => wp_create_nonce( rtrs()->getNonceId() ),
				'ajaxurl'            => admin_url( 'admin-ajax.php' ),
				'adminUrl'           => admin_url(),
				'version'            => RTRS_VERSION,
				'settingFields'      => $this->get_all_fields(),
				'allPostTypes'       => Functions::getPostTypes( false, false ),
				'settingFieldsValue' => $this->get_settings_value(),
				'snippetAutoCats'    => Functions::rich_snippet_auto_cats(),
				'settingTabs'        => $this->tabs,
				'settingSubTabs'     => $this->get_subtabs(),
				'hasValidLicence'    => Functions::has_valid_license(),
				'hasPro'             => function_exists( 'rtrsp' ),
				'promothumb'         => esc_url( rtrs()->get_assets_uri( 'imgs/Review-Schema_Promo_thumb.webp' ) ),
				'recommendedPlugins' => $this->get_recommended_plugins(),
				'pluginInstallNonce' => wp_create_nonce( 'updates' ),
				'pluginActivateBase' => esc_url( admin_url( 'plugins.php' ) ),
			]
		);
		?>
		<div class="rtrs-settings">
			<div id="rtrs-settings-root"></div>
		</div>
		<?php
	}

	/**
	 * Return the list of recommended companion plugins for the Power Up
	 * wizard step, each annotated with install/active status.
	 *
	 * Each entry contains:
	 *  - slug        : wp.org plugin slug (matches the directory name).
	 *  - name        : Display name.
	 *  - description : Short marketing description.
	 *  - icon        : Dashicons class shown on the card.
	 *  - installed   : Whether the plugin folder is present.
	 *  - active      : Whether the plugin is currently active.
	 *
	 * @return array
	 */
	protected function get_recommended_plugins() {
		$plugins = [
			[
				'slug'        => 'radius-booking',
				'name'        => esc_html__( 'Radius Booking', 'review-schema' ),
				'description' => esc_html__( 'WordPress booking plugin for appointments, staff management...', 'review-schema' ),
				'icon'        => 'dashicons-calendar-alt',
			],
			[
				'slug'        => 'classified-listing',
				'name'        => esc_html__( 'Classified Listing', 'review-schema' ),
				'description' => esc_html__( 'AI-powered WordPress plugin for classified listings and directories.', 'review-schema' ),
				'icon'        => 'dashicons-star-filled',
			],
			[
				'slug'        => 'shopbuilder',
				'name'        => esc_html__( 'ShopBuilder', 'review-schema' ),
				'description' => esc_html__( 'Build stunning WooCommerce stores with drag-and-drop builder.', 'review-schema' ),
				'icon'        => 'dashicons-cart',
			],
			[
				'slug'        => 'tlp-food-menu',
				'name'        => esc_html__( 'Food Menu', 'review-schema' ),
				'description' => esc_html__( 'Create beautiful restaurant menus, categories, and layouts.', 'review-schema' ),
				'icon'        => 'dashicons-carrot',
			],
			[
				'slug'        => 'tlp-team',
				'name'        => esc_html__( 'Team Members', 'review-schema' ),
				'description' => esc_html__( 'Showcase your team with beautiful profiles and social links.', 'review-schema' ),
				'icon'        => 'dashicons-groups',
			],
			[
				'slug'        => 'testimonial-slider-and-showcase',
				'name'        => esc_html__( 'Testimonial Slider', 'review-schema' ),
				'description' => esc_html__( 'Display customer testimonials with responsive slider and grid layouts.', 'review-schema' ),
				'icon'        => 'dashicons-groups',
			],
			[
				'slug'        => 'woo-product-variation-gallery',
				'name'        => esc_html__( 'Variation Gallery', 'review-schema' ),
				'description' => esc_html__( 'WooCommerce plugin for unlimited additional variation image galleries.', 'review-schema' ),
				'icon'        => 'dashicons-groups',
			],
			[
				'slug'        => 'woo-product-variation-swatches',
				'name'        => esc_html__( 'Variation Swatches', 'review-schema' ),
				'description' => esc_html__( 'WooCommerce variations into images, colors, labels, and radios.', 'review-schema' ),
				'icon'        => 'dashicons-groups',
			],
		];

		// Check installed/active status.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed_plugins = get_plugins();
		$active_plugins    = (array) get_option( 'active_plugins', [] );

		foreach ( $plugins as $key => $plugin ) {
			$plugin_file = $this->get_plugin_file( $plugin['slug'], $installed_plugins );

			$plugins[ $key ]['installed'] = ! empty( $plugin_file );
			$plugins[ $key ]['active']    = ! empty( $plugin_file ) && in_array( $plugin_file, $active_plugins, true );
		}

		return $plugins;
	}

	/**
	 * Locate a plugin's main file inside the installed-plugins map by slug.
	 *
	 * Matches entries whose path starts with "{$slug}/".
	 *
	 * @param string $slug              Plugin folder slug.
	 * @param array  $installed_plugins Result of get_plugins().
	 *
	 * @return string Plugin file path relative to /plugins/, or '' if not installed.
	 */
	protected function get_plugin_file( $slug, $installed_plugins ) {
		foreach ( array_keys( $installed_plugins ) as $plugin_file ) {
			if ( 0 === strpos( $plugin_file, $slug . '/' ) ) {
				return $plugin_file;
			}
		}

		return '';
	}

	protected function add_subsections() {
		if ( ! $this->active_tab ) {
			return;
		}
		if ( method_exists( $this, $this->active_tab . '_add_subsections' ) ) {
			$this->{$this->active_tab . '_add_subsections'}();
		} else {
			$sub_sections = apply_filters( 'rtrs_' . $this->active_tab . '_sub_sections', [] );
			if ( is_array( $sub_sections ) && ! empty( $sub_sections ) ) {
				$this->subtabs = $sub_sections;
			}
		}
	}

	protected function schema_add_subsections() {
		$sub_sections = [
			''                   => [
				'label'         => esc_html__( 'Site Info', 'review-schema' ),
				'is_pro'        => false,
				'section_group' => 'IDENTITY',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="4" height="3" rx=".5"/><rect x="2" y="11" width="4" height="3" rx=".5"/><rect x="10" y="11" width="4" height="3" rx=".5"/><path d="M8 5v3M4 8h8v3"/></svg>',
			],
			'social_profiles'    => [
				'label'         => esc_html__( 'Social Profiles', 'review-schema' ),
				'is_pro'        => false,
				'section_group' => 'IDENTITY',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="3.5" cy="8" r="1.5"/><circle cx="12" cy="3.5" r="1.5"/><circle cx="12" cy="12.5" r="1.5"/><path d="M5 7l5.5-2.5M5 9l5.5 2.5"/></svg>',
			],
			'sub_organization'   => [
				'label'         => esc_html__( 'Sub Organization', 'review-schema' ),
				'is_pro'        => true,
				'section_group' => 'IDENTITY',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="11" height="11" rx="1"/><path d="M5 5h2M5 8h2M5 11h2M9 5h2M9 8h2M9 11h2"/></svg>',
			],
			'corporate_contacts' => [
				'label'         => esc_html__( 'Corporate Contacts', 'review-schema' ),
				'is_pro'        => false,
				'section_group' => 'IDENTITY',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3.5A1 1 0 014 2.5h1.5l1 3-1.5.8a8 8 0 003.7 3.7l.8-1.5 3 1V11a1 1 0 01-1 1A9 9 0 013 3.5z"/></svg>',
			],
			'post_types'         => [
				'label'         => esc_html__( 'Post Type Mapping', 'review-schema' ),
				'is_pro'        => false,
				'section_group' => 'GENERATION',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4h2.5l7 8H14"/><path d="M2 12h2.5l7-8H14"/><path d="M11.5 2L14 4l-2.5 2"/><path d="M11.5 14L14 12l-2.5-2"/></svg>',
			],
			'archive'            => [
				'label'         => esc_html__( 'Archive Page', 'review-schema' ),
				'is_pro'        => true,
				'section_group' => 'GENERATION',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="12" height="3" rx=".5"/><path d="M3 6v7a1 1 0 001 1h8a1 1 0 001-1V6"/><path d="M6.5 9h3"/></svg>',
			],
		];

		// Only show TPP tab if its settings file returns any fields.
		$tpp_fields = (array) include RTRS_PATH . 'views/settings/schema-tpp-settings.php';
		if ( ! empty( $tpp_fields ) ) {
			$sub_sections['tpp'] = [
				'label'         => esc_html__( 'Third Party Conflict', 'review-schema' ),
				'is_pro'        => false,
				'section_group' => 'GENERATION',
				'iconHtml'      => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2l6.5 11.5h-13L8 2z"/><path d="M8 6.5v3M8 12v.5"/></svg>',
			];
		}

		$this->subtabs = apply_filters( 'rtrs_schema_sub_sections', $sub_sections );
		return $this->subtabs;
	}

	/**
	 * Define sub-sections for the Review tab.
	 *
	 * @return array
	 */
	protected function review_add_subsections() {
		$sub_sections = [
			''      => [
				'label'    => esc_html__( 'Review Settings', 'review-schema' ),
				'is_pro'   => false,
				'iconHtml' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20l-7 4 2-8-6-5 8-.7L12 3l3 7.3 8 .7-6 5 2 8z"/></svg>',
			],
			'media' => [
				'label'    => esc_html__( 'Media', 'review-schema' ),
				'is_pro'   => false,
				'iconHtml' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
			],
			'misc'  => [
				'label'    => esc_html__( 'Misc', 'review-schema' ),
				'is_pro'   => false,
				'iconHtml' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>',
			],
		];

		$this->subtabs = apply_filters( 'rtrs_review_sub_sections', $sub_sections );
		return $this->subtabs;
	}

	/**
	 * @return void
	 */
	public function setTabs() {
		$tabs = [
			'general'          => [
				'iconHtml' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33a1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>',
				'label'    => esc_html__( 'General', 'review-schema' ),
			],
			'schema'           => [
				'iconHtml' => ' <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"> <rect x="3" y="3" width="7" height="7" /> <rect x="14" y="3" width="7" height="7" /> <rect x="3" y="14" width="7" height="7" /> <rect x="14" y="14" width="7" height="7" /> </svg> ',
				'label'    => esc_html__( 'Schema', 'review-schema' ),
			],
			'schema_ecommerce' => [
				'iconHtml' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"> <circle cx="9" cy="21" r="1" /> <circle cx="20" cy="21" r="1" /> <path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6" /> </svg> ',
				'label'    => esc_html__( 'E-Commerce', 'review-schema' ),
				'is_pro'   => true,
			],
			'ai'               => [
				'iconHtml' => \Rtrs\Helpers\Functions::aiIconSvg( 17, 21 ),
				'label'    => esc_html__( 'AI Settings', 'review-schema' ),
			],
			'review'           => [
				'iconHtml' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20l-7 4 2-8-6-5 8-.7L12 3l3 7.3 8 .7-6 5 2 8z" /> </svg>',
				'label'    => esc_html__( 'Review', 'review-schema' ),
			],
			'seo_report'       => [
				'iconHtml' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l3-4 3 3 4-6"/></svg>',
				'label'    => esc_html__( 'SEO Report', 'review-schema' ),
			],
		];
		// Licensing tab — only relevant while the pro add-on is active.
		if ( defined( 'RTRSP_VERSION' ) ) {
			$tabs['tools'] = [
				'iconHtml' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>',
				'label'    => esc_html__( 'Licensing', 'review-schema' ),
			];
		}
		if ( class_exists( 'KcSeoWPSchema' ) ) {
			$tabs['migration'] = [
				'iconHtml' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/><path d="M7.8 7.8L5.6 5.6m12.8 12.8l-2.2-2.2m0-9l2.2-2.2M7.8 16.2l-2.2 2.2"/></svg>',
				'label'    => esc_html__( 'Migration', 'review-schema' ),
			];
		}
		// Hook to register custom tabs.
		$tabs            = apply_filters( 'rtrs_register_settings_tabs', $tabs );
		$normalized_tabs = [];
		foreach ( $tabs as $key => $tab ) {
			// If old format: 'schema' => 'Schema'.
			if ( is_string( $tab ) ) {
				$normalized_tabs[ $key ] = [
					'iconHtml' => '',
					'label'    => $tab,
				];
				continue;
			}
			// Ensure array structure.
			if ( is_array( $tab ) ) {
				$normalized_tabs[ $key ] = [
					'iconHtml' => isset( $tab['iconHtml'] ) && is_string( $tab['iconHtml'] ) ? $tab['iconHtml'] : '',
					'label'    => isset( $tab['label'] ) ? $tab['label'] : '',
					'is_pro'   => ! empty( $tab['is_pro'] ),
				];
			}
		}
		$this->tabs = $normalized_tabs;
		// Find the active tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab parameter; sanitized via sanitize_key( wp_unslash() ) and checked against allow-list.
		$rtrs_get_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$this->option = $this->active_tab = ( '' !== $rtrs_get_tab && array_key_exists( $rtrs_get_tab, $this->tabs ) ) ? $rtrs_get_tab : 'review';
		$this->add_subsections();
		if ( ! empty( $this->subtabs ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only section parameter; sanitized via sanitize_key( wp_unslash() ) and checked against allow-list.
			$rtrs_get_section      = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
			$this->current_section = ( '' !== $rtrs_get_section && array_key_exists( $rtrs_get_section, $this->subtabs ) ) ? $rtrs_get_section : '';
			$this->option          = $this->current_section ? $this->option . '_' . $this->current_section : $this->active_tab;
			$this->option         .= '_settings';
		} else {
			$this->option = $this->option . '_settings';
		}
	}

	/**
	 * Marketing links.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function marketing_links( $links ) {
		$new_links[] = '<a target="_blank" href="' . admin_url( 'admin.php?page=review-schema' ) . '">Settings</a>';
		$new_links[] = '<a target="_blank" href="' . esc_url( 'https://www.radiustheme.com/demo/plugins/review-schema' ) . '">Demo</a>';
		$new_links[] = '<a target="_blank" href="' . esc_url( 'https://schemaengineai.com/docs/docs/ai-settings/' ) . '">Documentation</a>';
		$links       = array_merge( $new_links, $links );
		if ( ! function_exists( 'rtrsp' ) ) {
			$links[] = '<a target="_blank" style="color: #39b54a;font-weight: 700;" href="' . esc_url( 'https://www.radiustheme.com/downloads/wordpress-review-structure-data-schema-plugin?utm_source=wordpress_dashboard&utm_medium=reviewschema&utm_campaign=free' ) . '">Get Pro</a>';
		}
		return $links;
	}
}
