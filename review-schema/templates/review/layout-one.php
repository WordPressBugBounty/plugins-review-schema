<?php
/**
 * Review layout one template
 *
 * @author      RadiusTheme
 * @package     review-schema/templates/review
 * @version     1.0.0
 *
 * @var use Rtrs\Helpers\Functions
 */

use Rtrs\Modules\Review\Helpers\ReviewFns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="<?php echo esc_attr( $comment_classes ); ?>">

	<?php if ( get_option( 'show_avatars' ) ) { ?>
	<div class="rtrs-review-imgholder">
		<?php
			$rtrs_avatar = '';
		if ( get_comment_meta( get_comment_ID(), 'rt_anonymous', true ) ) {
			$rtrs_avatar = RTRS_URL . '/assets/imgs/avatar.jpg';
		} else {
			$rtrs_avatar = get_avatar_url( $comment->comment_author_email, [ 'size' => '70' ] );
		}
		?>
		<img src="<?php echo esc_url( $rtrs_avatar ); ?>" alt="">	
	</div> 
	<?php } ?>

	<div class="rtrs-review-body"> 
		
		<ul class="rtrs-review-meta">  
			<?php if ( $rtrs_avg = get_comment_meta( get_comment_ID(), 'rating', true ) ) { ?>
				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::review_stars() returns plugin-controlled <i> star icon HTML. ?>
				<li class="rtrs-review-rating"><?php echo ReviewFns::review_stars( $rtrs_avg ); ?></li>
			<?php } ?>
			
			<?php rtrs()->get_partial_path( 'author', [ 'p_meta' => $p_meta ] ); ?> 

			<li class="rtrs-review-date"><i class="rtrs-calendar"></i>
			<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReviewFns::comment_review_time() returns plugin-formatted date HTML.
				echo ReviewFns::comment_review_time( $comment );
			?>
			<?php
			if ( $review_edit == 'yes' && get_current_user_id() && $comment->user_id == get_current_user_id() ) {
				?>
				<span class="rtrs-review-edit-btn" data-comment-post-id="<?php echo esc_attr( $comment->comment_post_ID ); ?>" data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
					<?php esc_html_e( '(Edit)', 'review-schema' ); ?>
				</span> 
			<?php } ?>

			</li>
			<?php rtrs()->get_partial_path( 'share', [ 'p_meta' => $p_meta ] ); ?> 
		</ul>

		<?php if ( $title = get_comment_meta( get_comment_ID(), 'rt_title', true ) ) { ?>
			<h4 class="rtrs-review-title"><?php echo esc_html( $title ); ?></h4>
		<?php } ?>

		<?php comment_text(); ?> 

		<?php if ( $comment->comment_approved == '0' ) : ?>
			<p><em class="comment-awaiting-moderation"><?php esc_html_e( 'Your comment is awaiting moderation.', 'review-schema' ); ?></em></p>
		<?php endif; ?>

		<?php rtrs()->get_partial_path( 'attachment', [ 'p_meta' => $p_meta ] ); ?>

		<?php rtrs()->get_partial_path( 'pros-cons', [ 'p_meta' => $p_meta ] ); ?> 

		<?php
		ob_start();
		rtrs()->get_partial_path( 'highlight', [ 'p_meta' => $p_meta ] );
		rtrs()->get_partial_path( 'helpful', [ 'p_meta' => $p_meta ] );
		$rtrs_action_area = ob_get_clean();
		if ( trim( $rtrs_action_area ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rtrs_action_area is HTML captured from plugin partials (highlight/helpful) that escape their own dynamic values.
			echo '<div class="rtrs-action-area">' . $rtrs_action_area . '</div>';
		}
		?>  

		<?php
			rtrs()->get_partial_path(
				'reply',
				[
					'args'      => $args,
					'add_below' => $add_below,
					'depth'     => $depth,
				]
			);
		?>

	</div>
</div>   
