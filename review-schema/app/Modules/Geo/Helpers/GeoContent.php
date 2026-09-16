<?php
/**
 * Content parsing helpers shared by the GEO criteria.
 *
 * Pulls "citability" signals (named entities, outbound authority links) out of
 * rendered post HTML for the Generative Engine Optimization channel. Kept
 * stateless so every criterion can reuse the same parsing without side effects.
 *
 * @package Rtrs\Modules\Geo\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Geo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeoContent
 */
class GeoContent {

	/**
	 * Words that should never start (or stand in for) a named entity.
	 *
	 * Sentence-initial capitalized words and common headers would otherwise be
	 * mistaken for proper nouns.
	 *
	 * @var string[]
	 */
	const NOISE_WORDS = [
		'The', 'This', 'That', 'These', 'Those', 'A', 'An', 'And', 'But', 'Or',
		'For', 'Nor', 'So', 'Yet', 'If', 'When', 'While', 'Because', 'Although',
		'Our', 'Your', 'Their', 'His', 'Her', 'Its', 'My', 'We', 'You', 'They',
		'It', 'He', 'She', 'How', 'What', 'Why', 'Where', 'Who', 'Which',
	];

	/**
	 * Extract distinct multi-word capitalized phrases (candidate named entities).
	 *
	 * Matches runs of two or more Capitalized words (e.g. "Whispering Pines
	 * Valley", "Old Mill Tavern"), trims leading noise/sentence-starter words,
	 * and de-duplicates case-insensitively while preserving first-seen casing.
	 *
	 * @param string $text Plain-text body.
	 * @return string[] Distinct entity phrases in first-seen order.
	 */
	public static function entities( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return [];
		}

		if ( ! preg_match_all( '/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\b/u', $text, $matches ) ) {
			return [];
		}

		$seen     = [];
		$entities = [];
		foreach ( $matches[1] as $phrase ) {
			$phrase = self::trim_noise( $phrase );
			if ( '' === $phrase || false === strpos( $phrase, ' ' ) ) {
				continue; // Dropped to a single word after trimming noise.
			}

			$key = strtolower( $phrase );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$entities[]   = $phrase;
		}

		return $entities;
	}

	/**
	 * Strip leading noise words (sentence starters, pronouns) from a phrase.
	 *
	 * @param string $phrase Capitalized phrase.
	 * @return string
	 */
	private static function trim_noise( $phrase ) {
		$words = preg_split( '/\s+/', trim( (string) $phrase ) );
		while ( ! empty( $words ) && in_array( $words[0], self::NOISE_WORDS, true ) ) {
			array_shift( $words );
		}

		return implode( ' ', $words );
	}

	/**
	 * Quotable statistics in the body text.
	 *
	 * Captures the kinds of figures generative engines lift verbatim:
	 * percentages, currency amounts, "X out of Y", "N times", and measurements
	 * with units. Returns the distinct matched fragments.
	 *
	 * @param string $text Plain-text body.
	 * @return string[] Distinct statistic fragments, first-seen order.
	 */
	public static function statistics( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return [];
		}

		$patterns = [
			'/\d+(?:\.\d+)?\s?%/',                                            // 42% / 3.5 %
			'/[\$\x{20AC}\x{00A3}\x{00A5}]\s?\d[\d,\.]*(?:\s?(?:million|billion|k|m|bn))?/iu', // $1,200 / €3.5 million
			'/\b\d[\d,\.]*\s+(?:out\s+of|in|per)\s+\d[\d,\.]*\b/i',           // 3 out of 5 / 1 in 4
			'/\b\d+(?:\.\d+)?\s?(?:x|times)\b/i',                             // 2x / 3 times
			'/\b\d[\d,\.]*\s?(?:kg|g|mg|lb|lbs|oz|km|m|cm|mm|mph|kmh|kwh|gb|mb|tb|ghz|mhz|°[cf]|hours?|minutes?|seconds?|days?|years?|people|users|customers|reviews|ratings)\b/i',
		];

		$seen  = [];
		$stats = [];
		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $hit ) {
					$hit = trim( preg_replace( '/\s+/', ' ', $hit ) );
					$key = strtolower( $hit );
					if ( '' === $key || isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$stats[]      = $hit;
				}
			}
		}

		return $stats;
	}

	/**
	 * Whether a single sentence carries a concrete, citable claim.
	 *
	 * True when it contains a statistic, a multi-word named entity, or a
	 * four-digit year — i.e. a fact an engine could lift.
	 *
	 * @param string $sentence One sentence of plain text.
	 * @return bool
	 */
	public static function is_factual( $sentence ) {
		$sentence = (string) $sentence;
		if ( '' === trim( $sentence ) ) {
			return false;
		}

		if ( ! empty( self::statistics( $sentence ) ) ) {
			return true;
		}
		if ( ! empty( self::entities( $sentence ) ) ) {
			return true;
		}

		return (bool) preg_match( '/\b(?:19|20)\d{2}\b/', $sentence ); // A year.
	}

	/**
	 * Count outbound links whose host is a high-authority domain.
	 *
	 * Recognizes government / education TLDs plus a curated set of widely
	 * trusted reference and research domains.
	 *
	 * @param string $html Rendered post HTML.
	 * @return int
	 */
	public static function authority_link_count( $html ) {
		$count = 0;

		if ( preg_match_all( '/href=["\']([^"\']+)/i', (string) $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( 0 !== strpos( $url, 'http' ) ) {
					continue; // Internal / relative link.
				}
				if ( preg_match( '#https?://[^/]*(\.gov|\.edu|\.gov\.[a-z]{2}|\.ac\.[a-z]{2})(?:[/:]|$)#i', $url )
					|| preg_match( '#https?://[^/]*\b(wikipedia\.org|wikidata\.org|nih\.gov|who\.int|nature\.com|sciencedirect\.com|ncbi\.nlm\.nih\.gov|britannica\.com|reuters\.com|nasa\.gov|un\.org|oecd\.org|worldbank\.org)\b#i', $url ) ) {
					$count++;
				}
			}
		}

		return $count;
	}
}
