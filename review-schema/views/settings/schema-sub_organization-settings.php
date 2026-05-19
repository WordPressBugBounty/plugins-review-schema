<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for sub_organization Info
 */
$rtrs_options = [
	'sub_organization_section' => [
		'title'       => esc_html__( 'Sub Organization', 'review-schema' ),
		'description' => esc_html__( 'Provide your sub organization information to a Google Knowledge panel', 'review-schema' ),
		'type'        => 'title',
	],
	'sub_organization'         => [
		'type'   => 'group',
		'title'  => esc_html__( 'Sub Organization', 'review-schema' ),
		'fields' => [
			'name' => [
				'type'  => 'text',
				'class' => 'regular-text',
				'title' => esc_html__( 'Name', 'review-schema' ),
			],
			'url'  => [
				'type'  => 'text',
				'class' => 'regular-text',
				'title' => esc_html__( 'URL', 'review-schema' ),
			],
		],
	],
];

return apply_filters( 'rtrs_schema_sub_organization_settings_options', $rtrs_options );
