<?php
/**
 * Base class for AEO criteria.
 *
 * Provides small builders for suggestions, checklist rows, and the final
 * CriterionResult (with `details` auto-derived from failing checks) so each
 * concrete criterion only needs its scoring logic.
 *
 * @package Rtrs\Modules\Aeo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Criteria;

use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Contracts\CriterionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractAeoCriterion
 */
abstract class AbstractAeoCriterion implements CriterionInterface {

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
	 * @param string      $label  Human label.
	 * @param bool        $pass   Whether the check passed.
	 * @param string      $detail Optional plain-language "what to do" hint shown under the label.
	 * @param array|null  $apply  Optional "Suggest with AI" hint, e.g. [ 'target' => 'section_lead', 'heading' => '…' ].
	 * @param string|null $status Optional explicit status override ('suggestion' for
	 *                            advisory rows that render as a suggestion and never
	 *                            count against the score). Defaults to pass/warn.
	 * @return array
	 */
	protected function check( $label, $pass, $detail = '', $apply = null, $status = null ) {
		$row = [
			'label'  => $label,
			'status' => null !== $status ? $status : ( $pass ? 'pass' : 'warn' ),
		];

		if ( '' !== $detail ) {
			$row['detail'] = $detail;
		}

		if ( null !== $apply ) {
			$row['apply'] = $apply;
		}

		return $row;
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
			// 'suggestion' rows are advisory only — excluded from issue details so
			// they never inflate the issue count or drive the score.
			if ( 'pass' !== $check['status'] && 'suggestion' !== $check['status'] ) {
				$details[] = $check['label'];
			}
		}

		$result         = new CriterionResult( (int) $value, $details, $suggestions );
		$result->checks = $checks;

		return $result;
	}
}
