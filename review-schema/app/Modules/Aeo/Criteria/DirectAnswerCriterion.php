<?php
/**
 * AEO criterion: Direct Answer Blocks.
 *
 * Featured snippets and AI answers favor sections that open with a concise,
 * answer-first paragraph (20-75 words). Scores the share of sections that do.
 *
 * @package Rtrs\Modules\Aeo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Criteria;

use Rtrs\Modules\Aeo\Helpers\AeoContent;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DirectAnswerCriterion
 */
class DirectAnswerCriterion extends AbstractAeoCriterion {

	/**
	 * Lower bound (words) for a usable direct answer.
	 */
	const MIN_WORDS = 20;

	/**
	 * Upper bound (words) for a concise direct answer.
	 */
	const MAX_WORDS = 75;

	/**
	 * Lower bound (words) for a FAQ-block answer.
	 *
	 * FAQ answers are intentionally concise Q&A and already carry FAQPage
	 * schema, so they only need to be present and non-trivial — they are not
	 * held to the article-section 20-75 band, and have no upper limit.
	 */
	const FAQ_MIN_WORDS = 10;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'direct_answers';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Direct Answer Blocks', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 25;
	}

	/**
	 * @inheritDoc
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$sections = AeoContent::sections( $context->content() );

		// Drop headings with no text (an empty <h2> or an icon-only heading):
		// they surface as an unlocatable "(untitled section)" with a 0-word lead
		// the author cannot act on. A titled section with a short lead still shows.
		$sections = array_values(
			array_filter(
				$sections,
				static function ( $section ) {
					return '' !== trim( (string) $section['heading'] );
				}
			)
		);

		$total = count( $sections );

		if ( 0 === $total ) {
			$paragraphs = AeoContent::paragraphs( $context->content() );
			$lead_words = isset( $paragraphs[0] ) ? str_word_count( $paragraphs[0] ) : 0;
			$lead_ok    = $lead_words >= self::MIN_WORDS && $lead_words <= self::MAX_WORDS;
			$lead_self  = ! isset( $paragraphs[0] ) || '' === $paragraphs[0] || AeoContent::is_self_contained( $paragraphs[0] );

			$checks = [
				$this->check(
					__( 'Has section headings to answer under', 'review-schema' ),
					false,
					__( 'Break the content into H2/H3 sections. Answer engines lift a heading plus the paragraph right below it, so headings give each answer a home.', 'review-schema' )
				),
				$this->check(
					__( 'Opens with a concise answer (20-75 words)', 'review-schema' ),
					$lead_ok,
					$lead_ok
						? __( 'Start the page with a direct 20-75 word answer to its main question before adding background.', 'review-schema' )
						: __( 'Optional: open with a direct 20-75 word answer to the main question. This is a suggestion and does not affect your score.', 'review-schema' ),
					$lead_ok ? null : [ 'target' => 'section_lead', 'heading' => '', 'reason' => 'short' ],
					$lead_ok ? null : 'suggestion'
				),
				$this->check(
					__( 'Opening answer is self-contained', 'review-schema' ),
					$lead_self,
					__( 'Name the subject in the first sentence (e.g. "The plugin…") instead of opening with "It", "This", or "They", so the answer still makes sense quoted on its own.', 'review-schema' )
				),
			];

			// Lead length is advisory here — a short lead never lowers the score;
			// the missing-headings check is the real signal for this no-section page.
			$lead_value = 50;
			if ( ! $lead_self ) {
				$lead_value = max( 0, $lead_value - 8 );
			}

			return $this->result(
				$lead_value,
				$checks,
				[
					$this->suggestion(
						'high',
						__( 'Break content into sections with answer-first paragraphs', 'review-schema' ),
						__( 'Add H2 sections and lead each with a 20-75 word direct answer so engines can lift a clean response.', 'review-schema' ),
						__( '+15 pts', 'review-schema' ),
						'Med'
					),
				]
			);
		}

		$answered         = 0;
		$suggestion_count = 0;
		$failing          = [];
		$section_rows     = [];
		foreach ( $sections as $section ) {
			$heading = '' !== $section['heading'] ? $section['heading'] : __( '(untitled section)', 'review-schema' );
			$words   = $section['first_para_words'];
			$is_faq  = ! empty( $section['is_faq'] );

			// FAQ answers only need to be present and non-trivial (no upper cap);
			// article sections are held to the strict 20-75 word band.
			if ( $is_faq ) {
				$ok    = $words >= self::FAQ_MIN_WORDS;
				$short = ! $ok;
			} else {
				$ok    = $words >= self::MIN_WORDS && $words <= self::MAX_WORDS;
				$short = $words < self::MIN_WORDS;
			}

			// Passing sections are folded into the summary count only; we list
			// just the sections that need attention to keep the checklist actionable.
			if ( $ok ) {
				$answered++;
				continue;
			}

			// A "too short" lead is an optional, advisory nudge — it is surfaced as
			// a suggestion row and counts toward answered so it never lowers the
			// score. Only a "too long" lead (can't be lifted as a snippet) stays a
			// real scoring issue that feeds the recovery aggregate below.
			if ( $short ) {
				$answered++;
				$suggestion_count++;
			} else {
				$failing[] = [
					'heading' => $heading,
					'words'   => $words,
					'short'   => false,
				];
			}

			$section_rows[]                 = $this->check(
				sprintf(
					/* translators: 1: section heading, 2: word count, 3: reason (too short / too long). */
					__( '%1$s — lead is %2$d words (%3$s)', 'review-schema' ),
					$heading,
					$words,
					$short ? __( 'too short', 'review-schema' ) : __( 'too long', 'review-schema' )
				),
				false,
				$short
					? __( 'Optional: expand this section\'s opening paragraph to a self-contained 20-75 word answer to the heading. This is a minor suggestion — worth a small amount toward a perfect 100%.', 'review-schema' )
					: __( 'Trim this section\'s opening to under 75 words — give a crisp answer first, then move the extra detail into the paragraphs that follow.', 'review-schema' ),
				[
					'target'  => 'section_lead',
					'heading' => '' !== $section['heading'] ? $section['heading'] : '',
					'reason'  => $short ? 'short' : 'long',
				],
				$short ? 'suggestion' : null
			);
		}

		$ratio = $answered / $total;
		$value = (int) round( $ratio * 100 );

		// Answers that open with a bare pronoun ("It", "This") aren't quotable
		// out of context; count them and apply a bounded penalty.
		$pronoun_leads = 0;
		foreach ( $sections as $section ) {
			if ( '' === $section['first_para'] || AeoContent::is_self_contained( $section['first_para'] ) ) {
				continue;
			}
			$pronoun_leads++;

			// Always surface the pronoun nudge as its own row so the 8-point
			// deduction is visible — even when this section is also listed for lead
			// length (they are separate fixes: expand the lead vs. name the subject).
			$h = '' !== $section['heading'] ? $section['heading'] : __( '(untitled section)', 'review-schema' );

			$section_rows[] = $this->check(
				sprintf(
					/* translators: %s: section heading. */
					__( '%s — opening starts with a pronoun', 'review-schema' ),
					$h
				),
				false,
				__( 'Restate the subject at the start of this answer (e.g. "The plugin…" instead of "It…") so it makes sense quoted on its own — this costs a few points until fixed.', 'review-schema' ),
				[ 'target' => 'section_lead', 'heading' => $section['heading'], 'reason' => 'pronoun' ]
			);
		}
		if ( $pronoun_leads > 0 ) {
			$value = max( 0, $value - min( 20, $pronoun_leads * 8 ) );
		}

		// Optional lead-length suggestions are advisory, but while any remain the
		// section still isn't perfect — hold the score just shy of 100 with a small
		// bounded cap (max 3 pts) so a clean 100% means nothing is left to improve.
		if ( $suggestion_count > 0 ) {
			$value = min( $value, 100 - min( 3, $suggestion_count ) );
		}

		// List only the sections that fall outside the concise-lead band, in
		// document order. (The "Sections with a concise lead answer (x/y)"
		// summary row is intentionally not shown.)
		// Pronoun-led openings surface as muted per-section rows only (added in
		// the loop above), matching the lead-length nudges. No separate amber
		// summary banner — it over-weighted a soft, optional fix.
		$checks = $section_rows;

		$suggestions = [];
		if ( $answered < $total ) {
			$names = [];
			foreach ( array_slice( $failing, 0, 5 ) as $fail ) {
				$names[] = sprintf(
					/* translators: 1: section heading, 2: word count, 3: reason. */
					__( '"%1$s" (%2$d words, %3$s)', 'review-schema' ),
					$fail['heading'],
					$fail['words'],
					$fail['short'] ? __( 'too short', 'review-schema' ) : __( 'too long', 'review-schema' )
				);
			}
			$list = implode( '; ', $names );
			if ( count( $failing ) > 5 ) {
				$list .= sprintf(
					/* translators: %d: number of additional sections. */
					__( '; +%d more', 'review-schema' ),
					count( $failing ) - 5
				);
			}

			$recoverable = (int) round( ( 1 - $ratio ) * 100 );

			$suggestions[] = $this->suggestion(
				$ratio < 0.5 ? 'high' : 'med',
				__( 'Give these sections an answer-first lead paragraph', 'review-schema' ),
				sprintf(
					/* translators: %s: list of section headings with their issue. */
					__( 'These sections don\'t open with a 20-75 word direct answer: %s. Lead each with a concise answer so engines can lift it.', 'review-schema' ),
					$list
				),
				sprintf(
					/* translators: %d: recoverable points. */
					__( '+%d pts', 'review-schema' ),
					$recoverable
				),
				'Med'
			);
		}

		if ( $pronoun_leads > 0 ) {
			$suggestions[] = $this->suggestion(
				'med',
				__( 'Make answer openings self-contained', 'review-schema' ),
				__( 'Some sections open with a bare pronoun like "It" or "This". Answer engines quote sentences out of context, so restate the subject at the start of each answer (e.g. "The plugin…" instead of "It…").', 'review-schema' ),
				__( '+8 pts', 'review-schema' ),
				'Low'
			);
		}

		// Keep the important summary rows on top and sink the per-section
		// lead-length / pronoun nudges (the muted "minor" rows, which carry an
		// AI-suggest apply target) to the bottom, so the checklist leads with
		// what matters. Stable within each group.
		$primary = [];
		$minor   = [];
		foreach ( $checks as $row ) {
			if ( isset( $row['apply'] ) ) {
				$minor[] = $row;
			} else {
				$primary[] = $row;
			}
		}
		$checks = array_merge( $primary, $minor );

		return $this->result( $value, $checks, $suggestions );
	}
}
