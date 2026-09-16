<?php
/**
 * Internal Links SEO analyzer (deterministic).
 *
 * Single source of truth for the internal-links dimension: scores the number of
 * internal links relative to content length, flagging pages with no internal
 * links, too few for their length, or excessive linking. Returns the same
 * normalized object shape as the other dimensions:
 *   {
 *     dimension: 'internal_links',
 *     score:     0–100 (int),
 *     status:    'pass' | 'warn' | 'fail',
 *     scored_by: 'deterministic',
 *     measured:  { internal_links, external_links, word_count, recommended, generic_anchors },
 *     issues:    [ { message, severity, points, effort } ]
 *   }
 *
 * @package Rtrs\Modules\Seo\Analyzers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analyzers;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InternalLinksAnalyzer
 */
class InternalLinksAnalyzer {

	/**
	 * Roughly one internal link per this many words is a healthy target.
	 */
	const WORDS_PER_LINK = 300;

	/**
	 * Analyze the Internal Links dimension for a post.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $content Optional live (unsaved) content to score
	 *                             instead of the saved post content.
	 * @return array Normalized dimension object.
	 */
	public static function analyze( $post_id, $content = null ) {
		return self::analyze_context( new AnalysisContext( (int) $post_id, $content ) );
	}

	/**
	 * Analyze using an existing (shared) AnalysisContext.
	 *
	 * @param AnalysisContext $context Shared content context.
	 * @return array Normalized dimension object.
	 */
	public static function analyze_context( AnalysisContext $context ) {
		$internal = $context->internal_link_count();
		$external = $context->external_link_count();
		$words    = $context->word_count();
		$target   = max( 1, (int) floor( $words / self::WORDS_PER_LINK ) );

		$score  = 100;
		$issues = [];

		// The "Improve with AI" action lives on the too-few / none suggestion (it
		// proposes anchor → existing-page pairs). Over-linking and generic-anchor
		// issues are excluded — adding links is not their fix.
		$link_apply = [ 'target' => 'internal_links' ];

		if ( 0 === $internal ) {
			$score   -= 45;
			$issues[] = self::issue(
				__( 'No internal links found.', 'review-schema' ),
				'warning',
				-45,
				'low',
				$link_apply
			);
		} elseif ( $internal < $target ) {
			$score   -= 18;
			$issues[] = self::issue(
				/* translators: 1: current internal link count, 2: recommended count. */
				sprintf( __( 'Only %1$d internal link(s); aim for around %2$d for this length.', 'review-schema' ), $internal, $target ),
				'warning',
				-18,
				'low',
				$link_apply
			);
		}

		// Over-linking guard: more than ~1 link per 20 words is excessive.
		if ( $words > 0 && $internal > max( 5, (int) floor( $words / 20 ) ) ) {
			$score   -= 10;
			$issues[] = self::issue(
				__( 'Very high internal link density; may dilute link value.', 'review-schema' ),
				'warning',
				-10,
				'low'
			);
		}

		// Generic anchor text ("click here", "read more") wastes ranking signal.
		$generic_anchors = self::count_generic_anchors( $context->content() );
		if ( $generic_anchors > 0 ) {
			$score   -= 8;
			$issues[] = self::issue(
				__( 'Some internal links use generic anchor text such as "click here" or "read more". Use descriptive, keyword-rich anchors.', 'review-schema' ),
				'warning',
				-8,
				'low'
			);
		}

		$score = max( 0, min( 100, (int) $score ) );

		// Full checklist (passes + fails) for display.
		$has_links    = $internal > 0;
		$enough_links = $internal >= $target;
		$not_over     = ! ( $words > 0 && $internal > max( 5, (int) floor( $words / 20 ) ) );

		if ( ! $has_links ) {
			/* translators: %d: recommended internal link count. */
			$count_label = sprintf( __( 'No internal links found. Aim for about %d.', 'review-schema' ), $target );
		} elseif ( ! $enough_links ) {
			/* translators: 1: current internal link count, 2: recommended count. */
			$count_label = sprintf( __( 'Only %1$d internal link(s) found. Aim for about %2$d.', 'review-schema' ), $internal, $target );
		} else {
			/* translators: %d: recommended internal link count. */
			$count_label = sprintf( __( 'Enough internal links for the length (≥%d)', 'review-schema' ), $target );
		}

		$checks = [
			self::check( $count_label, $enough_links ? 'pass' : 'warn' ),
		];

		// Link density only applies when links exist — with zero links it is
		// neither healthy nor excessive, so the row would be misleading.
		if ( $has_links ) {
			$checks[] = self::check(
				$not_over ? __( 'Link density is healthy', 'review-schema' ) : __( 'Very high internal link density', 'review-schema' ),
				$not_over ? 'pass' : 'warn'
			);
			$checks[] = self::check(
				0 === $generic_anchors ? __( 'Internal link anchors are descriptive', 'review-schema' ) : __( 'Some internal links use generic anchor text', 'review-schema' ),
				0 === $generic_anchors ? 'pass' : 'warn'
			);
		}

		return [
			'dimension' => 'internal_links',
			'score'     => $score,
			'status'    => self::band( $score ),
			'scored_by' => 'deterministic',
			'measured'  => [
				'internal_links' => $internal,
				'external_links' => $external,
				'word_count'     => $words,
				'recommended'    => $target,
				'generic_anchors' => $generic_anchors,
			],
			'issues'    => $issues,
			'checks'    => $checks,
		];
	}

