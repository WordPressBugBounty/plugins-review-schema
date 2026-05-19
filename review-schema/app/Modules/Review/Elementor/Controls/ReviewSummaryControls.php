<?php

namespace Rtrs\Modules\Review\Elementor\Controls;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ReviewSummaryControls {

	/**
	 * Rating Number Style section.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_rating_number_style_section( $wrapper ) {
		$this->start_controls_section(
			'rating_number_style_section',
			[
				'label' => esc_html__( 'Rating Number Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_text_style_controls( 'rating_number', $wrapper . ' .rtrs-rating-number .rtrs-rating' );

		$this->add_control(
			'rating_out_color',
			[
				'label'     => esc_html__( 'Out-of Color (/5)', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-rating-number .rtrs-rating-out' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Star Style section.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_star_style_section( $wrapper ) {
		$this->start_controls_section(
			'star_style_section',
			[
				'label' => esc_html__( 'Star Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_star_style_controls( 'star', $wrapper . ' .rtrs-rating-icon i' );

		$this->add_control(
			'rating_text_color',
			[
				'label'     => esc_html__( 'Rating Text Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-rating-text' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Progress Bar Style section.
	 *
	 * @param string $wrapper Base wrapper selector.
	 */
	private function register_progress_bar_style_section( $wrapper ) {
		$this->start_controls_section(
			'progress_style_section',
			[
				'label' => esc_html__( 'Progress Bar Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'progress_bar_color',
			[
				'label'     => esc_html__( 'Bar Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-progress-bar::-webkit-progress-value' => 'background-color: {{VALUE}};',
					$wrapper . ' .rtrs-progress-bar::-moz-progress-bar'      => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'progress_bg_color',
			[
				'label'     => esc_html__( 'Bar Background', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-progress-bar'                       => 'background-color: {{VALUE}};',
					$wrapper . ' .rtrs-progress-bar::-webkit-progress-bar' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'progress_label_color',
			[
				'label'     => esc_html__( 'Label Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .rtrs-progress label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'progress_percent_color',
			[
				'label'     => esc_html__( 'Percent Color', 'review-schema' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$wrapper . ' .progress-percent' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}
}
