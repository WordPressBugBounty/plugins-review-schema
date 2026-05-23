<?php

namespace Rtrs\Controllers\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller class for handling AJAX-related operations.
 *
 * This class is responsible for initializing required components
 * related to shortcode handling, reviews, migrations, plugin details,
 * and AJAX settings.
 */
class AjaxController {
	/**
	 * Constructor method.
	 *
	 * Initializes the necessary components by creating instances of Shortcode, Review, Migration, and OurPluginsController classes.
	 *
	 * @return void
	 */
	public function __construct() {
		new OurPluginsController();
		new FinishWizard();
		new InstallRecommendedPlugin();
	}
}
