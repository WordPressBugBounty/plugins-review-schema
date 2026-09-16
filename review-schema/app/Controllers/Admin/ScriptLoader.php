<?php

namespace Rtrs\Controllers\Admin;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScriptLoader {

	private $suffix;
	private $version;
	private $ajaxurl;

	function __construct() {
		$this->suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : RTRS_VERSION;
		$this->ajaxurl = admin_url( 'admin-ajax.php' );
		add_action( 'admin_init', [ $this, 'register_admin_script' ], 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_script_setting_page' ] );
	}

	function register_script_both_end() {
		wp_register_style( 'rtrs-app', rtrs()->get_assets_uri( 'css/app.css' ), [], $this->version );
		wp_register_script( 'featherlight', rtrs()->get_assets_uri( 'vendor/featherlight/featherlight.min.js' ), [ 'jquery' ], $this->version, true );
		wp_register_script( 'rtrs-app', rtrs()->get_assets_uri( 'js/app.js' ), [ 'jquery', 'featherlight' ], $this->version, true );

		// Dynamic Css.
		$upload_dir = wp_upload_dir();
		$cssFile    = $upload_dir['basedir'] . '/review-schema/sc.css';
		if ( file_exists( $cssFile ) ) {
			$version = filemtime( $cssFile );
			wp_register_style( 'rtrs-sc', set_url_scheme( $upload_dir['baseurl'] ) . '/review-schema/sc.css', [ 'rtrs-app' ], $version );
		}
	}

	function register_admin_script() {
		$this->register_script_both_end();

		wp_register_style( 'select2', rtrs()->get_assets_uri( 'vendor/select2/select2.min.css' ), [], $this->version );
		wp_register_style( 'rtrs-admin', rtrs()->get_assets_uri( 'css/admin.css' ), [], $this->version );
		wp_register_style( 'rtrs-settings', rtrs()->get_assets_uri( 'css/settings.css' ), [], $this->version );

		wp_register_script( 'select2', rtrs()->get_assets_uri( 'vendor/select2/select2.min.js' ), [ 'jquery' ], $this->version, true );
		wp_register_script( 'rtrs-admin', rtrs()->get_assets_uri( 'js/admin.js' ), [ 'jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-api-fetch', 'wp-data' ], rtrs()->get_assets_version( 'js/admin.js' ), true );
		wp_register_script( 'rtrs-settings', rtrs()->get_assets_uri( 'js/settings.js' ), [], $this->version, true );

		// SERP preview.
		wp_register_style( 'rtrs-serp-preview', rtrs()->get_assets_uri( 'css/serp-preview.css' ), [], $this->version );
		wp_register_script( 'rtrs-serp-preview', rtrs()->get_assets_uri( 'js/serp-preview.js' ), [], $this->version, true );
	}

	function load_admin_script_setting_page() {
		global $pagenow, $post_type;

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'select2' );
		wp_enqueue_style( 'rtrs-admin' );

		wp_enqueue_script( 'select2' );
		wp_enqueue_script( 'rtrs-admin' );
		wp_enqueue_media();

		wp_localize_script(
			'rtrs-admin',
			'rtrs',
			[
				'pro'              => function_exists( 'rtrsp' ),
				'hasApiKey'        => \Rtrs\AI\AIInit::hasApiKey(),
				'aiEnabled'        => 'yes' === \Rtrs\AI\AIInit::getSetting( 'ai_enabled', 'no' ),
				'aiSettingsUrl'    => admin_url( 'admin.php?page=review-schema#/ai' ),
				'proUrl'           => 'https://www.radiustheme.com/downloads/wordpress-review-structure-data-schema-plugin/#pricing',
				'seoActivePlugin'  => self::get_active_seo_plugin(),
				'lbCondTypes'      => \Rtrs\Helpers\Functions::getLocalBusinessCondTypes(),
				'aiIcon'           => \Rtrs\Helpers\Functions::aiIconSvg(),
				'i18n_apply_ai'    => esc_html__( 'Generate with AI', 'review-schema' ),
				'i18n_verify_ai'       => esc_html__( 'Review with AI', 'review-schema' ),
				'i18n_reverify_ai'     => esc_html__( 'Refresh AI review', 'review-schema' ),
				'i18n_remove_ai'       => esc_html__( 'Remove AI review', 'review-schema' ),
				'i18n_verifying'       => esc_html__( 'Reviewing…', 'review-schema' ),
				'i18n_ai_verified'     => esc_html__( 'AI reviewed', 'review-schema' ),
				'i18n_ai_notes_title'  => esc_html__( 'AI review notes', 'review-schema' ),
				'i18n_ai_review_failed' => esc_html__( 'AI review failed', 'review-schema' ),
				'i18n_close'           => esc_html__( 'Close', 'review-schema' ),
				'i18n_generating'  => esc_html__( 'Generating…', 'review-schema' ),
				'i18n_saving'      => esc_html__( 'Saving…', 'review-schema' ),
				'i18n_save'        => esc_html__( 'Save', 'review-schema' ),
				'i18n_discard'     => esc_html__( 'Discard', 'review-schema' ),
				'i18n_confirm_title'   => esc_html__( 'Confirm change', 'review-schema' ),
				'i18n_confirm'         => esc_html__( 'Confirm', 'review-schema' ),
				'i18n_cancel'          => esc_html__( 'Cancel', 'review-schema' ),
				'i18n_current_value'   => esc_html__( 'Current value', 'review-schema' ),
				'i18n_new_value'       => esc_html__( 'New value', 'review-schema' ),
				'i18n_saves_to'        => esc_html__( 'Saves to', 'review-schema' ),
				'i18n_dest_post_title' => esc_html__( 'Post title', 'review-schema' ),
				'i18n_dest_yoast_title'   => esc_html__( 'Yoast SEO title', 'review-schema' ),
				'i18n_dest_rankmath_title' => esc_html__( 'Rank Math SEO title', 'review-schema' ),
				'i18n_dest_seo_title'  => esc_html__( 'SEO meta title', 'review-schema' ),
				'i18n_dest_meta_desc'  => esc_html__( 'Meta description', 'review-schema' ),
				'i18n_dest_focus_keyword' => esc_html__( 'Focus keyword', 'review-schema' ),
				'i18n_notice_focus_keyword' => esc_html__( 'Focus keyword saved.', 'review-schema' ),
				'i18n_notice_post_title' => esc_html__( 'Post title updated.', 'review-schema' ),
				'i18n_notice_yoast_title'  => esc_html__( 'Yoast SEO title updated.', 'review-schema' ),
				'i18n_notice_rankmath_title' => esc_html__( 'Rank Math SEO title updated.', 'review-schema' ),
				'i18n_notice_seo_title'  => esc_html__( 'SEO meta title updated.', 'review-schema' ),
				'i18n_notice_meta_desc'  => esc_html__( 'Meta description updated.', 'review-schema' ),
				'i18n_also_post_title'   => esc_html__( 'Also update the post title', 'review-schema' ),
				'i18n_notice_title_and_post' => esc_html__( 'SEO title and post title updated.', 'review-schema' ),
				'i18n_notice_first_para' => esc_html__( 'First paragraph updated in the editor — click Update to save.', 'review-schema' ),
				'i18n_notice_subheading' => esc_html__( 'Subheading updated in the editor — click Update to save.', 'review-schema' ),
				'i18n_notice_keyword_density' => esc_html__( 'Paragraph updated to reduce keyword repetition — click Update to save.', 'review-schema' ),
				'i18n_apply_subheading_title' => esc_html__( 'Apply subheading', 'review-schema' ),
				'i18n_replaces_subheading'    => esc_html__( 'Replaces this subheading', 'review-schema' ),
				'i18n_replace_subheading'     => esc_html__( 'Replace subheading', 'review-schema' ),
				'i18n_no_subheading_found'    => esc_html__( 'No subheading found in your content. Copy the text and paste it where you want a heading.', 'review-schema' ),
				'i18n_notice_copied'          => esc_html__( 'Copied — paste it where you need it.', 'review-schema' ),
				'i18n_copy'                   => esc_html__( 'Copy', 'review-schema' ),
				'i18n_ai_error'               => esc_html__( 'Something went wrong. Please try again.', 'review-schema' ),
				/* translators: %s: qualitative impact level (High, Medium, or Low). */
				'i18n_est_impact'             => esc_html__( 'Estimated impact: %s', 'review-schema' ),
				'i18n_impact_high'            => esc_html__( 'High', 'review-schema' ),
				'i18n_impact_medium'          => esc_html__( 'Medium', 'review-schema' ),
				'i18n_impact_low'             => esc_html__( 'Low', 'review-schema' ),
				'nonceID'          => rtrs()->getNonceId(),
				'nonce'            => rtrs()->getNonceText(),
				'admin_nonce'      => wp_create_nonce( rtrs()->getNonceId() ),
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'sure_txt'         => esc_html__( 'Are you sure to delete?', 'review-schema' ),
				'criteria_alt_txt' => esc_html__( '3 criteria field are allowed for free version.', 'review-schema' ),
				'pros_alt_txt'     => esc_html__( '3 pros field are allowed for free version.', 'review-schema' ),
				'cons_alt_txt'     => esc_html__( '3 cons field are allowed for free version.', 'review-schema' ),
				'multiple_txt'     => esc_html__( 'Multiple schema are not allowed in free version.', 'review-schema' ),
				'write_txt'        => esc_html__( 'Write here!', 'review-schema' ),
				'at_least_txt'     => esc_html__( 'At least one field require', 'review-schema' ),
				'criteria_rating'  => esc_html__( '3 criteria rating field are allowed for free version.', 'review-schema' ),
				'remove_img'       => esc_html__( 'Remove Image', 'review-schema' ),
				'i18n_schema_report_loading' => esc_html__( 'Analyzing schema…', 'review-schema' ),
				'i18n_ai_notice'     => esc_html__( 'Schema is generated by AI. Please check the AI panel to view or edit.', 'review-schema' ),
				'i18n_ai_notice_sub' => esc_html__( 'After deleting AI data, manual generation fields will be visible.', 'review-schema' ),
			]
		);
	}

	/**
	 * Detect the active SEO plugin for the "Apply with AI" confirm/notice UI.
	 *
	 * @return string 'yoast' | 'rank_math' | 'none'.
	 */
	private static function get_active_seo_plugin() {
		if ( \Rtrs\Modules\Schema\Hooks\SeoHooks::isYoastActive() ) {
			return 'yoast';
		}
		if ( \Rtrs\Modules\Schema\Hooks\SeoHooks::isRankMathActive() ) {
			return 'rank_math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'seopress';
		}
		if ( function_exists( 'genesis' ) || defined( 'PARENT_THEME_VERSION' ) ) {
			return 'genesis';
		}
		return 'none';
	}
}
