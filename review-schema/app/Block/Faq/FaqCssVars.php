<?php
/**
 * FAQ CSS Variable Builder
 *
 * Generates responsive CSS custom properties for the FAQ block.
 * Outputs a <style> tag with media queries instead of inline styles.
 *
 * @package Rtrs\Block\Faq
 */

namespace Rtrs\Block\Faq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqCssVars {

	/**
	 * Build responsive CSS for a FAQ block.
	 *
	 * @param array  $attrs    Block attributes.
	 * @param string $block_id Unique block element ID.
	 * @return string <style> tag with CSS.
	 */
	public static function build( array $attrs, string $block_id ): string {
		$desktop = [];
		$tablet  = [];
		$mobile  = [];

		// ── Gap ──
		$item_gap = self::responsive_number( $attrs, 'itemGap', 'gap', 12 );
		$desktop[] = '--rtrs-faq-gap:' . $item_gap['desktop'] . 'px';
		if ( ! empty( $item_gap['tablet'] ) ) {
			$tablet[] = '--rtrs-faq-gap:' . $item_gap['tablet'] . 'px';
		}
		if ( ! empty( $item_gap['mobile'] ) ) {
			$mobile[] = '--rtrs-faq-gap:' . $item_gap['mobile'] . 'px';
		}

		// ── Grid columns ──
		$grid_cols = self::responsive_number( $attrs, 'gridColumns', null, 2 );
		$desktop[] = '--rtrs-faq-grid-columns:' . $grid_cols['desktop'];
		if ( ! empty( $grid_cols['tablet'] ) ) {
			$tablet[] = '--rtrs-faq-grid-columns:' . $grid_cols['tablet'];
		}
		if ( ! empty( $grid_cols['mobile'] ) ) {
			$mobile[] = '--rtrs-faq-grid-columns:' . $grid_cols['mobile'];
		}

		// ── Item Border ──
		$border = self::get_object( $attrs, 'itemBorder', [ 'color' => '#e5e7eb', 'width' => 1, 'radius' => 8, 'style' => 'solid' ] );
		$desktop[] = '--rtrs-faq-border-width:' . intval( $border['width'] ) . 'px';
		$desktop[] = '--rtrs-faq-border-radius:' . intval( $border['radius'] ) . 'px';
		$desktop[] = '--rtrs-faq-border-style:' . sanitize_text_field( $border['style'] );
		if ( ! empty( $border['color'] ) ) {
			$desktop[] = '--rtrs-faq-border-color:' . self::sanitize_color( $border['color'] );
		}

		// ── 4-sided paddings ──
		$desktop[] = '--rtrs-faq-q-padding:' . self::four_side_css( $attrs, 'questionPadding', 'desktop', 16 );
		$q_tablet = self::four_side_css( $attrs, 'questionPadding', 'tablet' );
		if ( $q_tablet ) $tablet[] = '--rtrs-faq-q-padding:' . $q_tablet;
		$q_mobile = self::four_side_css( $attrs, 'questionPadding', 'mobile' );
		if ( $q_mobile ) $mobile[] = '--rtrs-faq-q-padding:' . $q_mobile;

		$desktop[] = '--rtrs-faq-a-padding:' . self::four_side_css( $attrs, 'answerPadding', 'desktop', 16 );
		$a_tablet = self::four_side_css( $attrs, 'answerPadding', 'tablet' );
		if ( $a_tablet ) $tablet[] = '--rtrs-faq-a-padding:' . $a_tablet;
		$a_mobile = self::four_side_css( $attrs, 'answerPadding', 'mobile' );
		if ( $a_mobile ) $mobile[] = '--rtrs-faq-a-padding:' . $a_mobile;

		$section_pad = self::four_side_css( $attrs, 'sectionPadding', 'desktop', 0 );
		if ( $section_pad && '0px 0px 0px 0px' !== $section_pad ) {
			$desktop[] = '--rtrs-faq-section-padding:' . $section_pad;
		}

		$section_margin = self::four_side_css( $attrs, 'sectionMargin', 'desktop', 0 );
		if ( $section_margin && '0px 0px 0px 0px' !== $section_margin ) {
			$desktop[] = '--rtrs-faq-section-margin:' . $section_margin;
		}

		// ── Icon ──
		$desktop[] = '--rtrs-faq-icon-size:' . intval( $attrs['iconSize'] ?? 20 ) . 'px';
		$desktop[] = '--rtrs-faq-icon-spacing:' . intval( $attrs['iconSpacing'] ?? 12 ) . 'px';

		// ── Icon state colors ──
		$icon_normal = self::get_object( $attrs, 'iconNormal', [ 'color' => '' ] );
		if ( ! empty( $icon_normal['color'] ) ) {
			$desktop[] = '--rtrs-faq-icon-color:' . self::sanitize_color( $icon_normal['color'] );
		}

		$icon_hover = self::get_object( $attrs, 'iconHover', [ 'color' => '' ] );
		if ( ! empty( $icon_hover['color'] ) ) {
			$desktop[] = '--rtrs-faq-icon-hover-color:' . self::sanitize_color( $icon_hover['color'] );
		}

		$icon_active = self::get_object( $attrs, 'iconActive', [ 'color' => '' ] );
		if ( ! empty( $icon_active['color'] ) ) {
			$desktop[] = '--rtrs-faq-icon-active-color:' . self::sanitize_color( $icon_active['color'] );
		}

		$icon_border = self::get_object( $attrs, 'iconBorder', [ 'color' => '', 'width' => 0, 'radius' => 0, 'style' => 'none' ] );
		if ( intval( $icon_border['width'] ?? 0 ) > 0 ) {
			$desktop[] = '--rtrs-faq-icon-border-width:' . intval( $icon_border['width'] ) . 'px';
			$desktop[] = '--rtrs-faq-icon-border-style:' . sanitize_text_field( $icon_border['style'] ?: 'solid' );
			if ( ! empty( $icon_border['color'] ) ) {
				$desktop[] = '--rtrs-faq-icon-border-color:' . self::sanitize_color( $icon_border['color'] );
			}
		}
		if ( intval( $icon_border['radius'] ?? 0 ) > 0 ) {
			$desktop[] = '--rtrs-faq-icon-border-radius:' . intval( $icon_border['radius'] ) . 'px';
		}

		// ── Typography — Question ──
		$q_typo = self::get_object( $attrs, 'questionTypography', [] );
		self::add_typography_vars( $desktop, $tablet, $mobile, $q_typo, 'q' );

		// ── Typography — Answer ──
		$a_typo = self::get_object( $attrs, 'answerTypography', [] );
		self::add_typography_vars( $desktop, $tablet, $mobile, $a_typo, 'a' );

		// ── Question hover text color ──
		if ( ! empty( $q_typo['hoverColor'] ) ) {
			$desktop[] = '--rtrs-faq-q-hover-color:' . self::sanitize_color( $q_typo['hoverColor'] );
		}

		// ── Item hover box shadow ──
		$hover_shadow = self::get_object( $attrs, 'itemHoverBoxShadow', [ 'enabled' => false ] );
		if ( ! empty( $hover_shadow['enabled'] ) ) {
			$inset = ! empty( $hover_shadow['inset'] ) ? 'inset ' : '';
			$desktop[] = '--rtrs-faq-item-hover-shadow:' . $inset
				. intval( $hover_shadow['x'] ?? 0 ) . 'px '
				. intval( $hover_shadow['y'] ?? 2 ) . 'px '
				. intval( $hover_shadow['blur'] ?? 8 ) . 'px '
				. intval( $hover_shadow['spread'] ?? 0 ) . 'px '
				. self::sanitize_color( $hover_shadow['color'] ?? 'rgba(0,0,0,0.08)' );
		}

		// ── Question background ──
		$q_bg = self::get_object( $attrs, 'questionBackground', [ 'type' => 'solid', 'color' => '', 'gradient' => '' ] );
		if ( ! empty( $q_bg['color'] ) ) {
			$desktop[] = '--rtrs-faq-q-bg:' . self::sanitize_color( $q_bg['color'] );
		}
		if ( ! empty( $q_bg['gradient'] ) && 'gradient' === $q_bg['type'] ) {
			$desktop[] = '--rtrs-faq-q-bg:' . sanitize_text_field( $q_bg['gradient'] );
		}

		// ── Question active ──
		if ( ! empty( $attrs['questionActiveColor'] ) ) {
			$desktop[] = '--rtrs-faq-q-active-color:' . self::sanitize_color( $attrs['questionActiveColor'] );
		}
		$q_active_bg = self::get_object( $attrs, 'questionActiveBackground', [] );
		if ( ! empty( $q_active_bg['color'] ) ) {
			$desktop[] = '--rtrs-faq-q-active-bg:' . self::sanitize_color( $q_active_bg['color'] );
		}

		// ── Answer background ──
		$a_bg = self::get_object( $attrs, 'answerBackground', [] );
		if ( ! empty( $a_bg['color'] ) ) {
			$desktop[] = '--rtrs-faq-a-bg:' . self::sanitize_color( $a_bg['color'] );
		}

		// ── Container border radius ──
		$c_border = self::get_object( $attrs, 'containerBorder', [ 'radius' => 8 ] );
		$desktop[] = '--rtrs-faq-container-radius:' . intval( $c_border['radius'] ) . 'px';

		// ── Container background ──
		$c_bg = self::get_object( $attrs, 'containerBackground', [] );
		if ( ! empty( $c_bg['color'] ) ) {
			$desktop[] = '--rtrs-faq-container-bg:' . self::sanitize_color( $c_bg['color'] );
		}

		// ── Search Style ──
		if ( ! empty( $attrs['searchTextColor'] ) ) {
			$desktop[] = '--rtrs-faq-search-color:' . self::sanitize_color( $attrs['searchTextColor'] );
		}
		if ( ! empty( $attrs['searchBgColor'] ) ) {
			$desktop[] = '--rtrs-faq-search-bg:' . self::sanitize_color( $attrs['searchBgColor'] );
		}
		if ( ! empty( $attrs['searchBorderColor'] ) ) {
			$desktop[] = '--rtrs-faq-search-border-color:' . self::sanitize_color( $attrs['searchBorderColor'] );
		}
		$desktop[] = '--rtrs-faq-search-border-radius:' . intval( $attrs['searchBorderRadius'] ?? 8 ) . 'px';
		$desktop[] = '--rtrs-faq-search-font-size:' . intval( $attrs['searchFontSize'] ?? 14 ) . 'px';
		$search_padding = sanitize_text_field( $attrs['searchPadding'] ?? '10px 16px' );
		if ( $search_padding && '10px 16px' !== $search_padding ) {
			$desktop[] = '--rtrs-faq-search-padding:' . $search_padding;
		}
		$search_ph_color = $attrs['searchPlaceholderColor'] ?? '#9ca3af';
		if ( ! empty( $search_ph_color ) && '#9ca3af' !== $search_ph_color ) {
			$desktop[] = '--rtrs-faq-search-placeholder-color:' . self::sanitize_color( $search_ph_color );
		}

		// ── Button (Open/Close All) ──
		if ( ! empty( $attrs['btnTextColor'] ) ) {
			$desktop[] = '--rtrs-faq-btn-color:' . self::sanitize_color( $attrs['btnTextColor'] );
		}
		if ( ! empty( $attrs['btnBgColor'] ) ) {
			$desktop[] = '--rtrs-faq-btn-bg:' . self::sanitize_color( $attrs['btnBgColor'] );
		}
		if ( ! empty( $attrs['btnBorderColor'] ) ) {
			$desktop[] = '--rtrs-faq-btn-border-color:' . self::sanitize_color( $attrs['btnBorderColor'] );
		}
		$desktop[] = '--rtrs-faq-btn-border-radius:' . intval( $attrs['btnBorderRadius'] ?? 8 ) . 'px';
		$desktop[] = '--rtrs-faq-btn-font-size:' . intval( $attrs['btnFontSize'] ?? 13 ) . 'px';
		$btn_padding = sanitize_text_field( $attrs['btnPadding'] ?? '6px 14px' );
		if ( $btn_padding && '6px 14px' !== $btn_padding ) {
			$desktop[] = '--rtrs-faq-btn-padding:' . $btn_padding;
		}

		// ── Divider ──
		if ( ! empty( $attrs['dividerColor'] ) ) {
			$desktop[] = '--rtrs-faq-divider-color:' . self::sanitize_color( $attrs['dividerColor'] );
		}
		$desktop[] = '--rtrs-faq-divider-width:' . intval( $attrs['dividerWidth'] ?? 1 ) . 'px';
		$divider_style = sanitize_text_field( $attrs['dividerStyle'] ?? 'solid' );
		if ( $divider_style && 'solid' !== $divider_style ) {
			$desktop[] = '--rtrs-faq-divider-style:' . $divider_style;
		}

		// ── Border gap ──
		$border_gap = intval( $attrs['borderGap'] ?? 0 );
		if ( $border_gap > 0 ) {
			$desktop[] = '--rtrs-faq-border-gap:' . $border_gap . 'px';
		}

		// ── Question-answer gap ──
		$qa_gap = intval( $attrs['questionAnswerGap'] ?? 0 );
		if ( $qa_gap > 0 ) {
			$desktop[] = '--rtrs-faq-qa-gap:' . $qa_gap . 'px';
		}

		// Build CSS
		$css = '#' . $block_id . '{' . implode( ';', $desktop ) . '}';
		if ( ! empty( $tablet ) ) {
			$css .= '@media(max-width:1024px){#' . $block_id . '{' . implode( ';', $tablet ) . '}}';
		}
		if ( ! empty( $mobile ) ) {
			$css .= '@media(max-width:768px){#' . $block_id . '{' . implode( ';', $mobile ) . '}}';
		}

		return '<style>' . $css . '</style>';
	}

