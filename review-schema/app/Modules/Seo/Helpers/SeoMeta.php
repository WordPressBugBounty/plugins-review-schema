<?php
/**
 * Multi-plugin readers for SEO meta title, description and focus keyword.
 *
 * The plugin already reads meta descriptions in
 * Rtrs\Modules\Schema\Models\Schema::get_page_description(), but that method
 * truncates and falls back to content. For length-based analysis we need the
 * raw SEO-plugin values, plus a meta-title and focus-keyword reader which do
 * not exist elsewhere.
 *
 * @package Rtrs\Modules\Seo\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Modules\Seo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoMeta
 */
class SeoMeta {

	/**
	 * Get the SEO meta title for a post.
	 *
	 * Reads known SEO-plugin meta keys (Yoast, Rank Math, AIOSEO, SEOPress,
	 * Genesis) and falls back to the post title. Template variables such as
	 * %%title%% are reduced to the post title for accurate length checks.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_meta_title( $post_id ) {
		$keys = array_keys( self::active_title_keys() );

		foreach ( $keys as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( ! empty( $val ) && is_string( $val ) ) {
				return self::expand_template_vars( trim( $val ), $post_id );
			}
		}

		$aioseo = self::get_aioseo_field( $post_id, 'title' );
		if ( '' !== $aioseo ) {
			return self::expand_template_vars( $aioseo, $post_id );
		}

		$sc_title = self::get_surecart_field( $post_id, 'page_title' );
		if ( '' !== $sc_title ) {
			return self::expand_template_vars( $sc_title, $post_id );
		}

		return (string) get_the_title( $post_id );
	}

	/**
	 * Get the raw SEO meta description for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty string when none is set.
	 */
	public static function get_meta_description( $post_id ) {
		$keys = array_keys( self::active_description_keys() );

		foreach ( $keys as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( ! empty( $val ) && is_string( $val ) ) {
				return self::expand_template_vars( trim( $val ), $post_id );
			}
		}

		$aioseo = self::get_aioseo_field( $post_id, 'description' );
		if ( '' !== $aioseo ) {
			return self::expand_template_vars( $aioseo, $post_id );
		}

		$sc_desc = self::get_surecart_field( $post_id, 'meta_description' );
		if ( '' !== $sc_desc ) {
			return self::expand_template_vars( $sc_desc, $post_id );
		}

		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_excerpt ) ) {
			return trim( $post->post_excerpt );
		}

		return '';
	}

	/**
	 * Read a SureCart product's "Search Engine Listing" SEO field.
	 *
	 * SureCart persists a product as the serialized `product` post meta (its API
	 * model's toArray()). The SEO title and meta description the merchant enters
	 * in the product's "Search Engine Listing" section live in that model's
	 * `metadata` object under `page_title` / `meta_description`, not as standalone
	 * post meta — so a post-meta-only read cannot see them. Returns '' for
	 * non-SureCart posts or when the requested field is empty.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key SureCart metadata key ('page_title'|'meta_description').
	 * @return string
	 */
	private static function get_surecart_field( $post_id, $meta_key ) {
		if ( 'sc_product' !== get_post_type( $post_id ) ) {
			return '';
		}

		$product = get_post_meta( $post_id, 'product', true );
		if ( ! is_array( $product ) || empty( $product['metadata'] ) ) {
			return '';
		}

		// `metadata` is stored as a stdClass in the model's array form.
		$metadata = (array) $product['metadata'];
		if ( empty( $metadata[ $meta_key ] ) || ! is_string( $metadata[ $meta_key ] ) ) {
			return '';
		}

		return trim( $metadata[ $meta_key ] );
	}

	/**
	 * Read a title/description value from AIOSEO's own storage.
	 *
	 * AIOSEO v4 persists these in the `aioseo_posts` table (via the Post model),
	 * not post meta — so a value set through the AIOSEO metabox is invisible to a
	 * post-meta-only read. Returns '' when AIOSEO is inactive or the field is empty.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   'title' | 'description'.
	 * @return string
	 */
	private static function get_aioseo_field( $post_id, $field ) {
		if ( ! defined( 'AIOSEO_VERSION' ) || ! class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			return '';
		}

		try {
			$aioseo_post = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
			if ( $aioseo_post && isset( $aioseo_post->{$field} ) && is_string( $aioseo_post->{$field} ) ) {
				return trim( $aioseo_post->{$field} );
			}
		} catch ( \Throwable $e ) {
			return '';
		}

		return '';
	}

	/**
	 * Get the focus keyword for a post.
	 *
	 * Prefers an active SEO plugin's focus keyword, then the plugin's own
	 * `_rtrs_focus_keyword` meta (used by the Pro Keyword Coverage step).
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty string when none is set.
	 */
	public static function get_focus_keyword( $post_id ) {
		// When an SEO plugin is active it is the single source of truth: read
		// only its keyword. An empty field there yields '' (so Keyword Coverage
		// warns to set one) rather than falling back to a stale own-meta value.
		if ( defined( 'WPSEO_VERSION' ) ) {
			$val = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			return is_string( $val ) ? trim( $val ) : '';
		}

		if ( class_exists( 'RankMath' ) ) {
			$val = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$parts = explode( ',', $val );
				return trim( $parts[0] );
			}
			return '';
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			// AIOSEO v4's source of truth is its `aioseo_posts` table (the Focus
			// Keyword metabox field), not the legacy `_aioseo_keyphrases` post meta
			// — read the table via the model and fall back to the meta only if the
			// model is unavailable, so the analyzer matches what AIOSEO shows.
			if ( class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
				try {
					$aioseo_post = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
					$keyphrases  = $aioseo_post ? $aioseo_post->keyphrases : null;
					if ( is_string( $keyphrases ) ) {
						$keyphrases = json_decode( $keyphrases );
					}
					if ( is_object( $keyphrases ) && isset( $keyphrases->focus->keyphrase ) && is_string( $keyphrases->focus->keyphrase ) ) {
						return trim( $keyphrases->focus->keyphrase );
					}
					return '';
				} catch ( \Throwable $e ) {
					return '';
				}
			}

			$val = get_post_meta( $post_id, '_aioseo_keyphrases', true );
			if ( ! empty( $val ) ) {
				$data = is_string( $val ) ? json_decode( $val, true ) : $val;
				if ( is_array( $data ) && ! empty( $data['focus']['keyphrase'] ) ) {
					return trim( $data['focus']['keyphrase'] );
				}
			}
			return '';
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$val = get_post_meta( $post_id, '_seopress_analysis_target_kw', true );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$parts = explode( ',', $val );
				return trim( $parts[0] );
			}
			return '';
		}

		// No SEO plugin active — default behavior: the plugin's own meta.
		$val = get_post_meta( $post_id, '_rtrs_focus_keyword', true );
		if ( is_string( $val ) && '' !== trim( $val ) ) {
			return trim( $val );
		}

		return '';
	}

	/**
	 * Whether a recognized SEO plugin is active and owns the title/description.
	 *
	 * When true, that plugin's field is the single source of truth: the plugin's
	 * own `_rtrs_seo_*` meta is not consulted as a fallback, so an empty field is
	 * reported as missing rather than masked by a stale own-meta value.
	 *
	 * @return bool
	 */
	private static function has_active_seo_plugin() {
		return (bool) self::active_description_keys();
	}

	/**
	 * Whether the Genesis framework (which owns _genesis_* meta) is active.
	 *
	 * @return bool
	 */
	private static function has_genesis() {
		return function_exists( 'genesis' ) || defined( 'PARENT_THEME_VERSION' );
	}

	/**
	 * Ordered [ meta_key => source ] map of SEO title keys for ACTIVE plugins only.
	 *
	 * Reading an inactive plugin's leftover meta (e.g. a stale _aioseo_title from a
	 * removed plugin) would mask the active plugin's field, so only active plugins
	 * contribute. Order sets precedence when several are active.
	 *
	 * @return array<string,string>
	 */
	private static function active_title_keys() {
		$keys = [];
		if ( defined( 'WPSEO_VERSION' ) ) {
			$keys['_yoast_wpseo_title'] = 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			$keys['rank_math_title'] = 'rank_math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$keys['_aioseo_title'] = 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$keys['_seopress_titles_title'] = 'seopress';
		}
		if ( self::has_genesis() ) {
			$keys['_genesis_title'] = 'genesis';
		}
		return $keys;
	}

	/**
	 * Ordered [ meta_key => source ] map of meta-description keys for ACTIVE plugins only.
	 *
	 * @return array<string,string>
	 */
	private static function active_description_keys() {
		$keys = [];
		if ( defined( 'WPSEO_VERSION' ) ) {
			$keys['_yoast_wpseo_metadesc'] = 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			$keys['rank_math_description'] = 'rank_math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$keys['_aioseo_description'] = 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$keys['_seopress_titles_desc'] = 'seopress';
		}
		if ( self::has_genesis() ) {
			$keys['_genesis_description'] = 'genesis';
		}
		return $keys;
	}

	/**
	 * Resolve the SEO title to a rendered string plus its winning source.
	 *
	 * Source-resolution chain (first non-empty wins): Yoast → Rank Math →
	 * AIOSEO → SEOPress → Genesis → post title. Template variables are resolved
	 * before returning so the value reflects what actually renders.
	 *
	 * @param int $post_id Post ID.
	 * @return array{ value:string, source:string } Source is one of
	 *               yoast|rank_math|aioseo|seopress|genesis|post_title.
	 */
	public static function resolve_title( $post_id ) {
		$map = self::active_title_keys();

		// Own meta is a fallback only when no SEO plugin owns the field. When one is
		// active it is the single source of truth, so an empty field there must not
		// be masked by a stale own-meta value (mirrors get_focus_keyword()).
		if ( ! self::has_active_seo_plugin() ) {
			$map['_rtrs_seo_title'] = 'plugin';
		}

		foreach ( $map as $key => $source ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( ! empty( $val ) && is_string( $val ) ) {
				$resolved = self::expand_template_vars( trim( $val ), $post_id );
				if ( '' !== $resolved ) {
					return [
						'value'  => $resolved,
						'source' => $source,
					];
				}
			}
		}

		$aioseo = self::get_aioseo_field( $post_id, 'title' );
		if ( '' !== $aioseo ) {
			$resolved = self::expand_template_vars( $aioseo, $post_id );
			if ( '' !== $resolved ) {
				return [
					'value'  => $resolved,
					'source' => 'aioseo',
				];
			}
		}

		// SureCart products carry their own "Search Engine Listing" title. An active
		// SEO plugin's own field (checked above) still wins when set, but SureCart's
		// native value takes precedence over the bare post-title fallback.
		$sc_title = self::get_surecart_field( $post_id, 'page_title' );
		if ( '' !== $sc_title ) {
			return [
				'value'  => self::expand_template_vars( $sc_title, $post_id ),
				'source' => 'surecart',
			];
		}

		return [
			'value'  => (string) get_the_title( $post_id ),
			'source' => 'post_title',
		];
	}

	/**
	 * Resolve the SEO meta description to a rendered string plus its source.
	 *
	 * Strict chain (first non-empty wins): Yoast → Rank Math → AIOSEO →
	 * SEOPress → Genesis. There is intentionally NO excerpt fallback — an empty
	 * result means "missing" for analysis purposes.
	 *
	 * @param int $post_id Post ID.
	 * @return array{ value:string, source:string } Source is one of
	 *               yoast|rank_math|aioseo|seopress|genesis|none.
	 */
	public static function resolve_description( $post_id ) {
		$map = self::active_description_keys();

		// Own meta is a fallback only when no SEO plugin owns the field. When one is
		// active it is the single source of truth, so an empty field there reads as
		// "missing" instead of being masked by a stale own-meta value.
		if ( ! self::has_active_seo_plugin() ) {
			$map['_rtrs_seo_desc'] = 'plugin';
		}

		foreach ( $map as $key => $source ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( ! empty( $val ) && is_string( $val ) ) {
				$resolved = self::expand_template_vars( trim( $val ), $post_id );
				if ( '' !== $resolved ) {
					return [
						'value'  => $resolved,
						'source' => $source,
					];
				}
			}
		}

		$aioseo = self::get_aioseo_field( $post_id, 'description' );
		if ( '' !== $aioseo ) {
			$resolved = self::expand_template_vars( $aioseo, $post_id );
			if ( '' !== $resolved ) {
				return [
					'value'  => $resolved,
					'source' => 'aioseo',
				];
			}
		}

		// SureCart's "Search Engine Listing" meta description is authoritative for
		// its products when no active SEO plugin owns a value for the post.
		$sc_desc = self::get_surecart_field( $post_id, 'meta_description' );
		if ( '' !== $sc_desc ) {
			return [
				'value'  => self::expand_template_vars( $sc_desc, $post_id ),
				'source' => 'surecart',
			];
		}

		return [
			'value'  => '',
			'source' => 'none',
		];
	}

	/**
	 * Public wrapper to render a raw title/meta template string.
	 *
	 * Used by the live "on change" path so unsaved editor values (which may be
	 * Yoast/Rank Math templates) are normalized exactly like saved values before
	 * being measured.
	 *
	 * @param string $text    Raw template value.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function render_template( $text, $post_id ) {
		return self::expand_template_vars( (string) $text, (int) $post_id );
	}

	/**
	 * Reduce SEO title/description template variables to a rendered string.
	 *
	 * SEO plugins store values like "%%title%% %%sep%% %%sitename%%" (Yoast,
	 * double percent) or "%title% %sep% %sitename%" (Rank Math, single percent).
	 * We resolve the common variables to real values before measuring length, so
	 * the character count reflects what actually renders, then strip any
	 * remaining unknown tokens.
	 *
	 * @param string $text    Raw template value.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private static function expand_template_vars( $text, $post_id ) {
		if ( false === strpos( $text, '%' ) ) {
			return $text;
		}

		$title    = (string) get_the_title( $post_id );
		$sitename = (string) get_bloginfo( 'name' );
		$sep      = self::get_separator();
		$excerpt  = self::get_excerpt_text( $post_id );

		$replacements = [
			'%%title%%'    => $title,
			'%title%'      => $title,
			'%%sitename%%' => $sitename,
			'%sitename%'   => $sitename,
			'%%sep%%'      => $sep,
			'%sep%'        => $sep,
			'%%page%%'     => '',
			'%page%'       => '',
			'%%excerpt%%'  => $excerpt,
			'%excerpt%'    => $excerpt,
		];

		$text = str_ireplace( array_keys( $replacements ), array_values( $replacements ), $text );

		// Strip any remaining %%var%% or %var% tokens (best-effort resolution).
		$text = preg_replace( '/%%[^%]+%%/', '', $text );
		$text = preg_replace( '/%[^%\s]+%/', '', $text );
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

		return '' !== $text ? $text : $title;
	}

	/**
	 * Best-effort title separator used to render %%sep%% template tokens.
	 *
	 * Reads Yoast's configured separator when available, otherwise a dash.
	 *
	 * @return string
	 */
	private static function get_separator() {
		$titles = get_option( 'wpseo_titles' );
		if ( is_array( $titles ) && ! empty( $titles['separator'] ) ) {
			$known = [
				'sc-dash'   => '-',
				'sc-ndash'  => '–',
				'sc-mdash'  => '—',
				'sc-middot' => '·',
				'sc-bull'   => '•',
				'sc-star'   => '*',
				'sc-pipe'   => '|',
				'sc-tilde'  => '~',
				'sc-laquo'  => '«',
				'sc-raquo'  => '»',
				'sc-lt'     => '<',
				'sc-gt'     => '>',
			];
			if ( isset( $known[ $titles['separator'] ] ) ) {
				return $known[ $titles['separator'] ];
			}
		}

		return '-';
	}

	/**
	 * Plain-text excerpt used to render %%excerpt%% template tokens.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_excerpt_text( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		if ( ! empty( $post->post_excerpt ) ) {
			return trim( wp_strip_all_tags( $post->post_excerpt ) );
		}
		$body = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		return trim( wp_trim_words( $body, 30, '' ) );
	}
}
