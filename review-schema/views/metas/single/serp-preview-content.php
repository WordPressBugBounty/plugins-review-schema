<?php
/**
 * SERP Preview — inner content partial (AJAX refresh target).
 *
 * @var array $serpData SERP preview data from SerpPreviewHelper::extract().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the shared card HTML helper function.
require_once __DIR__ . '/_serp-card-fn.php';

$rtrs_title       = $serpData['title'] ?? '';
$rtrs_description = $serpData['description'] ?? '';
$rtrs_display_url = $serpData['displayUrl'] ?? '';
$rtrs_favicon     = $serpData['favicon'] ?? '';
$rtrs_site_name   = $serpData['siteName'] ?? '';
$rtrs_schema_type = $serpData['schemaType'] ?? '';
$rtrs_rich        = $serpData['richElements'] ?? [];
?>

<!-- Desktop panel -->
<div class="rtrs-serp-panel is-active" data-panel="desktop">
	<div class="rtrs-serp-card rtrs-serp-card--desktop">
		<?php rtrs_serp_card_html( $rtrs_title, $rtrs_description, $rtrs_display_url, $rtrs_favicon, $rtrs_site_name, $rtrs_rich, $rtrs_schema_type, 28 ); ?>
	</div>
</div>
<!-- Mobile panel -->
<div class="rtrs-serp-panel" data-panel="mobile">
	<div class="rtrs-serp-card rtrs-serp-card--mobile">
		<?php rtrs_serp_card_html( $rtrs_title, $rtrs_description, $rtrs_display_url, $rtrs_favicon, $rtrs_site_name, $rtrs_rich, $rtrs_schema_type, 20 ); ?>
	</div>
</div>
