<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for migration
 */
$rtrs_options = [
	'migration_info' => [
		'title'       => esc_html__( 'Migration', 'review-schema' ),
		'description' => esc_html__( 'Import data from others schema plugin', 'review-schema' ),
		'type'        => 'title',
	],
	'wp_seo_schema'  => [
		'title'       => esc_html__( 'WP SEO Schema', 'review-schema' ),
		'description' => esc_html__( 'Import data from WP SEO Schema plugin', 'review-schema' ),
		'type'        => 'migration',
		'data_id'     => 'wp_seo_schema',
	],
	// 'schema'         => [
	// 'title'   => esc_html__( 'Schema Plugin', 'review-schema' ),
	// 'type'    => 'migration',
	// 'data_id' => 'schema',
	// ],
];

return apply_filters( 'rtrs_migration_settings_options', $rtrs_options );
