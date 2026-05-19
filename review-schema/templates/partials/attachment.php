<?php
/**
 * Review helpful template
 *
 * @author      RadiusTheme
 * @package     review-schema/templates
 * @version     1.0.0
 *
 * @var use Rtrs\Helpers\Functions
 */
use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php
if ( $rtrs_get_attachment = get_comment_meta( get_comment_ID(), 'rt_attachment', true ) ) {
	?>
<div class="rtrs-review-item-media">
	<?php
	if ( isset( $p_meta['image_review'] ) && $p_meta['image_review'][0] == '1' ) {
		if ( isset( $rtrs_get_attachment['imgs'] ) ) {
			$rtrs_is_external_image = ( isset( $rtrs_get_attachment['image_source'] ) && $rtrs_get_attachment['image_source'] == 'external' );
			foreach ( $rtrs_get_attachment['imgs'] as $rtrs_img ) {
				?>
				<div class="rtrs-media-item rtrs-media-image">
					<?php if ( $rtrs_is_external_image ) { ?>
						<a class="rtrs-attachment-img" data-featherlight="image" href="<?php echo esc_url( $rtrs_img ); ?>"><img src="<?php echo esc_url( $rtrs_img ); ?>" style="width:70px;height:70px;object-fit:cover;" alt="<?php esc_attr_e( 'Review Image', 'review-schema' ); ?>"></a>
					<?php } else { ?>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() returns WP-core-built <img> HTML; href is wrapped in esc_url(). ?>
						<a class="rtrs-attachment-img" data-featherlight="image" href="<?php echo esc_url( wp_get_attachment_image_url( $rtrs_img, '' ) ); ?>"><?php echo wp_get_attachment_image( $rtrs_img, [ '70', '70' ], 'thumbnail' ); ?></a>
					<?php } ?>
				</div>
				<?php
			}
		}
	}
	?>

	<?php
	if ( isset( $p_meta['video_review'] ) && $p_meta['video_review'][0] == '1' ) {
		if ( isset( $rtrs_get_attachment['videos'] ) ) {
			foreach ( $rtrs_get_attachment['videos'] as $rtrs_video ) {
				?>
							<div class="rtrs-media-item rtrs-media-video">
					<?php
						$rtrs_self_video       = ( isset( $rtrs_get_attachment['video_source'] ) && $rtrs_get_attachment['video_source'] == 'self' );
						$rtrs_youtube_video_id = '';
					if ( ! $rtrs_self_video ) {
						$rtrs_pattern = '#^(?:https?://)?(?:www\.)?(?:youtu\.be/|youtube\.com(?:/embed/|/v/|/watch\?v=|/watch\?.+&v=))([\w-]{11})(?:.+)?$#x';
						preg_match( $rtrs_pattern, $rtrs_video, $rtrs_matches );
						$rtrs_youtube_video_id = ( isset( $rtrs_matches[1] ) ) ? $rtrs_matches[1] : '';
					}

						$rtrs_image_url = $rtrs_self_video ? Functions::get_default_placeholder_url() : 'https://img.youtube.com/vi/' . $rtrs_youtube_video_id . '/default.jpg';

						$rtrs_video_url = $rtrs_self_video ? wp_get_attachment_url( $rtrs_video ) : 'https://www.youtube.com/embed/' . $rtrs_youtube_video_id;
					?>
					<img src="<?php echo esc_url( $rtrs_image_url ); ?>" style="width: 80px;" alt="<?php esc_attr_e( 'SchemaEngine AI', 'review-schema' ); ?>">
					<?php if ( ! $rtrs_self_video ) { ?>
						<a href="<?php echo esc_url( $rtrs_video_url ); ?>?rel=0&amp;autoplay=1" data-featherlight="iframe" data-featherlight-iframe-width="640" data-featherlight-iframe-height="480" data-featherlight-iframe-frameborder="0" data-featherlight-iframe-allow="autoplay; encrypted-media" data-featherlight-iframe-allowfullscreen="true" class="rtrs-video-icon"><i class="rtrs-play"></i></a>
					<?php } else { ?>
						<a href="#" data-video-url="<?php echo esc_url( $rtrs_video_url ); ?>" class="rtrs-video-icon rtrs-play-self-video"><i class="rtrs-play"></i></a>
					<?php } ?>
				</div>
				<?php
			}
		}
	}
	?>
	</div>
<?php } ?>