<?php

namespace Rtrs\Controllers\Admin;

use Rtrs\Controllers\Admin\Meta\AddMetaBox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminController {
	public function __construct() {
		new AddMetaBox();
		new ScriptLoader();
		new AdminSettings();
		new Notifications();
	}
}
