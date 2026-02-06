<?php use Rtrs\Helpers\Functions;

if ( ! Functions::has_valid_license() ) { ?>
	<div class="rtrs-promo-container">
		<div class="rtrs-promo-inner">
			<div class="promo-image">
				<img src="<?php echo esc_url( rtrs()->get_assets_uri( 'imgs/Review-Schema_Promo_thumb.webp' ) ); ?>" alt="Review Schema">
			</div>
			<div class="promo-features">
				<h2 class="promo-title">
					Review & Structure Data Schema Plugin Pro Features
				</h2>
				<ul>
					<li> All Free Feature Included </li>
					<li> Product <span class="rtrs-hot">Popular</span></li>
					<li> Book <span class="rtrs-hot">Popular</span></li>
					<li> Real Estate Listing </li>
					<li> Course <span class="rtrs-hot" >Popular</span></li>
					<li> Job Posting </li>
					<li> Recipe <span class="rtrs-hot">Popular</span></li>
					<li> Software App <span class="rtrs-hot">Popular</span></li>
					<li> Image License </li>
					<li> Vacation Rental </li>
					<li> Vehicle listing</li>
					<li> Collection Page <span class="rtrs-hot">Popular</span></li>
					<li> Restaurant <span class="rtrs-hot">Popular</span></li>
					<li> TV Series </li>
					<li> Special Announcement</li>
					<li> Custom Schema</li>
				</ul>
				<a class="rtrs-admin-btn" href="https://www.radiustheme.com/downloads/wordpress-review-structure-data-schema-plugin/#pricing" target="_blank" >
					Get The Deal!
				</a>
			</div>
		</div>
	</div>
<?php } ?>
