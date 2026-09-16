<?php

namespace Rtrs\Modules\Review\Ajax;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewAjax {
	/**
	 * SingleTon
	 */
	use SingletonTrait;

	private function __construct() {
		add_action( 'wp_ajax_rtrs_review_edit_form', [ $this, 'rtrs_review_edit_form' ] );
		add_action( 'wp_ajax_nopriv_rtrs_review_edit_form', [ $this, 'rtrs_review_edit_form' ] );

		add_action( 'wp_ajax_rtrs_self_video_popup', [ $this, 'rtrs_self_video_popup' ] );
		add_action( 'wp_ajax_nopriv_rtrs_self_video_popup', [ $this, 'rtrs_self_video_popup' ] );

		add_action( 'wp_ajax_rtrs_review_edit', [ $this, 'rtrs_review_edit' ] );

		add_action( 'wp_ajax_rtrs_review_filter', [ $this, 'rtrs_review_filter' ] );
		add_action( 'wp_ajax_nopriv_rtrs_review_filter', [ $this, 'rtrs_review_filter' ] );

		add_action( 'wp_ajax_rtrs_pagination', [ $this, 'rtrs_pagination' ] );
		add_action( 'wp_ajax_nopriv_rtrs_pagination', [ $this, 'rtrs_pagination' ] );

		add_action( 'wp_ajax_rtrs_image_upload', [ $this, 'rtrs_image_upload' ] );
		add_action( 'wp_ajax_rtrs_video_upload', [ $this, 'rtrs_video_upload' ] );

		// Guest (non-logged-in) upload endpoints. The handlers self-guard on the
		// "Allow Guest Uploads" media setting, so registration is always safe.
		add_action( 'wp_ajax_nopriv_rtrs_image_upload', [ $this, 'rtrs_image_upload' ] );
		add_action( 'wp_ajax_nopriv_rtrs_video_upload', [ $this, 'rtrs_video_upload' ] );

		add_action( 'wp_ajax_rtrs_remove_file', [ $this, 'rtrs_remove_file' ] );
		add_action( 'wp_ajax_nopriv_rtrs_remove_file', [ $this, 'rtrs_remove_file' ] );

	}

