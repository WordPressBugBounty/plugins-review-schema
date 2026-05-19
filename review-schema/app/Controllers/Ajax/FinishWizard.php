<?php

namespace Rtrs\Controllers\Ajax;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX actions for installing and activating plugins
 * inside the RTRS admin interface.
 */
class FinishWizard {

	/**
	 * Constructor: Registers hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_rtrs_finishing_wizard', [ $this, 'finishing_wizard' ] );
	}
	/**
	 * AJAX: Install plugin from WordPress.org repo.
	 *
	 * @return void
	 */
	public function finishing_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
		}
		$general_options = get_option(
			'rtrs_general_settings',
			[
				'review_enabled' => 'yes',
				'schema_enabled' => '',
			]
		);
		$post_types      = $general_options['review_post_types'] ?? [];
		$is              = 'yes' === ( $general_options['review_enabled'] ?? '' ) && ! empty( $post_types );
		if ( $is ) {
			$postTypes = array_keys( Functions::getPostTypes( false, false ) );
			foreach ( $post_types as $post_type ) {
				if ( ! in_array( $post_type, $postTypes, true ) ) {
					continue;
				}
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				// Necessary meta query to look up the per-post-type config row; result limited to 1 record.
				$has_post = get_posts(
					[
						'post_type'      => rtrs()->getPostType(),
						'posts_per_page' => 1, // 🔑 only one
						'post_status'    => 'any',
						'fields'         => 'ids',
						'meta_query'     => [
							[
								'key'     => 'rtrs_post_type',
								'value'   => $post_type,
								'compare' => '=',
							],
						],
					]
				);
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				if ( ! empty( $has_post ) ) {
					continue;
				}
				/**
				 * If not found, create a new post
				 * and assign the meta value.
				 */
				$post_id = wp_insert_post(
					[
						'post_type'   => rtrs()->getPostType(),
						'post_status' => 'publish',
						'post_title'  => ucfirst( $post_type ) . ' - Review',
					]
				);
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				update_post_meta( $post_id, 'rtrs_post_type', $post_type );
				update_post_meta( $post_id, 'rtrs_support', 1 );
				update_post_meta( $post_id, 'criteria', 'single' );
				update_post_meta( $post_id, 'summary_layout', 'one' );
				update_post_meta( $post_id, 'review_layout', 'two' );

			}
			wp_send_json_success();
		}
		wp_send_json_success();
	}
}
