<?php
/**
 * Shared FAQ detection signals for the AEO FAQ criteria.
 *
 * Detects, once per post, whether a page has visible FAQ content, FAQPage
 * schema, and how many Q&A pairs it carries — pulling from the plugin FAQ block,
 * a definition list, the metabox FAQ (_rtrs_faqpage_data) and the AI FAQ schema
 * (_aise_schema_data). Used by FaqPresenceCriterion and FaqCoverageCriterion so
 * the two independent criteria score from one consistent source.
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
 * Class FaqSignals
 */
class FaqSignals {

	/**
	 * Detect FAQ signals for a post.
	 *
	 * @param AnalysisContext $context Per-post content context.
	 * @return array{content:bool,schema:bool,pairs:int,questions:string[]} FAQ signal set.
	 */
	public static function detect( AnalysisContext $context ) {
		$html    = $context->content();
		$post_id = $context->post_id();

		// Visible FAQ content: the plugin FAQ block (also emits FAQPage schema) or
		// a real definition list (HTML only — carries no schema).
		$has_faq_block = AeoContent::has_faq_block( $html );
		$has_dl        = AeoContent::has_definition_list( $html );

		// FAQPage schema can come from five parallel sources — check them all so a
		// FAQ built any supported way is credited (the plugin FAQ block emits
		// schema on render; the others store it):
		//   metabox (_rtrs_faqpage_data), AI (_aise_schema_data),
		//   traditional schema tab (_rtrs_faq_schema), Elementor accordion.
		$meta_questions      = self::meta_faq_questions( $post_id );
		$ai_questions        = self::ai_faq_questions( $post_id );
		$trad_questions      = self::traditional_faq_questions( $post_id );
		$elementor_questions = self::elementor_faq_questions( $post_id );

		$meta_pairs      = count( $meta_questions );
		$ai_pairs        = count( $ai_questions );
		$trad_pairs      = count( $trad_questions );
		$elementor_pairs = count( $elementor_questions );

		// Plugin FAQ block question headings (class-scoped to
		// `rtrs-faq-question-text`, never arbitrary article headings).
		$faq_heading_questions = [];
		foreach ( $context->headings() as $heading ) {
			if ( ! empty( $heading['is_faq'] ) && ! empty( $heading['text'] ) ) {
				$faq_heading_questions[] = $heading['text'];
			}
		}

		// Manually-written FAQ pairs in the content — a question-shaped heading
		// followed by an answer paragraph, anywhere on the page. Counted like any
		// other FAQ, but carries NO FAQPage schema on its own.
		$content_questions = self::content_faq_questions( $html );
		$content_pairs     = count( $content_questions );

		// Schema-backed sources emit FAQPage structured data; a bare <dl> or a
		// manual content FAQ does not.
		$has_schema  = $has_faq_block || $meta_pairs > 0 || $ai_pairs > 0 || $trad_pairs > 0 || $elementor_pairs > 0;
		$has_content = $has_schema || $has_dl || $content_pairs > 0;

		// Coverage counts the largest single FAQ source (schema sources or the
		// manual content FAQ) — pairs are not summed across sources.
		$pairs = max(
			count( $faq_heading_questions ),
			$meta_pairs,
			$ai_pairs,
			$trad_pairs,
			$elementor_pairs,
			$content_pairs
		);

		// Surface the question texts from the richest text-bearing source so the
		// report can list exactly which Q&A pairs were counted.
		$questions = self::richest( [
			$faq_heading_questions,
			$meta_questions,
			$ai_questions,
			$trad_questions,
			$elementor_questions,
			$content_questions,
		] );

		return [
			'content'   => $has_content,
			'schema'    => $has_schema,
			'pairs'     => $pairs,
			'questions' => $questions,
		];
	}

	/**
	 * Manually-written FAQ pairs in the content: a question-shaped heading
	 * (h2-h6) immediately followed by an answer paragraph. Excludes the plugin's
	 * own FAQ-block headings (counted separately) so nothing is double-counted.
	 *
	 * @param string $html Rendered post HTML.
	 * @return string[] Question texts, in document order.
	 */
	private static function content_faq_questions( $html ) {
		if ( '' === (string) $html ) {
			return [];
		}

		$questions = [];
		if ( preg_match_all( '/<h([2-6])\b([^>]*)>(.*?)<\/h\1>\s*<p\b[^>]*>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				// Skip the plugin FAQ block's own question headings.
				if ( false !== stripos( $match[2], 'rtrs-faq-question-text' ) ) {
					continue;
				}

				$question = trim( wp_strip_all_tags( $match[3] ) );
				$answer   = trim( wp_strip_all_tags( $match[4] ) );

				if ( '' !== $question && '' !== $answer && self::is_question( $question ) ) {
					$questions[] = $question;
				}
			}
		}

		return $questions;
	}

