<?php
/**
 * SEO criterion: Title & Meta (Step 1, free).
 *
 * Scores the SEO title and meta description on presence, length and (when a
 * focus keyword is available) keyword placement in the title.
 *
 * @package Rtrs\Modules\Seo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Criteria;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Analyzers\TitleMetaAnalyzer;
use Rtrs\Modules\Seo\Contracts\CriterionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TitleMetaCriterion
 */
class TitleMetaCriterion implements CriterionInterface {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'title_meta';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Title & Meta', 'review-schema' );
	}

	/**
	 * @inheritDoc
	 */
	public function weight() {
		return 25;
	}

	/**
	 * @inheritDoc
	 *
	 * Delegates to TitleMetaAnalyzer (the single source of truth for this
	 * dimension) and adapts its normalized result into a CriterionResult so the
	 * SEO panel and the REST endpoint always agree. The deterministic baseline
	 * is identical to the endpoint; the channel just maps issues → details and
	 * suggestions for the existing UI.
	 */
	public function evaluate( AnalysisContext $context ) {
		$analysis = TitleMetaAnalyzer::analyze( $context->post_id() );

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
	 * @param string     $sev    Severity ('high'|'med').
	 * @param string     $title  Suggestion title.
	 * @param string     $body   Suggestion body.
	 * @param string     $impact Impact label.
	 * @param string     $effort Effort label.
	 * @param array|null $apply  Optional "Apply with AI" hint, e.g. [ 'target' => 'title'|'meta' ].
	 * @return array
	 */
	private function suggestion( $sev, $title, $body, $impact, $effort, $apply = null ) {
		$suggestion = [
			'sev'    => $sev,
			'title'  => $title,
			'body'   => $body,
			'impact' => $impact,
			'effort' => $effort,
		];
		if ( null !== $apply ) {
			$suggestion['apply'] = $apply;
		}
		return $suggestion;
	}
}
