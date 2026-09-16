<?php
/**
 * AEO criterion: FAQ Content & Schema.
 *
 * A page is FAQ-ready only when it shows a real FAQ AND emits FAQPage schema.
 * Neither present → 0; only one present → capped partial; both → full marks.
 * (Coverage — how many Q&A pairs — is scored separately by FaqCoverageCriterion.)
 *
 * @package Rtrs\Modules\Aeo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Criteria;

use Rtrs\Modules\Aeo\Helpers\FaqSignals;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FaqPresenceCriterion
 */
class FaqPresenceCriterion extends AbstractAeoCriterion {

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'faq_presence';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'FAQ Content & Schema', 'review-schema' );
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
		$signals     = FaqSignals::detect( $context );
		$has_content = ! empty( $signals['content'] );
		$has_schema  = ! empty( $signals['schema'] );

		if ( ! $has_content && ! $has_schema ) {
			$value = 0;
		} elseif ( $has_content && $has_schema ) {
			$value = 100;
		} else {
			$value = 50;
		}

		// Labels state the actual outcome — a failing row must not read "present".
		// They also feed CriterionResult::$details (the issue list), so the wording
		// has to make sense on its own.
		$checks = [
			$this->check(
				$has_content
					? __( 'FAQ content present (FAQ block or definition list)', 'review-schema' )
					: __( 'FAQ content missing (add a FAQ block or definition list)', 'review-schema' ),
				$has_content
			),
			$this->check(
				$has_schema
					? __( 'FAQPage schema present', 'review-schema' )
					: __( 'FAQPage schema missing', 'review-schema' ),
				$has_schema
			),
		];

		$suggestions = [];
		if ( ! $has_content && ! $has_schema ) {
			$suggestions[] = $this->suggestion(
				'high',
				__( 'Add a FAQ with FAQPage schema', 'review-schema' ),
				__( 'This page has no FAQ. Add a FAQ block (or the metabox FAQ) with real question/answer pairs — it renders the Q&A and emits the FAQPage schema answer engines lift.', 'review-schema' ),
				__( '+100 pts', 'review-schema' ),
				'Med'
			);
		} elseif ( ! $has_schema ) {
			$suggestions[] = $this->suggestion(
				'high',
				__( 'Add FAQPage schema to your FAQ', 'review-schema' ),
				__( 'The FAQ is visible but carries no FAQPage schema, so engines can\'t reliably lift it. Use the plugin FAQ block or metabox FAQ, which output FAQPage structured data.', 'review-schema' ),
				__( '+50 pts', 'review-schema' ),
				'Med'
			);
		} elseif ( ! $has_content ) {
			$suggestions[] = $this->suggestion(
				'high',
				__( 'Show your FAQ on the page', 'review-schema' ),
				__( 'FAQPage schema exists but the FAQ is not visible in the content. Google requires the FAQ to be shown on the page — add the FAQ block or the [rtrs_faqpage] output.', 'review-schema' ),
				__( '+50 pts', 'review-schema' ),
				'Med'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
