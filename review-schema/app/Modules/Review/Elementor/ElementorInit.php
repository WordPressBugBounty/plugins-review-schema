<?php

namespace Rtrs\Modules\Review\Elementor;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;
use Rtrs\Modules\Review\Elementor\Widgets\AffiliateReview;
use Rtrs\Modules\Review\Elementor\Widgets\AverageRatingStars;
use Rtrs\Modules\Review\Elementor\Widgets\AverageRatingCount;
use Rtrs\Modules\Review\Elementor\Widgets\ReviewList;
use Rtrs\Modules\Review\Elementor\Widgets\ReviewForm;
use Rtrs\Modules\Review\Elementor\Widgets\ReviewSummary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElementorInit {

	use SingletonTrait;

	private function __instance() {
		if ( ! Functions::is_plugin_active( 'elementor/elementor.php' ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'rtrs-review-schema',
			[
				'title' => esc_html__( 'Review Schema', 'review-schema' ),
				'icon'  => 'eicon-star',
				'active' => true,
			]
		);

		$reorder = function () {
			if ( isset( $this->categories['rtrs-review-schema'] ) ) {
				$our = [ 'rtrs-review-schema' => $this->categories['rtrs-review-schema'] ];
				unset( $this->categories['rtrs-review-schema'] );
				$this->categories = $our + $this->categories;
			}
		};
		$reorder->call( $elements_manager );
	}

	public function register_widgets( $widgets_manager ) {
		$widgets_manager->register( new AffiliateReview() );
		$widgets_manager->register( new AverageRatingStars() );
		$widgets_manager->register( new AverageRatingCount() );
		$widgets_manager->register( new ReviewList() );
		$widgets_manager->register( new ReviewForm() );
		$widgets_manager->register( new ReviewSummary() );
	}
}
