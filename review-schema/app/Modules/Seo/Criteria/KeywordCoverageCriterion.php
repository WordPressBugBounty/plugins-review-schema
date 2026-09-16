<?php
/**
 * SEO criterion: Keyword Coverage (Step 4, Pro).
 *
 * Free build ships this as a locked placeholder. The Pro plugin replaces it
 * with real focus-keyword density/placement analysis via the
 * `rtrs_seo_analysis` filter.
 *
 * @package Rtrs\Modules\Seo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Criteria;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Contracts\CriterionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordCoverageCriterion
 */
class KeywordCoverageCriterion implements CriterionInterface {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'keyword_coverage';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Keyword Coverage', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 25;
	}

	/**
	 * @inheritDoc
	 */
	public function evaluate( AnalysisContext $context ) {
		// Locked in free; Pro supplies the real analysis via `rtrs_seo_analysis`.
		return CriterionResult::locked();
	}
}
