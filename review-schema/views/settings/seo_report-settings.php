<?php
/**
 * SEO Report Settings
 *
 * Configuration for the SEO Report tools. The XML Sitemap URL is used as the
 * source of internal-link targets: when set, the internal-link generator pulls
 * candidate URLs from the sitemap instead of scanning the database. Leave empty
 * to use the built-in content scan.
 *
 * @package ReviewSchema
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rtrs\Helpers\ContentIgnore;

$rtrs_options = [
	'text_section'      => [
		'title' => esc_html__( 'SEO Report', 'review-schema' ),
		'type'  => 'title',
	],
	'xml_sitemap_url'   => [
		'title'       => esc_html__( 'XML Sitemap URL', 'review-schema' ),
		'type'        => 'text',
		'default'     => '',
		/* translators: %s: example sitemap URL. */
		'description' => sprintf(
			esc_html__( 'Enter your site\'s XML sitemap URL (e.g. %s). Internal-link suggestions will use the URLs from this sitemap as link targets. Sitemap indexes are supported. Leave empty to use the built-in content scan.', 'review-schema' ),
			'<code>' . esc_html( home_url( '/sitemap.xml' ) ) . '</code>'
		),
	],
	'ignore_section'    => [
		'title' => esc_html__( 'Ignore Content', 'review-schema' ),
		'type'  => 'title',
	],
	'ignore_post_types' => [
		'title'       => esc_html__( 'Ignore Content Types', 'review-schema' ),
		'type'        => 'multi_checkbox',
		'default'     => [],
		'options'     => ContentIgnore::selectable_post_types(),
		'description' => esc_html__( 'Select the content types that should be excluded from SEO analysis and internal-link recommendations. Ignored content will not appear in SEO reports and will not be considered as a target when generating internal-link suggestions. This is useful for excluding internal, utility, system, or other content that is not intended to be optimized for search engines or used as an internal-link destination. Note: content types that are not public are already excluded automatically.', 'review-schema' ),
	],
];

return apply_filters( 'rtrs_seo_report_settings_options', $rtrs_options );