	// ── Helpers ──

	private static function responsive_number( array $attrs, string $key, ?string $legacy_key, int $default ): array {
		$val = $attrs[ $key ] ?? null;

		// New object format
		if ( is_array( $val ) ) {
			return [
				'desktop' => intval( $val['desktop'] ?? $default ),
				'tablet'  => isset( $val['tablet'] ) ? intval( $val['tablet'] ) : null,
				'mobile'  => isset( $val['mobile'] ) ? intval( $val['mobile'] ) : null,
			];
		}

		// Legacy flat number
		$legacy = $legacy_key ? intval( $attrs[ $legacy_key ] ?? $default ) : $default;
		return [ 'desktop' => intval( $val ?? $legacy ), 'tablet' => null, 'mobile' => null ];
	}

	private static function get_object( array $attrs, string $key, array $default ): array {
		$val = $attrs[ $key ] ?? null;
		return is_array( $val ) ? wp_parse_args( $val, $default ) : $default;
	}

	private static function four_side_css( array $attrs, string $key, string $device, int $default = 0 ): string {
		$val = $attrs[ $key ] ?? null;
		if ( ! is_array( $val ) ) {
			// Legacy single number
			$num = intval( $val ?? $default );
			return $device === 'desktop' ? "{$num}px {$num}px {$num}px {$num}px" : '';
		}

		$side = $val[ $device ] ?? ( $device === 'desktop' ? $val['desktop'] ?? null : null );
		if ( ! is_array( $side ) ) {
			return '';
		}

		return intval( $side['top'] ?? $default ) . 'px '
			. intval( $side['right'] ?? $default ) . 'px '
			. intval( $side['bottom'] ?? $default ) . 'px '
			. intval( $side['left'] ?? $default ) . 'px';
	}

