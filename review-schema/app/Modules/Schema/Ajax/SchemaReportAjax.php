<?php
/**
 * Schema Report AJAX handler.
 *
 * Provides the AJAX endpoint to validate manually-generated schema
 * and return a report (valid/invalid, score, errors, warnings)
 * plus optional quality evaluation (pro).
 *
 * @package Rtrs\Modules\Schema\Ajax
 * @since   1.0.0
 */

namespace Rtrs\Modules\Schema\Ajax;

use Rtrs\AI\RestApi;
use Rtrs\AI\SchemaValidator;
use Rtrs\Controllers\Admin\Meta\AddMetaBox;
use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Models\Schema;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaReportAjax
 *
 * Handles the `rtrs_get_schema_report` AJAX action used by the
 * Schema tab in the post editor metabox.
 */
class SchemaReportAjax {

	use SingletonTrait;

	/**
	 * Register AJAX actions.
	 */
	private function __construct() {
		add_action( 'wp_ajax_rtrs_get_schema_report', [ $this, 'get_schema_report' ] );
		add_action( 'wp_ajax_rtrs_save_and_validate_schema', [ $this, 'save_and_validate' ] );
	}

	/**
	 * Save schema meta fields and then validate.
	 *
	 * Accepts the serialized metabox form data, saves it to post meta,
	 * then generates and validates the schema — providing real-time report.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function save_and_validate() {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid nonce.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'review-schema' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid post ID.', 'review-schema' ) ] );
		}

		$post_obj = get_post( $post_id );
		if ( ! $post_obj ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Post not found.', 'review-schema' ) ] );
		}

		// Save the schema meta fields using AddMetaBox's save logic.
		$metabox = new AddMetaBox();
		$metabox->save_meta_data( $post_id, $post_obj );

		// Now generate and validate.
		$this->get_schema_report();
	}

	/**
	 * Generate schema for a post, validate it, and return the report.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function get_schema_report() {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid nonce.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'review-schema' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid post ID.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'review-schema' ) ] );
		}

		$post_obj = get_post( $post_id );
		if ( ! $post_obj ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Post not found.', 'review-schema' ) ] );
		}

		// Setup global post context for schema generators.
		global $post;
		$post = $post_obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		$schema       = new Schema( $post_id );
		$rich_snippet = $schema->header_schema_data();
		$rich_snippet = $schema->remove_script_wrappers( $rich_snippet );

		wp_reset_postdata();

		if ( empty( $rich_snippet ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No schema data generated. Please configure schema fields first.', 'review-schema' ) ] );
		}

		// Decode JSON-LD for validation.
		$schema_data = json_decode( $rich_snippet, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $schema_data ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to parse schema data.', 'review-schema' ) ] );
		}

		// Extract detected schema types from @graph.
		$detected_types = [];
		$graph_items    = isset( $schema_data['@graph'] ) ? $schema_data['@graph'] : $schema_data;
		if ( is_array( $graph_items ) ) {
			foreach ( $graph_items as $item ) {
				if ( ! empty( $item['@type'] ) ) {
					$type = is_array( $item['@type'] ) ? implode( '/', $item['@type'] ) : $item['@type'];
					if ( ! in_array( $type, $detected_types, true ) ) {
						$detected_types[] = $type;
					}
				}
			}
		}

		// Run validation.
		$validator  = new SchemaValidator();
		$validation = $validator->validate( $schema_data );

		// Run pro evaluation if available (use dummy placeholder for free version).
		$evaluation = apply_filters( 'rtrs_manual_schema_evaluation', RestApi::get_dummy_evaluation(), $schema_data );

		$response = [
			'validation'     => $validation,
			'evaluation'     => $evaluation,
			'detected_types' => $detected_types,
		];

		wp_send_json_success( $response );
	}
}