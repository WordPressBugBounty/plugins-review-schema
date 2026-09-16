<?php
/**
 * Title & Meta SEO analyzer (deterministic baseline).
 *
 * Produces a stable 0–100 score for one post from presence + length checks on
 * the resolved SEO title and meta description. This baseline always runs
 * server-side, free, with no API key and no AI. The optional AI refinement
 * (TitleMetaAiRefiner) layers on top of the object returned here; it never
 * replaces this deterministic result.
 *
 * Return is the single source of truth for both the row score and its issues:
 *   {
 *     dimension: 'title_meta',
 *     score:     0–100 (int),
 *     status:    'pass' | 'warn' | 'fail',
 *     scored_by: 'deterministic' | 'ai',
 *     measured:  { title, title_length, meta, meta_length, title_source, meta_source, title_has_hook },
 *     issues:    [ { message, severity, points, effort } ]
 *   }
 *
 * @package Rtrs\Modules\Seo\Analyzers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analyzers;

use Rtrs\Modules\Seo\Helpers\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TitleMetaAnalyzer
 */
class TitleMetaAnalyzer {

	const TITLE_MIN = 30;
	const TITLE_MAX = 60;
	const DESC_MIN  = 120;
	const DESC_MAX  = 160;

	/**
	 * Analyze the Title & Meta dimension for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Normalized dimension object (see class docblock).
	 */
	public static function analyze( $post_id ) {
		$title_res = SeoMeta::resolve_title( (int) $post_id );
		$meta_res  = SeoMeta::resolve_description( (int) $post_id );

		return self::build(
			(string) $title_res['value'],
			(string) $meta_res['value'],
			$title_res['source'],
			$meta_res['source']
		);
	}

	/**
	 * Analyze provided title/meta strings directly (live, unsaved values).
	 *
	 * Used by the live "on change" path so the editor can score the values it
	 * currently shows, without a save, through the SAME scoring as analyze().
	 *
	 * @param string $title Resolved (rendered) SEO title.
	 * @param string $meta  Resolved (rendered) meta description.
	 * @return array Normalized dimension object (scored_by='deterministic').
	 */
	public static function analyze_strings( $title, $meta ) {
		return self::build( (string) $title, (string) $meta, 'live', 'live' );
	}

