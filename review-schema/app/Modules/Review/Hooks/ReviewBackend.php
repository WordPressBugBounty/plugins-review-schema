<?php

namespace Rtrs\Modules\Review\Hooks;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewBackend {
	/**
	 * SingleTon
	 */
	use SingletonTrait;

	private function __construct() {
		add_filter( 'ajax_query_attachments_args', [ $this, 'wpse_hide_cv_media_overlay_view' ] );
		add_action( 'pre_get_comments', [ $this, 'hide_review_form_comments_table' ] );
	}

	/**
	 * Hide review comments from admin comments list table.
	 *
	 * @param WP_Comment_Query $query Comment query object.
	 * @return void
	 */
	public function hide_review_form_comments_table( $query ) {
		if ( ! is_admin() ) {
			return;
		}
		// Main comments screen only.
		if ( ! did_action( 'load-edit-comments.php' ) ) {
			return;
		}
		// Only affect the main admin comments screen.
		if ( ! empty( $query->query_vars['type'] ) ) {
			return;
		}
		$query->query_vars['type__not_in'] = [ 'review' ];
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
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
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
