<?php

namespace Rtrs\Modules\Review\Hooks;

use Rtcl\Controllers\Hooks\Comments;
use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Traits\SingletonTrait;
use WC_Comments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Review Front End Hooks
 */
class ReviewFrontend {
	/**
	 * SingleTon
	 */
	use SingletonTrait;

	/**
	 * Holds the field markup our comment_form() handler built for the current
	 * comment_form() invocation. Used by comment_form_default_fields() to
	 * restore our fields after themes (e.g. Astra) overwrite them at the same
	 * priority via the comment_form_default_fields filter.
	 *
	 * @var array|null
	 */
	private $stored_fields = null;

	private function __construct() {
		$this->reviews_init();
	}
	public function reviews_init() {
		// Run at priority 99 so we override theme-level customisations (e.g. Astra's
		// astra_comment_form_default_markup / astra_comment_form_default_fields_markup,
		// which both hook at default priority 10 and otherwise overwrite our review
		// fields with the theme's default comment-form markup).
		add_filter( 'comment_form_defaults', [ $this, 'comment_form' ], 99 );
		add_filter( 'comment_form_default_fields', [ $this, 'comment_form_default_fields' ], 99 );
		add_action( 'comment_post', [ $this, 'comment_review_meta_save' ] );
		add_action( 'comment_save_pre', [ $this, 'update_comment_data' ] );
		// Replace WP core's wp_die() "Duplicate comment detected" screen with an inline notice.
		add_action( 'comment_duplicate_trigger', [ $this, 'handle_duplicate_comment' ] );
		// Recalculate avg rating when a review is approved/unapproved/trashed.
		add_action( 'transition_comment_status', [ $this, 'recalculate_on_status_change' ], 10, 3 );
		// throw this into your plugin or your functions.php file to define the custom comments template.
		add_filter( 'comments_template', [ $this, 'comment_template' ], 99 );
		// Force comments_open() to return true for any post whose post type has
		// reviews enabled in the rtrs config. This guarantees the review form
		// renders regardless of the per-post Discussion → "Allow comments"
		// setting, which would otherwise close the comments_template path.
		add_filter( 'comments_open', [ $this, 'force_comments_open_for_review_post_types' ], 99, 2 );
		// adds the captcha to the WordPress form.
		add_action( 'pre_comment_on_post', [ $this, 'verify_google_recaptcha' ] );
		remove_action( 'pre_comment_on_post', [ WC_Comments::class, 'validate_product_review_verified_owners' ] );
		add_action( 'pre_comment_on_post', [ $this, 'validate_product_review_verified_owners' ] );
		// filter comment avater type.
		add_filter( 'get_avatar_comment_types', [ $this, 'comment_avater_types' ] );
		add_filter( 'rtrs_review_form_string_list', [ $this, 'review_form_string_list' ] );
		// Comment cookies.
		add_action( 'set_comment_cookies', [ $this, 'rtrs_set_comment_cookies' ] );
		add_action( 'init', [ $this, 'display_comment_cookies' ] );
		add_action( 'comment_form_before', [ $this, 'display_recaptcha_error' ] );

		// Shopbuilder Plugin Support.
		add_filter( 'rtsb/elements/elementor/reviews_settings_selecotor', [ $this, 'reviews_settings_selecotor' ], 20 );
		add_filter( 'rtsb/elements/elementor/widgets/controls/rtsb-product-tabs', [ $this, 'reviews_rtsb_product_tabs_control' ], 20 );
	}
	/**
	 * @param array $controls controls.
	 * @return array
	 */
	public function reviews_rtsb_product_tabs_control( $controls ) {
		if ( ! empty( $controls['review_star_icon_specing'] ) ) {
			$controls['review_star_icon_specing']['selectors']['{{WRAPPER}} .rtrs-review-box .rtrs-review-body .rtrs-review-meta .rtrs-review-rating i:not(:last-child)'] = 'margin-right: {{SIZE}}{{UNIT}}';
		}
		if ( ! empty( $controls['form_heading_typography'] ) ) {
			$controls['form_heading_typography']['selector'] = $controls['form_heading_typography']['selector'] . ', {{WRAPPER}} .rtrs-review-form .rtrs-form-title';
		}
		if ( ! empty( $controls['form_heading_color'] ) ) {
			$controls['form_heading_color']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-title'] = 'color: {{VALUE}} !important;';
		}
		if ( ! empty( $controls['form_title_margin'] ) ) {
			$controls['form_title_margin']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-title'] = 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; padding: 0;';
		}
		if ( ! empty( $controls['label_input_text_typography'] ) ) {
			$controls['label_input_text_typography']['selector'] = $controls['label_input_text_typography']['selector'] . ', {{WRAPPER}}  .rtrs-review-form .rtrs-form-group .rtrs-form-control';
		}

		if ( ! empty( $controls['review_input_color'] ) ) {
			$controls['review_input_color']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'color: {{VALUE}} !important;';
		}
		if ( ! empty( $controls['review_input_border_color'] ) ) {
			$controls['review_input_border_color']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'border-color: {{VALUE}};';
		}
		if ( ! empty( $controls['review_input_border_color_focus'] ) ) {
			$controls['review_input_border_color_focus']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control:focus'] = 'border-color: {{VALUE}} !important; outline-color: {{VALUE}} !important;';
		}
		if ( ! empty( $controls['review_comment_field_height'] ) ) {
			$controls['review_comment_field_height']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group textarea.rtrs-form-control'] = 'height: {{SIZE}}{{UNIT}} !important;';
		}
		if ( ! empty( $controls['review_form_rating_size'] ) ) {
			$controls['review_form_rating_size']['selectors']['{{WRAPPER}} .rtrs-rating-container > label'] = 'font-size: {{SIZE}}{{UNIT}};';
		}
		if ( ! empty( $controls['review_field_spacing'] ) ) {
			$controls['review_field_spacing']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'margin-bottom: {{SIZE}}{{UNIT}}!important;';
		}
		if ( ! empty( $controls['review_field_spacing'] ) ) {
			$controls['review_field_spacing']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'margin-bottom: {{SIZE}}{{UNIT}}!important;';
		}
		if ( ! empty( $controls['review_input_border_radius'] ) ) {
			$controls['review_input_border_radius']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'border-radius: {{SIZE}}{{UNIT}};';
		}
		if ( ! empty( $controls['review_input_padding'] ) ) {
			$controls['review_input_padding']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
		}
		if ( ! empty( $controls['review_input_padding'] ) ) {
			$controls['review_input_padding']['selectors']['{{WRAPPER}} .rtrs-review-form .rtrs-form-group .rtrs-form-control'] = 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
		}
		if ( ! empty( $controls['button_typography'] ) ) {
			$controls['button_typography']['selector'] = $controls['button_typography']['selector'] . ',{{WRAPPER}} #respond .rtrs-form-group input#submit';
		}
		if ( ! empty( $controls['submit_button_alignment'] ) ) {
			$controls['submit_button_alignment']['selectors']['{{WRAPPER}} #respond .rtrs-form-group.rtrs-review-submit-wrapper'] = 'text-align: {{VALUE}} !important;';
		}
		if ( ! empty( $controls['button_text_color_normal'] ) ) {
			$controls['button_text_color_normal']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit'] = 'color: {{VALUE}};';
		}
		if ( ! empty( $controls['button_bg_color_normal'] ) ) {
			$controls['button_bg_color_normal']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit'] = 'background-color: {{VALUE}};';
		}
		if ( ! empty( $controls['button_border'] ) ) {
			$controls['button_border']['selector'] = $controls['button_typography']['selector'] . ',{{WRAPPER}} #respond .rtrs-form-group input#submit';
		}
		if ( ! empty( $controls['button_text_color_hover'] ) ) {
			$controls['button_text_color_hover']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit:hover'] = 'color: {{VALUE}};';
		}
		if ( ! empty( $controls['button_bg_color_hover'] ) ) {
			$controls['button_bg_color_hover']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit:hover'] = 'background-color: {{VALUE}};';
		}
		if ( ! empty( $controls['button_bg_color_hover'] ) ) {
			$controls['button_bg_color_hover']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit:hover'] = 'background-color: {{VALUE}};';
		}
		if ( ! empty( $controls['button_border_hover_color'] ) ) {
			$controls['button_border_hover_color']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit:hover'] = 'border-color: {{VALUE}};';
		}
		if ( ! empty( $controls['button_border_radius'] ) ) {
			$controls['button_border_radius']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit'] = 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
		}
		if ( ! empty( $controls['button_padding'] ) ) {
			$controls['button_padding']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit'] = 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
		}
		if ( ! empty( $controls['button_margin'] ) ) {
			$controls['button_margin']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit'] = 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
		}
		if ( ! empty( $controls['submit_button_height'] ) ) {
			$controls['submit_button_height']['selectors']['{{WRAPPER}} #respond .rtrs-form-group input#submit'] = 'height: {{SIZE}}{{UNIT}}!important;';
		}

		return $controls;
	}
	/**
	 * @param array $controls selectors.
	 *
	 * @return void
	 */
	public function reviews_settings_selecotor( $selector ) {
		$selector['review_meta_color']      = $selector['review_meta_color'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-meta li:not(.rtrs-review-rating)';
		$selector['description_color']      = $selector['description_color'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-body p';
		$selector['review_border']          = $selector['review_border'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-each-review';
		$selector['review_meta_typography'] = $selector['review_meta_typography'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-meta li:not(.rtrs-review-rating)';
		$selector['review_desc_typography'] = $selector['review_desc_typography'] . ', {{WRAPPER}} .rtrs-review-box  .rtrs-review-body p';
		$selector['review_padding']         = $selector['review_padding'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-each-review';
		$selector['review_single_spacing']  = $selector['review_single_spacing'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-main-review';
		// Star Icon.
		$selector['review_star_icon_default_color'] = $selector['review_star_icon_default_color'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-rating .rtrs-star-empty';
		$selector['review_star_icon_color']         = $selector['review_star_icon_color'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-rating';
		$selector['review_star_icon_size']          = $selector['review_star_icon_size'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-rating';
		$selector['review_star_icon_margin']        = $selector['review_star_icon_margin'] . ', {{WRAPPER}} .rtrs-review-box .rtrs-review-rating';

		return $selector;
	}

	/**
	 * Comment Submitted cookies.
	 *
	 * @return void
	 */
	public function rtrs_set_comment_cookies() {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		if ( Functions::isEnableReviewByPostType( $post->post_type ) ) {
			setcookie( 'rtrs_comment_wait_approval', '1', 0, '/' );
		}
	}

	/**
	 * Comment Submitted cookies.
	 *
	 * @return void
	 */
	public function display_comment_cookies() {
		if ( isset( $_COOKIE['rtrs_comment_wait_approval'] ) && '1' === $_COOKIE['rtrs_comment_wait_approval'] ) {
			setcookie( 'rtrs_comment_wait_approval', '0', 0, '/' );
			add_action(
				'comment_form_before',
				function () {
					$message = apply_filters( 'rtrs_review_form_submited_notice', __( 'Your review has been submitted and is awaiting approval. Thank you for your feedback!', 'review-schema' ) );
					?>
					<div id="rtrs-review-success" style="margin: 20px 0; padding: 16px 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #22c55e; border-radius: 8px; display: flex; align-items: flex-start; gap: 12px;">
						<span style="flex-shrink: 0; width: 24px; height: 24px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-top: 1px;">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
						</span>
						<div>
							<strong style="display: block; color: #166534; font-size: 15px; margin-bottom: 2px;"><?php esc_html_e( 'Review Submitted Successfully!', 'review-schema' ); ?></strong>
							<span style="color: #15803d; font-size: 14px;"><?php echo esc_html( $message ); ?></span>
						</div>
					</div>
					<script>
						(function(){
							var el = document.getElementById('rtrs-review-success');
							if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
						})();
					</script>
					<?php
				}
			);
		}
	}

	/**
	 * Display reCAPTCHA error as inline notification above the review form.
	 */
	public function display_recaptcha_error() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['rtrs_error'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_code = sanitize_text_field( wp_unslash( $_GET['rtrs_error'] ) );

		$messages = [
			'recaptcha_empty'   => __( 'Please complete the reCAPTCHA verification before submitting your review.', 'review-schema' ),
			'recaptcha_failed'  => __( 'reCAPTCHA verification failed. Please try submitting your review again.', 'review-schema' ),
			'duplicate_comment' => __( 'It looks like you have already submitted this review. Please change your review text before submitting again.', 'review-schema' ),
		];

		$message = isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : '';
		if ( ! $message ) {
			return;
		}
		?>
		<div id="rtrs-recaptcha-error" style="margin: 20px 0; padding: 16px 20px; background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; border-radius: 8px; display: flex; align-items: flex-start; gap: 12px;">
			<span style="flex-shrink: 0; width: 24px; height: 24px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-top: 1px;">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</span>
			<div style="flex: 1;">
				<strong style="display: block; color: #991b1b; font-size: 15px; margin-bottom: 2px;"><?php esc_html_e( 'Review Submission Failed', 'review-schema' ); ?></strong>
				<span style="color: #b91c1c; font-size: 14px;"><?php echo esc_html( $message ); ?></span>
			</div>
			<button type="button" onclick="this.parentElement.remove();" style="flex-shrink: 0; background: none; border: none; color: #991b1b; cursor: pointer; padding: 0; font-size: 20px; line-height: 1;" aria-label="<?php esc_attr_e( 'Dismiss', 'review-schema' ); ?>">&times;</button>
		</div>
		<script>
			(function(){
				var el = document.getElementById('rtrs-recaptcha-error');
				if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
				// Strip the rtrs_error query arg so the notice does not reappear on reload.
				if (window.history && window.history.replaceState) {
					try {
						var url = new URL(window.location.href);
						url.searchParams.delete('rtrs_error');
						var clean = url.pathname + (url.search ? url.search : '') + url.hash;
						window.history.replaceState({}, document.title, clean);
					} catch (e) {}
				}
			})();
		</script>
		<?php
	}

	/**
	 * Google recaptcha check, validate and catch the spammer.
	 */
	private function is_valid_captcha( $captcha ) {
		$recaptcha_secretkey = rtrs()->get_options( 'rtrs_review_settings', [ 'recaptcha_secretkey', '' ] );
		$captcha_postdata    = http_build_query(
			[
				'secret'   => esc_attr( $recaptcha_secretkey ),
				'response' => $captcha,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			]
		);

		$api_url         = 'https://www.google.com/recaptcha/api/siteverify?' . $captcha_postdata;
		$check_recaptcha = wp_remote_get( $api_url );
		if ( is_wp_error( $check_recaptcha ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $check_recaptcha );
		if ( empty( $body ) ) {
			return false;
		}

		$decoded = json_decode( $body );
		if ( ! is_object( $decoded ) || ! isset( $decoded->success ) ) {
			return false;
		}

		return (bool) $decoded->success;
	}

	public function verify_google_recaptcha( $comment_post_id ) {
		$p_meta              = Functions::getMetaByPostType( get_post_type( $comment_post_id ) );
		$recaptcha           = ( isset( $p_meta['recaptcha'] ) && $p_meta['recaptcha'][0] == '1' );
		$recaptcha_secretkey = rtrs()->get_options( 'rtrs_review_settings', [ 'recaptcha_secretkey', '' ] );
		if ( ! $recaptcha || ! $recaptcha_secretkey ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- reCAPTCHA token submitted with the comment form; verified server-side via Google's API.
		$recaptcha_token = isset( $_POST['gRecaptchaResponse'] ) ? sanitize_text_field( wp_unslash( $_POST['gRecaptchaResponse'] ) ) : '';

		$error_code = '';
		if ( empty( $recaptcha_token ) ) {
			$error_code = 'recaptcha_empty';
		} elseif ( ! $this->is_valid_captcha( $recaptcha_token ) ) {
			$error_code = 'recaptcha_failed';
		}

		if ( $error_code ) {
			$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : get_permalink( $comment_post_id );
			$referer = remove_query_arg( 'rtrs_error', $referer );
			$referer = preg_replace( '/#.*$/', '', $referer );
			wp_safe_redirect( add_query_arg( 'rtrs_error', $error_code, $referer ) . '#rtrs-recaptcha-error' );
			exit;
		}
	}

	/**
	 * Intercept WordPress core's "Duplicate comment detected" wp_die() screen.
	 *
	 * WP core fires `comment_duplicate_trigger` right before calling wp_die()
	 * with the duplicate message. For review-enabled post types we intercept it,
	 * redirect the visitor back to the post with a query argument, and render
	 * an inline notice via display_recaptcha_error() on the next page load —
	 * matching the existing reCAPTCHA error / success UX.
	 *
	 * Skips AJAX requests so programmatic consumers still get the native 409.
	 *
	 * @param array $commentdata Incoming comment data passed by WP core.
	 * @return void
	 */
	public function handle_duplicate_comment( $commentdata ) {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( empty( $commentdata['comment_post_ID'] ) ) {
			return;
		}

		$post_id   = (int) $commentdata['comment_post_ID'];
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! Functions::isEnableReviewByPostType( $post_type ) ) {
			return;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : get_permalink( $post_id );
		$referer = remove_query_arg( 'rtrs_error', $referer );
		$referer = preg_replace( '/#.*$/', '', $referer );

		wp_safe_redirect( add_query_arg( 'rtrs_error', 'duplicate_comment', $referer ) . '#rtrs-recaptcha-error' );
		exit;
	}

	/**
	 * Force comments_open() to return true for any post whose post type has
	 * reviews enabled in the rtrs config.
	 *
	 * Ensures the review form is rendered on every post of the selected post
	 * type, even if the individual post's Discussion → "Allow comments"
	 * checkbox is disabled. If the post type is NOT review-enabled, the
	 * original $open value is preserved untouched (no side-effects on other
	 * post types).
	 *
	 * @param bool $open    Whether comments are open for the post.
	 * @param int  $post_id Post ID being checked.
	 * @return bool Filtered comments_open value.
	 */
	public function force_comments_open_for_review_post_types( $open, $post_id ) {
		if ( $open ) {
			return $open;
		}
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return $open;
		}
		if ( Functions::isEnableReviewByPostType( $post_type ) ) {
			return true;
		}
		return $open;
	}

	public function comment_template( $comment_template ) {
		global $post;
		// Use comments_open() so our force_comments_open_for_review_post_types
		// filter takes effect — this lets the review form render even when a
		// post has its individual "Allow comments" checkbox disabled.
		if ( ! ( is_singular() && ( have_comments() || comments_open( $post ) ) ) ) {
			return $comment_template;
		}
		if ( Functions::isEnableReviewByPostType( $post->post_type ) ) {
			$comment_template = Functions::get_template_part( 'reviews', [], false );
		}

		return $comment_template;
	}

	// Save the rating submitted by the user.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
	public function update_comment_data( $comment_content ) {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			return;
		}
		$comment_id = absint( $_POST['comment_ID'] ?? 0 );
		if ( ! $comment_id ) {
			return;
		}
		// not isset means not enable criteria
		$post_id = absint( $_POST['comment_post_ID'] ?? 0 );
		if ( ! isset( $_POST['rt_rating'] ) ) {
			$p_meta         = Functions::getMetaByPostType( get_post_type( $post_id ) );
			$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;
			if ( $multi_criteria ) {
				$i               = $total = $avg_rating = 0;
				$criteria_rating = [];
				foreach ( $multi_criteria as $key => $value ) {
					$slug = 'rt_rating_' . md5( $value );
					if ( isset( $_POST[ $slug ] ) && ( '' !== $_POST[ $slug ] ) ) {
						$rating = absint( $_POST[ $slug ] );
						$i++;
						$total            += $rating;
						$criteria_rating[] = $rating;
					}
				}
				update_comment_meta( $comment_id, 'rt_rating_criteria', array_map( 'absint', $criteria_rating ) );
				// add avg rating
				if ( 0 === $i ) {
					$avg_rating = 0;
				} else {
					$avg_rating = round( $total / $i, 1 );
				}

				if ( $avg_rating ) {
					update_comment_meta( $comment_id, 'rating', abs( $avg_rating ) );
				}
			}
		} else {
			update_comment_meta( $comment_id, 'rating', absint( $_POST['rt_rating'] ) );
		}

		// add title
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Review title; sanitized via sanitize_text_field() in calling code.
		if ( isset( $_POST['rt_title'] ) && ( '' !== $_POST['rt_title'] ) ) {
			update_comment_meta( $comment_id, 'rt_title', sanitize_text_field( wp_unslash( $_POST['rt_title'] ) ) );
		}

		// Allow pro plugin to save highlight & sticky review meta.
		do_action( 'rtrs_review_pro_meta_save', $comment_id, $post_id );

		// add pros & cons
		$rt_pros_cons = [];
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// Each array element is sanitized below via array_map( 'sanitize_text_field', ... ); the parent array is unslashed via wp_unslash().
		if ( ! empty( $_POST['rt_pros'] ) && is_array( $_POST['rt_pros'] ) ) {
			$rt_pros_cons['pros'] = array_map( 'sanitize_text_field', array_filter( wp_unslash( $_POST['rt_pros'] ) ) );
		}
		if ( ! empty( $_POST['rt_cons'] ) && is_array( $_POST['rt_cons'] ) ) {
			$rt_pros_cons['cons'] = array_map( 'sanitize_text_field', array_filter( wp_unslash( $_POST['rt_cons'] ) ) );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! empty( $rt_pros_cons ) ) {
			update_comment_meta( $comment_id, 'rt_pros_cons', $rt_pros_cons );
		}

		// add image & video
		$attachments = [];
		if ( isset( $_POST['rt_image_source'] ) && $_POST['rt_image_source'] == 'self' ) {
			if ( ! empty( $_POST['rt_attachment']['imgs'] ) && is_array( $_POST['rt_attachment']['imgs'] ) ) {
				$attachments['imgs']         = array_filter( array_map( 'absint', $_POST['rt_attachment']['imgs'] ) );
				$attachments['image_source'] = 'self';
			}
		} elseif ( isset( $_POST['rt_image_source'] ) && $_POST['rt_image_source'] == 'external' ) {
			if ( ! empty( $_POST['rt_external_image'] ) ) {
				$external_url = esc_url_raw( wp_unslash( $_POST['rt_external_image'] ) );
				if ( filter_var( $external_url, FILTER_VALIDATE_URL ) && preg_match( '/^https?:\/\//i', $external_url ) ) {
					$attachments['imgs']         = [ $external_url ];
					$attachments['image_source'] = 'external';
				}
			}
		} elseif ( ! empty( $_POST['rt_attachment']['imgs'] ) && is_array( $_POST['rt_attachment']['imgs'] ) ) {
			$attachments['imgs'] = array_filter( array_map( 'absint', $_POST['rt_attachment']['imgs'] ) );
		}

		// Allow pro plugin to add video attachment data.
		$attachments = apply_filters( 'rtrs_review_attachment_data', $attachments );

		if ( ! empty( $attachments ) ) {
			update_comment_meta( $comment_id, 'rt_attachment', $attachments );
		}

		if ( $post_id ) {
			$avRatingValue = ReviewFns::getAvgRatings( $post_id );
			$ratingCount   = ReviewFns::getTotalRatings( $post_id );
			update_post_meta( $post_id, 'rtrs_avg_rating', $avRatingValue );
			update_post_meta( $post_id, 'rtrs_rating_count', (int) $ratingCount );
			do_action( 'rtrs_avg_rating_meta_save', $avRatingValue, $comment_id, $post_id );
		}

		return $comment_content;
	}

	public function comment_review_meta_save( $comment_id ) {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			return;
		}
		// Extra meta field hide from comment reply
		if ( isset( $_POST['comment_parent'] ) && ( 0 != $_POST['comment_parent'] ) ) {
			return;
		}
		$post_id = absint( $_POST['comment_post_ID'] ?? 0 );
		// not isset means not enable criteria
		if ( ! isset( $_POST['rt_rating'] ) ) {

			$p_meta         = Functions::getMetaByPostType( get_post_type( $post_id ) );
			$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;

			if ( $multi_criteria ) {
				// save criteria & avg
				$i               = $total = $avg_rating = 0;
				$criteria_rating = [];
				foreach ( $multi_criteria as $key => $value ) {
					$slug = 'rt_rating_' . md5( $value );
					if ( ( isset( $_POST[ $slug ] ) ) && ( '' !== $_POST[ $slug ] ) ) {
						$rating = absint( $_POST[ $slug ] );
						$i++;
						$total            += $rating;
						$criteria_rating[] = $rating;
					}
				}
				add_comment_meta( $comment_id, 'rt_rating_criteria', array_map( 'absint', $criteria_rating ) );
				// add avg rating
				if ( 0 === $i ) {
					$avg_rating = 0;
				} else {
					$avg_rating = round( $total / $i, 1 );
				}

				if ( $avg_rating ) {
					add_comment_meta( $comment_id, 'rating', abs( $avg_rating ) );
				}
			}
		} else {
			add_comment_meta( $comment_id, 'rating', absint( $_POST['rt_rating'] ) );
		}

		// add title
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Review title; sanitized via sanitize_text_field() in calling code.
		if ( isset( $_POST['rt_title'] ) && ( '' !== $_POST['rt_title'] ) ) {
			add_comment_meta( $comment_id, 'rt_title', sanitize_text_field( wp_unslash( $_POST['rt_title'] ) ) );
		}

		// Allow pro plugin to save recommendation, anonymous, etc.
		do_action( 'rtrs_review_pro_meta_save', $comment_id, $post_id );

		$enable_gdpr = rtrs()->get_options( 'rtrs_review_settings', [ 'enable_review_gdpr_consent', 'no' ] );
		if ( 'yes' === $enable_gdpr ) {
			$rtrs_gdpr_input = isset( $_POST['rtrs_review_gdpr_consent'] ) ? sanitize_key( wp_unslash( $_POST['rtrs_review_gdpr_consent'] ) ) : 'show';
			$isShow          = 'show' === $rtrs_gdpr_input ? 'show' : 'hide';
		} else {
			$isShow = 'show';
		}
		add_comment_meta( $comment_id, 'rtrs_review_gdpr_consent', $isShow );
		// add pros & cons
		$rt_pros_cons = [];
		if ( isset( $_POST['rt_pros'] ) && is_array( $_POST['rt_pros'] ) && ( '' !== $_POST['rt_pros'] ) ) {
			$rt_pros = array_map( 'sanitize_text_field', wp_unslash( $_POST['rt_pros'] ) );
			$rt_pros = array_filter( $rt_pros );
			if ( $rt_pros ) {
				$rt_pros_cons['pros'] = $rt_pros;
			}
		}
		if ( isset( $_POST['rt_cons'] ) && is_array( $_POST['rt_cons'] ) && ( '' !== $_POST['rt_cons'] ) ) {
			$rt_cons = array_map( 'sanitize_text_field', wp_unslash( $_POST['rt_cons'] ) );
			$rt_cons = array_filter( $rt_cons );
			if ( $rt_cons ) {
				$rt_pros_cons['cons'] = $rt_cons;
			}
		}
		if ( isset( $rt_pros_cons['pros'] ) || isset( $rt_pros_cons['cons'] ) ) {
			add_comment_meta( $comment_id, 'rt_pros_cons', $rt_pros_cons );
		}

		// add image & video
		$attachments = [];
		if ( isset( $_POST['rt_image_source'] ) && $_POST['rt_image_source'] == 'self' ) {
			if ( isset( $_POST['rt_attachment']['imgs'] ) && ( '' !== $_POST['rt_attachment']['imgs'] ) ) {
				$attachments['imgs']         = array_map( 'absint', $_POST['rt_attachment']['imgs'] );
				$attachments['image_source'] = 'self';
			}
		} elseif ( isset( $_POST['rt_image_source'] ) && $_POST['rt_image_source'] == 'external' ) {
			if ( ! empty( $_POST['rt_external_image'] ) ) {
				$external_url = esc_url_raw( wp_unslash( $_POST['rt_external_image'] ) );
				if ( filter_var( $external_url, FILTER_VALIDATE_URL ) && preg_match( '/^https?:\/\//i', $external_url ) ) {
					$attachments['imgs']         = [ $external_url ];
					$attachments['image_source'] = 'external';
				}
			}
		} elseif ( isset( $_POST['rt_attachment']['imgs'] ) && ( '' !== $_POST['rt_attachment']['imgs'] ) ) {
			$attachments['imgs'] = array_map( 'absint', $_POST['rt_attachment']['imgs'] );
		}

		// Allow pro plugin to add video attachment data.
		$attachments = apply_filters( 'rtrs_review_attachment_data', $attachments );

		if ( isset( $attachments['imgs'] ) || isset( $attachments['videos'] ) ) {
			add_comment_meta( $comment_id, 'rt_attachment', $attachments );
		}

		if ( $post_id ) {
			$avRatingValue = ReviewFns::getAvgRatings( $post_id );
			$ratingCount   = ReviewFns::getTotalRatings( $post_id );
			update_post_meta( $post_id, 'rtrs_avg_rating', $avRatingValue );
			update_post_meta( $post_id, 'rtrs_rating_count', (int) $ratingCount );
			do_action( 'rtrs_avg_rating_meta_save', $avRatingValue, $comment_id, $post_id );
			wp_update_comment(
				[
					'comment_ID'   => $comment_id,
					'comment_type' => 'review',
				]
			);
		}
		// RTCL Support.
		if ( method_exists( Comments::class, 'clear_transients' ) ) {
			Comments::clear_transients( $post_id );
		}
	}

	/**
	 * Recalculate average rating and count when a comment status changes.
	 *
	 * Fires on approve, unapprove, trash, untrash, spam, and unspam.
	 *
	 * @param string      $new_status New comment status.
	 * @param string      $old_status Old comment status.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public function recalculate_on_status_change( $new_status, $old_status, $comment ) {
		if ( $new_status === $old_status ) {
			return;
		}

		// Only act when moving to/from 'approved' status.
		if ( 'approved' !== $new_status && 'approved' !== $old_status ) {
			return;
		}

		// Only recalculate for review-type comments that have a rating.
		$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
		if ( '' === $rating ) {
			return;
		}

		$post_id = (int) $comment->comment_post_ID;
		if ( ! $post_id ) {
			return;
		}

		$avRatingValue = ReviewFns::getAvgRatings( $post_id );
		$ratingCount   = ReviewFns::getTotalRatings( $post_id );

		update_post_meta( $post_id, 'rtrs_avg_rating', $avRatingValue );
		update_post_meta( $post_id, 'rtrs_rating_count', (int) $ratingCount );

		do_action( 'rtrs_avg_rating_meta_save', $avRatingValue, $comment->comment_ID, $post_id );
	}

	public function comment_field_after() {
		$post_type = get_post_type();
		if ( ! Functions::isEnableReviewByPostType( $post_type ) ) {
			return;
		}

		$p_meta = Functions::getMetaByPostType( $post_type );
		if ( ! $p_meta ) {
			return;
		} // get back if not added

		ob_start();

		$criteria       = ( isset( $p_meta['criteria'] ) && $p_meta['criteria'][0] == 'multi' );
		$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;

		do_action( 'rtrs_before_review_form' );

		$criteria_style = null;
		if ( $criteria && $multi_criteria ) {
			// add css for odd
			if ( count( $multi_criteria ) % 2 != 0 ) {
				$criteria_style = 'grid-template-columns: repeat(1, 270px);';
			}
		}

		echo '<div class="rtrs-form-group rtrs-hide-reply"><ul class="rtrs-rating-category" style="' . esc_attr( $criteria_style ) . '">';
		if ( $criteria && $multi_criteria ) {
			$criteria_count = 1;
			foreach ( $multi_criteria as $key => $value ) :
				$slug = 'rt_rating_' . md5( $value );
				?>

				<li>
					<div class="rtrs-category-text"><?php echo esc_html( $value ); ?></div>
					<div class="rtrs-rating-container">
						<?php
						$default_selected = absint( apply_filters( 'rtrs_set_default_review_value', 5 ) );
						for ( $i = 5; $i >= 1; $i-- ) :
							?>
							<input
									<?php
									if ( $i == $default_selected ) {
										echo 'checked';
									}
									?>
									type="radio" id="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>" name="<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $i ); ?>"/><label for="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
						<?php endfor; ?>
					</div>
				</li>
				<?php
				$criteria_count++;
			endforeach;
		} else {
			?>

			<li>
				<div class="rtrs-category-text"><?php esc_html_e( 'Rating', 'review-schema' ); ?></div>
				<div class="rtrs-rating-container">
					<?php
					$default_selected = absint( apply_filters( 'rtrs_set_default_review_value', 5 ) );
					for ( $i = 5; $i >= 1; $i-- ) :
						?>
						<input
								<?php
								if ( $i == $default_selected ) {
									echo 'checked';
								}
								?>
								type="radio" id="rt-rating-<?php echo esc_attr( $i ); ?>" name="rt_rating" value="<?php echo esc_attr( $i ); ?>"/><label for="rt-rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
					<?php endfor; ?>
				</div>
			</li>
			<?php
		}
		echo '</ul></div>';

		$pros_cons = ( isset( $p_meta['pros_cons'] ) && $p_meta['pros_cons'][0] == '1' );
		if ( $pros_cons ) {
			?>
			<div class="rtrs-form-group rtrs-hide-reply">
				<div class="rtrs-feedback-input">
					<div class="rtrs-input-item rtrs-pros">
						<h3 class="rtrs-input-title">
							<span class="item-icon"><i class="rtrs-thumbs-up"></i></span>
							<span class="item-text"><?php esc_html_e( 'PROS', 'review-schema' ); ?></span>
						</h3>
						<div class="rtrs-input-filed">
							<span class="rtrs-remove-btn">+</span>
							<input type="text" class="form-control" name="rt_pros[]" placeholder="<?php esc_attr_e( 'Write here!', 'review-schema' ); ?>">
						</div>
						<div class="rtrs-field-add"><i class="rtrs-plus"></i><?php esc_html_e( 'Add Field', 'review-schema' ); ?></div>
					</div>

					<div class="rtrs-input-item rtrs-cons">
						<h3 class="rtrs-input-title">
							<span class="item-icon unlike-icon"><i class="rtrs-thumbs-down"></i></span>
							<span class="item-text"><?php esc_html_e( 'CONS', 'review-schema' ); ?></span>
						</h3>
						<div class="rtrs-input-filed">
							<span class="rtrs-remove-btn">+</span>
							<input type="text" class="form-control" name="rt_cons[]" placeholder="<?php esc_attr_e( 'Write here!', 'review-schema' ); ?>">
						</div>
						<div class="rtrs-field-add"><i class="rtrs-plus"></i><?php esc_html_e( 'Add Field', 'review-schema' ); ?></div>
					</div>
				</div>
			</div>
			<?php
		}
		?>


		<div class="rtrs-media-buttons">
			<?php

			if ( isset( $p_meta['image_review'] ) && $p_meta['image_review'][0] == '1' ) {
				?>
				<div class="rtrs-image-media-groups">
					<div class="rtrs-form-group rtrs-media-form-group rtrs-hide-reply">
						<div class="rtrs-button-label">
							<label class="rtrs-input-image-label"><?php esc_html_e( 'Image', 'review-schema' ); ?></label>
						</div>

					<?php $can_upload_media = ReviewFns::canUploadMedia(); ?>
						<div class="rtrs-image-source-selector">
							<select name="rt_image_source" id="rtrs-image-source" class="rtrs-form-control">
								<option value="self"><?php esc_html_e( 'Upload Image', 'review-schema' ); ?></option>
								<option value="external" <?php echo ! $can_upload_media ? 'selected' : ''; ?>><?php esc_html_e( 'External Image URL', 'review-schema' ); ?></option>
							</select>
						</div>

						<?php if ( $can_upload_media ) { ?>
							<div class="rtrs-source-image">
								<div class="rtrs-image-button">
									<div class="rtrs-multimedia-upload">
										<div class="rtrs-upload-box" id="rtrs-upload-box-image">
											<span><?php esc_html_e( 'Choose Image', 'review-schema' ); ?></span>
										</div>
									</div>
									<input type="file" id="rtrs-image" accept="image/*" style="display:none">
									<div class="rtrs-image-error"></div>
								</div>
							</div>
						<?php } else { ?>
							<div class="rtrs-source-image" style="display:none;">
								<p class="rtrs-login-message">
									<?php
									printf(
										wp_kses(
											/* translators: %s: login URL */
											__( 'Please <a href="%s">log in</a> to upload images.', 'review-schema' ),
											[ 'a' => [ 'href' => [] ] ]
										),
										esc_url( wp_login_url( get_permalink() ) )
									);
									?>
								</p>
							</div>
						<?php } ?>
					</div>

					<?php if ( $can_upload_media ) { ?>
						<div class="rtrs-form-group rtrs-hide-reply">
							<div class="rtrs-preview-imgs"></div>
						</div>
					<?php } ?>

					<div class="rtrs-form-group rtrs-source-external-image rtrs-hide-reply" <?php echo $can_upload_media ? 'style="display:none;"' : ''; ?>>
						<label class="rtrs-input-label" for="rt_external_image"><?php esc_html_e( 'External Image URL', 'review-schema' ); ?></label>
						<input id="rt_external_image" class="rtrs-form-control" placeholder="https://example.com/image.jpg" name="rt_external_image" type="url">
						<div class="rtrs-external-image-preview"></div>
					</div>
				</div>

				<?php
			}

			/**
			 * Hook to render video review form field (Pro feature).
			 *
			 * @param array $p_meta Post meta data.
			 */
			do_action( 'rtrs_review_form_video_field', $p_meta );
			?>
		</div>


		<?php
		/**
		 * Hook to render recommendation form field (Pro feature).
		 *
		 * @param array $p_meta Post meta data.
		 */
		do_action( 'rtrs_review_form_recommendation_field', $p_meta );

		/**
		 * Hook to render anonymous review form field (Pro feature).
		 *
		 * @param array $p_meta Post meta data.
		 */
		do_action( 'rtrs_review_form_anonymous_field', $p_meta );
		?>
		<?php
		$enable_gdpr  = rtrs()->get_options( 'rtrs_review_settings', [ 'enable_review_gdpr_consent', 'no' ] );
		$require_gdpr = rtrs()->get_options( 'rtrs_review_settings', [ 'require_gdpr_consent_for_reviews', 'yes' ] );
		if ( 'yes' === $enable_gdpr ) {
			$consent_text = rtrs()->get_options( 'rtrs_review_settings', [ 'review_gdpr_consent_text', __( 'I agree that my review and personal data may be displayed publicly in accordance with the Privacy Policy.', 'review-schema' ) ] );
			?>

			<div class="rtrs-form-group rtrs-hide-reply rtrs-gdpr-consent">
				<div class="rtrs-form-check">
					<input
							type="checkbox"
							class="rtrs-form-checkbox"
							name="rtrs_review_gdpr_consent"
							id="rtrs-review-gdpr-consent"
							value="show"
					<?php echo esc_attr( 'yes' === $require_gdpr ? 'required' : '' ); ?>
					>
					<label for="rtrs-review-gdpr-consent" class="rtrs-checkbox-label">
				<?php echo wp_kses_post( $consent_text ); ?>
					</label>
				</div>
			</div>

			<?php
		}

		wp_nonce_field( rtrs()->getNonceId(), rtrs()->getNonceId() );
		do_action( 'rtrs_after_review_form' );

		return ob_get_clean();
	}

	/**
	 * Filter `comment_form_default_fields` to restore the fields built by our
	 * own {@see self::comment_form()} handler — themes such as Astra hook this
	 * filter at default priority 10 and silently rewrite author/email/url with
	 * their own markup, which strips our review fields and wrapping classes.
	 *
	 * Because we run at priority 99 the theme's handler has already executed;
	 * we simply replace the result with the markup we previously stashed.
	 *
	 * @param array $fields Default fields array (possibly already modified by
	 *                      theme handlers at lower priorities).
	 * @return array
	 */
	public function comment_form_default_fields( $fields ) {
		if ( is_array( $this->stored_fields ) && ! empty( $this->stored_fields ) ) {
			$restored            = $this->stored_fields;
			$this->stored_fields = null; // consume — each comment_form() call repopulates.
			return $restored;
		}
		return $fields;
	}

	public function comment_form( $args ) {
		global $post;
		if ( ! Functions::isEnableReviewByPostType( $post->post_type ) ) {
			return $args;
		}

		$commenter = wp_get_current_commenter();
		$req       = get_option( 'require_name_email' );
		$p_meta    = Functions::getMetaByPostType( get_post_type() );

		// If review is disabled via the toggle, return default form.
		if ( empty( $p_meta['rtrs_support'][0] ) ) {
			return $args;
		}

		$email   = ! ( isset( $p_meta['email'] ) && $p_meta['email'][0] === '1' );
		$website = ! ( isset( $p_meta['website'] ) && $p_meta['website'][0] === '1' );
		$title   = ! ( isset( $p_meta['title'] ) && $p_meta['title'][0] === '1' );
		$author  = ! ( isset( $p_meta['author'] ) && $p_meta['author'][0] === '1' );

		$string_text = apply_filters(
			'rtrs_review_form_string_list',
			[
				'title_reply'               => esc_html__( 'Leave feedback about this', 'review-schema' ),
				'title_reply_to'            => wp_kses(
					/* translators: %s: comment author name */
					__( 'Leave feedback about this to %s', 'review-schema' ),
					[ 'allow_tag_list' ]
				),
				'cancel_reply_link'         => esc_html__( 'Cancel Reply', 'review-schema' ),
				'label_submit'              => esc_html__( 'Submit Review', 'review-schema' ),
				'comment_notes_before'      => '',
				'comment_notes_after'       => '',
				'name_field_placeholder'    => esc_html__( 'Name', 'review-schema' ),
				'email_field_placeholder'   => esc_html__( 'Email', 'review-schema' ),
				'website_field_placeholder' => esc_html__( 'Website', 'review-schema' ),
				'title_field_placeholder'   => esc_html__( 'Title', 'review-schema' ),
				'comment_field_placeholder' => esc_html__( 'Write your review *', 'review-schema' ),

			]
		);

		$args['fields'] = [];
		if ( $author ) {
			$name_field_placeholder   = ! empty( $string_text['name_field_placeholder'] ) ? $string_text['name_field_placeholder'] : '';
			$args['fields']['author'] = '<div class="rtrs-form-group"><input id="name" class="rtrs-form-control" placeholder="' . $name_field_placeholder . ( $req ? '*' : '' ) . '" name="author"  type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30"' . ( $req ? " required='required' aria-required='true'" : '' ) . ' /></div>';
		}
		if ( $email ) {
			$email_field_placeholder = ! empty( $string_text['email_field_placeholder'] ) ? $string_text['email_field_placeholder'] : '';
			$args['fields']['email'] = '<div class="rtrs-form-group"><input id="email" class="rtrs-form-control" placeholder="' . $email_field_placeholder . ( $req ? '*' : '' ) . '" name="email"  type="text" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30"' . ( $req ? " required='required' aria-required='true'" : '' ) . ' /></div>';
		}

		if ( $website ) {
			$website_field_placeholder = ! empty( $string_text['website_field_placeholder'] ) ? $string_text['website_field_placeholder'] : '';
			$args['fields']['url']     = '<div class="rtrs-form-group"><input id="url" class="rtrs-form-control" placeholder="' . $website_field_placeholder . '" name="url" type="text" value="' . esc_url( $commenter['comment_author_url'] ) . '" size="30"/></div>';
		}

		$args['id_form']            = 'comment_form';
		$args['class_form']         = 'rtrs-form-box';
		$args['id_submit']          = 'submit';
		$args['class_submit']       = 'rtrs-submit-btn rtrs-review-submit';
		$args['class_container']    = 'comment-respond rtrs-review-form';
		$args['submit_field']       = '<div class="rtrs-form-group rtrs-review-submit-wrapper ">%1$s %2$s</div>';
		$args['name_submit']        = 'submit';
		$args['title_reply']        = ! empty( $string_text['title_reply'] ) ? $string_text['title_reply'] : '';
		$args['title_reply_before'] = '<h2 id="reply-title" class="rtrs-form-title">';
		$args['title_reply_after']  = '</h2>';
		/* translators: %s: Extra words for comment title */
		$args['title_reply_to']       = ! empty( $string_text['title_reply_to'] ) ? $string_text['title_reply_to'] : '';
		$args['cancel_reply_link']    = ! empty( $string_text['cancel_reply_link'] ) ? $string_text['cancel_reply_link'] : '';
		$args['comment_notes_before'] = ! empty( $string_text['comment_notes_before'] ) ? $string_text['comment_notes_before'] : '';
		$args['comment_notes_after']  = ! empty( $string_text['comment_notes_after'] ) ? $string_text['comment_notes_after'] : '';
		$args['label_submit']         = ! empty( $string_text['label_submit'] ) ? $string_text['label_submit'] : '';

		$args['comment_field'] = '';
		if ( $title ) {
			$title_field_placeholder = ! empty( $string_text['title_field_placeholder'] ) ? $string_text['title_field_placeholder'] : '';
			$args['comment_field']   = '<div class="rtrs-form-group rtrs-hide-reply"><input id="rt_title" class="rtrs-form-control" placeholder="' . $title_field_placeholder . '" name="rt_title" type="text" value="" size="30" aria-required="true"></div>';
		}

		$comment_field_placeholder = ! empty( $string_text['comment_field_placeholder'] ) ? $string_text['comment_field_placeholder'] : '';

		$args['comment_field'] .= '<div class="rtrs-form-group"><textarea id="message" class="rtrs-form-control" placeholder="' . $comment_field_placeholder . '"  name="comment" required="required"  aria-required="true" rows="6" cols="45"></textarea></div>';
		$args['comment_field'] .= '<input type="hidden" id="gRecaptchaResponse" name="gRecaptchaResponse" value="">';

		if ( is_user_logged_in() ) {
			if ( $website ) {
				$website_field_placeholder = ! empty( $string_text['website_field_placeholder'] ) ? $string_text['website_field_placeholder'] : '';
				$args['comment_field']    .= '<div class="rtrs-form-group rtrs-hide-reply"><input id="url" class="rtrs-form-control" placeholder="' . $website_field_placeholder . '" name="url" type="url" value="' . esc_url( $commenter['comment_author_url'] ) . '" size="30"/></div>';
			}
			$args['comment_field'] .= $this->comment_field_after();
		} else {
			$args['fields']['extra'] = $this->comment_field_after();
		}
		$args = array_merge( $args, $string_text );

		// Stash final fields so that comment_form_default_fields() can restore
		// them after themes (e.g. Astra) rewrite the author/email/url markup.
		$this->stored_fields = $args['fields'];

		return $args;
	}

	public function comment_avater_types() {
		return apply_filters( 'rtrs_get_avatar_comment_types', [ 'comment', 'review' ] );
	}

	public function review_form_string_list( $string ) {
		$misc_settings = rtrs()->get_options( 'rtrs_misc_settings' );
		if ( ! empty( $misc_settings['title_reply'] ) ) {
			$string['title_reply'] = esc_html( $misc_settings['title_reply'] );
		}
		if ( ! empty( $misc_settings['cancel_reply_link'] ) ) {
			$string['cancel_reply_link'] = esc_html( $misc_settings['cancel_reply_link'] );
		}
		if ( ! empty( $misc_settings['label_submit'] ) ) {
			$string['label_submit'] = esc_html( $misc_settings['label_submit'] );
		}
		if ( ! empty( $misc_settings['name_field_placeholder'] ) ) {
			$string['name_field_placeholder'] = esc_html( $misc_settings['name_field_placeholder'] );
		}
		if ( ! empty( $misc_settings['title_field_placeholder'] ) ) {
			$string['title_field_placeholder'] = esc_html( $misc_settings['title_field_placeholder'] );
		}
		if ( ! empty( $misc_settings['email_field_placeholder'] ) ) {
			$string['email_field_placeholder'] = esc_html( $misc_settings['email_field_placeholder'] );
		}
		if ( ! empty( $misc_settings['email_field_placeholder'] ) ) {
			$string['email_field_placeholder'] = esc_html( $misc_settings['email_field_placeholder'] );
		}
		if ( ! empty( $misc_settings['website_field_placeholder'] ) ) {
			$string['website_field_placeholder'] = esc_html( $misc_settings['website_field_placeholder'] );
		}
		if ( ! empty( $misc_settings['comment_field_placeholder'] ) ) {
			$string['comment_field_placeholder'] = esc_html( $misc_settings['comment_field_placeholder'] );
		}

		return $string;
	}


	public function validate_product_review_verified_owners( $comment_post_id ) {

		if ( 'yes' !== get_option( 'woocommerce_review_rating_verification_required' ) ) {
			return;
		}

		// Validate only products.
		if ( 'product' !== get_post_type( $comment_post_id ) ) {
			return;
		}

		// Skip if is a verified owner.
		if ( wc_customer_bought_product( '', get_current_user_id(), $comment_post_id ) ) {
			return;
		}

		// Skip if is a verified owner.
		if ( ReviewFns::has_reply_permition() ) {
			return;
		}

		wp_die(
			esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'review-schema' ),
			esc_html__( 'Reviews can only be left by "verified owners"', 'review-schema' ),
			[
				'code' => 403,
			]
		);
	}
}