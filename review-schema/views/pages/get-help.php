<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpwrap rtrs-settings">
	<?php require_once RTRS_PATH . 'views/setting-sections/settings-header.php'; ?>
	<div id="rtrs-get-help-wrapper">
		<div class="rtrs-help-container">

			<!-- Hero Section -->
			<div class="rtrs-help-hero">
				<div class="rtrs-help-hero-content">
					<h2><?php esc_html_e( 'Thank you for installing SchemaEngine AI', 'review-schema' ); ?></h2>
					<p><?php esc_html_e( 'Everything you need to get started — documentation, video tutorials, and direct support.', 'review-schema' ); ?></p>
				</div>
			</div>

			<!-- Video + Quick Links Row -->
			<div class="rtrs-help-main-row">
				<div class="rtrs-help-video-card">
					<div class="rtrs-help-video-wrap">
						<iframe src="https://www.youtube.com/embed/P1dYzMcerNs" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
					</div>
					<div class="rtrs-help-video-caption">
						<span class="dashicons dashicons-video-alt3"></span>
						<?php esc_html_e( 'Watch the getting started tutorial', 'review-schema' ); ?>
					</div>
				</div>

				<div class="rtrs-help-quick-links">
					<div class="rtrs-help-link-card">
						<div class="rtrs-help-link-icon rtrs-icon-docs">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
						</div>
						<div class="rtrs-help-link-body">
							<h4><?php esc_html_e( 'Documentation', 'review-schema' ); ?></h4>
							<p><?php esc_html_e( 'Step-by-step guides with screenshots to help you configure every feature.', 'review-schema' ); ?></p>
							<a href="https://www.radiustheme.com/docs/review-schema/review-schema" target="_blank" class="rtrs-help-btn">
								<?php esc_html_e( 'Browse Docs', 'review-schema' ); ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
							</a>
						</div>
					</div>

					<div class="rtrs-help-link-card">
						<div class="rtrs-help-link-icon rtrs-icon-support">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
						</div>
						<div class="rtrs-help-link-body">
							<h4><?php esc_html_e( 'Need Help?', 'review-schema' ); ?></h4>
							<p><?php esc_html_e( 'Create a support ticket or join our Facebook community for quick assistance.', 'review-schema' ); ?></p>
							<a href="https://www.radiustheme.com/ticket-support/" target="_blank" class="rtrs-help-btn">
								<?php esc_html_e( 'Get Support', 'review-schema' ); ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
							</a>
						</div>
					</div>

					<div class="rtrs-help-link-card">
						<div class="rtrs-help-link-icon rtrs-icon-review">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						</div>
						<div class="rtrs-help-link-body">
							<h4><?php esc_html_e( 'Leave a Review', 'review-schema' ); ?></h4>
							<p><?php esc_html_e( 'Enjoying SchemaEngine AI? Your review helps us grow and improve the plugin.', 'review-schema' ); ?></p>
							<a href="https://wordpress.org/support/plugin/review-schema/reviews/" target="_blank" class="rtrs-help-btn">
								<?php esc_html_e( 'Write a Review', 'review-schema' ); ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- Pro Features Section -->
			<div class="rtrs-help-pro-section">
				<div class="rtrs-help-pro-header">
					<h3><?php esc_html_e( 'Unlock Pro Features', 'review-schema' ); ?></h3>
					<p><?php esc_html_e( 'Upgrade to Pro for advanced review and schema capabilities.', 'review-schema' ); ?></p>
				</div>

				<div class="rtrs-help-pro-grid">
					<div class="rtrs-help-pro-column">
						<h4>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
							<?php esc_html_e( 'Review Features', 'review-schema' ); ?>
						</h4>
						<ul>
							<li><?php esc_html_e( 'Unlimited Rating Criteria', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Video Review', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Additional Layouts', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Ajax Pagination', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Unlimited Pros & Cons', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Social Share', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Review Like & Dislike', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Anonymous Review', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Purchase Badge (WC/EDD)', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Advanced Layout Styling', 'review-schema' ); ?></li>
						</ul>
					</div>
					<div class="rtrs-help-pro-column">
						<h4>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
							<?php esc_html_e( 'Schema Features', 'review-schema' ); ?>
						</h4>
						<ul>
							<li><?php esc_html_e( 'Product Schema (WooCommerce)', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Course Schema', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Job Posting Schema', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Recipe Schema', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Software App Schema', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Image License Schema', 'review-schema' ); ?></li>
							<li><?php esc_html_e( 'Special Announcement Schema', 'review-schema' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="rtrs-help-pro-cta">
					<a href="https://www.radiustheme.com/downloads/wordpress-review-structure-data-schema-plugin/?utm_source=WordPress&utm_medium=reviewschema&utm_campaign=pro_click" target="_blank" class="rtrs-help-pro-btn">
						<?php esc_html_e( 'Upgrade to Pro', 'review-schema' ); ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
					</a>
				</div>
			</div>

		</div>
	</div>

	<style>
		/* ── Variables ── */
		#rtrs-get-help-wrapper {
			--help-primary: #4f46e5;
			--help-primary-dark: #4338ca;
			--help-primary-light: #ece8ff;
			--help-radius: 12px;
			--help-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
			--help-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
		}

		/* ── Container ── */
		.rtrs-help-container {
			max-width: 1100px;
			margin: 0 auto;
			padding: 30px 20px 40px;
		}

		/* ── Hero ── */
		.rtrs-help-hero {
			background: linear-gradient(135deg, var(--help-primary) 0%, var(--help-primary-dark) 100%);
			border-radius: var(--help-radius);
			padding: 40px 36px;
			margin-bottom: 24px;
			color: #fff;
		}
		.rtrs-help-hero h2 {
			font-size: 24px;
			font-weight: 700;
			margin: 0 0 8px;
			color: #fff;
		}
		.rtrs-help-hero p {
			font-size: 15px;
			margin: 0;
			opacity: 0.85;
			line-height: 1.6;
		}

		/* ── Main Row (Video + Links) ── */
		.rtrs-help-main-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 24px;
			margin-bottom: 24px;
		}

		/* ── Video Card ── */
		.rtrs-help-video-card {
			background: #fff;
			border-radius: var(--help-radius);
			box-shadow: var(--help-shadow);
			overflow: hidden;
			border: 1px solid #e5e7eb;
		}
		.rtrs-help-video-wrap {
			position: relative;
			padding-bottom: 56.25%;
			height: 0;
			overflow: hidden;
		}
		.rtrs-help-video-wrap iframe {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			border: 0;
		}
		.rtrs-help-video-caption {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 14px 18px;
			font-size: 13px;
			color: #6b7280;
			border-top: 1px solid #f3f4f6;
		}
		.rtrs-help-video-caption .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
			color: var(--help-primary);
		}

		/* ── Quick Link Cards ── */
		.rtrs-help-quick-links {
			display: flex;
			flex-direction: column;
			gap: 16px;
		}
		.rtrs-help-link-card {
			display: flex;
			gap: 16px;
			padding: 20px;
			background: #fff;
			border-radius: var(--help-radius);
			border: 1px solid #e5e7eb;
			box-shadow: var(--help-shadow);
			transition: box-shadow 0.2s, border-color 0.2s;
		}
		.rtrs-help-link-card:hover {
			box-shadow: var(--help-shadow-md);
			border-color: #d1d5db;
		}
		.rtrs-help-link-icon {
			flex-shrink: 0;
			width: 40px;
			height: 40px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.rtrs-help-link-icon svg {
			width: 20px;
			height: 20px;
		}
		.rtrs-icon-docs {
			background: #eff6ff;
			color: #3b82f6;
		}
		.rtrs-icon-support {
			background: #f0fdf4;
			color: #22c55e;
		}
		.rtrs-icon-review {
			background: #fefce8;
			color: #eab308;
		}
		.rtrs-help-link-body {
			flex: 1;
			min-width: 0;
		}
		.rtrs-help-link-body h4 {
			font-size: 15px;
			font-weight: 600;
			margin: 0 0 4px;
			color: #111827;
		}
		.rtrs-help-link-body p {
			font-size: 13px;
			color: #6b7280;
			margin: 0 0 10px;
			line-height: 1.5;
		}
		.rtrs-help-btn {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			font-size: 13px;
			font-weight: 600;
			color: var(--help-primary);
			text-decoration: none;
			transition: gap 0.2s;
		}
		.rtrs-help-btn:hover {
			gap: 8px;
			color: var(--help-primary-dark);
		}
		.rtrs-help-btn:focus {
			outline: none;
			box-shadow: none;
		}
		.rtrs-help-btn svg {
			width: 14px;
			height: 14px;
		}

		/* ── Pro Features Section ── */
		.rtrs-help-pro-section {
			background: #fff;
			border-radius: var(--help-radius);
			border: 1px solid #e5e7eb;
			box-shadow: var(--help-shadow);
			padding: 32px;
			position: relative;
			overflow: hidden;
		}
		.rtrs-help-pro-section::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			height: 4px;
			background: linear-gradient(90deg, var(--help-primary), var(--help-primary-dark));
		}
		.rtrs-help-pro-header {
			text-align: center;
			margin-bottom: 28px;
		}
		.rtrs-help-pro-header h3 {
			font-size: 20px;
			font-weight: 700;
			margin: 0 0 6px;
			color: #111827;
		}
		.rtrs-help-pro-header p {
			font-size: 14px;
			color: #6b7280;
			margin: 0;
		}

		.rtrs-help-pro-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 28px;
			margin-bottom: 28px;
		}
		.rtrs-help-pro-column h4 {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 15px;
			font-weight: 600;
			color: #111827;
			margin: 0 0 14px;
			padding-bottom: 10px;
			border-bottom: 1px solid #f3f4f6;
		}
		.rtrs-help-pro-column h4 svg {
			width: 18px;
			height: 18px;
			color: var(--help-primary);
		}
		.rtrs-help-pro-column ul {
			list-style: none;
			margin: 0;
			padding: 0;
		}
		.rtrs-help-pro-column ul li {
			position: relative;
			padding: 6px 0 6px 24px;
			font-size: 14px;
			color: #374151;
			line-height: 1.5;
		}
		.rtrs-help-pro-column ul li::before {
			content: '';
			position: absolute;
			left: 0;
			top: 50%;
			transform: translateY(-50%);
			width: 16px;
			height: 16px;
			background: var(--help-primary-light);
			border-radius: 50%;
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234f46e5' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
			background-size: 10px;
			background-repeat: no-repeat;
			background-position: center;
		}

		/* ── Pro CTA Button ── */
		.rtrs-help-pro-cta {
			text-align: center;
		}
		.rtrs-help-pro-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 12px 32px;
			background: linear-gradient(135deg, var(--help-primary) 0%, var(--help-primary-dark) 100%);
			color: #fff !important;
			font-size: 15px;
			font-weight: 600;
			border-radius: 8px;
			text-decoration: none;
			transition: transform 0.15s, box-shadow 0.15s;
			box-shadow: 0 1px 2px rgba(79,70,229,0.2);
		}
		.rtrs-help-pro-btn:hover {
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(79,70,229,0.3);
			color: #fff !important;
		}
		.rtrs-help-pro-btn:focus {
			outline: none;
		}
		.rtrs-help-pro-btn svg {
			width: 16px;
			height: 16px;
		}

		/* ── Responsive ── */
		@media (max-width: 900px) {
			.rtrs-help-main-row {
				grid-template-columns: 1fr;
			}
			.rtrs-help-quick-links {
				flex-direction: row;
				flex-wrap: wrap;
			}
			.rtrs-help-link-card {
				flex: 1 1 calc(50% - 8px);
				min-width: 220px;
			}
		}
		@media (max-width: 600px) {
			.rtrs-help-container {
				padding: 20px 12px 30px;
			}
			.rtrs-help-hero {
				padding: 28px 20px;
			}
			.rtrs-help-hero h2 {
				font-size: 20px;
			}
			.rtrs-help-link-card {
				flex: 1 1 100%;
				flex-direction: column;
				align-items: flex-start;
			}
			.rtrs-help-pro-grid {
				grid-template-columns: 1fr;
				gap: 20px;
			}
			.rtrs-help-pro-section {
				padding: 24px 18px;
			}
		}
	</style>
</div>
