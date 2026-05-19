<?php
/**
 * Gutenberg FAQ Schema Support.
 *
 * Adds FAQPage structured data for the Gutenberg FAQ block (rtrs/faq)
 * into the main schema graph with proper @id linking.
 *
 * @package review-schema
 * @since   1.2.0
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Helpers\SchemaFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenbergFaq {

	use SingletonTrait;

	/**
	 * Store the FAQ @id when FAQ schema is generated.
	 *
	 * @var string|null
	 */
	private $faq_schema_id = null;

	/**
	 * Initialize hooks.
	 */
	private function __instance() {
		// Traditional schema flow.
		add_filter( 'rtrs_schema_graph_data', [ $this, 'add_faq_schema' ], 15, 2 );
		add_filter( 'rtrs_schema_graph_data', [ $this, 'link_faq_to_webpage' ], 20 );

		// AI-generated schema flow.
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'add_faq_schema' ], 15, 2 );
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'link_faq_to_webpage' ], 20 );
	}

	/**
	 * Add FAQPage schema to the graph data.
	 *
	 * @param array $schema_graph_list Existing schema graph list.
	 * @param int   $post_id           Current post ID.
	 * @return array Modified schema graph list.
	 */
	public function add_faq_schema( $schema_graph_list, $post_id ) {
		if ( ! $post_id ) {
			return $schema_graph_list;
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return $schema_graph_list;
		}

		$blocks = parse_blocks( $post->post_content );
		$faqs   = $this->extract_faqs_from_blocks( $blocks );

		if ( empty( $faqs ) ) {
			return $schema_graph_list;
		}

		$main_entity = [];
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$main_entity[] = [
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $faq['answer'],
				],
			];
		}

		if ( empty( $main_entity ) ) {
			return $schema_graph_list;
		}

		$permalink           = get_permalink( $post_id );
		$faq_id              = trailingslashit( $permalink ) . '#faq';
		$this->faq_schema_id = $faq_id;

		$faq_node = [
			'@type'      => 'FAQPage',
			'@id'        => $faq_id,
			'url'        => $permalink,
			'mainEntity' => $main_entity,
		];

		// Replace any existing FAQPage node (block data takes precedence).
		$replaced = false;

		foreach ( $schema_graph_list as $idx => $schema ) {
			if ( 'FAQPage' === ( $schema['@type'] ?? '' ) ) {
				$schema_graph_list[ $idx ] = $faq_node;
				$replaced                  = true;
				break;
			}
		}

		if ( ! $replaced ) {
			$schema_graph_list[] = $faq_node;
		}

		return $schema_graph_list;
	}

	/**
	 * Link the FAQPage to the main content schema and WebPage.
	 *
	 * @param array $schema_graph_list The schema graph list.
	 * @return array Modified schema graph list.
	 */
	public function link_faq_to_webpage( $schema_graph_list ) {
		return SchemaFns::link_faq_to_graph( $schema_graph_list, $this->faq_schema_id );
	}

	/**
	 * Recursively extract FAQ pairs from parsed blocks.
	 *
	 * Supports both rtrs/faq block and yoast/faq-block.
	 *
	 * @param array $blocks Parsed block array.
	 * @return array Array of ['question' => ..., 'answer' => ...].
	 */
	private function extract_faqs_from_blocks( $blocks ) {
		$faqs = [];

		foreach ( $blocks as $block ) {
			if ( 'rtrs/faq' === $block['blockName'] ) {
				$enable_schema = $block['attrs']['enableSchema'] ?? true;

				if ( ! $enable_schema ) {
					continue;
				}

				$inner_blocks = $block['innerBlocks'] ?? [];
				foreach ( $inner_blocks as $item_block ) {
					if ( 'rtrs/faq-item' !== $item_block['blockName'] ) {
						continue;
					}

					$question    = '';
					$answer_text = '';

					// Extract question from the saved HTML (.rtrs-faq-question-text element).
					$html = $item_block['innerHTML'] ?? '';
					if ( preg_match( '/class="[^"]*rtrs-faq-question-text[^"]*"[^>]*>(.+?)<\/h[1-6]>/s', $html, $q_match ) ) {
						$question = wp_strip_all_tags( $q_match[1] );
					}

					// Answer content comes from inner blocks.
					foreach ( $item_block['innerBlocks'] as $inner_block ) {
						$rendered     = render_block( $inner_block );
						$answer_text .= wp_strip_all_tags( $rendered ) . ' ';
					}

					$question    = trim( $question );
					$answer_text = trim( $answer_text );

					if ( ! empty( $question ) && ! empty( $answer_text ) ) {
						$faqs[] = [
							'question' => $question,
							'answer'   => $answer_text,
						];
					}
				}
			} elseif ( 'yoast/faq-block' === $block['blockName'] ) {
				$questions = $block['attrs']['questions'] ?? [];
				foreach ( $questions as $yoast_question ) {
					$question = ! empty( $yoast_question['jsonQuestion'] ) ? wp_strip_all_tags( $yoast_question['jsonQuestion'] ) : '';
					$answer   = ! empty( $yoast_question['jsonAnswer'] ) ? wp_strip_all_tags( $yoast_question['jsonAnswer'] ) : '';

					$question = trim( $question );
					$answer   = trim( $answer );

					if ( ! empty( $question ) && ! empty( $answer ) ) {
						$faqs[] = [
							'question' => $question,
							'answer'   => $answer,
						];
					}
				}
			} elseif ( 'rank-math/faq-block' === $block['blockName'] ) {
				$questions = $block['attrs']['questions'] ?? [];
				foreach ( $questions as $rm_question ) {
					if ( isset( $rm_question['visible'] ) && ! $rm_question['visible'] ) {
						continue;
					}

					$question = ! empty( $rm_question['title'] ) ? wp_strip_all_tags( $rm_question['title'] ) : '';
					$answer   = ! empty( $rm_question['content'] ) ? wp_strip_all_tags( $rm_question['content'] ) : '';

					$question = trim( $question );
					$answer   = trim( $answer );

					if ( ! empty( $question ) && ! empty( $answer ) ) {
						$faqs[] = [
							'question' => $question,
							'answer'   => $answer,
						];
					}
				}
			}

			// Recurse into inner blocks (e.g. FAQ inside columns/groups).
			if ( ! empty( $block['innerBlocks'] ) && ! in_array( $block['blockName'], [ 'rtrs/faq', 'yoast/faq-block', 'rank-math/faq-block' ], true ) ) {
				$faqs = array_merge( $faqs, $this->extract_faqs_from_blocks( $block['innerBlocks'] ) );
			}
		}

		return $faqs;
	}
}