	/**
	 * Add CSS variable declarations for typography properties.
	 *
	 * @param array  $desktop Desktop declarations.
	 * @param array  $tablet  Tablet declarations.
	 * @param array  $mobile  Mobile declarations.
	 * @param array  $typo    Typography attribute object.
	 * @param string $prefix  Variable prefix ('q' or 'a').
	 */
	private static function add_typography_vars( array &$desktop, array &$tablet, array &$mobile, array $typo, string $prefix ): void {
		if ( ! empty( $typo['color'] ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-color:" . self::sanitize_color( $typo['color'] );
		}
		if ( ! empty( $typo['fontWeight'] ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-font-weight:" . intval( $typo['fontWeight'] );
		}
		if ( ! empty( $typo['lineHeight'] ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-line-height:" . sanitize_text_field( $typo['lineHeight'] );
		}
		if ( ! empty( $typo['letterSpacing'] ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-letter-spacing:" . sanitize_text_field( $typo['letterSpacing'] );
		}
		if ( ! empty( $typo['textTransform'] ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-text-transform:" . sanitize_text_field( $typo['textTransform'] );
		}
		if ( ! empty( $typo['fontFamily'] ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-font-family:" . sanitize_text_field( $typo['fontFamily'] );
		}

		// Responsive font size.
		$font_size = $typo['fontSize'] ?? null;
		if ( is_array( $font_size ) ) {
			$d = intval( $font_size['desktop'] ?? 0 );
			if ( $d > 0 ) { $desktop[] = "--rtrs-faq-{$prefix}-font-size:{$d}px"; }
			$t = isset( $font_size['tablet'] ) ? intval( $font_size['tablet'] ) : 0;
			if ( $t > 0 ) { $tablet[] = "--rtrs-faq-{$prefix}-font-size:{$t}px"; }
			$m = isset( $font_size['mobile'] ) ? intval( $font_size['mobile'] ) : 0;
			if ( $m > 0 ) { $mobile[] = "--rtrs-faq-{$prefix}-font-size:{$m}px"; }
		} elseif ( ! empty( $font_size ) ) {
			$desktop[] = "--rtrs-faq-{$prefix}-font-size:" . intval( $font_size ) . 'px';
		}
	}

	/**
	 * Compute conditional has-* CSS classes based on which attributes are set.
	 *
	 * @param array $attrs Block attributes.
	 * @return string Space-separated class names.
	 */
	public static function conditional_classes( array $attrs ): string {
		$classes = [];

		// Question typography.
		$q_typo = self::get_object( $attrs, 'questionTypography', [] );
		if ( ! empty( $q_typo['color'] ) )         { $classes[] = 'has-q-color'; }
		if ( ! empty( $q_typo['fontWeight'] ) )     { $classes[] = 'has-q-font-weight'; }
		if ( ! empty( $q_typo['lineHeight'] ) )     { $classes[] = 'has-q-line-height'; }
		if ( ! empty( $q_typo['letterSpacing'] ) )  { $classes[] = 'has-q-letter-spacing'; }
		if ( ! empty( $q_typo['textTransform'] ) )  { $classes[] = 'has-q-text-transform'; }
		if ( ! empty( $q_typo['fontFamily'] ) )     { $classes[] = 'has-q-font-family'; }
		if ( ! empty( $q_typo['hoverColor'] ) )     { $classes[] = 'has-q-hover-color'; }

		$font_size = $q_typo['fontSize'] ?? null;
		if ( is_array( $font_size ) ? intval( $font_size['desktop'] ?? 0 ) > 0 : ! empty( $font_size ) ) {
			$classes[] = 'has-q-font-size';
		}

		// Question background & active.
		$q_bg = self::get_object( $attrs, 'questionBackground', [] );
		if ( ! empty( $q_bg['color'] ) || ! empty( $q_bg['gradient'] ) ) {
			$classes[] = 'has-q-bg';
		}
		if ( ! empty( $attrs['questionActiveColor'] ) )  { $classes[] = 'has-q-active-color'; }
		$q_active_bg = self::get_object( $attrs, 'questionActiveBackground', [] );
		if ( ! empty( $q_active_bg['color'] ) )          { $classes[] = 'has-q-active-bg'; }

		// Answer typography.
		$a_typo = self::get_object( $attrs, 'answerTypography', [] );
		if ( ! empty( $a_typo['color'] ) )         { $classes[] = 'has-a-color'; }
		if ( ! empty( $a_typo['fontWeight'] ) )     { $classes[] = 'has-a-font-weight'; }
		if ( ! empty( $a_typo['lineHeight'] ) )     { $classes[] = 'has-a-line-height'; }
		if ( ! empty( $a_typo['letterSpacing'] ) )  { $classes[] = 'has-a-letter-spacing'; }
		if ( ! empty( $a_typo['textTransform'] ) )  { $classes[] = 'has-a-text-transform'; }
		if ( ! empty( $a_typo['fontFamily'] ) )     { $classes[] = 'has-a-font-family'; }

		$a_font_size = $a_typo['fontSize'] ?? null;
		if ( is_array( $a_font_size ) ? intval( $a_font_size['desktop'] ?? 0 ) > 0 : ! empty( $a_font_size ) ) {
			$classes[] = 'has-a-font-size';
		}

		// Answer background.
		$a_bg = self::get_object( $attrs, 'answerBackground', [] );
		if ( ! empty( $a_bg['color'] ) ) { $classes[] = 'has-a-bg'; }

		return implode( ' ', $classes );
	}

	private static function sanitize_color( string $color ): string {
		// Allow hex, rgb, rgba, hsl, hsla, and CSS variables
		if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|var\([^)]+\))$/', $color ) ) {
			return $color;
		}
		return sanitize_hex_color( $color ) ?: '';
	}
}
