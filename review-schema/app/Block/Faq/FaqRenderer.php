<?php
/**
 * FAQ Block Renderer
 *
 * Renders the FAQ parent block container.
 * Child blocks are static (save output used directly).
 *
 * @package Rtrs\Block\Faq
 */

namespace Rtrs\Block\Faq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqRenderer {

	/**
	 * Render the parent FAQ container.
	 */
	public static function render( $attributes, $content, $block ) {
		$defaults = [];
		foreach ( FaqAttributes::get() as $key => $config ) {
			$defaults[ $key ] = $config['default'] ?? '';
		}
		$attrs  = wp_parse_args( $attributes, $defaults );
		$layout = sanitize_text_field( $attrs['layout'] );

		// Strip FAQ items with empty question and answer before rendering.
		$content = self::strip_empty_faq_items( $content );

		if ( empty( trim( $content ) ) ) {
			return '';
		}

		$first_open = ! empty( $attrs['firstOpen'] );

		// Mark first item as active for accordion layout only.
		if ( $first_open && 'accordion' === $layout ) {
			$content = preg_replace(
				'/class="([^"]*\brtrs-faq-item\b[^"]*)"/',
				'class="$1 active"',
				$content,
				1
			);
			$content = preg_replace(
				'/class="rtrs-faq-answer"/',
				'class="rtrs-faq-answer" style="max-height:none;"',
				$content,
				1
			);
		}

		$block_id       = ! empty( $attrs['anchor'] ) ? sanitize_html_class( $attrs['anchor'] ) : 'rtrs-faq-' . wp_unique_id();
		$css_style      = FaqCssVars::build( $attrs, $block_id );
		$layout_class   = 'rtrs-faq-layout--' . $layout;
		$cond_classes   = FaqCssVars::conditional_classes( $attrs );
		$data_attrs     = self::build_data_attrs( $attrs, $layout );

		$block_classes = 'rtrs-faq-block ' . $layout_class;
		if ( $cond_classes ) {
			$block_classes .= ' ' . $cond_classes;
		}

		$wrapper_attrs = get_block_wrapper_attributes( [
			'id'    => $block_id,
			'class' => $block_classes,
		] );

		// Build HTML
		$html = $css_style;
		$html .= '<div ' . $wrapper_attrs . ' data-layout="' . esc_attr( $layout ) . '"' . $data_attrs . '>';

		// Search bar
		if ( ! empty( $attrs['enableSearch'] ) ) {
			$html .= self::render_search_bar( $attrs );
		}

		// Open/Close All buttons (accordion only)
		if ( ! empty( $attrs['enableOpenCloseAll'] ) && 'accordion' === $layout ) {
			$html .= self::render_open_close_all();
		}

		$html .= $content;
		$html .= '</div>';

		return $html;
	}

	// ── Private helpers ──

	/**
	 * Remove FAQ items that have no question text and no meaningful answer content.
	 *
	 * @param string $content Block inner HTML.
	 * @return string Filtered content.
	 */
	private static function strip_empty_faq_items( string $content ): string {
		if ( empty( $content ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<div[^>]*\brtrs-faq-item\b[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>/s',
			function ( $match ) {
				$html = $match[0];

				// Extract question text.
				$question = '';
				if ( preg_match( '/class="[^"]*rtrs-faq-question-text[^"]*"[^>]*>(.*?)<\/h[1-6]>/s', $html, $q ) ) {
					$question = trim( wp_strip_all_tags( $q[1] ) );
				}

				// Extract answer text.
				$answer = '';
				if ( preg_match( '/class="[^"]*rtrs-faq-answer-inner[^"]*"[^>]*>(.*?)<\/div>/s', $html, $a ) ) {
					$answer = trim( wp_strip_all_tags( $a[1] ) );
				}

				// Remove the item if both question and answer are empty.
				if ( empty( $question ) && empty( $answer ) ) {
					return '';
				}

				return $html;
			},
			$content
		);
	}

	private static function build_data_attrs( array $attrs, string $layout ): string {
		$data = '';

		$data .= ' data-expand-icon="' . esc_attr( $attrs['expandIcon'] ?? 'plus' ) . '"';
		$data .= ' data-collapse-icon="' . esc_attr( $attrs['collapseIcon'] ?? 'minus' ) . '"';
		$data .= ' data-icon-position="' . esc_attr( $attrs['iconPosition'] ?? 'right' ) . '"';

		if ( 'accordion' === $layout ) {
			$data .= ' data-max-expanded="' . intval( $attrs['maxExpanded'] ?? 0 ) . '"';
			$data .= ' data-multiple-open="' . ( ! empty( $attrs['multipleOpen'] ) ? 'true' : 'false' ) . '"';
			$data .= ' data-auto-close="' . intval( $attrs['autoCloseSeconds'] ?? 0 ) . '"';
		}

		if ( ! empty( $attrs['enableSearch'] ) ) {
			$data .= ' data-search="1"';
		}

		if ( ! empty( $attrs['enableOpenCloseAll'] ) ) {
			$data .= ' data-open-close-all="1"';
		}

		return $data;
	}

	private static function render_search_bar( array $attrs ): string {
		$placeholder = esc_attr( $attrs['searchPlaceholder'] ?? 'Search FAQs...' );
		return '<div class="rtrs-faq-search">'
			. '<input type="text" class="rtrs-faq-search-input" placeholder="' . $placeholder . '" aria-label="' . $placeholder . '">'
			. '<button type="button" class="rtrs-faq-search-clear" aria-label="' . esc_attr__( 'Clear search', 'review-schema' ) . '">'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
			. '</button>'
			. '</div>';
	}

	private static function render_open_close_all(): string {
		return '<div class="rtrs-faq-open-close-all">'
			. '<button type="button" class="rtrs-faq-toggle-all" data-expand-text="' . esc_attr__( 'Expand All', 'review-schema' ) . '" data-collapse-text="' . esc_attr__( 'Collapse All', 'review-schema' ) . '">'
			. esc_html__( 'Expand All', 'review-schema' )
			. '</button>'
			. '</div>';
	}
}
