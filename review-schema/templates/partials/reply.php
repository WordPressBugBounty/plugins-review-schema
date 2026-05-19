<?php
/**
 * Review reply template
 *
 * @author      RadiusTheme
 * @package     review-schema/templates
 * @version     1.0.0
 *
 * @var use Rtrs\Helpers\Functions
 */
use Rtrs\Modules\Review\Helpers\ReviewFns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! ReviewFns::has_reply_permition() ) {
	return;
}
// admin reply not allowed for classifed listing
if ( get_post_type( get_the_ID() ) == 'rtcl_listing' ) {
	return;
}

$rtrs_comment_reply_link = get_comment_reply_link(
	array_merge(
		$args,
		[
			'add_below' => $add_below,
			'depth'     => $depth,
			// 'reply_to_text' => '',
			'max_depth' => $args['max_depth'] ?? get_option( 'thread_comments_depth', 5 ),
		]
	)
);
if ( empty( $rtrs_comment_reply_link ) ) {
	return;
}
?>
<div class="rtrs-reply-btn comment-reply">
	<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rtrs_comment_reply_link is WP-core-produced HTML from get_comment_reply_link(); we only swap the CSS class via preg_replace.
		echo preg_replace(
			'/comment-reply-link/',
			apply_filters( 'rtrs_review_comment_reply_link', 'comment-reply-link rtrs-item-btn' ),
			$rtrs_comment_reply_link,
			1
		);
		?>
</div>
