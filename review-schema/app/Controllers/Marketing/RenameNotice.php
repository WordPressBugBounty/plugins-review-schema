<?php
/**
 * Rebrand Announcement Notice.
 *
 * Displays a one-time admin notice informing users that Review Schema
 * has been renamed to Schema Engine AI. Includes a CTA button to the
 * Pro upgrade page and a dismiss button.
 *
 * @package Rtrs\Controllers\Marketing
 */

namespace Rtrs\Controllers\Marketing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RenameNotice
 *
 * Shows the "Review Schema is now Schema Engine AI" rebrand announcement.
 */
class RenameNotice {

	/**
	 * Option key used to remember when the user dismissed the notice.
	 *
	 * @var string
	 */
	const DISMISS_OPTION = 'rtrs_rename_notice_dismissed';

	/**
	 * Nonce action used to validate dismiss / CTA clicks.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'rtrs_rename_notice_nonce';

	/**
	 * Pro upgrade landing URL.
	 *
	 * @var string
	 */
	const PRO_URL = 'https://schemaengineai.com/?utm_source=WordPress&utm_medium=reviewschema&utm_campaign=rename_notice';

	/**
	 * Launch discount percentage shown in the notice.
	 *
	 * @var int
	 */
	const OFFER_DISCOUNT = 40;

