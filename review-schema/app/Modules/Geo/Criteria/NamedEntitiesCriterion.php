<?php
/**
 * GEO criterion: Named Entities.
 *
 * Generative engines ground answers in recognizable entities (people, places,
 * brands, organizations). Rewards posts that name several distinct multi-word
 * proper nouns relative to their length.
 *
 * @package Rtrs\Modules\Geo\Criteria
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Criteria;

use Rtrs\Modules\Geo\Helpers\GeoContent;
use Rtrs\Modules\Seo\Analysis\AnalysisContext;
use Rtrs\Modules\Seo\Analysis\CriterionResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NamedEntitiesCriterion
 */
class NamedEntitiesCriterion extends AbstractGeoCriterion {

	/**
	 * One distinct entity per this many words is a healthy target.
	 */
	const WORDS_PER_ENTITY = 150;

	/**
	 * @inheritDoc
	 */
	public function key() {
		return 'named_entities';
	}

	/**
	 * @inheritDoc
	 */
	public function name() {
		return __( 'Named Entities', 'review-schema' );
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
	 * @param AnalysisContext $context Per-post content context.
	 * @return CriterionResult
	 */
	public function evaluate( AnalysisContext $context ) {
		$entities = GeoContent::entities( $context->plain_text() );
		$distinct = count( $entities );
		$words    = $context->word_count();
		$target   = max( 2, (int) floor( $words / self::WORDS_PER_ENTITY ) );

		$enough = $distinct >= $target;

		$value = $target > 0 ? (int) round( min( 100, $distinct / $target * 100 ) ) : 0;

		// Summary row first.
		$checks = [
			$this->check(
				sprintf(
					/* translators: 1: distinct entity count, 2: recommended count. */
					_n( 'Names %1$d distinct entity (target %2$d)', 'Names %1$d distinct entities (target %2$d)', $distinct, 'review-schema' ),
					$distinct,
					$target
				),
				$enough
			),
		];

		// Pass row naming the detected entities so the author sees what was found.
		if ( $distinct > 0 ) {
			$shown = array_slice( $entities, 0, 8 );
			$list  = implode( ', ', $shown );
			if ( $distinct > count( $shown ) ) {
				$list .= sprintf(
					/* translators: %d: number of additional entities. */
					__( ', +%d more', 'review-schema' ),
					$distinct - count( $shown )
				);
			}
			$checks[] = $this->check(
				sprintf(
					/* translators: %s: comma-separated list of detected entities. */
					__( 'Detected: %s', 'review-schema' ),
					$list
				),
				true
			);
		}

		$suggestions = [];
		if ( $value < 100 && ! $enough ) {
			$suggestions[] = $this->suggestion(
				$distinct < max( 1, (int) floor( $target / 2 ) ) ? 'high' : 'med',
				__( 'Name more specific entities', 'review-schema' ),
				sprintf(
					/* translators: 1: distinct count, 2: target count. */
					__( 'Generative engines cite content rich in recognizable people, places, brands and organizations. You name %1$d distinct entit%3$s; aim for about %2$d. Use full proper names instead of generic nouns ("the resort" → "Whispering Pines Resort").', 'review-schema' ),
					$distinct,
					$target,
					1 === $distinct ? 'y' : 'ies'
				),
				__( '+15 pts', 'review-schema' ),
				'Med'
			);
		}

		return $this->result( $value, $checks, $suggestions );
	}
}
