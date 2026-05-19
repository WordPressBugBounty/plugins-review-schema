<?php

namespace Rtrs\Modules\Review\Elementor;

use Rtrs\Helpers\Functions;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Background;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WidgetBase extends Widget_Base {

	public function get_categories() {
		return [ 'rtrs-review-schema' ];
	}

	public function get_keywords() {
		return [ 'review', 'rating', 'schema' ];
	}

	/**
	 * Check if review is supported for the current post type.
	 *
	 * @return bool
	 */
	protected function check_review_support() {
		if ( ! Functions::review_enabled() ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div style="padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;color:#856404;">%s</div>',
					esc_html__( 'Reviews are globally disabled. Enable them in SchemaEngine AI > Settings.', 'review-schema' )
				);
			}
			return false;
		}

		if ( ! Functions::isEnableReviewByPostType( get_post_type() ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div style="padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;color:#856404;">%s</div>',
					esc_html__( 'Reviews are not enabled for this post type.', 'review-schema' )
				);
			}
			return false;
		}

		return true;
	}

	protected function enqueue_review_assets() {
		wp_enqueue_style( 'rtrs-app' );
		wp_enqueue_script( 'rtrs-app' );
	}

	/**
	 * Get allowed HTML tags for shortcode output escaping.
	 *
	 * Extends wp_kses_post allowed tags with form elements, progress,
	 * and script tags needed by review shortcode output.
	 *
	 * @return array Allowed HTML tags and their attributes.
	 */
	protected static function get_kses_allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		// Form elements needed by review form and review list filter.
		$allowed['form']     = [
			'action'         => true,
			'method'         => true,
			'class'          => true,
			'id'             => true,
			'enctype'        => true,
			'novalidate'     => true,
			'data-*'         => true,
		];
		$allowed['input']    = [
			'type'           => true,
			'name'           => true,
			'value'          => true,
			'placeholder'    => true,
			'class'          => true,
			'id'             => true,
			'required'       => true,
			'checked'        => true,
			'disabled'       => true,
			'readonly'       => true,
			'maxlength'      => true,
			'min'            => true,
			'max'            => true,
			'step'           => true,
			'data-*'         => true,
			'aria-*'         => true,
			'style'          => true,
			'hidden'         => true,
			'autocomplete'   => true,
		];
		$allowed['textarea'] = [
			'name'           => true,
			'rows'           => true,
			'cols'           => true,
			'class'          => true,
			'id'             => true,
			'placeholder'    => true,
			'required'       => true,
			'readonly'       => true,
			'maxlength'      => true,
			'data-*'         => true,
			'aria-*'         => true,
			'style'          => true,
		];
		$allowed['select']   = [
			'name'           => true,
			'class'          => true,
			'id'             => true,
			'multiple'       => true,
			'data-*'         => true,
			'aria-*'         => true,
			'style'          => true,
		];
		$allowed['option']   = [
			'value'          => true,
			'selected'       => true,
			'disabled'       => true,
		];
		$allowed['optgroup'] = [
			'label'          => true,
			'disabled'       => true,
		];
		$allowed['button']   = [
			'type'           => true,
			'name'           => true,
			'value'          => true,
			'class'          => true,
			'id'             => true,
			'disabled'       => true,
			'data-*'         => true,
			'aria-*'         => true,
			'style'          => true,
		];

		// Progress element needed by review summary.
		$allowed['progress'] = [
			'value'          => true,
			'max'            => true,
			'class'          => true,
			'id'             => true,
			'style'          => true,
		];

		// Script element needed by review form inline JS.
		$allowed['script']   = [
			'type'           => true,
		];

		return $allowed;
	}

	/**
	 * Escape shortcode HTML output using an extended set of allowed tags.
	 *
	 * Uses wp_kses with additional form, progress, and script tags
	 * required by review shortcode output.
	 *
	 * @param string $output The shortcode HTML output.
	 * @return string Escaped HTML.
	 */
	protected static function kses_shortcode_output( $output ) {
		return wp_kses( $output, self::get_kses_allowed_html() ); // escaped
	}

	/**
	 * Add text style controls: Typography + Color + optional bg_color, alignment, padding, margin.
	 *
	 * Call between start_controls_section() and end_controls_section().
	 *
	 * @param string $prefix   Unique control ID prefix.
	 * @param string $selector CSS selector for the text element.
	 * @param array  $args     Optional flags: bg_color, alignment, alignment_selector, padding, margin.
	 */
	protected function add_text_style_controls( $prefix, $selector, $args = [] ) {
		$args = wp_parse_args( $args, [
			'bg_color'           => false,
			'alignment'          => false,
			'alignment_selector' => $selector,
			'padding'            => false,
			'margin'             => false,
		] );

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => $prefix . '_typography',
				'label'    => esc_html__( 'Typography', 'review-schema' ),
				'selector' => $selector,
			]
		);

		$this->add_control(
			$prefix . '_color',
			[
				'label'     => esc_html__( 'Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$selector => 'color: {{VALUE}};',
				],
			]
		);

		if ( $args['bg_color'] ) {
			$this->add_control(
				$prefix . '_bg_color',
				[
					'label'     => esc_html__( 'Background Color', 'review-schema' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [
						$selector => 'background-color: {{VALUE}};',
					],
				]
			);
		}

		if ( $args['alignment'] ) {
			$this->add_responsive_control(
				$prefix . '_alignment',
				[
					'label'     => esc_html__( 'Alignment', 'review-schema' ),
					'type'      => Controls_Manager::CHOOSE,
					'options'   => [
						'left'   => [
							'title' => esc_html__( 'Left', 'review-schema' ),
							'icon'  => 'eicon-text-align-left',
						],
						'center' => [
							'title' => esc_html__( 'Center', 'review-schema' ),
							'icon'  => 'eicon-text-align-center',
						],
						'right'  => [
							'title' => esc_html__( 'Right', 'review-schema' ),
							'icon'  => 'eicon-text-align-right',
						],
					],
					'selectors' => [
						$args['alignment_selector'] => 'text-align: {{VALUE}};',
					],
				]
			);
		}

		if ( $args['padding'] ) {
			$this->add_responsive_control(
				$prefix . '_padding',
				[
					'label'      => esc_html__( 'Padding', 'review-schema' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px', 'em' ],
					'selectors'  => [
						$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
		}

		if ( $args['margin'] ) {
			$this->add_responsive_control(
				$prefix . '_margin',
				[
					'label'      => esc_html__( 'Margin', 'review-schema' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px', 'em' ],
					'selectors'  => [
						$selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
		}
	}

	/**
	 * Add star style controls: Color + optional empty color, size, gap, alignment.
	 *
	 * Call between start_controls_section() and end_controls_section().
	 *
	 * @param string $prefix        Unique control ID prefix.
	 * @param string $icon_selector CSS selector for the star <i> elements.
	 * @param array  $args          Optional flags: empty_color, empty_selector, size, gap, alignment, alignment_selector.
	 */
	protected function add_star_style_controls( $prefix, $icon_selector, $args = [] ) {
		$args = wp_parse_args( $args, [
			'empty_color'        => false,
			'empty_selector'     => str_replace( ' i', ' .rtrs-star-empty', $icon_selector ),
			'size'               => true,
			'gap'                => false,
			'alignment'          => false,
			'alignment_selector' => '',
		] );

		$this->add_control(
			$prefix . '_color',
			[
				'label'     => esc_html__( 'Star Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$icon_selector => 'color: {{VALUE}};',
				],
			]
		);

		if ( $args['empty_color'] ) {
			$this->add_control(
				$prefix . '_empty_color',
				[
					'label'     => esc_html__( 'Empty Star Color', 'review-schema' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [
						$args['empty_selector'] => 'color: {{VALUE}};',
					],
				]
			);
		}

		if ( $args['size'] ) {
			$this->add_responsive_control(
				$prefix . '_size',
				[
					'label'      => esc_html__( 'Star Size', 'review-schema' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => [ 'px', 'em' ],
					'range'      => [
						'px' => [
							'min' => 8,
							'max' => 60,
						],
					],
					'selectors'  => [
						$icon_selector => 'font-size: {{SIZE}}{{UNIT}};',
					],
				]
			);
		}

		if ( $args['gap'] ) {
			$this->add_responsive_control(
				$prefix . '_gap',
				[
					'label'      => esc_html__( 'Star Gap', 'review-schema' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => [ 'px' ],
					'range'      => [
						'px' => [
							'min' => 0,
							'max' => 20,
						],
					],
					'selectors'  => [
						$icon_selector => 'margin-right: {{SIZE}}{{UNIT}};',
					],
				]
			);
		}

		if ( $args['alignment'] && ! empty( $args['alignment_selector'] ) ) {
			$this->add_responsive_control(
				$prefix . '_alignment',
				[
					'label'     => esc_html__( 'Alignment', 'review-schema' ),
					'type'      => Controls_Manager::CHOOSE,
					'options'   => [
						'left'   => [
							'title' => esc_html__( 'Left', 'review-schema' ),
							'icon'  => 'eicon-text-align-left',
						],
						'center' => [
							'title' => esc_html__( 'Center', 'review-schema' ),
							'icon'  => 'eicon-text-align-center',
						],
						'right'  => [
							'title' => esc_html__( 'Right', 'review-schema' ),
							'icon'  => 'eicon-text-align-right',
						],
					],
					'selectors' => [
						$args['alignment_selector'] => 'text-align: {{VALUE}};',
					],
				]
			);
		}
	}


	/**
	 * Register a button style section with Normal/Hover tabs.
	 *
	 * @param string $prefix   Unique control ID prefix.
	 * @param string $title    Section title.
	 * @param string $selector CSS selector for the button element.
	 */
	protected function register_button_style_section( $prefix, $title, $selector ) {
		$hover_selector = self::hover_selector( $selector );

		$this->start_controls_section(
			$prefix . '_style_section',
			[
				'label' => $title,
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => $prefix . '_typography',
				'label'    => esc_html__( 'Typography', 'review-schema' ),
				'selector' => $selector,
			]
		);

		$this->start_controls_tabs( $prefix . '_color_tabs' );

		$this->start_controls_tab(
			$prefix . '_normal_tab',
			[
				'label' => esc_html__( 'Normal', 'review-schema' ),
			]
		);

		$this->add_control(
			$prefix . '_color',
			[
				'label'     => esc_html__( 'Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix . '_bg_color',
			[
				'label'     => esc_html__( 'Background Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			$prefix . '_hover_tab',
			[
				'label' => esc_html__( 'Hover', 'review-schema' ),
			]
		);

		$this->add_control(
			$prefix . '_hover_color',
			[
				'label'     => esc_html__( 'Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$hover_selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix . '_hover_bg_color',
			[
				'label'     => esc_html__( 'Background Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$hover_selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'      => $prefix . '_border',
				'label'     => esc_html__( 'Border', 'review-schema' ),
				'selector'  => $selector,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			$prefix . '_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			$prefix . '_padding',
			[
				'label'      => esc_html__( 'Padding', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register wrapper style section (background, border, border-radius, padding, margin).
	 *
	 * @param string $prefix   Unique control ID prefix.
	 * @param string $title    Section title.
	 * @param string $selector CSS selector for the wrapper element.
	 */
	protected function register_wrapper_style_section( $prefix, $title, $selector ) {
		$this->start_controls_section(
			$prefix . '_style_section',
			[
				'label' => $title,
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'     => $prefix . '_background',
				'label'    => esc_html__( 'Background', 'review-schema' ),
				'types'    => [ 'classic', 'gradient' ],
				'selector' => $selector,
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => $prefix . '_border',
				'label'    => esc_html__( 'Border', 'review-schema' ),
				'selector' => $selector,
			]
		);

		$this->add_responsive_control(
			$prefix . '_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			$prefix . '_padding',
			[
				'label'      => esc_html__( 'Padding', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			$prefix . '_margin',
			[
				'label'      => esc_html__( 'Margin', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					$selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}
	protected function register_content_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Info', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'info_notice',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'This widget displays the review list for the current post. No additional configuration is needed.', 'review-schema' ),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->end_controls_section();
	}
	protected function register_rating_stars_content_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Settings', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'post_id',
			[
				'label'       => esc_html__( 'Post ID', 'review-schema' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => '',
				'description' => esc_html__( 'Leave empty to use the current post.', 'review-schema' ),
			]
		);

		$this->add_control(
			'hide_for_no_rating',
			[
				'label'        => esc_html__( 'Hide If No Rating', 'review-schema' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'review-schema' ),
				'label_off'    => esc_html__( 'No', 'review-schema' ),
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->end_controls_section();
	}
	/**
	 * Append :hover to each part of a comma-separated selector.
	 *
	 * @param string $selector CSS selector (may be comma-separated).
	 * @return string
	 */
	protected static function hover_selector( $selector ) {
		$parts = array_map( 'trim', explode( ',', $selector ) );

		return implode( ', ', array_map( function ( $s ) {
			return $s . ':hover';
		}, $parts ) );
	}
}
