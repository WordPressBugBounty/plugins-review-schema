<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for archive Info
 */
$rtrs_new  = [
	'text_section' => [
		'title' => esc_html__( 'Blog Post Archive', 'review-schema' ),
		'type'  => 'title',
	],
	'archive'      => [
		'title'       => esc_html__( 'Archive?', 'review-schema' ),
		'description' => esc_html__( 'This archive page is for blog post', 'review-schema' ),
		'type'        => 'checkbox',
		'label'       => esc_html__( 'Enable', 'review-schema' ),
	],
	'schema_type'  => [
		'title'       => esc_html__( 'Schema Type', 'review-schema' ),
		'description' => esc_html__( 'Choose the default schema type that will be applied to your blog archive and post listing pages.', 'review-schema' ),
		'type'        => 'select',
		'class'       => 'regular-text',
		'options'     => [
			'article'      => esc_html__( 'Article', 'review-schema' ),
			'news_article' => esc_html__( 'News Article', 'review-schema' ),
			'blog_posting' => esc_html__( 'Blog Posting', 'review-schema' ),
		],
		'empty'       => esc_html__( 'Select One', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_schema_archive_settings.archive',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
];
$rtrs_ecom = [];
if ( function_exists( 'WC' ) || function_exists( 'EDD' ) ) {
	$rtrs_ecom['product_archive'] = [
		'title'       => esc_html__( 'Product Archive?', 'review-schema' ),
		'description' => esc_html__( 'This archive page is for WooCommerce and EDD product', 'review-schema' ),
		'type'        => 'checkbox',
		'label'       => esc_html__( 'Enable', 'review-schema' ),
	];
}

if ( is_plugin_active( 'classified-listing/classified-listing.php' ) ) {
	$rtrs_ecom['cl_archive'] = [
		'title'       => esc_html__( 'Classified Listing Archive?', 'review-schema' ),
		'description' => esc_html__( 'This archive page is for Classified Listing', 'review-schema' ),
		'type'        => 'checkbox',
		'label'       => esc_html__( 'Enable', 'review-schema' ),
	];
}
if ( ! empty( $rtrs_ecom ) ) {
	$rtrs_ecom = [
		'ecommerce_archive_section' => [
			'title' => esc_html__( 'E-Commerce Archive', 'review-schema' ),
			'type'  => 'title',
		],
	] + $rtrs_ecom;
}
$rtrs_options = $rtrs_new + $rtrs_ecom;
return apply_filters( 'rtrs_schema_archive_settings_options', $rtrs_options );
