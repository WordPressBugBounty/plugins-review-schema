<?php
/**
 * Our Plugins Page
 *
 * @package Review_Schema
 */

use Rtrs\Helpers\Functions;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

$rtrs_heading_title = 'Our Plugins';
?>
<div class="rtrs-settings">
	<?php require_once RTRS_PATH . 'views/setting-sections/settings-header.php'; ?>
	<div class="rtrs-our-plugins-wrapper rtrs-settings-container">
		<?php
		$rtrs_plugins = [
			[
				'slug'    => 'classified-listing',
				'image'   => 'imgs/our-plugins/classified-listing.gif',
				'title'   => 'Classified Listing',
				'excerpt' => 'AI-Powered Classified Listing and Business Directory plugin to create classified listing, real estate directory, Job board, local business directory, and more.',
				'docs'    => 'https://www.radiustheme.com/docs/classified-listing/',
			],
			[
				'slug'    => 'shopbuilder',
				'image'   => 'imgs/our-plugins/shopbuilder.png',
				'title'   => 'ShopBuilder',
				'excerpt' => 'The Ultimate All-in-One Solution for Elementor & WooCommerce. Including 28 powerful modules, 120+ creative widgets, and 40+ pre-built templates.',
				'docs'    => 'https://shopbuilderwp.com/docs/',
			],
			[
				'slug'    => 'the-post-grid',
				'image'   => 'imgs/our-plugins/the-post-grid-icon-256x256.gif',
				'title'   => 'The Post Grid',
				'excerpt' => 'Fast & Easy way to display WordPress post in Grid, List & Isotope view (filter by category, tag, author..) without a single line of coding.',
				'docs'    => 'https://www.radiustheme.com/docs/the-post-grid/',
			],
			[
				'slug'    => 'tlp-food-menu',
				'image'   => 'imgs/our-plugins/food-menu.gif',
				'title'   => 'Food Menu',
				'excerpt' => 'Food & Restaurant Menu Display Plugin for Restaurant, Cafes, Fast Food, Coffee House with WooCommerce Online Ordering.',
				'docs'    => 'https://www.radiustheme.com/docs/food-menu/',
			],
			[
				'slug'    => 'woo-product-variation-swatches',
				'image'   => 'imgs/our-plugins/variation-swatches.png',
				'title'   => 'Variation Swatches',
				'excerpt' => 'Woocommerce variation swatches plugin converts the product variation select fields into radio, images, colors, and labels.',
				'docs'    => 'https://www.radiustheme.com/docs/variation-swatches/',
			],
			[
				'slug'    => 'woo-product-variation-gallery',
				'image'   => 'imgs/our-plugins/variation-gallery.gif',
				'title'   => 'Variation Images Gallery',
				'excerpt' => 'Variation Images Gallery for WooCommerce plugin allows to add UNLIMITED additional images for each variation of product.',
				'docs'    => 'https://www.radiustheme.com/docs/variation-gallery/',
			],
			[
				'slug'    => 'testimonial-slider-and-showcase',
				'image'   => 'imgs/our-plugins/testimonials.gif',
				'title'   => 'Testimonial Slider',
				'excerpt' => 'Testimonial Slider and Showcase plugin the ultimate WordPress plugin for displaying customer testimonials, reviews, and social proof.',
				'docs'    => 'https://www.radiustheme.com/docs/testimonial-slider/',
			],
			[
				'slug'    => 'tlp-team',
				'image'   => 'imgs/our-plugins/team.gif',
				'title'   => 'Team Members Showcase',
				'excerpt' => 'Team Member plugin is the ultimate solution for displaying your team members, staff, and associates in a way that builds trust for your brand.',
				'docs'    => 'https://www.radiustheme.com/docs/team/',
			],
			[
				'slug'    => 'tlp-portfolio',
				'image'   => 'imgs/our-plugins/portfolio.gif',
				'title'   => 'Portfolio',
				'excerpt' => 'Portfolio is the ultimate WordPress portfolio plugin to create and display a beautiful, responsive, and filterable portfolio.',
				'docs'    => 'https://www.radiustheme.com/docs/portfolio/',
			],
		];
		?>
		<div class="rtrs-plugins-row">
			<?php foreach ( $rtrs_plugins as $rtrs_plugin ) : ?>
				<div class="rtrs-plugin-item">
					<header class="rtrs-plugin-header">
						<img src="<?php echo esc_url( rtrs()->get_assets_uri( $rtrs_plugin['image'] ) ); ?>" alt="<?php echo esc_attr( $rtrs_plugin['title'] ); ?>">
						<h3 class="rtrs-plugin-title"><?php echo esc_html( $rtrs_plugin['title'] ); ?></h3>
					</header>
					<p class="rtrs-plugin-excerpt"><?php echo esc_html( $rtrs_plugin['excerpt'] ); ?></p>
					<footer class="rtrs-plugin-footer">
						<?php Functions::get_plugin_install_button( $rtrs_plugin['slug'] ); ?>
						<a target="_blank" href="<?php echo esc_url( $rtrs_plugin['docs'] ); ?>" class="rtrs-admin-btn rtrs-btn-outline">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
							Docs
						</a>
					</footer>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
