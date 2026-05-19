<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General Settings
 */
use Rtrs\Helpers\Functions;

$rtrs_post_types = Functions::getPostTypes();
array_shift( $rtrs_post_types );

$rtrs_options = [
	'gl_section'                       => [
		'title' => esc_html__( 'Google Captcha v3', 'review-schema' ),
		'type'  => 'title',
	],
	'recaptcha_sitekey'                => [
		'type'        => 'password',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'Recaptcha site key', 'review-schema' ),
		'description' => wp_kses(
			__( "How to get <a target='_blank' href='https://www.radiustheme.com/docs/review-schema/faq/how-to-add-google-captcha-v3-api-key/'>Recaptcha site & secret key?</a>", 'review-schema' ),
			[
				'a' => [
					'href'   => [],
					'target' => [],
				],
			]
		),
	],
	'recaptcha_secretkey'              => [
		'type'  => 'password',
		'class' => 'regular-text',
		'title' => esc_html__( 'Recaptcha secret key', 'review-schema' ),
	],
	'review_multiple_section'          => [
		'title' => esc_html__( 'Multiple Review Submission', 'review-schema' ),
		'type'  => 'title',
	],
	'multiple_review'                  => [
		'title'       => esc_html__( 'Multiple review', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'type'        => 'checkbox',
		'description' => esc_attr__( 'User can give multiple reviews.', 'review-schema' ),
		'default'     => '',
	],
	'enable_review_gdpr_consent'       => [
		'title'       => esc_html__( 'Enable GDPR Consent for Reviews', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'description' => esc_html__( 'Require users to give consent before their review is included in structured data (schema).', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
	],
	'require_gdpr_consent_for_reviews' => [
		'title'       => esc_html__( 'Make GDPR Consent Mandatory', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'description' => esc_html__( 'Users must agree before submitting a review.', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => 'yes',
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_review_settings.enable_review_gdpr_consent',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
	'review_gdpr_consent_text'         => [
		'type'        => 'textarea',
		'class'       => 'regular-text',
		'title'       => esc_html__( 'GDPR Consent Message', 'review-schema' ),
		'description' => esc_html__( 'This text will appear next to the consent checkbox on the review form.', 'review-schema' ),
		'default'     => esc_html__( 'I agree that my review and personal data may be displayed publicly in accordance with the Privacy Policy.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_review_settings.enable_review_gdpr_consent',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
	'review_edit_section'              => [
		'title'       => esc_html__( 'Edit Review Frontend', 'review-schema' ),
		'type'        => 'title',
		'description' => esc_html__( 'Which field you want to allow to edit for user', 'review-schema' ),
	],
	'review_edit'                      => [
		'title'   => esc_html__( 'Edit Review', 'review-schema' ),
		'label'   => esc_html__( 'Allow', 'review-schema' ),
		'type'    => 'checkbox',
		'default' => 'yes',
	],
	'review_edit_field'                => [
		'title'   => esc_html__( 'Review edit field', 'review-schema' ),
		'type'    => 'multi_checkbox',
		'default' => [
			'rating',
			'desc',
		],
		'options' => [
			'rating'    => esc_html__( 'Rating', 'review-schema' ),
			'desc'      => esc_html__( 'Description', 'review-schema' ),
			'title'     => esc_html__( 'Title', 'review-schema' ),
			'pros_cons' => esc_html__( 'Pros & Cons', 'review-schema' ),
			'image'     => esc_html__( 'Image', 'review-schema' ),
			'video'     => esc_html__( 'Video', 'review-schema' ),
			'anonymous' => esc_html__( 'Anonymous Review', 'review-schema' ),
		],
		'depends' => [
			'on' => [
				[
					'field'     => 'rtrs_review_settings.review_edit',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],

	'comment_reply_permition'          => [
		'title'       => esc_html__( 'Comment Reply Permission', 'review-schema' ),
		'type'        => 'multi_checkbox',
		'default'     => [
			'administrator',
			'shop_manager',
		],
		'options'     => Functions::get_available_roles(),
		'empty'       => esc_html__( 'Select One', 'review-schema' ),
		'description' => esc_html__( 'They Can comment And Reply Without Restriction', 'review-schema' ),
	],

];

return apply_filters( 'rtrs_review_settings_options', $rtrs_options );
