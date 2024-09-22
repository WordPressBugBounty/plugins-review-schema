<?php

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Settings
 */

$options = [
	'title_section'           => [
		'type'        => 'title',
		'description' => '<strong>Note: </strong> This information is need for WooCommerce/EDD/Classified Listing product to Google schema (Structured Data)',
	],
	'brand_name'              => [
		'title' => esc_html__( 'Brand name', 'review-schema' ),
		'type'  => 'text',
		'class' => 'regular-text',
	],
	'identifier_type'         => [
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
	'identifier'              => [
		'title' => esc_html__( 'Identifier value', 'review-schema' ),
		'type'  => 'text',
		'class' => 'regular-text',
	],

];

return apply_filters( 'rtrs_woocommerce_settings_options', $options );