	/**
	 * Score a title + meta pair and build the normalized dimension object.
	 *
	 * @param string $title        Rendered SEO title.
	 * @param string $meta         Rendered meta description.
	 * @param string $title_source Source label for the title.
	 * @param string $meta_source  Source label for the meta.
	 * @return array
	 */
	private static function build( $title, $meta, $title_source, $meta_source ) {
		$title_len = self::length( $title );
		$meta_len  = self::length( $meta );

		$issues = [];

		$apply_title = [ 'target' => 'title' ];
		$apply_meta  = [ 'target' => 'meta' ];

		// --- Title presence (25) + length (25). Length needs presence first. ---
		if ( 0 === $title_len ) {
			$title_points = 0;
			$issues[]     = self::issue(
				__( 'No SEO title is set.', 'review-schema' ),
				'critical',
				-50,
				'low',
				$apply_title
			);
		} else {
			$title_points = 25; // presence.
			$len_points   = self::score_length( $title_len, self::TITLE_MIN, self::TITLE_MAX );
			$title_points += $len_points;
			if ( $len_points < 25 ) {
				if ( $title_len > self::TITLE_MAX ) {
					$issues[] = self::issue(
						/* translators: %d: title length in characters. */
						sprintf( __( 'Title is %d characters; search engines truncate beyond ~60.', 'review-schema' ), $title_len ),
						'warning',
						$len_points - 25,
						'low',
						$apply_title
					);
				} else {
					$issues[] = self::issue(
						/* translators: %d: title length in characters. */
						sprintf( __( 'Title is %d characters; aim for 30–60.', 'review-schema' ), $title_len ),
						'warning',
						$len_points - 25,
						'low',
						$apply_title
					);
				}
			}
		}

		// --- Meta presence (25) + length (25). ---
		if ( 0 === $meta_len ) {
			$meta_points = 0;
			$issues[]    = self::issue(
				__( 'No meta description is set.', 'review-schema' ),
				'critical',
				-50,
				'low',
				$apply_meta
			);
		} else {
			$meta_points = 25; // presence.
			$len_points  = self::score_length( $meta_len, self::DESC_MIN, self::DESC_MAX );
			$meta_points += $len_points;
			if ( $len_points < 25 ) {
				if ( $meta_len > self::DESC_MAX ) {
					$issues[] = self::issue(
						/* translators: %d: description length in characters. */
						sprintf( __( 'Meta description is %d characters; it may be truncated beyond ~160.', 'review-schema' ), $meta_len ),
						'warning',
						$len_points - 25,
						'low',
						$apply_meta
					);
				} else {
					$issues[] = self::issue(
						/* translators: %d: description length in characters. */
						sprintf( __( 'Meta description is %d characters; aim for 120–160.', 'review-schema' ), $meta_len ),
						'warning',
						$len_points - 25,
						'low',
						$apply_meta
					);
				}
			}
		}

		$score = max( 0, min( 100, (int) round( $title_points + $meta_points ) ) );

		// Full checklist (passes + fails) for display.
		$title_present = $title_len > 0;
		$meta_present  = $meta_len > 0;
		$title_len_ok  = $title_len >= self::TITLE_MIN && $title_len <= self::TITLE_MAX;
		$meta_len_ok   = $meta_len >= self::DESC_MIN && $meta_len <= self::DESC_MAX;

		$checks = [
			self::check(
				$title_present ? __( 'SEO title is set', 'review-schema' ) : __( 'SEO title is missing', 'review-schema' ),
				$title_present ? 'pass' : 'warn'
			),
			self::check(
				$title_len_ok
					/* translators: %d: title length in characters. */
					? sprintf( __( 'Title length is ideal (%d chars)', 'review-schema' ), $title_len )
					/* translators: %d: title length in characters. */
					: ( $title_present ? sprintf( __( 'Title length is %d chars; aim for 30–60', 'review-schema' ), $title_len ) : __( 'Title length: aim for 30–60 chars', 'review-schema' ) ),
				$title_len_ok ? 'pass' : 'warn'
			),
			self::check(
				$meta_present ? __( 'Meta description is set', 'review-schema' ) : __( 'Meta description is missing', 'review-schema' ),
				$meta_present ? 'pass' : 'warn'
			),
			self::check(
				$meta_len_ok
					/* translators: %d: description length in characters. */
					? sprintf( __( 'Description length is ideal (%d chars)', 'review-schema' ), $meta_len )
					/* translators: %d: description length in characters. */
					: ( $meta_present ? sprintf( __( 'Description length is %d chars; aim for 120–160', 'review-schema' ), $meta_len ) : __( 'Description length: aim for 120–160 chars', 'review-schema' ) ),
				$meta_len_ok ? 'pass' : 'warn'
			),
		];

		// CTR hooks: numbers and power words lift click-through in the SERP.
		// Display-only guidance; these do not change the deterministic score.
		$title_has_hook = $title_present ? self::has_ctr_hook( $title ) : false;
		if ( $title_present ) {
			$checks[] = self::check(
				$title_has_hook
					? __( 'Title includes a number or power word (higher CTR)', 'review-schema' )
					: __( 'Add a number or power word to the title for higher CTR', 'review-schema' ),
				$title_has_hook ? 'pass' : 'warn'
			);
			if ( self::is_shouting( $title ) ) {
				$checks[] = self::check(
					__( 'Title is in all caps; use normal capitalization', 'review-schema' ),
					'warn'
				);
			}
		}

		return [
			'dimension' => 'title_meta',
			'score'     => $score,
			'status'    => self::band( $score ),
			'scored_by' => 'deterministic',
			'measured'  => [
				'title'        => $title,
				'title_length' => $title_len,
				'meta'         => $meta,
				'meta_length'  => $meta_len,
				'title_source' => $title_source,
				'meta_source'  => $meta_source,
				'title_has_hook' => $title_has_hook,
			],
			'issues'    => $issues,
			'checks'    => $checks,
		];
	}

