<?php
/**
 * FAQPage Schema from dedicated meta.
 *
 * Generates FAQPage JSON-LD schema from the _rtrs_faqpage_data post meta
 * and hooks into both traditional and AI schema pipelines.
 *
 * @package Rtrs\Modules\Schema\Hooks
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Admin\Meta\FaqPageMeta;
use Rtrs\Modules\Schema\Helpers\SchemaFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqPageSchema {

	use SingletonTrait;

	/**
	 * @var string|null
	 */
	private $faq_schema_id = null;

	/**
	 * Initialize hooks.
	 */
	private function __instance() {
		add_filter( 'rtrs_schema_graph_data', [ $this, 'add_faqpage_schema' ], 12, 2 );
		add_filter( 'rtrs_schema_graph_data', [ $this, 'link_faq_to_webpage' ], 22 );

		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'add_faqpage_schema' ], 12, 2 );
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'link_faq_to_webpage' ], 22 );
	}

	/**
	 * Add FAQPage schema from dedicated meta to the graph.
	 *
	 * @param array $schema_graph_list Existing schema graph.
	 * @param int   $post_id           Current post ID.
	 * @return array
	 */
	public function add_faqpage_schema( $schema_graph_list, $post_id ) {
		if ( ! $post_id ) {
			return $schema_graph_list;
		}

		// Skip if post uses Gutenberg FAQ blocks — GutenbergFaq handles schema for those.
		$post = get_post( $post_id );
		if ( $post && has_blocks( $post->post_content ) && ( has_block( 'rtrs/faq', $post->post_content ) || has_block( 'yoast/faq-block', $post->post_content ) || has_block( 'rank-math/faq-block', $post->post_content ) ) ) {
			return $schema_graph_list;
		}

		$faq_meta = get_post_meta( $post_id, FaqPageMeta::META_KEY, true );
		if ( empty( $faq_meta ) || ! is_array( $faq_meta ) ) {
			return $schema_graph_list;
		}

		// Skip if FAQPage already exists in graph (from Gutenberg block, AI, or Schema tab).
		foreach ( $schema_graph_list as $schema ) {
			if ( ( $schema['@type'] ?? '' ) === 'FAQPage' ) {
				return $schema_graph_list;
			}
		}

		$main_entity = [];
		foreach ( $faq_meta as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}
			$main_entity[] = [
				'@type'          => 'Question',
				'name'           => sanitize_text_field( $item['question'] ),
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => sanitize_textarea_field( $item['answer'] ),
				],
			];
		}

		if ( empty( $main_entity ) ) {
			return $schema_graph_list;
		}

		$permalink           = get_permalink( $post_id );
		$faq_id              = trailingslashit( $permalink ) . '#faqpage';
		$this->faq_schema_id = $faq_id;

		$schema_graph_list[] = [
			'@type'      => 'FAQPage',
			'@id'        => $faq_id,
			'url'        => $permalink,
			'mainEntity' => $main_entity,
		];

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
}