	/**
	 * Hook into WordPress admin lifecycle.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'handle_dismiss' ], 5 );
		add_action( 'admin_notices', [ __CLASS__, 'maybe_render_notice' ] );
	}

	/**
	 * Render the notice when it has not yet been dismissed.
	 *
	 * Skipped on a small allow-list of low-value screens to avoid clutter.
	 *
	 * @return void
	 */
	public static function maybe_render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'yes' === get_option( self::DISMISS_OPTION ) ) {
			return;
		}

		global $pagenow;
		$excluded_screens = [
			'themes.php',
			'tools.php',
			'options-writing.php',
			'options-reading.php',
			'options-discussion.php',
			'options-media.php',
			'options-permalink.php',
			'options-privacy.php',
			'upload.php',
			'media-new.php',
			'import.php',
			'export.php',
			'site-health.php',
			'export-personal-data.php',
			'erase-personal-data.php',
		];

		if ( in_array( $pagenow, $excluded_screens, true ) ) {
			return;
		}

		self::render_notice();
	}

	/**
	 * Render the notice markup and styles.
	 *
	 * @return void
	 */
	protected static function render_notice() {
		$dismiss_url = esc_url(
			wp_nonce_url(
				add_query_arg( 'rtrs_rename_dismiss', '1', self::current_admin_url() ),
				self::NONCE_ACTION
			)
		);

		$pro_url     = esc_url( self::PRO_URL );
		$docs_url    = esc_url( 'https://schemaengineai.com/blog/whats-new-schema-engine-ai-3-0-0/' );
		$logo_url    = esc_url( rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ) );

		$heading     = sprintf(
			/* translators: 1: previous plugin name wrapped in a span, 2: new plugin name wrapped in a span */
			esc_html__( '%1$s is now %2$s', 'review-schema' ),
			'<span class="rtrs-rebrand-notice__name rtrs-rebrand-notice__name--old">' . esc_html__( 'Review Schema', 'review-schema' ) . '</span>',
			'<span class="rtrs-rebrand-notice__name rtrs-rebrand-notice__name--new">' . esc_html__( 'Schema Engine AI', 'review-schema' ) . '</span>'
		);
		$subheading  = esc_html__( 'Same plugin, new name — supercharged with AI', 'review-schema' );
		$message     = esc_html__( 'Your reviews, schema settings, and data are preserved automatically. Schema Engine AI adds AI-powered schema generation, FAQ drafting, and real-time Rich Results validation on top of everything you already use.', 'review-schema' );

		$btn_pro     = sprintf(
			/* translators: %d: discount percentage */
			esc_html__( 'Get %d%% OFF Pro', 'review-schema' ),
			self::OFFER_DISCOUNT
		);
		$btn_docs    = esc_html__( 'Learn What\'s New', 'review-schema' );
		$btn_dismiss = esc_html__( 'Got it, dismiss', 'review-schema' );

		$offer_label = esc_html__( 'Re-Launch deal', 'review-schema' );
		$offer_text  = sprintf(
			/* translators: %d: discount percentage */
			esc_html__( '%d%% OFF Schema Engine AI Pro — limited time. Discount automatically applied at checkout.', 'review-schema' ),
			self::OFFER_DISCOUNT
		);

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolated values pre-escaped above.
		printf(
			'<div class="notice rtrs-rebrand-notice">
				<div class="rtrs-rebrand-notice__inner">
					<div class="rtrs-rebrand-notice__logo">
						<img src="%1$s" alt="" width="64" height="64" />
					</div>
					<div class="rtrs-rebrand-notice__body">
						<div class="rtrs-rebrand-notice__head">
							<h2 class="rtrs-rebrand-notice__title">%2$s</h2>
						</div>
						<p class="rtrs-rebrand-notice__sub">%3$s</p>
						<div class="rtrs-rebrand-notice__offer" role="note">
							<span class="rtrs-rebrand-notice__offer-icon" aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
							</span>
							<span class="rtrs-rebrand-notice__offer-label">%4$s:</span>
							<span class="rtrs-rebrand-notice__offer-text">%5$s</span>
						</div>
						<p class="rtrs-rebrand-notice__desc">%6$s</p>
						<div class="rtrs-rebrand-notice__actions">
							<a href="%7$s" class="rtrs-rebrand-btn rtrs-rebrand-btn--pro" target="_blank" rel="noopener noreferrer">
								<svg class="rtrs-rebrand-btn__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
								<span>%8$s</span>
							</a>
							<a href="%9$s" class="rtrs-rebrand-btn rtrs-rebrand-btn--ghost" target="_blank" rel="noopener noreferrer">
								<span>%10$s</span>
							</a>
							<a href="%11$s" class="rtrs-rebrand-btn rtrs-rebrand-btn--link">
								<span>%12$s</span>
							</a>
						</div>
					</div>
					<a href="%11$s" class="rtrs-rebrand-notice__close" aria-label="%12$s">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</a>
				</div>
			</div>',
			$logo_url,
			$heading,
			$subheading,
			$offer_label,
			$offer_text,
			$message,
			$pro_url,
			$btn_pro,
			$docs_url,
			$btn_docs,
			$dismiss_url,
			$btn_dismiss
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		self::print_styles();
	}

	/**
	 * Print the notice CSS once per page load.
	 *
	 * @return void
	 */
	protected static function print_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		<style id="rtrs-rebrand-notice-styles">
			.rtrs-rebrand-notice {
				position: relative;
				margin: 16px 20px 16px 2px;
				padding: 0;
				border: 0;
				border-radius: 12px;
				background: #ffffff;
				box-shadow: 0 4px 18px rgba(76, 29, 149, 0.08), 0 1px 3px rgba(0, 0, 0, 0.04);
				overflow: hidden;
			}
			.rtrs-rebrand-notice::before {
				content: "";
				position: absolute;
				inset: 0 0 auto 0;
				height: 4px;
				background: linear-gradient(90deg, #7c3aed 0%, #4f46e5 50%, #6366f1 100%);
			}
			.rtrs-rebrand-notice.notice {
				padding: 0;
				border-inline-start-width: 0;
			}
			.rtrs-rebrand-notice__inner {
				display: flex;
				gap: 18px;
				align-items: flex-start;
				padding: 20px 22px 20px 22px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.rtrs-rebrand-notice__logo {
				position: relative;
				flex-shrink: 0;
				width: 64px;
				height: 64px;
				border-radius: 14px;
				background:
					radial-gradient(120% 120% at 0% 0%, rgba(255,255,255,0.85) 0%, rgba(255,255,255,0) 55%),
					linear-gradient(135deg, #ffffff 0%, #faf7ff 50%, #f3eeff 100%);
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 6px;
				border: 1px solid rgba(124, 58, 237, 0.18);
				box-shadow:
					0 1px 0 rgba(255, 255, 255, 0.9) inset,
					0 -1px 0 rgba(124, 58, 237, 0.06) inset,
					0 8px 18px -8px rgba(76, 29, 149, 0.28),
					0 2px 4px rgba(15, 23, 42, 0.06);
			}
			.rtrs-rebrand-notice__logo::after {
				content: "";
				position: absolute;
				inset: 3px;
				border-radius: 11px;
				border: 1px solid rgba(124, 58, 237, 0.08);
				pointer-events: none;
			}
			.rtrs-rebrand-notice__logo img {
				width: 100%;
				height: 100%;
				object-fit: contain;
				border-radius: 10px;
				display: block;
				filter: drop-shadow(0 1px 1px rgba(15, 23, 42, 0.12));
			}
			.rtrs-rebrand-notice__body {
				flex: 1;
				min-width: 0;
			}
			.rtrs-rebrand-notice__head {
				display: flex;
				align-items: center;
				gap: 10px;
				flex-wrap: wrap;
				margin-bottom: 4px;
			}
			.rtrs-rebrand-notice__title {
				margin: 0 !important;
				padding: 0;
				font-size: 18px;
				font-weight: 700;
				line-height: 1.4;
				color: #16151e;
				letter-spacing: -0.01em;
			}
			.rtrs-rebrand-notice__name {
				display: inline-block;
				padding: 1px 8px;
				border-radius: 6px;
				font-weight: 800;
				white-space: nowrap;
				vertical-align: baseline;
			}
			.rtrs-rebrand-notice__name--old {
				color: #6b7280;
				background: #f3f4f6;
				border: 1px solid #e5e7eb;
				text-decoration: line-through;
				text-decoration-color: rgba(107, 114, 128, 0.5);
				text-decoration-thickness: 1.5px;
			}
			.rtrs-rebrand-notice__name--new {
				color: #ffffff;
				background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 50%, #6366f1 100%);
				border: 1px solid rgba(124, 58, 237, 0.6);
				box-shadow: 0 2px 6px rgba(76, 29, 149, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.18);
				letter-spacing: 0.01em;
			}
			.rtrs-rebrand-notice__sub {
				margin: 2px 0 8px 0 !important;
				padding: 0;
				font-size: 13.5px;
				font-weight: 600;
				color: #4f46e5;
				line-height: 1.4;
			}
			.rtrs-rebrand-notice__desc {
				margin: 0 0 14px 0 !important;
				padding: 0;
				font-size: 13.5px;
				color: #4b5563;
				line-height: 1.55;
				max-width: 720px;
			}
			.rtrs-rebrand-notice__offer {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				flex-wrap: wrap;
				margin: 0 0 12px 0;
				padding: 8px 12px;
				border-radius: 8px;
				background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%);
				border: 1px solid rgba(245, 158, 11, 0.35);
				color: #78350f;
				font-size: 13px;
				line-height: 1.3;
			}
			.rtrs-rebrand-notice__offer-icon {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 22px;
				height: 22px;
				border-radius: 6px;
				background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
				color: #ffffff;
				box-shadow: 0 1px 3px rgba(245, 158, 11, 0.4);
			}
			.rtrs-rebrand-notice__offer-label {
				font-weight: 800;
				text-transform: uppercase;
				letter-spacing: 0.06em;
				font-size: 11px;
				color: #92400e;
			}
			.rtrs-rebrand-notice__offer-text {
				font-weight: 600;
				color: #78350f;
			}
			.rtrs-rebrand-notice__actions {
				display: flex;
				gap: 8px;
				align-items: center;
				flex-wrap: wrap;
			}
			.rtrs-rebrand-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				border-radius: 8px;
				font-size: 13px;
				font-weight: 600;
				line-height: 1;
				text-decoration: none !important;
				border: 1px solid transparent;
				transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease, color 120ms ease;
				white-space: nowrap;
				box-shadow: none;
			}
			.rtrs-rebrand-btn:focus {
				outline: 0;
				box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.25);
			}
			.rtrs-rebrand-btn--pro {
				color: #3a2a00 !important;
				background: linear-gradient(135deg, #fde68a 0%, #fbbf24 50%, #f59e0b 100%);
				box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.5);
				border-color: rgba(245, 158, 11, 0.5);
			}
			.rtrs-rebrand-btn--pro:hover {
				transform: translateY(-1px);
				box-shadow: 0 6px 16px rgba(245, 158, 11, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.5);
				color: #3a2a00 !important;
			}
			.rtrs-rebrand-btn--pro .rtrs-rebrand-btn__icon {
				color: #92400e;
			}
			.rtrs-rebrand-btn--ghost {
				color: #4f46e5 !important;
				background: rgba(79, 70, 229, 0.06);
				border-color: rgba(79, 70, 229, 0.2);
			}
			.rtrs-rebrand-btn--ghost:hover {
				background: rgba(79, 70, 229, 0.12);
				color: #3730a3 !important;
				border-color: rgba(79, 70, 229, 0.35);
			}
			.rtrs-rebrand-btn--link {
				color: #6b7280 !important;
				background: transparent;
				padding: 8px 10px;
			}
			.rtrs-rebrand-btn--link:hover {
				color: #16151e !important;
				background: rgba(0, 0, 0, 0.04);
			}
			.rtrs-rebrand-notice__close {
				flex-shrink: 0;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 28px;
				height: 28px;
				border-radius: 8px;
				color: #9ca3af;
				background: transparent;
				text-decoration: none !important;
				transition: background 120ms ease, color 120ms ease;
				margin-left: 4px;
			}
			.rtrs-rebrand-notice__close:hover,
			.rtrs-rebrand-notice__close:focus {
				background: rgba(0, 0, 0, 0.06);
				color: #16151e;
				outline: 0;
				box-shadow: none;
			}
			@media (max-width: 782px) {
				.rtrs-rebrand-notice__inner {
					flex-direction: column;
					padding: 18px;
				}
				.rtrs-rebrand-notice__close {
					position: absolute;
					top: 10px;
					right: 10px;
				}
				.rtrs-rebrand-notice__actions {
					gap: 6px;
				}
				.rtrs-rebrand-btn {
					padding: 8px 12px;
				}
			}
		</style>
		<?php
	}

	/**
	 * Handle the dismiss click. Validates the nonce and persists the
	 * dismissed flag so the notice is hidden going forward.
	 *
	 * @return void
	 */
	public static function handle_dismiss() {
		if ( ! isset( $_GET['rtrs_rename_dismiss'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		update_option( self::DISMISS_OPTION, 'yes' );

		wp_safe_redirect( self::current_admin_url() );
		exit;
	}

	/**
	 * Resolve the current admin URL with our query args stripped, used
	 * as the redirect target after dismissal.
	 *
	 * @return string
	 */
	protected static function current_admin_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

		if ( ! $uri ) {
			return admin_url();
		}

		return remove_query_arg(
			[ '_wpnonce', 'rtrs_rename_dismiss' ],
			admin_url( $uri )
		);
	}
}
