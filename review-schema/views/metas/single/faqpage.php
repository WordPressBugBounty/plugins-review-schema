<?php

use Rtrs\Modules\Schema\Admin\Meta\FaqPageMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_helper       = new Rtrs\Helpers\Functions();
$rtrs_meta_options = FaqPageMeta::getInstance();
?>
<div class="rtrs-faqpage-shortcode-hint" style="font-size:13px;">
	<div class="rtrs-field-wrapper  " id="_rtrs_faqpage_data_holder">
		<div class="rtrs-label">
			<label for=""> ShortCode </label>
		</div>
		<div class="rtrs-field ">
			<?php
			printf(
			/* translators: %s: shortcode */
				esc_html__( 'Use the shortcode %s to display FAQ data on the frontend.', 'review-schema' ),
				'<code style="background:#e7e7e7;padding:2px 6px;border-radius:3px;">[rtrs_faqpage]</code>'
			);
			?>
		 </div>
	</div>
</div>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fieldGenerator() returns plugin-built HTML form fields with internal esc_attr/esc_html on dynamic values.
echo $rtrs_helper->fieldGenerator( $rtrs_meta_options->faqPageFields(), true );
