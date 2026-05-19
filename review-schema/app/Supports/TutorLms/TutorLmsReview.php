<?php
/**
 * Tutor LMS Review Integration
 *
 * Replaces Tutor LMS course review tab with Review Schema's review template
 * when Tutor's built-in review system is disabled.
 *
 * @package Rtrs\Supports\TutorLms
 */

namespace Rtrs\Supports\TutorLms;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TutorLmsReview {

	use SingletonTrait;

	private function __construct() {
		add_filter( 'tutor_course/single/nav_items', [ $this, 'inject_review_tab' ], 1000, 2 );

		add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );
	}

	/**
	 * Force comments open on courses post type so comment_form() works.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 *
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( get_post_type( $post_id ) === 'courses' && Functions::isEnableReviewByPostType( 'courses' ) ) {
			return true;
		}
		return $open;
	}

	/**
	 * Inject review-schema review tab when Tutor's review is disabled.
	 *
	 * @param array $items Nav items.
	 * @param int   $course_id Course ID.
	 *
	 * @return array
	 */
	public function inject_review_tab( $items, $course_id ) {
		$post_type = get_post_type( $course_id );

		if ( ! Functions::isEnableReviewByPostType( $post_type ) ) {
			return $items;
		}

		$p_meta = Functions::getMetaByPostType( $post_type );
		if ( empty( $p_meta['rtrs_support'][0] ) ) {
			return $items;
		}

		if ( isset( $items['reviews'] ) ) {
			return $items;
		}

		$items['reviews'] = [
			'title'  => __( 'Reviews', 'review-schema' ),
			'method' => [ $this, 'render_review_tab' ],
		];

		return $items;
	}

	/**
	 * Render review-schema review template inside Tutor's tab.
	 *
	 * @return void
	 */
	public function render_review_tab() {
		wp_enqueue_style( 'rtrs-app' );
		wp_enqueue_script( 'rtrs-app' );

		global $wp_query;
		$post_id  = get_the_ID();
		$comments = get_comments(
			[
				'post_id' => $post_id,
				'type'    => 'review',
				'status'  => 'approve',
			]
		);

		$wp_query->comments      = $comments;
		$wp_query->comment_count = count( $comments );

		Functions::get_template_part( 'reviews' );
	}
}
