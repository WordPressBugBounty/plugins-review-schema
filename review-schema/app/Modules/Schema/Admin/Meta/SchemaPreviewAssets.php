<?php
/**
 * Schema Preview Assets
 *
 * Enqueues the classic editor panel JS/CSS on post edit screens so that the
 * schema preview modal (`window.rtrsOpenSchemaModal`) is always available
 * when schema is enabled, regardless of whether the AI feature is turned on.
 *
 * Uses classic-editor-panel.js for both editors because it registers the
 * modal function at the top level with vanilla JS (no React dependency).
 * The panel UI only renders into `#aise-classic-panel` which doesn't exist
 * when AIInit is not active, so no AI panel is shown.
 *
 * When AIInit also loads (AI enabled), it enqueues the same handle with full
 * `aiseData`, so WordPress uses the richer version.
 *
 * @package Rtrs\Modules\Schema\Admin\Meta
 */

namespace Rtrs\Modules\Schema\Admin\Meta;

use Rtrs\AI\AIInit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaPreviewAssets
 */
class SchemaPreviewAssets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueuePreviewAssets' ], 5 );
	}

	/**
	 * Enqueue preview modal assets for any editor.
	 *
	 * Skips when AI is enabled because AIInit handles all enqueuing.
	 *
	 * @return void
	 */
	public function enqueuePreviewAssets() {
		if ( 'yes' === AIInit::getSetting( 'ai_enabled', 'no' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( ! in_array( $screen->post_type, AIInit::getSupportedPostTypes(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			RTRS_VERSION
		);

		wp_enqueue_style(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/classic-editor-panel.css' ),
			[ 'rtrs-ai-editor-panel' ],
			RTRS_VERSION
		);

		wp_enqueue_script(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/classic-editor-panel.js' ),
			[ 'wp-api-fetch', 'wp-hooks' ],
			RTRS_VERSION,
			true
		);

		wp_localize_script(
			'rtrs-ai-classic-editor-panel',
			'aiseData',
			$this->buildMinimalData()
		);
	}

	/**
	 * Build minimal aiseData for the preview modal only.
	 *
	 * @return array
	 */
	private function buildMinimalData() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin asset enqueue check; reads page/action only.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) :
				   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Admin asset enqueue check; reads page/action only.
				   ( isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : get_the_ID() );

		return [
			'restUrl'        => rest_url( 'rtrs-ai/v1/' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'postId'         => $post_id,
			'schemaData'     => [],
			'fullGraph'      => [],
			'schemaType'     => '',
			'confidence'     => '',
			'hasApiKey'      => false,
			'logoUrl'        => rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ),
			'validation'     => null,
			'evaluation'     => null,
			'schemaTypes'    => [],
			'proSchemaTypes' => [],
			'isPro'          => function_exists( 'rtrsp' ),
			'aiEnabled'      => false,
			'settingsUrl'    => admin_url( 'admin.php?page=review-schema&tab=ai' ),
			'i18n'           => AIInit::getI18nStrings(),
		];
	}
}
