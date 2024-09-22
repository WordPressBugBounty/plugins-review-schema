<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools Settings
 */

ob_start();
?>
<h2 style="border-bottom: 1px solid #ddd;padding-bottom: 10px;margin-bottom: 15px;"><?php esc_html_e( 'Themes', 'review-schema' ); ?></h2>
<ul class="product_list">
	<li>
		<a target="_blank" href="https://www.radiustheme.com/downloads/revieweb-review-wordpress-theme/">
			<img src="https://www.radiustheme.com/wp-content/uploads/edd/2022/10/Revieweb-Review-WordPress-Theme.png" alt="Revieweb – Review WordPress Theme" style="max-width:100%;;width:100%;">
			<span><?php esc_html_e( 'BUY', 'review-schema' ); ?></span>
		</a>
	</li>
	<li>
		<a target="_blank" href="https://www.radiustheme.com/downloads/listygo-directory-listing-wordpress-theme/">
			<img src="https://www.radiustheme.com/wp-content/uploads/edd/2022/10/Listygo%E2%80%93Directory-Listing-WordPress-Theme.png" alt="Listygo – Directory & Listing WordPress Theme" style="max-width:100%;;width:100%;">
			<span><?php esc_html_e( 'BUY', 'review-schema' ); ?></span>
		</a>
	</li>
	<li>
		<a target="_blank" href="https://www.radiustheme.com/downloads/neeon-wordpress-news-magazine-theme/">
			<img src="https://www.radiustheme.com/wp-content/uploads/edd/2022/01/Neeon-WordPress-News-Magazine-Theme.png" alt="Neeon – WordPress News Magazine Theme" style="max-width:100%;;width:100%;">
			<span><?php esc_html_e( 'BUY', 'review-schema' ); ?></span>
		</a>
	</li>
</ul>

<?php
$content = ob_get_clean();

$options = [
	'pro_sections' => [
		'title'        => esc_html__( 'Our Products', 'review-schema' ),
		'type'         => 'html',
		// 'css_style'    => $css_style,
		'html_content' => $content,
	],
];

return apply_filters( 'rtrs_tools_settings_options', $options );
