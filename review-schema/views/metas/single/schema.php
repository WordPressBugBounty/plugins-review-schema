<?php

use Rtrs\Modules\Schema\Admin\Meta\SchemaMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_helper       = new Rtrs\Helpers\Functions();
$rtrs_meta_options = SchemaMeta::getInstance();

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fieldGenerator() returns plugin-built HTML form fields with internal esc_attr/esc_html on dynamic values.
echo $rtrs_helper->fieldGenerator( $rtrs_meta_options->sectionSchemaFields(), true );
