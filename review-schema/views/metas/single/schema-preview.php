<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

$rtrs_favicon       = get_site_icon_url( 16 );
$rtrs_site_url      = wp_parse_url( home_url(), PHP_URL_HOST );
$rtrs_has_ai_schema = ! empty( get_post_meta( $post->ID, '_aise_schema_data', true ) );

$rtrs_has_custom_schema   = '';
$rtrs_apply_custom_schema = apply_filters( 'rtrs_custom_rich_snippet_enabled', ( wp_doing_ajax() || is_singular() ), $post->ID );
if ( $post->ID && function_exists( 'rtrsp' ) && $rtrs_apply_custom_schema ) {
	$rtrs_disable_generator = get_post_meta( $post->ID, '_rtrs_disable_snippet_generator', true );
	$rtrs_custom_snippet    = get_post_meta( $post->ID, '_rtrs_custom_rich_snippet', true );
	if ( $rtrs_custom_snippet && $rtrs_disable_generator ) {
		$rtrs_has_custom_schema = apply_filters( 'rtrs_custom_rich_snippet', '', $post->ID );
	}
}

?>

<div class="rtrs-schema-preview <?php echo esc_attr( ! function_exists( 'rtrsp' ) ? 'rtrs-schema-preview-pro' : '' ); ?>">
	<div class="rtrs-preview-message">
		<?php if ( $rtrs_has_ai_schema ) : ?>
			<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
			<p><?php esc_html_e( 'AI-generated schema is available for this post. Click this tab to preview and edit.', 'review-schema' ); ?></p>
		<?php else : ?>
			<?php if ( $rtrs_has_custom_schema ) { ?>
				<p><span class="dashicons dashicons-info"></span><?php esc_html_e( 'Manual JSON Snippet (Custom Schema) is Empty.', 'review-schema' ); ?></p>
			<?php } else { ?>
				<p><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'System-generated schema preview. Click this tab to view the structured data output.', 'review-schema' ); ?></p>
			<?php } ?>
		<?php endif; ?>
	</div>
</div>

