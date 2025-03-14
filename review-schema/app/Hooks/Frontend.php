<?php

namespace Rtrs\Hooks;

use Rtcl\Controllers\Hooks\Comments;
use Rtrs\Models\Review;
use Rtrs\Helpers\Functions;
use WC_Comments;

class Frontend {
	public function __construct() {
		add_filter( 'comment_form_defaults', [ $this, 'comment_form' ] );

		add_action( 'comment_post', [ $this, 'comment_review_meta_save' ] );
		add_action( 'comment_save_pre', [ $this, 'update_comment_data' ] );

		// throw this into your plugin or your functions.php file to define the custom comments template.
		add_filter( 'comments_template', [ $this, 'comment_template' ], 99 );

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
		if ( Functions::isEnableByPostType( $post->post_type ) ) {
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
				function () { ?>
					<p id='wait_approval' style='padding-top: 40px;'><strong><?php echo esc_html( apply_filters( 'rtrs_review_form_submited_notice', __( 'Your review has been submitted.', 'review-schema' ) ) ); ?></strong></p>
					<?php
				}
			);
		}
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
				'remoteip' => $_SERVER['REMOTE_ADDR'],
			]
		);

		$api_url         = 'https://www.google.com/recaptcha/api/siteverify?' . $captcha_postdata;
		$check_recaptcha = wp_remote_get( $api_url );
		if ( is_wp_error( $check_recaptcha ) ) {
			return false;
		}

		$check_recaptcha = wp_remote_retrieve_body( $check_recaptcha );
		$check_recaptcha = json_decode( $check_recaptcha );

		return ( $check_recaptcha ) ? $check_recaptcha->success : false;
	}

	public function verify_google_recaptcha( $comment_post_id ) {
		$p_meta              = Functions::getMetaByPostType( get_post_type( $comment_post_id ) );
		$recaptcha           = ( isset( $p_meta['recaptcha'] ) && $p_meta['recaptcha'][0] == '1' );
		$recaptcha_secretkey = rtrs()->get_options( 'rtrs_review_settings', [ 'recaptcha_secretkey', '' ] );
		if ( ! $recaptcha || ! $recaptcha_secretkey ) {
			return true;
		}
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$recaptcha    = sanitize_text_field( $_POST['gRecaptchaResponse'] );
		$allowed_html = [
			'a' => [
				'href'  => [],
				'title' => [],
			],
			'b' => [],
			'p' => [],
		];
		if ( empty( $recaptcha ) ) {
			wp_die( wp_kses( __( "<b>ERROR:</b> please select <b>I'm not a robot!</b>", 'review-schema' ), $allowed_html ) . "<p><a href='javascript:history.back()'>" . esc_html__( '« Back', 'review-schema' ) . '</a></p>' );
		} elseif ( ! $this->is_valid_captcha( $recaptcha ) ) {
			wp_die( wp_kses( __( '<b>Go away SPAMMER!</b>', 'review-schema' ), $allowed_html ) );
		}
	}

	public function comment_template( $comment_template ) {
		global $post;
		if ( ! ( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
			return $comment_template;
		}
		if ( Functions::isEnableByPostType( $post->post_type ) ) {
			// extra arg will pass in array.
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

		$comment_id = absint( $_POST['comment_ID'] );
		// not isset means not enable criteria
		$post_id = absint( $_POST['comment_post_ID'] ?? 0 );
		if ( ! isset( $_POST['rt_rating'] ) ) {
			$p_meta         = Functions::getMetaByPostType( get_post_type( $post_id ) );
			$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;
			if ( $multi_criteria ) {
				$i               = $total = $avg_rating = 0;
				$criteria_rating = [];
				foreach ( $multi_criteria as $key => $value ) {
					$slug = 'rt_rating_' . Functions::slugify( $value );
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
		if ( isset( $_POST['rt_title'] ) && ( '' !== $_POST['rt_title'] ) ) {
			update_comment_meta( $comment_id, 'rt_title', sanitize_text_field( $_POST['rt_title'] ) );
		}

		// add highlight
		if ( current_user_can( 'administrator' ) ) {
			$highlight = isset( $_POST['rt_highlight'] ) ? 1 : 0;
			update_comment_meta( $comment_id, 'rt_highlight', $highlight );

			$rt_sticky_review = isset( $_POST['rt_sticky_review'] ) ? 1 : 0;
			update_comment_meta( $comment_id, 'rt_sticky_review', $rt_sticky_review );
		}

		// add recommendation
		if ( ! empty( $_POST['rt_recommended'] ) ) {
			update_comment_meta( $comment_id, 'rt_recommended', intval( $_POST['rt_recommended'] ) );
		}

		// add pros & cons
		$rt_pros_cons = [];
		if ( ! empty( $_POST['rt_pros'] ) && is_array( $_POST['rt_pros'] ) ) {
			$rt_pros_cons['pros'] = array_map( 'sanitize_text_field', array_filter( $_POST['rt_pros'] ) );
		}
		if ( ! empty( $_POST['rt_cons'] ) && is_array( $_POST['rt_cons'] ) ) {
			$rt_pros_cons['cons'] = array_map( 'sanitize_text_field', array_filter( $_POST['rt_cons'] ) );
		}
		if ( ! empty( $rt_pros_cons ) ) {
			update_comment_meta( $comment_id, 'rt_pros_cons', $rt_pros_cons );
		}

		// add image & video
		$attachments = [];
		if ( ! empty( $_POST['rt_attachment']['imgs'] ) && is_array( $_POST['rt_attachment']['imgs'] ) ) {
			$attachments['imgs'] = array_filter( array_map( 'absint', $_POST['rt_attachment']['imgs'] ) );
		}

		if ( isset( $_POST['rt_video_source'] ) && $_POST['rt_video_source'] == 'self' ) {
			if ( ! empty( $_POST['rt_attachment']['videos'] ) && is_array( $_POST['rt_attachment']['videos'] ) ) {
				$attachments['videos']       = array_map( 'absint', $_POST['rt_attachment']['videos'] );
				$attachments['video_source'] = 'self';
			}
		} elseif ( isset( $_POST['rt_video_source'] ) && $_POST['rt_video_source'] == 'external' ) {
			if ( ! empty( $_POST['rt_external_video'] ) ) {
				$attachments['videos']       = [ esc_url( $_POST['rt_external_video'] ) ];
				$attachments['video_source'] = 'external';
			}
		}

		if ( ! empty( $attachments ) ) {
			update_comment_meta( $comment_id, 'rt_attachment', $attachments );
		}

		if ( $post_id ) {
			$avRatingValue = Review::getAvgRatings( $post_id );
			update_post_meta( $post_id, 'rtrs_avg_rating', $avRatingValue );
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
					$slug = 'rt_rating_' . Functions::slugify( $value );
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

		// add recommendation
		if ( isset( $_POST['rt_recommended'] ) && ( '' !== $_POST['rt_recommended'] ) ) {
			add_comment_meta( $comment_id, 'rt_recommended', intval( $_POST['rt_recommended'] ) );
		}

		// add title
		if ( isset( $_POST['rt_title'] ) && ( '' !== $_POST['rt_title'] ) ) {
			add_comment_meta( $comment_id, 'rt_title', sanitize_text_field( $_POST['rt_title'] ) );
		}

		// add pros & cons
		$rt_pros_cons = [];
		if ( isset( $_POST['rt_pros'] ) && ( '' !== $_POST['rt_pros'] ) ) {
			$rt_pros = array_map( 'sanitize_text_field', $_POST['rt_pros'] );
			$rt_pros = array_filter( $rt_pros );
			if ( $rt_pros ) {
				$rt_pros_cons['pros'] = $rt_pros;
			}
		}
		if ( isset( $_POST['rt_cons'] ) && ( '' !== $_POST['rt_cons'] ) ) {
			$rt_cons = array_map( 'sanitize_text_field', $_POST['rt_cons'] );
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
		if ( isset( $_POST['rt_attachment']['imgs'] ) && ( '' !== $_POST['rt_attachment']['imgs'] ) ) {
			$attachments['imgs'] = array_map( 'absint', $_POST['rt_attachment']['imgs'] );
		}

		if ( isset( $_POST['rt_video_source'] ) && $_POST['rt_video_source'] == 'self' ) {
			if ( isset( $_POST['rt_attachment']['videos'] ) && ( '' !== $_POST['rt_attachment']['videos'] ) ) {
				$attachments['videos']       = array_map( 'absint', $_POST['rt_attachment']['videos'] );
				$attachments['video_source'] = 'self';
			}
		} elseif ( isset( $_POST['rt_video_source'] ) && $_POST['rt_video_source'] == 'external' ) {
			if ( isset( $_POST['rt_external_video'] ) && ( '' !== $_POST['rt_external_video'] ) ) {
				$attachments['videos']       = [ esc_url( $_POST['rt_external_video'] ) ];
				$attachments['video_source'] = 'external';
			}
		}

		if ( isset( $attachments['imgs'] ) || isset( $attachments['videos'] ) ) {
			add_comment_meta( $comment_id, 'rt_attachment', $attachments );
		}

		// add anonymous
		if ( isset( $_POST['rt_anonymous'] ) ) {
			update_comment_meta( $comment_id, 'rt_anonymous', 1 );
		}

		if ( $post_id ) {
			$avRatingValue = Review::getAvgRatings( $post_id );
			update_post_meta( $post_id, 'rtrs_avg_rating', $avRatingValue );
			do_action( 'rtrs_avg_rating_meta_save', $avRatingValue, $comment_id, $post_id );
		}

		// RTCL Support.
		if ( method_exists( Comments::class, 'clear_transients' ) ) {
			Comments::clear_transients( $post_id );
		}
	}

	public function comment_field_after() {
		$post_type = get_post_type();
		if ( ! Functions::isEnableByPostType( $post_type ) ) {
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
				$slug = 'rt_rating_' . Functions::slugify( $value );
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
					<div class="rtrs-form-group rtrs-hide-reply">
						<div class="rtrs-preview-imgs"></div>
					</div>
					<div class="rtrs-form-group rtrs-media-form-group rtrs-hide-reply">
						<div class="rtrs-button-label">
							<label class="rtrs-input-image-label"><?php esc_html_e( 'Upload Image', 'review-schema' ); ?></label>
						</div>

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
				</div>

				<?php
			}

			$video_review = ( isset( $p_meta['video_review'] ) && $p_meta['video_review'][0] == '1' );
			if ( $video_review && function_exists( 'rtrsp' ) ) {
				?>
				<div class="rtrs-video-media-groups">
					<div class="rtrs-form-group rtrs-hide-reply">
						<div class="rtrs-preview-videos"></div>
					</div>

					<div class="rtrs-form-group rtrs-media-form-group rtrs-hide-reply">
						<div class="rtrs-button-label">
							<label class="rtrs-input-video-label"><?php esc_html_e( 'Upload Video', 'review-schema' ); ?></label>
						</div>

						<div class="rtrs-video-source-selector">
							<select name="rt_video_source" id="rtrs-video-source" class="rtrs-form-control">
								<option value="self"><?php esc_html_e( 'Hosted Video', 'review-schema' ); ?></option>
								<option value="external"><?php esc_html_e( 'External Video', 'review-schema' ); ?></option>
							</select>
						</div>

						<div class="rtrs-source-video">
							<div class="rtrs-multimedia-upload">
								<div class="rtrs-upload-box" id="rtrs-upload-box-video">
									<span><?php esc_html_e( 'Choose Video', 'review-schema' ); ?></span>
								</div>
							</div>
							<input type="file" id="rtrs-video" accept="video/*" style="display:none">
							<div class="rtrs-video-error"></div>
						</div>
					</div>

					<div class="rtrs-form-group rtrs-source-external rtrs-hide-reply">
						<label class="rtrs-input-label" for="rt_external_video"><?php esc_html_e( 'External Video Link', 'review-schema' ); ?></label>
						<input id="rt_external_video" class="rtrs-form-control" placeholder="https://www.youtube.com/watch?v=668nUCeBHyY" name="rt_external_video" type="text">
					</div>
				</div>
				<?php
			} //video_review
			?>
		</div>


		<?php
		$recommendation = ( isset( $p_meta['recommendation'] ) && $p_meta['recommendation'][0] == '1' );
		if ( $recommendation && function_exists( 'rtrsp' ) ) {
			?>
			<div class="rtrs-form-group rtrs-hide-reply">
				<label class="rtrs-input-label"><?php esc_html_e( 'Recommendation', 'review-schema' ); ?></label>
				<div class="rtrs-recomnd-check">
					<div class="rtrs-form-check rtrs-tooltip">
						<input type="radio" class="rtrs-form-checkbox" name="rt_recommended" value="1">
						<label class="rtrs-checkbox-label check-excelent"></label>
						<span class="rtrs-tooltiptext"><?php esc_html_e( 'Happy', 'review-schema' ); ?></span>
					</div>
					<div class="rtrs-form-check rtrs-tooltip">
						<input type="radio" class="rtrs-form-checkbox" name="rt_recommended" value="-1">
						<label class="rtrs-checkbox-label check-good"></label>
						<span class="rtrs-tooltiptext"><?php esc_html_e( 'Sad', 'review-schema' ); ?></span>
					</div>
					<div class="rtrs-form-check rtrs-tooltip">
						<input type="radio" class="rtrs-form-checkbox" name="rt_recommended" value="0">
						<label class="rtrs-checkbox-label check-bad"></label>
						<span class="rtrs-tooltiptext"><?php esc_html_e( 'Nothing', 'review-schema' ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}

		$anonymous_review = ( isset( $p_meta['anonymous_review'] ) && $p_meta['anonymous_review'][0] == '1' );
		if ( $anonymous_review && function_exists( 'rtrsp' ) ) {
			?>

			<div class="rtrs-form-group rtrs-hide-reply">
				<div class="rtrs-form-check">
					<input type="checkbox" class="rtrs-form-checkbox" name="rt_anonymous" id="rtrs-anonymous">
					<label for="rtrs-anonymous" class="rtrs-checkbox-label"><?php esc_html_e( 'Review anonymously', 'review-schema' ); ?></label>
				</div>
			</div>
			<?php
		}
		wp_nonce_field( rtrs()->getNonceId(), rtrs()->getNonceId() );
		do_action( 'rtrs_after_review_form' );

		return ob_get_clean();
	}

	public function comment_form( $args ) {
		global $post;
		if ( ! Functions::isEnableByPostType( $post->post_type ) ) {
			return;
		}

		$commenter = wp_get_current_commenter();
		$req       = get_option( 'require_name_email' );
		$p_meta    = Functions::getMetaByPostType( get_post_type() );

		$email   = isset( $p_meta['email'] ) && $p_meta['email'][0] == '1' ? false : true;
		$website = isset( $p_meta['website'] ) && $p_meta['website'][0] == '1' ? false : true;
		$title   = isset( $p_meta['title'] ) && $p_meta['title'][0] == '1' ? false : true;
		$author  = isset( $p_meta['author'] ) && $p_meta['author'][0] == '1' ? false : true;

		$string_text = apply_filters(
			'rtrs_review_form_string_list',
			[
				'title_reply'               => esc_html__( 'Leave feedback about this', 'review-schema' ),
				'title_reply_to'            => wp_kses( __( 'Leave feedback about this to %s', 'review-schema' ), [ 'allow_tag_list' ] ),
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
			$args['comment_field'] .= $this->comment_field_after();
		} else {
			$args['fields']['extra'] = $this->comment_field_after();
		}
		$args = array_merge( $args, $string_text );

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
		if ( Functions::has_reply_permition() ) {
			return;
		}

		wp_die(
			esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'woocommerce' ),
			esc_html__( 'Reviews can only be left by "verified owners"', 'woocommerce' ),
			[
				'code' => 403,
			]
		);
	}
}
