<?php
/**
 * SEO criterion: Heading Hierarchy (Step 2, free).
 *
 * Checks for a single H1 (the post title is treated as the implicit H1), the
 * presence of H2 subheadings, and that heading levels are not skipped.
 *
 * @package Rtrs\Modules\Seo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Criteria;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Analyzers\HeadingHierarchyAnalyzer;
use Rtrs\Modules\Seo\Contracts\CriterionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HeadingHierarchyCriterion
 */
class HeadingHierarchyCriterion implements CriterionInterface {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'heading_hierarchy';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Heading Hierarchy', 'review-schema' );
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
	 * Delegates to HeadingHierarchyAnalyzer (the single source of truth) and
	 * adapts its normalized result into a CriterionResult so the SEO panel and
	 * the REST endpoint always agree.
	 */
	public function evaluate( AnalysisContext $context ) {
		$analysis = HeadingHierarchyAnalyzer::analyze_context( $context );

		$details     = [];
		$suggestions = [];

		foreach ( $analysis['issues'] as $issue ) {
			$message   = isset( $issue['message'] ) ? $issue['message'] : '';
			$details[] = $message;

			$suggestions[] = $this->suggestion(
				isset( $issue['severity'] ) && 'critical' === $issue['severity'] ? 'high' : 'med',
				$message,
				isset( $issue['body'] ) ? $issue['body'] : '',
				$this->impact_label( isset( $issue['points'] ) ? $issue['points'] : 0 ),
				isset( $issue['effort'] ) ? ucfirst( $issue['effort'] ) : 'Low'
			);
		}

		$result         = new CriterionResult( (int) $analysis['score'], $details, $suggestions );
		$result->checks = isset( $analysis['checks'] ) ? $analysis['checks'] : [];
		return $result;
	}

	/**
	 * Format the recoverable points for a suggestion impact label.
	 *
	 * @param int $points Points impact from the analyzer (negative = lost).
	 * @return string Empty string when there is nothing to recover.
	 */
	private function impact_label( $points ) {
		$points = (int) $points;
		if ( 0 === $points ) {
			return '';
		}
		return sprintf( '+%d pts', abs( $points ) );
	}

	/**
	 * Build a suggestion row.
	 *
	 * @param string $sev    Severity.
	 * @param string $title  Title.
	 * @param string $body   Body.
	 * @param string $impact Impact label.
	 * @param string $effort Effort label.
	 * @return array
	 */
	private function suggestion( $sev, $title, $body, $impact, $effort ) {
		return [
			'sev'    => $sev,
			'title'  => $title,
			'body'   => $body,
			'impact' => $impact,
			'effort' => $effort,
		];
	}
}
