<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rtrs\Helpers\Functions;

$rtrs_post_type = Functions::getPostTypes( false, false );

/**
 * Schema Settings.
 */
$rtrs_str = esc_html__( "Determine how your posts should be described by default in your site's Schema.org markup. You can always change the settings for individual posts in the 'Schema Settings' metabox.", 'review-schema' );

$rtrs_arr = [
	'a'      => [ 'href' => [] ],
	'strong' => [],
];

$rtrs_options = [
//	'general_section'         => [
//		'title' => esc_html__( 'General', 'review-schema' ),
//		'type'  => 'title',
//	],
//	'post_type'               => [
//		'title'       => esc_html__( 'Schema Support', 'review-schema' ),
//		'description' => wp_kses( $rtrs_str, $rtrs_arr ),
//		'type'        => 'auto_schema',
//		'options'     => $rtrs_post_type,
//	],
	'site_section'            => [
		'title' => esc_html__( 'Site Info', 'review-schema' ),
		'type'  => 'title',
	],
	'site_category'           => [
		'title'       => esc_html__( 'Site Represents Type', 'review-schema' ),
		'description' => esc_html__(
			'Choose whether your site represents an Organization, Local Business, or a Person.',
			'review-schema'
		) . '<a href="#" target="_blank"> ' . esc_html__( 'Learn more about the differences between Organization and Person..', 'review-schema' ) . ' </a>',
		'type'        => 'radio_button',
		'class'       => 'regular-text',
		'default'     => 'Organization',
		'options'     => [
			'Person'       => esc_html__( 'Person', 'review-schema' ),
			'Organization' => esc_html__( 'Organization', 'review-schema' ),
		],
	],

	'organization_category'   => [
		'title'       => esc_html__( 'Sub Category', 'review-schema' ),
		'type'        => 'schema_type',
		'class'       => 'regular-text rtrs-select2',
		'required'    => true,
		'options'     => Functions::getSiteSubTypesOrganization(),
        'default'     => 'Organization',
		'empty'       => esc_html__( 'Select One', 'review-schema' ),
		'description' => esc_html__( 'Use the most appropriate schema category for local business', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],

	'name'                    => [
		'type'     => 'text',
		'class'    => 'regular-text',
		'title'    => esc_html__( 'Name', 'review-schema' ),
		'required' => true,
	],
	'alternateName'           => [
		'type'  => 'text',
		'class' => 'regular-text',
		'title' => esc_html__( 'Alternate Name', 'review-schema' ),
	],
	'logo'                    => [
		'type'        => 'image',
		'required'    => true,
		'title'       => esc_html__( 'Business Logo', 'review-schema' ) . "<span class='rtrs-required'>*</span>",
		'description' => esc_html__( 'The image must be 112x112px, at minimum.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'image'                   => [
		'type'  => 'image',
		'title' => esc_html__( 'Image', 'review-schema' ),
	],
	'priceRange'              => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'required'    => true,
		'title'       => esc_html__( 'Price Range', 'review-schema' ) . "<span class='rtrs-required'>*</span>",
		'recommended' => true,
		'description' => esc_html__( 'The price range of the business, for example $$$.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'telephone'               => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'required'    => true,
		'title'       => esc_html__( 'Telephone', 'review-schema' ) . "<span class='rtrs-required'>*</span>",
		'description' => esc_html__( 'Required For Organization And Local Business', 'review-schema' ),
	],
	/*
	 * 'sameAs'                  => [
		'type'        => 'textarea',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Same As', 'review-schema' ),
		'placeholder' => esc_html__( 'http://example1.com&#10;http://example2.com', 'review-schema' ),
		'description' => wp_kses( __( 'Add additional url per line which are same as this site', 'review-schema' ), [ 'br' => [] ] ),
	],
	*/
	'address_section'         => [
		'title' => esc_html__( 'Address', 'review-schema' ),
		'type'  => 'title',
	],
	'addresses'               => [
		'type'   => 'group',
		'is_pro' => true,
		'title'  => esc_html__( 'Address', 'review-schema' ),
		'fields' => [
			'streetAddress'   => [
				'type'  => 'text',
				'class' => 'regular-text',
				'title' => esc_html__( 'Street Address', 'review-schema' ),
			],
			'addressLocality' => [
				'type'        => 'text',
				'class'       => 'regular-text',
				'title'       => esc_html__( 'Address Locality', 'review-schema' ),
				'description' => esc_html__( 'City (i.e Melbourne)', 'review-schema' ),
			],
			'addressRegion'   => [
				'type'        => 'text',
				'class'       => 'regular-text',
				'title'       => esc_html__( 'Address Region', 'review-schema' ),
				'description' => esc_html__( 'State (i.e. Victoria)', 'review-schema' ),
			],
			'postalCode'      => [
				'type'  => 'text',
				'class' => 'regular-text',
				'title' => esc_html__( 'Postal Code', 'review-schema' ),
			],
			'addressCountry'  => [
				'title'   => esc_html__( 'Country', 'review-schema' ),
				'type'    => 'select',
				'class'   => 'regular-text ',
				'options' => Functions::getCountryList(),
				'empty'   => esc_html__( 'Select One', 'review-schema' ),
			],
		],
	],
	'geo_coordinates_section' => [
		'title'   => esc_html__( 'Geo Coordinates', 'review-schema' ),
		'type'    => 'title',
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'latitude'                => [
		'title'   => esc_html__( 'Latitude', 'review-schema' ),
		'type'    => 'text',
		'class'   => 'regular-text',
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'longitude'               => [
		'title'   => esc_html__( 'Longitude', 'review-schema' ),
		'type'    => 'text',
		'class'   => 'regular-text',
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'radius'                  => [
		'title'   => esc_html__( 'Radius', 'review-schema' ),
		'type'    => 'number',
		'class'   => 'regular-text',
		'min'     => 0,
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'site_others_section'     => [
		'title' => esc_html__( 'Site Others Info', 'review-schema' ),
		'type'  => 'title',
	],
	'description'             => [
		'type'  => 'textarea',
		'class' => 'regular-text',
		'title' => esc_html__( 'Description', 'review-schema' ),
	],
	'openingHours'            => [
		'type'        => 'opening_hours',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Opening Hours', 'review-schema' ),
		'placeholder' => esc_html__( 'Monday 11:00-14:30&#10;Tuesday 17:00-21:30', 'review-schema' ),
		'description' => wp_kses( __( '- Days are specified with the day name. Like: Monday, Tuesday</br> - Times are specified using 24:00 time. For example, 3PM is specified as 15:00. <br> - Add Opening Hours by separate line. Like: Monday 10:00-18:00', 'review-schema' ), [ 'br' => [] ] ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'contact_point_support'   => [
		'title'   => esc_html__( 'Help Center', 'review-schema' ),
		'type'    => 'title',
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
	],
	'contactPoint'            => [
		'type'    => 'group',
		'is_pro'  => true,
		'title'   => esc_html__( 'Contact Point ( Contact Support )', 'review-schema' ),
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '!=',
				],
			],
		],
		'fields'  => [
			'telephone'   => [
				'type'  => 'text',
				'title' => esc_html__( 'Telephone', 'review-schema' ),
			],
			'contactType' => [
				'type'  => 'text',
				'title' => esc_html__( 'Contact Type', 'review-schema' ),
			],
			'language'    => [
				'name'        => 'language',
				'type'        => 'text',
				'title'       => esc_html__( 'Available Language', 'review-schema' ),
				'description' => esc_html__( 'Comma Seperated. Example: en-US, bn-BD', 'review-schema' ),
			],
			'areaServed'  => [
				'title'   => esc_html__( 'Area Served (Country)', 'review-schema' ),
				'type'    => 'select',
				'options' => Functions::getCountryList(),
				'empty'   => esc_html__( 'Select One', 'review-schema' ),
			],
		],
	],
	'servesCuisine'           => [
		'type'    => 'textarea',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Serves Cuisine', 'review-schema' ),
		'depends' => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Organization',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_schema_settings.organization_category',
					'value'     => [ 'FoodEstablishment', 'Bakery', 'BarOrPub', 'Brewery', 'CafeOrCoffeeShop', 'Distillery', 'FastFoodRestaurant', 'IceCreamShop', 'Restaurant', 'Winery' ],
					'condition' => 'includes',
				],
			],
		],
	],
	'menu'                    => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Restaurant Menu URL', 'review-schema' ),
		'depends' => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Organization',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_schema_settings.organization_category',
					'value'     => 'Restaurant',
					'condition' => '=',
				],
			],
		],
	],
	'acceptsReservations'     => [
		'title'   => esc_html__( 'Accepts Reservations', 'review-schema' ),
		'type'    => 'checkbox',
		'label'   => esc_html__( 'Accept', 'review-schema' ),
		'depends' => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Organization',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_schema_settings.organization_category',
					'value'     => 'Restaurant',
					'condition' => '=',
				],
			],
		],
	],
	'page_section_support'    => [
		'title' => esc_html__( 'Page', 'review-schema' ),
		'type'  => 'title',
	],
	'about_page'              => [
		'title'       => esc_html__( 'About Page', 'review-schema' ),
		'description' => esc_html__( 'Select a page on your site where you want to show the LocalBusiness meta data.', 'review-schema' ),
		'type'        => 'select',
		'options'     => Functions::allPages(),
		'empty'       => esc_html__( 'Select About Page', 'review-schema' ),
	],
	'contact_page'            => [
		'title'       => esc_html__( 'Contact Page', 'review-schema' ),
		'description' => esc_html__( 'Select a page on your site where you want to show the LocalBusiness meta data.', 'review-schema' ),
		'type'        => 'select',
		'options'     => Functions::allPages(),
		'empty'       => esc_html__( 'Select Contact Page', 'review-schema' ),
	],
];

return apply_filters( 'rtrs_schema_settings_options', $rtrs_options );
