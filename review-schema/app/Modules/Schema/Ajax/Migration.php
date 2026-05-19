<?php
/**
 * Migration Ajax Handler.
 *
 * Routes migration requests to the appropriate plugin-specific
 * migration class. Each third-party plugin has its own class.
 *
 * @package Rtrs\Modules\Schema\Ajax
 */

namespace Rtrs\Modules\Schema\Ajax;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migration
 *
 * Central dispatcher for schema data migration.
 * Delegates to plugin-specific classes:
 * - WpSeoSchema: WP SEO Structured Data Schema
 * - (future) Schema: Schema plugin
 */
class Migration {

	use SingletonTrait;

	/**
	 * Constructor.
	 *
	 * Registers AJAX action and initializes migration handlers.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'wp_ajax_rtrs_data_import', [ $this, 'data_import' ] );
		add_action( 'wp_ajax_rtrs_migration_progress', [ $this, 'migration_progress' ] );

		// Initialize plugin-specific migration handlers.
		WpSeoSchema::getInstance();
	}

	/**
	 * Handle AJAX data import request.
	 *
	 * Detects requested migration source and delegates
	 * to the corresponding migration class.
	 *
	 * @return void Sends JSON response.
	 */
	public function data_import() {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Session expired. Please reload the page.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'review-schema' ) ] );
		}

		$result  = false;
		$data_id = sanitize_text_field( wp_unslash( $_REQUEST['data_id'] ?? '' ) );

		switch ( $data_id ) {
			case 'wp_seo_schema':
				if ( is_plugin_active( 'wp-seo-structured-data-schema/wp-seo-structured-data-schema.php' ) || is_plugin_active( 'wp-seo-structured-data-schema-pro/wp-seo-structured-data-schema-pro.php' ) ) {
					$result = WpSeoSchema::getInstance()->start();
				}
				break;

			case 'schema':
				if ( is_plugin_active( 'schema/schema.php' ) ) {
					$result = false;
				}
				break;
		}

		if ( ! empty( $result ) ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Migration started. Processing in background...', 'review-schema' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'No data found to migrate.', 'review-schema' ) ] );
		}
	}

	/**
	 * Return current migration progress.
	 *
	 * Polled by the frontend to update the progress bar in real time.
	 *
	 * @return void Sends JSON response.
	 */
	public function migration_progress() {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Session expired.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'review-schema' ) ] );
		}

		$data_id = sanitize_text_field( wp_unslash( $_REQUEST['data_id'] ?? '' ) );

		$progress_key = '';
		switch ( $data_id ) {
			case 'wp_seo_schema':
				$progress_key = WpSeoSchema::PROGRESS_KEY;
				break;
		}

		if ( empty( $progress_key ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid migration source.', 'review-schema' ) ] );
		}

		// Process next batch before returning progress.
		switch ( $data_id ) {
			case 'wp_seo_schema':
				WpSeoSchema::getInstance()->process_batch();
				break;
		}

		$progress = get_option( $progress_key, [] );

		if ( empty( $progress ) ) {
			wp_send_json_success(
				[
					'status'    => 'idle',
					'processed' => 0,
					'total'     => 0,
					'percent'   => 0,
				]
			);
		}

		$total     = max( 1, (int) ( $progress['total'] ?? 1 ) );
		$processed = (int) ( $progress['processed'] ?? 0 );
		$status    = $progress['status'] ?? 'idle';
		$percent   = min( 100, (int) round( ( $processed / $total ) * 100 ) );

		wp_send_json_success(
			[
				'status'    => $status,
				'processed' => $processed,
				'total'     => $total,
				'percent'   => $percent,
			]
		);
	}
}
