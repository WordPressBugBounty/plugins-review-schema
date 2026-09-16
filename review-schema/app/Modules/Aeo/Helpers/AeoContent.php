<?php
/**
 * Content parsing helpers shared by the AEO criteria.
 *
 * Pulls answer-oriented signals (sections, paragraphs, sentences, questions,
 * lists/tables) out of rendered post HTML. Kept stateless so every criterion
 * can reuse the same parsing without side effects.
 *
 * @package Rtrs\Modules\Aeo\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Aeo\Helpers;

use Rtrs\Modules\Seo\Analysis\AnalysisContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AeoContent
 */
class AeoContent {

	/**
	 * Sentence boundary: terminal punctuation, optionally followed by a closing
	 * quote or bracket (e.g. a sentence that ends inside quotes — `…effect."`),
	 * then whitespace. Without the quote/bracket allowance the splitter never
	 * breaks after `."` / `?"` and merges every following sentence into one
	 * giant "sentence" (which then trips the too-long-sentence check).
	 */
	const SENTENCE_BOUNDARY = '/(?<=[.!?]|[.!?]["\')\]\x{201D}\x{2019}])\s+/u';

	/**
	 * Split content into heading-led sections.
	 *
	 * Each section pairs an H2-H6 heading with the paragraph that immediately
	 * follows it (the candidate "direct answer").
	 *
	 * @param string $html Rendered post HTML.
	 * @return array[] List of [ 'heading', 'level', 'first_para', 'first_para_words', 'is_faq' ].
	 */
	public static function sections( $html ) {
		$sections = [];

		if ( ! preg_match_all( '/<h([2-6])\b[^>]*>(.*?)<\/h\1>/is', (string) $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $sections;
		}

		$count  = count( $matches );
		$length = strlen( $html );

		for ( $i = 0; $i < $count; $i++ ) {
			$heading_tag = $matches[ $i ][0][0];
			$start       = $matches[ $i ][0][1] + strlen( $heading_tag );
			$end         = ( $i + 1 < $count ) ? $matches[ $i + 1 ][0][1] : $length;
			$body_html   = substr( $html, $start, $end - $start );
			$first_para  = self::first_paragraph( $body_html );

			$sections[] = [
				'heading'          => self::clean_text( $matches[ $i ][2][0] ),
				'level'            => (int) $matches[ $i ][1][0],
				'first_para'       => $first_para,
				'first_para_words' => '' !== $first_para ? str_word_count( $first_para ) : 0,
				'body'             => self::clean_text( $body_html ),
				'is_faq'           => self::is_faq_heading( $heading_tag ),
			];
		}

		return $sections;
	}

	/**
	 * Strip tags, decode HTML entities, and normalise whitespace.
	 *
	 * wp_strip_all_tags() removes tags but leaves entities like &nbsp; / &amp;
	 * intact, which then surface literally in labels (e.g. "3.&nbsp;Cooked") and
	 * skew word counts. Decoding + collapsing whitespace yields clean text.
	 *
	 * @param string $html Raw HTML fragment.
	 * @return string
	 */
	private static function clean_text( $html ) {
		$text = wp_strip_all_tags( AnalysisContext::strip_css( (string) $html ) );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text ); // U+00A0 non-breaking space.
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Whether a heading belongs to a generated FAQ block.
	 *
	 * The plugin's FAQ blocks render the question as
	 * <h3 class="rtrs-faq-question-text">. FAQ answers are intentionally concise
	 * Q&A and carry FAQPage schema, so they are scored under a gentler rule.
	 *
	 * @param string $heading_tag Full heading element (open tag, text, close tag).
	 * @return bool
	 */
	private static function is_faq_heading( $heading_tag ) {
		return false !== stripos( (string) $heading_tag, 'rtrs-faq-question-text' );
	}

	/**
	 * Extract the first answer paragraph from a section body.
	 *
	 * Prefers a real <p>; falls back to the first sentence of the stripped body.
	 *
	 * @param string $body_html Section HTML (between two headings).
	 * @return string
	 */
	private static function first_paragraph( $body_html ) {
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', (string) $body_html, $match ) ) {
			return self::clean_text( $match[1] );
		}

		$text = self::clean_text( (string) $body_html );
		if ( '' === $text ) {
			return '';
		}

		$parts = preg_split( self::SENTENCE_BOUNDARY, $text, 2 );
		return isset( $parts[0] ) ? trim( $parts[0] ) : $text;
	}

	/**
	 * All body paragraphs as plain text.
	 *
	 * @param string $html Rendered post HTML.
	 * @return string[]
	 */
	public static function paragraphs( $html ) {
		$paragraphs = [];

		if ( preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', (string) $html, $matches ) ) {
			foreach ( $matches[1] as $paragraph ) {
				$text = self::clean_text( $paragraph );
				if ( '' !== $text ) {
					$paragraphs[] = $text;
				}
			}
		}

		return $paragraphs;
	}