	/**
	 * Build a checklist entry.
	 *
	 * @param string $label  Human label.
	 * @param string $status 'pass' | 'warn'.
	 * @return array
	 */
	private static function check( $label, $status ) {
		return [
			'label'  => $label,
			'status' => $status,
		];
	}

	/**
	 * Score a length value out of 25.
	 *
	 * Full marks inside the ideal band; ~12 just outside it, scaling down to ~4
	 * far outside. Character count only — pixel-width is a future refinement.
	 *
	 * @param int $len Measured length in characters.
	 * @param int $min Ideal minimum.
	 * @param int $max Ideal maximum.
	 * @return int 0–25.
	 */
	private static function score_length( $len, $min, $max ) {
		if ( $len <= 0 ) {
			return 0;
		}
		if ( $len >= $min && $len <= $max ) {
			return 25;
		}
		if ( $len < $min ) {
			$ratio = $len / $min; // 0..1 (1 at the lower edge).
			return (int) round( 4 + 8 * $ratio ); // ~4..12.
		}
		// len > max.
		$ratio = max( 0, 1 - ( ( $len - $max ) / $max ) ); // 1 at max, 0 at 2×max.
		return (int) round( 4 + 8 * $ratio ); // ~12 just over … 4 far over.
	}

	/**
	 * Map a 0–100 score to an explicit band so the icon and issues agree.
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
	 * Multibyte-safe character length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * Whether a title carries a click-through hook: a number or a power word.
	 *
	 * @param string $title Title text.
	 * @return bool
	 */
	private static function has_ctr_hook( $title ) {
		$title = (string) $title;
		if ( '' === trim( $title ) ) {
			return false;
		}
		if ( preg_match( '/\d/', $title ) ) {
			return true;
		}

		$power = [
			'best', 'top', 'guide', 'how', 'why', 'ultimate', 'free', 'proven',
			'easy', 'essential', 'complete', 'new', 'fast', 'simple', 'tips',
			'review', 'vs', 'checklist', 'step', 'ways', 'secrets',
		];
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			return false;
		}
		foreach ( $power as $word ) {
			if ( in_array( $word, $words, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a title is written entirely in upper case ("shouting").
	 *
	 * Ignores titles with too few letters to judge reliably.
	 *
	 * @param string $title Title text.
	 * @return bool
	 */
	private static function is_shouting( $title ) {
		$title   = (string) $title;
		$letters = preg_replace( '/[^\p{L}]+/u', '', $title );
		if ( self::length( (string) $letters ) < 8 ) {
			return false;
		}
		$upper = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $title ) : strtoupper( $title );
		return $title === $upper;
	}

	/**
	 * Build a normalized issue row.
	 *
	 * @param string     $message  Human message.
	 * @param string     $severity 'critical' | 'warning' | 'info'.
	 * @param int        $points   Points impact (negative = lost).
	 * @param string     $effort   'low' | 'medium' | 'high'.
	 * @param array|null $apply    Optional "Apply with AI" hint, e.g. [ 'target' => 'title'|'meta' ].
	 * @return array
	 */
	private static function issue( $message, $severity, $points, $effort, $apply = null ) {
		$issue = [
			'message'  => $message,
			'severity' => $severity,
			'points'   => (int) $points,
			'effort'   => $effort,
		];
		if ( null !== $apply ) {
			$issue['apply'] = $apply;
		}
		return $issue;
	}
}
