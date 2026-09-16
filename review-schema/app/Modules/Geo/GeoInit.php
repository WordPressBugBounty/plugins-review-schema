<?php
/**
 * GEO analysis module bootstrap.
 *
 * Lightweight entry point for the Generative Engine Optimization channel.
 * Registers the GEO report REST route; analysis is delivered through that route
 * plus the editor `aiseData` localization and the Schema report AJAX response,
 * all of which call GeoAnalyzer::analyze().
 *
 * @package Rtrs\Modules\Geo
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo;

use Rtrs\Modules\Geo\Rest\GeoRestController;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeoInit
 */
class GeoInit {

	use SingletonTrait;

	/**
	 * Register hooks.
	 */
	private function __construct() {
		// Register the GEO report REST route (rtrs-ai/v1/geo/{post_id}).
		GeoRestController::getInstance();
	}
}
