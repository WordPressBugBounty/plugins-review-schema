<?php
/**
 * SEO criterion: Internal Links (Step 3, free).
 *
 * Scores the number of internal links relative to content length, flagging
 * pages with no internal links or excessive linking.
 *
 * @package Rtrs\Modules\Seo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Criteria;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Analyzers\InternalLinksAnalyzer;
use Rtrs\Modules\Seo\Contracts\CriterionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InternalLinksCriterion
 */
class InternalLinksCriterion implements CriterionInterface {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'internal_links';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Internal Links', 'review-schema' );
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
	 * Delegates to InternalLinksAnalyzer (the single source of truth) and adapts
	 * its normalized result into a CriterionResult so the SEO panel and the REST
	 * endpoint always agree.
	 */
	public function evaluate( AnalysisContext $context ) {
		$analysis = InternalLinksAnalyzer::analyze_context( $context );

		$details     = [];
		$suggestions = [];

		foreach ( $analysis['issues'] as $issue ) {
			$message   = isset( $issue['message'] ) ? $issue['message'] : '';
			$details[] = $message;

			$suggestions[] = $this->suggestion(
				isset( $issue['severity'] ) && 'critical' === $issue['severity'] ? 'high' : 'med',
				$message,
				'',
				$this->impact_label( isset( $issue['points'] ) ? $issue['points'] : 0 ),
				isset( $issue['effort'] ) ? ucfirst( $issue['effort'] ) : 'Low',
				isset( $issue['apply'] ) ? $issue['apply'] : null
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
	 * @param string     $sev    Severity.
	 * @param string     $title  Title.
	 * @param string     $body   Body.
	 * @param string     $impact Impact label.
	 * @param string     $effort Effort label.
	 * @param array|null $apply  Optional "Improve with AI" hint, e.g. [ 'target' => 'internal_links' ].
	 * @return array
	 */
	private function suggestion( $sev, $title, $body, $impact, $effort, $apply = null ) {
		$row = [
			'sev'    => $sev,
			'title'  => $title,
			'body'   => $body,
			'impact' => $impact,
			'effort' => $effort,
		];
		if ( null !== $apply ) {
			$row['apply'] = $apply;
		}
		return $row;
	}
}
