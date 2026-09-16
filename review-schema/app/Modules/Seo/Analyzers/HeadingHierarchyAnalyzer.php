<?php
/**
 * Heading Hierarchy SEO analyzer (deterministic).
 *
 * Single source of truth for the heading dimension: scores the in-content
 * heading outline on a single H1 (the post title is the implicit H1), the
 * presence of H2 subheadings, and a non-skipping level sequence. Returns the
 * same normalized object shape as the other dimensions so the SEO panel and the
 * REST endpoint always agree:
 *   {
 *     dimension: 'heading_hierarchy',
 *     score:     0–100 (int),
 *     status:    'pass' | 'warn' | 'fail',
 *     scored_by: 'deterministic',
 *     measured:  { h1_in_content, total_h1, h2_count, total_headings, skipped_levels, empty_headings, long_headings },
 *     issues:    [ { message, severity, points, effort } ]
 *   }
 *
 * @package Rtrs\Modules\Seo\Analyzers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analyzers;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\ThemeH1Detector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HeadingHierarchyAnalyzer
 */
class HeadingHierarchyAnalyzer {

	/**
	 * Headings longer than this many characters are flagged as too long.
	 */
	const HEADING_MAX = 70;

	/**
	 * Analyze the Heading Hierarchy dimension for a post.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $content Optional live (unsaved) content to score instead
	 *                             of the saved post content.
	 * @return array Normalized dimension object.
	 */
	public static function analyze( $post_id, $content = null ) {
		return self::analyze_context( new AnalysisContext( (int) $post_id, $content ) );
	}

