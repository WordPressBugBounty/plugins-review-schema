<?php

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Settings
 */

$rtrs_options = [
	'title_section'            => [
		'type'        => 'title',
		'title'       => esc_html__( 'E-Commerce', 'review-schema' ),
		'description' => '<strong>Note: </strong> This information is need for WooCommerce/EDD/Classified Listing product to Google schema (Structured Data)',
	],
	'brand_name'               => [
		'title' => esc_html__( 'Brand name', 'review-schema' ),
		'type'  => 'text',
		'class' => 'regular-text',
	],
	'identifier_type'          => [
		'type'        => 'select',
		'title'       => esc_html__( 'Identifier Type', 'review-schema' ),
		'required'    => true,
		'default'     => '',
		'options'     => [
			'mpn'    => esc_html__( 'MPN', 'review-schema' ),
			'isbn'   => esc_html__( 'ISBN', 'review-schema' ),
			'gtin8'  => esc_html__( 'GTIN-8 (UPC, JAN)', 'review-schema' ),
			'gtin12' => esc_html__( 'GTIN-12 (UPC)', 'review-schema' ),
			'gtin13' => esc_html__( 'GTIN-13 (EAN,JAN)', 'review-schema' ),
		],
		'description' => '<strong>MPN</strong><br>  &#8594; MPN(Manufacturer Part Number) Used globally, Alphanumeric digits (various lengths)<br>
                    <strong>GTIN</strong><br> &#8594; UPC(Universal Product Code) Used in primarily North America. 12 numeric digits. eg. 892685001003.<br>
                    &#8594; EAN(European Article Number) Used primarily outside of North America. Typically 13 numeric digits (can occasionally be either eight or 14 numeric digits). eg. 4011200296908<br>
                    &#8594; ISBN(International Standard Book Number) Used globally, ISBN-13 (recommended), 13 numeric digits 978-0747595823<br>
                    &#8594; JAN(Japanese Article Number) Used only in Japan, 8 or 13 numeric digits.',

	],
	'identifier'               => [
		'title' => esc_html__( 'Identifier value', 'review-schema' ),
		'type'  => 'text',
		'class' => 'regular-text',
	],

	'return_policy_section'    => [
		'type'        => 'title',
		'title'       => esc_html__( 'Merchant Return Policy', 'review-schema' ),
		'description' => esc_html__( 'Configure hasMerchantReturnPolicy for product schema markup.', 'review-schema' ),
	],
	'return_policy_country'    => [
		'title'       => esc_html__( 'Applicable Country', 'review-schema' ),
		'type'        => 'text',
		'class'       => 'regular-text',
		'description' => esc_html__( 'ISO 3166-1 alpha-2 country code. e.g. US, GB, BD', 'review-schema' ),
	],
	'return_policy_category'   => [
		'title'   => esc_html__( 'Return Policy Type', 'review-schema' ),
		'type'    => 'select',
		'class'   => 'regular-text',
		'options' => [
			'MerchantReturnFiniteReturnWindow' => esc_html__( 'Finite Return Window', 'review-schema' ),
			'MerchantReturnNotPermitted'       => esc_html__( 'Return Not Permitted', 'review-schema' ),
			'MerchantReturnUnlimitedWindow'    => esc_html__( 'Unlimited Return Window', 'review-schema' ),
		],
	],
	'return_policy_days'       => [
		'title'             => esc_html__( 'Return Days', 'review-schema' ),
		'type'              => 'number',
		'class'             => 'regular-text',
		'description'       => esc_html__( 'Number of days for return (only for Finite Return Window).', 'review-schema' ),
		'custom_attributes' => [
			'min'  => '0',
			'step' => '1',
		],
	],
	'return_policy_method'     => [
		'title'   => esc_html__( 'Return Method', 'review-schema' ),
		'type'    => 'select',
		'class'   => 'regular-text',
		'options' => [
			'ReturnByMail'  => esc_html__( 'Return By Mail', 'review-schema' ),
			'ReturnInStore' => esc_html__( 'Return In Store', 'review-schema' ),
			'ReturnAtKiosk' => esc_html__( 'Return At Kiosk', 'review-schema' ),
		],
	],
	'return_policy_fees'       => [
		'title'   => esc_html__( 'Return Fees', 'review-schema' ),
		'type'    => 'select',
		'class'   => 'regular-text',
		'options' => [
			'FreeReturn'                       => esc_html__( 'Free Return', 'review-schema' ),
			'ReturnFeesCustomerResponsibility' => esc_html__( 'Customer Responsibility', 'review-schema' ),
			'ReturnShippingFees'               => esc_html__( 'Return Shipping Fees', 'review-schema' ),
		],
	],
	'return_shipping_fees_amount' => [
		'title'             => esc_html__( 'Return Shipping Fees Amount', 'review-schema' ),
		'type'              => 'number',
		'class'             => 'regular-text',
		'description'       => esc_html__( 'The shipping fees amount for returns. Required when Return Fees is not Free Return.', 'review-schema' ),
		'custom_attributes' => [
			'min'  => '0',
			'step' => '0.01',
		],
	],
	'return_shipping_fees_currency' => [
		'title'       => esc_html__( 'Return Shipping Fees Currency', 'review-schema' ),
		'type'        => 'text',
		'class'       => 'regular-text',
		'description' => esc_html__( 'ISO 4217 currency code (e.g. USD, EUR, GBP). Auto-detects from WooCommerce if empty.', 'review-schema' ),
	],
	'return_policy_link'       => [
		'title'       => esc_html__( 'Return Policy URL', 'review-schema' ),
		'type'        => 'url',
		'class'       => 'regular-text',
		'description' => esc_html__( 'Link to your return policy page.', 'review-schema' ),
	],

	'shipping_details_section' => [
		'type'        => 'title',
		'title'       => esc_html__( 'Shipping Details', 'review-schema' ),
		'description' => esc_html__( 'Shipping rate and destination are pulled from WooCommerce shipping zones. Configure delivery time below.', 'review-schema' ),
	],
	'handling_time_min'        => [
		'title'             => esc_html__( 'Handling Time Min (days)', 'review-schema' ),
		'type'              => 'number',
		'class'             => 'regular-text',
		'description'       => esc_html__( 'Minimum handling time in days.', 'review-schema' ),
		'custom_attributes' => [
			'min'  => '0',
			'step' => '1',
		],
	],
	'handling_time_max'        => [
		'title'             => esc_html__( 'Handling Time Max (days)', 'review-schema' ),
		'type'              => 'number',
		'class'             => 'regular-text',
		'description'       => esc_html__( 'Maximum handling time in days.', 'review-schema' ),
		'custom_attributes' => [
			'min'  => '0',
			'step' => '1',
		],
	],
	'transit_time_min'         => [
		'title'             => esc_html__( 'Transit Time Min (days)', 'review-schema' ),
		'type'              => 'number',
		'class'             => 'regular-text',
		'description'       => esc_html__( 'Minimum transit time in days.', 'review-schema' ),
		'custom_attributes' => [
			'min'  => '0',
			'step' => '1',
		],
	],
	'transit_time_max'         => [
		'title'             => esc_html__( 'Transit Time Max (days)', 'review-schema' ),
		'type'              => 'number',
		'class'             => 'regular-text',
		'description'       => esc_html__( 'Maximum transit time in days.', 'review-schema' ),
		'custom_attributes' => [
			'min'  => '0',
			'step' => '1',
		],
	],

];

return apply_filters( 'rtrs_woocommerce_settings_options', $rtrs_options );
