<?php

namespace Rtrs\Modules\Review\Elementor\Widgets;

use Rtrs\Modules\Review\Elementor\WidgetBase;
use Rtrs\Modules\Review\Elementor\Controls\ReviewSummaryControls;
use Rtrs\Modules\Review\Shortcodes\ShortcodesInit;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review Summary Elementor widget.
 *
 * Displays the review summary (rating, stars, progress bars) for the current post.
 */
class ReviewSummary extends WidgetBase {

	use ReviewSummaryControls;

	/**
	 * Get widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'rtrs-review-summary';
	}

	/**
	 * Get widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Review Summary', 'review-schema' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string Widget icon CSS class.
	 */
	public function get_icon() {
		return 'eicon-info-box';
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
		$wrapper = '{{WRAPPER}} .rtrs-review-wrap';

		$this->register_rating_number_style_section( $wrapper );
		$this->register_star_style_section( $wrapper );
		$this->register_progress_bar_style_section( $wrapper );

		$this->register_wrapper_style_section(
			'wrapper',
			esc_html__( 'Wrapper Style', 'review-schema' ),
			'{{WRAPPER}} .rtrs-rating-box'
		);

		do_action( 'rtrs_elementor_review_summary_style_controls', $this, $wrapper );
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

		$output = ShortcodesInit::review_summary();

		if ( empty( $output ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			printf(
				'<div style="padding:10px;background:#d1ecf1;border:1px solid #bee5eb;border-radius:4px;color:#0c5460;">%s</div>',
				esc_html__( 'No reviews found for this post. The summary will appear once reviews are submitted.', 'review-schema' )
			);
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::kses_shortcode_output() runs wp_kses() with an allow-list before returning the markup.
		echo self::kses_shortcode_output( $output );
	}
}