<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for Third Party Plugin
 */
$rtrs_options = [];
if ( class_exists( 'WooCommerce' ) ) {
	$rtrs_options['wc_schema'] = [
		'title'       => esc_html__( 'Disable WooCommerce default schema', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'This will disable WooCommerce plugin default schema.', 'review-schema' ),
	];
}

if ( class_exists( 'Easy_Digital_Downloads' ) ) {
	$rtrs_options['edd_schema'] = [
		'title'       => esc_html__( 'Disable Easy Digital Download default schema', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'This will disable Easy Digital Download plugin default schema.', 'review-schema' ),
	];
}

if ( class_exists( 'SureCart' ) ) {
	$rtrs_options['sure_cart_schema'] = [
		'title'       => esc_html__( 'Disable SureCart default schema', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'This will disable SureCart plugin default schema.', 'review-schema' ),
	];
}

if ( class_exists( 'RankMath' ) ) {
	$rtrs_options['rank_math_schema'] = [
		'title'       => esc_html__( 'Disable Rank Math default schema', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'This will disable Rank Math SEO plugin default schema.', 'review-schema' ),
	];
}

if ( defined( 'WPSEO_VERSION' ) ) {
	$rtrs_options['yoast_search_schema'] = [
		'title'       => esc_html__( 'Disable Yoast SEO sitelinks searchbox', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'This will disable sitelinks searchbox default by Yoast SEO plugin.', 'review-schema' ),
	];
	$rtrs_options['yoast_schema']        = [
		'title'       => esc_html__( 'Disable Yoast SEO Default Schema JSON-LD', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'This will disable all default schema default by Yoast SEO plugin.', 'review-schema' ),
	];
}
if ( ! empty( $rtrs_options ) ) {
	$rtrs_options = [
		'tpp_section' => [
			'title'       => esc_html__( 'Third party plugin conflict prevention', 'review-schema' ),
			'description' => esc_html__( 'Prevent duplicate schema issues — these toggles help avoid conflicts between multiple schema plugins.', 'review-schema' ),
			'type'        => 'title',
		],
	] + $rtrs_options;
}
return apply_filters( 'rtrs_schema_tpp_settings_options', $rtrs_options );
