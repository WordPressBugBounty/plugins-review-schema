<?php

namespace Rtrs\Modules\Review\Elementor\Widgets;

use Rtrs\Modules\Review\Elementor\WidgetBase;
use Rtrs\Modules\Review\Shortcodes\ShortcodesInit;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Average Rating Count Elementor widget.
 *
 * Displays the average rating count for a post.
 */
class AverageRatingCount extends WidgetBase {

	/**
	 * Get widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'rtrs-average-rating-count';
	}

	/**
	 * Get widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Average Rating Count', 'review-schema' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string Widget icon CSS class.
	 */
	public function get_icon() {
		return 'eicon-counter';
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_rating_stars_content_controls();
		$this->register_style_controls();
	}

	/**
	 * Register style controls for the widget.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		// Count Style.
		$this->start_controls_section(
			'count_style_section',
			[
				'label' => esc_html__( 'Count Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_text_style_controls(
			'count',
			'{{WRAPPER}} .rtrs .shortcode-rtrs-rating-count',
			[
				'bg_color'  => true,
				'alignment' => true,
				'padding'   => true,
			]
		);

		$this->end_controls_section();

		// Wrapper Style.
		$this->register_wrapper_style_section(
			'wrapper',
			esc_html__( 'Wrapper Style', 'review-schema' ),
			'{{WRAPPER}} .rtrs'
		);
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * @return void
	 */
	protected function render() {
		if ( ! $this->check_review_support() ) {
			return;
		}

		$this->enqueue_review_assets();

		$settings = $this->get_settings_for_display();
		$atts     = [];

		if ( ! empty( $settings['post_id'] ) ) {
			$atts['post_id'] = absint( $settings['post_id'] );
		}
		if ( ! empty( $settings['hide_for_no_rating'] ) && 'yes' === $settings['hide_for_no_rating'] ) {
			$atts['hide_for_no_rating'] = 'hide';
		}

		$output = ShortcodesInit::average_rating_count( $atts );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::kses_shortcode_output() runs wp_kses() with an allow-list before returning the markup.
		echo self::kses_shortcode_output( $output );
	}
}