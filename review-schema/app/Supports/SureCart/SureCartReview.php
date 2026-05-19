<?php
/**
 * SureCart Review Integration
 *
 * SureCart uses a custom API-based review system with block templates.
 * The sc_product post type does not support WordPress comments, and the
 * FSE template has no wp:comments block.
 *
 * When review-schema reviews are enabled for sc_product, this class:
 * - Adds comment support to sc_product post type.
 * - Disables SureCart's review blocks via their built-in filters.
 * - Replaces the product-review-list block output with review-schema template.
 * - Forces comments open so the review form works.
 * - Shows review summary on SureCart product edit page in admin.
 *
 * When review-schema is disabled, SureCart's own reviews render normally.
 *
 * @package Rtrs\Supports\SureCart
 */

namespace Rtrs\Supports\SureCart;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SureCartReview {

	use SingletonTrait;

	private function __construct() {
		add_action( 'init', [ $this, 'add_comment_support' ], 20 );
		add_action( 'wp', [ $this, 'maybe_replace_reviews' ] );
		add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );

		// Show review summary on SureCart admin product edit page.
		add_action( 'admin_footer', [ $this, 'render_admin_review_summary' ] );
	}

	/**
	 * Add comment support to sc_product.
	 *
	 * @return void
	 */
	public function add_comment_support() {
		add_post_type_support( 'sc_product', 'comments' );
	}

	/**
	 * Replace SureCart reviews with review-schema on single product pages.
	 *
	 * @return void
	 */
	public function maybe_replace_reviews() {
		if ( ! is_singular( 'sc_product' ) ) {
			return;
		}

		$p_meta = Functions::getMetaByPostType( 'sc_product' );
		if ( empty( $p_meta ) || empty( $p_meta['rtrs_support'][0] ) ) {
			return;
		}

		// Disable all SureCart review blocks.
		add_filter( 'surecart/review_form/enabled', '__return_false' );
		add_filter( 'surecart/review_average/enabled', '__return_false' );
		add_filter( 'surecart/review_stars/enabled', '__return_false' );
		add_filter( 'surecart/review_count/enabled', '__return_false' );
	}

	/**
	 * Force comments open on sc_product.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 *
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( 'sc_product' !== get_post_type( $post_id ) ) {
			return $open;
		}

		$p_meta = Functions::getMetaByPostType( 'sc_product' );
		if ( ! empty( $p_meta ) && ! empty( $p_meta['rtrs_support'][0] ) ) {
			return true;
		}

		return $open;
	}

	/**
	 * Render review summary on SureCart product edit page.
	 *
	 * SureCart uses a React SPA for product editing. The review summary
	 * HTML is output hidden in admin_footer, then JavaScript moves it
	 * after the "Custom Affiliate Commission" section once React renders.
	 *
	 * @return void
	 */
	public function render_admin_review_summary() {
		$screen = get_current_screen();
		if ( ! $screen || 'surecart_page_sc-products' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter check.
		if ( empty( $_GET['action'] ) || 'edit' !== $_GET['action'] || empty( $_GET['id'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter check.
		$sc_id = sanitize_text_field( wp_unslash( $_GET['id'] ) );

		// Find the WordPress post by SureCart product ID.
		$posts = get_posts( [
			'post_type'      => 'sc_product',
			'posts_per_page' => 1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
			'meta_query'     => [
				[
					'key'   => 'sc_id',
					'value' => $sc_id,
				],
			],
		] );

		if ( empty( $posts ) ) {
			return;
		}

		$post_id       = $posts[0]->ID;
		$total_ratings = ReviewFns::getTotalRatings( $post_id );
		$avg_rating    = ReviewFns::getAvgRatings( $post_id );
		$best_rating   = ReviewFns::getBestRating( $post_id );
		$worst_rating  = ReviewFns::getWorstRating( $post_id );

		ob_start();
		?>
		<div id="rtrs-sc-review-summary" style="display: none; max-width:1160px; margin-left: auto; margin-right: auto ">
			<div class="rtrs-sc-review-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;margin-top:16px;">
				<h3 style="margin:0 0 12px;font-size:15px;"><?php esc_html_e( 'Review Summary', 'review-schema' ); ?></h3>
				<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
					<span>
						<?php esc_html_e( 'Total Reviews:', 'review-schema' ); ?>
						<strong><?php echo absint( $total_ratings ); ?></strong>
					</span>
					<span>
						<?php esc_html_e( 'Average:', 'review-schema' ); ?>
						<strong><?php echo esc_html( $avg_rating ? number_format( (float) $avg_rating, 1 ) : '0.0' ); ?></strong>
						<?php
						if ( $avg_rating ) {
							echo wp_kses_post( ReviewFns::review_stars( $avg_rating, true ) );
						}
						?>
					</span>
					<span>
						<?php esc_html_e( 'Best:', 'review-schema' ); ?>
						<strong><?php echo esc_html( $best_rating ? number_format( (float) $best_rating, 1 ) : '0' ); ?></strong>
					</span>
					<span>
						<?php esc_html_e( 'Worst:', 'review-schema' ); ?>
						<strong><?php echo esc_html( $worst_rating ? number_format( (float) $worst_rating, 1 ) : '0' ); ?></strong>
					</span>
				</div>
			</div>
		</div>
		<script>
		(function() {
			function placeSummary() {
				var summary = document.getElementById('rtrs-sc-review-summary');
				var app = document.getElementById('app');
				if (!summary || !app) {
					return;
				}

				// Use MutationObserver to detect when React renders content.
				var observer = new MutationObserver(function() {
					// Wait until #app has meaningful content.
					if (app.children.length > 0) {
						observer.disconnect();
						app.appendChild(summary);
						summary.style.display = 'block';
					}
				});

				// If React already rendered, place immediately.
				if (app.children.length > 0) {
					app.appendChild(summary);
					summary.style.display = 'block';
				} else {
					observer.observe(app, { childList: true });
				}
			}

			// Wait for page to fully load before starting.
			if (document.readyState === 'complete') {
				setTimeout(placeSummary, 300);
			} else {
				window.addEventListener('load', function() {
					setTimeout(placeSummary, 300);
				});
			}
		})();
		</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