	/**
	 * Whether a heading reads as a question (ends with '?' or opens with an
	 * interrogative word). Used to spot FAQ-worthy content that carries no schema.
	 *
	 * @param string $text Heading text.
	 * @return bool
	 */
	private static function is_question( $text ) {
		return (bool) preg_match(
			'/\?\s*$|^\s*(who|what|when|where|why|how|which|can|could|does|do|is|are|should|will)\b/i',
			(string) $text
		);
	}

	/**
	 * Return the longest of several question lists (the source that found the
	 * most Q&A pairs), sanitized and capped for display.
	 *
	 * @param array[] $lists Candidate question-string lists.
	 * @return string[]
	 */
	private static function richest( array $lists ) {
		$best = [];
		foreach ( $lists as $list ) {
			if ( count( $list ) > count( $best ) ) {
				$best = $list;
			}
		}

		// No cap — the listed questions must always match the "N found" count so
		// every counted Q&A pair is shown.
		$clean = [];
		foreach ( $best as $question ) {
			$question = trim( wp_strip_all_tags( (string) $question ) );
			if ( '' !== $question ) {
				$clean[] = $question;
			}
		}

		return $clean;
	}

	/**
	 * Question texts saved in the metabox / AI FAQ meta.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function meta_faq_questions( $post_id ) {
		$data = $post_id ? get_post_meta( $post_id, '_rtrs_faqpage_data', true ) : '';
		if ( ! is_array( $data ) ) {
			return [];
		}

		$questions = [];
		foreach ( $data as $item ) {
			if ( is_array( $item ) && ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
				$questions[] = $item['question'];
			}
		}

		return $questions;
	}

	/**
	 * Question texts in the saved AI FAQPage schema (_aise_schema_data).
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function ai_faq_questions( $post_id ) {
		if ( ! $post_id ) {
			return [];
		}

		$data      = \Rtrs\AI\AIInit::normalizeSchemaData( get_post_meta( $post_id, \Rtrs\AI\AIInit::META_KEY, true ) );
		$questions = [];
		foreach ( (array) $data as $node ) {
			if ( ! is_array( $node ) || 'FAQPage' !== ( isset( $node['@type'] ) ? $node['@type'] : '' ) || empty( $node['mainEntity'] ) ) {
				continue;
			}
			$entities = isset( $node['mainEntity'][0] ) ? $node['mainEntity'] : [ $node['mainEntity'] ];
			foreach ( $entities as $entity ) {
				if ( is_array( $entity ) && ! empty( $entity['name'] ) ) {
					$questions[] = $entity['name'];
				}
			}
		}

		return $questions;
	}

	/**
	 * Question texts from the traditional schema tab.
	 *
	 * The FAQ rich-snippet type stores its pairs in `_rtrs_faq_schema` (keys
	 * `ques` / `ans`), gated by `_rtrs_rich_snippet_cat` including `faq`.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function traditional_faq_questions( $post_id ) {
		if ( ! $post_id ) {
			return [];
		}

		$cats = get_post_meta( $post_id, '_rtrs_rich_snippet_cat', false );
		if ( ! in_array( 'faq', (array) $cats, true ) ) {
			return [];
		}

		$data = get_post_meta( $post_id, '_rtrs_faq_schema', true );
		if ( ! is_array( $data ) || empty( $data['faqs'] ) || ! is_array( $data['faqs'] ) ) {
			return [];
		}

		$questions = [];
		foreach ( $data['faqs'] as $item ) {
			if ( is_array( $item ) && ! empty( $item['ques'] ) && ! empty( $item['ans'] ) ) {
				$questions[] = $item['ques'];
			}
		}

		return $questions;
	}

	/**
	 * Question texts from an Elementor accordion FAQ with the schema toggle on.
	 *
	 * Parses `_elementor_data` for the real question titles so coverage reflects
	 * the actual number of Q&A pairs, not a nominal estimate. Only accordions
	 * with the plugin's "Enable FAQ Schema" toggle are counted — the same ones
	 * that emit FAQPage structured data.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function elementor_faq_questions( $post_id ) {
		if ( ! $post_id || ! class_exists( '\Rtrs\Modules\Schema\Hooks\ElementorFaq' ) ) {
			return [];
		}

		return \Rtrs\Modules\Schema\Hooks\ElementorFaq::get_faq_questions( $post_id );
	}
}
