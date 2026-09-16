<?php
/**
 * Score → grade / status helpers, shared by every analysis channel.
 *
 * @package Rtrs\Modules\Seo\Analysis
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Grade
 *
 * Thresholds intentionally match the frontend D5 renderer (qGrade in
 * src/js/admin.js) so the server and UI agree on letter grades.
 */
class Grade {

	/**
	 * Highest score a purely deterministic channel (SEO / AEO / GEO) may report.
	 *
	 * A perfect 100 is reserved for content that has passed an AI review, so the
	 * baseline analysis tops out well below it — leaving a gap of more than 5
	 * points that only an AI review can close, which keeps "Review with AI"
	 * meaningful. NOTE: the Schema (Structured Data Health) channel is NOT
	 * subject to this cap — it has no AI review and may legitimately reach 100.
	 */
	const DETERMINISTIC_MAX = 90;

	/**
	 * Clamp a deterministic channel score to DETERMINISTIC_MAX so it never
	 * reports a perfect 100 without an AI review. Applied by the SEO/AEO/GEO
	 * analyzers only.
	 *
	 * @param int $score Raw 0-100 score.
	 * @return int Score clamped to DETERMINISTIC_MAX.
	 */
	public static function cap_deterministic( $score ) {
		return min( (int) $score, self::DETERMINISTIC_MAX );
	}

	/**
	 * Hint shown on a channel that has maxed out the deterministic checks but has
	 * not yet been AI-reviewed — an AI review is the only way to reach a perfect
	 * 100. Returns an empty string when the channel still has room to improve on
	 * its own (so the report only nudges toward AI once everything else is good).
	 *
	 * @param int $score Deterministic channel score (already capped).
	 * @return string Hint text, or '' when not applicable.
	 */
	public static function ai_full_marks_hint( $score ) {
		return (int) $score >= self::DETERMINISTIC_MAX
			? __( 'Add an AI review and apply its suggested fixes — it is deeper and more analytical, and boosts your score.', 'review-schema' )
			: '';
	}

	/**
	 * Convert a 0-100 score to a letter grade.
	 *
	 * @param int $score Score 0-100.
	 * @return string Grade label (A+, A, B, C, D).
	 */
	public static function from_score( $score ) {
		$score = (int) $score;
		if ( $score >= 95 ) {
			return 'A+';
		}
		if ( $score >= 90 ) {
			return 'A';
		}
		if ( $score >= 80 ) {
			return 'B';
		}
		if ( $score >= 65 ) {
			return 'C';
		}
		return 'D';
	}

	/**
	 * Map a score to the frontend status token.
	 *
	 * @param int $score Score 0-100.
	 * @return string 'ok' or 'warn'.
	 */
	public static function status( $score ) {
		return (int) $score >= 65 ? 'ok' : 'warn';
	}
}
