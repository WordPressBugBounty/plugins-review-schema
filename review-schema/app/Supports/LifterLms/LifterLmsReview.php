<?php
/**
 * LifterLMS Review Integration
 *
 * WordPress's comments_template() loads all comment types into
 * $wp_query->comments, but reviews.php relies on have_comments()
 * to decide whether to render the review list. When LifterLMS
 * courses have no standard comments, have_comments() returns false
 * and the review list is skipped (even though review-type comments
 * exist in the database).
 *
 * This class seeds $wp_query->comments with review-type comments
 * before reviews.php loads, and forces comments open so the
 * comment_form() works on courses where LifterLMS defaults
 * comment_status to closed.
 *
 * @package Rtrs\Supports\LifterLms
 */

namespace Rtrs\Supports\LifterLms;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LifterLmsReview {

	use SingletonTrait;

	private function __construct() {
		// Force comments open on course post type so comment_form() works.
		add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );

		// Set up $wp_query->comments with review-type comments
		// before ReviewFrontend's comment_template (priority 99) loads reviews.php.
		add_filter( 'comments_template', [ $this, 'setup_review_comments' ], 98 );

		// Remove LifterLMS built-in review section when Review Schema reviews are active.
		add_action( 'wp', [ $this, 'maybe_remove_llms_reviews' ] );
	}

	/**
	 * Remove LifterLMS built-in review section to avoid duplication.
	 *
	 * The review template is already loaded via comments_template() flow,
	 * so LifterLMS's own review output is not needed.
	 *
	 * @return void
	 */
	public function maybe_remove_llms_reviews() {
		if ( ! is_singular( 'course' ) ) {
			return;
		}

		if ( ! Functions::isEnableReviewByPostType( 'course' ) ) {
			return;
		}

		$p_meta = Functions::getMetaByPostType( 'course' );
		if ( ! empty( $p_meta['rtrs_support'][0] ) ) {
			remove_action( 'lifterlms_single_course_after_summary', 'lifterlms_template_single_reviews', 100 );
		}
	}

	/**
	 * Set up $wp_query->comments with review-type comments for course.
	 *
	 * Runs at priority 98 on comments_template filter (before
	 * ReviewFrontend's priority 99 which replaces the template
	 * with reviews.php). This ensures have_comments() returns true
	 * when review-type comments exist.
	 *
	 * @param string $comment_template Template path.
	 *
	 * @return string
	 */
	public function setup_review_comments( $comment_template ) {
		global $post, $wp_query;

		if ( ! is_singular( 'course' ) ) {
			return $comment_template;
		}

		$p_meta = Functions::getMetaByPostType( 'course' );
		if ( empty( $p_meta ) || empty( $p_meta['rtrs_support'][0] ) ) {
			return $comment_template;
		}

		$comments = get_comments( [
			'post_id' => $post->ID,
			'type'    => 'review',
			'status'  => 'approve',
		] );

		$wp_query->comments      = $comments;
		$wp_query->comment_count = count( $comments );

		return $comment_template;
	}

	/**
	 * Force comments open on course so comment_form() works.
	 *
	 * LifterLMS defaults comment_status to 'closed' on courses.
	 * Without this, the theme may skip comments_template() entirely
	 * and ReviewFrontend's comment_template filter won't fire.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 *
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( get_post_type( $post_id ) !== 'course' ) {
			return $open;
		}

		$p_meta = Functions::getMetaByPostType( 'course' );
		if ( ! empty( $p_meta ) && ! empty( $p_meta['rtrs_support'][0] ) ) {
			return true;
		}

		return $open;
	}
}
