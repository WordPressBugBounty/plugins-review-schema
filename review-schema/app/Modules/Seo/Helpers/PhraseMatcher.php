<?php
/**
 * Conservative local phrase matcher for sitemap-sourced internal linking.
 *
 * Finds strong occurrences of candidate phrases inside a piece of content using
 * a two-stage strategy:
 *   1. Discovery — a normalized 2–4 word n-gram set gives O(1) candidate lookup.
 *   2. Validation — an exact, case-insensitive, word-boundary regex on the
 *      original text confirms the match (optionally a plural on the final word)
 *      so partial-word and substring matches are never accepted.
 * The original text is preserved so the exact surrounding sentence can be handed
 * to the AI as context.
 *
 * @package Rtrs\Modules\Seo\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PhraseMatcher
 */
class PhraseMatcher {

	/** Original plain text (positions preserved). */
	private $text;

	/** Lower-cased copy of the text for validation. */
	private $lower;

	/** Set of normalized 2–4 word n-grams present in the text. */
	private $ngrams = [];

	/**
	 * @param string $plain_text Content to search within (plain text).
	 */
	public function __construct( $plain_text ) {
		$this->text  = (string) $plain_text;
		$this->lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $this->text ) : strtolower( $this->text );
		$this->build_ngrams();
	}

	/**
	 * Build the 2–4 word n-gram set from the content for fast discovery.
	 *
	 * @return void
	 */
	private function build_ngrams() {
		// Split on non-word runs; keep only word tokens, lower-cased.
		$tokens = preg_split( '/[^\p{L}\p{N}]+/u', $this->lower, -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $tokens ) ) {
			return;
		}
		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$gram = $tokens[ $i ];
			for ( $n = 1; $n < 4 && ( $i + $n ) < $count; $n++ ) {
				$gram                  .= ' ' . $tokens[ $i + $n ];
				$this->ngrams[ $gram ]  = true;
			}
			// Also index the single token so a 1-word discovery is possible.
			$this->ngrams[ $tokens[ $i ] ] = true;
		}
	}

	/**
	 * Find the first strong occurrence of a phrase.
	 *
	 * @param string $phrase Candidate phrase.
	 * @return array{phrase:string,offset:int,length:int}|null Match info or null.
	 */
	public function match( $phrase ) {
		$phrase = trim( preg_replace( '/\s+/', ' ', (string) $phrase ) );
		if ( '' === $phrase ) {
			return null;
		}

		// Stage 1 — discovery: the phrase's normalized n-gram must exist. The
		// singular/plural variant of the final word is also accepted so discovery
		// stays in step with the plural-tolerant validation below.
		$key   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $phrase ) : strtolower( $phrase );
		$parts = explode( ' ', $key );
		$last  = end( $parts );
		$variants = [ $key ];
		$parts[ count( $parts ) - 1 ] = $last . 's';
		$variants[]                   = implode( ' ', $parts );
		if ( 's' === substr( $last, -1 ) ) {
			$parts[ count( $parts ) - 1 ] = substr( $last, 0, -1 );
			$variants[]                   = implode( ' ', $parts );
		}
		$discovered = false;
		foreach ( $variants as $variant ) {
			if ( ! empty( $this->ngrams[ $variant ] ) ) {
				$discovered = true;
				break;
			}
		}
		if ( ! $discovered ) {
			return null;
		}

		// Stage 2 — validation: exact, word-boundary, case-insensitive, with an
		// optional plural on the final word only. Spaces tolerate any whitespace.
		$words   = explode( ' ', $phrase );
		$escaped = [];
		foreach ( $words as $i => $word ) {
			$part = preg_quote( $word, '/' );
			if ( $i === count( $words ) - 1 ) {
				$part .= 's?'; // allow a trailing plural on the last word only.
			}
			$escaped[] = $part;
		}
		$pattern = '/(?<![\p{L}\p{N}_])' . implode( '\s+', $escaped ) . '(?![\p{L}\p{N}_])/iu';

		if ( preg_match( $pattern, $this->text, $m, PREG_OFFSET_CAPTURE ) ) {
			$offset = (int) $m[0][1];
			return [
				'phrase' => $phrase,
				'offset' => $offset,
				'length' => strlen( $m[0][0] ),
			];
		}

		return null;
	}

	/**
	 * The sentence surrounding a match offset, trimmed for use as AI context.
	 *
	 * @param int $offset Byte offset into the original text.
	 * @param int $max_words Word cap for the returned context.
	 * @return string
	 */
	public function sentence( $offset, $max_words = 45 ) {
		$len = strlen( $this->text );
		if ( $offset < 0 || $offset >= $len ) {
			return '';
		}

		// Expand left/right to the nearest sentence boundary (. ! ? or newline).
		$start = 0;
		for ( $i = $offset; $i > 0; $i-- ) {
			$ch = $this->text[ $i - 1 ];
			if ( '.' === $ch || '!' === $ch || '?' === $ch || "\n" === $ch ) {
				$start = $i;
				break;
			}
		}
		$end = $len;
		for ( $i = $offset; $i < $len; $i++ ) {
			$ch = $this->text[ $i ];
			if ( '.' === $ch || '!' === $ch || '?' === $ch || "\n" === $ch ) {
				$end = $i + 1;
				break;
			}
		}

		$sentence = trim( substr( $this->text, $start, $end - $start ) );
		$sentence = preg_replace( '/\s+/', ' ', $sentence );

		return trim( wp_trim_words( $sentence, $max_words, '' ) );
	}
}
