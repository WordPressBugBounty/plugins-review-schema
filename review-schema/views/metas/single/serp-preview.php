<?php
/**
 * Google Search Preview metabox view.
 *
 * @var array $serpData SERP preview data from SerpPreviewHelper::extract().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_title       = $serpData['title'] ?? '';
$rtrs_description = $serpData['description'] ?? '';
$rtrs_url         = $serpData['url'] ?? '';
$rtrs_display_url = $serpData['displayUrl'] ?? '';
$rtrs_favicon     = $serpData['favicon'] ?? '';
$rtrs_site_name   = $serpData['siteName'] ?? '';
$rtrs_schema_type = $serpData['schemaType'] ?? '';
$rtrs_source      = $serpData['schemaSource'] ?? '';
$rtrs_rich        = $serpData['richElements'] ?? [];

// Load the shared card HTML helper function.
require_once __DIR__ . '/_serp-card-fn.php';
?>

<div id="rtrs-serp-preview-wrap">

	<!-- Tab bar -->
	<div class="rtrs-serp-tabs">
		<button type="button" class="rtrs-serp-tab is-active" data-tab="desktop">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
			<?php esc_html_e( 'Desktop', 'review-schema' ); ?>
		</button>
		<button type="button" class="rtrs-serp-tab" data-tab="mobile">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
			<?php esc_html_e( 'Mobile', 'review-schema' ); ?>
		</button>

		<button type="button" class="rtrs-serp-refresh" id="rtrs-serp-refresh" title="<?php esc_attr_e( 'Refresh preview', 'review-schema' ); ?>">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
		</button>
	</div>

	<!-- Content area (JS re-renders dynamically when schema changes) -->
	<div id="rtrs-serp-content">
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
	</div>

	<!-- Footer -->
	<div class="rtrs-serp-footer">
		<?php if ( $rtrs_schema_type ) : ?>
			<span class="rtrs-serp-badge">
				<?php echo esc_html( $rtrs_schema_type ); ?>
				<?php if ( $rtrs_source ) : ?>
					<span class="rtrs-serp-badge__source"><?php echo esc_html( $rtrs_source ); ?></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
        <p class="rtrs-serp-notice"><?php esc_html_e( 'This is an approximate preview of how your page may appear in Google search results.', 'review-schema' ); ?></p>

	</div>


</div>
