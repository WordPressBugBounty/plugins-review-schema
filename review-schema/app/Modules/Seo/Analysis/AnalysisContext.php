<?php
/**
 * Per-post content context, built once and shared by every criterion.
 *
 * Parses the rendered post content a single time and exposes the signals the
 * SEO criteria need (title, meta, headings incl. H1, links, word count). This
 * keeps each criterion cheap and side-effect free.
 *
 * @package Rtrs\Modules\Seo\Analysis
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Analysis;

use Rtrs\Modules\Seo\Helpers\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnalysisContext
 */
class AnalysisContext {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Cached WP_Post.
	 *
	 * @var \WP_Post|null
	 */
	private $post;

	/**
	 * Cached rendered HTML content.
	 *
	 * @var string|null
	 */
	private $content;

	/**
	 * Cached plain-text content.
	 *
	 * @var string|null
	 */
	private $plain_text;

	/**
	 * Cached headings list.
	 *
	 * @var array[]|null
	 */
	private $headings;

	/**
	 * Cached FAQ question/answer pairs.
	 *
	 * @var array[]|null
	 */
	private $faqs;

	/**
	 * Cached main-article HTML (non-prose elements removed).
	 *
	 * @var string|null
	 */
	private $article_html;

	/**
	 * Cached main-article plain text.
	 *
	 * @var string|null
	 */
	private $article_text;

	/**
	 * Cached main-article word count.
	 *
	 * @var int|null
	 */
	private $article_word_count;

	/**
	 * Cached main-article paragraphs (plain text).
	 *
	 * @var string[]|null
	 */
	private $article_paragraphs;

	/**
	 * Optional live content override (unsaved editor content).
	 *
	 * When set, content-based checks (headings, links) score this instead of the
	 * saved post content — used by the editor's "re-analyze on change" path.
	 *
	 * Ignored for page-builder posts (see content()): a builder stores its layout
	 * outside the block-editor DOM, so an override cannot represent what the page
	 * renders. Builder posts always score the builder output so every editor
	 * surface reports an identical result.
	 *
	 * @var string|null
	 */
	private $content_override;

	/**
	 * Constructor.
	 *
	 * @param int         $post_id          Post ID.
	 * @param string|null $content_override Optional live content to score instead
	 *                                      of the saved post content.
	 */
	public function __construct( $post_id, $content_override = null ) {
		$this->post_id          = (int) $post_id;
		$this->post             = get_post( $this->post_id );
		$this->content_override = is_string( $content_override ) ? $content_override : null;
	}

	/**
	 * @return int
	 */
	public function post_id() {
		return $this->post_id;
	}

	/**
	 * SEO meta title (with plugin overrides).
	 *
	 * @return string
	 */
	public function meta_title() {
		return SeoMeta::get_meta_title( $this->post_id );
	}

	/**
	 * SEO meta description.
	 *
	 * @return string
	 */
	public function meta_description() {
		return SeoMeta::get_meta_description( $this->post_id );
	}

	/**
	 * Focus keyword (may be empty).
	 *
	 * @return string
	 */
	public function focus_keyword() {
		return SeoMeta::get_focus_keyword( $this->post_id );
	}

