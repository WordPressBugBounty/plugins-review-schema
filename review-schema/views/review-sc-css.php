<?php

/**
 * @var integer $scID
 * @var bool    $old 
 * @var array    $rtrs_meta_data
 */
use Rtrs\Controllers\Admin\Meta\AddMetaBox;
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_filter = new AddMetaBox();

$rtrs_sc_id = $scID;
$rtrs_sc_meta = []; 
$rtrs_meta_data = get_post_meta($rtrs_sc_id);

$rtrs_sc_meta['layout'] = isset( $rtrs_meta_data['layout'][0] ) && !empty( $rtrs_meta_data['layout'][0] ) ? $rtrs_filter->sanitize_field('text', $rtrs_meta_data['layout'][0] ) : null;
$rtrs_sc_meta['width'] = isset( $rtrs_meta_data['width'][0] ) && !empty( $rtrs_meta_data['width'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['width'][0] ) : null; 
$rtrs_sc_meta['margin'] = isset( $rtrs_meta_data['margin'][0] ) && !empty( $rtrs_meta_data['margin'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['margin'][0] ) : null; 
$rtrs_sc_meta['padding'] = isset( $rtrs_meta_data['padding'][0] ) && !empty( $rtrs_meta_data['padding'][0] ) ? $rtrs_filter->sanitize_field( 'text', $rtrs_meta_data['padding'][0] ) : null; 
$rtrs_sc_meta['author_name'] = isset( $rtrs_meta_data['author_name'][0] ) && !empty( $rtrs_meta_data['author_name'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize($rtrs_meta_data['author_name'][0]) ) : null; 
$rtrs_sc_meta['author_name_hover'] = isset( $rtrs_meta_data['author_name_hover'][0] ) && !empty( $rtrs_meta_data['author_name_hover'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize($rtrs_meta_data['author_name_hover'][0]) ) : null;
$rtrs_sc_meta['review_title'] = isset( $rtrs_meta_data['review_title'][0] ) && !empty( $rtrs_meta_data['review_title'][0] ) ? $rtrs_filter->sanitize_field('style', unserialize($rtrs_meta_data['review_title'][0]) ) : null;   
$rtrs_sc_meta['review_text'] = isset( $rtrs_meta_data['review_text'][0] ) && !empty( $rtrs_meta_data['review_text'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize($rtrs_meta_data['review_text'][0]) ) : null;  
$rtrs_sc_meta['date_text'] = isset( $rtrs_meta_data['date_text'][0] ) && !empty( $rtrs_meta_data['date_text'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize($rtrs_meta_data['date_text'][0]) ) : null;      
$rtrs_sc_meta['star_color'] = isset( $rtrs_meta_data['star_color'][0] ) && !empty( $rtrs_meta_data['star_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['star_color'][0] ) : null;   
$rtrs_sc_meta['meta_icon_color'] = isset( $rtrs_meta_data['meta_icon_color'][0] ) && !empty( $rtrs_meta_data['meta_icon_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['meta_icon_color'][0] ) : null;   
 
$rtrs_sc_meta['helper_btn'] = isset( $rtrs_meta_data['helper_btn'][0] ) && !empty( $rtrs_meta_data['helper_btn'][0] ) ? $rtrs_filter->sanitize_field( 'style', unserialize($rtrs_meta_data['helper_btn'][0]) ) : null;
$rtrs_sc_meta['helper_btn_color'] = isset( $rtrs_meta_data['helper_btn_color'][0] ) && !empty( $rtrs_meta_data['helper_btn_color'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['helper_btn_color'][0] ) : null;
$rtrs_sc_meta['helper_btn_hover'] = isset( $rtrs_meta_data['helper_btn_hover'][0] ) && !empty( $rtrs_meta_data['helper_btn_hover'][0] ) ? $rtrs_filter->sanitize_field( 'color', $rtrs_meta_data['helper_btn_hover'][0] ) : null; 

$rtrs_css  = null;

$rtrs_css  .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-list .depth-2 .rtrs-reply-btn{display:none}";
if ( $rtrs_sc_meta['width'] || $rtrs_sc_meta['margin'] || $rtrs_sc_meta['padding']) { 
    $rtrs_css  .= "@media only screen and (min-width: 768px) { .rtrs-review-sc-{$rtrs_sc_id}{";

    if ( $rtrs_value = $rtrs_sc_meta['width'] ) { 
        $rtrs_css .= "width:" . $rtrs_value . ";";
    } 
    if ( $rtrs_value = $rtrs_sc_meta['margin'] ) { 
        $rtrs_css .= "margin:" . $rtrs_value . ";";
    } 
    if ( $rtrs_value = $rtrs_sc_meta['padding'] ) { 
        $rtrs_css .= "padding:" . $rtrs_value . ";";
    } 

    $rtrs_css .= "} }";
} 

$rtrs_typo = ( ! empty( $rtrs_sc_meta['author_name'] ) ? $rtrs_sc_meta['author_name'] : array() );
if ( ! empty( $rtrs_typo ) ) {
    $rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
    $rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
    $rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
    $rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null ); 
    if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
        $rtrs_css             .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-author-link{";
        if ( $rtrs_typo_color ) {
            $rtrs_css .= "color:" . $rtrs_typo_color . ";";
        }
        if ( $rtrs_typo_size ) {
            $rtrs_css .= "font-size:" . $rtrs_typo_size . "px;";
        }
        if ( $rtrs_typo_weight ) {
            $rtrs_css .= "font-weight:" . $rtrs_typo_weight . ";";
        }
        if ( $rtrs_typo_alignment ) {
            $rtrs_css .= "text-align:" . $rtrs_typo_alignment . ";";
        }
        $rtrs_css .= "}";  
    }
} 

$rtrs_typo = ( ! empty( $rtrs_sc_meta['author_name_hover'] ) ? $rtrs_sc_meta['author_name_hover'] : array() );
if ( ! empty( $rtrs_typo ) ) {
    $rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
    $rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
    $rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
    $rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null ); 
    if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
        $rtrs_css             .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-author-link:hover{";
        if ( $rtrs_typo_color ) {
            $rtrs_css .= "color:" . $rtrs_typo_color . ";";
        }
        if ( $rtrs_typo_size ) {
            $rtrs_css .= "font-size:" . $rtrs_typo_size . "px;";
        }
        if ( $rtrs_typo_weight ) {
            $rtrs_css .= "font-weight:" . $rtrs_typo_weight . ";";
        }
        if ( $rtrs_typo_alignment ) {
            $rtrs_css .= "text-align:" . $rtrs_typo_alignment . ";";
        }
        $rtrs_css .= "}";  
    }
}  
$rtrs_typo = ( ! empty( $rtrs_sc_meta['review_title'] ) ? $rtrs_sc_meta['review_title'] : array() );  
if ( ! empty( $rtrs_typo ) ) {
    $rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
    $rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
    $rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
    $rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null ); 
    if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
        $rtrs_css             .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-review-title{";
        if ( $rtrs_typo_color ) {
            $rtrs_css .= "color:" . $rtrs_typo_color . ";";
        }
        if ( $rtrs_typo_size ) {
            $rtrs_css .= "font-size:" . $rtrs_typo_size . "px;";
        }
        if ( $rtrs_typo_weight ) {
            $rtrs_css .= "font-weight:" . $rtrs_typo_weight . ";";
        }
        if ( $rtrs_typo_alignment ) {
            $rtrs_css .= "text-align:" . $rtrs_typo_alignment . ";";
        }
        $rtrs_css .= "}";  
    }
} 

$rtrs_typo = ( ! empty( $rtrs_sc_meta['review_text'] ) ? $rtrs_sc_meta['review_text'] : array() ); 
if ( ! empty( $rtrs_typo ) ) {
    $rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
    $rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
    $rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
    $rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null ); 
    if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
        $rtrs_css             .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body p{";
        if ( $rtrs_typo_color ) {
            $rtrs_css .= "color:" . $rtrs_typo_color . ";";
        }
        if ( $rtrs_typo_size ) {
            $rtrs_css .= "font-size:" . $rtrs_typo_size . "px;";
        }
        if ( $rtrs_typo_weight ) {
            $rtrs_css .= "font-weight:" . $rtrs_typo_weight . ";";
        }
        if ( $rtrs_typo_alignment ) {
            $rtrs_css .= "text-align:" . $rtrs_typo_alignment . ";";
        }
        $rtrs_css .= "}"; 
    } 
}    

$rtrs_typo = ( ! empty( $rtrs_sc_meta['date_text'] ) ? $rtrs_sc_meta['date_text'] : array() ); 
if ( ! empty( $rtrs_typo ) ) {
    $rtrs_typo_color     = ( ! empty( $rtrs_typo['color'] ) ? $rtrs_typo['color'] : null );
    $rtrs_typo_size      = ( ! empty( $rtrs_typo['size'] ) ? absint( $rtrs_typo['size'] ) : null );
    $rtrs_typo_weight    = ( ! empty( $rtrs_typo['weight'] ) ? $rtrs_typo['weight'] : null );
    $rtrs_typo_alignment = ( ! empty( $rtrs_typo['align'] ) ? $rtrs_typo['align'] : null ); 
    if ( $rtrs_typo_color || $rtrs_typo_size || $rtrs_typo_weight || $rtrs_typo_alignment ) {
        $rtrs_css  .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-review-meta .rtrs-review-date{";
        if ( $rtrs_typo_color ) {
            $rtrs_css .= "color:" . $rtrs_typo_color . ";";
        }
        if ( $rtrs_typo_size ) {
            $rtrs_css .= "font-size:" . $rtrs_typo_size . "px;";
        }
        if ( $rtrs_typo_weight ) {
            $rtrs_css .= "font-weight:" . $rtrs_typo_weight . ";";
        }
        if ( $rtrs_typo_alignment ) {
            $rtrs_css .= "text-align:" . $rtrs_typo_alignment . ";";
        }
        $rtrs_css .= "}";  
    }
}   
if ( $rtrs_value = $rtrs_sc_meta['star_color'] ) {
    $rtrs_css  .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-review-meta .rtrs-review-rating{";
    $rtrs_css .= "color:" . $rtrs_value . ";";
    $rtrs_css .= "}";
}

if ( $rtrs_value = $rtrs_sc_meta['meta_icon_color'] ) {
    $rtrs_css  .= ".rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-review-meta .rtrs-calendar:before, .rtrs-review-sc-{$rtrs_sc_id} .rtrs-review-box .rtrs-review-body .rtrs-review-meta .rtrs-share:before{";
    $rtrs_css .= "color:" . $rtrs_value . ";";
    $rtrs_css .= "}";
} 
$rtrs_css = apply_filters( 'rtrs_review_sc_css', $rtrs_css, $rtrs_meta_data, $rtrs_sc_id, $rtrs_filter );
if ( $rtrs_css ) {
    echo esc_html( wp_strip_all_tags( $rtrs_css ) );
} 