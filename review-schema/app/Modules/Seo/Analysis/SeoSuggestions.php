<?php
/**
 * SEO suggestion prioritization + capping.
 *
 * The SEO panel shows a bounded list of suggestions. Ranking by severity alone
 * let low-severity but AI-actionable rows (those carrying an "Apply with AI"
 * `apply` hint, e.g. title/meta length) get sliced out once Pro adds its own
 * higher-severity Keyword/Performance suggestions — dropping the very button a
 * user could click to fix the issue.
 *
 * This shared helper ranks by severity, then keeps actionable (apply-capable)
 * suggestions ahead of non-actionable ones of the same severity, so the fixable
 * rows survive the cap. Kept in the free plugin so Pro reuses the same contract.
 *
 * @package Rtrs\Modules\Seo\Analysis
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoSuggestions
 */
class SeoSuggestions {

	/**
	 * Default number of suggestions the panel renders.
	 */
	const CAP = 6;

	/**
	 * Select the suggestions to show, keeping AI-actionable rows through the cap.
	 *
	 * Actionable rows (those carrying an `apply.target` "Apply with AI" hint) are
	 * reserved first so their button is never sliced out by higher-severity but
	 * non-actionable rows — the bug where a `med` title/meta fix vanished once Pro
	 * added `high` Keyword/Performance suggestions. Remaining slots are filled by
	 * severity. The final list is ordered by severity for display, with actionable
	 * rows ahead of non-actionable ones at the same severity.
	 *
	 * @param array[] $suggestions List of suggestion rows.
	 * @param int     $cap         Maximum rows to keep. 0 or negative = no cap.
	 * @return array[]
	 */
	public static function prioritize( $suggestions, $cap = self::CAP ) {
		if ( ! is_array( $suggestions ) ) {
			return [];
		}

		$actionable = [];
		$rest       = [];
		foreach ( array_values( $suggestions ) as $i => $suggestion ) {
			$pair = [ $i, $suggestion ];
			if ( isset( $suggestion['apply']['target'] ) && '' !== $suggestion['apply']['target'] ) {
				$actionable[] = $pair;
			} else {
				$rest[] = $pair;
			}
		}

		if ( $cap > 0 ) {
			// Reserve actionable rows first; fill the remainder with the rest by
			// severity. If actionable alone exceeds the cap, keep the strongest.
			self::sort_by_severity( $actionable );
			self::sort_by_severity( $rest );

			if ( count( $actionable ) >= $cap ) {
				$kept = array_slice( $actionable, 0, $cap );
			} else {
				$kept = array_merge( $actionable, array_slice( $rest, 0, $cap - count( $actionable ) ) );
			}
		} else {
			$kept = array_merge( $actionable, $rest );
		}

		// Order the kept set for display: severity first, actionable ahead of
		// non-actionable at the same severity, original order as the tiebreak.
		usort(
			$kept,
			static function ( $a, $b ) {
				$cmp = self::rank( $a[1] ) <=> self::rank( $b[1] );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				$cmp = self::actionable_rank( $a[1] ) <=> self::actionable_rank( $b[1] );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return $a[0] <=> $b[0];
			}
		);

		$out = [];
		foreach ( $kept as $pair ) {
			$out[] = $pair[1];
		}

		return $out;
	}

	/**
	 * Sort a decorated [ index, suggestion ] list by severity (stable).
	 *
	 * @param array[] $pairs Decorated list, mutated in place.
	 * @return void
	 */
	private static function sort_by_severity( &$pairs ) {
		usort(
			$pairs,
			static function ( $a, $b ) {
				$cmp = self::rank( $a[1] ) <=> self::rank( $b[1] );
				return 0 !== $cmp ? $cmp : $a[0] <=> $b[0];
			}
		);
	}

	/**
	 * Severity rank (lower = shown first).
	 *
	 * @param array $suggestion Suggestion row.
	 * @return int
	 */
	private static function rank( $suggestion ) {
		$sev  = isset( $suggestion['sev'] ) ? $suggestion['sev'] : '';
		$rank = [ 'high' => 0, 'med' => 1 ];
		return isset( $rank[ $sev ] ) ? $rank[ $sev ] : 2;
	}

	/**
	 * Actionability rank (0 = has an Apply-with-AI target, 1 = none).
	 *
	 * @param array $suggestion Suggestion row.
	 * @return int
	 */
	private static function actionable_rank( $suggestion ) {
		return ( isset( $suggestion['apply']['target'] ) && '' !== $suggestion['apply']['target'] ) ? 0 : 1;
	}
}