	/**
	 * Build a checklist entry.
	 *
	 * @param string     $label  Human label.
	 * @param string     $status 'pass' | 'warn'.
	 * @param array|null $apply  Optional "Improve with AI" hint, e.g. [ 'target' => 'internal_links' ].
	 * @return array
	 */
	private static function check( $label, $status, $apply = null ) {
		$row = [
			'label'  => $label,
			'status' => $status,
		];

		if ( null !== $apply ) {
			$row['apply'] = $apply;
		}

		return $row;
	}

	/**
	 * Count internal links that use non-descriptive (generic) anchor text.
	 *
	 * Descriptive, keyword-rich anchors pass ranking signal to the target page;
	 * generic phrases like "click here" or "read more" waste it. Only internal
	 * links (home-relative or same-host) are inspected.
	 *
	 * @param string $html Rendered post HTML.
	 * @return int Number of internal links with generic anchor text.
	 */
	private static function count_generic_anchors( $html ) {
		$home    = home_url();
		$generic = [
			'click here',
			'here',
			'read more',
			'more',
			'this',
			'this page',
			'this link',
			'link',
			'continue reading',
			'read',
			'learn more',
			'find out more',
			'see more',
			'go',
		];

		$count = 0;
		if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', (string) $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url         = $match[1];
				$is_internal = ( 0 === strpos( $url, $home ) ) || ( 0 === strpos( $url, '/' ) );
				if ( ! $is_internal ) {
					continue;
				}

				$text = wp_strip_all_tags( $match[2] );
				$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
				$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
				$text = trim( $text, " \t\n\r\0\x0B.!?,:;" );

				if ( '' !== $text && in_array( $text, $generic, true ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * Map a 0–100 score to an explicit band.
	 *
	 * @param int $score Score 0–100.
	 * @return string 'pass' (>=80) | 'warn' (50–79) | 'fail' (<50).
	 */
	private static function band( $score ) {
		if ( $score >= 80 ) {
			return 'pass';
		}
		if ( $score >= 50 ) {
			return 'warn';
		}
		return 'fail';
	}

	/**
	 * Build a normalized issue row.
	 *
	 * @param string     $message  Human message.
	 * @param string     $severity 'critical' | 'warning' | 'info'.
	 * @param int        $points   Points impact (negative = lost).
	 * @param string     $effort   'low' | 'medium' | 'high'.
	 * @param array|null $apply    Optional "Improve with AI" hint carried to the suggestion.
	 * @return array
	 */
	private static function issue( $message, $severity, $points, $effort, $apply = null ) {
		$row = [
			'message'  => $message,
			'severity' => $severity,
			'points'   => (int) $points,
			'effort'   => $effort,
		];
		if ( null !== $apply ) {
			$row['apply'] = $apply;
		}
		return $row;
	}
}
