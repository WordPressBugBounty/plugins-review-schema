<div class="rtrs-settings">
	<?php
	use Rtrs\Controllers\Admin\AdminSettings;
	settings_errors();
	if ( class_exists( AdminSettings::class ) ) {
		AdminSettings::show_messages();
	}
	$settings_url = admin_url( 'admin.php?page=rtrs-settings' );
	$get_help     = admin_url( 'admin.php?page=rtrs-reviews-get-help' );
	?>
	<div class="rtrs-settings-header">
		<div class="rtrs-settings-container">
			<div class="rtrs-settings-header-inner">
				<div class="rtrs-settings-logo">
					<div class="rtrs-logo">
						<img alt="Review Schema" src="<?php echo esc_url( rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ) ); ?>" width="50px" height="50px" style="grid-row: 1 / 4; align-self: center;justify-self: center"/>
					</div>
					<div class="rtrs-content">
						<div class="h2">Review Schema</div>
						<span>Plugin Settings</span>
					</div>
				</div>
				<div class="settings-menu">
					<a href="<?php echo esc_url( $settings_url ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" x="0" y="0" viewBox="0 0 682.667 682.667" style="enable-background:new 0 0 512 512" xml:space="preserve" class="">
							<g>
								<defs>
									<clipPath id="a" clipPathUnits="userSpaceOnUse">
										<path d="M0 512h512V0H0Z" fill="currentColor" opacity="1" data-original="currentColor"></path>
									</clipPath>
								</defs>
								<g clip-path="url(#a)" transform="matrix(1.33333 0 0 -1.33333 0 682.667)">
									<path d="M0 0c-43.446 0-78.667-35.22-78.667-78.667 0-43.446 35.221-78.666 78.667-78.666 43.446 0 78.667 35.22 78.667 78.666C78.667-35.22 43.446 0 0 0Zm220.802-22.53-21.299-17.534c-24.296-20.001-24.296-57.204 0-77.205l21.299-17.534c7.548-6.214 9.497-16.974 4.609-25.441l-42.057-72.845c-4.889-8.467-15.182-12.159-24.337-8.729l-25.835 9.678c-29.469 11.04-61.688-7.561-66.862-38.602l-4.535-27.213c-1.607-9.643-9.951-16.712-19.727-16.712h-84.116c-9.776 0-18.12 7.069-19.727 16.712l-4.536 27.213c-5.173 31.041-37.392 49.642-66.861 38.602l-25.834-9.678c-9.156-3.43-19.449.262-24.338 8.729l-42.057 72.845c-4.888 8.467-2.939 19.227 4.609 25.441l21.3 17.534c24.295 20.001 24.295 57.204 0 77.205l-21.3 17.534c-7.548 6.214-9.497 16.974-4.609 25.441l42.057 72.845c4.889 8.467 15.182 12.159 24.338 8.729l25.834-9.678c29.469-11.04 61.688 7.561 66.861 38.602l4.536 27.213c1.607 9.643 9.951 16.711 19.727 16.711h84.116c9.776 0 18.12-7.068 19.727-16.711l4.535-27.213c5.174-31.041 37.393-49.642 66.862-38.602l25.835 9.678c9.155 3.43 19.448-.262 24.337-8.729l42.057-72.845c4.888-8.467 2.939-19.227-4.609-25.441z" style="stroke-width:40;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;stroke-dasharray:none;stroke-opacity:1" transform="translate(256 334.666)" fill="none" stroke="currentColor" stroke-width="40" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="none" stroke-opacity="" data-original="currentColor" class=""></path>
								</g>
							</g>
						</svg>
						Settings
					</a>
					<a href="https://www.radiustheme.com/ticket-support/" target="_blank">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512">
							<g>
								<path d="M136 332c0 22.091-17.909 40-40 40s-40-17.909-40-40v-72c0-22.091 17.909-40 40-40s40 17.909 40 40v72zM456 332c0 22.091-17.909 40-40 40s-40-17.909-40-40v-72c0-22.091 17.909-40 40-40s40 17.909 40 40v72z" fill="none" stroke="currentColor" stroke-width="40" stroke-linecap="round" stroke-linejoin="round"></path>
								<path d="M56 260v-40c0-110.457 89.543-200 200-200s200 89.543 200 200v40M456 332v40c0 44.183-35.817 80-80 80h-80" fill="none" stroke="currentColor" stroke-width="40" stroke-linecap="round" stroke-linejoin="round"></path>
								<circle cx="256" cy="452" r="40" fill="none" stroke="currentColor" stroke-width="40" stroke-linecap="round" stroke-linejoin="round"></circle>
							</g>
						</svg>
						Support
					</a>
					<a href="<?php echo esc_url( $get_help ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512">
							<g>
								<path d="M256 0C114.509 0 0 114.496 0 256c0 141.489 114.496 256 256 256 141.491 0 256-114.496 256-256C512 114.509 397.504 0 256 0zm0 476.279c-121.462 0-220.279-98.816-220.279-220.279S134.538 35.721 256 35.721c121.463 0 220.279 98.816 220.279 220.279S377.463 476.279 256 476.279z" fill="currentColor"></path>
								<path d="M248.425 323.924c-14.153 0-25.61 11.794-25.61 25.946 0 13.817 11.12 25.948 25.61 25.948s25.946-12.131 25.946-25.948c0-14.152-11.794-25.946-25.946-25.946zM252.805 127.469c-45.492 0-66.384 26.959-66.384 45.155 0 13.142 11.12 19.208 20.218 19.208 18.197 0 10.784-25.948 45.155-25.948 16.848 0 30.328 7.414 30.328 22.915 0 18.196-18.871 28.642-29.991 38.077-9.773 8.423-22.577 22.24-22.577 51.22 0 17.522 4.718 22.577 18.533 22.577 16.511 0 19.881-7.413 19.881-13.817 0-17.522.337-27.631 18.871-42.121 9.098-7.076 37.74-29.991 37.74-61.666s-28.642-55.6-71.774-55.6z" fill="currentColor"></path>
							</g>
						</svg>
						Help
					</a>
					<a class="doc" href="https://www.radiustheme.com/docs/review-schema/" target="_blank">
						Documentation
					</a>
				</div><!-- .settings-menu -->
			</div><!-- .rt-settings-header-inner -->
		</div><!-- .settings-container -->
	</div><!-- .rt-settings-header -->
</div>