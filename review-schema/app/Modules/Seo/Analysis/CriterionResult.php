<?php
/**
 * Value object holding the outcome of a single SEO criterion evaluation.
 *
 * @package Rtrs\Modules\Seo\Analysis
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CriterionResult
 *
 * Returned by every CriterionInterface::evaluate(). Carries the 0-100 score,
 * the list of detected issue strings, the actionable suggestions, and a
 * `locked` flag used for Pro-gated criteria that are not computed in free.
 */
class CriterionResult {

	/**
	 * Score for this criterion, 0-100.
	 *
	 * @var int
	 */
	public $value;

	/**
	 * Short issue strings describing what lowered the score.
	 *
	 * @var string[]
	 */
	public $details;

	/**
	 * Actionable suggestions: each [ 'sev', 'title', 'body', 'impact', 'effort' ].
	 *
	 * @var array[]
	 */
	public $suggestions;

	/**
	 * Whether this criterion is locked (Pro-only, not computed in free).
	 *
	 * @var bool
	 */
	public $locked;

	/**
	 * Display-only checklist: every sub-check with its status.
	 *
	 * Each entry is [ 'label' => string, 'status' => 'pass'|'warn' ]. Unlike
	 * `details` (failures only), this lists passing checks too, so the UI can
	 * render a green/amber checklist.
	 *
	 * @var array[]
	 */
	public $checks = [];

	/**
	 * Constructor.
	 *
	 * @param int      $value       Score 0-100.
	 * @param string[] $details     Issue strings.
	 * @param array[]  $suggestions Suggestion rows.
	 * @param bool     $locked      Locked flag.
	 */
	public function __construct( $value = 0, array $details = [], array $suggestions = [], $locked = false ) {
		$this->value       = (int) $value;
		$this->details     = $details;
		$this->suggestions = $suggestions;
		$this->locked      = (bool) $locked;
	}

	/**
	 * Build a locked (Pro-gated) result.
	 *
	 * @return self
	 */
	public static function locked() {
		return new self( 0, [], [], true );
	}
}
