<?php
/**
 * AEO criterion: Answer Conciseness.
 *
 * Voice assistants and AI answers read only the first part of a passage.
 * Penalizes oversized answer paragraphs that bury the response.
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
 * Class AnswerConcisenessCriterion
 */
class AnswerConcisenessCriterion extends AbstractAeoCriterion {

	/**
	 * Word count above which a paragraph is considered too long to be a clean answer.
	 */
	const CONCISE_MAX = 90;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'answer_conciseness';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Answer Conciseness', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 20;
	}

	/**
	 * @inheritDoc
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$paragraphs = AeoContent::paragraphs( $context->content() );

		if ( empty( $paragraphs ) ) {
			$paragraphs = array_values(
				array_filter( array_map( 'trim', preg_split( '/\n+/', $context->plain_text() ) ) )
			);
		}

		$total = count( $paragraphs );
		if ( 0 === $total ) {
			return $this->result(
				40,
				[ $this->check( __( 'Has readable answer paragraphs', 'review-schema' ), false ) ]
			);
		}

		$concise   = 0;
		$longest   = 0;
		$long_rows = [];
		foreach ( $paragraphs as $paragraph ) {
			$words = str_word_count( $paragraph );
			if ( $words > $longest ) {
				$longest = $words;
			}
			if ( $words <= self::CONCISE_MAX ) {
				$concise++;
				continue;
			}
			$long_rows[] = [
				'preview' => $this->preview( $paragraph ),
				'words'   => $words,
				'text'    => $paragraph,
			];
		}

		$ratio = $concise / $total;
		$value = (int) round( $ratio * 100 );

		// Passive voice reads clunky when spoken by assistants. Flag it when a
		// large share of sentences are passive (needs enough sentences to judge).
		$sentences    = AeoContent::sentences( $context->plain_text() );
		$passive      = 0;
		foreach ( $sentences as $sentence ) {
			if ( AeoContent::is_passive( $sentence ) ) {
				$passive++;
			}
		}
		$passive_ratio = count( $sentences ) >= 5 ? $passive / count( $sentences ) : 0;
		$low_passive   = $passive_ratio <= 0.3;
		if ( ! $low_passive ) {
			$value = max( 0, $value - 10 );
		}

		// Summary positives first, then a row naming each over-long paragraph.
		$checks = [
			$this->check(
				sprintf(
					/* translators: 1: concise paragraphs, 2: total paragraphs. */
					__( 'Concise paragraphs (%1$d/%2$d at <=90 words)', 'review-schema' ),
					$concise,
					$total
				),
				0 === count( $long_rows )
			),
			$this->check( __( 'No oversized blocks (over 120 words)', 'review-schema' ), $longest <= 120 ),
			$this->check( __( 'Mostly active voice (reads cleanly aloud)', 'review-schema' ), $low_passive ),
		];

		foreach ( $long_rows as $row ) {
			$checks[] = $this->check(
				sprintf(
					/* translators: 1: paragraph preview, 2: word count. */
					__( '"%1$s" — %2$d words (too long)', 'review-schema' ),
					$row['preview'],
					$row['words']
				),
				false,
				'',
				[ 'target' => 'tighten_paragraph', 'text' => $row['text'] ]
			);
		}

		$suggestions = [];
		if ( count( $long_rows ) > 0 ) {
			$names = [];
			foreach ( array_slice( $long_rows, 0, 5 ) as $row ) {
				$names[] = sprintf(
					/* translators: 1: paragraph preview, 2: word count. */
					__( '"%1$s" (%2$d words)', 'review-schema' ),
					$row['preview'],
					$row['words']
				);
			}
			$list = implode( '; ', $names );
			if ( count( $long_rows ) > 5 ) {
				$list .= sprintf(
					/* translators: %d: number of additional paragraphs. */
					__( '; +%d more', 'review-schema' ),
					count( $long_rows ) - 5
				);
			}

			$recoverable = max( 1, (int) round( ( 1 - $ratio ) * 100 ) );

			$suggestions[] = $this->suggestion(
				'med',
				__( 'Tighten these long paragraphs', 'review-schema' ),
				sprintf(
					/* translators: %s: list of paragraph previews with word counts. */
					__( 'These paragraphs exceed 90 words, so an answer engine can\'t cleanly quote them: %s. Split each one or lead with a 1-2 sentence summary.', 'review-schema' ),
					$list
				),
				sprintf(
					/* translators: %d: recoverable points. */
					__( '+%d pts', 'review-schema' ),
					$recoverable
				),
				'Low'
			);
		}

		if ( ! $low_passive ) {
			$suggestions[] = $this->suggestion(
				'med',
				__( 'Prefer active voice for spoken answers', 'review-schema' ),
				__( 'A large share of sentences use passive voice, which sounds clunky when read aloud by assistants. Rewrite "X was done by Y" as "Y did X".', 'review-schema' ),
				__( '+10 pts', 'review-schema' ),
				'Med'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}

	/**
	 * Short preview of a paragraph for naming it in the checklist.
	 *
	 * @param string $text  Paragraph text.
	 * @param int    $words Number of leading words to keep.
	 * @return string
	 */
	private function preview( $text, $words = 8 ) {
		$text  = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		$parts = explode( ' ', $text );
		if ( count( $parts ) <= $words ) {
			return $text;
		}
		return implode( ' ', array_slice( $parts, 0, $words ) ) . '…';
	}
}
