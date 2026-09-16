<?php
/**
 * SEO channel analyzer.
 *
 * Runs the registered SEO criteria against a single AnalysisContext, builds
 * the channel object the D5 frontend renders, and exposes a `rtrs_seo_analysis`
 * filter so the Pro plugin can replace the locked Keyword / Performance
 * criteria with real analysis.
 *
 * @package Rtrs\Modules\Seo\Analyzers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analyzers;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;
use Rtrs\Modules\Seo\Analysis\Grade;
use Rtrs\Modules\Seo\Analysis\SeoSuggestions;
use Rtrs\Modules\Seo\Criteria\HeadingHierarchyCriterion;
use Rtrs\Modules\Seo\Criteria\InternalLinksCriterion;
use Rtrs\Modules\Seo\Criteria\KeywordCoverageCriterion;
use Rtrs\Modules\Seo\Criteria\PagePerformanceCriterion;
use Rtrs\Modules\Seo\Criteria\TitleMetaCriterion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoAnalyzer
 */
class SeoAnalyzer {

	/**
	 * Analyze a post and return the SEO channel object.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $opts    Optional live overrides for "re-analyze on change":
	 *                       'title_meta' => [ 'title' => string, 'meta' => string ]
	 *                       scores unsaved Title & Meta; 'content' => string scores
	 *                       unsaved post content (headings, internal links). The
	 *                       whole channel is recomputed so the panel stays consistent.
	 * @return array Channel object consumed by the D5 frontend.
	 */
	public static function analyze( $post_id, $opts = [] ) {
		$post_id          = (int) $post_id;
		$content_override = ( isset( $opts['content'] ) && is_string( $opts['content'] ) ) ? $opts['content'] : null;
		$context          = new AnalysisContext( $post_id, $content_override );

		$tm_override = ( isset( $opts['title_meta'] ) && is_array( $opts['title_meta'] ) ) ? $opts['title_meta'] : null;

		$criteria_out = [];
		$suggestions  = [];
		$weighted_sum = 0;
		$weight_total = 0;

		foreach ( self::criteria() as $criterion ) {
			if ( null !== $tm_override && 'title_meta' === $criterion->key() ) {
				$analysis = TitleMetaAnalyzer::analyze_strings(
					isset( $tm_override['title'] ) ? $tm_override['title'] : '',
					isset( $tm_override['meta'] ) ? $tm_override['meta'] : ''
				);
				$result = self::result_from_title_meta( $analysis );
			} else {
				$result = $criterion->evaluate( $context );
			}

			if ( $result->locked ) {
				$criteria_out[] = [
					'key'     => $criterion->key(),
					'name'    => $criterion->name(),
					'value'   => 0,
					'status'  => 'warn',
					'issues'  => 0,
					'details' => [],
					'checks'  => [],
					'locked'  => true,
					'weight'  => $criterion->weight(),
				];
				continue;
			}

			$value = max( 0, min( 100, (int) $result->value ) );

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

		// High-impact + AI-actionable suggestions first, then cap.
		$suggestions = SeoSuggestions::prioritize( $suggestions, SeoSuggestions::CAP );

		$channel = [
			'key'         => 'seo',
			'label'       => 'SEO',
			'long'        => __( 'Search Engine Optimization', 'review-schema' ),
			'score'       => $score,
			'grade'       => Grade::from_score( $score ),
			'headline'    => self::headline( $score ),
			'note'        => __( 'On-page signals only — real ranking also depends on backlinks, site authority, and search intent.', 'review-schema' ),
			'ai_hint'     => Grade::ai_full_marks_hint( $score ),
			'criteria'    => $criteria_out,
			'suggestions' => $suggestions,
		];

		/**
		 * Filter the computed SEO analysis channel.
		 *
		 * Pro hooks here to replace the locked Keyword Coverage / Page
		 * Performance criteria with real values and recompute the score.
		 *
		 * @param array           $channel The channel object.
		 * @param int             $post_id Post ID.
		 * @param AnalysisContext $context Shared analysis context.
		 */
		return apply_filters( 'rtrs_seo_analysis', $channel, $post_id, $context );
	}

	/**
	 * Adapt a TitleMetaAnalyzer result into a CriterionResult.
	 *
	 * Mirrors the mapping used by TitleMetaCriterion so the live override and
	 * the saved path produce identical channel entries.
	 *
	 * @param array $analysis Normalized object from TitleMetaAnalyzer.
	 * @return CriterionResult
	 */
	private static function result_from_title_meta( array $analysis ) {
		$details     = [];
		$suggestions = [];

		$issues = isset( $analysis['issues'] ) ? $analysis['issues'] : [];
		foreach ( $issues as $issue ) {
			$message   = isset( $issue['message'] ) ? $issue['message'] : '';
			$details[] = $message;
			$points    = isset( $issue['points'] ) ? (int) $issue['points'] : 0;

			$suggestion = [
				'sev'    => isset( $issue['severity'] ) && 'critical' === $issue['severity'] ? 'high' : 'med',
				'title'  => $message,
				'body'   => '',
				'impact' => 0 !== $points ? sprintf( '+%d pts', abs( $points ) ) : '',
				'effort' => isset( $issue['effort'] ) ? ucfirst( $issue['effort'] ) : 'Low',
			];
			if ( isset( $issue['apply'] ) ) {
				$suggestion['apply'] = $issue['apply'];
			}
			$suggestions[] = $suggestion;
		}

		$result         = new CriterionResult( (int) $analysis['score'], $details, $suggestions );
		$result->checks = isset( $analysis['checks'] ) ? $analysis['checks'] : [];
		return $result;
	}

	/**
	 * Registered criteria, in display order.
	 *
	 * @return \Rtrs\Modules\Seo\Contracts\CriterionInterface[]
	 */
	private static function criteria() {
		return [
			new TitleMetaCriterion(),
			new HeadingHierarchyCriterion(),
			new InternalLinksCriterion(),
			new KeywordCoverageCriterion(),
			new PagePerformanceCriterion(),
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
			return __( 'Excellent on-page SEO', 'review-schema' );
		}
		if ( $score >= 80 ) {
			return __( 'Ranking-ready, with room to grow', 'review-schema' );
		}
		if ( $score >= 65 ) {
			return __( 'Solid foundation, a few fixes needed', 'review-schema' );
		}
		return __( 'Several on-page issues to address', 'review-schema' );
	}
}
