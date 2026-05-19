<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General Settings
 *
 * Defines the general settings options for the Review Schema plugin.
 *
 * @package ReviewSchema
 * @since 1.0.0
 */

use Rtrs\Helpers\Functions;

$rtrs_post_types = Functions::getPostTypes();
array_shift( $rtrs_post_types );

$rtrs_options = [
	'text_section'      => [
		'title' => esc_html__( 'General Settings', 'review-schema' ),
		'type'  => 'title',
	],
	'schema_enabled'    => [
		'title'       => esc_html__( 'Enable Schema', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => 'yes',
		'description' => esc_html__( 'Enable structured data (JSON-LD) markup to help search engines understand your content better and display rich snippets in search results.', 'review-schema' ),
	],
	'review_enabled'    => [
		'title'       => esc_html__( 'Enable Review', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'Enable the review system to allow visitors to submit reviews and ratings on your site. This also enables WooCommerce reviews, media settings, and other review-related features.', 'review-schema' ),
	],
	'affiliate_enabled' => [
		'title'       => esc_html__( 'Enable Affiliates', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'Enable reviews for the affiliate.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_general_settings.review_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
];

return apply_filters( 'rtrs_general_settings_options', $rtrs_options );
