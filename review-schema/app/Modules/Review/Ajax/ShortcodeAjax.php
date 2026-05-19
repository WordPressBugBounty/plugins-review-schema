<?php

namespace Rtrs\Modules\Review\Ajax;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShortcodeAjax {

	/**
	 * SingleTon
	 */
	use SingletonTrait;

	public function __construct() {
		add_action( 'wp_ajax_rtrs_check_post_type', [ $this, 'check_post_type' ] );
	}

	function check_post_type() {

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error();
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Post ID; processed via absint().
		$post_id   = ( ! empty( $_REQUEST['post_id'] ) ) ? sanitize_text_field( $_REQUEST['post_id'] ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Post type slug; sanitized via sanitize_key().
		$post_type = ( ! empty( $_REQUEST['post_type'] ) ) ? sanitize_text_field( $_REQUEST['post_type'] ) : '';

		$scPostIds = get_posts(
			[
				'post_type'      => rtrs()->getPostType(),
				'posts_per_page' => -1,
				'post_status'    => [ 'publish', 'draft' ],
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
				'meta_query'     => [
					[
						'key'     => 'rtrs_post_type',
						'value'   => $post_type,
						'compare' => '=',
					],
				],
			]
		);

		$current_post_type = get_post_meta( $post_id, 'rtrs_post_type', true );

		if ( ( $current_post_type != $post_type ) && ! empty( $scPostIds ) ) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
	}
}
