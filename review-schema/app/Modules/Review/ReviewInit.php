<?php
/**
 * Review Initialization Handler
 *
 * Bootstraps review-related functionality, including shortcode registration.
 *
 * @package Rtrs\Review
 */

namespace Rtrs\Modules\Review;

use Rtrs\Traits\SingletonTrait;
use Rtrs\Modules\Review\Widgets\Widget;
use Rtrs\Modules\Review\Ajax\ReviewAjax;
use Rtrs\Modules\Review\Ajax\ShortcodeAjax;
use Rtrs\Modules\Review\Hooks\ReviewBackend;
use Rtrs\Modules\Review\Admin\ReviewSettings;
use Rtrs\Modules\Review\Hooks\ReviewFrontend;
use Rtrs\Modules\Review\Admin\RegisterPostType;
use Rtrs\Modules\Review\Shortcodes\ShortcodesInit;
use Rtrs\Modules\Review\Scripts\ReviewScriptLoader;
use Rtrs\Modules\Review\Admin\Meta\AddReviewMetaBox;
use Rtrs\Modules\Review\Elementor\ElementorInit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewInit
 *
 * Handles initialization tasks for the Review module.
 * Uses the Singleton pattern to ensure a single instance.
 */
class ReviewInit {

	use SingletonTrait;

	/**
	 * ReviewInit constructor.
	 *
	 * Registers required actions and initializes review shortcodes.
	 * Constructor is private to enforce singleton usage.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'init', [ ShortcodesInit::class, 'init_short_code' ] );
		$this->init();
	}

	/**
	 * Initializes the review system.
	 *
	 * Ensures the backend and frontend components of the review system are instantiated.
	 *
	 * @return void
	 */
	public function init() {
		ReviewBackend::getInstance();
		ReviewFrontend::getInstance();
		Widget::getInstance();
		ReviewAjax::getInstance();
		ReviewScriptLoader::getInstance();
		ReviewSettings::getInstance();
		RegisterPostType::getInstance();
		ShortcodeAjax::getInstance();
		AddReviewMetaBox::getInstance();
		ElementorInit::getInstance();
	}
}
