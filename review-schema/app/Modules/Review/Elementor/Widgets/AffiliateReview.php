<?php

namespace Rtrs\Modules\Review\Elementor\Widgets;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Elementor\WidgetBase;
use Rtrs\Modules\Review\Shortcodes\ShortcodesInit;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Affiliate Review Elementor widget.
 *
 * Displays an affiliate review post using a selected layout.
 */
class AffiliateReview extends WidgetBase {

	/**
	 * Get widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'rtrs-affiliate-review';
	}

	/**
	 * Get widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Affiliate Review', 'review-schema' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string Widget icon CSS class.
	 */
	public function get_icon() {
		return 'eicon-review';
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_affiliate_content_controls();
		$this->register_style_controls();
	}

	/**
	 * Register content controls for affiliate selection.
	 *
	 * @return void
	 */
	private function register_affiliate_content_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Settings', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$options = [];
		$posts   = get_posts( [
			'post_type'      => rtrs()->getPostTypeAffiliate(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		] );
		foreach ( $posts as $post ) {
			$options[ $post->ID ] = esc_html( $post->post_title );
		}

		$this->add_control(
			'affiliate_id',
			[
				'label'       => esc_html__( 'Select Affiliate', 'review-schema' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $options,
				'default'     => '',
				'label_block' => true,
			]
		);

		$this->add_control(
			'title',
			[
				'label'   => esc_html__( 'Title', 'review-schema' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register style controls for the widget.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$wrapper = '{{WRAPPER}} .rtrs .rtrs-review-wrap';

		$this->start_controls_section(
			'title_style_section',
			[
				'label' => esc_html__( 'Title Style', 'review-schema' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_text_style_controls( 'title', $wrapper . ' .rtrs-title h3', [ 'margin' => true ] );

		$this->end_controls_section();

		$this->register_button_style_section(
			'button',
			esc_html__( 'Button Style', 'review-schema' ),
			$wrapper . ' .rtrs-buy-btn'
		);

		$this->register_wrapper_style_section(
			'wrapper',
			esc_html__( 'Wrapper Style', 'review-schema' ),
			$wrapper
		);
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * @return void
	 */
	protected function render() {
		if ( ! Functions::affiliate_enabled() ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div style="padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;color:#856404;">%s</div>',
					esc_html__( 'Affiliate reviews are disabled. Enable them in SchemaEngine AI > Settings.', 'review-schema' )
				);
			}
			return;
		}

		$settings = $this->get_settings_for_display();
		$atts     = [];

		if ( ! empty( $settings['affiliate_id'] ) ) {
			$atts['id'] = absint( $settings['affiliate_id'] );
		}
		if ( ! empty( $settings['title'] ) ) {
			$atts['title'] = sanitize_text_field( $settings['title'] );
		}

		$output = ShortcodesInit::review_schema( $atts );

		if ( empty( $output ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			printf(
				'<div style="padding:10px;background:#d1ecf1;border:1px solid #bee5eb;border-radius:4px;color:#0c5460;">%s</div>',
				esc_html__( 'Select an affiliate post to display.', 'review-schema' )
			);
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::kses_shortcode_output() runs wp_kses() with an allow-list before returning the markup.
		echo self::kses_shortcode_output( $output );
	}
}
