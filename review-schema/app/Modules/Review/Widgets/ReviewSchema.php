<?php

namespace Rtrs\Modules\Review\Widgets;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewSchema extends \WP_Widget {

	protected $widget_slug;

	public function __construct() {

		$this->widget_slug = 'rtrs-widget-review';

		parent::__construct(
			$this->widget_slug,
			esc_html__( 'Affiliate', 'review-schema' ),
			[
				'classname'   => 'rtrs ' . $this->widget_slug,
				'description' => esc_html__( 'A list of Affiliate.', 'review-schema' ),
			]
		);
	}

	public function widget( $args, $instance ) {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP-supplied widget wrapper markup.
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			$rtrs_widget_title = apply_filters( 'widget_title', wp_kses( $instance['title'], [ 'span' => [] ] ) );
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $args['before_title']/$args['after_title'] are WP-supplied wrapper markup; $rtrs_widget_title is run through wp_kses + widget_title filter.
			echo $args['before_title'] . $rtrs_widget_title . $args['after_title'];
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( $instance['shortcode_id'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_shortcode() output is rendered HTML produced by the registered shortcode callback.
			echo do_shortcode( '[rtrs-affiliate id="' . absint( $instance['shortcode_id'] ) . '"]' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP-supplied widget wrapper markup.
		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		$instance['title']        = ! empty( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['shortcode_id'] = isset( $new_instance['shortcode_id'] ) ? absint( $new_instance['shortcode_id'] ) : '';

		return $instance;
	}

	public function form( $instance ) {

		// Define the array of defaults
		$defaults = [
			'title'        => '',
			'shortcode_id' => '',
		];

		// Parse incoming $instance into an array and merge it with $defaults
		$instance = wp_parse_args(
			(array) $instance,
			$defaults
		);

		// Display the admin form
		include RTRS_PATH . 'views/widgets/review-schema.php';
	}
}
