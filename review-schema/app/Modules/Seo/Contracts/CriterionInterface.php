<?php
/**
 * Contract implemented by every analysis criterion (SEO, and later AEO/GEO).
 *
 * @package Rtrs\Modules\Seo\Contracts
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Contracts;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface CriterionInterface
 */
interface CriterionInterface {

	/**
	 * Stable machine key, e.g. 'title_meta'.
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Human label shown in the UI, e.g. 'Title & Meta'.
	 *
	 * @return string
	 */
	public function name();

	/**
	 * Relative weight used when aggregating the channel score.
	 *
	 * @return int
	 */
	public function weight();

	/**
	 * Evaluate this criterion against the shared context.
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context );
}
