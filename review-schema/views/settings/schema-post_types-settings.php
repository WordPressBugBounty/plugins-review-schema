<?php
/**
 * Post Types Schema Settings.
 *
 * @package ReviewSchema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rtrs\Helpers\Functions;

$rtrs_post_types     = Functions::getPostTypes( false, false );
$rtrs_auto_cat_types = Functions::rich_snippet_auto_cats();

$rtrs_posts_tabs = [];

if ( ! empty( $rtrs_post_types ) ) {
	foreach ( $rtrs_post_types as $rtrs_key => $rtrs_value ) {
		$rtrs_posts_tabs[ $rtrs_key ] = [
			'label'  => $rtrs_value,
			'fields' => [
				$rtrs_key . '_schema_type'         => [
					'title'       => esc_html__( 'Schema Type', 'review-schema' ),
					'description' => esc_html__( 'Choose the default schema type that will be applied to your blog archive and post listing pages.', 'review-schema' ),
					'type'        => 'select',
					'class'       => 'regular-text',
					'options'     => $rtrs_auto_cat_types,
					'empty'       => esc_html__( 'Select One', 'review-schema' ),
				],
				$rtrs_key . '_event_notice'        => [
					'type'         => 'html',
					'html_content' => '<div class="rtrs-field-notice" style="background:#fef7e0;border-left:3px solid #e37400;padding:8px 12px;border-radius:4px;font-size:13px;color:#6b5900;">'
						. esc_html__( 'Event schema requires', 'review-schema' )
						. ' <a href="https://wordpress.org/plugins/the-events-calendar/" target="_blank" rel="noopener noreferrer">'
						. esc_html__( 'The Events Calendar', 'review-schema' )
						. '</a> ' . esc_html__( 'plugin to be installed and active.', 'review-schema' )
						. '</div>',
					'depends'      => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => 'event',
								'condition' => '=',
							],
						],
					],
				],
				$rtrs_key . '_product_notice'      => [
					'type'         => 'html',
					'html_content' => '<div class="rtrs-field-notice" style="background:#e8f0fe;border-left:3px solid #1a73e8;padding:8px 12px;border-radius:4px;font-size:13px;color:#174ea6;">'
						. esc_html__( 'Product auto-schema is supported for the following plugins:', 'review-schema' )
						. '<ul style="margin:6px 0 0 16px;list-style:disc;color:#3c4043;">'
						. '<li>WooCommerce</li>'
						. '<li>Easy Digital Downloads (EDD)</li>'
						. '<li>FluentCart</li>'
						. '<li>SureCart</li>'
						. '<li>Download Manager</li>'
						. '<li>Classified Listing (RTCL)</li>'
						. '</ul>'
						. esc_html__( 'Note: Auto schema is applied for supported plugins, but you can override schema settings for any post individually.', 'review-schema' )
						. '</div>',
					'depends'      => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => 'product',
								'condition' => '=',
							],
						],
					],
				],
				$rtrs_key . '_auto_generate'       => [
					'title'       => esc_html__( 'Auto-Generate', 'review-schema' ),
					'label'       => esc_html__( 'Enable', 'review-schema' ),
					'type'        => 'checkbox',
					'default'     => '',
					'description' => esc_html__( 'Automatically generate schema with AI based on site info & schema type settings.', 'review-schema' ),
					'depends'     => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => '',
								'condition' => '!=',
							],
						],
					],
				],
				$rtrs_key . '_job_salary'          => [
					'type'        => 'text',
					'class'       => 'regular-text',
					'title'       => esc_html__( 'Base Salary', 'review-schema' ),
					'description' => esc_html__( 'E.g., 50000', 'review-schema' ),
					'depends'     => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => 'job_posting',
								'condition' => '=',
							],
						],
					],
				],
				$rtrs_key . '_job_salary_currency' => [
					'type'    => 'select',
					'title'   => esc_html__( 'Salary Currency', 'review-schema' ),
					'default' => 'USD',
					'options' => [
						'USD' => 'USD',
						'EUR' => 'EUR',
						'GBP' => 'GBP',
						'BDT' => 'BDT',
						'INR' => 'INR',
						'AUD' => 'AUD',
						'CAD' => 'CAD',
						'JPY' => 'JPY',
						'CNY' => 'CNY',
					],
					'depends' => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => 'job_posting',
								'condition' => '=',
							],
						],
					],
				],
				$rtrs_key . '_job_salary_unit'     => [
					'type'    => 'select',
					'title'   => esc_html__( 'Salary Unit', 'review-schema' ),
					'default' => 'YEAR',
					'options' => [
						'YEAR'  => esc_html__( 'Per Year', 'review-schema' ),
						'MONTH' => esc_html__( 'Per Month', 'review-schema' ),
						'WEEK'  => esc_html__( 'Per Week', 'review-schema' ),
						'DAY'   => esc_html__( 'Per Day', 'review-schema' ),
						'HOUR'  => esc_html__( 'Per Hour', 'review-schema' ),
					],
					'depends' => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => 'job_posting',
								'condition' => '=',
							],
						],
					],
				],
				$rtrs_key . '_servesCuisine'       => [
					'type'    => 'textarea',
					'class'   => 'regular-text',
					'title'   => esc_html__( 'Serves Cuisine', 'review-schema' ),
					'depends' => [
						'on' => [
							[
								'field'     => 'rtrs_schema_post_types_settings.' . $rtrs_key . '_schema_type',
								'value'     => [ 'Restaurant' ],
								'condition' => 'includes',
							],
						],
					],
				],
			],
		];
	}
}

$rtrs_options = [
	'post_types_section' => [
		'title'       => esc_html__( 'Post Types Schema', 'review-schema' ),
		'description' => esc_html__( 'Configure the default schema type for each post type. When auto-generate is enabled, schema markup will be created automatically based on your Site Info settings.', 'review-schema' ),
		'type'        => 'title',
	],
	'post_type_tabs'     => [
		'type' => 'post_type_tabs',
		'tabs' => $rtrs_posts_tabs,
	],
];

return apply_filters( 'rtrs_schema_post_types_settings_options', $rtrs_options );
