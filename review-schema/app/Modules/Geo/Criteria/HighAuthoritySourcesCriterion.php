<?php
/**
 * GEO criterion: High-Authority Sources (optional).
 *
 * Linking to a recognized authority — a .gov or .edu site, an official body, a
 * peer-reviewed journal, or a major encyclopedia — signals to generative
 * engines that the page's claims are well grounded. Useful where the content
 * states facts, but not every page needs it, so this is offered as an optional,
 * context-dependent recommendation that never lowers the score.
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
 * Class HighAuthoritySourcesCriterion
 */
class HighAuthoritySourcesCriterion extends AbstractGeoCriterion {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'high_authority_sources';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'High-Authority Sources', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 0;
	}

	/**
	 * Citing top-tier sources depends on whether the page makes factual claims,
	 * so it is offered as guidance and never affects the score.
	 *
	 * @return bool
	 */
	public function is_optional() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function why() {
		return __( 'When you state facts, linking to a recognized authority — a .gov or .edu site, an official body, a peer-reviewed journal, or a major encyclopedia — signals to AI engines that your claims are well grounded and worth citing. Optional and context-dependent: most useful on fact-heavy pages, and safe to skip where it does not apply.', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$authority = GeoContent::authority_link_count( $context->content() );
		$has       = $authority > 0;

		$value = $has ? 100 : 0;

		$checks = [
			$this->check(
				$has
					? sprintf(
						/* translators: %d: number of high-authority links found. */
						_n(
							'Cites %d high-authority source (.gov, .edu, or major reference)',
							'Cites %d high-authority sources (.gov, .edu, or major references)',
							$authority,
							'review-schema'
						),
						$authority
					)
					: __( 'No high-authority sources cited yet (.gov, .edu, or major references)', 'review-schema' ),
				$has
			),
		];

		$suggestions = [];
		if ( ! $has ) {
			$suggestions[] = $this->suggestion(
				'med',
				__( 'Optional: cite a high-authority source', 'review-schema' ),
				__( 'Where you state facts, link a claim to a .gov, .edu, official body, peer-reviewed journal, or major encyclopedia. It strengthens how trustworthy AI engines consider the page. Optional and context-dependent — skip it where it does not apply.', 'review-schema' ),
				__( 'Optional', 'review-schema' ),
				'Low'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
