<?php

namespace Rtrs\Modules\Review\Elementor\Controls;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ReviewFormControls {

	/**
	 * Form Style section: heading, label, input typography/colors/borders.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_form_style_section( $wrapper ) {
		$input_selector = $wrapper . ' input:not([type="submit"]), ' . $wrapper . ' textarea, ' . $wrapper . ' select';

		$this->start_controls_section(
			'form_style_section',
			[
				'label' => esc_html__( 'Form Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'form_title_heading',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<h3 style="margin:0;font-size:14px;font-weight:500;color:#000">' . esc_html__( 'Heading', 'review-schema' ) . '</h3>',
			]
		);

		$this->add_text_style_controls( 'form_title', $wrapper . ' .rtrs-form-title', [ 'margin' => true ] );

		$this->add_control(
			'label_heading',
			[
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => '<h3 style="margin:0;font-size:16px;font-weight:500;color:#000"">' . esc_html__( 'Label', 'review-schema' ) . '</h3>',
				'separator' => 'before',
			]
		);

		$this->add_text_style_controls( 'label', $wrapper . ' label:not(.rtrs-rating-container label), ' . $wrapper . ' .rtrs-review-form .rtrs-rating-category .rtrs-category-text' );

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'      => 'input_typography',
				'label'     => esc_html__( 'Input Typography', 'review-schema' ),
				'selector'  => $input_selector,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'input_color',
			[
				'label'     => esc_html__( 'Input Text Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$input_selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'input_bg_color',
			[
				'label'     => esc_html__( 'Input Background', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$input_selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'input_border',
				'label'    => esc_html__( 'Input Border', 'review-schema' ),
				'selector' => $input_selector,
			]
		);

		$this->add_responsive_control(
			'input_border_radius',
			[
				'label'      => esc_html__( 'Input Border Radius', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					$input_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Rating Style section: color, margin.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_rating_style_section( $wrapper ) {
		$this->start_controls_section(
			'rating_style_section',
			[
				'label' => esc_html__( 'Rating Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'rating_style_color',
			[
				'label'     => esc_html__( 'Rating Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-rating-container label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'rating_style_margin',
			[
				'label'      => esc_html__( 'Margin', 'review-schema' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					$wrapper . ' .rtrs-rating-container' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}
}
