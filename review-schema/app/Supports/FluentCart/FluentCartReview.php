<?php
/**
 * FluentCart Review Integration
 *
 * FluentCart does not have a built-in review/rating system.
 * The `fluent-products` post type also lacks comment support by default.
 *
 * When review-schema reviews are enabled for fluent-products, this class:
 * - Adds comment support to the fluent-products post type.
 * - Forces comments open so the standard comments_template() flow works,
 *   which triggers ReviewFrontend's comment_template filter to render reviews.
 * - Shows review summary on FluentCart product edit page in admin.
 *
 * @package Rtrs\Supports\FluentCart
 */

namespace Rtrs\Supports\FluentCart;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentCartReview {

	use SingletonTrait;

	private function __construct() {
		// Add comment support to fluent-products post type.
		add_action( 'init', [ $this, 'add_comment_support' ], 20 );

		// Force comments open so comment_form() works.
		add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );

		// Add top margin for review section on FluentCart products.
		add_action( 'wp_head', [ $this, 'inline_style' ] );

		// Show review summary on FluentCart admin product edit page.
		add_action( 'admin_footer', [ $this, 'render_admin_review_summary' ] );
		add_action( 'wp_ajax_rtrs_fluentcart_review_summary', [ $this, 'ajax_review_summary' ] );
	}

	/**
	 * Add comment support to fluent-products post type.
	 *
	 * FluentCart doesn't register comments support by default.
	 * Once added, the theme's single template calls comments_template(),
	 * and ReviewFrontend's comment_template filter (priority 99)
	 * replaces it with review-schema's reviews.php.
	 *
	 * @return void
	 */
	public function add_comment_support() {
		add_post_type_support( 'fluent-products', 'comments' );
	}

	/**
	 * Add top gap for the review section on FluentCart product pages.
	 *
	 * @return void
	 */
	public function inline_style() {
		if ( ! is_singular( 'fluent-products' ) ) {
			return;
		}
		echo '<style>.rtrs-review-post-type-fluent-products{margin-top:40px}</style>';
	}

	/**
	 * Force comments open on fluent-products so comment_form() works.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 *
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( 'fluent-products' !== get_post_type( $post_id ) ) {
			return $open;
		}

		$p_meta = Functions::getMetaByPostType( 'fluent-products' );
		if ( ! empty( $p_meta ) && ! empty( $p_meta['rtrs_support'][0] ) ) {
			return true;
		}

		return $open;
	}

	/**
	 * AJAX handler to return review summary HTML for a FluentCart product.
	 *
	 * @return void
	 */
	public function ajax_review_summary() {
		check_ajax_referer( 'rtrs_fluentcart_review_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || 'fluent-products' !== get_post_type( $post_id ) ) {
			wp_send_json_error();
		}

		$total_ratings = ReviewFns::getTotalRatings( $post_id );
		$avg_rating    = ReviewFns::getAvgRatings( $post_id );
		$best_rating   = ReviewFns::getBestRating( $post_id );
		$worst_rating  = ReviewFns::getWorstRating( $post_id );

		ob_start();
		?>
		<div class="rtrs-fc-review-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;margin-top:16px;">
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
		<?php
		wp_send_json_success( [ 'html' => ob_get_clean() ] );
	}

	/**
	 * Render review summary on FluentCart product edit page.
	 *
	 * FluentCart uses a React SPA with hash-based routing. The product ID
	 * is in the URL hash (e.g. #/products/123), not accessible from PHP.
	 * JavaScript watches for hash changes, extracts the product ID, and
	 * fetches review data via AJAX to display the summary.
	 *
	 * @return void
	 */
	public function render_admin_review_summary() {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_fluent-cart' !== $screen->id ) {
			return;
		}

		$nonce = wp_create_nonce( 'rtrs_fluentcart_review_nonce' );
		?>
		<div id="rtrs-fc-review-summary" style="display:none;max-width:1160px;"></div>
		<script>
		(function() {
			var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce = '<?php echo esc_js( $nonce ); ?>';
			var currentProductId = null;
			var pendingHtml = null;

			function getProductIdFromHash() {
				var hash = window.location.hash;
				var match = hash.match(/^#\/products\/(\d+)$/);
				return match ? parseInt(match[1], 10) : null;
			}

			function loadReviewSummary(productId) {
				if (currentProductId === productId) {
					// Already loaded but may not be placed yet — retry placement.
					if (productId && pendingHtml) {
						placeSummary();
					}
					return;
				}
				currentProductId = productId;

				var summary = document.getElementById('rtrs-fc-review-summary');
				if (!summary) {
					return;
				}

				if (!productId) {
					summary.style.display = 'none';
					summary.innerHTML = '';
					pendingHtml = null;
					currentProductId = null;
					return;
				}

				var formData = new FormData();
				formData.append('action', 'rtrs_fluentcart_review_summary');
				formData.append('nonce', nonce);
				formData.append('post_id', productId);

				fetch(ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success && data.data.html) {
						pendingHtml = data.data.html;
						summary.innerHTML = pendingHtml;
						placeSummary();
					} else {
						summary.style.display = 'none';
						summary.innerHTML = '';
						pendingHtml = null;
					}
				})
				.catch(function() {
					summary.style.display = 'none';
					pendingHtml = null;
				});
			}

			function placeSummary() {
				var summary = document.getElementById('rtrs-fc-review-summary');
				if (!summary || !pendingHtml) {
					return;
				}

				// Already placed in the correct container.
				var body = document.querySelector('.single-page-body');
				if (body && body.contains(summary)) {
					summary.style.display = 'block';
					return;
				}

				var attempts = 0;
				var maxAttempts = 100;
				var interval = setInterval(function() {
					attempts++;
					var target = document.querySelector('.single-page-body');
					if (target) {
						clearInterval(interval);
						target.appendChild(summary);
						summary.style.display = 'block';
					} else if (attempts >= maxAttempts) {
						clearInterval(interval);
					}
				}, 300);
			}

			function checkRoute() {
				var productId = getProductIdFromHash();
				loadReviewSummary(productId);
			}

			window.addEventListener('hashchange', function() {
				// Reset so re-navigation triggers fresh load.
				currentProductId = null;
				pendingHtml = null;
				var summary = document.getElementById('rtrs-fc-review-summary');
				if (summary) {
					summary.style.display = 'none';
					summary.innerHTML = '';
				}
				checkRoute();
			});

			// Wait for SPA to initialize before first check.
			if (document.readyState === 'complete') {
				setTimeout(checkRoute, 500);
			} else {
				window.addEventListener('load', function() {
					setTimeout(checkRoute, 500);
				});
			}
		})();
		</script>
		<?php
	}
}
