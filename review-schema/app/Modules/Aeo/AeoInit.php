<?php
/**
 * AEO analysis module bootstrap.
 *
 * Lightweight entry point for the Answer Engine Optimization channel. Registers
 * the AEO report REST route; analysis is delivered through that route plus the
 * editor `aiseData` localization and the Schema report AJAX response, all of
 * which call AeoAnalyzer::analyze().
 *
 * @package Rtrs\Modules\Aeo
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo;

use Rtrs\Modules\Aeo\Rest\AeoRestController;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AeoInit
 */
class AeoInit {

	use SingletonTrait;

	/**
	 * Register hooks.
	 */
	private function __construct() {
		// Register the AEO report REST route (rtrs-ai/v1/aeo/{post_id}).
		AeoRestController::getInstance();
	}
}
