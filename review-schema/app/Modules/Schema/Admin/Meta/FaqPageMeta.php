<?php

namespace Rtrs\Modules\Schema\Admin\Meta;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqPageMeta {

	use SingletonTrait;

	const META_KEY     = '_rtrs_faqpage_data';
	const TAB_META_KEY = '_rtrs_faq_product_tab';

	/**
	 * @return array
	 */
	public function faqPageFields() {
		$fields = [];

		if ( class_exists( 'WooCommerce' ) && get_post_type() === 'product' ) {
			$fields[] = [
				'name'  => self::TAB_META_KEY,
				'type'  => 'switch',
				'label' => esc_html__( 'Show FAQ in WooCommerce Product Tab', 'review-schema' ),
				'desc'  => esc_html__( 'Display FAQ as a tab on WooCommerce product pages (before the Reviews tab).', 'review-schema' ),
			];
		}

		$fields[] = [
			'type'   => 'group',
			'name'   => self::META_KEY,
			'id'     => 'rtrs-faqpage_data',
			'label'  => esc_html__( 'Questions & Answers', 'review-schema' ),
			'fields' => [
				[
					'name'     => 'question',
					'type'     => 'text',
					'label'    => esc_html__( 'Question', 'review-schema' ),
					'required' => true,
				],
				[
					'name'     => 'answer',
					'type'     => 'textarea',
					'label'    => esc_html__( 'Answer', 'review-schema' ),
					'required' => true,
				],
			],
		];

		return $fields;
	}
}
