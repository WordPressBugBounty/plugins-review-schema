<?php
/**
 * Auto-Generation Progress AJAX handler.
 *
 * Exposes the live progress of the AI auto-generation batch (managed by the
 * pro plugin's Rtrsp\AI\CronBatch) to the React admin UI. Reads the option
 * keys written by the pro plugin so it works without any cross-plugin coupling
 * and gracefully reports "idle" when the pro plugin is not active.
 *
 * @package Rtrs\Controllers\Ajax
 */

namespace Rtrs\Controllers\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AutoGenProgress
 *
 * Registers wp_ajax_rtrs_auto_gen_progress for polling the auto-generation
 * batch state from the React settings UI.
 */
class AutoGenProgress {

	/**
	 * Option key written by the pro plugin while a batch is in progress.
	 *
	 * @var string
	 */
	const PROGRESS_OPTION = '_rtrs_ai_batch_progress';

	/**
	 * Option key written by the pro plugin once a batch has finished.
	 *
	 * @var string
	 */
	const DONE_OPTION = '_rtrs_ai_batch_done';

	/**
	 * Constructor. Registers the AJAX hook.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_rtrs_auto_gen_progress', [ $this, 'progress' ] );
	}

	/**
	 * Return the current auto-generation batch progress as JSON.
	 *
	 * Response shape:
	 * {
	 *   status:    'idle' | 'running' | 'completed',
	 *   processed: int,
	 *   total:     int,
	 *   failed:    int,
	 *   percent:   int,
	 *   started:   int (unix timestamp; 0 when not started)
	 * }
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function progress() {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Session expired. Please reload the page.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'review-schema' ) ] );
		}

		$progress = get_option( self::PROGRESS_OPTION, [] );
		$done     = get_option( self::DONE_OPTION, false );

		// No active batch and no recorded completion — fully idle.
		if ( empty( $progress ) && empty( $done ) ) {
			wp_send_json_success(
				[
					'status'    => 'idle',
					'processed' => 0,
					'total'     => 0,
					'failed'    => 0,
					'percent'   => 0,
					'started'   => 0,
				]
			);
		}

		// Pro plugin clears PROGRESS_OPTION and sets DONE_OPTION on completion.
		if ( empty( $progress ) && ! empty( $done ) ) {
			wp_send_json_success(
				[
					'status'    => 'completed',
					'processed' => 0,
					'total'     => 0,
					'failed'    => 0,
					'percent'   => 100,
					'started'   => (int) ( is_array( $done ) ? ( $done['started'] ?? 0 ) : 0 ),
				]
			);
		}

		$total     = max( 0, (int) ( $progress['total'] ?? 0 ) );
		$completed = (int) ( $progress['completed'] ?? 0 );
		$failed    = (int) ( $progress['failed'] ?? 0 );
		$started   = (int) ( $progress['started'] ?? 0 );
		$processed = $completed + $failed;
		$percent   = $total > 0 ? min( 100, (int) round( ( $processed / $total ) * 100 ) ) : 0;
		$queue     = isset( $progress['queue'] ) && is_array( $progress['queue'] ) ? count( $progress['queue'] ) : 0;
		$status    = ( 0 === $queue && $processed >= $total && $total > 0 ) ? 'completed' : 'running';

		wp_send_json_success(
			[
				'status'    => $status,
				'processed' => $processed,
				'total'     => $total,
				'failed'    => $failed,
				'percent'   => $percent,
				'started'   => $started,
			]
		);
	}
}
