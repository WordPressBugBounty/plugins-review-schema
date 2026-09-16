<?php

namespace Rtrs\Modules\Review\Admin;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Traits\SingletonTrait;
use WP_Comment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewSettings {
	use SingletonTrait;

	// class instance
	static $instance;

	// customer WP_List_Table object
	public $reviews_obj;

	// class constructor
	public function __construct() {
		add_filter( 'set-screen-option', [ $this, 'set_screen' ], 25, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ], 30 );
	}

	public function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {
		$hook = add_submenu_page(
			'review-schema',
			esc_html__( 'All Reviews', 'review-schema' ),
			esc_html__( 'All Reviews', 'review-schema' ),
			'manage_options',
			'rtrs-reviews',
			[ $this, 'plugin_settings_page' ],
			1
		);

		add_action( "load-$hook", [ $this, 'screen_option' ] );
	}

	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
			global $action;
			$action = 'editreview';

			$title = esc_html__( 'Edit Review', 'review-schema' );

			wp_enqueue_script( 'comment' );
			require_once ABSPATH . 'wp-admin/admin-header.php';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$comment_id = isset( $_GET['c'] ) ? absint( $_GET['c'] ) : 0;

			$comment = get_comment( $comment_id );

			if ( ! $comment ) {
				comment_footer_die( esc_html__( 'Invalid comment ID.', 'review-schema' ) . sprintf( ' <a href="%s">' . esc_html__( 'Go back', 'review-schema' ) . '</a>.', 'javascript:history.go(-1)' ) );
			}

			$can_edit_review = post_type_exists( (string) get_post_type( $comment->comment_post_ID ) )
				? current_user_can( 'edit_comment', $comment_id )
				: current_user_can( 'moderate_comments' );
			if ( ! $can_edit_review ) {
				comment_footer_die( esc_html__( 'Sorry, you are not allowed to edit this comment.', 'review-schema' ) );
			}

			if ( 'trash' == $comment->comment_approved ) {
				comment_footer_die( esc_html__( 'This comment is in the Trash. Please move it out of the Trash if you want to edit it.', 'review-schema' ) );
			}
			?>
			<form name="post" action="comment.php" method="post" id="post">
				<?php wp_nonce_field( 'update-comment_' . $comment->comment_ID ); ?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Edit Review', 'review-schema' ); ?></h1>
					<div id="poststuff">
						<input type="hidden" name="action" value="editedcomment" />
						<input type="hidden" name="comment_ID" value="<?php echo esc_attr( $comment->comment_ID ); ?>" />
						<input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( $comment->comment_post_ID ); ?>" />
						<div id="post-body" class="metabox-holder columns-2">
							<div id="post-body-content" class="edit-form-section edit-comment-section">
								<?php
								if ( 'approved' === wp_get_comment_status( $comment ) && $comment->comment_post_ID > 0 ) :
									$comment_link = get_comment_link( $comment );
									?>
								<div class="inside">
									<div id="comment-link-box">
										<strong><?php esc_html_e( 'Permalink:', 'review-schema' ); ?></strong>
										<span id="sample-permalink">
										<a href="<?php echo esc_url( $comment_link ); ?>">
										<?php echo esc_html( $comment_link ); ?>
										</a>
										</span>
									</div>
								</div>
								<?php endif; ?>
								<div id="namediv" class="stuffbox">
									<div class="inside">
										<h2 class="edit-comment-author"><?php esc_html_e( 'Author', 'review-schema' ); ?></h2>
										<fieldset>
										<legend class="screen-reader-text"><?php esc_html_e( 'Comment Author', 'review-schema' ); ?></legend>
										<table class="form-table editcomment" role="presentation">
											<tbody>
												<tr>
													<td class="first"><label for="name"><?php esc_html_e( 'Name', 'review-schema' ); ?></label></td>
													<td><input type="text" name="newcomment_author" size="30" value="<?php echo esc_attr( $comment->comment_author ); ?>" id="name" /></td>
												</tr>
												<tr>
													<td class="first"><label for="email"><?php esc_html_e( 'Email', 'review-schema' ); ?></label></td>
													<td>
													<input type="text" name="newcomment_author_email" size="30" value="<?php echo esc_attr( $comment->comment_author_email ); ?>" id="email" />
													</td>
												</tr>
												<tr>
													<td class="first"><label for="newcomment_author_url"><?php esc_html_e( 'URL', 'review-schema' ); ?></label></td>
													<td>
													<input type="text" id="newcomment_author_url" name="newcomment_author_url" size="30" class="code" value="<?php echo esc_url( $comment->comment_author_url ); ?>" />
													</td>
												</tr>                     
											</tbody>
										</table>
										</fieldset>
									</div>
								</div>
								<div id="postdiv" class="postarea">
									<?php
										echo '<label for="content" class="screen-reader-text">' . esc_html__( 'Comment', 'review-schema' ) . '</label>';
										$quicktags_settings = [ 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' ];
										wp_editor(
											$comment->comment_content,
											'content',
											[
												'media_buttons' => false,
												'tinymce' => false,
												'quicktags' => $quicktags_settings,
												'textarea_rows' => '6',
											]
										);
										 wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
									?>
								</div> 
								
								<!-- <input type="hidden" name="rtrs-edit-review-nonce" id="rtrs-edit-review-nonce" value="--><?php // echo wp_create_nonce( "rtrs-nonce" ); ?><!--">-->
							</div>
				
							<!-- /post-body-content -->
							<div id="postbox-container-1" class="postbox-container">
								<div id="submitdiv" class="stuffbox" >
									<h2><?php esc_html_e( 'Save', 'review-schema' ); ?></h2>
									<div class="inside">
										<div class="submitbox" id="submitcomment">
										<div id="minor-publishing">
											<div id="misc-publishing-actions">
												<div class="misc-pub-section misc-pub-comment-status" id="comment-status">
													<?php esc_html_e( 'Status:', 'review-schema' ); ?>
													<span id="comment-status-display">
													<?php
													switch ( $comment->comment_approved ) {
														case '1':
															esc_html_e( 'Approved', 'review-schema' );
															break;
														case '0':
															esc_html_e( 'Pending', 'review-schema' );
															break;
														case 'spam':
															esc_html_e( 'Spam', 'review-schema' );
															break;
													}
													?>
													</span>
													<fieldset id="comment-status-radio">
													<legend class="screen-reader-text"><?php esc_html_e( 'Comment status', 'review-schema' ); ?></legend>
													<label><input type="radio"<?php checked( $comment->comment_approved, '1' ); ?> name="comment_status" value="1" /><?php echo esc_html_x( 'Approved', 'comment status', 'review-schema' ); ?></label><br />
													<label><input type="radio"<?php checked( $comment->comment_approved, '0' ); ?> name="comment_status" value="0" /><?php echo esc_html_x( 'Pending', 'comment status', 'review-schema' ); ?></label><br />
													<label><input type="radio"<?php checked( $comment->comment_approved, 'spam' ); ?> name="comment_status" value="spam" /><?php echo esc_html_x( 'Spam', 'comment status', 'review-schema' ); ?></label>
													</fieldset>
												</div>
												<!-- .misc-pub-section -->
												<div class="misc-pub-section curtime misc-pub-curtime">
													<?php
													$submitted = sprintf(
														/* translators: 1: Comment date, 2: Comment time. */
														'%1$s at %2$s',
														/* translators: Publish box date format, see https://www.php.net/date */
														date_i18n( esc_html_x( 'M j, Y', 'publish box date format', 'review-schema' ), strtotime( $comment->comment_date ) ),
														/* translators: Publish box time format, see https://www.php.net/date */
														date_i18n( esc_html_x( 'H:i', 'publish box time format', 'review-schema' ), strtotime( $comment->comment_date ) )
													);
													?>
													<span id="timestamp">
													<?php
													echo wp_kses(
														sprintf(
															/* translators: %s: Comment date. */
															__( 'Submitted on: %s', 'review-schema' ),
															'<b>' . esc_html( $submitted ) . '</b>'
														),
														[ 'b' => [] ]
													);
													?>
													</span>
													<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js">
													<span aria-hidden="true"><?php esc_html_e( 'Edit', 'review-schema' ); ?></span> 
													<span class="screen-reader-text"><?php esc_html_e( 'Edit date and time', 'review-schema' ); ?></span>
													</a>
													<fieldset id='timestampdiv' class='hide-if-js'>
													<legend class="screen-reader-text"><?php esc_html_e( 'Date and time', 'review-schema' ); ?></legend>
													<?php
													// Todo: remove rtrs touch time and change editreview
													isset( $action ) ? ReviewFns::touch_time( ( 'editreview' === $action ), 0, 0, 0, $comment->comment_date ) : '';
													?>
													</fieldset>
												</div>
												<?php
													$post_id = $comment->comment_post_ID;
												if ( post_type_exists( (string) get_post_type( $post_id ) ) && current_user_can( 'edit_post', $post_id ) ) {
													$post_link  = "<a href='" . esc_url( get_edit_post_link( $post_id ) ) . "'>";
													$post_link .= esc_html( get_the_title( $post_id ) ) . '</a>';
												} else {
													$post_link = esc_html( get_the_title( $post_id ) );
												}
												?>
												<div class="misc-pub-section misc-pub-response-to">
													<?php
													echo wp_kses(
														sprintf(
															/* translators: %s: Post link. */
															__( 'In response to: %s', 'review-schema' ),
															'<b>' . $post_link . '</b>'
														),
														[
															'a' => [ 'href' => [] ],
															'b' => [],
														]
													);
													?>
												</div>
												<?php
												if ( $comment->comment_parent ) :
													$parent = get_comment( $comment->comment_parent );
													if ( $parent ) :
														$parent_link = esc_url( get_comment_link( $parent ) );
														$name        = get_comment_author( $parent );
														?>
														<div class="misc-pub-section misc-pub-reply-to">
														<?php
														printf(
															wp_kses(
																/* translators: %s: Comment link. */
																__( 'In reply to: %s', 'review-schema' ),
																[
																	'a' => [ 'href' => [] ],
																	'b' => [],
																]
															),
															'<b><a href="' . esc_url( $parent_link ) . '">' . esc_html( $name ) . '</a></b>'
														);
														?>
														</div>
														<?php
														endif;
													endif;

													/**
													 * Filters miscellaneous actions for the edit comment form sidebar.
													*
													* @since 4.3.0
													*
													* @param string     $html    Output HTML to display miscellaneous action.
													* @param WP_Comment $comment Current comment object.
													*/
													// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "edit_comment_misc_actions" is a WP core admin filter; we apply it to integrate with the core comment edit UI.
													echo wp_kses_post( apply_filters( 'edit_comment_misc_actions', '', $comment ) );
												?>
											</div>
											<!-- misc actions -->
											<div class="clear"></div>
										</div>
										<div id="major-publishing-actions">
											<div id="delete-action">
												<?php
												$rtrs_delete_url = wp_nonce_url(
													'comment.php?action=' . ( ! EMPTY_TRASH_DAYS ? 'deletecomment' : 'trashcomment' )
														. '&c=' . $comment->comment_ID
														. '&_wp_original_http_referer=' . rawurlencode( wp_get_referer() ),
													'delete-comment_' . $comment->comment_ID
												);
												$rtrs_delete_label = ! EMPTY_TRASH_DAYS
													? __( 'Delete Permanently', 'review-schema' )
													: __( 'Move to Trash', 'review-schema' );
												printf(
													'<a class="submitdelete deletion" href="%1$s">%2$s</a>' . "\n",
													esc_url( $rtrs_delete_url ),
													esc_html( $rtrs_delete_label )
												);
												?>
											</div>
											<div id="publishing-action">
												<?php submit_button( esc_html__( 'Update', 'review-schema' ), 'primary large', 'save', false ); ?>
											</div>
											<div class="clear"></div>
										</div>
										</div>
									</div>
								</div>
								<!-- /submitdiv -->
							</div>
							
							<div id="postbox-container-2" class="postbox-container">
								<div id="normal-sortables" class="meta-box-sortables">
									<div id="rtrs_comment_meta" class="postbox " >
										<div class="postbox-header">
											<h2 class="hndle ui-sortable-handle"><?php esc_html_e( 'Review Options', 'review-schema' ); ?></h2> 
										</div>
										<div class="inside">
											<table class="form-table editcomment rtrs-edit-review" role="presentation">
												<tbody>
													<?php do_action( 'rtrs_before_review_edit_form' ); ?>
													<tr>
														<?php
															$post_type = get_post_type( $comment->comment_post_ID );
															$p_meta    = Functions::getMetaByPostType( $post_type );
															$criteria  = ( isset( $p_meta['criteria'] ) && $p_meta['criteria'][0] == 'multi' );
														?>
														<td class="first">
															<label for="rtrs-criteria">
															<?php
															if ( $criteria ) {
																esc_html_e( 'Multi Criteria', 'review-schema' );
															} else {
																esc_html_e( 'Rating', 'review-schema' );
															}
															?>
															</label>
														</td>
														<td> 
															<?php
															$multi_criteria = isset( $p_meta['multi_criteria'] ) ? unserialize( $p_meta['multi_criteria'][0] ) : null;
															$rating_only    = []; // without multi criteria
															$criteria_count = 1;
															if ( $criteria && $multi_criteria ) {
																foreach ( $multi_criteria as $key => $value ) :
																	$slug = 'rt_rating_' . md5( $value );
																	?>
																																<label for="rating"><?php echo esc_html( $value ); ?></label>
																<fieldset class="rtrs-comments-rating">
																	<span class="rtrs-rating-container">
																		<?php
																		$rt_rating_criteria = get_comment_meta( $comment->comment_ID, 'rt_rating_criteria', true );
																		// if enable non criteria to criteria
																		if ( ! $rt_rating_criteria ) {
																			$rating             = get_comment_meta( $comment->comment_ID, 'rating', true );
																			$rating_only[]      = $rating;
																			$rt_rating_criteria = $rating_only;
																		}

																		for ( $i = 5; $i >= 1; $i-- ) :
																			$checked = isset( $rt_rating_criteria[ $criteria_count - 1 ] ) && ( $rt_rating_criteria[ $criteria_count - 1 ] == $i ) ? 'checked' : '';
																			?>
																			<input <?php echo esc_attr( $checked ); ?> type="radio" id="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>" name="<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $i ); ?>" /><label for="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
																		<?php endfor; ?> 
																	</span>
																</fieldset>
																	<?php
																	$criteria_count++;
endforeach;
															} else {
																?>
															
																<fieldset class="rtrs-comments-rating">
																	<span class="rtrs-rating-container">
																		<?php
																		$rt_rating = get_comment_meta( $comment->comment_ID, 'rating', true );
																		for ( $i = 5; $i >= 1; $i-- ) :
																			$checked = isset( $rt_rating ) && ( $rt_rating == $i ) ? 'checked' : '';
																			?>
																			<input <?php echo esc_attr( $checked ); ?> type="radio" id="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>" name="rt_rating" value="<?php echo esc_attr( $i ); ?>" /><label for="<?php echo esc_attr( $criteria_count ); ?>-rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
																		<?php endfor; ?> 
																	</span>
																</fieldset>
																<?php
															}
															?>
														</td> 
													</tr>  
													<tr>
														<td class="first"><label for="rt_title"><?php esc_html_e( 'Title', 'review-schema' ); ?></label></td>
														<td><input type="text" name="rt_title" size="30" value="<?php echo esc_attr( get_comment_meta( $comment->comment_ID, 'rt_title', true ) ); ?>" id="rt_title"></td>
													</tr>
													
													<?php
													$recommendation = ( isset( $p_meta['recommendation'] ) && $p_meta['recommendation'][0] == '1' );
													if ( $recommendation && function_exists( 'rtrsp' ) ) {
														?>
													<tr>
														<td class="first"><label for="rtrs-recommendation"><?php esc_html_e( 'Recommendation', 'review-schema' ); ?></label></td>
														<td>  
														<fieldset>
															<span class="rtrs-recommendation">
																<input 
																<?php
																if ( get_comment_meta( $comment->comment_ID, 'rt_recommended', true ) ) {
																	echo 'checked';}
																?>
																type="radio" id="rtrs-rec-happy" name="rt_recommended" value="1"><label for="rtrs-rec-happy"><?php esc_html_e( 'Happy', 'review-schema' ); ?></label>
																<input 
																<?php
																if ( get_comment_meta( $comment->comment_ID, 'rt_recommended', true ) == 0 ) {
																	echo 'checked';}
																?>
																type="radio" id="rtrs-rec-normal" name="rt_recommended" value="-1"><label for="rtrs-rec-normal"><?php esc_html_e( 'Sad', 'review-schema' ); ?></label>
																<input 
																<?php
																if ( get_comment_meta( $comment->comment_ID, 'rt_recommended', true ) == -1 ) {
																	echo 'checked';}
																?>
																type="radio" id="rtrs-rec-sad" name="rt_recommended" value="0"><label for="rtrs-rec-sad"><?php esc_html_e( 'Normal', 'review-schema' ); ?></label> 
															</span>
														</fieldset>
														</td>
													</tr>
													<?php } ?>
													<tr>
														<td class="first"><label><?php esc_html_e( 'Pros', 'review-schema' ); ?></label></td>
														<td>  
															<?php
																$pros_cons = get_comment_meta( $comment->comment_ID, 'rt_pros_cons', true );
																$h         = null;
																$h        .= "<div class='rtrs-repeater' id='rtrs-pros'>";
															if ( isset( $pros_cons['pros'] ) ) {
																foreach ( $pros_cons['pros'] as $key => $value ) {
																	$h .= "<label><input type='text' name='rt_pros[]' value='" . esc_attr( $value ) . "'><i class='dashicons dashicons-move'></i> <i class='remove dashicons dashicons-dismiss'></i>
																					</label>";
																}
															} else {
																$h .= "<label><input type='text' name='rt_pros[]' value=''><i class='dashicons dashicons-move'></i> <i class='remove dashicons dashicons-dismiss'></i></label>";
															}
																$h .= '</div>';
																$h .= "<a href='#' data-type='edit'><i class='dashicons dashicons-insert'></i> " . esc_html__( 'Add Pros', 'review-schema' ) . '</a>';
																// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $h is built with esc_attr/esc_html__/esc_url on dynamic parts.
																echo $h;
															?>
																													</td>
													</tr>												
													<tr>
														<td class="first"><label><?php esc_html_e( 'Cons', 'review-schema' ); ?></label></td>
														<td>   
															<?php
																$h  = null;
																$h .= "<div class='rtrs-repeater' id='rtrs-cons'>";
															if ( isset( $pros_cons['cons'] ) ) {
																foreach ( $pros_cons['cons'] as $key => $value ) {
																	$h .= "<label><input type='text' name='rt_cons[]' value='" . esc_attr( $value ) . "'><i class='dashicons dashicons-move'></i> <i class='remove dashicons dashicons-dismiss'></i>
																					</label>";
																}
															} else {
																$h .= "<label><input type='text' name='rt_cons[]' value=''><i class='dashicons dashicons-move'></i> <i class='remove dashicons dashicons-dismiss'></i></label>";
															}
																$h .= '</div>';
																$h .= "<a href='#' data-type='edit'><i class='dashicons dashicons-insert'></i> " . esc_html__( 'Add Cons', 'review-schema' ) . '</a>';
																// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $h is built with esc_attr/esc_html__/esc_url on dynamic parts.
																echo $h;
															?>
																													</td>
													</tr> 
													<?php
														$image_review = ( isset( $p_meta['image_review'] ) && $p_meta['image_review'][0] == '1' );
													if ( $image_review ) {
														?>
													<tr>
														<td class="first"><label for="rt_image"><?php esc_html_e( 'Image', 'review-schema' ); ?></label></td>
														<td>
														<?php
														$h         = null;
														$id        = '';
														$alignment = '';
														$name      = 'rt_attachment[imgs]';

														$get_attachment = get_comment_meta( $comment->comment_ID, 'rt_attachment', true );

														$value = ( isset( $get_attachment['imgs'] ) && ! empty( $get_attachment['imgs'] ) ? $get_attachment['imgs'] : [] );

														$h .= sprintf( "<div class='rtrs-gallery %s' id='%s'>", esc_attr( $alignment ), esc_attr( $id ) );

														$h .= "<div class='rtrs-form-group'>
																<div class='rtrs-preview-imgs'>";

														if ( $value ) {
															foreach ( $value as $value ) {
																$img_url = '';
																$img_src = wp_get_attachment_url( $value );
																if ( $img_src ) {
																	$img_url = $img_src;
																}

																$h .= "<div class='rtrs-preview-img'><img src='" . esc_url( $img_url ) . "' /><input type='hidden' name='" . esc_attr( $name ) . "[]' value='" . esc_attr( $value ) . "'><button class='rtrs-file-remove' data-id='" . esc_attr( $value ) . "'>x</button></div>";
															}
														} else {
															$h .= "<div class='rtrs-preview-img'><input type='hidden' name='" . esc_attr( $name ) . "' value='0'></div>";
														}

														$h .= sprintf(
															"</div>
																			<button data-name='%s' data-field='gallery' type='button' class='rtrs-upload-box'>
																				<i class='rtrs-picture'></i>
																				<span>%s</span>
																			</button>
																		</div>",
															$name,
															esc_html__( 'Upload Image', 'review-schema' )
														);
														$h .= '</div>';

														// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $h is built with esc_attr/esc_html__/esc_url on dynamic parts.
														echo $h;
														?>
																													</td>
													</tr>
													<?php } //end image_review ?>
													<?php do_action( 'rtrs_after_review_edit_form', $p_meta, $comment ); ?>
												</tbody>
											</table> 
										</div>
									</div>
								</div>
							</div>

							<div id="postbox-container-3" class="postbox-container"> 
								<?php
									/** This action is documented in wp-admin/includes/meta-boxes.php */
									do_action( 'add_meta_boxes', 'comment', $comment );

									/**
									 * Fires when comment-specific meta boxes are added.
									 *
									 * @since 3.0.0
									 *
									 * @param WP_Comment $comment Comment object.
									 */
									// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "add_meta_boxes_comment" is a WP core action used by the comment edit screen.
									do_action( 'add_meta_boxes_comment', $comment );

									do_meta_boxes( null, 'normal', $comment );

									// $referer = wp_get_referer();
									$request_url = remove_query_arg( '_wp_http_referer' );
								?>
							</div>
							<input type="hidden" name="c" value="<?php echo esc_attr( $comment->comment_ID ); ?>" />
							<input type="hidden" name="p" value="<?php echo esc_attr( $comment->comment_post_ID ); ?>" />
							 <input name="referredby" type="hidden" id="referredby" value="<?php echo esc_url( $request_url ); ?>" />
							<?php // wp_original_referer_field( true, 'previous' ); ?>
							<?php // wp_nonce_field( 'rtrs-review-edit' ); ?>
							<?php wp_nonce_field( rtrs()->getNonceId(), rtrs()->getNonceId() ); ?>
							<input type="hidden" name="noredir" value="1" />
						</div>
						<!-- /post-body -->
					</div>
				</div>
			</form>
			<?php if ( ! wp_is_mobile() ) : ?>
			<script type="text/javascript">
				try{document.post.name.focus();}catch(e){}
			</script>
				<?php
			endif;
		} else {
			?>
					<div class="wrap">
				<h2><?php esc_html_e( 'All Reviews', 'review-schema' ); ?></h2> 
				<?php
					wp_enqueue_script( 'admin-comments' );
					enqueue_comment_hotkeys_js();
				?>
				<?php $this->reviews_obj->call_analytics_header(); ?>
				<?php $this->reviews_obj->review_action_status(); ?>

				<form method="post"> 
					<?php
						$this->reviews_obj->prepare_items();
						$this->reviews_obj->search_box( esc_html__( 'Search Review', 'review-schema' ), 'search_id' );
						$this->reviews_obj->views();
						$this->reviews_obj->display();
					?>
				</form>
			</div>
			<?php
		}
	}

	/**
	 * Screen options
	 */
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => esc_html__( 'Reviews', 'review-schema' ),
			'default' => 5,
			'option'  => 'reviews_per_page',
		];

		add_screen_option( $option, $args );

		$this->reviews_obj = new ReviewTable();
	}
}
