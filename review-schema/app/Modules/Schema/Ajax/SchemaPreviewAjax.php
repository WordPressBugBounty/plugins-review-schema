<?php
/**
 * Schema Preview AJAX handler (free plugin).
 *
 * Provides the AJAX endpoint used by the Schema Preview tab / modal so the
 * preview is available even without the pro plugin active. Mirrors the pro
 * implementation; when both plugins are active WordPress runs this handler
 * first (earlier registration) and the pro duplicate becomes a no-op via
 * wp_die() inside wp_send_json_*.
 *
 * @package Rtrs\Modules\Schema\Ajax
 * @since   1.0.0
 */

namespace Rtrs\Modules\Schema\Ajax;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Models\Schema;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaPreviewAjax
 *
 * Handles the `rtrs_get_schema_preview_json` AJAX action used by the schema
 * preview modal in the post edit screen.
 */
class SchemaPreviewAjax {

	use SingletonTrait;

	/**
	 * Register AJAX actions.
	 */
	private function __construct() {
		add_action( 'wp_ajax_rtrs_get_schema_preview_json', [ $this, 'get_schema_for_preview' ] );
	}

	/**
	 * Generate the JSON-LD schema for a given post and return it as JSON
	 * (along with a hidden form used by "Test on Google Rich Results").
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function get_schema_for_preview() {
		// Nonce check — reuse the shared admin nonce id exposed by the plugin.
		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid nonce.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'review-schema' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid post ID.', 'review-schema' ) ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You cannot preview schema for this post.', 'review-schema' ) ] );
		}

		$post_obj = get_post( $post_id );
		if ( ! $post_obj ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Post not found.', 'review-schema' ) ] );
		}

		/**
		 * Setup global post context so schema generators reading the global
		 * $post have the correct reference.
		 */
		global $post;
		$post = $post_obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		$schema       = new Schema( $post_id );
		$rich_snippet = $schema->header_schema_data();
		$rich_snippet = $schema->remove_script_wrappers( $rich_snippet );

		wp_reset_postdata();

		if ( empty( $rich_snippet ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No schema data generated.', 'review-schema' ) ] );
		}

		ob_start();
		?>
		<form
			id="rtrs-snipet-form"
			method="post"
			target="_blank"
			action="https://search.google.com/test/rich-results"
		>
			<textarea name="code_snippet"><?php echo esc_textarea( $rich_snippet ); ?></textarea>
		</form>
		<?php
		$form_html = ob_get_clean();

		wp_send_json_success(
			[
				'html'     => esc_html( $rich_snippet ),
				'rawJson'  => $rich_snippet,
				'formHtml' => $form_html,
			]
		);
	}
}
