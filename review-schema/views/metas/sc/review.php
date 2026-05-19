<?php
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_helper = new Rtrs\Helpers\Functions;
$rtrs_meta_options = new \Rtrs\Modules\Review\Admin\Meta\MetaOptions;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fieldGenerator() returns plugin-built HTML form fields with internal esc_attr/esc_html on dynamic values.
echo $rtrs_helper->fieldGenerator($rtrs_meta_options->sectionReviewFields(), true);