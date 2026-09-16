<?php
/**
 * GEO criterion: Source Authority.
 *
 * Content that cites trustworthy outside sources is judged more reliable — and
 * more citable — by generative engines. Rewards outbound citations. Linking to
 * top-tier domains (.gov / .edu / major references) is surfaced separately as an
 * optional recommendation, so it never penalizes the required score.
 *
 * @package Rtrs\Modules\Geo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Criteria;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SourceAuthorityCriterion
 */
class SourceAuthorityCriterion extends AbstractGeoCriterion {

	/**
	 * Outbound citations that constitute "well sourced".
	 */
	const TARGET_LINKS = 2;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'source_authority';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Source Authority', 'review-schema' );
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
		$external    = $context->external_link_count();
		$has_sources = $external > 0;

		$value = (int) round( min( 100, $external / self::TARGET_LINKS * 100 ) );

		$checks = [
			$this->check(
				sprintf(
					/* translators: %d: number of outbound links. */
					_n(
						'%d authoritative external source detected. Consider adding more supporting references for factual claims and statistics.',
						'%d authoritative external sources detected. Strong sourcing helps engines trust your factual claims and statistics.',
						$external,
						'review-schema'
					),
					$external
				),
				$external >= self::TARGET_LINKS
			),
		];

		$suggestions = [];
		if ( $value < 100 ) {
			if ( ! $has_sources ) {
				$suggestions[] = $this->suggestion(
					'high',
					__( 'Cite external sources', 'review-schema' ),
					__( 'This page has no outbound citations. Reference 2-3 reputable sources for your key claims and link to them. Engines treat well-sourced content as more trustworthy and quote it more often.', 'review-schema' ),
					__( '+15 pts', 'review-schema' ),
					'Low'
				);
			} elseif ( $external < self::TARGET_LINKS ) {
				$suggestions[] = $this->suggestion(
					'med',
					__( 'Add another reputable citation', 'review-schema' ),
					__( 'Back a second key claim with an outbound link to a credible source to strengthen the page\'s authority signal.', 'review-schema' ),
					__( '+8 pts', 'review-schema' ),
					'Low'
				);
			}
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
