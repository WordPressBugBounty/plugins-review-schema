<?php
/**
 * AEO channel analyzer.
 *
 * Runs the deterministic Answer Engine Optimization criteria against a shared
 * AnalysisContext, builds the channel object the D5 frontend renders, and
 * exposes a `rtrs_aeo_analysis` filter so the Pro plugin can later layer AI
 * refinement on top of the baseline.
 *
 * @package Rtrs\Modules\Aeo\Analyzers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Analyzers;

use Rtrs\Modules\Aeo\Criteria\AnswerConcisenessCriterion;
use Rtrs\Modules\Aeo\Criteria\DirectAnswerCriterion;
use Rtrs\Modules\Aeo\Criteria\FaqCoverageCriterion;
use Rtrs\Modules\Aeo\Criteria\FaqPresenceCriterion;
use Rtrs\Modules\Aeo\Criteria\SnippetFitCriterion;
use Rtrs\Modules\Aeo\Criteria\VoicePhrasingCriterion;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\Grade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AeoAnalyzer
 */
class AeoAnalyzer {

	/**
	 * Analyze a post and return the AEO channel object.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $opts    Optional overrides: 'content' => string scores unsaved
	 *                       editor content for "re-analyze on change".
	 * @return array Channel object consumed by the D5 frontend.
	 */
	public static function analyze( $post_id, $opts = [] ) {
		$post_id          = (int) $post_id;
		$content_override = ( isset( $opts['content'] ) && is_string( $opts['content'] ) ) ? $opts['content'] : null;
		$context          = new AnalysisContext( $post_id, $content_override );

		$criteria_out = [];
		$suggestions  = [];
		$weighted_sum = 0;
		$weight_total = 0;

		foreach ( self::criteria() as $criterion ) {
			$result = $criterion->evaluate( $context );
			$value  = max( 0, min( 100, (int) $result->value ) );

			$criteria_out[] = [
				'key'     => $criterion->key(),
				'name'    => $criterion->name(),
				'value'   => $value,
				'status'  => Grade::status( $value ),
				'issues'  => count( $result->details ),
				'details' => array_values( $result->details ),
				'checks'  => array_values( $result->checks ),
				'locked'  => false,
				'weight'  => $criterion->weight(),
			];

			$weighted_sum += $value * $criterion->weight();
			$weight_total += $criterion->weight();

			foreach ( $result->suggestions as $suggestion ) {
				$suggestions[] = $suggestion;
			}
		}

		$score = $weight_total > 0 ? (int) round( $weighted_sum / $weight_total ) : 0;

		// A deterministic (non-AI) channel never reports a perfect 100 — that is
		// reserved for content confirmed by an AI review.
		$score = Grade::cap_deterministic( $score );

		// High-impact suggestions first.
		usort(
			$suggestions,
			static function ( $a, $b ) {
				$rank = [ 'high' => 0, 'med' => 1 ];
				$ra   = isset( $rank[ $a['sev'] ] ) ? $rank[ $a['sev'] ] : 2;
				$rb   = isset( $rank[ $b['sev'] ] ) ? $rank[ $b['sev'] ] : 2;
				return $ra <=> $rb;
			}
		);

		$channel = [
			'key'         => 'aeo',
			'label'       => 'AEO',
			'long'        => __( 'Answer Engine Optimization', 'review-schema' ),
			'score'       => $score,
			'grade'       => Grade::from_score( $score ),
			'headline'    => self::headline( $score ),
			'note'        => __( 'On-page answer-readiness only — actual answer and voice selection is decided by each engine.', 'review-schema' ),
			'ai_hint'     => Grade::ai_full_marks_hint( $score ),
			'criteria'    => $criteria_out,
			'suggestions' => array_slice( array_values( $suggestions ), 0, 6 ),
		];

		/**
		 * Filter the computed AEO analysis channel.
		 *
		 * Pro can hook here to refine criteria with AI and recompute the score.
		 *
		 * @param array           $channel The channel object.
		 * @param int             $post_id Post ID.
		 * @param AnalysisContext $context Shared analysis context.
		 */
		return apply_filters( 'rtrs_aeo_analysis', $channel, $post_id, $context );
	}

	/**
	 * Registered criteria, in display order.
	 *
	 * @return \Rtrs\Modules\Seo\Contracts\CriterionInterface[]
	 */
	private static function criteria() {
		return [
			new FaqPresenceCriterion(),
			new FaqCoverageCriterion(),
			new DirectAnswerCriterion(),
			new AnswerConcisenessCriterion(),
			new SnippetFitCriterion(),
			new VoicePhrasingCriterion(),
		];
	}

	/**
	 * Channel headline based on the aggregate score.
	 *
	 * @param int $score Score 0-100.
	 * @return string
	 */
	private static function headline( $score ) {
		if ( $score >= 90 ) {
			return __( 'Answer-first and AI-ready', 'review-schema' );
		}
		if ( $score >= 80 ) {
			return __( 'Strong answers, a little polish left', 'review-schema' );
		}
		if ( $score >= 65 ) {
			return __( 'Answers, but not always answer-first', 'review-schema' );
		}
		return __( 'Hard for answer engines to quote', 'review-schema' );
	}
}
