<?php
/**
 * FAQ Block Attribute Definitions
 *
 * Single source of truth for all block attributes (PHP side).
 * Mirrors the TypeScript BLOCK_ATTRIBUTES in types/attributes.ts.
 *
 * @package Rtrs\Block\Faq
 */

namespace Rtrs\Block\Faq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqAttributes {

	/**
	 * Get all block attribute definitions.
	 */
	public static function get(): array {
		return array_merge(
			self::layout_attributes(),
			self::behavior_attributes(),
			self::schema_search_attributes(),
			self::icon_attributes(),
			self::spacing_attributes(),
			self::typography_attributes(),
			self::question_style_attributes(),
			self::answer_style_attributes(),
			self::container_style_attributes(),
			self::item_style_attributes(),
			self::divider_attributes(),
			self::legacy_attributes()
		);
	}

	private static function layout_attributes(): array {
		return [
			'layout'       => [ 'type' => 'string',  'default' => 'accordion' ],
			'gridColumns'  => [ 'type' => 'object',  'default' => [ 'desktop' => 2, 'tablet' => 2, 'mobile' => 1 ] ],
			'headingLevel' => [ 'type' => 'number',  'default' => 3 ],
		];
	}

	private static function behavior_attributes(): array {
		return [
			'firstOpen'          => [ 'type' => 'boolean', 'default' => true ],
			'maxExpanded'        => [ 'type' => 'number',  'default' => 0 ],
			'multipleOpen'       => [ 'type' => 'boolean', 'default' => false ],
			'autoCloseSeconds'   => [ 'type' => 'number',  'default' => 0 ],
			'enableOpenCloseAll' => [ 'type' => 'boolean', 'default' => false ],
		];
	}

	private static function schema_search_attributes(): array {
		return [
			'enableSchema'      => [ 'type' => 'boolean', 'default' => true ],
			'enableSearch'      => [ 'type' => 'boolean', 'default' => false ],
			'searchPlaceholder' => [ 'type' => 'string',  'default' => 'Search FAQs...' ],
			'enableCategories'  => [ 'type' => 'boolean', 'default' => false ],

			// Search Style
			'searchTextColor'        => [ 'type' => 'string',  'default' => '' ],
			'searchBgColor'          => [ 'type' => 'string',  'default' => '' ],
			'searchBorderColor'      => [ 'type' => 'string',  'default' => '' ],
			'searchBorderRadius'     => [ 'type' => 'number',  'default' => 8 ],
			'searchFontSize'         => [ 'type' => 'number',  'default' => 14 ],
			'searchPadding'          => [ 'type' => 'string',  'default' => '10px 16px' ],
			'searchPlaceholderColor' => [ 'type' => 'string',  'default' => '#9ca3af' ],

			// Button Style (Open/Close All)
			'btnTextColor'        => [ 'type' => 'string',  'default' => '' ],
			'btnBgColor'          => [ 'type' => 'string',  'default' => '' ],
			'btnBorderColor'      => [ 'type' => 'string',  'default' => '' ],
			'btnBorderRadius'     => [ 'type' => 'number',  'default' => 8 ],
			'btnFontSize'         => [ 'type' => 'number',  'default' => 13 ],
			'btnPadding'          => [ 'type' => 'string',  'default' => '6px 14px' ],
		];
	}

	private static function icon_attributes(): array {
		return [
			'expandIcon'   => [ 'type' => 'string',  'default' => 'plus' ],
			'collapseIcon' => [ 'type' => 'string',  'default' => 'minus' ],
			'iconPosition' => [ 'type' => 'string',  'default' => 'right' ],
			'iconSize'     => [ 'type' => 'number',  'default' => 20 ],
			'iconSpacing'  => [ 'type' => 'number',  'default' => 12 ],
			'iconBorder'   => [ 'type' => 'object',  'default' => [ 'color' => '', 'width' => 0, 'radius' => 0, 'style' => 'none' ] ],
			'iconNormal'   => [ 'type' => 'object',  'default' => [ 'color' => '', 'bgColor' => '' ] ],
			'iconHover'    => [ 'type' => 'object',  'default' => [ 'color' => '', 'bgColor' => '' ] ],
			'iconActive'   => [ 'type' => 'object',  'default' => [ 'color' => '', 'bgColor' => '' ] ],
		];
	}

	private static function spacing_attributes(): array {
		$default_padding = [ 'desktop' => [ 'top' => 16, 'right' => 16, 'bottom' => 16, 'left' => 16 ] ];
		$zero_padding    = [ 'desktop' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ] ];

