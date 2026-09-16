<?php
/**
 * GEO criterion: Citable Statistics.
 *
 * Concrete figures — percentages, amounts, ratios, measurements — are the
 * fragments generative engines lift verbatim into answers. Rewards content that
 * carries enough quotable statistics for its length.
 *
 * @package Rtrs\Modules\Geo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Criteria;

use Rtrs\Modules\Geo\Helpers\GeoContent;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CitableStatisticsCriterion
 */
class CitableStatisticsCriterion extends AbstractGeoCriterion {

	/**
	 * One quotable statistic per this many words is a healthy target.
	 */
	const WORDS_PER_STAT = 200;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'citable_statistics';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Citable Statistics', 'review-schema' );
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
		$stats   = GeoContent::statistics( $context->plain_text() );
		$count   = count( $stats );
		$words   = $context->word_count();
		$target  = max( 2, (int) floor( $words / self::WORDS_PER_STAT ) );
		$enough  = $count >= $target;

		$value = $target > 0 ? (int) round( min( 100, $count / $target * 100 ) ) : 0;

		$checks = [
			$this->check(
				$enough
					? sprintf(
						/* translators: 1: statistics found, 2: recommended count. */
						_n(
							'%1$d citable statistic detected (target %2$d). Concrete figures give engines quotable, evidence-backed facts.',
							'%1$d citable statistics detected (target %2$d). Concrete figures give engines quotable, evidence-backed facts.',
							$count,
							'review-schema'
						),
						$count,
						$target
					)
					: sprintf(
						/* translators: 1: statistics found, 2: recommended count. */
						_n(
							'Only %1$d citable statistic detected — aim for about %2$d. Add more relevant, evidence-backed figures (percentages, prices, ratios) to strengthen your content\'s credibility.',
							'Only %1$d citable statistics detected — aim for about %2$d. Add more relevant, evidence-backed figures (percentages, prices, ratios) to strengthen your content\'s credibility.',
							$count,
							'review-schema'
						),
						$count,
						$target
					),
				$enough
			),
		];

		if ( $count > 0 ) {
			$shown = array_slice( $stats, 0, 8 );
			$list  = implode( ', ', array_map( 'esc_html', $shown ) );
			if ( $count > count( $shown ) ) {
				$list .= sprintf(
					/* translators: %d: number of additional statistics. */
					__( ', +%d more', 'review-schema' ),
					$count - count( $shown )
				);
			}
			$checks[] = $this->check(
				sprintf(
					/* translators: %s: comma-separated list of detected statistics. */
					__( 'Detected: %s', 'review-schema' ),
					$list
				),
				true
			);
		}

		$suggestions = [];
		if ( $value < 100 ) {
			$suggestions[] = $this->suggestion(
				$count < max( 1, (int) floor( $target / 2 ) ) ? 'high' : 'med',
				__( 'Add concrete, quotable figures', 'review-schema' ),
				sprintf(
					/* translators: 1: current count, 2: target count. */
					__( 'You have %1$d quotable statistic(s); aim for about %2$d. Replace vague claims ("much faster", "very popular") with specific numbers ("38%% faster", "rated 4.6/5 by 1,200 guests"). The figures need not be your own — properly cited third-party statistics count too. Percentages, prices, ratios and measurements are what AI answers cite.', 'review-schema' ),
					$count,
					$target
				),
				__( '+20 pts', 'review-schema' ),
				'Med'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
