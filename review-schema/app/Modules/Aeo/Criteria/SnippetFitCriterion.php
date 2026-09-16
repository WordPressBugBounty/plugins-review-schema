<?php
/**
 * AEO criterion: Featured Snippet Fit.
 *
 * Google lifts three snippet formats: paragraph, list and table. Rewards a
 * question-style heading plus snippet-friendly list/table structures.
 *
 * @package Rtrs\Modules\Aeo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Criteria;

use Rtrs\Modules\Aeo\Helpers\AeoContent;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SnippetFitCriterion
 */
class SnippetFitCriterion extends AbstractAeoCriterion {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'snippet_fit';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Featured Snippet Fit', 'review-schema' );
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
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$html = $context->content();

		$has_question = false;
		foreach ( $context->headings() as $heading ) {
			if ( AeoContent::is_question( $heading['text'] ) ) {
				$has_question = true;
				break;
			}
		}

		$has_list    = AeoContent::has_list( $html );
		$has_table   = AeoContent::has_table( $html );
		$has_ordered = AeoContent::has_ordered_list( $html );

		$checks = [
			$this->check( __( 'Question-style heading present', 'review-schema' ), $has_question, '', $has_question ? null : [ 'target' => 'snippet_heading' ] ),
			$this->check( __( 'Includes a list (bulleted or numbered)', 'review-schema' ), $has_list, '', $has_list ? null : [ 'target' => 'snippet_list' ] ),
			$this->check( __( 'Includes a comparison table', 'review-schema' ), $has_table, '', $has_table ? null : [ 'target' => 'snippet_table' ] ),
			$this->check( __( 'Includes a numbered step-by-step list', 'review-schema' ), $has_ordered, '', $has_ordered ? null : [ 'target' => 'snippet_steps' ] ),
		];

		// Ordered lists win "how-to" list snippets, so give a small extra credit.
		$score = ( $has_question ? 45 : 0 ) + ( $has_list ? 35 : 0 ) + ( $has_table ? 20 : 0 ) + ( $has_ordered ? 5 : 0 );
		$value = max( $score, $context->word_count() > 0 ? 20 : 0 );
		$value = min( 100, $value );

		$suggestions = [];
		if ( ! $has_list && ! $has_table ) {
			$suggestions[] = $this->suggestion(
				'med',
				__( 'Add a list or table for snippet eligibility', 'review-schema' ),
				__( 'Paragraph, list and table are the three snippet formats Google lifts. A bulleted list or comparison table makes the page eligible.', 'review-schema' ),
				__( '+8 pts', 'review-schema' ),
				'Low'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