	/**
	 * Rendered post content HTML (blocks expanded, shortcodes stripped).
	 *
	 * Uses do_blocks() rather than the_content to avoid page-builder side
	 * effects (see Schema::get_page_description note). For Elementor-built posts
	 * the raw post_content is empty/stale, so the builder output is rendered
	 * instead — safe here because the SEO analyzer runs in an isolated REST
	 * request, not early in wp_head.
	 *
	 * @return string
	 */
	public function content() {
		if ( null !== $this->content ) {
			return $this->content;
		}

		// Page-builder posts (e.g. Elementor) store their layout in meta, not in
		// post_content or the block-editor DOM. A client-supplied live override
		// therefore cannot represent what the page actually renders, so the
		// builder output always wins — this keeps every surface (metabox,
		// Gutenberg sidebar, Elementor panel) scoring one identical source.
		// Non-builder posts keep the live override for "re-analyze on change".
		$builder = $this->elementor_content();

		if ( null !== $builder ) {
			$raw = $builder;
		} elseif ( null !== $this->content_override ) {
			$raw = $this->content_override;
		} else {
			$raw = $this->post ? (string) $this->post->post_content : '';
		}

		$raw = do_blocks( $raw );
		$raw = strip_shortcodes( $raw );

		// Metabox FAQ (_rtrs_faqpage_data) is stored outside post_content and
		// output only via the [rtrs_faqpage] shortcode / FAQPage schema, so it
		// never appears in the analyzed body. Append it as FAQ-block markup so the
		// AEO FAQ / Direct-Answer detectors credit it too.
		$raw = $this->append_faq_meta( $raw );

		$this->content = $raw;

		return $this->content;
	}