	/**
	 * Get reviews by meta.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function get_reviews( $sort_by, $filter_by ) {
		// $per_page = get_option('comments_per_page') == 0 ? 5 : get_option('comments_per_page');

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}

		$per_page = get_option( 'comments_per_page' );
		$cur_page = isset( $_REQUEST['current_page'] ) ? absint( $_REQUEST['current_page'] ) : 1;
		$post_id  = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : null;
		$offset   = ( $cur_page - 1 ) * $per_page;
		$args     = [
			'number'  => $per_page,
			'post_id' => $post_id,
			'status'  => 'approve',
			'type'    => 'review',
			'offset'  => $offset,
		];
		switch ( $sort_by ) {
			case 'top_rated':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary meta query for plugin feature.
				$args['meta_key'] = 'rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';

				break;

			case 'low_rated':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary meta query for plugin feature.
				$args['meta_key'] = 'rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';

				break;

			case 'oldest_first':
				$args['order'] = 'ASC';

				break;
		}

		$args = apply_filters( 'rtrs_review_sort_args', $args, $sort_by );

		if ( $filter_by ) {
			$filter_by_value = [ 1, 5 ];
			switch ( $filter_by ) {
				case '5':
					$filter_by_value = [ 4.01, 5 ];
					break;
				case '4':
					$filter_by_value = [ 3.01, 4 ];
					break;
				case '3':
					$filter_by_value = [ 2.01, 3 ];
					break;
				case '2':
					$filter_by_value = [ 1.01, 2 ];
					break;
				case '1':
					$filter_by_value = [ 1, 1.99 ];
					break;
			}

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
			$args['meta_query'] = [
				[
					'key'     => 'rating',
					'value'   => $filter_by_value,
					'compare' => 'BETWEEN',
				],
			];
		}
		$comments = get_comments( $args );
		// pass post id in comment list
		global $rtrs_post_id;
		$rtrs_post_id = $post_id;

		wp_list_comments(
			[
				'style'      => 'li',
				'short_ping' => true,
				'callback'   => [ ReviewFns::class, 'comment_list' ],
			],
			$comments
		);
	}

	/**
	 * Get review pagination.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function paginate_comments_links( $cur_page, $max_page, $args = [] ) {
		if ( get_option( 'comments_per_page' ) > $max_page ) {
			return '';
		}

		global $wp_rewrite;
		$args = [
			'prev_text' => '<i class="rtrs-angle-left"></i>',
			'next_text' => '<i class="rtrs-angle-right"></i>',
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : null;

		$defaults = [
			'base'         => add_query_arg( 'cpage', '%#%' ),
			'format'       => '',
			'total'        => $max_page,
			'current'      => $cur_page,
			'echo'         => true,
			'type'         => 'plain',
			'add_fragment' => '#comments',
		];

		if ( $wp_rewrite->using_permalinks() ) {
			$defaults['base'] = user_trailingslashit( trailingslashit( get_permalink( $post_id ) ) . $wp_rewrite->comments_pagination_base . '-%#%', 'commentpaged' );
		}

		$args       = wp_parse_args( $args, $defaults );
		$page_links = paginate_links( $args );

		return $page_links;
	}

	/**
	 * Get reviews by meta.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function get_total_reviews( $sort_by, $filter_by ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : null;

		$args = [
			'post_id' => $post_id,
			'count'   => true,
		];

		switch ( $sort_by ) {
			case 'top_rated':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary meta query for plugin feature.
				$args['meta_key'] = 'rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';

				break;

			case 'low_rated':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary meta query for plugin feature.
				$args['meta_key'] = 'rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';

				break;

			case 'oldest_first':
				$args['order'] = 'ASC';

				break;
		}

		$args = apply_filters( 'rtrs_review_sort_args', $args, $sort_by );

		if ( $filter_by ) {
			$filter_by_value = [ 1, 5 ];
			switch ( $filter_by ) {
				case '5':
					$filter_by_value = [ 4.01, 5 ];
					break;
				case '4':
					$filter_by_value = [ 3.01, 4 ];
					break;
				case '3':
					$filter_by_value = [ 2.01, 3 ];
					break;
				case '2':
					$filter_by_value = [ 1.01, 2 ];
					break;
				case '1':
					$filter_by_value = [ 1, 1.99 ];
					break;
			}

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
			$args['meta_query'] = [
				[
					'key'     => 'rating',
					'value'   => $filter_by_value,
					'compare' => 'BETWEEN',
				],
			];
		}

		return get_comments( $args );
	}

	/**
	 * Review filter ajax function.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only sort filter; sanitized via sanitize_text_field().
	public function rtrs_review_filter() {

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only filter parameter; sanitized via sanitize_text_field().

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}

		$sort_by   = isset( $_REQUEST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['sort_by'] ) ) : '';
		$filter_by = isset( $_REQUEST['filter_by'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_by'] ) ) : '';
		$cur_page  = isset( $_REQUEST['current_page'] ) ? absint( $_REQUEST['current_page'] ) : 1;
		// $max_page = isset( $_REQUEST['max_page'] ) ? absint( $_REQUEST['max_page'] ) : 3;
		$max_page = $this->get_total_reviews( $sort_by, $filter_by );

		ob_start();
		$this->get_reviews( $sort_by, $filter_by );
		$review = ob_get_clean();

		$pagination = $this->paginate_comments_links( $cur_page, $max_page );
		wp_send_json_success(
			[
				'review'     => $review,
				'pagination' => $pagination,
				'sort_by'    => $sort_by,
			]
		);
	}

	/**
	 * Review paginaiton ajax.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only sort filter; sanitized via sanitize_text_field().
	public function rtrs_pagination() {

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only filter parameter; sanitized via sanitize_text_field().

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}

		$sort_by   = isset( $_REQUEST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['sort_by'] ) ) : '';
		$filter_by = isset( $_REQUEST['filter_by'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_by'] ) ) : '';
		$cur_page  = isset( $_REQUEST['current_page'] ) ? absint( $_REQUEST['current_page'] ) : 1;
		if ( $sort_by ) {
			$max_page = $this->get_total_reviews( $sort_by, $filter_by );
		} else {
			$max_page = isset( $_REQUEST['max_page'] ) ? absint( $_REQUEST['max_page'] ) : 1;
		}

		ob_start();
		$this->get_reviews( $sort_by, $filter_by );
		$review     = ob_get_clean();
		$pagination = $this->paginate_comments_links( $cur_page, $max_page );
		wp_send_json_success(
			[
				'review'     => $review,
				'pagination' => $pagination,
			]
		);
	}

	/**
	 * Review form upload image.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function rtrs_image_upload() {

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload array; validated/sanitized via wp_handle_upload() downstream.
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}

		if ( ! ReviewFns::canUploadMedia() ) {
			wp_send_json_error( [ 'msg' => esc_html__( 'You must be logged in to upload files.', 'review-schema' ) ], 403 );
		}

		$img_max_size = ReviewFns::getMediaOption( 'img_max_size', 1024 );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated and sanitized via wp_handle_upload() downstream.
		$file               = isset( $_FILES['rtrs-image'] ) ? $_FILES['rtrs-image'] : [];
		$allowed_file_types = ReviewFns::getMediaOption( 'img_type', [ 'image/jpg', 'image/jpeg', 'image/png' ] );
		// Allowed file size -> 2MB
		$allowed_file_size = $img_max_size * 1024;

		if ( ! empty( $file['name'] ) ) {
			// Check file type
			if ( ! in_array( $file['type'], $allowed_file_types ) ) {
				$valid_file_type = str_replace( 'image/', '', implode( ', ', $allowed_file_types ) );
				$error_file_type = str_replace( 'image/', '', $file['type'] );

				wp_send_json_error(
					[
						'msg' => sprintf(
							/* translators: 1: uploaded file extension, 2: comma separated list of allowed file types */
							esc_html__( 'Invalid file type: %1$s. Supported file types: %2$s', 'review-schema' ),
							$error_file_type,
							$valid_file_type
						),
					]
				);
			}

			// Check file size
			if ( $file['size'] > $allowed_file_size ) {
				wp_send_json_error(
					[
						'msg' => sprintf(
							/* translators: %s: maximum allowed upload file size */
							esc_html__( 'File is too large. Max. upload file size is %s', 'review-schema' ),
							Functions::format_bytes( $allowed_file_size )
						),
					]
				);
			}

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			// Frontend/guest requests do not load the admin image + media helpers
			// that wp_generate_attachment_metadata() depends on.
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			$upload_overrides = [ 'test_form' => false ];
			$uploaded         = wp_handle_upload( $file, $upload_overrides );

			if ( $uploaded && ! isset( $uploaded['error'] ) ) {
				$filename = $uploaded['file'];
				$filetype = wp_check_filetype( basename( $filename ), null );

				$attach_id = wp_insert_attachment(
					[
						'guid'            => $uploaded['url'],
						'post_title'      => sanitize_text_field( preg_replace( '/\.[^.]+$/', '', basename( $filename ) ) ),
						'post_excerpt'    => '',
						'post_content'    => '',
						'post_mime_type'  => sanitize_text_field( $filetype['type'] ),
						'post_status'     => 'inherit',
						'comments_status' => 'closed',
					],
					$uploaded['file'],
					0
				);

				$file_info = [];
				if ( ! is_wp_error( $attach_id ) ) {
					wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $filename ) );
					update_post_meta( $attach_id, 'attach_type', 'review' );

					$file_info = [
						'id'  => $attach_id,
						'url' => wp_get_attachment_image_url( $attach_id, 'thumbnail' ),
					];
				}

				wp_send_json_success( [ 'file_info' => $file_info ] );
			} else {
				/*
				 * Error generated by _wp_handle_upload()
				 * @see _wp_handle_upload() in wp-admin/includes/file.php
				 */
				wp_send_json_error( [ 'msg' => $uploaded['error'] ] );
			}
		}
	}

	/**
	 * Review form upload video.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload array; validated/sanitized via wp_handle_upload() downstream.
	 */
	public function rtrs_video_upload() {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}

		if ( ! ReviewFns::canUploadMedia() ) {
			wp_send_json_error( [ 'msg' => esc_html__( 'You must be logged in to upload files.', 'review-schema' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated and sanitized via wp_handle_upload() downstream.
		$file               = isset( $_FILES['rtrs-video'] ) ? $_FILES['rtrs-video'] : [];
		$allowed_file_types = ReviewFns::getMediaOption( 'video_type', [ 'video/mp4', 'video/mov', 'video/avi' ] );

		$video_max_size    = ReviewFns::getMediaOption( 'video_max_size', 2048 );
		$allowed_file_size = $video_max_size * 1024;

		if ( ! empty( $file['name'] ) ) {
			// Check file type
			if ( ! in_array( $file['type'], $allowed_file_types ) ) {
				$valid_file_type = str_replace( 'video/', '', implode( ', ', $allowed_file_types ) );
				$error_file_type = str_replace( 'video/', '', $file['type'] );

				wp_send_json_error(
					[
						'msg' => sprintf(
							/* translators: 1: uploaded file extension, 2: comma separated list of allowed file types */
							esc_html__( 'Invalid file type: %1$s. Supported file types: %2$s', 'review-schema' ),
							$error_file_type,
							$valid_file_type
						),
					]
				);
			}

			// Check file size
			if ( $file['size'] > $allowed_file_size ) {
				wp_send_json_error(
					[
						'msg' => sprintf(
							/* translators: %s: maximum allowed upload file size */
							esc_html__( 'File is too large. Max. upload file size is %s', 'review-schema' ),
							Functions::format_bytes( $allowed_file_size )
						),
					]
				);
			}

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$upload_overrides = [ 'test_form' => false ];
			$uploaded         = wp_handle_upload( $file, $upload_overrides );

			if ( $uploaded && ! isset( $uploaded['error'] ) ) {
				$filename = $uploaded['file'];
				$filetype = wp_check_filetype( basename( $filename ), null );

				// Todo: think about sanitization here
				$attach_id = wp_insert_attachment(
					[
						'guid'           => $uploaded['url'],
						'post_title'     => sanitize_text_field( preg_replace( '/\.[^.]+$/', '', basename( $filename ) ) ),
						'post_excerpt'   => '',
						'post_content'   => '',
						'post_mime_type' => sanitize_text_field( $filetype['type'] ),
						'post_status'    => 'inherit',
					],
					$uploaded['file'],
					0
				);

				$file_info = [];
				if ( ! is_wp_error( $attach_id ) ) {
					update_post_meta( $attach_id, 'attach_type', 'review' );

					$file_info = [
						'id'   => $attach_id,
						'name' => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
					];
				}

				wp_send_json_success( [ 'file_info' => $file_info ] );
			} else {
				/*
				 * Error generated by _wp_handle_upload()
				 * @see _wp_handle_upload() in wp-admin/includes/file.php
				 */
				wp_send_json_error( [ 'msg' => $uploaded['error'] ] );
			}
		}
	}

	/**
	 * Review form remove file (image, video).
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function rtrs_remove_file() {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$attachment_id = isset( $_REQUEST['attachment_id'] ) ? absint( $_REQUEST['attachment_id'] ) : '';

		if ( ! $attachment_id ) {
			wp_send_json_error();
		}

		// Guests may only remove an unattached review upload they just created
		// (post_parent 0 = not yet linked to a saved review). Logged-in users
		// fall back to the standard capability check.
		$is_removable_guest_upload = ReviewFns::isGuestUploadEnabled()
			&& 'review' === get_post_meta( $attachment_id, 'attach_type', true )
			&& 0 === (int) wp_get_post_parent_id( $attachment_id );

		if ( ! current_user_can( 'delete_post', $attachment_id ) && ! $is_removable_guest_upload ) {
			return;
		}

		$deleted = wp_delete_attachment( $attachment_id );
		if ( $deleted ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Review edit form.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function rtrs_review_edit_form() {

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			 return;
		}

		$comment_post_id = isset( $_REQUEST['comment_post_id'] ) ? absint( $_REQUEST['comment_post_id'] ) : null;
		$comment_id      = isset( $_REQUEST['comment_id'] ) ? absint( $_REQUEST['comment_id'] ) : null;
		$comment_data    = get_comment( $comment_id );
		$review_edit     = rtrs()->get_options( 'rtrs_review_settings', [ 'review_edit', 'yes' ] );
		if ( $review_edit != 'yes' || $comment_data->user_id != get_current_user_id() ) {
			wp_send_json_error( esc_html__( 'Sorry! You do not have permission.', 'review-schema' ) );
		}
		// review edit global settings
		$review_edit_field = rtrs()->get_options( 'rtrs_review_settings', [ 'review_edit_field', [ 'rating', 'desc' ] ] );

		ob_start();
		$post_type = get_post_type( $comment_post_id );
		if ( ! Functions::isEnableReviewByPostType( $post_type ) ) {
			return;
		}

		$p_meta = Functions::getMetaByPostType( $post_type );
		if ( ! $p_meta ) {
			return;
		} // get back if not added

		$criteria       = ( isset( $p_meta['criteria'] ) && $p_meta['criteria'][0] == 'multi' );
		$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;

		echo '<div class="rtrs-modal">';
		echo '<div class="rtrs-review-form rtrs-review-popup">';
		echo '<button type="button" class="rtrs-modal-close" aria-label="' . esc_attr__( 'Close', 'review-schema' ) . '">&times;</button>';
		echo '<h2 id="reply-title" class="rtrs-form-title">' . esc_html__( 'Edit your review', 'review-schema' ) . '</h2>';
		echo '<form action="#" method="post" class="rtrs-form-box">';
		$criteria_style = null;
		if ( $criteria && $multi_criteria ) {
			// add css for odd
			if ( count( $multi_criteria ) % 2 != 0 ) {
				$criteria_style = 'grid-template-columns: repeat(1, 270px);';
			}
		}
		if ( ! $comment_data->comment_parent ) {
			echo '<div class="rtrs-form-group rtrs-hide-reply"><ul class="rtrs-rating-category" style="' . esc_attr( $criteria_style ) . '">';

			if ( $criteria && $multi_criteria ) {
				$criteria_count = 1;
				foreach ( $multi_criteria as $key => $value ) :
					$slug = 'rt_rating_' . md5( $value ); ?>
				<li>
					<div class="rtrs-category-text"><?php echo esc_html( $value ); ?></div> 
					<div class="rtrs-rating-container">
						<?php
							$rt_rating_criteria = get_comment_meta( $comment_id, 'rt_rating_criteria', true );
						// if enable non criteria to criteria
						if ( ! $rt_rating_criteria ) {
							$rating             = get_comment_meta( $comment_id, 'rating', true );
							$rating_only[]      = $rating;
							$rt_rating_criteria = $rating_only;
						}
						for ( $i = 5; $i >= 1; $i-- ) :

							$checked = isset( $rt_rating_criteria[ $criteria_count - 1 ] ) && ( $rt_rating_criteria[ $criteria_count - 1 ] == $i ) ? 'checked' : '';
							?>
							<input <?php echo esc_attr( $checked ); ?> type="radio" id="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>" name="<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $i ); ?>" /><label for="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
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
						$rt_rating = get_comment_meta( $comment_id, 'rating', true );
						for ( $i = 5; $i >= 1; $i-- ) :
							$checked = isset( $rt_rating ) && ( $rt_rating == $i ) ? 'checked' : '';
							?>
							<input <?php echo esc_attr( $checked ); ?> type="radio" id="rt-rating-<?php echo esc_attr( $i ); ?>" name="rt_rating" value="<?php echo esc_attr( $i ); ?>" /><label for="rt-rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
						<?php endfor; ?> 
					</div> 
				</li> 
				<?php
			}
			echo '</ul></div>';
		}

		$pros_cons = ( isset( $p_meta['pros_cons'] ) && $p_meta['pros_cons'][0] == '1' );
		if ( $pros_cons && in_array( 'pros_cons', $review_edit_field ) ) {
			?>
		<div class="rtrs-form-group rtrs-hide-reply">
			<div class="rtrs-feedback-input"> 
				<div class="rtrs-input-item rtrs-pros">
					<h3 class="rtrs-input-title">
						<span class="item-icon"><i class="rtrs-thumbs-up"></i></span>
						<span class="item-text"><?php esc_html_e( 'PROS', 'review-schema' ); ?></span>
					</h3>
					<?php
						$pros_cons = get_comment_meta( $comment_id, 'rt_pros_cons', true );
					if ( isset( $pros_cons['pros'] ) ) {
						foreach ( $pros_cons['pros'] as $key => $value ) {
							?>
								<div class="rtrs-input-filed">
									<span class="rtrs-remove-btn">+</span>
									<input type="text" value="<?php echo esc_attr( $value ); ?>" class="form-control" name="rt_pros[]" placeholder="<?php esc_attr_e( 'Write here!', 'review-schema' ); ?>">
								</div>
							<?php
						}
					} else {
						?>
							<div class="rtrs-input-filed">
								<span class="rtrs-remove-btn">+</span>
								<input type="text" class="form-control" name="rt_pros[]" placeholder="<?php esc_attr_e( 'Write here!', 'review-schema' ); ?>">
							</div>
					<?php } ?> 
					<div class="rtrs-field-add"><i class="rtrs-plus"></i><?php esc_html_e( 'Add Field', 'review-schema' ); ?></div>
				</div>

				<div class="rtrs-input-item rtrs-cons">
					<h3 class="rtrs-input-title">
						<span class="item-icon unlike-icon"><i class="rtrs-thumbs-down"></i></span>
						<span class="item-text"><?php esc_html_e( 'CONS', 'review-schema' ); ?></span>
					</h3>
					<?php
						$pros_cons = get_comment_meta( $comment_id, 'rt_pros_cons', true );
					if ( isset( $pros_cons['cons'] ) ) {
						foreach ( $pros_cons['cons'] as $key => $value ) {
							?>
								<div class="rtrs-input-filed">
									<span class="rtrs-remove-btn">+</span>
									<input type="text" value="<?php echo esc_attr( $value ); ?>" class="form-control" name="rt_cons[]" placeholder="<?php esc_attr_e( 'Write here!', 'review-schema' ); ?>">
								</div>
							<?php
						}
					} else {
						?>
							<div class="rtrs-input-filed">
								<span class="rtrs-remove-btn">+</span>
								<input type="text" class="form-control" name="rt_cons[]" placeholder="<?php esc_attr_e( 'Write here!', 'review-schema' ); ?>">
							</div>
					<?php } ?> 
					
					<div class="rtrs-field-add"><i class="rtrs-plus"></i><?php esc_html_e( 'Add Field', 'review-schema' ); ?></div>
				</div>
			</div>
		</div> 
			<?php
		}

		$image_review = ( isset( $p_meta['image_review'] ) && $p_meta['image_review'][0] == '1' );
		if ( $image_review && in_array( 'image', $review_edit_field ) ) {
			$saved_attachment    = get_comment_meta( $comment_id, 'rt_attachment', true );
			$saved_image_source  = ( is_array( $saved_attachment ) && isset( $saved_attachment['image_source'] ) ) ? $saved_attachment['image_source'] : 'self';
			$saved_external_url  = '';
			if ( 'external' === $saved_image_source && ! empty( $saved_attachment['imgs'][0] ) ) {
				$saved_external_url = is_string( $saved_attachment['imgs'][0] ) ? $saved_attachment['imgs'][0] : '';
			}
			$is_logged_in = is_user_logged_in();
			$default_src  = $is_logged_in ? $saved_image_source : 'external';
			?>
		<div class="rtrs-image-media-groups">
			<div class="rtrs-form-group rtrs-media-form-group rtrs-hide-reply">
				<div class="rtrs-button-label">
					<label class="rtrs-input-image-label"><?php esc_html_e( 'Image', 'review-schema' ); ?></label>
				</div>

				<div class="rtrs-image-source-selector">
					<select name="rt_image_source" id="rtrs-image-source" class="rtrs-form-control">
						<option value="self" <?php selected( $default_src, 'self' ); ?>><?php esc_html_e( 'Upload Image', 'review-schema' ); ?></option>
						<option value="external" <?php selected( $default_src, 'external' ); ?>><?php esc_html_e( 'External Image URL', 'review-schema' ); ?></option>
					</select>
				</div>

				<?php if ( $is_logged_in ) { ?>
					<div class="rtrs-source-image"<?php echo 'external' === $default_src ? ' style="display:none;"' : ''; ?>>
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
								esc_url( wp_login_url( get_permalink( $comment_post_id ) ) )
							);
							?>
						</p>
					</div>
				<?php } ?>
			</div>

			<?php if ( $is_logged_in ) { ?>
				<div class="rtrs-form-group rtrs-hide-reply"<?php echo 'external' === $default_src ? ' style="display:none;"' : ''; ?>>
					<div class="rtrs-preview-imgs"></div>
				</div>
			<?php } ?>

			<div class="rtrs-form-group rtrs-source-external-image rtrs-hide-reply"<?php echo 'external' !== $default_src ? ' style="display:none;"' : ''; ?>>
				<label class="rtrs-input-label" for="rt_external_image"><?php esc_html_e( 'External Image URL', 'review-schema' ); ?></label>
				<input id="rt_external_image" class="rtrs-form-control" placeholder="https://example.com/image.jpg" name="rt_external_image" type="url" value="<?php echo esc_attr( $saved_external_url ); ?>">
				<div class="rtrs-external-image-preview">
					<?php if ( $saved_external_url ) { ?>
						<img src="<?php echo esc_url( $saved_external_url ); ?>" alt="">
					<?php } ?>
				</div>
			</div>
		</div>
			<?php
		}

		/**
		 * Hook to render video edit form field (Pro feature).
		 */
		do_action( 'rtrs_review_edit_form_video_field', $p_meta, $comment_id, $review_edit_field );

		/**
		 * Hook to render recommendation edit form field (Pro feature).
		 */
		do_action( 'rtrs_review_edit_form_recommendation_field', $p_meta, $comment_id, $review_edit_field );

		/**
		 * Hook to render anonymous edit form field (Pro feature).
		 */
		do_action( 'rtrs_review_edit_form_anonymous_field', $p_meta, $comment_id, $review_edit_field );
		?>
		
		<?php if ( in_array( 'title', $review_edit_field ) ) { ?>
		<div class="rtrs-form-group rtrs-hide-reply">
			<input id="rt_title" class="rtrs-form-control" placeholder="<?php esc_attr_e( 'Title', 'review-schema' ); ?>" name="rt_title" value="<?php echo esc_attr( get_comment_meta( $comment_id, 'rt_title', true ) ); ?>" type="text" value="" size="30" aria-required="true">
		</div>
		<?php } ?>
		
		<?php if ( in_array( 'desc', $review_edit_field ) ) { ?>
		<div class="rtrs-form-group">
			<textarea id="message" class="rtrs-form-control" placeholder="<?php esc_attr_e( 'Write your review', 'review-schema' ); ?>" name="comment" aria-required="true" rows="6" cols="45"><?php
			$comment = get_comment( intval( $comment_id ) );
			echo wp_kses_post( trim( (string) $comment->comment_content ) );
			?></textarea>
		</div>
		<?php } ?>

		<div class="rtrs-form-group">
			<input name="submit" type="submit" id="submit" class="rtrs-submit-btn rtrs-review-edit-submit" value="<?php esc_attr_e( 'Submit Review', 'review-schema' ); ?>"> 
			<input type="hidden" name="action" value="rtrs_review_edit">
			<input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( $comment_post_id ); ?>" id="comment_post_ID">
			<input type="hidden" name="comment_ID" value="<?php echo esc_attr( $comment_id ); ?>" id="comment_ID">
			<input type="hidden" name="comment_parent" id="comment_parent" value="0">
			<?php // wp_nonce_field( 'rtrs-review-edit' ); ?>
			<?php // wp_nonce_field( rtrs()->getNonceText(), rtrs()->getNonceId() ); ?>
			<?php // wp_nonce_field( 'update-comment_' . $comment_id ); ?>
		</div>

		<?php
		echo '</form>';
		echo '</div>';
		echo '</div>'; // modal
		$edit_form = ob_get_clean();
		wp_send_json_success( $edit_form );
	}

	/**
	 * Review edit.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Comment ID; processed via absint() below.
	public function rtrs_review_edit() {

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			 return;
		}

		$comment_id = isset( $_POST['comment_ID'] ) ? absint( $_POST['comment_ID'] ) : 0;
		// WordFence Responsible Disclosure Reported Issue Fixed.
		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			$comment = get_comment( $comment_id );
			if ( get_current_user_id() != $comment->user_id ) {
				return;
			}
		}

		// not isset means not enable criteria
		if ( ! isset( $_POST['rt_rating'] ) ) {
			$post_id = isset( $_POST['comment_post_ID'] ) ? absint( $_POST['comment_post_ID'] ) : 0;

			$p_meta         = Functions::getMetaByPostType( get_post_type( $post_id ) );
			$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;

			if ( $multi_criteria ) {
				$i               = $total               = $avg_rating               = 0;
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
		if ( isset( $_POST['rt_title'] ) && ( '' !== $_POST['rt_title'] ) ) {
			update_comment_meta( $comment_id, 'rt_title', sanitize_text_field( wp_unslash( $_POST['rt_title'] ) ) );
		}

		/**
		 * Hook to save pro meta on review edit (highlight, recommendation, anonymous, pros/cons).
		 */
		do_action( 'rtrs_review_edit_pro_meta_save', $comment_id );

		// add image & video
		$attachments = [];
		if ( isset( $_POST['rt_image_source'] ) && $_POST['rt_image_source'] == 'self' ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- External image URL; sanitized via esc_url_raw().
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
			update_comment_meta( $comment_id, 'rt_attachment', $attachments );
		}

		if ( isset( $_POST['comment'] ) ) {
			// comment data
			$commentarr = [
				'comment_ID'      => $comment_id,
				'comment_content' => wp_kses_post( wp_unslash( $_POST['comment'] ) ),
			];
			// update data in the database
			wp_update_comment( $commentarr );
		}
		wp_send_json_success();
	}

	/**
	 * Self Hosted Video Popup.
	 *
	 * @since 1.0.0
	 *
	 // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Video URL; passed through esc_url() at output point.
	 * @return mixed
	 */
	public function rtrs_self_video_popup() {
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed' ], 403 );
		}
		$video_url = isset( $_REQUEST['video_url'] ) ? esc_url_raw( wp_unslash( $_REQUEST['video_url'] ) ) : null;
		ob_start();

		do_action( 'rtrs_before_self_hosted_popup' );

		echo '<div class="rtrs-modal">';
		echo '<div class="rtrs-review-form rtrs-review-popup">';
		echo '<button type="button" class="rtrs-modal-close" aria-label="' . esc_attr__( 'Close', 'review-schema' ) . '">&times;</button>';
		echo '<div class="rtrs-self-video"><video src="' . esc_url( $video_url ) . '"  autoplay controls /></div>';

		echo '</div>';
		echo '</div>'; // modal
		do_action( 'rtrs_after_self_hosted_popup' );

		$edit_form = ob_get_clean();
		wp_send_json_success( $edit_form );
	}
}