		return [
			'itemGap'            => [ 'type' => 'object',  'default' => [ 'desktop' => 12, 'tablet' => 12, 'mobile' => 10 ] ],
			'questionAnswerGap'  => [ 'type' => 'number',  'default' => 0 ],
			'questionPadding'    => [ 'type' => 'object',  'default' => $default_padding ],
			'answerPadding'      => [ 'type' => 'object',  'default' => $default_padding ],
			'sectionPadding'     => [ 'type' => 'object',  'default' => $zero_padding ],
			'sectionMargin'      => [ 'type' => 'object',  'default' => $zero_padding ],
			'borderGap'          => [ 'type' => 'number',  'default' => 0 ],
		];
	}

	private static function typography_attributes(): array {
		return [
			'questionTypography' => [
				'type'    => 'object',
				'default' => [
					'fontFamily'    => '',
					'fontSize'      => [ 'desktop' => 0 ],
					'fontWeight'    => '',
					'lineHeight'    => '',
					'letterSpacing' => '',
					'textTransform' => '',
					'color'         => '',
					'hoverColor'    => '',
				],
			],
			'answerTypography' => [
				'type'    => 'object',
				'default' => [
					'fontFamily'    => '',
					'fontSize'      => [ 'desktop' => 0 ],
					'fontWeight'    => '',
					'lineHeight'    => '',
					'letterSpacing' => '',
					'textTransform' => '',
					'color'         => '',
					'hoverColor'    => '',
				],
			],
		];
	}

	private static function question_style_attributes(): array {
		$bg_default = [ 'type' => 'solid', 'color' => '', 'gradient' => '' ];

		return [
			'questionBackground'       => [ 'type' => 'object', 'default' => $bg_default ],
			'questionActiveColor'      => [ 'type' => 'string', 'default' => '' ],
			'questionActiveBackground' => [ 'type' => 'object', 'default' => $bg_default ],
		];
	}

	private static function answer_style_attributes(): array {
		return [
			'answerBackground' => [ 'type' => 'object', 'default' => [ 'type' => 'solid', 'color' => '', 'gradient' => '' ] ],
		];
	}

	private static function container_style_attributes(): array {
		return [
			'containerBackground' => [ 'type' => 'object', 'default' => [ 'type' => 'solid', 'color' => '', 'gradient' => '' ] ],
			'containerBorder'     => [ 'type' => 'object', 'default' => [ 'color' => '#e5e7eb', 'width' => 1, 'radius' => 8, 'style' => 'solid' ] ],
			'containerBoxShadow'  => [ 'type' => 'object', 'default' => [ 'enabled' => false, 'x' => 0, 'y' => 1, 'blur' => 3, 'spread' => 0, 'color' => 'rgba(0,0,0,0.08)', 'inset' => false ] ],
		];
	}

	private static function item_style_attributes(): array {
		return [
			'itemBorder'          => [ 'type' => 'object', 'default' => [ 'color' => '#e5e7eb', 'width' => 1, 'radius' => 8, 'style' => 'solid' ] ],
			'itemHoverBoxShadow'  => [ 'type' => 'object', 'default' => [ 'enabled' => false, 'x' => 0, 'y' => 1, 'blur' => 4, 'spread' => 0, 'color' => 'rgba(0,0,0,0.06)', 'inset' => false ] ],
			'itemActiveBackground'=> [ 'type' => 'object', 'default' => [ 'type' => 'solid', 'color' => '', 'gradient' => '' ] ],
		];
	}

	private static function divider_attributes(): array {
		return [
			'enableDivider' => [ 'type' => 'boolean', 'default' => true ],
			'dividerColor'  => [ 'type' => 'string',  'default' => '#e5e7eb' ],
			'dividerWidth'  => [ 'type' => 'number',  'default' => 1 ],
			'dividerStyle'  => [ 'type' => 'string',  'default' => 'solid' ],
		];
	}

	/**
	 * Legacy attributes kept for backward compatibility and migration detection.
	 */
	private static function legacy_attributes(): array {
		return [
			'iconStyle'            => [ 'type' => 'string',  'default' => 'plus-minus' ],
			'questionColor'        => [ 'type' => 'string',  'default' => '' ],
			'questionBgColor'      => [ 'type' => 'string',  'default' => '' ],
			'questionActiveBgColor'=> [ 'type' => 'string',  'default' => '' ],
			'questionFontWeight'   => [ 'type' => 'string',  'default' => '600' ],
			'answerColor'          => [ 'type' => 'string',  'default' => '' ],
			'answerBgColor'        => [ 'type' => 'string',  'default' => '' ],
			'containerBgColor'     => [ 'type' => 'string',  'default' => '' ],
			'borderColor'          => [ 'type' => 'string',  'default' => '' ],
			'borderWidth'          => [ 'type' => 'number',  'default' => 1 ],
			'borderRadius'         => [ 'type' => 'number',  'default' => 8 ],
			'gap'                  => [ 'type' => 'number',  'default' => 12 ],
			'questionPaddingVal'   => [ 'type' => 'number',  'default' => 16 ],
			'answerPaddingVal'     => [ 'type' => 'number',  'default' => 16 ],
			'questionFontSize'     => [ 'type' => 'number',  'default' => 0 ],
			'answerFontSize'       => [ 'type' => 'number',  'default' => 0 ],
		];
	}
}