	/**
	 * Rendered Elementor builder HTML for the post, or null when not applicable.
	 *
	 * Elementor stores its layout in the `_elementor_data` meta, leaving
	 * `post_content` empty or stale. Rendering the builder output lets the
	 * heading / link / word-count criteria analyze what actually reaches the
	 * front end. Returns null when Elementor is inactive, the post is not
	 * built with Elementor, or the renderer is unavailable — callers then fall
	 * back to post_content.
	 *
	 * @return string|null
	 */
	private function elementor_content() {
		if ( ! $this->post
			|| ! \Rtrs\Modules\Schema\Hooks\ElementorFaq::is_elementor_post( $this->post_id )
		) {
			return null;
		}

		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->frontend ) ) {
			return null;
		}

		// Elementor's get_builder_content_for_display() has an anti-recursion
		// guard that returns an empty string when get_the_ID() equals the post
		// being rendered (frontend.php: "Avoid recursion"). The SEO report sets
		// the global post to the analyzed post before this runs, so that guard
		// trips and the builder HTML comes back empty — making every content
		// check (first paragraph, headings, links, subheadings) false-fire.
		// Neutralize the global post around the render so the guard is skipped,
		// then restore it so nothing downstream inherits the change.
		$previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary; restored in finally.
		$GLOBALS['post'] = null;

		try {
			$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $this->post_id, false );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the original global.
			$GLOBALS['post'] = $previous_post;
		}

		return '' !== trim( (string) $html ) ? $html : null;
	}

	/**
	 * Append the metabox FAQ (_rtrs_faqpage_data) as FAQ-block markup.
	 *
	 * Uses the same `rtrs-faq-question-text` heading class the AEO criteria look
	 * for, so both FAQ / Q&A Structure and Direct Answer Blocks detect metabox
	 * FAQ. No-op when the content already carries FAQ-block markup (an rtrs/faq
	 * block was in the body — avoid double-counting) or no metabox FAQ is saved.
	 *
	 * @param string $html Analyzed content so far.
	 * @return string
	 */
	private function append_faq_meta( $html ) {
		if ( ! $this->post_id || false !== stripos( $html, 'rtrs-faq-question-text' ) ) {
			return $html;
		}

		$faq = get_post_meta( $this->post_id, '_rtrs_faqpage_data', true );
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return $html;
		}

		$markup = '';
		foreach ( $faq as $item ) {
			$question = isset( $item['question'] ) ? trim( wp_strip_all_tags( (string) $item['question'] ) ) : '';
			$answer   = isset( $item['answer'] ) ? trim( (string) $item['answer'] ) : '';
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$markup .= '<div class="rtrs-faq-item">'
				. '<h3 class="rtrs-faq-question-text">' . esc_html( $question ) . '</h3>'
				. '<div class="rtrs-faq-answer-inner"><p>' . wp_kses_post( $answer ) . '</p></div>'
				. '</div>';
		}

		return '' === $markup ? $html : $html . '<div class="rtrs-faq">' . $markup . '</div>';
	}

	/**
	 * Remove CSS from rendered content so it is never scored as prose.
	 *
	 * do_blocks() and some block plugins (e.g. gtnbrg / core layout containers)
	 * emit style rules two ways: tag-wrapped <style>…</style>, and as bare
	 * "selector{ … }" text that survives tag stripping. Both are removed here.
	 * The bare-rule pattern requires a leading class/id selector (.foo / #bar) so
	 * it targets CSS only and never eats prose that happens to contain braces.
	 *
	 * Shared choke point: every AEO/SEO text path routes through this.
	 *
	 * @param string $html Rendered HTML (or already-flattened text).
	 * @return string
	 */
	public static function strip_css( $html ) {
		$html = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', (string) $html );
		$out  = preg_replace( '/(?:[.#][A-Za-z0-9_\-]+\s*)+\{[^{}]*\}/', ' ', (string) $html );

		return is_string( $out ) ? $out : (string) $html;
	}

	/**
	 * Plain-text body (tags stripped, whitespace collapsed).
	 *
	 * @return string
	 */
	public function plain_text() {
		if ( null !== $this->plain_text ) {
			return $this->plain_text;
		}

		$text             = wp_strip_all_tags( self::strip_css( $this->content() ) );
		$this->plain_text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

		return $this->plain_text;
	}

	/**
	 * Word count of the body.
	 *
	 * @return int
	 */
	public function word_count() {
		return str_word_count( $this->plain_text() );
	}

	/**
	 * Main-article HTML: content() with non-prose elements removed.
	 *
	 * Strips headings, tables, code/pre blocks, navigation, figure captions and
	 * script/style so density and prose metrics reflect only the readable
	 * article body — not structural or repeated UI markup. Site menus/widgets
	 * are not part of post_content, so they are already excluded.
	 *
	 * @return string
	 */
	public function article_html() {
		if ( null !== $this->article_html ) {
			return $this->article_html;
		}

		$html     = $this->content();
		$patterns = [
			'/<h[1-6]\b[^>]*>.*?<\/h[1-6]>/is',
			'/<table\b[^>]*>.*?<\/table>/is',
			'/<pre\b[^>]*>.*?<\/pre>/is',
			'/<code\b[^>]*>.*?<\/code>/is',
			'/<nav\b[^>]*>.*?<\/nav>/is',
			'/<figcaption\b[^>]*>.*?<\/figcaption>/is',
			'/<(script|style)\b[^>]*>.*?<\/\1>/is',
		];
		$stripped = preg_replace( $patterns, ' ', $html );
		$stripped = self::strip_css( is_string( $stripped ) ? $stripped : $html );

		$this->article_html = is_string( $stripped ) ? $stripped : $html;

		return $this->article_html;
	}

	/**
	 * Main-article plain text (tags stripped, whitespace collapsed).
	 *
	 * @return string
	 */
	public function article_text() {
		if ( null !== $this->article_text ) {
			return $this->article_text;
		}

		$text               = wp_strip_all_tags( $this->article_html() );
		$this->article_text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

		return $this->article_text;
	}

	/**
	 * Word count of the main-article text.
	 *
	 * @return int
	 */
	public function article_word_count() {
		if ( null !== $this->article_word_count ) {
			return $this->article_word_count;
		}

		$this->article_word_count = str_word_count( $this->article_text() );

		return $this->article_word_count;
	}

	/**
	 * Main-article paragraphs as plain-text strings, in document order.
	 *
	 * @return string[]
	 */
	public function article_paragraphs() {
		if ( null !== $this->article_paragraphs ) {
			return $this->article_paragraphs;
		}

		$paragraphs = [];
		if ( preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $this->article_html(), $matches ) ) {
			foreach ( $matches[1] as $raw ) {
				$text = trim( preg_replace( '/\s+/', ' ', (string) wp_strip_all_tags( $raw ) ) );
				if ( '' !== $text ) {
					$paragraphs[] = $text;
				}
			}
		}

		$this->article_paragraphs = $paragraphs;

		return $paragraphs;
	}

	/**
	 * Headings found in the content, in document order.
	 *
	 * Captures H1-H6 (ContentExtractor only captures H2-H6). Note that most
	 * themes render the post title as the page H1 outside the content, so the
	 * Heading criterion treats the title as the implicit H1.
	 *
	 * The `is_faq` flag marks questions rendered by the plugin's FAQ block
	 * (`<h3 class="rtrs-faq-question-text">`). These are concise Q&A carrying
	 * FAQPage schema, not document structure, so consumers can exclude them.
	 *
	 * @return array[] List of [ 'level' => int, 'text' => string, 'is_faq' => bool ].
	 */
	public function headings() {
		if ( null !== $this->headings ) {
			return $this->headings;
		}

		$headings = [];
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $this->content(), $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headings[] = [
					'level'  => (int) $match[1],
					'text'   => trim( wp_strip_all_tags( $match[2] ) ),
					'is_faq' => false !== stripos( $match[0], 'rtrs-faq-question-text' ),
				];
			}
		}

		$this->headings = $headings;

		return $headings;
	}

	/**
	 * FAQ question/answer pairs found in the content, in document order.
	 *
	 * Parses the plugin's FAQ markup (`rtrs-faq-item` with a
	 * `rtrs-faq-question-text` heading and a `rtrs-faq-answer-inner` body) that
	 * both the FAQ block and the metabox/AI-generated FAQ (injected by
	 * append_faq_meta()) render into. Exposing the answers as discrete pairs lets
	 * consumers (e.g. the AEO AI review) evaluate the complete Q&A even when the
	 * flattened body text would be truncated before the FAQ — the FAQ block sits
	 * at the end of the content, so a long article would otherwise cut it off.
	 *
	 * @return array[] List of [ 'question' => string, 'answer' => string ].
	 */
	public function faqs() {
		if ( null !== $this->faqs ) {
			return $this->faqs;
		}

		$faqs = [];
		if ( preg_match_all( '/<div[^>]*\brtrs-faq-item\b[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>/is', $this->content(), $items ) ) {
			foreach ( $items[0] as $item ) {
				$question = '';
				if ( preg_match( '/class="[^"]*rtrs-faq-question-text[^"]*"[^>]*>(.*?)<\/h[1-6]>/is', $item, $q ) ) {
					$question = trim( preg_replace( '/\s+/', ' ', (string) wp_strip_all_tags( $q[1] ) ) );
				}

				$answer = '';
				if ( preg_match( '/class="[^"]*rtrs-faq-answer-inner[^"]*"[^>]*>(.*?)<\/div>/is', $item, $a ) ) {
					$answer = trim( preg_replace( '/\s+/', ' ', (string) wp_strip_all_tags( $a[1] ) ) );
				}

				if ( '' !== $question && '' !== $answer ) {
					$faqs[] = [
						'question' => $question,
						'answer'   => $answer,
					];
				}
			}
		}

		$this->faqs = $faqs;

		return $faqs;
	}

	/**
	 * Count internal links in the content.
	 *
	 * Mirrors ContentExtractor::count_internal_links() (home-relative or same-host).
	 *
	 * @return int
	 */
	public function internal_link_count() {
		return $this->count_links( true );
	}

	/**
	 * Count external links in the content.
	 *
	 * @return int
	 */
	public function external_link_count() {
		return $this->count_links( false );
	}

	/**
	 * Shared link counter.
	 *
	 * @param bool $internal Whether to count internal (true) or external (false).
	 * @return int
	 */
	private function count_links( $internal ) {
		$home  = home_url();
		$count = 0;

		if ( preg_match_all( '/href=["\']([^"\']+)/i', $this->content(), $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$is_internal = ( 0 === strpos( $url, $home ) ) || ( 0 === strpos( $url, '/' ) );
				if ( $internal && $is_internal ) {
					$count++;
				} elseif ( ! $internal && 0 === strpos( $url, 'http' ) && 0 !== strpos( $url, $home ) ) {
					$count++;
				}
			}
		}

		return $count;
	}
}
