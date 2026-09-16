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

use Rtrs\AI\AIInit;
use Rtrs\AI\RestApi;
use Rtrs\AI\SchemaRenderer;
use Rtrs\AI\SchemaValidator;
use Rtrs\Controllers\Admin\Meta\AddMetaBox;
use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Models\Schema;
use Rtrs\Modules\Seo\Analyzers\SeoAnalyzer;
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

		$report = self::build_report( $post_id );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( [ 'message' => $report->get_error_message() ] );
		}

		wp_send_json_success( $report );
	}

	/**
	 * Build the full analysis report (validation, evaluation, SEO/AEO/GEO) for a post.
	 *
	 * Shared by the admin-ajax handler and the REST `report/{id}` route so both
	 * surfaces return an identical payload. Callers are responsible for the
	 * capability check; this method assumes the current user may edit the post.
	 *
	 * @param int $post_id The post ID to analyze.
	 *
	 * @return array|\WP_Error Report payload on success, WP_Error on failure.
	 */
	public static function build_report( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return new \WP_Error( 'rtrs_invalid_post', esc_html__( 'Invalid post ID.', 'review-schema' ) );
		}

		$post_obj = get_post( $post_id );
		if ( ! $post_obj ) {
			return new \WP_Error( 'rtrs_post_not_found', esc_html__( 'Post not found.', 'review-schema' ) );
		}

		// Setup global post context for schema generators.
		global $post;
		$post = $post_obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		// Schema is optional: a post may carry no structured data yet but still
		// gets an SEO/AEO/GEO report. When schema is present we add the validation
		// + readiness section; otherwise these stay null and the report renders the
		// content channels alone — keeping every surface (metabox, Elementor panel,
		// Gutenberg sidebar) consistent and always showing the report from this
		// single shared source.
		$validation     = null;
		$evaluation     = null;
		$detected_types = [];

		// Analyse whatever ACTUALLY renders on the frontend. When the post has
		// AI-generated schema, the AI renderer emits its graph (content AI schema
		// + global nodes) and the traditional output is suppressed — so the report
		// must score that same AI graph, or it would ignore the AI schema. Manual
		// (non-AI) posts fall back to the traditional Schema model graph.
		$graph_nodes = self::ai_schema_graph( $post_id );
		$is_ai_graph = ( null !== $graph_nodes );

		if ( ! $is_ai_graph ) {
			$schema       = new Schema( $post_id );
			$rich_snippet = $schema->remove_script_wrappers( $schema->header_schema_data() );

			if ( ! empty( $rich_snippet ) ) {
				$decoded = json_decode( $rich_snippet, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					$graph_nodes = isset( $decoded['@graph'] ) ? $decoded['@graph'] : $decoded;
				}
			}
		}

		wp_reset_postdata();

		if ( ! empty( $graph_nodes ) && is_array( $graph_nodes ) ) {
			// Extract detected schema types.
			foreach ( $graph_nodes as $item ) {
				if ( ! empty( $item['@type'] ) ) {
					$type = is_array( $item['@type'] ) ? implode( '/', $item['@type'] ) : $item['@type'];
					if ( ! in_array( $type, $detected_types, true ) ) {
						$detected_types[] = $type;
					}
				}
			}

			// Run validation.
			$validator  = new SchemaValidator();
			$validation = $validator->validate( $graph_nodes );

			// Run pro evaluation if available (use dummy placeholder for free version).
			// The AI graph uses the AI filter (matches the editor's own validation);
			// the manual graph uses the manual filter. Both resolve to the same
			// Pro SchemaEvaluator, so scores stay consistent across surfaces.
			$evaluation = apply_filters(
				$is_ai_graph ? 'rtrs_ai_schema_evaluation' : 'rtrs_manual_schema_evaluation',
				RestApi::get_dummy_evaluation(),
				$graph_nodes
			);
		}

		return [
			'validation'     => $validation,
			'evaluation'     => $evaluation,
			'detected_types' => $detected_types,
			'seo'            => \Rtrs\AI\AiVerifyStore::restore( 'seo', $post_id, SeoAnalyzer::analyze( $post_id ) ),
			'aeo'            => \Rtrs\AI\AiVerifyStore::restore( 'aeo', $post_id, \Rtrs\Modules\Aeo\Analyzers\AeoAnalyzer::analyze( $post_id ) ),
			'geo'            => \Rtrs\AI\AiVerifyStore::restore( 'geo', $post_id, \Rtrs\Modules\Geo\Analyzers\GeoAnalyzer::analyze( $post_id ) ),
		];
	}

	/**
	 * Build the AI-generated schema @graph (content schema + global nodes) for a
	 * post, exactly as the AI renderer emits it on the frontend, so the report
	 * scores the SAME graph the site actually outputs — and never ignores the
	 * AI-generated schema.
	 *
	 * Mirrors the frontend contract in SchemaRenderer / SchemaFrontend: the AI
	 * graph only renders (and the traditional output is suppressed) when AI is
	 * enabled AND the post has saved AI content schema. Returns null otherwise so
	 * the caller falls back to the traditional Schema model graph.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array|null Flat array of @graph nodes, or null when no AI schema.
	 */
	private static function ai_schema_graph( $post_id ) {
		if ( 'yes' !== AIInit::getSetting( 'ai_enabled', 'no' ) ) {
			return null;
		}

		$content_schemas = AIInit::normalizeSchemaData( get_post_meta( $post_id, AIInit::META_KEY, true ) );

		if ( empty( $content_schemas ) ) {
			return null;
		}

		$renderer       = new SchemaRenderer();
		$global_schemas = $renderer->build_global_schemas( $post_id, $content_schemas );
		$graph          = array_merge( $content_schemas, $global_schemas );
		$graph          = apply_filters( 'rtrs_ai_schema_before_render', $graph, $post_id );

		return is_array( $graph ) ? $graph : null;
	}
}