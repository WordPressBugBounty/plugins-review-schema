<?php

namespace Rtrs\Hooks;

class Backend {
	public function __construct() {
		add_filter( 'ajax_query_attachments_args', [ $this, 'wpse_hide_cv_media_overlay_view' ] );
		// add_filter( 'rtrs_register_settings_tabs', [ $this, 'register_settings_tabs' ], 11 );
	}

	/**
	 * Hide attachment files from the Media Library's overlay (modal) view
	 * if they have a certain meta key set.
	 *
	 * @param array $args An array of query variables.
	 */
	public function wpse_hide_cv_media_overlay_view( $args ) {
		// Bail if this is not the admin area.
		if ( ! is_admin() ) {
            return $args;
		}

		// Modify the query.
		$args['meta_query'] = [
			[
				'key'     => 'attach_type',
				'compare' => 'NOT EXISTS',
			],
		];

		return $args;
	}
	/**
	 * Undocumented function
	 *
	 * @param array $args
	 * @return array
	 */
	public function register_settings_tabs( $tabs ) {
		$tabs['themes_and_plugins'] = esc_html__( 'Themes And Plugins ( Pro )', 'review-schema' );
		return $tabs;
	}


}
