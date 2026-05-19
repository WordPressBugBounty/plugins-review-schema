<?php
/**
 * FAQPage Frontend Shortcode.
 *
 * Registers [rtrs_faqpage] shortcode to render FAQ accordion HTML
 * from dedicated meta or AI schema data.
 *
 * @package Rtrs\Modules\Schema\Hooks
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\AI\AIInit;
use Rtrs\Modules\Schema\Admin\Meta\FaqPageMeta;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqPageFrontend {

	use SingletonTrait;

	/**
	 * Initialize hooks.
	 */
	private function __instance() {
		add_shortcode( 'rtrs_faqpage', [ $this, 'shortcode_output' ] );
		add_filter( 'woocommerce_product_tabs', [ $this, 'add_faq_product_tab' ] );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_output( $atts ) {
		$atts = shortcode_atts( [
			'post_id' => get_the_ID(),
		], $atts, 'rtrs_faqpage' );

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			return '';
		}

		$faq_data = $this->get_faq_data( $post_id );

		if ( empty( $faq_data ) ) {
			return '';
		}

		return $this->build_faq_html( $faq_data );
	}

	/**
	 * Get FAQ data from dedicated meta or AI schema fallback.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_faq_data( $post_id ) {
		// 1. Check dedicated FAQ meta first.
		$faq_meta = get_post_meta( $post_id, FaqPageMeta::META_KEY, true );
		if ( ! empty( $faq_meta ) && is_array( $faq_meta ) ) {
			$faqs = [];
			foreach ( $faq_meta as $item ) {
				if ( ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
					$faqs[] = [
						'question' => $item['question'],
						'answer'   => $item['answer'],
					];
				}
			}
			if ( ! empty( $faqs ) ) {
				return $faqs;
			}
		}

		// 2. Fallback: extract from AI schema data.
		if ( ! class_exists( AIInit::class ) ) {
			return [];
		}

		$raw_data = get_post_meta( $post_id, AIInit::META_KEY, true );
		if ( empty( $raw_data ) ) {
			return [];
		}

		$ai_data = AIInit::normalizeSchemaData( $raw_data );
		if ( empty( $ai_data ) || ! is_array( $ai_data ) ) {
			return [];
		}

		foreach ( $ai_data as $schema ) {
			if ( ( $schema['@type'] ?? '' ) !== 'FAQPage' || empty( $schema['mainEntity'] ) ) {
				continue;
			}

			$entities = isset( $schema['mainEntity']['@type'] )
				? [ $schema['mainEntity'] ]
				: $schema['mainEntity'];

			$faqs = [];
			foreach ( $entities as $entity ) {
				if ( ( $entity['@type'] ?? '' ) === 'Question'
					&& ! empty( $entity['name'] )
					&& ! empty( $entity['acceptedAnswer']['text'] )
				) {
					$faqs[] = [
						'question' => $entity['name'],
						'answer'   => $entity['acceptedAnswer']['text'],
					];
				}
			}

			if ( ! empty( $faqs ) ) {
				return $faqs;
			}
		}

		return [];
	}

	/**
	 * Add FAQ tab to WooCommerce product tabs.
	 *
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public function add_faq_product_tab( $tabs ) {
		$post_id = get_the_ID();
		$enabled = get_post_meta( $post_id, FaqPageMeta::TAB_META_KEY, true );

		if ( empty( $enabled ) ) {
			return $tabs;
		}

		$faq_data = $this->get_faq_data( $post_id );

		if ( empty( $faq_data ) ) {
			return $tabs;
		}

		$tabs['rtrs_faq'] = [
			'title'    => esc_html__( 'FAQ', 'review-schema' ),
			'priority' => 25,
			'callback' => [ $this, 'render_faq_product_tab' ],
		];

		return $tabs;
	}

	/**
	 * Render the FAQ product tab content.
	 */
	public function render_faq_product_tab() {
		echo $this->shortcode_output( [ 'post_id' => get_the_ID() ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build FAQ accordion HTML.
	 *
	 * @param array $faqs Array of ['question' => ..., 'answer' => ...].
	 * @return string
	 */
	private function build_faq_html( $faqs ) {
		ob_start();
		include RTRS_PATH . '/views/metas/single/faqpage-frontend.php';
		return ob_get_clean();
	}
}
