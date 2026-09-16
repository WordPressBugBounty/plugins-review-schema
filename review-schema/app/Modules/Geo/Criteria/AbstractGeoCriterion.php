<?php
/**
 * Base class for GEO criteria.
 *
 * Provides small builders for suggestions, checklist rows, and the final
 * CriterionResult (with `details` auto-derived from failing checks) so each
 * concrete criterion only needs its scoring logic.
 *
 * @package Rtrs\Modules\Geo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Criteria;

use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Contracts\CriterionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractGeoCriterion
 */
abstract class AbstractGeoCriterion implements CriterionInterface {

	/**
	 * Whether this criterion is an optional, context-dependent recommendation.
	 *
	 * Optional criteria are surfaced to the author as guidance but are excluded
	 * from the weighted channel score — they must never penalize content for
	 * which they do not apply. Concrete criteria override to return true.
	 *
	 * @return bool
	 */
	public function is_optional() {
		return false;
	}

	/**
	 * Short, plain-language reason this optional recommendation can help.
	 *
	 * Shown beneath an optional item to explain why it could improve AI
	 * visibility / GEO. Empty for required criteria.
	 *
	 * @return string
	 */
	public function why() {
		return '';
	}

	/**
	 * Build a suggestion row.
	 *
	 * @param string $sev    Severity ('high'|'med').
	 * @param string $title  Short title.
	 * @param string $body   Detail text.
	 * @param string $impact Impact label, e.g. '+11 pts'.
	 * @param string $effort Effort label.
	 * @return array
	 */
	protected function suggestion( $sev, $title, $body = '', $impact = '', $effort = 'Low' ) {
		return [
			'sev'    => $sev,
			'title'  => $title,
			'body'   => $body,
			'impact' => $impact,
			'effort' => $effort,
		];
	}

	/**
	 * Build a checklist row.
	 *
	 * @param string $label Human label.
	 * @param bool   $pass  Whether the check passed.
	 * @return array
	 */
	protected function check( $label, $pass ) {
		return [
			'label'  => $label,
			'status' => $pass ? 'pass' : 'warn',
		];
	}

	/**
	 * Assemble a CriterionResult, deriving issue details from failing checks.
	 *
	 * @param int     $value       Score 0-100.
	 * @param array[] $checks      Checklist rows.
	 * @param array[] $suggestions Suggestion rows.
	 * @return CriterionResult
	 */
	protected function result( $value, array $checks, array $suggestions = [] ) {
		$details = [];
		foreach ( $checks as $check ) {
			if ( 'pass' !== $check['status'] ) {
				$details[] = $check['label'];
			}
		}

		$result         = new CriterionResult( (int) $value, $details, $suggestions );
		$result->checks = $checks;

		return $result;
	}
}
