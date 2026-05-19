<?php

namespace Rtrs\Modules\Review\Elementor\Controls;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ReviewListControls {

	/**
	 * Heading Style section.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_heading_style_section( $wrapper ) {
		$this->start_controls_section(
			'heading_style_section',
			[
				'label' => esc_html__( 'Heading Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_text_style_controls( 'heading', $wrapper . ' .rtrs-sorting-bar,' . $wrapper . ' .rtrs-sorting-title', [ 'padding' => true, 'bg_color' => true ] );

		$this->end_controls_section();
	}

	/**
	 * Review Item Style section.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_review_item_style_section( $wrapper ) {
		$this->start_controls_section(
			'review_item_style_section',
			[
				'label' => esc_html__( 'Review Item Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_text_style_controls( 'review_title', $wrapper . ' .rtrs-review-title' );

		$this->add_star_style_controls(
			'review_star',
			$wrapper . ' .rtrs-review-rating i',
			[
				'size'      => false,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'review_text_color',
			[
				'label'     => esc_html__( 'Text Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-review-body p' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'review_meta_color',
			[
				'label'     => esc_html__( 'Meta Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-review-meta li' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'review_item_padding',
			[
				'label'      => esc_html__( 'Item Padding', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					$wrapper . ' .rtrs-each-review' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator'  => 'before',
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'review_item_border',
				'label'    => esc_html__( 'Border', 'review-schema' ),
				'selector' => $wrapper . ' .rtrs-each-review',
			]
		);

		$this->add_responsive_control(
			'review_item_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					$wrapper . ' .rtrs-each-review' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'review_item_bg_color',
			[
				'label'     => esc_html__( 'Item Background', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-each-review' => 'background-color: {{VALUE}};',
				],
			]
		);
		$this->add_responsive_control(
			'review_item_margin',
			[
				'label'      => esc_html__( 'Margin', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					$wrapper . ' .rtrs-each-review' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$btn_selector       = $wrapper . ' .rtrs-item-btn, ' . $wrapper . ' .rtrs-review-helpful';
		$btn_hover_selector = $wrapper . ' .rtrs-item-btn:hover, ' . $wrapper . ' .rtrs-review-helpful:hover';

		$this->add_control(
			'review_btn_heading',
			[
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => '<h3 style="margin:0;font-size:14px;font-weight:500;color:#000">' . esc_html__( 'Button', 'review-schema' ) . '</h3>',
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'review_btn_typography',
				'label'    => esc_html__( 'Typography', 'review-schema' ),
				'selector' => $btn_selector,
			]
		);

		$this->start_controls_tabs( 'review_btn_color_tabs' );

		$this->start_controls_tab(
			'review_btn_normal_tab',
			[
				'label' => esc_html__( 'Normal', 'review-schema' ),
			]
		);

		$this->add_control(
			'review_btn_color',
			[
				'label'     => esc_html__( 'Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$btn_selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'review_btn_bg_color',
			[
				'label'     => esc_html__( 'Background Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$btn_selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'review_btn_hover_tab',
			[
				'label' => esc_html__( 'Hover', 'review-schema' ),
			]
		);

		$this->add_control(
			'review_btn_hover_color',
			[
				'label'     => esc_html__( 'Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$btn_hover_selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'review_btn_hover_bg_color',
			[
				'label'     => esc_html__( 'Background Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$btn_hover_selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'      => 'review_btn_border',
				'label'     => esc_html__( 'Border', 'review-schema' ),
				'selector'  => $btn_selector,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'review_btn_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					$btn_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'review_btn_padding',
			[
				'label'      => esc_html__( 'Padding', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					$btn_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}
}