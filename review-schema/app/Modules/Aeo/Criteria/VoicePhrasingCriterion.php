<?php
/**
 * AEO criterion: Voice-Search Phrasing.
 *
 * Voice assistants and AI answers favor short, conversational sentences.
 * Scores average sentence length and natural question phrasing.
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
 * Class VoicePhrasingCriterion
 */
class VoicePhrasingCriterion extends AbstractAeoCriterion {

	/**
	 * Word count above which a single sentence is flagged as too long for voice.
	 */
	const LONG_SENTENCE = 25;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'voice_phrasing';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Voice-Search Phrasing', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 15;
	}

	/**
	 * @inheritDoc
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$sentences = AeoContent::sentences( $context->plain_text() );
		$count     = count( $sentences );

		if ( 0 === $count ) {
			return $this->result(
				30,
				[ $this->check( __( 'Has readable sentences', 'review-schema' ), false ) ]
			);
		}

		$words        = 0;
		$has_question = false;
		$long_rows    = [];
		foreach ( $sentences as $sentence ) {
			$sentence_words = str_word_count( $sentence );
			$words         += $sentence_words;
			if ( ! $has_question && '?' === substr( $sentence, -1 ) ) {
				$has_question = true;
			}
			if ( $sentence_words > self::LONG_SENTENCE ) {
				$long_rows[] = [
					'preview' => $this->preview( $sentence ),
					'words'   => $sentence_words,
					'text'    => $sentence,
				];
			}
		}

		// Worst offenders first.
		usort(
			$long_rows,
			static function ( $a, $b ) {
				return $b['words'] <=> $a['words'];
			}
		);

		$average = $words / $count;

		if ( $average <= 16 ) {
			$value = 100;
		} elseif ( $average <= 20 ) {
			$value = 85;
		} elseif ( $average <= 25 ) {
			$value = 70;
		} elseif ( $average <= 30 ) {
			$value = 55;
		} else {
			$value = 40;
		}

		if ( $has_question ) {
			$value = min( 100, $value + 5 );
		}

		$avg_label = sprintf(
			/* translators: %d: average words per sentence. */
			__( 'Conversational sentence length (avg %d words)', 'review-schema' ),
			(int) round( $average )
		);

		// Surface the reason for any deduction inline so the criterion is
		// self-explanatory. Over 20 words is a failing check that needs splitting;
		// the 17-20 band still passes (it is conversational) but carries a short
		// note so the missing points don't look unexplained.
		if ( $average > 20 ) {
			$avg_label .= ' — ' . __( 'Split long sentences into shorter ones.', 'review-schema' );
		} elseif ( $average > 16 ) {
			$avg_label .= ' — ' . __( 'trim toward 15-16 words for full marks.', 'review-schema' );
		}

		$checks = [
			$this->check( $avg_label, $average <= 20 ),
			$this->check( __( 'Includes natural question phrasing', 'review-schema' ), $has_question ),
		];

		// Name each long sentence only when the average is actually over target,
		// so a perfect (avg-based) score never shows "too long" warnings.
		if ( $average > 20 ) {
			foreach ( array_slice( $long_rows, 0, 5 ) as $row ) {
				$checks[] = $this->check(
					sprintf(
						/* translators: 1: sentence preview, 2: word count. */
						__( '"%1$s" — %2$d words (too long)', 'review-schema' ),
						$row['preview'],
						$row['words']
					),
					false,
					'',
					[ 'target' => 'shorten_sentence', 'text' => $row['text'] ]
				);
			}
		}

		$suggestions = [];
		if ( $average > 20 ) {
			$body = __( 'Voice assistants and AI answers favor short, direct sentences. Aim for ~15-20 words so a clean spoken answer can be extracted.', 'review-schema' );

			if ( ! empty( $long_rows ) ) {
				$names = [];
				foreach ( array_slice( $long_rows, 0, 5 ) as $row ) {
					$names[] = sprintf(
						/* translators: 1: sentence preview, 2: word count. */
						__( '"%1$s" (%2$d words)', 'review-schema' ),
						$row['preview'],
						$row['words']
					);
				}
				$list = implode( '; ', $names );
				if ( count( $long_rows ) > 5 ) {
					$list .= sprintf(
						/* translators: %d: number of additional sentences. */
						__( '; +%d more', 'review-schema' ),
						count( $long_rows ) - 5
					);
				}
				$body = sprintf(
					/* translators: %s: list of long sentence previews with word counts. */
					__( 'Split these long sentences so the average drops toward 15-20 words: %s.', 'review-schema' ),
					$list
				);
			}

			$suggestions[] = $this->suggestion(
				'med',
				__( 'Use shorter, conversational sentences', 'review-schema' ),
				$body,
				__( '+6 pts', 'review-schema' ),
				'Med'
			);
		} elseif ( $average > 16 ) {
			// Middle band (17-20 words): conversational, but trimming to ~16
			// words lifts the score to full marks.
			$suggestions[] = $this->suggestion(
				'low',
				__( 'Tighten sentences for voice search', 'review-schema' ),
				__( 'Sentences average 17-20 words — already conversational, but trimming them toward 15-16 words lets a voice assistant read a cleaner, quicker answer.', 'review-schema' ),
				sprintf(
					/* translators: %d: points gained by reaching full marks. */
					__( '+%d pts', 'review-schema' ),
					max( 0, 100 - $value )
				),
				'Low'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}

	/**
	 * Short preview of a sentence for naming it in the checklist.
	 *
	 * @param string $text  Sentence text.
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
