<?php 

if ( ! defined( 'ABSPATH' ) ) exit; 

/**
 * Media Settings
 */

$rtrs_image_type = apply_filters( 'rtrs_media_image_type', array(
	'image/jpg' => esc_html__('jpg', 'review-schema'),
	'image/jpeg' => esc_html__('jpeg', 'review-schema'),
	'image/png' => esc_html__('png', 'review-schema'),
	'image/gif' => esc_html__('gif', 'review-schema'),
	'image/webp' => esc_html__('webp', 'review-schema'),
) );

$rtrs_options = array(
	'img_section' => array(
		'title'  => esc_html__( 'Image Upload Settings', 'review-schema' ),
		'type'   => 'title', 
	), 
	'img_max_size'  => array(
        'title'   => esc_html__('Image Max Size', 'review-schema'),
        'type'    => 'number',  
        'default' => 1024,  
		'css'     => 'width: 70px',
		'description' => esc_html__('Change the value as KB, Like 1M = 1024KB', 'review-schema')
    ), 
	'img_type' => array(
		'title'   => esc_html__( 'Supported Image Type', 'review-schema' ),
		'type'    => 'multi_checkbox',
		'default' => array(
			'image/jpg',
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp'
		),
		'options' => $rtrs_image_type
	),
	'allow_guest_upload' => array(
		'title'       => esc_html__( 'Allow Guest Uploads', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'Let non-logged-in visitors upload files with their review. Uploads are still restricted to the supported types and size limits above. Leave off to require login (recommended).', 'review-schema' ),
	),
);

return apply_filters( 'rtrs_media_settings_options', $rtrs_options );