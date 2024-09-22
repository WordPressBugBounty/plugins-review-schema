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

use Rtrs\Helpers\Functions;

if ( ! Functions::has_reply_permition() ) {
	return;
}
// admin reply not allowed for classifed listing
if ( get_post_type( get_the_ID() ) == 'rtcl_listing' ) {
	return;
}

$comment_reply_link = get_comment_reply_link(
	array_merge(
		$args,
		[
			'add_below' => $add_below,
			'depth'     => $depth,
			// 'reply_to_text' => '',
			'max_depth' => $args['max_depth'],
		]
	)
);
if( empty( $comment_reply_link )){
	return;
}
?>   
<div class="rtrs-reply-btn"> 
	<?php
		echo preg_replace(
			'/comment-reply-link/',
			'comment-reply-link rtrs-item-btn',
			$comment_reply_link,
			1
		);
		?>
</div> 