	/**
	 * Analyze using an existing (shared) AnalysisContext.
	 *
	 * Lets SeoAnalyzer reuse the single per-request context instead of parsing
	 * the content again.
	 *
	 * @param AnalysisContext $context Shared content context.
	 * @return array Normalized dimension object.
	 */
	public static function analyze_context( AnalysisContext $context ) {
		// FAQ-block questions are concise Q&A carrying FAQPage schema, not
		// document structure — exclude them from every heading check here.
		$headings = array_values(
			array_filter(
				$context->headings(),
				static function ( $h ) {
					return empty( $h['is_faq'] );
				}
			)
		);
		$keyword  = $context->focus_keyword();

		$h1_in_content = 0;
		$h2_count      = 0;
		$h1_texts      = [];
		foreach ( $headings as $h ) {
			if ( 1 === $h['level'] ) {
				$h1_in_content++;
				$h1_texts[] = self::normalize( $h['text'] );
			} elseif ( 2 === $h['level'] ) {
				$h2_count++;
			}
		}

		$page_title   = get_the_title( $context->post_id() );
		$skip_details = self::find_skips( $headings, $page_title );
		$skips        = count( $skip_details );
		$score        = 100;
		$issues       = [];

		// Whether the post title is rendered as a *separate* H1 outside the
		// content. Classic themes output the title as the page H1, so an
		// in-content H1 becomes a second one. Page builders (Elementor, Bricks,
		// Oxygen, …) render the title H1 *inside* the content instead — as does
		// any content already carrying an H1 that matches the title. In those
		// cases the title must not be counted a second time.
		$title                = self::normalize( get_the_title( $context->post_id() ) );
		$content_has_title_h1 = '' !== $title && in_array( $title, $h1_texts, true );

		if ( 0 === $h1_in_content ) {
			// No H1 in the editor / page-builder content. Probe the rendered
			// front-end (cached per template) to see whether the theme itself
			// outputs the page H1 in its template / hero / builder chrome. Only
			// flag "missing" when the live page genuinely has none.
			$total_h1 = ThemeH1Detector::renders_h1( $context->post_id() ) ? 1 : 0;
		} else {
			// Content already carries H1(s). Count the title as a *separate* H1
			// only on classic themes that render it outside the content and
			// where the content doesn't already repeat it — otherwise a genuine
			// duplicate-H1 in the content is surfaced.
			$title_is_implicit_h1 = ! $content_has_title_h1 && ! self::is_builder_page( $context->post_id() );
			$total_h1             = $h1_in_content + ( $title_is_implicit_h1 ? 1 : 0 );
		}

		// A page should have exactly one H1. Zero (common on page-builder layouts
		// where the title H1 was deleted) and multiple both hurt SEO/accessibility.
		if ( 0 === $total_h1 ) {
			$score   -= 20;
			$issues[] = self::issue(
				__( 'Missing H1 tag (add a single H1 heading to the page).', 'review-schema' ),
				'warning',
				-20,
				'medium'
			);
		} elseif ( $total_h1 >= 2 ) {
			$score   -= 20;
			$issues[] = self::issue(
				__( 'Multiple H1 tags detected (a page should have exactly one H1).', 'review-schema' ),
				'warning',
				-20,
				'low'
			);
		}

		if ( 0 === $h2_count ) {
			$score   -= 25;
			$issues[] = self::issue(
				__( 'No H2 subheadings found.', 'review-schema' ),
				'warning',
				-25,
				'medium'
			);
		}

		if ( $skips > 0 ) {
			$lost     = min( 30, $skips * 10 );
			$score   -= $lost;
			$issue    = self::issue(
				__( 'Heading levels are skipped (e.g. H2 followed by H4).', 'review-schema' ),
				'warning',
				-$lost,
				'low'
			);
			$issue['body'] = self::skips_body( $skip_details );
			$issues[]      = $issue;
		}

		// Empty or overly long headings hurt scannability and snippet extraction.
		$empty_headings = 0;
		$long_headings  = 0;
		$long_texts     = [];
		$empty_details  = [];
		// Seeded empty (not the page title): an empty heading before any real
		// content heading reads as "at the top of the page", not "after <title>".
		$prev_label     = '';
		foreach ( $headings as $h ) {
			$text = trim( (string) $h['text'] );
			if ( '' === $text ) {
				$empty_headings++;
				// An empty heading has no text of its own, so record the heading it
				// follows (and its level) to help the author locate it.
				$empty_details[] = [
					'level' => (int) $h['level'],
					'after' => $prev_label,
				];
				continue;
			}

			if ( self::length( $text ) > self::HEADING_MAX ) {
				$long_headings++;
				$long_texts[] = $text;
			}

			$prev_label = trim( wp_strip_all_tags( $text ) );
		}

		if ( $empty_headings > 0 ) {
			$score  -= 10;
			$issue   = self::issue(
				__( 'One or more headings are empty.', 'review-schema' ),
				'warning',
				-10,
				'low'
			);
			$issue['body'] = self::empty_headings_body( $empty_details );
			$issues[]      = $issue;
		}

		if ( $long_headings > 0 ) {
			$score  -= 5;
			$issue   = self::issue(
				/* translators: %d: maximum recommended heading length in characters. */
				sprintf( __( 'One or more headings are longer than %d characters.', 'review-schema' ), self::HEADING_MAX ),
				'info',
				-5,
				'low'
			);
			$issue['body'] = self::long_headings_body( $long_texts );
			$issues[]      = $issue;
		}

		// Optional keyword-in-heading signal (only when a focus keyword exists).
		if ( '' !== $keyword && ! self::keyword_in_headings( $headings, $keyword ) ) {
			$score   -= 10;
			$issues[] = self::issue(
				/* translators: %s: focus keyword. */
				sprintf( __( 'Focus keyword "%s" is not used in any subheading.', 'review-schema' ), $keyword ),
				'info',
				-10,
				'low'
			);
		}

		$score = max( 0, min( 100, (int) $score ) );

		// Full checklist (passes + fails) for display.
		$single_h1 = 1 === $total_h1;
		$has_h2    = $h2_count > 0;
		$no_skips  = 0 === $skips;

		if ( $single_h1 ) {
			$h1_label = __( 'Single H1 (the page title)', 'review-schema' );
		} elseif ( 0 === $total_h1 ) {
			$h1_label = __( 'Missing H1 tag', 'review-schema' );
		} else {
			$h1_label = __( 'Multiple H1 tags detected', 'review-schema' );
		}

		$checks = [
			self::check(
				$h1_label,
				$single_h1 ? 'pass' : 'warn'
			),
			self::check(
				$has_h2 ? __( 'H2 subheadings present', 'review-schema' ) : __( 'No H2 subheadings found', 'review-schema' ),
				$has_h2 ? 'pass' : 'warn'
			),
			self::check(
				$no_skips ? __( 'Heading levels are not skipped', 'review-schema' ) : __( 'Heading levels are skipped', 'review-schema' ),
				$no_skips ? 'pass' : 'warn'
			),
		];

		// Empty / long heading rows only apply when the content has headings.
		if ( ! empty( $headings ) ) {
			$checks[] = self::check(
				0 === $empty_headings ? __( 'No empty headings', 'review-schema' ) : __( 'Empty headings found', 'review-schema' ),
				0 === $empty_headings ? 'pass' : 'warn'
			);
			$checks[] = self::check(
				0 === $long_headings
					? __( 'Heading lengths are concise', 'review-schema' )
					/* translators: %d: maximum recommended heading length in characters. */
					: sprintf( __( 'Some headings exceed %d characters', 'review-schema' ), self::HEADING_MAX ),
				0 === $long_headings ? 'pass' : 'warn'
			);
		}

		if ( '' !== $keyword ) {
			$kw_in_heading = self::keyword_in_headings( $headings, $keyword );
			$checks[]      = self::check(
				$kw_in_heading ? __( 'Focus keyword used in a subheading', 'review-schema' ) : __( 'Focus keyword not in any subheading', 'review-schema' ),
				$kw_in_heading ? 'pass' : 'warn'
			);
		}

		return [
			'dimension' => 'heading_hierarchy',
			'score'     => $score,
			'status'    => self::band( $score ),
			'scored_by' => 'deterministic',
			'measured'  => [
				'h1_in_content'  => $h1_in_content,
				'total_h1'       => $total_h1,
				'h2_count'       => $h2_count,
				'total_headings' => count( $headings ),
				'skipped_levels' => $skips,
				'empty_headings' => $empty_headings,
				'long_headings'  => $long_headings,
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
	 * Whether the post is rendered by a page builder that owns the whole
	 * template — so the post title is output as an H1 *inside* the content
	 * rather than as a separate theme-rendered H1.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function is_builder_page( $post_id ) {
		// Elementor.
		if ( \Rtrs\Modules\Schema\Hooks\ElementorFaq::is_elementor_post( $post_id ) ) {
			return true;
		}

		// Bricks builder (content stored in `_bricks_page_content_2`).
		if ( 'bricks' === get_post_meta( $post_id, '_bricks_editor_mode', true ) ) {
			return true;
		}
		if ( ! empty( get_post_meta( $post_id, '_bricks_page_content_2', true ) ) ) {
			return true;
		}

		/**
		 * Allow other builders (Oxygen, Divi, Beaver, …) to declare that they
		 * render the title H1 inside the content.
		 *
		 * @param bool $is_builder Whether the post is builder-rendered.
		 * @param int  $post_id    Post ID.
		 */
		return (bool) apply_filters( 'rtrs_seo_title_h1_in_content', false, $post_id );
	}

	/**
	 * Find where the heading outline skips a level, with the surrounding headings.
	 *
	 * The sequence is seeded with the implicit H1 (post title), so a skip records
	 * the heading it jumps *from* (previous) and the heading it jumps *to*.
	 *
	 * @param array[] $headings Headings in document order.
	 * @param string  $title    Post title (the implicit H1 seed).
	 * @return array[] List of [ from_level, from_text, to_level, to_text ].
	 */
	private static function find_skips( array $headings, $title ) {
		$skips      = [];
		$last_level = 1; // Implicit H1 from the title.
		$last_text  = trim( wp_strip_all_tags( (string) $title ) );

		foreach ( $headings as $h ) {
			$level = (int) $h['level'];
			$text  = trim( wp_strip_all_tags( (string) $h['text'] ) );

			if ( $level > $last_level + 1 ) {
				$skips[] = [
					'from_level' => $last_level,
					'from_text'  => $last_text,
					'to_level'   => $level,
					'to_text'    => $text,
				];
			}

			$last_level = $level;
			$last_text  = $text;
		}

		return $skips;
	}

	/**
	 * Build a readable list of the level skips for the suggestion body, naming the
	 * heading each jump happens after so the author can locate it.
	 *
	 * @param array[] $skips Skip descriptors from find_skips().
	 * @return string
	 */
	private static function skips_body( array $skips ) {
		$max_shown = 5;
		$shown     = array_slice( $skips, 0, $max_shown );
		$untitled  = __( '(untitled)', 'review-schema' );

		$items = [];
		foreach ( $shown as $s ) {
			$from = '' !== $s['from_text'] ? self::clip( $s['from_text'] ) : $untitled;
			$to   = '' !== $s['to_text'] ? self::clip( $s['to_text'] ) : $untitled;
			$items[] = sprintf(
				/* translators: 1: previous heading level, 2: previous heading text, 3: skipped-to heading level, 4: skipped-to heading text. */
				__( '• H%1$d “%2$s” → H%3$d “%4$s”', 'review-schema' ),
				$s['from_level'],
				$from,
				$s['to_level'],
				$to
			);
		}

		$more = count( $skips ) - count( $shown );
		if ( $more > 0 ) {
			/* translators: %d: number of additional level skips not listed. */
			$items[] = '• ' . sprintf( _n( '+%d more', '+%d more', $more, 'review-schema' ), $more );
		}

		// One skip per line; the UI renders line breaks (white-space: pre-line).
		return implode( "\n", $items );
	}

	/**
	 * Build a readable list of the empty headings for the suggestion body, naming
	 * the heading each empty one follows so the author can locate it.
	 *
	 * @param array[] $empties Empty-heading descriptors [ level, after ].
	 * @return string
	 */
	private static function empty_headings_body( array $empties ) {
		$max_shown = 5;
		$shown     = array_slice( $empties, 0, $max_shown );

		$items = [];
		foreach ( $shown as $e ) {
			if ( '' !== $e['after'] ) {
				$items[] = sprintf(
					/* translators: 1: empty heading level, 2: the heading it follows. */
					__( '• Empty H%1$d after “%2$s”', 'review-schema' ),
					$e['level'],
					self::clip( $e['after'] )
				);
			} else {
				$items[] = sprintf(
					/* translators: %d: empty heading level. */
					__( '• Empty H%d at the top of the page', 'review-schema' ),
					$e['level']
				);
			}
		}

		$more = count( $empties ) - count( $shown );
		if ( $more > 0 ) {
			/* translators: %d: number of additional empty headings not listed. */
			$items[] = '• ' . sprintf( _n( '+%d more', '+%d more', $more, 'review-schema' ), $more );
		}

		// One heading per line; the UI renders line breaks (white-space: pre-line).
		return implode( "\n", $items );
	}

	/**
	 * Trim a heading label for compact display in a suggestion body.
	 *
	 * @param string $text Heading text.
	 * @return string
	 */
	private static function clip( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( self::length( $text ) > 60 ) {
			$text = ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 60 ) : substr( $text, 0, 60 ) );
			$text = rtrim( $text ) . '…';
		}
		return $text;
	}

	/**
	 * Whether the focus keyword appears in any subheading (H2-H6).
	 *
	 * Mirrors how Yoast / Rank Math judge "keyphrase in subheading": both the
	 * heading and the keyword are normalized (entities decoded, lower-cased,
	 * punctuation stripped), then a subheading matches when it contains the
	 * keyword as an exact phrase OR contains every significant keyword word
	 * (order-independent, common stop words ignored). This avoids false misses
	 * from word order, punctuation, or HTML entities.
	 *
	 * @param array[] $headings Headings in document order.
	 * @param string  $keyword  Focus keyword.
	 * @return bool
	 */
	private static function keyword_in_headings( array $headings, $keyword ) {
		$keyword_norm = self::normalize( $keyword );
		if ( '' === $keyword_norm ) {
			return false;
		}

		$stop_words    = [ 'the', 'a', 'an', 'to', 'of', 'in', 'on', 'for', 'and', 'or', 'with', 'at', 'by' ];
		$keyword_words = array_values(
			array_filter(
				explode( ' ', $keyword_norm ),
				static function ( $word ) use ( $stop_words ) {
					return strlen( $word ) > 1 && ! in_array( $word, $stop_words, true );
				}
			)
		);

		foreach ( $headings as $h ) {
			// Subheadings only (H2-H6); the post title is the page H1.
			if ( (int) $h['level'] < 2 ) {
				continue;
			}

			$text = self::normalize( $h['text'] );
			if ( '' === $text ) {
				continue;
			}

			// Exact-phrase match.
			if ( false !== strpos( $text, $keyword_norm ) ) {
				return true;
			}

			// All significant keyword words present in this heading (any order).
			if ( ! empty( $keyword_words ) ) {
				$all_present = true;
				foreach ( $keyword_words as $word ) {
					if ( false === strpos( $text, $word ) ) {
						$all_present = false;
						break;
					}
				}
				if ( $all_present ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Normalize text for keyword comparison.
	 *
	 * Decodes HTML entities, lower-cases (multibyte-aware), replaces punctuation
	 * with spaces, and collapses whitespace.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function normalize( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Multibyte-safe character length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text ) : strlen( (string) $text );
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
	 * @param string $message  Human message.
	 * @param string $severity 'critical' | 'warning' | 'info'.
	 * @param int    $points   Points impact (negative = lost).
	 * @param string $effort   'low' | 'medium' | 'high'.
	 * @return array
	 */
	private static function issue( $message, $severity, $points, $effort ) {
		return [
			'message'  => $message,
			'severity' => $severity,
			'points'   => (int) $points,
			'effort'   => $effort,
		];
	}

	/**
	 * Build a readable list of the over-long headings for the suggestion body.
	 *
	 * Shows the first few offenders (each trimmed for scannability) so the author
	 * can spot which headings to shorten, with a "+N more" tail when there are many.
	 *
	 * @param string[] $texts Offending heading texts.
	 * @return string
	 */
	private static function long_headings_body( array $texts ) {
		$max_shown = 5;
		$shown     = array_slice( $texts, 0, $max_shown );

		$items = [];
		foreach ( $shown as $text ) {
			$text = trim( wp_strip_all_tags( (string) $text ) );
			if ( self::length( $text ) > 72 ) {
				$text = ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 72 ) : substr( $text, 0, 72 ) );
				$text = rtrim( $text ) . '…';
			}
			$items[] = '• ' . $text;
		}

		$more = count( $texts ) - count( $shown );
		if ( $more > 0 ) {
			/* translators: %d: number of additional over-long headings not listed. */
			$items[] = '• ' . sprintf( _n( '+%d more', '+%d more', $more, 'review-schema' ), $more );
		}

		// One heading per line; the UI renders line breaks (white-space: pre-line).
		return implode( "\n", $items );
	}
}
