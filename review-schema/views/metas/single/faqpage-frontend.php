<?php
/**
 * Frontend FAQ list template.
 *
 * @var array $faqs Array of ['question' => ..., 'answer' => ...].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $faqs ) ) {
	return;
}
?>
<style>
.rtrs-faqpage-section{margin:2em 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif}
.rtrs-faqpage-section .rtrs-faqpage-title{margin:0 0 20px;font-size:1.3em;font-weight:700;color:#1a1a1a}
.rtrs-faqpage-section .rtrs-faqpage-list{list-style:none;margin:0;padding:0;display:grid;gap:16px}
.rtrs-faqpage-section .rtrs-faqpage-item{padding:18px 20px;background:#f8f9fa;border-radius:8px;}
.rtrs-faqpage-section .rtrs-faqpage-question{display:flex;align-items:baseline;gap:10px;margin:0 0 8px;font-size:1em;font-weight:600;line-height:1.5;color:#1a1a1a}
.rtrs-faqpage-section .rtrs-faqpage-question .rtrs-faq-icon{flex-shrink:0;font-weight:700;color:#3b82f6;font-size:1.1em}
.rtrs-faqpage-section .rtrs-faqpage-answer{display:flex;align-items:baseline;gap:10px;margin:0;padding-left:0;line-height:1.7;color:#4d5156;font-size:.95em}
.rtrs-faqpage-section .rtrs-faqpage-answer .rtrs-faq-icon{flex-shrink:0;font-weight:700;color:#16a34a;font-size:1.1em}
</style>

<div class="rtrs-faqpage-section">
	<h3 class="rtrs-faqpage-title"><?php esc_html_e( 'Frequently Asked Questions', 'review-schema' ); ?></h3>
	<ul class="rtrs-faqpage-list">
		<?php foreach ( $faqs as $rtrs_faq ) : ?>
		<li class="rtrs-faqpage-item">
			<h4 class="rtrs-faqpage-question">
				<span class="rtrs-faq-icon">Q.</span>
				<span><?php echo esc_html( $rtrs_faq['question'] ); ?></span>
			</h4>
			<div class="rtrs-faqpage-answer">
				<span class="rtrs-faq-icon">A.</span>
				<div><?php echo wp_kses_post( $rtrs_faq['answer'] ); ?></div>
			</div>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
