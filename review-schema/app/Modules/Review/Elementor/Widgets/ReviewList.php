<?php

namespace Rtrs\Modules\Review\Elementor\Widgets;

use Rtrs\Modules\Review\Elementor\WidgetBase;
use Rtrs\Modules\Review\Elementor\Controls\ReviewListControls;
use Rtrs\Modules\Review\Shortcodes\ShortcodesInit;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review List Elementor widget.
 *
 * Displays the review list for the current post.
 */
class ReviewList extends WidgetBase {

	use ReviewListControls;

	/**
	 * Get widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'rtrs-review-list';
	}

	/**
	 * Get widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Review List', 'review-schema' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string Widget icon CSS class.
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	/**
	 * Register style controls for the widget.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$wrapper = '{{WRAPPER}}';

		$this->register_heading_style_section( $wrapper );
		$this->register_review_item_style_section( $wrapper );

		$this->register_wrapper_style_section(
			'wrapper',
			esc_html__( 'Wrapper Style', 'review-schema' ),
			'{{WRAPPER}} .rtrs-review-box'
		);

		do_action( 'rtrs_elementor_review_list_style_controls', $this, $wrapper );
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

		$output = ShortcodesInit::review_list( [] );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::kses_shortcode_output() runs wp_kses() with an allow-list before returning the markup.
		echo self::kses_shortcode_output( $output );
	}
}