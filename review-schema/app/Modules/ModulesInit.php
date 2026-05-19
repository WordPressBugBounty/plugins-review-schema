<?php
/**
 * Modules Initialization Handler
 *
 * Initializes core plugin modules such as Review and Schema.
 *
 * @package Rtrs\Modules
 */

namespace Rtrs\Modules;

use Rtrs\Block\Faq\FaqBlock;
use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\ReviewInit;
use Rtrs\Modules\Schema\SchemaInit;
use Rtrs\AI\AIInit;
use Rtrs\Supports\Academy\AcademyReview;
use Rtrs\Supports\FluentCart\FluentCartReview;
use Rtrs\Supports\LearnPress\LearnPressReview;
use Rtrs\Supports\SureCart\SureCartReview;
use Rtrs\Supports\LifterLms\LifterLmsReview;
use Rtrs\Supports\Pricing\PricingCollector;
use Rtrs\Supports\TutorLms\TutorLmsReview;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ModulesInit
 *
 * Loads and initializes all enabled plugin modules.
 * Uses the Singleton pattern to prevent multiple initializations.
 */
class ModulesInit {

	/**
	 * Singleton trait.
	 */
	use SingletonTrait;

	/**
	 * ModulesInit constructor.
	 *
	 * Triggers initialization of plugin modules.
	 * Constructor is private to enforce singleton usage.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin modules.
	 *
	 * Boots Review and Schema modules.
	 *
	 * @return void
	 */
	public function init() {
		if ( Functions::review_enabled() ) {
			ReviewInit::getInstance();
		}
		if ( Functions::schema_enabled() ) {
			SchemaInit::getInstance();
		}
		// AI module is gated on `ai_enabled` only — it powers FAQ content generation
		// in addition to schema generation, so it must remain available even when
		// the Schema module is disabled.
		if ( 'yes' === AIInit::getSetting( 'ai_enabled', 'no' ) ) {
			AIInit::getInstance();
		}

		FaqBlock::getInstance();

		// Dynamic pricing collector for Product/SoftwareApplication schemas.
		if ( Functions::schema_enabled() ) {
			PricingCollector::getInstance();
		}

		// Third-party plugin support.
		if ( Functions::review_enabled() && Functions::is_plugin_active( 'tutor/tutor.php' ) ) {
			TutorLmsReview::getInstance();
		}
		if ( Functions::review_enabled() && Functions::is_plugin_active( 'learnpress/learnpress.php' ) ) {
			LearnPressReview::getInstance();
		}
		if ( Functions::review_enabled() && Functions::is_plugin_active( 'lifterlms/lifterlms.php' ) ) {
			LifterLmsReview::getInstance();
		}
		if ( Functions::review_enabled() && Functions::is_plugin_active( 'academy/academy.php' ) ) {
			AcademyReview::getInstance();
		}
		if ( Functions::review_enabled() && Functions::is_plugin_active( 'fluent-cart/fluent-cart.php' ) ) {
			FluentCartReview::getInstance();
		}
		if ( Functions::review_enabled() && Functions::is_plugin_active( 'surecart/surecart.php' ) ) {
			SureCartReview::getInstance();
		}
	}
}
