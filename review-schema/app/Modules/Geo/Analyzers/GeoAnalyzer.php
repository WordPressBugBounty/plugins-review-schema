<?php
/**
 * GEO channel analyzer.
 *
 * Runs the deterministic Generative Engine Optimization criteria against a
 * shared AnalysisContext, builds the channel object the D5 frontend renders, and
 * exposes a `rtrs_geo_analysis` filter so the Pro plugin can later layer AI
 * refinement on top of the baseline.
 *
 * @package Rtrs\Modules\Geo\Analyzers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Analyzers;

use Rtrs\Modules\Geo\Criteria\AbstractGeoCriterion;
use Rtrs\Modules\Geo\Criteria\CitableStatisticsCriterion;
use Rtrs\Modules\Geo\Criteria\HighAuthoritySourcesCriterion;
use Rtrs\Modules\Geo\Criteria\NamedEntitiesCriterion;
use Rtrs\Modules\Geo\Criteria\SourceAuthorityCriterion;
use Rtrs\Modules\Geo\Criteria\StatementDensityCriterion;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\Grade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeoAnalyzer
 */
class GeoAnalyzer {

	/**
	 * Analyze a post and return the GEO channel object.
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
		$optional_out = [];
		$suggestions  = [];
		$weighted_sum = 0;
		$weight_total = 0;

		foreach ( self::criteria() as $criterion ) {
			$result   = $criterion->evaluate( $context );
			$value    = max( 0, min( 100, (int) $result->value ) );
			$optional = $criterion instanceof AbstractGeoCriterion && $criterion->is_optional();

			if ( $optional ) {
				// Optional recommendation: surfaced for guidance, excluded from
				// the score, and never framed as a failure.
				$optional_out[] = [
					'key'         => $criterion->key(),
					'name'        => $criterion->name(),
					'value'       => $value,
					'present'     => $value >= 100,
					'why'         => $criterion->why(),
					'checks'      => array_values( $result->checks ),
					'suggestions' => array_values( $result->suggestions ),
					'optional'    => true,
				];
				continue;
			}

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
			'key'         => 'geo',
			'label'       => 'GEO',
			'long'        => __( 'Generative Engine Optimization', 'review-schema' ),
			'score'       => $score,
			'grade'       => Grade::from_score( $score ),
			'headline'    => self::headline( $score ),
			'note'        => __( 'Content-readiness signals only — whether an AI engine actually cites you also depends on its own retrieval and ranking.', 'review-schema' ),
			'ai_hint'     => Grade::ai_full_marks_hint( $score ),
			'criteria'    => $criteria_out,
			'optional'    => $optional_out,
			'suggestions' => array_slice( array_values( $suggestions ), 0, 6 ),
		];

		/**
		 * Filter the computed GEO analysis channel.
		 *
		 * Pro can hook here to refine criteria with AI and recompute the score.
		 *
		 * @param array           $channel The channel object.
		 * @param int             $post_id Post ID.
		 * @param AnalysisContext $context Shared analysis context.
		 */
		return apply_filters( 'rtrs_geo_analysis', $channel, $post_id, $context );
	}

	/**
	 * Registered criteria, in display order.
	 *
	 * @return \Rtrs\Modules\Seo\Contracts\CriterionInterface[]
	 */
	private static function criteria() {
		return [
			new NamedEntitiesCriterion(),
			new CitableStatisticsCriterion(),
			new SourceAuthorityCriterion(),
			new StatementDensityCriterion(),
			new HighAuthoritySourcesCriterion(),
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
			return __( 'Highly citable by generative engines', 'review-schema' );
		}
		if ( $score >= 80 ) {
			return __( 'Citable, with room to sharpen', 'review-schema' );
		}
		if ( $score >= 65 ) {
			return __( 'Some citable signals, not yet authoritative', 'review-schema' );
		}
		return __( 'Hard for AI engines to cite', 'review-schema' );
	}
}
