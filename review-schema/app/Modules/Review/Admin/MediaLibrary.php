<?php
/**
 * Media Library visibility handler for review uploads.
 *
 * Review images/videos are stored as real WordPress attachments (so they get
 * thumbnails and the standard media pipeline) but are tagged with the
 * `attach_type = review` meta and hidden from the admin Media Library so they
 * do not clutter the library grid/list or appear in media pickers.
 *
 * @package Rtrs\Modules\Review\Admin
 */

namespace Rtrs\Modules\Review\Admin;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaLibrary
 *
 * Excludes review-uploaded attachments from the Media Library views.
 */
class MediaLibrary {

	use SingletonTrait;

	/**
	 * Meta key that flags an attachment as a review upload.
	 *
	 * @var string
	 */
	const ATTACH_TYPE_META = 'attach_type';

	/**
	 * Meta value used for review uploads.
	 *
	 * @var string
	 */
	const REVIEW_TYPE = 'review';

	/**
	 * MediaLibrary constructor.
	 *
	 * Registers the filters that exclude review uploads from both the grid
	 * (AJAX) and list-table Media Library views.
	 */
	private function __construct() {
		add_filter( 'ajax_query_attachments_args', [ $this, 'exclude_from_grid' ] );
		add_action( 'pre_get_posts', [ $this, 'exclude_from_list' ] );
	}

	/**
	 * Meta query fragment that excludes review uploads.
	 *
	 * @return array
	 */
	private function exclusion_clause() {
		return [
			'relation' => 'OR',
			[
				'key'     => self::ATTACH_TYPE_META,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => self::ATTACH_TYPE_META,
				'value'   => self::REVIEW_TYPE,
				'compare' => '!=',
			],
		];
	}

	/**
	 * Exclude review uploads from the Media Library grid (AJAX modal).
	 *
	 * @param array $args WP_Query args used by the media grid.
	 * @return array
	 */
	public function exclude_from_grid( $args ) {
		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];

		$args['meta_query'] = $meta_query
			? [
				'relation' => 'AND',
				$meta_query,
				$this->exclusion_clause(),
			]
			: $this->exclusion_clause();

		return $args;
	}

	/**
	 * Exclude review uploads from the Media Library list table.
	 *
	 * @param \WP_Query $query Current query.
	 * @return void
	 */
	public function exclude_from_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		$existing = $query->get( 'meta_query' );
		$existing = is_array( $existing ) ? $existing : [];

		$query->set(
			'meta_query',
			$existing
				? [
					'relation' => 'AND',
					$existing,
					$this->exclusion_clause(),
				]
				: $this->exclusion_clause()
		);
	}
}
