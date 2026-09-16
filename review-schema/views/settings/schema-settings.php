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

/**
 * Reusable `depends` rules.
 *
 * Every category-specific field below reuses one of these so a field is never
 * gated on a narrower type list than the schema builder outputs it for.
 */
$rtrs_not_person = [
	'on' => [
		[
			'field'     => 'rtrs_schema_settings.site_category',
			'value'     => 'Person',
			'condition' => '!=',
		],
	],
];

$rtrs_is_person = [
	'on' => [
		[
			'field'     => 'rtrs_schema_settings.site_category',
			'value'     => 'Person',
			'condition' => '=',
		],
	],
];

/**
 * Build an "Organization + one of these sub categories" rule.
 *
 * @param array $types Schema types the field applies to.
 *
 * @return array
 */
$rtrs_sub_category_is = function ( array $types ) {
	return [
		'relation' => 'and',
		'on'       => [
			[
				'field'     => 'rtrs_schema_settings.site_category',
				'value'     => 'Organization',
				'condition' => '=',
			],
			[
				'field'     => 'rtrs_schema_settings.organization_category',
				'value'     => $types,
				'condition' => 'includes',
			],
		],
	];
};

$rtrs_is_local_business = $rtrs_sub_category_is( Functions::getLocalBusinessTypeList() );
$rtrs_is_lodging        = $rtrs_sub_category_is( Functions::getLodgingBusinessTypes() );
$rtrs_is_food           = $rtrs_sub_category_is( Functions::getFoodEstablishmentTypes() );
$rtrs_is_news_media     = $rtrs_sub_category_is( [ 'NewsMediaOrganization' ] );

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
		) . '<a href="https://schemaengineai.com/docs/docs/site-info/#3-toc-title" target="_blank"> ' . esc_html__( 'Learn more about the differences between Organization and Person..', 'review-schema' ) . ' </a>',
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
		'description' => esc_html__( 'Use the most appropriate schema category for local business', 'review-schema' ) . ' <a href="' . esc_url( 'https://www.radiustheme.com/ticket-support/' ) . '" target="_blank">' . esc_html__( "Can't find your category? Contact support", 'review-schema' ) . '</a>',
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
	'legalName'               => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Legal Name', 'review-schema' ),
		'description' => esc_html__( 'The registered legal name, if it differs from the trading name.', 'review-schema' ),
		'depends'     => $rtrs_not_person,
	],
	'slogan'                  => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Slogan', 'review-schema' ),
		'description' => esc_html__( 'A short tagline for the business.', 'review-schema' ),
		'depends'     => $rtrs_not_person,
	],
	'jobTitle'                => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Job Title', 'review-schema' ),
		'description' => esc_html__( 'The job title of the person (i.e. Software Engineer).', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_schema_settings.site_category',
					'value'     => 'Person',
					'condition' => '=',
				],
			],
		],
	],
	'worksFor'                => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Works For', 'review-schema' ),
		'description' => esc_html__( 'The name of the organization the person works for.', 'review-schema' ),
		'depends'     => $rtrs_is_person,
	],
	'alumniOf'                => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Alumni Of', 'review-schema' ),
		'description' => esc_html__( 'A school or university the person attended.', 'review-schema' ),
		'depends'     => $rtrs_is_person,
	],
	'birthDate'               => [
		'type'    => 'date',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Birth Date', 'review-schema' ),
		'depends' => $rtrs_is_person,
	],
	'knowsAbout'              => [
		'type'        => 'textarea',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Knows About', 'review-schema' ),
		'placeholder' => esc_html__( 'WordPress&#10;Structured data', 'review-schema' ),
		'description' => esc_html__( 'Topics this person has expertise in — one per line. A strong E-E-A-T signal.', 'review-schema' ),
		'depends'     => $rtrs_is_person,
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
	'email'                   => [
		'type'        => 'email',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Email', 'review-schema' ),
		'description' => esc_html__( 'A public contact address. Part of the Google Organization rich result.', 'review-schema' ),
	],
	'faxNumber'               => [
		'type'  => 'text',
		'class' => 'regular-text',
		'title' => esc_html__( 'Fax Number', 'review-schema' ),
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
		'depends' => $rtrs_is_food,
	],
	'menu'                    => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Menu URL', 'review-schema' ),
		'depends' => $rtrs_is_food,
	],
	'acceptsReservations'     => [
		'title'   => esc_html__( 'Accepts Reservations', 'review-schema' ),
		'type'    => 'checkbox',
		'label'   => esc_html__( 'Accept', 'review-schema' ),
		'depends' => $rtrs_is_food,
	],
	'hasDriveThroughService'  => [
		'title'   => esc_html__( 'Has Drive-Through Service', 'review-schema' ),
		'type'    => 'checkbox',
		'label'   => esc_html__( 'Available', 'review-schema' ),
		'depends' => $rtrs_is_food,
	],
	'checkinTime'             => [
		'type'        => 'time',
		'step'        => 1,
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Check-in Time', 'review-schema' ),
		'description' => esc_html__( 'The earliest someone may check into a lodging establishment. Use 24:00 time, for example 3PM is 15:00:00.', 'review-schema' ),
		'depends'     => $rtrs_is_lodging,
	],
	'checkoutTime'            => [
		'type'        => 'time',
		'step'        => 1,
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Check-out Time', 'review-schema' ),
		'description' => esc_html__( 'The latest someone may check out of a lodging establishment. Use 24:00 time, for example 11AM is 11:00:00.', 'review-schema' ),
		'depends'     => $rtrs_is_lodging,
	],
	'starRating'              => [
		'type'        => 'number',
		'class'       => 'regular-text',
		'min'         => 1,
		'max'         => 5,
		'step'        => 0.5,
		'title'       => esc_html__( 'Star Rating', 'review-schema' ),
		'description' => esc_html__( 'Official rating of the property, 1 to 5. Part of Google Hotel structured data.', 'review-schema' ),
		'depends'     => $rtrs_is_lodging,
	],
	'numberOfRooms'           => [
		'type'    => 'number',
		'class'   => 'regular-text',
		'min'     => 0,
		'title'   => esc_html__( 'Number Of Rooms', 'review-schema' ),
		'depends' => $rtrs_is_lodging,
	],
	'petsAllowed'             => [
		'title'   => esc_html__( 'Pets Allowed', 'review-schema' ),
		'type'    => 'checkbox',
		'label'   => esc_html__( 'Allowed', 'review-schema' ),
		'depends' => $rtrs_is_lodging,
	],
	'amenityFeature'          => [
		'type'        => 'textarea',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Amenity Features', 'review-schema' ),
		'placeholder' => esc_html__( 'Free WiFi&#10;Swimming pool&#10;Airport shuttle', 'review-schema' ),
		'description' => esc_html__( 'One amenity per line. Each becomes a LocationFeatureSpecification.', 'review-schema' ),
		'depends'     => $rtrs_is_lodging,
	],
	'medicalSpecialty'        => [
		'type'        => 'multiselect',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Medical Specialty', 'review-schema' ),
		'options'     => Functions::getMedicalSpecialties(),
		'description' => esc_html__( 'The medical specialties offered, for example Emergency, Cardiovascular, Neurologic.', 'review-schema' ),
		'depends'     => $rtrs_sub_category_is( Functions::getMedicalTypes() ),
	],
	'business_details_section' => [
		'title'       => esc_html__( 'Business Details', 'review-schema' ),
		'description' => esc_html__( 'Entity details Google reads for the Organization knowledge panel.', 'review-schema' ),
		'type'        => 'title',
		'depends'     => $rtrs_not_person,
	],
	'foundingDate'            => [
		'type'    => 'date',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Founding Date', 'review-schema' ),
		'depends' => $rtrs_not_person,
	],
	'numberOfEmployees'       => [
		'type'        => 'number',
		'class'       => 'regular-text',
		'min'         => 0,
		'title'       => esc_html__( 'Number Of Employees', 'review-schema' ),
		'description' => esc_html__( 'Output as a QuantitativeValue.', 'review-schema' ),
		'depends'     => $rtrs_not_person,
	],
	'areaServed'              => [
		'type'        => 'textarea',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Area Served', 'review-schema' ),
		'placeholder' => esc_html__( 'California&#10;Nevada', 'review-schema' ),
		'description' => esc_html__( 'Regions the business serves — one per line.', 'review-schema' ),
		'depends'     => $rtrs_not_person,
	],
	'award'                   => [
		'type'        => 'textarea',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Awards', 'review-schema' ),
		'description' => esc_html__( 'One award per line.', 'review-schema' ),
	],
	'paymentAccepted'         => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Payment Accepted', 'review-schema' ),
		'description' => esc_html__( 'Comma separated. Example: Cash, Credit Card, Invoice.', 'review-schema' ),
		'depends'     => $rtrs_is_local_business,
	],
	'currenciesAccepted'      => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Currencies Accepted', 'review-schema' ),
		'description' => esc_html__( 'Comma separated 3-letter currency codes. Example: USD, EUR.', 'review-schema' ),
		'depends'     => $rtrs_is_local_business,
	],
	'hasMap'                  => [
		'type'        => 'url',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Map URL', 'review-schema' ),
		'description' => esc_html__( 'Link to the business on Google Maps.', 'review-schema' ),
		'depends'     => $rtrs_is_local_business,
	],
	'identifiers_section'     => [
		'title'       => esc_html__( 'Business Identifiers', 'review-schema' ),
		'description' => esc_html__( 'Registration numbers that disambiguate the entity. Leave blank if not applicable.', 'review-schema' ),
		'type'        => 'title',
		'depends'     => $rtrs_not_person,
	],
	'vatID'                   => [
		'type'    => 'text',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'VAT ID', 'review-schema' ),
		'depends' => $rtrs_not_person,
	],
	'taxID'                   => [
		'type'    => 'text',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Tax ID', 'review-schema' ),
		'depends' => $rtrs_not_person,
	],
	'duns'                    => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'DUNS', 'review-schema' ),
		'description' => esc_html__( 'Dun & Bradstreet DUNS number.', 'review-schema' ),
		'depends'     => $rtrs_not_person,
	],
	'leiCode'                 => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'LEI Code', 'review-schema' ),
		'description' => esc_html__( 'Legal Entity Identifier (ISO 17442).', 'review-schema' ),
		'depends'     => $rtrs_not_person,
	],
	'news_policies_section'   => [
		'title'       => esc_html__( 'Publisher Policies', 'review-schema' ),
		'description' => esc_html__( 'Google News reads these policy pages from a NewsMediaOrganization.', 'review-schema' ),
		'type'        => 'title',
		'depends'     => $rtrs_is_news_media,
	],
	'ethicsPolicy'            => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Ethics Policy URL', 'review-schema' ),
		'depends' => $rtrs_is_news_media,
	],
	'correctionsPolicy'       => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Corrections Policy URL', 'review-schema' ),
		'depends' => $rtrs_is_news_media,
	],
	'diversityPolicy'         => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Diversity Policy URL', 'review-schema' ),
		'depends' => $rtrs_is_news_media,
	],
	'verificationFactCheckingPolicy' => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Fact-Checking Policy URL', 'review-schema' ),
		'depends' => $rtrs_is_news_media,
	],
	'ownershipFundingInfo'    => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Ownership & Funding Info URL', 'review-schema' ),
		'depends' => $rtrs_is_news_media,
	],
	'masthead'                => [
		'type'    => 'url',
		'class'   => 'regular-text',
		'title'   => esc_html__( 'Masthead URL', 'review-schema' ),
		'depends' => $rtrs_is_news_media,
	],
	'offer_catalog_section'   => [
		'title'       => esc_html__( 'Offer Catalog', 'review-schema' ),
		'description' => esc_html__( 'List the services this business offers. Outputs an OfferCatalog of Service items.', 'review-schema' ),
		'type'        => 'title',
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
	'offer_catalog_name'      => [
		'type'        => 'text',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Catalog Name', 'review-schema' ),
		'placeholder' => esc_html__( 'Services', 'review-schema' ),
		'description' => esc_html__( 'A title for the catalog, for example "Services", "Treatments", or "Menu". Defaults to "Services".', 'review-schema' ),
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
	'offer_catalog'           => [
		'type'    => 'group',
		'is_pro'  => true,
		'title'   => esc_html__( 'Services', 'review-schema' ),
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
			'name' => [
				'type'        => 'text',
				'class'       => 'regular-text',
				'title'       => esc_html__( 'Service Name', 'review-schema' ),
				'placeholder' => esc_html__( 'e.g. Cardiology Consultation', 'review-schema' ),
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
