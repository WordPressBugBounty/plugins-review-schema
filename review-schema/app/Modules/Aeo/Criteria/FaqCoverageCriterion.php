<?php
/**
 * AEO criterion: FAQ Coverage.
 *
 * Scores how many question/answer pairs the FAQ covers — answer engines lift
 * more when a FAQ addresses several real questions. Independent of whether the
 * FAQ carries schema (that is FaqPresenceCriterion's job); a page with no FAQ
 * scores 0.
 *
 * @package Rtrs\Modules\Aeo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Criteria;

use Rtrs\Modules\Aeo\Helpers\FaqSignals;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FaqCoverageCriterion
 */
class FaqCoverageCriterion extends AbstractAeoCriterion {

	/**
	 * Number of Q&A pairs considered strong coverage.
	 */
	const STRONG_COVERAGE = 3;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'faq_coverage';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'FAQ Coverage', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 10;
	}

	/**
	 * @inheritDoc
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$signals   = FaqSignals::detect( $context );
		$pairs     = (int) $signals['pairs'];
		$questions = isset( $signals['questions'] ) ? (array) $signals['questions'] : [];
		$has_schema = ! empty( $signals['schema'] );

		if ( $pairs >= self::STRONG_COVERAGE ) {
			$value = 100;
		} elseif ( $pairs >= 2 ) {
			$value = 70;
		} elseif ( $pairs >= 1 ) {
			$value = 40;
		} else {
			$value = 0;
		}

		$to_two    = max( 0, 2 - $pairs );
		$to_strong = max( 0, self::STRONG_COVERAGE - $pairs );

		$checks = [
			$this->check(
				sprintf(
					/* translators: %d: number of Q&A pairs found. */
					__( 'Multiple Q&A pairs (2+) — %d found', 'review-schema' ),
					$pairs
				),
				$pairs >= 2,
				$pairs >= 2
					? ''
					: sprintf(
						/* translators: %d: number of additional Q&A pairs to add. */
						_n( 'Add %d more Q&A pair to reach 2.', 'Add %d more Q&A pairs to reach 2.', $to_two, 'review-schema' ),
						$to_two
					)
			),
			$this->check(
				sprintf(
					/* translators: 1: Q&A pairs found, 2: recommended minimum. */
					__( 'Strong FAQ coverage (%2$d+) — %1$d found', 'review-schema' ),
					$pairs,
					self::STRONG_COVERAGE
				),
				$pairs >= self::STRONG_COVERAGE,
				$pairs >= self::STRONG_COVERAGE
					? ''
					: sprintf(
						/* translators: 1: number of additional Q&A pairs, 2: recommended minimum. */
						_n( 'Add %1$d more Q&A pair to reach %2$d.', 'Add %1$d more Q&A pairs to reach %2$d.', $to_strong, 'review-schema' ),
						$to_strong,
						self::STRONG_COVERAGE
					)
			),
		];

		// List exactly which Q&A pairs were counted, so the "N found" figure is
		// transparent. Rendered as passing, non-scoring rows under the notice.
		foreach ( $questions as $i => $question ) {
			$checks[] = $this->check(
				sprintf(
					/* translators: 1: index, 2: the FAQ question text. */
					__( 'Q%1$d: %2$s', 'review-schema' ),
					$i + 1,
					$question
				),
				true
			);
		}

		$suggestions = [];

		if ( $pairs < self::STRONG_COVERAGE ) {
			$suggestions[] = $this->suggestion(
				0 === $pairs ? 'high' : 'med',
				__( 'Cover more questions in the FAQ', 'review-schema' ),
				sprintf(
					/* translators: %d: recommended minimum number of Q&A pairs. */
					__( 'Include at least %d question/answer pairs so the FAQ covers what people actually ask. When you generate the FAQ, produce a complete set of questions rather than only one or two.', 'review-schema' ),
					self::STRONG_COVERAGE
				),
				__( '+30 pts', 'review-schema' ),
				'Med'
			);
		}

		// The page has FAQ content but it is written in the body only — no FAQPage
		// structured data. Nudge the user to mark it up so answer engines can read
		// it, without penalising the coverage score.
		if ( $pairs > 0 && ! $has_schema ) {
			$suggestions[] = $this->suggestion(
				'med',
				__( 'Add FAQPage schema to your FAQ', 'review-schema' ),
				__( 'Your FAQ is in the page content but has no FAQPage structured data, so search and answer engines cannot read it as a FAQ. Rebuild it with the SchemaEngine AI FAQ block or "Generate with AI" to output the schema and become eligible for rich results.', 'review-schema' ),
				__( 'Rich results', 'review-schema' ),
				'Med'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
