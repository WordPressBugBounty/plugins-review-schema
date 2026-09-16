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

	/**
	 * Per-request cache of recalculated comment counts, keyed by post ID.
	 *
	 * @var array
	 */
	private $comment_count_cache = [];

	private function __construct() {
		add_filter( 'ajax_query_attachments_args', [ $this, 'wpse_hide_cv_media_overlay_view' ] );
		add_action( 'pre_get_comments', [ $this, 'hide_review_form_comments_table' ] );
		add_filter( 'wp_count_comments', [ $this, 'exclude_reviews_from_comment_counts' ], 99, 2 );
	}

	/**
	 * Recalculate the global comment counts excluding review comments.
	 *
	 * The native comments list table and the admin menu bubble read their
	 * status counts from wp_count_comments(), which is not affected by the
	 * pre_get_comments filter used to hide review rows. Without this, reviews
	 * (comment_type = 'review') are counted as regular comments, inflating the
	 * All/Pending/Approved/Spam/Trash numbers.
	 *
	 * Counts are derived with get_comments() using type__not_in => review, so
	 * the numbers pass through the exact same query filters as the admin list
	 * (e.g. WooCommerce's exclusion of order notes) and therefore always match
	 * the rows actually displayed.
	 *
	 * @param array|object $stats   Existing comment counts (empty by default).
	 * @param int          $post_id Post ID to count comments for. 0 for all.
	 * @return object Recalculated counts excluding reviews.
	 */
	public function exclude_reviews_from_comment_counts( $stats, $post_id ) {
		$post_id = (int) $post_id;

		if ( isset( $this->comment_count_cache[ $post_id ] ) ) {
			return $this->comment_count_cache[ $post_id ];
		}

		$approved     = $this->count_non_review_comments( 'approve', $post_id );
		$moderated    = $this->count_non_review_comments( 'hold', $post_id );
		$spam         = $this->count_non_review_comments( 'spam', $post_id );
		$trash        = $this->count_non_review_comments( 'trash', $post_id );
		$post_trashed = $this->count_non_review_comments( 'post-trashed', $post_id );

		$counts = (object) [
			'approved'       => $approved,
			'spam'           => $spam,
			'trash'          => $trash,
			'post-trashed'   => $post_trashed,
			'total_comments' => $approved + $moderated + $spam,
			'all'            => $approved + $moderated,
			'moderated'      => $moderated,
		];

		$this->comment_count_cache[ $post_id ] = $counts;

		return $counts;
	}

	/**
	 * Count comments for a given status, excluding reviews.
	 *
	 * @param string $status  Comment status: approve|hold|spam|trash|post-trashed.
	 * @param int    $post_id Post ID to scope the count to. 0 for all posts.
	 * @return int Number of matching comments.
	 */
	private function count_non_review_comments( $status, $post_id ) {
		$args = [
			'status'       => $status,
			'type__not_in' => [ 'review' ],
			'count'        => true,
		];

		if ( $post_id > 0 ) {
			$args['post_id'] = $post_id;
		}

		return (int) get_comments( $args );
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
