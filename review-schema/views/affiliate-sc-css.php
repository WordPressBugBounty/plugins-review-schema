<?php

/**
 * @var integer $scID
 * @var bool    $old
 * @var array   $rtrs_meta_data
 */
use Rtrs\Controllers\Admin\Meta\AddMetaBox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_filter = new AddMetaBox();

$rtrs_sc_id    = $scID;
$rtrs_sc_meta  = [];
$rtrs_meta_data = get_post_meta( $rtrs_sc_id );

$rtrs_sc_meta['layout']              = isset( $rtrs_meta_data['layout'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['layout'][0] ) : null;
$rtrs_sc_meta['width']               = isset( $rtrs_meta_data['width'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['width'][0] ) : null;
$rtrs_sc_meta['product_title']       = isset( $rtrs_meta_data['product_title'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize( $rtrs_meta_data['product_title'][0] ) ) : null;
$rtrs_sc_meta['product_desc']        = isset( $rtrs_meta_data['product_desc'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize( $rtrs_meta_data['product_desc'][0] ) ) : null;
$rtrs_sc_meta['style_regular_price'] = isset( $rtrs_meta_data['style_regular_price'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize( $rtrs_meta_data['style_regular_price'][0] ) ) : null;
$rtrs_sc_meta['style_offer_price']   = isset( $rtrs_meta_data['style_offer_price'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize( $rtrs_meta_data['style_offer_price'][0] ) ) : null;

$rtrs_sc_meta['border_color']  = isset( $rtrs_meta_data['border_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['border_color'][0] ) : null;
$rtrs_sc_meta['border_size']   = isset( $rtrs_meta_data['border_size'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['border_size'][0] ) : null;
$rtrs_sc_meta['border_radius'] = isset( $rtrs_meta_data['border_radius'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['border_radius'][0] ) : null;

$rtrs_sc_meta['circle_fill_color']   = isset( $rtrs_meta_data['circle_fill_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['circle_fill_color'][0] ) : null;
$rtrs_sc_meta['circle_empty_color']  = isset( $rtrs_meta_data['circle_empty_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['circle_empty_color'][0] ) : null;
$rtrs_sc_meta['circle_border_width'] = isset( $rtrs_meta_data['circle_border_width'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['circle_border_width'][0] ) : null;

$rtrs_sc_meta['btn']                    = isset( $rtrs_meta_data['btn'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize( $rtrs_meta_data['btn'][0] ) ) : null;
$rtrs_sc_meta['btn_bg']                 = isset( $rtrs_meta_data['btn_bg'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['btn_bg'][0] ) : null;
$rtrs_sc_meta['btn_border_color']       = isset( $rtrs_meta_data['btn_border_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['btn_border_color'][0] ) : null;
$rtrs_sc_meta['btn_hover']              = isset( $rtrs_meta_data['btn_hover'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize( $rtrs_meta_data['btn_hover'][0] ) ) : null;
$rtrs_sc_meta['btn_hover_bg']           = isset( $rtrs_meta_data['btn_hover_bg'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['btn_hover_bg'][0] ) : null;
$rtrs_sc_meta['btn_border_hover_color'] = isset( $rtrs_meta_data['btn_border_hover_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['btn_border_hover_color'][0] ) : null;

$rtrs_css = null;

if ( $rtrs_value = $rtrs_sc_meta['width'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id}{";
	$rtrs_css .= 'width:' . $rtrs_value . ';';
	$rtrs_css .= '}';
}

$rtrs_typo = ( ! empty( $rtrs_sc_meta['product_title'] ) ? $rtrs_sc_meta['product_title'] : [] );
if ( ! empty( $rtrs_typo ) ) {
	$rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
	$rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
	$rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
	$rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null );
	if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
		$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-title h3{";
		if ( $rtrs_typo_color ) {
			$rtrs_css .= 'color:' . $rtrs_typo_color . ';';
		}
		if ( $rtrs_typo_size ) {
			$rtrs_css .= 'font-size:' . $rtrs_typo_size . 'px;';
		}
		if ( $rtrs_typo_weight ) {
			$rtrs_css .= 'font-weight:' . $rtrs_typo_weight . ';';
		}
		if ( $rtrs_typo_alignment ) {
			$rtrs_css .= 'text-align:' . $rtrs_typo_alignment . ';';
		}
		$rtrs_css .= '}';
	}
}

$rtrs_typo = ( ! empty( $rtrs_sc_meta['product_desc'] ) ? $rtrs_sc_meta['product_desc'] : [] );
if ( ! empty( $rtrs_typo ) ) {
	$rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
	$rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
	$rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
	$rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null );
	if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
		$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-text p{";
		if ( $rtrs_typo_color ) {
			$rtrs_css .= 'color:' . $rtrs_typo_color . ';';
		}
		if ( $rtrs_typo_size ) {
			$rtrs_css .= 'font-size:' . $rtrs_typo_size . 'px;';
		}
		if ( $rtrs_typo_weight ) {
			$rtrs_css .= 'font-weight:' . $rtrs_typo_weight . ';';
		}
		if ( $rtrs_typo_alignment ) {
			$rtrs_css .= 'text-align:' . $rtrs_typo_alignment . ';';
		}
		$rtrs_css .= '}';
	}
}

$rtrs_typo = ( ! empty( $rtrs_sc_meta['style_regular_price'] ) ? $rtrs_sc_meta['style_regular_price'] : [] );
if ( ! empty( $rtrs_typo ) ) {
	$rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
	$rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
	$rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
	$rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null );
	if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
		$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-price-area .rtrs-regular-price{";
		if ( $rtrs_typo_color ) {
			$rtrs_css .= 'color:' . $rtrs_typo_color . ';';
		}
		if ( $rtrs_typo_size ) {
			$rtrs_css .= 'font-size:' . $rtrs_typo_size . 'px;';
		}
		if ( $rtrs_typo_weight ) {
			$rtrs_css .= 'font-weight:' . $rtrs_typo_weight . ';';
		}
		if ( $rtrs_typo_alignment ) {
			$rtrs_css .= 'text-align:' . $rtrs_typo_alignment . ';';
		}
		$rtrs_css .= '}';
	}
}

$rtrs_typo = ( ! empty( $rtrs_sc_meta['style_offer_price'] ) ? $rtrs_sc_meta['style_offer_price'] : [] );
if ( ! empty( $rtrs_typo ) ) {
	$rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
	$rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
	$rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
	$rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null );
	if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
		$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-price-area .rtrs-offer-price{";
		if ( $rtrs_typo_color ) {
			$rtrs_css .= 'color:' . $rtrs_typo_color . ';';
		}
		if ( $rtrs_typo_size ) {
			$rtrs_css .= 'font-size:' . $rtrs_typo_size . 'px;';
		}
		if ( $rtrs_typo_weight ) {
			$rtrs_css .= 'font-weight:' . $rtrs_typo_weight . ';';
		}
		if ( $rtrs_typo_alignment ) {
			$rtrs_css .= 'text-align:' . $rtrs_typo_alignment . ';';
		}
		$rtrs_css .= '}';
	}
}
// .rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary .rtrs-feedback-box
// border

if ( $rtrs_value = $rtrs_sc_meta['border_radius'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary{";
	$rtrs_css .= 'border-radius:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}
if ( $rtrs_value = $rtrs_sc_meta['border_size'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary{";
	$rtrs_css .= 'border:' . $rtrs_value . ' solid !important;';
	$rtrs_css .= '}';

	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-summary-2 .rtrs-rating-item, .rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary .rtrs-feedback-box{";
	$rtrs_css .= 'border-right:' . $rtrs_value . ' solid !important;';
	$rtrs_css .= '}';

	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-summary-2 .rtrs-rating-item:last-child, .rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary .rtrs-feedback-box:last-child{";
	$rtrs_css .= 'border-right: none !important;';
	$rtrs_css .= '}';
}
if ( $rtrs_value = $rtrs_sc_meta['border_color'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-summary-2 .rtrs-rating-item, .rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary, .rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-feedback-summary .rtrs-feedback-box{";
	$rtrs_css .= 'border-color:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}

// circle
if ( $rtrs_value = $rtrs_sc_meta['circle_border_width'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-summary-2 .rtrs-circle-bar svg circle{";
	$rtrs_css .= 'stroke-width:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}
if ( $rtrs_value = $rtrs_sc_meta['circle_empty_color'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-summary-2 .rtrs-circle-bar svg circle{";
	$rtrs_css .= 'stroke:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}
if ( $rtrs_value = $rtrs_sc_meta['circle_fill_color'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-summary-2 .rtrs-circle-bar svg circle:nth-child(2n){";
	$rtrs_css .= 'stroke:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}

// button
$rtrs_typo = ( ! empty( $rtrs_sc_meta['btn'] ) ? $rtrs_sc_meta['btn'] : [] );
if ( ! empty( $rtrs_typo ) ) {
	$rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
	$rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
	$rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
	$rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null );
	if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
		$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-buy-btn{";
		if ( $rtrs_typo_color ) {
			$rtrs_css .= 'color:' . $rtrs_typo_color . ' !important;';
		}
		if ( $rtrs_typo_size ) {
			$rtrs_css .= 'font-size:' . $rtrs_typo_size . 'px !important;';
		}
		if ( $rtrs_typo_weight ) {
			$rtrs_css .= 'font-weight:' . $rtrs_typo_weight . ' !important;';
		}
		if ( $rtrs_typo_alignment ) {
			$rtrs_css .= 'text-align:' . $rtrs_typo_alignment . '!important;';
		}
		$rtrs_css .= '}';
	}
}

if ( $rtrs_value = $rtrs_sc_meta['btn_bg'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-buy-btn{";
	$rtrs_css .= 'background:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}
if ( $rtrs_value = $rtrs_sc_meta['btn_border_color'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-buy-btn{";
	$rtrs_css .= 'border-color:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}

$rtrs_typo = ( ! empty( $rtrs_sc_meta['btn_hover'] ) ? $rtrs_sc_meta['btn_hover'] : [] );
if ( ! empty( $rtrs_typo ) ) {
	$rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
	$rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
	$rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
	$rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null );
	if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
		$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-buy-btn:hover{";
		if ( $rtrs_typo_color ) {
			$rtrs_css .= 'color:' . $rtrs_typo_color . ' !important;';
		}
		if ( $rtrs_typo_size ) {
			$rtrs_css .= 'font-size:' . $rtrs_typo_size . 'px !important;';
		}
		if ( $rtrs_typo_weight ) {
			$rtrs_css .= 'font-weight:' . $rtrs_typo_weight . ' !important;';
		}
		if ( $rtrs_typo_alignment ) {
			$rtrs_css .= 'text-align:' . $rtrs_typo_alignment . '!important;';
		}
		$rtrs_css .= '}';
	}
}

if ( $rtrs_value = $rtrs_sc_meta['btn_hover_bg'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-buy-btn:hover{";
	$rtrs_css .= 'background:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}
if ( $rtrs_value = $rtrs_sc_meta['btn_border_hover_color'] ) {
	$rtrs_css .= ".rtrs-affiliate-sc-{$rtrs_sc_id} .rtrs-buy-btn:hover{";
	$rtrs_css .= 'border-color:' . $rtrs_value . ' !important;';
	$rtrs_css .= '}';
}

$rtrs_css = apply_filters( 'rtrs_affiliate_sc_css', $rtrs_css, $rtrs_meta_data, $rtrs_sc_id, $rtrs_filter );

if ( $rtrs_css ) {
	echo esc_html( wp_strip_all_tags( $rtrs_css ) );
}
