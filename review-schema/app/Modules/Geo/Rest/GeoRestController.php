<?php
/**
 * REST controller for the GEO report (per-post, keyed by ID).
 *
 * Mirrors the SEO / AEO report routes. Deterministic only — no provider calls.
 * Accepts an optional live `content` override so the editor can re-analyze on
 * change without a save or reload.
 *
 * @package Rtrs\Modules\Geo\Rest
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Rest;

use Rtrs\AI\AiVerifyStore;
use Rtrs\Modules\Geo\Analyzers\GeoAnalyzer;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeoRestController
 */
class GeoRestController {

	use SingletonTrait;

	/**
	 * REST namespace (matches the plugin's existing AI routes).
	 */
	const REST_NAMESPACE = 'rtrs-ai/v1';

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the GEO report route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/geo/(?P<post_id>\d+)',
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ $this, 'get_report' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'post_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'content' => [
						'required'          => false,
						'sanitize_callback' => 'wp_kses_post',
					],
					'verify'  => [
						'required'          => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'clear'   => [
						'required'          => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * Permission check — the user must be able to edit the target post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function check_permission( $request ) {
		$post_id = absint( $request['post_id'] );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Build the GEO report for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_report( $request ) {
		$post_id = absint( $request['post_id'] );

		$opts = [];
		if ( $request->has_param( 'content' ) ) {
			$opts['content'] = (string) $request['content'];
		}

		$channel = GeoAnalyzer::analyze( $post_id, $opts );

		if ( ! empty( $request['clear'] ) ) {
			// Remove any saved AI review — revert to the deterministic channel.
			AiVerifyStore::clear( 'geo', $post_id );
		} elseif ( ! empty( $request['verify'] ) && has_filter( 'rtrs_geo_ai_verify' ) ) {
			// Optional AI verification (Pro layers a bounded re-score on the hook).
			$context  = new AnalysisContext( $post_id, isset( $opts['content'] ) ? $opts['content'] : null );
			$verified = apply_filters( 'rtrs_geo_ai_verify', $channel, $post_id, $context );
			if ( is_wp_error( $verified ) ) {
				return $verified; // Surface the provider error to the editor.
			}
			AiVerifyStore::save( 'geo', $post_id, $channel, $verified );
			$channel = $verified;
		} else {
			// Re-apply a saved verification while its signature still matches.
			$channel = AiVerifyStore::restore( 'geo', $post_id, $channel );
		}

		return rest_ensure_response(
			[
				'post_id' => $post_id,
				'geo'     => $channel,
			]
		);
	}
}
