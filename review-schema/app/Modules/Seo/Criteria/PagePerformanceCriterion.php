<?php
/**
 * SEO criterion: Page Performance (Step 5, Pro).
 *
 * Free build ships this as a locked placeholder. The Pro plugin replaces it
 * with real performance metrics (e.g. PageSpeed Insights / Core Web Vitals,
 * cached in a transient) via the `rtrs_seo_analysis` filter.
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
 * Class PagePerformanceCriterion
 */
class PagePerformanceCriterion implements CriterionInterface {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'page_performance';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Page Performance', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function evaluate( AnalysisContext $context ) {
		// Locked in free; Pro supplies the real analysis via `rtrs_seo_analysis`.
		return CriterionResult::locked();
	}
}
