<?php
/**
 * Schema Initialization Handler
 *
 * Bootstraps schema-related functionality for frontend output.
 *
 * @package Rtrs\Schema
 */

namespace Rtrs\Modules\Schema;

use Rtrs\Modules\Schema\Admin\Meta\SchemaPreviewAssets;
use Rtrs\Modules\Schema\Admin\Meta\SerpPreview;
use Rtrs\Modules\Schema\Ajax\Migration;
use Rtrs\Modules\Schema\Ajax\SchemaPreviewAjax;
use Rtrs\Modules\Schema\Ajax\SchemaReportAjax;
use Rtrs\Modules\Schema\Hooks\AggregateRatingInjector;
use Rtrs\Modules\Schema\Hooks\ElementorFaq;
use Rtrs\Modules\Schema\Hooks\GutenbergFaq;
use Rtrs\Modules\Schema\Hooks\SeoHooks;
use Rtrs\Modules\Schema\Hooks\FaqPageFrontend;
use Rtrs\Modules\Schema\Hooks\FaqPageSchema;
use Rtrs\Modules\Schema\Hooks\MediaSchemaLinker;
use Rtrs\Modules\Schema\Hooks\SchemaFrontend;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaInit
 *
 * Responsible for initializing schema functionality.
 * Uses the Singleton pattern to ensure a single instance.
 */
class SchemaInit {

	use SingletonTrait;

	/**
	 * SchemaInit constructor.
	 *
	 * Initializes frontend schema hooks.
	 * Constructor is private to enforce singleton usage.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'rtrs_ai_loaded', [ $this, 'on_plugins_loaded' ], -1 );
		$this->init();
	}

	/**
	 * @return void
	 */
	public function init() {
		SchemaFrontend::getInstance();
		AggregateRatingInjector::getInstance();
		ElementorFaq::getInstance();
		GutenbergFaq::getInstance();
		FaqPageSchema::getInstance();
		MediaSchemaLinker::getInstance();
		FaqPageFrontend::getInstance();
		Migration::getInstance();

		if ( is_admin() ) {
			new SerpPreview();
			new SchemaPreviewAssets();
			SchemaPreviewAjax::getInstance();
			SchemaReportAjax::getInstance();
		}
	}
	/**
	 * @return void
	 */
	public function on_plugins_loaded() {
		SeoHooks::getInstance();
	}
}
