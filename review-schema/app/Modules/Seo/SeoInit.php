<?php
/**
 * SEO analysis module bootstrap.
 *
 * Lightweight entry point for the SEO channel. Registers cache invalidation
 * used by the (Pro) Page Performance criterion. Analysis itself is delivered
 * through the existing Schema report AJAX response and the editor `aiseData`
 * localization, both of which call SeoAnalyzer::analyze().
 *
 * @package Rtrs\Modules\Seo
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo;

use Rtrs\Modules\Seo\Analysis\ThemeH1Detector;
use Rtrs\Modules\Seo\Rest\SeoRestController;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoInit
 */
class SeoInit {

	use SingletonTrait;

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'save_post', [ __CLASS__, 'clear_cache' ] );

		// The theme-H1 probe verdict is theme-dependent — re-probe when the
		// theme, its templates, or Customizer output can change the rendered H1.
		add_action( 'switched_theme', [ ThemeH1Detector::class, 'flush_all' ] );
		add_action( 'customize_save_after', [ ThemeH1Detector::class, 'flush_all' ] );

		// Background source check queued after a save (see clear_cache) — kept
		// off the save request so Publish never waits on the HTTP probe.
		add_action( 'rtrs_seo_theme_h1_probe', [ ThemeH1Detector::class, 'prime' ] );

		// Register the SEO report REST route (rtrs-ai/v1/seo/{post_id}).
		SeoRestController::getInstance();

		// SEO Report settings: sanitize the sitemap URL, and drop the cached
		// sitemap parse whenever those settings are saved.
		add_filter( 'rtrs_settings_api_sanitized_fields_rtrs_seo_report_settings', [ Helpers\SitemapIndex::class, 'sanitize_settings' ] );
		add_action( 'rtrs_admin_settings_saved', [ Helpers\SitemapIndex::class, 'on_settings_saved' ] );
	}

	/**
	 * Clear cached analysis for a post on save.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function clear_cache( $post_id ) {
		delete_transient( 'rtrs_seo_perf_' . (int) $post_id );

		// Skip revision / autosave passes — they neither change the published
		// front-end nor warrant a probe.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Block-theme template / template-part edits (Site Editor) save as these
		// post types and can change the H1 markup for many pages at once, so
		// every verdict is stale.
		$post_type = get_post_type( $post_id );
		if ( 'wp_template' === $post_type || 'wp_template_part' === $post_type ) {
			ThemeH1Detector::flush_all();
			return;
		}

		// Re-probe the theme-H1 verdict: the just-saved edit may have added or
		// removed the only H1 the rendered page shows. Flush now so any
		// immediate read is fresh, then queue a background source check so the
		// next report load doesn't wait on the HTTP probe.
		ThemeH1Detector::flush( $post_id );

		$args = [ (int) $post_id ];
		if ( ! wp_next_scheduled( 'rtrs_seo_theme_h1_probe', $args ) ) {
			wp_schedule_single_event( time() + 5, 'rtrs_seo_theme_h1_probe', $args );
		}
	}
}