	/**
	 * Split plain text into sentences.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	public static function sentences( $text ) {
		$text = self::clean_text( (string) $text );
		if ( '' === $text ) {
			return [];
		}

		$parts = preg_split( self::SENTENCE_BOUNDARY, $text );

		return array_values(
			array_filter(
				array_map( 'trim', (array) $parts ),
				static function ( $sentence ) {
					return '' !== $sentence;
				}
			)
		);
	}

	/**
	 * Whether a string reads as a question.
	 *
	 * True when it ends with "?" or opens with a common interrogative word.
	 *
	 * @param string $text Heading or sentence.
	 * @return bool
	 */
	public static function is_question( $text ) {
		$text = self::clean_text( (string) $text );
		if ( '' === $text ) {
			return false;
		}

		if ( '?' === substr( $text, -1 ) ) {
			return true;
		}

		$first   = strtolower( (string) strtok( $text, " \t\n" ) );
		$openers = [ 'who', 'what', 'when', 'where', 'why', 'how', 'which', 'can', 'does', 'do', 'is', 'are', 'should', 'will', 'could', 'would', 'has', 'have' ];

		return in_array( $first, $openers, true );
	}

	/**
	 * Whether the content contains a bullet or numbered list.
	 *
	 * @param string $html Rendered post HTML.
	 * @return bool
	 */
	public static function has_list( $html ) {
		return (bool) preg_match( '/<(ul|ol)\b/i', (string) $html );
	}

	/**
	 * Whether the content contains a table.
	 *
	 * @param string $html Rendered post HTML.
	 * @return bool
	 */
	public static function has_table( $html ) {
		return (bool) preg_match( '/<table\b/i', (string) $html );
	}

	/**
	 * Whether the content contains a numbered (ordered) list.
	 *
	 * Ordered lists signal step-by-step "how to" answers, which answer engines
	 * lift as list snippets.
	 *
	 * @param string $html Rendered post HTML.
	 * @return bool
	 */
	public static function has_ordered_list( $html ) {
		return (bool) preg_match( '/<ol\b/i', (string) $html );
	}

	/**
	 * Whether the content contains a definition list (<dl>).
	 *
	 * Definition lists are a valid, machine-readable Q&A structure.
	 *
	 * @param string $html Rendered post HTML.
	 * @return bool
	 */
	public static function has_definition_list( $html ) {
		// Require a real term + description pair — a bare or styling-only <dl>
		// (no <dt>/<dd>) is not a machine-readable Q&A structure.
		return (bool) preg_match( '/<dl\b[^>]*>.*?<dt\b.*?<dd\b/is', (string) $html );
	}

	/**
	 * Whether the content contains a generated FAQ block.
	 *
	 * The plugin's FAQ blocks render each question with the
	 * `rtrs-faq-question-text` class and emit FAQPage schema, so their presence
	 * is a strong structured-Q&A signal.
	 *
	 * @param string $html Rendered post HTML.
	 * @return bool
	 */
	public static function has_faq_block( $html ) {
		// Require the class on an actual FAQ question *heading* element — not the
		// bare class string, which can appear in inline CSS, escaped text, or an
		// empty/removed block and would otherwise falsely report a FAQ.
		return (bool) preg_match( '/<h[1-6]\b[^>]*\brtrs-faq-question-text\b/i', (string) $html );
	}

	/**
	 * Whether an answer opening is self-contained (not pronoun-led).
	 *
	 * Answer engines quote sentences out of context, so a lead that opens with a
	 * bare pronoun ("It", "This", "They") loses its subject when lifted. Returns
	 * false only when the first word is such a pronoun.
	 *
	 * @param string $text Answer/lead text.
	 * @return bool
	 */
	public static function is_self_contained( $text ) {
		$text = self::clean_text( (string) $text );
		if ( '' === $text ) {
			return true;
		}

		$first = strtolower( (string) strtok( $text, " \t\n" ) );
		$first = preg_replace( '/[^a-z]/', '', (string) $first );

		$pronouns = [ 'it', 'this', 'that', 'they', 'these', 'those', 'he', 'she', 'there', 'them', 'its', 'their' ];

		return '' !== $first && ! in_array( $first, $pronouns, true );
	}

	/**
	 * Whether a sentence reads as passive voice.
	 *
	 * Heuristic: a "to be" verb followed (optionally past an adverb) by a word
	 * ending in "-ed" or "-en" (e.g. "was written", "is carefully reviewed").
	 * Passive answers sound clunky when read aloud by voice assistants.
	 *
	 * @param string $sentence Sentence text.
	 * @return bool
	 */
	public static function is_passive( $sentence ) {
		$sentence = self::clean_text( (string) $sentence );

		return (bool) preg_match( '/\b(?:is|are|was|were|be|been|being)\b\s+(?:\w+ly\s+)?\w+(?:ed|en)\b/i', $sentence );
	}
}
