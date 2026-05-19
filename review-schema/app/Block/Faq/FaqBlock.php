<?php
/**
 * FAQ Gutenberg Block
 *
 * Registers and renders the FAQ accordion block with FAQPage schema markup.
 * Delegates rendering, attributes, CSS, and schema to dedicated classes.
 *
 * @package Rtrs\Block\Faq
 */

namespace Rtrs\Block\Faq;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FaqBlock {

	use SingletonTrait;

	private $version;

	private function __construct() {
		$this->version = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RTRS_VERSION;
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block() {
		wp_register_script(
			'rtrs-faq-block-editor',
			rtrs()->get_assets_uri( 'js/blocks/faq/index.js' ),
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ],
			$this->version,
			true
		);

		wp_localize_script( 'rtrs-faq-block-editor', 'rtrsFaqBlock', [
			'plugin_url' => trailingslashit( RTRS_URL ),
		] );

		wp_register_script(
			'rtrs-faq-block-view',
			rtrs()->get_assets_uri( 'js/blocks/faq/view.js' ),
			[],
			$this->version,
			true
		);

		wp_register_style(
			'rtrs-faq-block-style',
			rtrs()->get_assets_uri( 'css/app.css' ),
			[],
			$this->version
		);

		// Parent block.
		register_block_type( 'rtrs/faq', [
			'editor_script'    => 'rtrs-faq-block-editor',
			'script'           => 'rtrs-faq-block-view',
			'style'            => 'rtrs-faq-block-style',
			'render_callback'  => [ FaqRenderer::class, 'render' ],
			'attributes'       => FaqAttributes::get(),
			'provides_context' => [
				'rtrs/layout'       => 'layout',
				'rtrs/iconPosition' => 'iconPosition',
				'rtrs/expandIcon'   => 'expandIcon',
				'rtrs/collapseIcon' => 'collapseIcon',
				'rtrs/iconSize'     => 'iconSize',
				'rtrs/iconSpacing'  => 'iconSpacing',
				'rtrs/iconBorder'   => 'iconBorder',
				'rtrs/iconNormal'   => 'iconNormal',
				// Legacy compat
				'rtrs/iconStyle'    => 'iconStyle',
			],
		] );

		// Child block (static — save output used directly).
		register_block_type( 'rtrs/faq-item', [
			'parent' => [ 'rtrs/faq' ],
		] );
	}
}
