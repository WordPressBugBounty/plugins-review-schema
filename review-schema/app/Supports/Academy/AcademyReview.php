<?php
/**
 * Academy LMS Review Integration
 *
 * Academy LMS renders its own "Student Feedback" summary and review list
 * via hooks on `academy/templates/single_course_content` (priorities 35 & 40).
 *
 * When review-schema reviews are enabled for academy_courses, this class:
 * - Removes Academy's "Student Feedback" summary section.
 * - Removes Academy's review list and renders review-schema's template instead.
 * - Forces comments open so comment_form() renders on courses.
 * - Syncs review data to Academy's format (comment_type + academy_rating meta)
 *   so Academy retains correct data if review-schema is later disabled.
 *
 * @package Rtrs\Supports\Academy
 */

namespace Rtrs\Supports\Academy;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AcademyReview {

	use SingletonTrait;

	private function __construct() {
		add_action( 'wp', [ $this, 'maybe_replace_academy_reviews' ] );
		add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );

		// Sync review-schema review data to Academy's format.
		add_action( 'rtrs_avg_rating_meta_save', [ $this, 'sync_review_to_academy' ], 10, 3 );

		// Adjust review-schema AJAX queries to find academy_courses comment type.
		add_filter( 'rtrs_review_sort_args', [ $this, 'adjust_review_query_type' ], 10, 2 );
	}

	/**
	 * Remove Academy's feedback and review sections, add review-schema's template.
	 *
	 * @return void
	 */
	public function maybe_replace_academy_reviews() {
		if ( ! is_singular( 'academy_courses' ) ) {
			return;
		}

		if ( ! Functions::isEnableReviewByPostType( 'academy_courses' ) ) {
			return;
		}

		$p_meta = Functions::getMetaByPostType( 'academy_courses' );
		if ( ! empty( $p_meta['rtrs_support'][0] ) ) {
			// Remove "Student Feedback" summary section.
			remove_action( 'academy/templates/single_course_content', 'academy_single_course_feedback', 35 );

			// Replace Academy's review list with review-schema's template.
			remove_action( 'academy/templates/single_course_content', 'academy_single_course_reviews', 40 );
			add_action( 'academy/templates/single_course_content', [ $this, 'render_review_template' ], 40 );
		}
	}

	/**
	 * Render review-schema's review template directly.
	 *
	 * @return void
	 */
	public function render_review_template() {
		wp_enqueue_style( 'rtrs-app' );
		wp_enqueue_script( 'rtrs-app' );

		global $wp_query;
		$post_id  = get_the_ID();
		$comments = get_comments( [
			'post_id' => $post_id,
			'type'    => 'academy_courses',
			'status'  => 'approve',
		] );

		$wp_query->comments      = $comments;
		$wp_query->comment_count = count( $comments );

		Functions::get_template_part( 'reviews' );
	}

	/**
	 * Sync review-schema review data to Academy's format.
	 *
	 * Saves `academy_rating` meta and sets comment_type to `academy_courses`
	 * so Academy's native queries find the reviews when review-schema is disabled.
	 *
	 * @param float $avg_rating Average rating for the post.
	 * @param int   $comment_id Comment ID.
	 * @param int   $post_id    Post ID.
	 *
	 * @return void
	 */
	public function sync_review_to_academy( $avg_rating, $comment_id, $post_id ) {
		if ( 'academy_courses' !== get_post_type( $post_id ) ) {
			return;
		}

		$rating = get_comment_meta( $comment_id, 'rating', true );
		if ( ! $rating ) {
			return;
		}

		// Save rating in Academy's meta key format.
		update_comment_meta( $comment_id, 'academy_rating', absint( round( $rating ) ) );

		// Set comment_type to Academy's format so Academy's native queries
		// find the reviews when review-schema is disabled.
		wp_update_comment( [
			'comment_ID'   => $comment_id,
			'comment_type' => 'academy_courses',
		] );
	}

	/**
	 * Adjust review-schema AJAX review queries for academy_courses.
	 *
	 * Review-schema queries with type=review, but for academy courses
	 * the comment_type is set to academy_courses. This filter updates
	 * the query type so AJAX pagination/sorting works correctly.
	 *
	 * @param array  $args    Comment query args.
	 * @param string $sort_by Sort by value.
	 *
	 * @return array
	 */
	public function adjust_review_query_type( $args, $sort_by ) {
		if ( empty( $args['post_id'] ) ) {
			return $args;
		}

		if ( 'academy_courses' === get_post_type( $args['post_id'] ) ) {
			$args['type'] = 'academy_courses';
		}

		return $args;
	}

	/**
	 * Force comments open on academy_courses so comment_form() works.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 *
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( get_post_type( $post_id ) !== 'academy_courses' ) {
			return $open;
		}

		$p_meta = Functions::getMetaByPostType( 'academy_courses' );
		if ( ! empty( $p_meta ) && ! empty( $p_meta['rtrs_support'][0] ) ) {
			return true;
		}

		return $open;
	}
}
