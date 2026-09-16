<?php
/**
 * REST controller for the SEO report (per-post, keyed by ID).
 *
 * Exposes the deterministic Title & Meta analysis under the plugin's existing
 * REST namespace. The AI refinement is a separately-triggered path: it only
 * runs when `?ai=true` is requested AND a provider key is configured — the
 * baseline never calls the provider.
 *
 * @package Rtrs\Modules\Seo\Rest
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Rest;

use Rtrs\AI\AiVerifyStore;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\Grade;
use Rtrs\Modules\Seo\Analysis\SeoSuggestions;
use Rtrs\Modules\Seo\Analyzers\HeadingHierarchyAnalyzer;
use Rtrs\Modules\Seo\Analyzers\InternalLinksAnalyzer;
use Rtrs\Modules\Seo\Analyzers\SeoAnalyzer;
use Rtrs\Modules\Seo\Analyzers\TitleMetaAnalyzer;
use Rtrs\Modules\Seo\Helpers\SeoMeta;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoRestController
 */
class SeoRestController {

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
	 * Register the SEO report route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/(?P<post_id>\d+)',
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ $this, 'get_report' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'post_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'ai'      => [
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'verify'  => [
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'clear'   => [
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'title'   => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'meta'    => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'content' => [
						'required'          => false,
						'sanitize_callback' => 'wp_kses_post',
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
	 * Build the SEO report for a post.
	 *
	 * Returns the full SEO channel plus the Title & Meta dimension. When `title`
	 * and/or `meta` are supplied, those live (unsaved) values are scored instead
	 * of the saved ones and the whole channel is recomputed — this powers the
	 * editor's "re-analyze on change" with no save and no reload. When `ai=true`
	 * and a key is configured, the Title & Meta dimension is refined within a
	 * bounded range.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_report( $request ) {
		$post_id = absint( $request['post_id'] );
		$want_ai = rest_sanitize_boolean( $request['ai'] );

		// Live overrides — render any Yoast/Rank Math template tokens first so
		// the measured length matches what actually renders.
		$opts = [];
		if ( $request->has_param( 'title' ) || $request->has_param( 'meta' ) ) {
			$title = $request->has_param( 'title' )
				? SeoMeta::render_template( (string) $request['title'], $post_id )
				: SeoMeta::resolve_title( $post_id )['value'];
			$meta  = $request->has_param( 'meta' )
				? SeoMeta::render_template( (string) $request['meta'], $post_id )
				: SeoMeta::resolve_description( $post_id )['value'];

			// AIOSEO (own table) and SEOPress (lazy-rendered metabox tab) cannot be
			// reliably read by the editor's live reader, so an empty posted value
			// would wrongly read as "not set" on load. When the posted value is
			// blank, fall back to the saved resolved value if its source is AIOSEO
			// or SEOPress. Yoast/Rank Math send their live store value, so their
			// blank means intentionally cleared and is left as-is.
			$dom_backed = [ 'aioseo', 'seopress' ];
			if ( '' === trim( $title ) ) {
				$resolved_title = SeoMeta::resolve_title( $post_id );
				if ( in_array( $resolved_title['source'], $dom_backed, true ) ) {
					$title = $resolved_title['value'];
				}
			}
			if ( '' === trim( $meta ) ) {
				$resolved_meta = SeoMeta::resolve_description( $post_id );
				if ( in_array( $resolved_meta['source'], $dom_backed, true ) ) {
					$meta = $resolved_meta['value'];
				}
			}

			$opts['title_meta'] = [
				'title' => $title,
				'meta'  => $meta,
			];
		}

		// Live (unsaved) content for the content-based checks (headings, links).
		$content_override = $request->has_param( 'content' ) ? (string) $request['content'] : null;
		if ( null !== $content_override ) {
			$opts['content'] = $content_override;
		}

		$channel = SeoAnalyzer::analyze( $post_id, $opts );

		$title_meta = isset( $opts['title_meta'] )
			? TitleMetaAnalyzer::analyze_strings( $opts['title_meta']['title'], $opts['title_meta']['meta'] )
			: TitleMetaAnalyzer::analyze( $post_id );

		// AI is a Pro feature: free defines the seam (`rtrs_seo_ai_refine`) and ships
		// the buttons locked; Pro hooks the filter to do the bounded refinement.
		if ( $want_ai ) {
			$ai_context = new AnalysisContext( $post_id, $content_override );
			$title_meta = apply_filters( 'rtrs_seo_ai_refine', $title_meta, $post_id, $ai_context );

			// Reflect an AI-refined Title & Meta into the rendered channel + score.
			if ( isset( $title_meta['scored_by'] ) && 'ai' === $title_meta['scored_by'] ) {
				$channel = self::apply_ai_title_meta( $channel, $title_meta );
			}
		}

		// Optional channel-level AI verification (Pro layers a bounded re-score).
		$want_verify = rest_sanitize_boolean( $request['verify'] );
		$want_clear  = rest_sanitize_boolean( $request['clear'] );
		if ( $want_clear ) {
			// Remove any saved AI review — revert to the deterministic channel.
			AiVerifyStore::clear( 'seo', $post_id );
		} elseif ( $want_verify && has_filter( 'rtrs_seo_ai_verify' ) ) {
			$verify_context = new AnalysisContext( $post_id, $content_override );
			$verified       = apply_filters( 'rtrs_seo_ai_verify', $channel, $post_id, $verify_context );
			if ( is_wp_error( $verified ) ) {
				return $verified; // Surface the provider error to the editor.
			}
			AiVerifyStore::save( 'seo', $post_id, $channel, $verified );
			$channel = $verified;
		} elseif ( ! $want_ai ) {
			// Re-apply a saved verification while its signature still matches.
			$channel = AiVerifyStore::restore( 'seo', $post_id, $channel );
		}

		return rest_ensure_response(
			[
				'post_id'    => $post_id,
				'seo'        => $channel,
				'dimensions' => [
					'title_meta'        => $title_meta,
					'heading_hierarchy' => HeadingHierarchyAnalyzer::analyze( $post_id, $content_override ),
					'internal_links'    => InternalLinksAnalyzer::analyze( $post_id, $content_override ),
				],
			]
		);
	}

	/**
	 * Write an AI-refined Title & Meta dimension back into the channel.
	 *
	 * Mirrors the Pro Keyword/Performance recompute: replaces the title_meta
	 * criterion row, recomputes the weighted channel score/grade over countable
	 * criteria, and merges the AI notes into the channel suggestions.
	 *
	 * @param array $channel  The SEO channel object.
	 * @param array $analysis The AI-refined Title & Meta dimension.
	 * @return array
	 */
	private static function apply_ai_title_meta( $channel, $analysis ) {
		if ( ! is_array( $channel ) || empty( $channel['criteria'] ) ) {
			return $channel;
		}

		$details     = [];
		$suggestions = [];
		$issues      = isset( $analysis['issues'] ) ? $analysis['issues'] : [];
		foreach ( $issues as $issue ) {
			$message   = isset( $issue['message'] ) ? $issue['message'] : '';
			$details[] = $message;
			$points    = isset( $issue['points'] ) ? (int) $issue['points'] : 0;

			$suggestion = [
				'sev'    => isset( $issue['severity'] ) && 'critical' === $issue['severity'] ? 'high' : 'med',
				'title'  => $message,
				'body'   => '',
				'impact' => 0 !== $points ? sprintf( '+%d pts', abs( $points ) ) : '',
				'effort' => isset( $issue['effort'] ) ? ucfirst( $issue['effort'] ) : 'Low',
			];
			if ( isset( $issue['apply'] ) ) {
				$suggestion['apply'] = $issue['apply'];
			}
			$suggestions[] = $suggestion;
		}

		$score = max( 0, min( 100, (int) $analysis['score'] ) );
		foreach ( $channel['criteria'] as $i => $criterion ) {
			if ( ( isset( $criterion['key'] ) ? $criterion['key'] : '' ) !== 'title_meta' ) {
				continue;
			}
			$channel['criteria'][ $i ]['value']     = $score;
			$channel['criteria'][ $i ]['status']    = $score >= 65 ? 'ok' : 'warn';
			$channel['criteria'][ $i ]['issues']    = count( $details );
			$channel['criteria'][ $i ]['details']   = $details;
			$channel['criteria'][ $i ]['checks']    = isset( $analysis['checks'] ) ? $analysis['checks'] : [];
			$channel['criteria'][ $i ]['scored_by'] = 'ai';
			break;
		}

		$sum = 0;
		$wt  = 0;
		foreach ( $channel['criteria'] as $criterion ) {
			if ( ! empty( $criterion['locked'] ) || ! empty( $criterion['pending'] ) ) {
				continue;
			}
			$weight = isset( $criterion['weight'] ) ? (int) $criterion['weight'] : 0;
			$sum   += (int) $criterion['value'] * $weight;
			$wt    += $weight;
		}
		if ( $wt > 0 ) {
			$channel['score'] = (int) round( $sum / $wt );
			$channel['grade'] = Grade::from_score( $channel['score'] );
		}

		if ( ! empty( $suggestions ) ) {
			$merged                 = array_merge( isset( $channel['suggestions'] ) ? $channel['suggestions'] : [], $suggestions );
			$channel['suggestions'] = SeoSuggestions::prioritize( $merged, SeoSuggestions::CAP );
		}

		return $channel;
	}
}
