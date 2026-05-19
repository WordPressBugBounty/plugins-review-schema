<?php
/**
 * SERP card HTML helper function.
 *
 * Shared by serp-preview.php (initial render) and serp-preview-content.php (AJAX refresh).
 *
 * @package review-schema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rtrs_serp_card_html' ) ) :
function rtrs_serp_card_html( $title, $description, $display_url, $favicon, $site_name, $rich, $schema_type, $word_limit ) {
	?>
	<div class="rtrs-serp-site-row">
		<?php if ( $favicon ) : ?>
			<img class="rtrs-serp-favicon" src="<?php echo esc_url( $favicon ); ?>" alt="" width="16" height="16" />
		<?php else : ?>
			<span class="rtrs-serp-favicon rtrs-serp-favicon--placeholder"></span>
		<?php endif; ?>
		<div class="rtrs-serp-site-info">
			<span class="rtrs-serp-site-name"><?php echo esc_html( $site_name ); ?></span>
			<span class="rtrs-serp-url"><?php echo esc_html( $display_url ); ?></span>
		</div>
	</div>

	<div class="rtrs-serp-title"><?php echo esc_html( $title ); ?></div>

	<?php if ( $description ) : ?>
		<div class="rtrs-serp-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $description ), $word_limit, '...' ) ); ?></div>
	<?php endif; ?>

	<?php
	// Rating.
	if ( ! empty( $rich['rating'] ) ) :
		$val = floatval( $rich['rating']['value'] );
		$bst = floatval( $rich['rating']['best'] ?? 5 );
		$cnt = intval( $rich['rating']['count'] ?? 0 );
		$pct = $bst > 0 ? ( $val / $bst ) * 100 : 0;
		?>
		<div class="rtrs-serp-rating">
			<span class="rtrs-serp-stars">
				<span class="rtrs-serp-stars__track">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
				<span class="rtrs-serp-stars__fill" style="width:<?php echo esc_attr( $pct ); ?>%">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
			</span>
			<span class="rtrs-serp-rating-text">
				<?php
				printf(
					/* translators: 1: rating value, 2: best rating */
					esc_html__( 'Rating: %1$s/%2$s', 'review-schema' ),
					esc_html( number_format( $val, 1 ) ),
					esc_html( number_format( $bst, 0 ) )
				);
				if ( $cnt ) {
					echo ' &middot; ';
					printf(
						/* translators: %s: review count */
						esc_html( _n( '%s review', '%s reviews', $cnt, 'review-schema' ) ),
						esc_html( number_format_i18n( $cnt ) )
					);
				}
				?>
			</span>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $rich['price'] ) ) : ?>
		<div class="rtrs-serp-price">
			<?php if ( ! empty( $rich['price']['value'] ) ) : ?>
				<span class="rtrs-serp-price__value"><?php echo esc_html( ! empty( $rich['price']['currency'] ) ? $rich['price']['currency'] . ' ' . $rich['price']['value'] : $rich['price']['value'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $rich['price']['availability'] ) ) : ?>
				<span class="rtrs-serp-price__avail rtrs-serp-price__avail--<?php echo esc_attr( sanitize_html_class( strtolower( $rich['price']['availability'] ) ) ); ?>">
					<?php echo esc_html( $rich['price']['availability'] ); ?>
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $rich['event'] ) ) :
		$parts = array_filter( [ $rich['event']['startDate'] ?? '', $rich['event']['location'] ?? '' ] );
		if ( $parts ) : ?>
			<div class="rtrs-serp-event">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
				<?php echo esc_html( implode( ' · ', $parts ) ); ?>
			</div>
		<?php endif;
	endif; ?>

	<?php if ( ! empty( $rich['recipe'] ) ) :
		$parts = array_filter( [ $rich['recipe']['totalTime'] ?? '', $rich['recipe']['calories'] ?? '' ] );
		if ( $parts ) : ?>
			<div class="rtrs-serp-recipe-meta"><?php echo esc_html( implode( ' · ', $parts ) ); ?></div>
		<?php endif;
	endif; ?>

	<?php if ( ! empty( $rich['faqItems'] ) ) : ?>
		<div class="rtrs-serp-faq">
			<?php foreach ( $rich['faqItems'] as $faq ) : ?>
				<div class="rtrs-serp-faq-item">
					<button type="button" class="rtrs-serp-faq-q">
						<span><?php echo esc_html( $faq['question'] ); ?></span>
						<svg class="rtrs-serp-faq-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
					</button>
					<?php if ( ! empty( $faq['answer'] ) ) : ?>
						<div class="rtrs-serp-faq-a"><?php echo esc_html( wp_trim_words( $faq['answer'], 25, '...' ) ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $rich['howToSteps'] ) ) : ?>
		<div class="rtrs-serp-howto">
			<?php foreach ( $rich['howToSteps'] as $i => $step ) : ?>
				<div class="rtrs-serp-howto-step">
					<span class="rtrs-serp-howto-num"><?php echo esc_html( $i + 1 ); ?></span>
					<span class="rtrs-serp-howto-name"><?php echo esc_html( $step['name'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif;
}
endif;
