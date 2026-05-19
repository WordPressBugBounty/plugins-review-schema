<?php

namespace Rtrs\Modules\Schema\Admin\Meta;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Helpers\SerpPreviewHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SerpPreview {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_rtrs_serp_preview_refresh', [ $this, 'ajax_refresh' ] );
	}

	/**
	 * Enqueue CSS/JS on post edit screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		global $post;
		if ( ! $post || ! Functions::schema_enabled() ) {
			return;
		}

		wp_enqueue_style( 'rtrs-serp-preview' );
		wp_enqueue_script( 'rtrs-serp-preview' );

		// Pass initial SERP data + base info for client-side rendering.
		$serp_data = SerpPreviewHelper::extract( $post->ID );

		wp_localize_script( 'rtrs-serp-preview', 'rtrs_serp', [
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'nonceId'  => rtrs()->getNonceId(),
			'nonce'    => wp_create_nonce( rtrs()->getNonceId() ),
			'serpData' => $serp_data,
		] );
	}

	/**
	 * AJAX handler: refresh SERP preview content.
	 */
	public function ajax_refresh() {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$serpData = SerpPreviewHelper::extract( $post_id );
		$html     = rtrs()->render( 'metas.single.serp-preview-content', compact( 'serpData' ), true );

		wp_send_json_success( [ 'html' => $html ] );
	}
}
