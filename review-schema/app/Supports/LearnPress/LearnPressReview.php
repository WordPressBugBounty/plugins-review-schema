<?php
/**
 * LearnPress Review Integration
 *
 * LearnPress modern layout loads comments via AJAX where is_singular()
 * returns false, breaking ReviewFrontend's template replacement.
 *
 * This class replaces LearnPress's AJAX-loaded comment section with
 * review-schema's review template rendered server-side.
 *
 * @package Rtrs\Supports\LearnPress
 */

namespace Rtrs\Supports\LearnPress;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LearnPressReview {

	use SingletonTrait;

	private function __construct() {
		// Replace the comment section in LearnPress modern layout
		// with review-schema's server-side rendered review template.
		add_filter( 'learn-press/single-course/modern/section_left', [ $this, 'replace_comment_section' ], 10, 3 );

		// For classic layout: set up $wp_query->comments with review-type
		// comments before reviews.php loads, so have_comments() works.
		add_filter( 'comments_template', [ $this, 'setup_review_comments' ], 98 );

		// Force comments open for lp_course so comment_form() works.
		add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );
	}

	/**
	 * Replace LP's AJAX comment section with review-schema template.
	 *
	 * @param array  $sections Section components.
	 * @param object $course   Course model.
	 * @param object $user     User model.
	 *
	 * @return array
	 */
	public function replace_comment_section( $sections, $course = null, $user = null ) {
		if ( ! isset( $sections['comment'] ) ) {
			return $sections;
		}

		$p_meta = Functions::getMetaByPostType( 'lp_course' );

		// If no config exists, leave LP's default untouched.
		if ( empty( $p_meta ) ) {
			return $sections;
		}

		if ( ! empty( $p_meta['rtrs_support'][0] ) ) {
			// Review enabled — render review-schema template.
			$sections['comment'] = $this->render_review_html();
		} else {
			// Review disabled — render default comments server-side
			// to avoid LP's AJAX context breaking comment_form().
			$sections['comment'] = $this->render_default_comments();
		}

		return $sections;
	}

	/**
	 * Set up $wp_query->comments with review-type comments for lp_course.
	 *
	 * In LP's classic layout, comments_template() queries all comment types.
	 * The reviews.php template needs review-type comments in $wp_query
	 * for have_comments() and the review list to work correctly.
	 *
	 * Runs at priority 98 (before ReviewFrontend's priority 99 replaces the template).
	 *
	 * @param string $comment_template Template path.
	 *
	 * @return string
	 */
	public function setup_review_comments( $comment_template ) {
		global $post, $wp_query;

		if ( ! is_singular( 'lp_course' ) ) {
			return $comment_template;
		}

		$p_meta = Functions::getMetaByPostType( 'lp_course' );
		if ( empty( $p_meta ) || empty( $p_meta['rtrs_support'][0] ) ) {
			return $comment_template;
		}

		$post_id  = $post->ID;
		$comments = get_comments( [
			'post_id' => $post_id,
			'type'    => 'review',
			'status'  => 'approve',
		] );

		$wp_query->comments      = $comments;
		$wp_query->comment_count = count( $comments );

		return $comment_template;
	}

	/**
	 * Render review-schema review template and return HTML.
	 *
	 * @return string
	 */
	private function render_review_html() {
		wp_enqueue_style( 'rtrs-app' );
		wp_enqueue_script( 'rtrs-app' );

		// Set up the comment query so have_comments() works.
		global $wp_query;
		$post_id  = get_the_ID();
		$comments = get_comments( [
			'post_id' => $post_id,
			'type'    => 'review',
			'status'  => 'approve',
		] );

		$wp_query->comments      = $comments;
		$wp_query->comment_count = count( $comments );

		ob_start();
		Functions::get_template_part( 'reviews' );
		return ob_get_clean();
	}

	/**
	 * Render default WordPress comments server-side.
	 *
	 * LP's AJAX comment loading breaks when ReviewFrontend's
	 * comment_form filter calls getMetaByPostType() which corrupts
	 * the global $post. Rendering server-side bypasses the AJAX flow.
	 *
	 * @return string
	 */
	private function render_default_comments() {
		global $withcomments, $post;

		$saved_post   = $post;
		$post         = get_post( get_the_ID() );
		$withcomments = true;

		ob_start();
		add_filter( 'deprecated_file_trigger_error', '__return_false' );
		comments_template();
		remove_filter( 'deprecated_file_trigger_error', '__return_false' );
		$html = ob_get_clean();

		$post = $saved_post;

		return $html;
	}

	/**
	 * Force comments open on lp_course so comment_form() works.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 *
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( get_post_type( $post_id ) !== 'lp_course' ) {
			return $open;
		}

		$p_meta = Functions::getMetaByPostType( 'lp_course' );
		if ( ! empty( $p_meta ) && ! empty( $p_meta['rtrs_support'][0] ) ) {
			return true;
		}

		return $open;
	}
}
