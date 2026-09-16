<?php
/**
 * Theme H1 detector.
 *
 * The heading analyzer only sees the editor / page-builder content, never the
 * rendered front-end. Many themes output the page title as an <h1> in their
 * own template or hero markup, so a page whose content carries no H1 can still
 * render one — flagging it "missing" would be a false positive.
 *
 * This detector answers "does the rendered page actually show an H1 that the
 * content does not provide?" by probing the published permalink once and
 * caching the verdict per theme + post-type + template, so the HTTP request is
 * paid at most once per template (not per analysis / keystroke).
 *
 * @package Rtrs\Modules\Seo\Analysis
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ThemeH1Detector
 */
class ThemeH1Detector {

	/**
	 * How long a probe verdict is cached (per theme/post-type/template).
	 *
	 * Kept short so a direct theme-file edit (which fires no WordPress hook we
	 * can bust on) is re-detected within a few hours at most. Post saves, theme
	 * switches and Site-Editor template edits bust it immediately.
	 */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Whether the rendered front-end shows an H1 that the editor content does
	 * not provide (i.e. the theme template/hero/builder chrome renders it).
	 *
	 * Only call this when the analyzed content itself has zero H1s — the probe
	 * then attributes any rendered H1 to the theme. When the page cannot be
	 * probed (draft, private, non-public type, or a failed request) it returns
	 * true, assuming the common case that the theme outputs the title H1 rather
	 * than raising a false "missing H1".
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function renders_h1( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		/**
		 * Short-circuit the HTTP probe. Return a boolean to answer directly
		 * (e.g. a theme that knows it renders the title H1), or null to let the
		 * detector probe the front-end.
		 *
		 * @param bool|null $pre     Pre-computed verdict, or null to probe.
		 * @param int       $post_id Post ID.
		 */
		$pre = apply_filters( 'rtrs_seo_theme_renders_h1', null, $post_id );
		if ( null !== $pre ) {
			return (bool) $pre;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		// Only a publicly viewable, published entry can be fetched on the
		// front-end. Otherwise we cannot see the rendered page — assume the
		// theme outputs the title H1 instead of a false "missing H1".
		if ( 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return true;
		}

		$cache_key = self::cache_key( $post );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$verdict = self::probe( $post );

		// Only cache a *definitive* verdict. A failed fetch returns null — never
		// persist that as "H1 present", or a one-off loopback failure would
		// silently suppress a real "missing H1" for the whole TTL.
		if ( null === $verdict ) {
			return true;
		}

		set_transient( $cache_key, $verdict ? '1' : '0', self::CACHE_TTL );

		return $verdict;
	}

	/**
	 * Re-detect from the rendered source and warm the cache — run in the
	 * background shortly after a post is saved (see SeoInit::clear_cache) so the
	 * next report load reads a fresh verdict without paying the HTTP probe.
	 *
	 * Only probes when the post's own content has no H1: otherwise the rendered
	 * page would contain the content's H1 and the probe would wrongly credit it
	 * to the theme. When the content already has an H1 the theme-H1 verdict is
	 * irrelevant (the analyzer never asks), so there is nothing to warm.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function prime( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		if ( 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		$content_h1 = 0;
		foreach ( ( new AnalysisContext( $post_id ) )->headings() as $heading ) {
			if ( empty( $heading['is_faq'] ) && 1 === (int) $heading['level'] ) {
				$content_h1++;
			}
		}
		if ( $content_h1 > 0 ) {
			return;
		}

		// Fresh source check: clear any prior verdict, then re-probe & cache.
		self::flush( $post_id );
		self::renders_h1( $post_id );
	}

	/**
	 * Flush the cached verdict for a single post's template (called on save).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function flush( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( $post instanceof \WP_Post ) {
			delete_transient( self::cache_key( $post ) );
		}
	}

	/**
	 * Flush every cached verdict (called on theme switch — the H1 rendering is
	 * theme-dependent, so all verdicts must be re-probed).
	 *
	 * @return void
	 */
	public static function flush_all() {
		global $wpdb;

		$like    = $wpdb->esc_like( '_transient_rtrs_theme_h1_' ) . '%';
		$timeout = $wpdb->esc_like( '_transient_timeout_rtrs_theme_h1_' ) . '%';

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like,
				$timeout
			)
		);
	}

	/**
	 * Build the cache key: shared across posts using the same theme, post type
	 * and template, so identical layouts are probed only once. Switching themes
	 * changes the stylesheet and naturally invalidates the verdict.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private static function cache_key( \WP_Post $post ) {
		$is_front = (string) get_option( 'page_on_front' ) === (string) $post->ID ? 'front' : 'single';
		$parts    = [
			get_stylesheet(),
			$post->post_type,
			(string) get_page_template_slug( $post ),
			$is_front,
		];

		return 'rtrs_theme_h1_' . md5( implode( '|', $parts ) );
	}

	/**
	 * Fetch the permalink and report whether the rendered HTML contains an H1.
	 *
	 * Returns true when the rendered page has an H1, false when it definitively
	 * has none, and null when the page could not be fetched (so the caller can
	 * avoid caching an inconclusive result).
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool|null
	 */
	private static function probe( \WP_Post $post ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 5,
				'redirection' => 2,
				'sslverify'   => false,
				'user-agent'  => 'ReviewSchema-SEO-Analyzer/1.0 (+' . home_url() . ')',
				'headers'     => [ 'X-Rtrs-Seo-Probe' => '1' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return null;
		}

		// Strip <head> so a <h1> that only appears in inline SVG sprites or
		// meta is ignored — we want the visible document heading. We only reach
		// here when the editor/builder content has no H1, so any non-empty
		// <h1> in the body comes from the theme template, hero, or chrome.
		$body = preg_replace( '/<head\b[^>]*>.*?<\/head>/is', '', $body );

		return (bool) preg_match( '/<h1\b[^>]*>\s*\S.*?<\/h1>/is', (string) $body );
	}
}
