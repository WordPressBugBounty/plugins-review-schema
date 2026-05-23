<?php
namespace Rtrs\Modules\Review\Scripts;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewScriptLoader {
	/**
	 * SingleTon
	 */
	use SingletonTrait;

	private $suffix;
	private $version;
	private $ajaxurl;
	private static $wp_localize_scripts = [];

	private function __construct() {
		$this->suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : RTRS_VERSION;
		$this->ajaxurl = admin_url( 'admin-ajax.php' );
		$this->review_script_init();
	}
	/**
	 * @return void
	 */
	public function review_script_init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_script' ] );
		add_filter( 'comment_post_redirect', [ $this, 'redirect_after_review' ] );
	}
	// Auto redirect to first review page instead of last.
	function redirect_after_review( $location ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server HTTP_REFERER; used only to skip loading when referer matches.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : $location;
		return esc_url( $referer . '#comments' );
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
	function register_script() {
		$this->register_script_both_end();

		if ( is_singular() ) {

			$p_meta = Functions::getMetaByPostType( get_post_type() );
			// Shopbuilder Support.
			if ( defined( 'RTSB_VERSION' ) && in_array( get_post_type(), [ 'rtsb_builder' ] ) ) {
				$p_meta = Functions::getMetaByPostType( 'product' );
			}
			// RTCL Listing Builder.
			if ( defined( 'RTCL_ELB_VERSION' ) && in_array( get_post_type(), [ 'rtcl_builder' ] ) ) {
				$p_meta = Functions::getMetaByPostType( 'rtcl_listing' );
			}

			if ( ! $p_meta ) {
				return;
			}

			if ( empty( $p_meta['rtrs_support'][0] ) ) {
				return;
			}

			wp_enqueue_style( 'rtrs-app' );
			$recaptcha         = ( isset( $p_meta['recaptcha'] ) && $p_meta['recaptcha'][0] == '1' );
			$recaptcha_sitekey = rtrs()->get_options( 'rtrs_review_settings', [ 'recaptcha_sitekey', '' ] );
			if ( $recaptcha && $recaptcha_sitekey ) {
				wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recaptcha_sitekey ), [], RTRS_VERSION, true );
			}
			wp_enqueue_script( 'rtrs-app' );

			$pros_cons_limit   = isset( $p_meta['pros_cons_limit'] ) ? esc_attr( $p_meta['pros_cons_limit'][0] ) : 3;
			$pro_cons_limit    = ( ! function_exists( 'rtrsp' ) ) ? 3 : $pros_cons_limit;
			$free_version_text = ( ! function_exists( 'rtrsp' ) ) ? esc_html__( ' for free version.', 'review-schema' ) : '';

			wp_localize_script(
				'rtrs-app',
				'rtrs',
				[
					'pro'               => function_exists( 'rtrsp' ),
					'recaptcha'         => $recaptcha,
					'recaptcha_sitekey' => $recaptcha_sitekey,
					'highlight'         => esc_html__( 'Highlight?', 'review-schema' ),
					'remove_highlight'  => esc_html__( 'Remove Highlight?', 'review-schema' ),
					'loading'           => esc_html__( 'Loading...', 'review-schema' ),
					'edit'              => esc_html__( 'Edit', 'review-schema' ),
					'upload_img'        => esc_html__( 'Upload Image', 'review-schema' ),
					'upload_video'      => esc_html__( 'Upload Video', 'review-schema' ),
					'sure_txt'          => esc_html__( 'Are you sure to delete?', 'review-schema' ),
					'pro_label'         => esc_html__( '[PRO]', 'review-schema' ),
					'pro_cons_limit'    => $pro_cons_limit,
					'pros_alt_txt'      => $pro_cons_limit . esc_html__( ' pros field are allowed', 'review-schema' ) . $free_version_text,
					'cons_alt_txt'      => $pro_cons_limit . esc_html__( ' cons field are allowed', 'review-schema' ) . $free_version_text,
					'write_txt'         => esc_html__( 'Write here!', 'review-schema' ),
					'nonce'             => wp_create_nonce( rtrs()->getNonceId() ),
					'ajaxurl'           => admin_url( 'admin-ajax.php' ),
					'post_id'           => get_the_ID(),
					'current_page'      => get_query_var( 'cpage' ) ? get_query_var( 'cpage' ) : 1,
					'invalid_url_msg'       => esc_html__( 'Please enter a valid image URL.', 'review-schema' ),
					'invalid_video_url_msg' => esc_html__( 'Please enter a valid video URL.', 'review-schema' ),
				]
			);
		}

		wp_enqueue_style( 'rtrs-sc' );
	}
}
