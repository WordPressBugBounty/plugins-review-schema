<?php
/**
 * GEO criterion: Statement Density.
 *
 * Generative engines reward fact-dense prose — sentences that each assert
 * something concrete (a number, a named entity, a date) rather than filler.
 * Scores the share of body sentences that carry a citable claim.
 *
 * @package Rtrs\Modules\Geo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Criteria;

use Rtrs\Modules\Aeo\Helpers\AeoContent;
use Rtrs\Modules\Geo\Helpers\GeoContent;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StatementDensityCriterion
 */
class StatementDensityCriterion extends AbstractGeoCriterion {

	/**
	 * Share of fact-carrying sentences that earns full marks.
	 */
	const TARGET_RATIO = 0.4;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'statement_density';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Statement Density', 'review-schema' );
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
		$sentences = AeoContent::sentences( $context->plain_text() );
		$total     = count( $sentences );

		if ( 0 === $total ) {
			$checks = [ $this->check( __( 'Body has analyzable sentences', 'review-schema' ), false ) ];
			$sug    = [
				$this->suggestion(
					'high',
					__( 'Add substantive body copy', 'review-schema' ),
					__( 'There is too little prose to assess. Add fact-led sentences that state concrete details.', 'review-schema' ),
					__( '+20 pts', 'review-schema' ),
					'Med'
				),
			];
			return $this->result( 0, $checks, $sug );
		}

		$factual = 0;
		foreach ( $sentences as $sentence ) {
			if ( GeoContent::is_factual( $sentence ) ) {
				$factual++;
			}
		}

		$ratio     = $factual / $total;
		$value     = (int) round( min( 100, $ratio / self::TARGET_RATIO * 100 ) );
		$percent   = (int) round( $ratio * 100 );
		$target    = (int) round( self::TARGET_RATIO * 100 );
		$is_passing = $ratio >= self::TARGET_RATIO;

		$checks = [
			$this->check(
				$is_passing
					? sprintf(
						/* translators: 1: percentage of fact-carrying sentences, 2: fact-carrying sentences, 3: total sentences. */
						__( 'Fact-rich prose: %1$d%% of sentences state a concrete claim (%2$d of %3$d).', 'review-schema' ),
						$percent,
						$factual,
						$total
					)
					: sprintf(
						/* translators: 1: current percentage, 2: fact-carrying sentences, 3: total sentences, 4: target percentage. */
						__( 'Only %1$d%% of sentences state a concrete claim (%2$d of %3$d) — aim for %4$d%%+. Add a number, named entity, or date to more sentences.', 'review-schema' ),
						$percent,
						$factual,
						$total,
						$target
					),
				$is_passing
			),
		];

		$suggestions = [];
		if ( $value < 100 ) {
			$suggestions[] = $this->suggestion(
				$ratio < self::TARGET_RATIO / 2 ? 'high' : 'med',
				__( 'Make sentences more fact-led', 'review-schema' ),
				sprintf(
					/* translators: 1: current percentage, 2: target percentage. */
					__( 'Only %1$d%% of sentences assert something concrete; aim for at least %2$d%%. Trim filler and transitions, and rewrite generic statements to include a number, a named entity, or a date that an engine can quote.', 'review-schema' ),
					$percent,
					$target
				),
				__( '+20 pts', 'review-schema' ),
				'Med'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
