<?php

namespace Rtrs\Modules\Schema\Models;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Review\Helpers\ReviewFns;
use Rtrs\Modules\Schema\Helpers\SchemaFns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema class.
 */
class Schema {
	/**
	 * @var $post_id.
	 */
	public $post_id = null;

	/**
	 * Initialize hooks.
	 */
	public function __construct( $post_id = null ) {
		$this->post_id = absint( $post_id ?? get_queried_object_id() );
		add_filter( 'rtrs_schema_graph_data', [ $this, 'schema_filtered' ], 10 );
	}

	/**
	 * @param  array $schema_graph_list  list.
	 *
	 * @return array|mixed
	 */
	public function schema_filtered( $schema_graph_list ) {
		return array_merge(
			$schema_graph_list,
			$this->site_schema(),
			$this->rich_snippet(),
		);
	}

	/**
	 * Check whether a schema item exists in post meta.
	 *
	 * @param  int    $post_id  Post ID.
	 * @param  string $item  Schema key to check.
	 *
	 * @return bool
	 */
	public function has_schema( $post_id, $item ) {
		$schema_cat = get_post_meta( $post_id, '_rtrs_rich_snippet_cat', false );
		if ( empty( $schema_cat ) ) {
			return false;
		}

		return in_array( $item, $schema_cat, true );
	}

	/**
	 * Get page title safely for WebPage schema.
	 *
	 * @return string
	 */
	public function get_page_title() {
		if ( is_singular() ) {
			// Single post or page.
			return get_the_title();
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return single_term_title( '', false );
		}
		if ( is_post_type_archive() ) {
			return post_type_archive_title( '', false );
		}
		if ( is_author() ) {
			return get_the_author_meta( 'display_name', get_query_var( 'author' ) );
		}
		if ( is_date() ) {
			return get_the_archive_title();
		}
		if ( is_search() ) {
			return sprintf(
				/* translators: %s: search query */
				esc_html__( 'Search results for "%s"', 'review-schema' ),
				get_search_query()
			);
		}
		if ( is_home() ) {
			return get_the_title( get_option( 'page_for_posts' ) );
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Get page description safely for WebPage schema.
	 *
	 * @return string
	 */
	public function get_page_description() {
		$is_single = is_singular() || ( $this->post_id && ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) );

		if ( $is_single ) {
			$post_obj = get_post( $this->post_id );
			$desc     = '';

			// 1. Prefer SEO plugin meta descriptions if present.
			$seo_meta_keys = [
				'_yoast_wpseo_metadesc',  // Yoast SEO.
				'rank_math_description',  // Rank Math.
				'_aioseo_description',    // All in One SEO.
				'_genesis_description',   // Genesis Framework.
				'_seopress_titles_desc',  // SEOPress.
			];
			foreach ( $seo_meta_keys as $meta_key ) {
				$meta_val = get_post_meta( $this->post_id, $meta_key, true );
				if ( ! empty( $meta_val ) && is_string( $meta_val ) ) {
					$desc = $meta_val;
					break;
				}
			}

			// 2. Fallback to post excerpt.
			if ( empty( $desc ) && $post_obj && ! empty( $post_obj->post_excerpt ) ) {
				$desc = $post_obj->post_excerpt;
			}

			// 3. Fallback to AI-generated content schema description (Article, Product, etc.).
			if ( empty( $desc ) ) {
				$ai_schemas = get_post_meta( $this->post_id, '_aise_schema_data', true );
				if ( ! empty( $ai_schemas ) ) {
					$ai_schemas = is_string( $ai_schemas ) ? json_decode( $ai_schemas, true ) : $ai_schemas;
					if ( is_array( $ai_schemas ) ) {
						foreach ( $ai_schemas as $ai_schema ) {
							if ( ! empty( $ai_schema['description'] ) && is_string( $ai_schema['description'] ) ) {
								$desc = $ai_schema['description'];
								break;
							}
						}
					}
				}
			}

			// 4. Fallback to rendered post_content (covers page builders via the_content filter).
			if ( empty( $desc ) && $post_obj ) {
				$raw = $post_obj->post_content;
				if ( has_filter( 'the_content' ) ) {
					$raw = apply_filters( 'the_content', $raw );
				}
				$desc = wp_strip_all_tags( strip_shortcodes( $raw ) );
			}

			// 5. Final fallback: post title + site tagline.
			if ( empty( trim( $desc ) ) ) {
				$title   = $post_obj ? $post_obj->post_title : '';
				$tagline = get_bloginfo( 'description' );
				$desc    = trim( $title . ' - ' . $tagline, ' -' );
			}

			$desc = html_entity_decode( $desc, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$desc = preg_replace( '/\s+/', ' ', trim( $desc ) );

			// Use 55 words (~300 chars) without trailing ellipsis when short enough.
			$word_count = str_word_count( $desc );
			if ( $word_count > 55 ) {
				$desc = wp_trim_words( $desc, 55, '...' );
			}

			return self::strip_emoji( $desc );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			$desc = ! empty( $term->description ) ? wp_strip_all_tags( $term->description ) : single_term_title( '', false );

			return self::strip_emoji( $desc );
		}
		if ( is_post_type_archive() ) {
			$post_type = get_post_type_object( get_post_type() );

			$desc = ! empty( $post_type->description ) ? wp_strip_all_tags( $post_type->description ) : $post_type->label;

			return self::strip_emoji( $desc );
		}
		if ( is_author() ) {
			return self::strip_emoji( get_the_author_meta( 'description', get_query_var( 'author' ) ) );
		}
		if ( is_search() ) {
			return sprintf(
				/* translators: %s: search query */
				esc_html__( 'Search results for "%s"', 'review-schema' ),
				get_search_query()
			);
		}
		if ( is_home() ) {
			return self::strip_emoji( get_bloginfo( 'description' ) );
		}

		return self::strip_emoji( get_bloginfo( 'description' ) );
	}

	/**
	 * Sanitize text for schema output — decode HTML entities and remove emojis.
	 *
	 * @param string $text Input text.
	 *
	 * @return string Clean plain text suitable for JSON-LD.
	 */
	public static function strip_emoji( $text ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Decode HTML entities to plain text.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove emoji unicode ranges.
		$text = preg_replace(
			'/[\x{1F600}-\x{1F64F}' . // Emoticons.
			'\x{1F300}-\x{1F5FF}' . // Misc Symbols and Pictographs.
			'\x{1F680}-\x{1F6FF}' . // Transport and Map.
			'\x{1F1E0}-\x{1F1FF}' . // Flags.
			'\x{2600}-\x{26FF}' . // Misc symbols.
			'\x{2700}-\x{27BF}' . // Dingbats.
			'\x{FE00}-\x{FE0F}' . // Variation Selectors.
			'\x{1F900}-\x{1F9FF}' . // Supplemental Symbols.
			'\x{1FA00}-\x{1FA6F}' . // Chess Symbols.
			'\x{1FA70}-\x{1FAFF}' . // Symbols and Pictographs Extended-A.
			'\x{200D}' . // Zero Width Joiner.
			'\x{20E3}' . // Combining Enclosing Keycap.
			'\x{E0020}-\x{E007F}' . // Tags.
			']+/u',
			'',
			$text
		);

		// Clean up extra spaces left after removal.
		$text = preg_replace( '/\s{2,}/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Get the public page URL with a custom hash appended.
	 *
	 * Handles AJAX, admin, and frontend contexts to always
	 * return the public-facing URL.
	 *
	 * @param  string $hash  Hash fragment without the '#' symbol.
	 *
	 * @return string Full URL with hash fragment.
	 * @since 1.0.0
	 */
	public function get_id_with_hash( $hash = '' ) {
		if ( wp_doing_ajax() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {

			// Priority 1: explicit POST
			// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Read-only ID lookup; result is always passed through absint().
			if ( isset( $_POST['post_id'] ) ) {
				$post_id = absint( $_POST['post_id'] );

				// Priority 2: GET param (edit screen)
			} elseif ( isset( $_GET['post'] ) ) {
				$post_id = absint( $_GET['post'] );
				// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended

				// Priority 3: global post
			} else {
				$post_id = get_the_ID();
			}

			$current_url = $post_id ? get_permalink( $post_id ) : home_url( '/' );

		} else {
			// Frontend — prefer get_permalink() for singular pages to avoid
			// path duplication when WP is installed in a subdirectory.
			if ( ! empty( $this->post_id ) && is_singular() ) {
				$current_url = get_permalink( $this->post_id );
			} else {
				global $wp;
				$current_url = home_url( $wp->request ? trailingslashit( $wp->request ) : '/' );
			}
		}
		// Remove any existing hash from the URL.
		$current_url = strtok( $current_url, '#' );
		// Fallback to home URL if empty.
		if ( empty( $current_url ) ) {
			$current_url = home_url( '/' );
		}
		if ( empty( $hash ) ) {
			return $current_url;
		}
		// Make sure the hash is clean.
		$hash = ltrim( $hash, '#' );

		return trailingslashit( $current_url ) . '#' . strtolower( $hash );
	}

	/**
	 * Get social links array.
	 *
	 * @return array
	 * @since 1.0
	 */
	public function get_social() {
		$social_links = rtrs()->get_options( 'rtrs_schema_social_profiles_settings', [ 'social_profiles', [] ] );

		// Remove empty fields.
		$social = [];
		foreach ( $social_links as $profile ) {
			if ( $profile['url'] ) {
				$social[] = $profile['url'];
			}
		}

		return $social;
	}

	/**
	 * Remove opening and closing script tags only.
	 *
	 * @param  string $content  Raw content.
	 *
	 * @return string
	 */
	public function remove_script_wrappers( $content ) {
		return preg_replace(
			'/<\/?script\b[^>]*>/i',
			'',
			(string) $content,
		);
	}

	/**
	 * Schema Return.
	 * return string
	 */
	public function header_schema_data() {
		// Bail if no valid post ID (archives, search, 404, etc).
		$post_id             = $this->post_id;
		$apply_custom_schema = apply_filters( 'rtrs_custom_rich_snippet_enabled', ( wp_doing_ajax() || is_singular() ), $post_id );
		if ( $post_id && function_exists( 'rtrsp' ) && $apply_custom_schema ) {
			$disable_generator = get_post_meta( $post_id, '_rtrs_disable_snippet_generator', true );
			$custom_snippet    = get_post_meta( $post_id, '_rtrs_custom_rich_snippet', true );
			if ( $custom_snippet && $disable_generator ) {
				return apply_filters( 'rtrs_custom_rich_snippet', '', $post_id );
			}
		}

		$schemaData          = [
			'@context' => 'https://schema.org',
		];
		$schema_graph_list   = [];
		$schema_graph_list[] = $this->generate_breadcrumb_schema( $post_id );
		$schema_graph_list   = apply_filters( 'rtrs_schema_graph_data', $schema_graph_list, $post_id );
		if ( ! empty( $schema_graph_list ) ) {
			$schemaData['@graph'] = $schema_graph_list;

			return $this->getJsonEncode( $schemaData );
		}

		return '';
	}

	/**
	 * Generate BreadcrumbList schema programmatically.
	 *
	 * @return array
	 */
	public function generate_breadcrumb_schema( $post_id = 0 ) {
		$items     = [];
		$position  = 1;
		$is_rest   = $post_id && ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		$is_ajax   = $post_id && wp_doing_ajax();
		$is_single = (bool) $post_id || is_singular();

		/**
		 * 1. Home
		 */
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => esc_html__( 'Home', 'review-schema' ),
			'item'     => trailingslashit( home_url() ),
		];
		/**
		 * 2. Blog page (if applicable)
		 */
		if ( $is_single && $post_id && 'post' === get_post_type( $post_id ) ) {
			$blog_id = get_option( 'page_for_posts' );
			if ( $blog_id ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => html_entity_decode( get_the_title( $blog_id ), ENT_QUOTES, 'UTF-8' ),
					'item'     => trailingslashit( get_permalink( $blog_id ) ),
				];
			}
		} elseif ( ! $is_rest && ( is_singular( 'post' ) || is_category() || is_tag() || is_home() ) ) {
			$blog_id = get_option( 'page_for_posts' );
			if ( $blog_id ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => html_entity_decode( get_the_title( $blog_id ), ENT_QUOTES, 'UTF-8' ),
					'item'     => trailingslashit( get_permalink( $blog_id ) ),
				];
			}
		}
		/**
		 * 3. Taxonomy archive (category / tag / custom tax)
		 */
		if ( ! $is_rest && ( is_category() || is_tag() || is_tax() ) ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => single_term_title( '', false ),
					'item'     => trailingslashit( get_term_link( $term ) ),
				];
			}
		}
		/**
		 * 4. Post type archive
		 */
		if ( ! $is_rest && is_post_type_archive() ) {
			$post_type = get_post_type();
			$obj       = get_post_type_object( $post_type );
			if ( $obj && ! empty( $obj->has_archive ) ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $obj->labels->name,
					'item'     => trailingslashit( get_post_type_archive_link( $post_type ) ),
				];
			}
		}
		/**
		 * 4b. Author archive
		 */
		if ( ! $is_rest && is_author() ) {
			$author_name = get_the_author();
			$author_url  = get_author_posts_url( get_queried_object_id() );
			if ( $author_name && $author_url ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $author_name,
					'item'     => trailingslashit( $author_url ),
				];
			}
		}
		/**
		 * 4c. Date archive
		 */
		if ( ! $is_rest && is_date() ) {
			if ( is_year() ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_date( 'Y' ),
					'item'     => trailingslashit( get_year_link( get_the_date( 'Y' ) ) ),
				];
			} elseif ( is_month() ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_date( 'Y' ),
					'item'     => trailingslashit( get_year_link( get_the_date( 'Y' ) ) ),
				];
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_date( 'F Y' ),
					'item'     => trailingslashit( get_month_link( get_the_date( 'Y' ), get_the_date( 'm' ) ) ),
				];
			} elseif ( is_day() ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_date( 'Y' ),
					'item'     => trailingslashit( get_year_link( get_the_date( 'Y' ) ) ),
				];
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_date( 'F Y' ),
					'item'     => trailingslashit( get_month_link( get_the_date( 'Y' ), get_the_date( 'm' ) ) ),
				];
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_date(),
					'item'     => trailingslashit( get_day_link( get_the_date( 'Y' ), get_the_date( 'm' ), get_the_date( 'd' ) ) ),
				];
			}
		}
		/**
		 * 4d. Search results
		 */
		if ( ! $is_rest && is_search() ) {
			$search_query = get_search_query();
			if ( $search_query ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => sprintf(
						/* translators: %s: search query */
						esc_html__( 'Search Results for "%s"', 'review-schema' ),
						$search_query
					),
					'item'     => trailingslashit( get_search_link( $search_query ) ),
				];
			}
		}
		/**
		 * 5. WooCommerce Shop
		 */
		if ( ! $is_rest && function_exists( 'is_shop' ) && is_shop() ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => html_entity_decode( get_the_title( $shop_id ), ENT_QUOTES, 'UTF-8' ),
					'item'     => trailingslashit( get_permalink( $shop_id ) ),
				];
			}
		}
		/**
		 * 6. Single post / page
		 */
		if ( $is_single ) {
			if ( ! $post_id ) {
				global $post;
				$post_id = $post->ID;
			}
			$post_type = get_post_type( $post_id );
			if ( 'post' === $post_type ) {
				// Built-in posts: use get_the_category() which respects primary category.
				$categories = get_the_category( $post_id );

				if ( ! empty( $categories ) ) {
					$cat       = $categories[0];
					$ancestors = array_reverse( get_ancestors( $cat->term_id, 'category' ) );

					foreach ( $ancestors as $ancestor_id ) {
						$ancestor = get_term( $ancestor_id, 'category' );

						if ( $ancestor && ! is_wp_error( $ancestor ) ) {
							$items[] = [
								'@type'    => 'ListItem',
								'position' => $position++,
								'name'     => $ancestor->name,
								'item'     => get_term_link( $ancestor ),
							];
						}
					}

					$items[] = [
						'@type'    => 'ListItem',
						'position' => $position++,
						'name'     => $cat->name,
						'item'     => get_term_link( $cat ),
					];
				}
			} else {
				// Any other post type: find the primary hierarchical taxonomy.
				$taxonomy = $this->get_primary_taxonomy( $post_type );

				if ( $taxonomy ) {
					$terms = get_the_terms( $post_id, $taxonomy );

					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$term      = $terms[0];
						$ancestors = array_reverse( get_ancestors( $term->term_id, $taxonomy ) );

						foreach ( $ancestors as $ancestor_id ) {
							$ancestor = get_term( $ancestor_id, $taxonomy );

							if ( $ancestor && ! is_wp_error( $ancestor ) ) {
								$items[] = [
									'@type'    => 'ListItem',
									'position' => $position++,
									'name'     => $ancestor->name,
									'item'     => get_term_link( $ancestor ),
								];
							}
						}

						$items[] = [
							'@type'    => 'ListItem',
							'position' => $position++,
							'name'     => $term->name,
							'item'     => get_term_link( $term ),
						];
					}
				} elseif ( is_post_type_hierarchical( $post_type ) ) {
					// Hierarchical post types without taxonomy: use parent posts (e.g. pages).
					$ancestors = array_reverse( get_ancestors( $post_id, $post_type ) );

					foreach ( $ancestors as $ancestor_id ) {
						$items[] = [
							'@type'    => 'ListItem',
							'position' => $position++,
							'name'     => html_entity_decode( get_the_title( $ancestor_id ), ENT_QUOTES, 'UTF-8' ),
							'item'     => get_permalink( $ancestor_id ),
						];
					}
				}
			}
			// Current post (last item — no 'item' URL per Google's spec).
			$title = get_the_title( $post_id );
			if ( empty( $title ) ) {
				$title = get_post_field( 'post_title', $post_id );
			}
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ),
				'item'     => get_permalink( $post_id ),
			];
		}

		// Remove any items with empty name or empty item URL.
		$items = array_values(
			array_filter(
				$items,
				function ( $item ) {
					$name = isset( $item['name'] ) ? trim( $item['name'] ) : '';
					$url  = isset( $item['item'] ) ? trim( $item['item'] ) : '';

					return '' !== $name && '' !== $url;
				}
			)
		);

		// Re-index positions sequentially after filtering.
		foreach ( $items as $index => &$item ) {
			$item['position'] = $index + 1;
		}
		unset( $item );

		// Build @id: use explicit permalink when post_id is available (REST-safe).
		if ( $post_id ) {
			$breadcrumb_id = trailingslashit( get_permalink( $post_id ) ) . '#breadcrumb';
		} else {
			$breadcrumb_id = $this->get_id_with_hash( 'breadcrumb' );
		}

		// Build schema.
		$breadcrumb = [
			'@type'           => 'BreadcrumbList',
			'@id'             => $breadcrumb_id,
			'itemListElement' => $items,
		];

		return apply_filters( 'rtseo_snippet_breadcrumb', $breadcrumb );
	}

	/**
	 * Get the primary hierarchical taxonomy for a post type.
	 *
	 * Uses known mappings for common plugins (e.g. product → product_cat),
	 * then falls back to the first public hierarchical taxonomy registered
	 * for the post type.
	 *
	 * @param  string $post_type  Post type slug.
	 *
	 * @return string|null Taxonomy slug or null if none found.
	 */
	private function get_primary_taxonomy( $post_type ) {
		$known = apply_filters(
			'rtrs_ai_breadcrumb_taxonomy_map',
			[
				'product'         => 'product_cat',
				'rtcl_listing'    => 'rtcl_category',
				'fluent-products' => 'product-categories',
			],
		);

		if ( isset( $known[ $post_type ] ) && taxonomy_exists( $known[ $post_type ] ) ) {
			return $known[ $post_type ];
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		foreach ( $taxonomies as $tax ) {
			if ( $tax->hierarchical && $tax->public ) {
				return $tax->name;
			}
		}

		return null;
	}

	/**
	 * Generate CollectionPage schema for archive pages.
	 *
	 * @return array|null
	 */
	public function generate_website_auto_schema() {
		// Website Schema.
		$WebSite          = [];
		$WebSite['@type'] = 'WebSite';
		$WebSite['@id']   = home_url( '#website' );
		$WebSite['url']   = trailingslashit( get_home_url() );
		// Site name & tagline.
		$WebSite['name'] = get_bloginfo( 'name' );
		$tagline         = get_bloginfo( 'description' );
		if ( ! empty( $tagline ) ) {
			$WebSite['alternateName'] = $tagline;
		}
		// Language code.
		$WebSite['inLanguage'] = get_bloginfo( 'language' );
		// Publisher (your Organization schema).
		$WebSite['publisher'] = [
			'@id' => $this->get_site_schema_id(),
		];

		// Add to graph.
		return apply_filters( 'rtseo_snippet_website', $WebSite );
	}

	/**
	 * Generate CollectionPage schema for archive pages.
	 *
	 * @return array|null
	 */
	public function generate_collectionpage_auto_schema() {
		// Only archive-like pages.
		if ( ! is_archive() && ! is_home() && ! ( function_exists( 'is_shop' ) && is_shop() ) ) {
			return [];
		}
		$page_url   = get_post_type_archive_link( get_post_type() );
		$collection = [
			'@type'      => 'CollectionPage',
			'@id'        => $this->get_id_with_hash( 'webpage' ),
			'url'        => trailingslashit( $page_url ),
			'name'       => $this->get_page_title(),
			'isPartOf'   => [
				'@id' => home_url( '#website' ),
			],
			'inLanguage' => get_bloginfo( 'language' ),
			'breadcrumb' => [
				'@id' => $this->get_id_with_hash( 'breadcrumb' ),
			],
		];

		return apply_filters( 'rtseo_snippet_collectionpage', $collection );
	}

	/**
	 * Determine the WebPage @type based on schema settings.
	 *
	 * Returns 'AboutPage' or 'ContactPage' if the current page
	 * matches the configured about/contact page, 'ItemPage' for
	 * product/software application pages, otherwise 'WebPage'.
	 *
	 * @param  array $all_schema_id List of schema IDs on the current page.
	 *
	 * @return string The schema @type value.
	 */
	private function get_webpage_type( $all_schema_id = [] ) {
		$meta_data = get_option( 'rtrs_schema_settings' );
		if ( ! empty( $meta_data['about_page'] ) && is_page( $meta_data['about_page'] ) ) {
			return 'AboutPage';
		}
		if ( ! empty( $meta_data['contact_page'] ) && is_page( $meta_data['contact_page'] ) ) {
			return 'ContactPage';
		}

		foreach ( $all_schema_id as $schema_id ) {
			if ( str_starts_with( $schema_id, 'product' ) || str_starts_with( $schema_id, 'software_app' ) ) {
				return 'ItemPage';
			}
		}

		return 'WebPage';
	}

	/**
	 * Generate WebPage schema (AboutPage/ContactPage when applicable).
	 *
	 * @param  array $all_schema_id  List of schema IDs for mainEntity.
	 *
	 * @return array|null
	 */
	public function generate_webpage_auto_schema( $all_schema_id = [] ) {
		$post_id = $this->post_id;
		// Webpage Schema.
		$webpage          = [];
		$webpage['@type'] = $this->get_webpage_type( $all_schema_id );
		$webpage['@id']   = $this->get_id_with_hash( 'webpage' );
		$webpage['url']   = $this->get_id_with_hash();
		// Title & description.
		$webpage['name']        = $this->get_page_title();
		$webpage['description'] = $this->get_page_description();
		// Language.
		$webpage['inLanguage'] = get_bloginfo( 'language' );
		// Website relation.
		$webpage['isPartOf'] = [
			'@id' => home_url( '#website' ),
		];
		// Primary image.
		if ( has_post_thumbnail( $post_id ) ) {
			$webpage['primaryImageOfPage'] = [
				'@type' => 'ImageObject',
				'@id'   => $this->get_id_with_hash( 'primaryImageOfPage' ),
				'url'   => get_the_post_thumbnail_url( $post_id, 'full' ),
			];
		}
		// Dates.
		$webpage['datePublished'] = get_the_date( DATE_W3C, $post_id );
		$webpage['dateModified']  = get_the_modified_date( DATE_W3C, $post_id );
		// Publisher (Organization / LocalBusiness).
		$webpage['publisher']  = [
			'@id' => $this->get_site_schema_id(),
		];
		$webpage['breadcrumb'] = [
			'@id' => $this->get_id_with_hash( 'breadcrumb' ),
		];

		// Add mainEntity for ItemPage (product/software_app pages). 'mainEntity' Will Hide the software schema, So dont use it
		if ( 'ItemPage' === $webpage['@type'] && ! empty( $all_schema_id ) ) {
			foreach ( $all_schema_id as $schema_id ) {
				if ( str_starts_with( $schema_id, 'product' ) || str_starts_with( $schema_id, 'software_app' ) ) {
					$webpage['mainEntity'] = [
						'@id' => $this->get_id_with_hash( $schema_id ),
					];
					break;
				}
			}
		}

		return apply_filters( 'rtseo_snippet_webpage', $webpage );
	}

	/**
	 * @return string
	 */
	public function get_site_schema_category() {
		$metaData = get_option( 'rtrs_schema_settings' );
		$helper   = new Functions();
		return ! empty( $metaData['site_category'] ) ? $helper->sanitizeOutPut( $metaData['site_category'] ) : 'localBusiness';
	}

	/**
	 * @return string
	 */
	public function get_site_schema_id() {
		$site_category = $this->get_site_schema_category();

		return home_url( '#' . strtolower( $site_category ) );
	}

	/**
	 * Google sitelink searchbox.
	 *
	 * @return mixed
	 */
	public function site_schema() {
		$metaData      = get_option( 'rtrs_schema_settings' );
		$site_category = $this->get_site_schema_category();
		if ( 'Person' === $site_category ) {
			$site_schema = $this->generate_site_person_schema();
		} else {
			$site_schema = $this->generate_local_business_and_organization_schema();
		}
		$schema_graph_list[] = apply_filters( 'rtseo_site_schema', $site_schema, $metaData );
		$schema_graph_list[] = $this->generate_website_auto_schema();

		return $schema_graph_list;
	}

	/**
	 * Generate Person schema site-wide.
	 *
	 * @return array
	 */
	public function generate_site_person_schema() {
		$helper   = new Functions();
		$metaData = get_option( 'rtrs_schema_settings' );
		$person   = [
			'@type' => 'Person',
			'@id'   => $this->get_site_schema_id(),
			'url'   => home_url(),
		];

		if ( ! empty( $metaData['name'] ) ) {
			$person['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}

		if ( ! empty( $metaData['alternateName'] ) ) {
			$person['alternateName'] = $helper->sanitizeOutPut( $metaData['alternateName'] );
		}

		if ( ! empty( $metaData['description'] ) ) {
			$person['description'] = $this->clean_schema_text( $metaData['description'] );
		}

		if ( ! empty( $metaData['image'] ) ) {
			$img             = $helper->imageInfo( absint( $metaData['image'] ) );
			$person['image'] = $helper->sanitizeOutPut( $img['url'], 'url' );
		}

		if ( ! empty( $metaData['sameAs'] ) ) {
			$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
			if ( ! empty( $sameAs ) ) {
				$person['sameAs'] = $sameAs;
			}
		} elseif ( $this->get_social() ) {
			$person['sameAs'] = $this->get_social();
		}

		if ( ! empty( $metaData['telephone'] ) ) {
			$person['telephone'] = $helper->sanitizeOutPut( $metaData['telephone'] );
		}
		if ( ! empty( $metaData['addresses'] ) ) {
			$addresses = [];
			$address_i = 1;
			foreach ( $metaData['addresses'] as $key => $address ) {
				if ( ! empty( $address['addressLocality'] ) || ! empty( $address['addressRegion'] )
					 || ! empty( $address['postalCode'] )
					 || ! empty( $address['streetAddress'] )
				) {
					if ( ! function_exists( 'rtrsp' ) && $address_i > 1 ) {
						break;
					}

					$addresses[] = [
						'@type'           => 'PostalAddress',
						'@id'             => $person['@id'] . '-address-' . $key,
						'addressLocality' => $helper->sanitizeOutPut( $address['addressLocality'] ?? '' ),
						'addressRegion'   => $helper->sanitizeOutPut( $address['addressRegion'] ?? '' ),
						'postalCode'      => $helper->sanitizeOutPut( $address['postalCode'] ?? '' ),
						'streetAddress'   => $helper->sanitizeOutPut( $address['streetAddress'] ?? '' ),
						'addressCountry'  => $helper->sanitizeOutPut( $address['addressCountry'] ?? '' ),
					];
					$address_i++;
				}
			}
			if ( ! empty( $addresses ) ) {
				$person['address'] = count( $addresses ) > 1 ? $addresses : $addresses[0];
			}
		}

		return apply_filters( 'rtseo_site_person_schema', $person );
	}

	/**
	 * Generate FAQPage schema for pages.
	 *
	 * @return array
	 */
	public function generate_local_business_and_organization_schema() {
		$metaData = get_option( 'rtrs_schema_settings' );
		$helper   = new Functions();
		$category = ! empty( $metaData['organization_category'] ) ? $helper->sanitizeOutPut( $metaData['organization_category'] ) : 'LocalBusiness';
		$helper   = new Functions();
		// Site link Searchbar.
		$schema_type    = Functions::getSchemaType( $category );
		$local_business = [
			'@type' => $schema_type,
			'@id'   => $this->get_site_schema_id(),
			'url'   => home_url( '/' ),
		];
		if ( ! empty( $metaData['name'] ) ) {
			$local_business['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['alternateName'] ) ) {
			$local_business['alternateName'] = $helper->sanitizeOutPut( $metaData['alternateName'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$local_business['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		if ( ! empty( $metaData['image'] ) ) {
			$img                       = $helper->imageInfo( absint( $metaData['image'] ) );
			$local_business['image'][] = $helper->sanitizeOutPut( $img['url'], 'url' );
		}
		if ( ! empty( $metaData['logo'] ) ) {
			$img                    = $helper->imageInfo( absint( $metaData['logo'] ) );
			$local_business['logo'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'] ?? 0,
				'width'  => $img['width'] ?? 0,
			];
		}
		if ( Functions::isLocalBusinessType( $category ) && ! empty( $metaData['priceRange'] ) ) {
			$local_business['priceRange'] = $helper->sanitizeOutPut( $metaData['priceRange'] );
		}
		if ( ! empty( $metaData['sameAs'] ) ) {
			$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
			if ( ! empty( $sameAs ) ) {
				$local_business['sameAs'] = $sameAs;
			}
		} elseif ( $this->get_social() ) {
			$local_business['sameAs'] = $this->get_social();
		}
		$contactPoints = [];
		if ( 'LocalBusiness' === $category && $this->corporate_contacts_types() ) {
			$contactPoints[] = $this->corporate_contacts_types();
		}

		if ( Functions::isFoodEstablishmentType( $category ) && ! empty( $metaData['servesCuisine'] ) ) {
			$local_business['servesCuisine'] = $helper->sanitizeOutPut( $metaData['servesCuisine'] );
		}

		if ( 'Restaurant' === $category ) {
			if ( ! empty( $metaData['menu'] ) ) {
				$local_business['menu'] = $helper->sanitizeOutPut( $metaData['menu'], 'url' );
				if ( isset( $metaData['acceptsReservations'] ) && 'yes' === $metaData['acceptsReservations'] ) {
					$local_business['acceptsReservations'] = 'True';
				}
			}
		}
		if ( ! empty( $metaData['addresses'] ) ) {
			$addresses = [];
			$address_i = 1;
			foreach ( $metaData['addresses'] as $key => $address ) {
				if ( ! empty( $address['addressLocality'] ) || ! empty( $address['addressRegion'] )
					 || ! empty( $address['postalCode'] )
					 || ! empty( $address['streetAddress'] )
				) {
					if ( ! function_exists( 'rtrsp' ) && $address_i > 1 ) {
						break;
					}

					$addresses[] = [
						'@type'           => 'PostalAddress',
						'@id'             => $local_business['@id'] . '-address-' . $key,
						'addressLocality' => $helper->sanitizeOutPut( $address['addressLocality'] ?? '' ),
						'addressRegion'   => $helper->sanitizeOutPut( $address['addressRegion'] ?? '' ),
						'postalCode'      => $helper->sanitizeOutPut( $address['postalCode'] ?? '' ),
						'streetAddress'   => $helper->sanitizeOutPut( $address['streetAddress'] ?? '' ),
						'addressCountry'  => $helper->sanitizeOutPut( $address['addressCountry'] ?? '' ),
					];
					$address_i++;
				}
			}
			if ( ! empty( $addresses ) && is_array( $addresses ) ) {
				if ( count( $addresses ) > 1 ) {
					$local_business['address'] = $addresses;
				} else {
					$local_business['address'] = $addresses[0];
				}
			}
		}
		$metaDataSubOrg = get_option( 'rtrs_schema_sub_organization_settings' );
		if ( function_exists( 'rtrsp' ) && ! empty( $metaDataSubOrg['sub_organization'] ) ) {
			$sub_organization = [];
			foreach ( $metaDataSubOrg['sub_organization'] as $index => $sub_org ) {
				if ( ! empty( $sub_org['name'] ) || ! empty( $sub_org['url'] ) ) {
					$sub_organization[] = [
						'@type' => 'Organization',
						'@id'   => $local_business['@id'] . '-suborg-' . $index,
						'name'  => $helper->sanitizeOutPut( $sub_org['name'] ),
						'url'   => $helper->sanitizeOutPut( $sub_org['url'] ),
					];
				}
			}

			if ( $sub_organization ) {
				$local_business['subOrganization'] = $sub_organization;
			}
		}

		if ( Functions::isLocalBusinessType( $category ) && ( ! empty( $metaData['latitude'] ) || ! empty( $metaData['longitude'] ) ) ) {
			$local_business['geo'] = [
				'@type'       => 'GeoCircle',
				'@id'         => $local_business['@id'] . '-geocircle',
				'geoMidpoint' => [
					'@type'     => 'GeoCoordinates',
					'latitude'  => $helper->sanitizeOutPut( $metaData['latitude'] ),
					'longitude' => $helper->sanitizeOutPut( $metaData['longitude'] ),
				],
				'geoRadius'   => ! empty( $metaData['radius'] ) ? absint( $metaData['radius'] ) : 50,
			];
		}

		if ( ! empty( $metaData['telephone'] ) ) {
			$local_business['telephone'] = $helper->sanitizeOutPut( $metaData['telephone'] );
		}

		if ( Functions::isLocalBusinessType( $category ) && ! empty( $metaData['openingHours'] ) && is_array( $metaData['openingHours'] ) ) {
			$opening_hours_specs = [];

			foreach ( $metaData['openingHours'] as $entry ) {
				$day    = ! empty( $entry['day'] ) ? $helper->sanitizeOutPut( $entry['day'] ) : '';
				$opens  = ! empty( $entry['opens'] ) ? $helper->sanitizeOutPut( $entry['opens'] ) : '';
				$closes = ! empty( $entry['closes'] ) ? $helper->sanitizeOutPut( $entry['closes'] ) : '';

				if ( $day ) {
					$opening_hours_specs[] = [
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => [ $day ],
						'opens'     => $opens,
						'closes'    => $closes,
					];
				}
			}
			if ( ! empty( $opening_hours_specs ) ) {
				$local_business['openingHoursSpecification'] = $opening_hours_specs;
			}
		}

		if ( ! empty( $metaData['contactPoint'] ) ) {
			foreach ( $metaData['contactPoint'] as $point ) {
				$contactData = [];
				if ( ! empty( $point['telephone'] ) ) {
					$contactData['telephone'] = $helper->sanitizeOutPut( $point['telephone'] );
				}
				if ( ! empty( $point['contactType'] ) ) {
					$contactData['contactType'] = $helper->sanitizeOutPut( $point['contactType'] );
				}
				if ( ! empty( $point['areaServed'] ) ) {
					$contactData['areaServed'] = $helper->sanitizeOutPut( $point['areaServed'] );
				}

				if ( ! empty( $point['language'] ) ) {
					$contactData['availableLanguage'] = array_map(
						'trim',
						explode( ',', $helper->sanitizeOutPut( $point['language'] ) ),
					);
				}
				if ( ! empty( $contactData ) ) {
					$contactPoints[] = [
						'@type' => 'ContactPoint',
					] + $contactData;
				}
			}
		}

		if ( ! empty( $contactPoints ) ) {
			$local_business['contactPoint'] = $contactPoints;
		}

		return apply_filters( 'rtseo_local_business_and_organization_schema', $local_business );
	}

	/**
	 * Get Get corporate contacts types array.
	 *
	 * @return array
	 * @since 1.0
	 */
	public function corporate_contacts_types() {
		$corporate_contacts = rtrs()->get_options( 'rtrs_schema_corporate_contacts_settings' );

		$contact_type = isset( $corporate_contacts['type'] ) ? esc_attr( $corporate_contacts['type'] ) : '';
		if ( $contact_type ) {
			// Remove dashes and replace it with a space.
			$contact_type = str_replace( '_', ' ', $contact_type );
			$contact      = [
				'@type'       => 'ContactPoint', // Required default value.
				'contactType' => $contact_type,
			];
			if ( ! empty( $corporate_contacts['telephone'] ) ) {
				$contact['telephone'] = esc_html( $corporate_contacts['telephone'] );
			}
			if ( ! empty( $corporate_contacts['url'] ) ) {
				$contact['url'] = esc_url( $corporate_contacts['url'] );
			}
			if ( ! empty( $corporate_contacts['email'] ) ) {
				$contact['email'] = esc_html( $corporate_contacts['email'] );
			}
			if ( ! empty( $corporate_contacts['contactOption'] ) ) {
				$contact['contactOption'] = esc_html( $corporate_contacts['contactOption'] );
			}
			if ( ! empty( $corporate_contacts['areaServed'] ) ) {
				$contact['areaServed'] = $corporate_contacts['areaServed'];
			}
			if ( ! empty( $corporate_contacts['availableLanguage'] ) ) {
				$contact['availableLanguage'] = $corporate_contacts['availableLanguage'];
			}
			$corporate_contacts = $contact;
		}

		return $corporate_contacts;
	}

	/**
	 * rich snippet generator.
	 *
	 * @return []
	 */
	public function rich_snippet() {
		$schema_graph_list = [];
		if ( ! is_singular() && ! is_admin() && ! wp_doing_ajax() ) {
			return $schema_graph_list;
		}
		$prefix  = 'rtrs_';
		$post_id = $this->post_id;
		$post    = get_post( $post_id );
		if ( ! $post_id ) {
			return $schema_graph_list;
		}
		$custom_snippet    = get_post_meta( $post_id, '_rtrs_custom_rich_snippet', true );
		$disable_generator = get_post_meta( $post_id, '_rtrs_disable_snippet_generator', true );
		if ( boolval( $disable_generator ) ) {
			return $schema_graph_list;
		}
		$all_schema_id = [];
		if ( $custom_snippet ) {
			$schemaCat = get_post_meta( $post_id, '_rtrs_rich_snippet_cat', false );
			foreach ( $schemaCat as $singleCat ) {
				$metaData = get_post_meta( $post_id, $prefix . $singleCat . '_schema', true );
				if ( empty( $metaData ) ) {
					continue;
				}
				foreach ( $metaData as $metaKey => $meta ) {
					if ( 'show' !== $meta['status'] ) {
						continue;
					}
					$meta['schema_id'] = $metaKey;

					/**
					 * Filter schema metadata before output generation.
					 *
					 * Used by PricingCollector to inject dynamic pricing for
					 * Product and SoftwareApplication schema types.
					 *
					 * @param array  $meta       Schema metadata array.
					 * @param string $singleCat  Schema category slug.
					 * @param int    $post_id    WordPress post ID.
					 */
					$meta = apply_filters( 'rtrs_schema_meta_before_output', $meta, $singleCat, $post_id );

					$all_schema_id[] = strtolower( $singleCat ) . $metaKey;
					$generatedSchema = $this->schemaOutput( $singleCat, $meta );
					foreach ( $generatedSchema as $generated ) {
						$schema_graph_list[] = $generated;
					}
				}
			}
		} else { // auto generate.
			$support_cat = $this->post_type_auto_schema_supported( $post->post_type );
			if ( ! empty( $support_cat['schema_type'] ) && 'yes' === ( $support_cat['auto_generate'] ?? '' ) ) {
				$schema_type = $support_cat['schema_type'];

				// Ensure schema_type is a string (could be array from legacy data).
				if ( is_array( $schema_type ) ) {
					$schema_type = reset( $schema_type );
				}

				if ( $schema_type && is_string( $schema_type ) ) {
					$all_schema_id[]     = strtolower( $schema_type ) . '0';
					$generatedAutoSchema = $this->autoSchemaOutput( $schema_type, $post_id );
					foreach ( $generatedAutoSchema as $auto_generated ) {
						$schema_graph_list[] = $auto_generated;
					}
				}
			}
		}

		$cullectionPage = $this->generate_collectionpage_auto_schema();
		if ( ! empty( $cullectionPage ) ) {
			$schema_graph_list[] = $cullectionPage;
		} else {
			$schema_graph_list[] = $this->generate_webpage_auto_schema( $all_schema_id );
		}

		return $schema_graph_list;
	}

	/**
	 * Check if a post type is supported for auto schema generation.
	 *
	 * Delegates to SchemaFns::getPostTypeAutoSchemaConfig() which handles
	 * settings lookup, caching, and ecommerce fallbacks.
	 *
	 * @param string|null $post_type The post type to check.
	 *
	 * @return array|false Post type settings or false if not supported.
	 */
	public function post_type_auto_schema_supported( $post_type = null ) {
		return SchemaFns::getPostTypeAutoSchemaConfig( $post_type );
	}

	/**
	 * @param $data
	 *
	 * @return string|null
	 */
	public function getJsonEncode( $data = [] ) {
		$html = '';
		if ( ! empty( $data ) && is_array( $data ) ) {
			$html .= '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . '</script>';
		}

		return $html;
	}

	/**
	 * schema output.
	 *
	 * @return array
	 */
	public function schemaOutput( $schemaCat, $metaData = [] ) {
		$html          = null;
		$output        = [];
		$schema_key_id = $metaData['schema_id'] ?? 0;
		if ( $schemaCat ) {
			$helper         = new Functions();
			$site_schema_id = $this->get_site_schema_id();
			switch ( $schemaCat ) {
				case 'breadcrumb':
					$output[] = $this->build_breadcrumb_schema( $metaData, $helper, $schema_key_id );
					break;
				case 'article':
					$output = array_merge( $output, $this->build_article_schema( $metaData, $helper, $schema_key_id ) );
					break;
				case 'news_article':
					$output = array_merge( $output, $this->build_news_article_schema( $metaData, $helper, $schema_key_id ) );
					break;
				case 'blog_posting':
					$output = array_merge( $output, $this->build_blog_posting_schema( $metaData, $helper, $schema_key_id ) );
					break;
				case 'event':
					$output = array_merge( $output, $this->build_event_schema( $metaData, $helper, $schema_key_id ) );
					break;

				case 'audio':
					$output[] = $this->build_audio_schema( $metaData, $helper, $schema_key_id );
					break;
				case 'video':
					$output[] = $this->build_video_schema( $metaData, $helper, $schema_key_id );
					break;

				case 'movie':
					$output = array_merge( $output, $this->build_movie_schema( $metaData, $helper, $schema_key_id ) );
					break;
				case 'music':
					$output[] = $this->build_music_schema( $metaData, $helper );
					break;

				case 'service':
					$output[] = $this->build_service_schema( $metaData, $helper );
					break;

				case 'review':
					$output[] = $this->build_review_schema( $metaData, $helper, $schema_key_id );
					break;

				case 'local_business':
					$output = array_merge( $output, $this->build_local_business_schema( $metaData, $helper, $schema_key_id ) );
					break;

				case 'faq':
					$output[] = $this->build_faq_faqpage_schema( $metaData, $helper, $schema_key_id );
					break;

				case 'question_answer':
					$output[] = $this->build_question_answer_schema( $metaData, $helper, $schema_key_id ); // Deprecated.
					break;

				case 'how_to':
					$output[] = $this->build_how_to_schema( $metaData, $helper, $schema_key_id );
					break;

				case 'about':
					$output[] = $this->build_about_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'contact':
					$output[] = $this->build_contact_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'person':
					$output[] = $this->build_person_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'mosque':
					$output[] = $this->build_mosque_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'church':
					$output[] = $this->build_church_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'hindutemple':
					$output[] = $this->build_hindutemple_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'buddhisttemple':
					$output[] = $this->build_buddhisttemple_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'tech_article':
					$output[] = $this->build_tech_article_schema( $metaData, $helper, $schema_key_id );
					break;

				case 'medical_webpage':
					$output[] = $this->build_medical_webpage_schema( $metaData, $helper, $schema_key_id, $site_schema_id );
					break;

				case 'web_page':
					$output[] = $this->build_web_page_schema( $metaData, $helper, $schema_key_id );
					break;

				case 'profile_page':
					$output[] = $this->build_profile_page_schema( $metaData, $helper, $schema_key_id );
					break;

				default:
			}
		}

		return apply_filters( 'rtseo_snippet_add_more_schema_output', $output, $schemaCat, $metaData, $this );
	}

	/**
	 * schema output.
	 *
	 * @return array
	 */
	public function autoSchemaOutput( $schemaCat, $post_id = null ) {
		$metaData          = [];
		$schema_graph_list = [];
		if ( ! $schemaCat ) {
			return $schema_graph_list;
		}
		$helper         = new Functions();
		$site_schema_id = $this->get_site_schema_id();
		$post           = get_post( $post_id );
		switch ( $schemaCat ) {
			case 'article':
				$schema_graph_list[] = $this->build_auto_article_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			case 'tech_article':
				$schema_graph_list[] = $this->build_auto_tech_article_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			case 'news_article':
				$schema_graph_list[] = $this->build_auto_news_article_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			case 'blog_posting':
				$schema_graph_list[] = $this->build_auto_blog_posting_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			case 'book':
				$schema_graph_list[] = $this->build_auto_book_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			case 'event':
				$schema_graph_list = array_merge( $schema_graph_list, $this->build_auto_event_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) );
				break;
			case 'video':
				$schema_graph_list = array_merge( $schema_graph_list, $this->build_auto_video_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) );
				break;
			case 'person':
				$schema_graph_list[] = $this->build_auto_person_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			case 'service':
				$schema_graph_list[] = $this->build_auto_service_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData );
				break;
			default:
				/**
				 * Fires for unhandled auto-schema categories.
				 *
				 * Allows third-party code to generate auto-schema output
				 * for custom schema types added via rtrs_rich_snippet_auto_cats.
				 *
				 * @param array     $schema_graph_list Schema items (passed by reference via filter return).
				 * @param string    $schemaCat         The schema category key.
				 * @param array     $metaData          Meta data array.
				 * @param \WP_Post  $post              The current post object.
				 * @param Schema    $this              The Schema model instance.
				 */
				$schema_graph_list = apply_filters( 'rtrs_auto_schema_output_custom_type', $schema_graph_list, $schemaCat, $metaData, $post, $this );
				break;
		}

		return apply_filters( 'rtseo_snippet_add_more_auto_schema_output', $schema_graph_list, $schemaCat, $metaData, $post, $this );
	}

	/**
	 * Build local business schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_local_business_schema( $metaData, $helper, $schema_key_id ) {
		$output         = [];
		$schema_id      = $this->get_id_with_hash( 'local_business' . $schema_key_id );
		$category       = ! empty( $metaData['category'] ) ? $helper->sanitizeOutPut( $metaData['category'] ) : 'LocalBusiness';
		$local_business = [
			'@id'   => $schema_id,
			'@type' => Functions::getSchemaType( $category ),
		];

		if ( ! empty( $metaData['name'] ) ) {
			$local_business['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}

		if ( ! empty( $metaData['description'] ) ) {
			$local_business['description'] = $this->clean_schema_text( $metaData['description'] );
		}

		if ( ! empty( $metaData['image'] ) ) {
			$imges = [];
			foreach ( $metaData['image'] as $img_single ) {
				if ( ! $img_single ) {
					continue;
				}
				$img     = $helper->imageInfo( absint( $img_single ) );
				$imges[] = $helper->sanitizeOutPut( $img['url'], 'url' );
			}
			$local_business['image'] = $imges;
		}

		if ( $category == 'Organization' && ! empty( $metaData['logo'] ) ) {
			$img                    = $helper->imageInfo( absint( $metaData['logo'] ) );
			$local_business['logo'] = $helper->sanitizeOutPut( $img['url'], 'url' );
		}

		if ( ( Functions::isLocalBusinessType( $category ) || Functions::isMedicalOrgType( $category ) ) && ! empty( $metaData['priceRange'] ) ) {
			$local_business['priceRange'] = $helper->sanitizeOutPut( $metaData['priceRange'] );
		}
		if ( $category == 'Restaurant' ) {
			if ( ! empty( $metaData['servesCuisine'] ) ) {
				$local_business['servesCuisine'] = $helper->sanitizeOutPut( $metaData['servesCuisine'] );
			}
			if ( isset( $metaData['menu_sections'] ) && is_array( $metaData['menu_sections'] ) ) {
				$local_business_menu_sections = [];
				foreach ( $metaData['menu_sections'] as $menu_sections_single ) {
					$menu_sections_single_schema = [
						'@type' => 'MenuSection',
					];

					if ( ! empty( $menu_sections_single['name'] ) ) {
						$menu_sections_single_schema['name'] = $helper->sanitizeOutPut( $menu_sections_single['name'] );
					}

					if ( ! empty( $menu_sections_single['desc'] ) ) {
						$menu_sections_single_schema['description'] = $this->clean_schema_text( $menu_sections_single['desc'] );
					}

					if ( ! empty( $menu_sections_single['url'] ) ) {
						$menu_sections_single_schema['url'] = $helper->sanitizeOutPut( $menu_sections_single['url'], 'url' );
					}

					if ( ! empty( $menu_sections_single['images'] ) ) {
						$menu_section_images = [];
						foreach ( $menu_sections_single['images'] as $image ) {
							$img                   = $helper->imageInfo( absint( $image ) );
							$menu_section_images[] = $helper->sanitizeOutPut( $img['url'], 'url' );
						}

						if ( $menu_section_images ) {
							$menu_sections_single_schema['image'] = $menu_section_images;
						}
					}

					if ( ! empty( $menu_sections_single['availabilityStarts'] ) || ! empty( $menu_sections_single['availabilityEnds'] ) ) {
						$menu_sections_single_schema['offers'] = [
							'@type'              => 'Offer',
							'availabilityStarts' => $helper->sanitizeOutPut( $menu_sections_single['availabilityStarts'] ),
							'availabilityEnds'   => $helper->sanitizeOutPut( $menu_sections_single['availabilityEnds'] ),
						];
					}

					if ( isset( $menu_sections_single['menu_items'] ) && is_array( $menu_sections_single['menu_items'] ) ) {
						$local_business_menu_sections_menu_items = [];
						foreach ( $menu_sections_single['menu_items'] as $menu_items_single ) {
							$menu_items_single_schema              = [
								'@type'       => 'MenuItem',
								'name'        => $menu_items_single['name'] ? $helper->sanitizeOutPut( $menu_items_single['name'] ) : null,
								'description' => $menu_items_single['desc'] ? $helper->sanitizeOutPut( $menu_items_single['desc'] ) : null,
							];
							$menu_items_single_schema['nutrition'] = [
								'@type' => 'NutritionInformation',
							];

							if ( $menu_items_single['calories'] ) {
								$menu_items_single_schema['nutrition']['calories'] = $helper->sanitizeOutPut( $menu_items_single['calories'] );
							}
							if ( $menu_items_single['fatContent'] ) {
								$menu_items_single_schema['nutrition']['fatContent'] = $helper->sanitizeOutPut( $menu_items_single['fatContent'] );
							}
							if ( $menu_items_single['fiberContent'] ) {
								$menu_items_single_schema['nutrition']['fiberContent'] = $helper->sanitizeOutPut( $menu_items_single['fiberContent'] );
							}
							if ( $menu_items_single['proteinContent'] ) {
								$menu_items_single_schema['nutrition']['proteinContent'] = $helper->sanitizeOutPut( $menu_items_single['proteinContent'] );
							}

							if ( $menu_items_single['suitableForDiet'] ) {
								$menu_items_single_schema['suitableForDiet'] = $helper->sanitizeOutPut( $menu_items_single['suitableForDiet'] );
							}

							array_push( $local_business_menu_sections_menu_items, $menu_items_single_schema );
						}
						$menu_sections_single_schema['hasMenuItem'] = $local_business_menu_sections_menu_items;
					}

					array_push( $local_business_menu_sections, $menu_sections_single_schema );
				}
				$local_business['hasMenu']['@type']          = 'Menu';
				$local_business['hasMenu']['hasMenuSection'] = $local_business_menu_sections;
			}
		}

		if ( ! empty( $metaData['address'][0]['addressLocality'] ) || ! empty( $metaData['address'][0]['addressRegion'] )
			 || ! empty( $metaData['address'][0]['postalCode'] )
			 || ! empty( $metaData['address'][0]['streetAddress'] )
		) {
			$local_business['address'] = [
				'@type'           => 'PostalAddress',
				'addressLocality' => $helper->sanitizeOutPut( $metaData['address'][0]['addressLocality'] ),
				'addressRegion'   => $helper->sanitizeOutPut( $metaData['address'][0]['addressRegion'] ),
				'postalCode'      => $helper->sanitizeOutPut( $metaData['address'][0]['postalCode'] ),
				'streetAddress'   => $helper->sanitizeOutPut( $metaData['address'][0]['streetAddress'] ),
				'addressCountry'  => $helper->sanitizeOutPut( $metaData['address'][0]['addressCountry'] ),
			];
		}

		if ( ! empty( $metaData['geo'][0]['latitude'] ) || ! empty( $metaData['geo'][0]['longitude'] ) ) {
			$local_business['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => $helper->sanitizeOutPut( $metaData['geo'][0]['latitude'] ),
				'longitude' => $helper->sanitizeOutPut( $metaData['geo'][0]['longitude'] ),
			];
		}

		if ( ! empty( $metaData['telephone'] ) ) {
			$local_business['telephone'] = $helper->sanitizeOutPut( $metaData['telephone'] );
		}

		if ( ! empty( $metaData['url'] ) ) {
			$local_business['url'] = $helper->sanitizeOutPut( $metaData['url'], 'url' );
		}

		if ( Functions::isLocalBusinessType( $category ) && isset( $metaData['opening_hours'] ) && is_array( $metaData['opening_hours'] ) ) {
			$local_business_opening_hours = [];
			foreach ( $metaData['opening_hours'] as $opening_hours_single ) {
				$opening_hours_single_schema = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $opening_hours_single['day'] ? $helper->sanitizeOutPut( $opening_hours_single['day'] ) : '',
					'opens'     => $opening_hours_single['opens'] ? $helper->sanitizeOutPut( $opening_hours_single['opens'] ) : '',
					'closes'    => $opening_hours_single['closes'] ? $helper->sanitizeOutPut( $opening_hours_single['closes'] ) : '',
				];
				array_push( $local_business_opening_hours, $opening_hours_single_schema );
			}
			$local_business['openingHoursSpecification'] = $local_business_opening_hours;
		}
		$local_business_review = [];

		if ( isset( $metaData['review_active'] ) && $metaData['review_active'] == 'show' ) {
			$local_business_review = [
				'@type' => 'Review',
			];

			if ( ! empty( $local_business['url'] ) ) {
				$type_for_id                  = is_array( $local_business['@type'] ) ? $local_business['@type'][0] : $local_business['@type'];
				$local_business_review['@id'] = $local_business['url'] . '#review-' . strtolower( $type_for_id ) . $schema_key_id;
				$local_business['review']     = [
					'@id' => $local_business_review['@id'],
				];
			}

			if ( isset( $metaData['review_datePublished'] ) && ! empty( $metaData['review_datePublished'] ) ) {
				$local_business_review['datePublished'] = $helper->sanitizeOutPut( $metaData['review_datePublished'] );
			}
			if ( isset( $metaData['review_body'] ) && ! empty( $metaData['review_body'] ) ) {
				$local_business_review['reviewBody'] = $this->clean_schema_text( $metaData['review_body'] );
			}

			unset( $local_business['@context'] );
			if ( isset( $local_business['description'] ) ) {
				$local_business_review['description'] = Functions::filter_content( $local_business['description'], 200 );
				unset( $local_business['description'] );
			}
			if ( isset( $metaData['review_sameAs'] ) && ! empty( $metaData['review_sameAs'] ) ) {
				$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['review_sameAs'] ) );
				if ( ! empty( $sameAs ) ) {
					$local_business['sameAs'] = $sameAs;
				}
			}

			if ( ! empty( $metaData['review_author'] ) ) {
				$local_business_review['author'] = [
					'@type' => 'Person',
					'name'  => $helper->sanitizeOutPut( $metaData['review_author'] ),
					'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['review_author'] ) ),
				];

				if ( isset( $metaData['review_author_sameAs'] ) && ! empty( $metaData['review_author_sameAs'] ) ) {
					$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['review_author_sameAs'] ) );
					if ( ! empty( $sameAs ) ) {
						$local_business_review['author']['sameAs'] = $sameAs;
					}
				}
			}
			if ( ! empty( $metaData['review_ratingValue'] ) ) {
				$local_business_review['reviewRating'] = [
					'@type'       => 'Rating',
					'ratingValue' => esc_attr( $metaData['review_ratingValue'] ),
				];
				if ( ! empty( $metaData['review_bestRating'] ) ) {
					$local_business_review['reviewRating']['bestRating'] = $helper->sanitizeOutPut( $metaData['review_bestRating'], 'number' );
				}
				if ( ! empty( $metaData['review_worstRating'] ) ) {
					$local_business_review['reviewRating']['worstRating'] = $helper->sanitizeOutPut( $metaData['review_worstRating'], 'number' );
				}
			}
		}

		$output[] = apply_filters( 'rtseo_snippet_local_business', $local_business, $metaData );
		if ( ! empty( $local_business_review ) ) {
			$output[] = apply_filters( 'rtseo_snippet_local_business_review', $local_business_review, $metaData );
		}

		return $output;
	}

	/**
	 * Build FAQ schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_faq_faqpage_schema( $metaData, $helper, $schema_key_id ) {
		$schema_id = $this->get_id_with_hash( 'faq' . $schema_key_id );
		$faqSchema = [
			'@type' => 'FAQPage',
			'@id'   => $schema_id,
		];

		if ( isset( $metaData['faqs'] ) && is_array( $metaData['faqs'] ) ) {
			$faqs_schema = [];
			foreach ( $metaData['faqs'] as $position => $faq_item ) {
				$faq_item_schema = [
					'@type'          => 'Question',
					'@id'            => $this->get_id_with_hash( 'question' . $position . $schema_key_id ),
					'name'           => $faq_item['ques'] ? $helper->sanitizeOutPut( $faq_item['ques'] ) : null,
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => isset( $faq_item['ans'] ) ? $this->clean_schema_text( $faq_item['ans'] ) : null,
					],
				];
				array_push( $faqs_schema, $faq_item_schema );
			}
			if ( count( $faqs_schema ) == 1 ) {
				$faqSchema['mainEntity'] = $faqs_schema[0];
			} else {
				$faqSchema['mainEntity'] = $faqs_schema;
			}
		}

		return apply_filters( 'rtseo_snippet_faq', $faqSchema, $metaData );
	}

	/**
	 * Build Question Answer schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_question_answer_schema( $metaData, $helper, $schema_key_id ) {
		$schema_id            = $this->get_id_with_hash( 'question_answer' . $schema_key_id );
		$questionAnswerSchema = [
			'@type' => 'QAPage',
			'@id'   => $schema_id,
		];

		$question = [
			'@type' => 'Question',
			'@id'   => $this->get_id_with_hash( 'question' . $schema_key_id ),
		];
		if ( ! empty( $metaData['name'] ) ) {
			$question['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['text'] ) ) {
			$question['text'] = $this->clean_schema_text( $metaData['text'] );
		}
		if ( ! empty( $metaData['answerCount'] ) ) {
			$question['answerCount'] = absint( $metaData['answerCount'] ?? 0 );
		}
		if ( ! empty( $metaData['upvoteCount'] ) ) {
			$question['upvoteCount'] = absint( $metaData['upvoteCount'] ?? 0 );
		}
		if ( ! empty( $metaData['dateCreated'] ) ) {
			$question['datePublished'] = $helper->sanitizeOutPut( $metaData['dateCreated'] );
		}
		if ( ! empty( $metaData['author'] ) ) {
			$question['author'] = [
				'@type' => 'Person',
				'url'   => $helper->sanitizeOutPut( ( $metaData['question_author_url'] ?? '#' ), 'url' ),
				'name'  => $helper->sanitizeOutPut( $metaData['author'] ),
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['author'] ) ),
			];
		}

		if ( isset( $metaData['answers'] ) && is_array( $metaData['answers'] ) ) {
			$acceptedAnswer  = [];
			$suggestedAnswer = [];
			foreach ( $metaData['answers'] as $position => $answer_item ) {
				$id                 = $this->get_id_with_hash( '#qapage-answer-' . $position . $schema_key_id );
				$answer_item_schema = [
					'@type'       => 'Answer',
					'@id'         => $id,
					'url'         => $id,
					'text'        => $answer_item['text'] ? $helper->sanitizeOutPut( $answer_item['text'] ) : null,
					'dateCreated' => $answer_item['dateCreated'] ? $helper->sanitizeOutPut( $answer_item['dateCreated'] ) : null,
					'upvoteCount' => absint( $answer_item['upvoteCount'] ?? 0 ),
					'author'      => [
						'@type' => 'Person',
						'url'   => $helper->sanitizeOutPut( ( $answer_item['author_url'] ?? '#' ), 'url' ),
						'name'  => isset( $answer_item['author'] ) ? $helper->sanitizeOutPut( $answer_item['author'] ) : null,
						'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['author'] ) ),
					],
				];
				if ( 'normal' === $answer_item['answerType'] ) {
					array_push( $suggestedAnswer, $answer_item_schema );
				} else {
					$acceptedAnswer = $answer_item_schema;
				}
				if ( $acceptedAnswer ) {
					$question['acceptedAnswer'] = $acceptedAnswer;
				}
				if ( $suggestedAnswer ) {
					$question['suggestedAnswer'] = $suggestedAnswer;
				}
			}
		}
		$questionAnswerSchema['mainEntity'] = $question;

		return apply_filters( 'rtseo_snippet_question_answer', $questionAnswerSchema, $metaData );
	}

	/**
	 * Build HowTo schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_how_to_schema( $metaData, $helper, $schema_key_id ) {
		$schema_id   = $this->get_id_with_hash( 'how_to' . $schema_key_id );
		$howToSchema = [
			'@type' => 'HowTo',
			'@id'   => $schema_id,
		];

		if ( ! empty( $metaData['name'] ) ) {
			$howToSchema['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$howToSchema['description'] = $this->clean_schema_text( $metaData['description'] );
		}

		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                  = $helper->imageInfo( $image_id );
			$howToSchema['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		if ( ! empty( $metaData['price'] ) ) {
			$howToSchema['estimatedCost'] = [
				'@type' => 'MonetaryAmount',
				'value' => $helper->sanitizeOutPut( $metaData['price'], 'number' ),
			];
			if ( ! empty( $metaData['priceCurrency'] ) ) {
				$howToSchema['estimatedCost']['currency'] = $helper->sanitizeOutPut( $metaData['priceCurrency'] );
			}
		}

		if ( isset( $metaData['supply'] ) && is_array( $metaData['supply'] ) ) {
			$how_to_supply = [];
			foreach ( $metaData['supply'] as $supply_single ) {
				$supply_single_schema = [
					'@type' => 'HowToSupply',
					'name'  => $supply_single['name'] ? $helper->sanitizeOutPut( $supply_single['name'] ) : null,
				];
				array_push( $how_to_supply, $supply_single_schema );
			}
			$howToSchema['supply'] = $how_to_supply;
		}

		if ( isset( $metaData['tool'] ) && is_array( $metaData['tool'] ) ) {
			$how_to_tool = [];
			foreach ( $metaData['tool'] as $tool_single ) {
				$tool_single_schema = [
					'@type' => 'HowToTool',
					'name'  => $tool_single['name'] ? $helper->sanitizeOutPut( $tool_single['name'] ) : null,
				];
				array_push( $how_to_tool, $tool_single_schema );
			}
			$howToSchema['tool'] = $how_to_tool;
		}
		$videID = $this->get_id_with_hash( 'howto-video' . $schema_key_id );
		if ( isset( $metaData['step'] ) && is_array( $metaData['step'] ) ) {
			$how_to_step = [];
			foreach ( $metaData['step'] as $step_single ) {
				$step_single_schema = [
					'@type' => 'HowToStep',
				];

				if ( ! empty( $step_single['name'] ) ) {
					$step_single_schema['name'] = $helper->sanitizeOutPut( $step_single['name'] );
				}

				if ( ! empty( $step_single['text'] ) ) {
					$step_single_schema['text'] = $this->clean_schema_text( $step_single['text'] );
				}

				if ( ! empty( $step_single['url'] ) ) {
					$step_single_schema['url'] = $helper->sanitizeOutPut( $step_single['url'], 'url' );
				}

				if ( ! empty( $step_single['image'] ) ) {
					$img                         = $helper->imageInfo( absint( $step_single['image'] ) );
					$step_single_schema['image'] = [
						'@type'  => 'ImageObject',
						'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
						'height' => $img['height'],
						'width'  => $img['width'],
					];
				}

				if ( ! empty( $step_single['clipId'] ) ) {
					$step_single_schema['video']     = [
						'@id' => $videID,
					];
					$step_single_schema['subjectOf'] = [
						'@id' => $videID . '-' . $helper->sanitizeOutPut( $step_single['clipId'] ),
					];
				}

				if ( isset( $step_single['direction'] ) && is_array( $step_single['direction'] ) ) {
					$how_to_step_direction = [];
					foreach ( $step_single['direction'] as $direction_single ) {
						$direction_single_schema = [
							'@type' => 'HowToDirection',
							'text'  => $direction_single['text'] ? $helper->sanitizeOutPut( $direction_single['text'] ) : null,
						];
						array_push( $how_to_step_direction, $direction_single_schema );
					}
					$step_single_schema['itemListElement'] = $how_to_step_direction;
				}

				array_push( $how_to_step, $step_single_schema );
			}
			$howToSchema['step'] = $how_to_step;
		}

		if ( isset( $metaData['video'] ) && is_array( $metaData['video'] ) ) {
			$how_to_video = [];
			foreach ( $metaData['video'] as $video_single ) {
				if ( $video_single['name'] && $video_single['contentUrl'] ) {
					$video_single_schema = [
						'@type'       => 'VideoObject',
						'@id'         => $videID,
						'name'        => $video_single['name'] ? $helper->sanitizeOutPut( $video_single['name'] ) : null,
						'description' => $video_single['description'] ? $helper->sanitizeOutPut( $video_single['description'] ) : null,
						'contentUrl'  => $video_single['contentUrl'] ? $helper->sanitizeOutPut( $video_single['contentUrl'] ) : null,
						'embedUrl'    => $video_single['embedUrl'] ? $helper->sanitizeOutPut( $video_single['embedUrl'] ) : null,
						'uploadDate'  => $video_single['uploadDate'] ? $helper->sanitizeOutPut( $video_single['uploadDate'] ) : null,
						'duration'    => $video_single['duration'] ? $helper->sanitizeOutPut( $video_single['duration'] ) : null,
					];
					if ( ! empty( $video_single['thumbnailUrl'] ) ) {
						$img                                 = $helper->imageInfo( absint( $video_single['thumbnailUrl'] ) );
						$video_single_schema['thumbnailUrl'] = $helper->sanitizeOutPut( $img['url'], 'url' );
					}

					$how_to_video = $video_single_schema;

					if ( isset( $video_single['clip'] ) && is_array( $video_single['clip'] ) ) {
						$how_to_video_clip = [];
						foreach ( $video_single['clip'] as $clip_single ) {
							$clip_single_schema = [
								'@type'       => 'Clip',
								'@id'         => $videID . '-' . ( $clip_single['id'] ? $helper->sanitizeOutPut( $clip_single['id'] ) : '' ),
								'name'        => $clip_single['name'] ? $helper->sanitizeOutPut( $clip_single['name'] ) : null,
								'startOffset' => $clip_single['startOffset'] ? absint( $clip_single['startOffset'] ) : null,
								'endOffset'   => $clip_single['endOffset'] ? absint( $clip_single['endOffset'] ) : null,
								'url'         => $clip_single['url'] ? $helper->sanitizeOutPut( $clip_single['url'], 'url' ) : null,
							];
							array_push( $how_to_video_clip, $clip_single_schema );
						}
						$how_to_video['hasPart'] = $how_to_video_clip;
					}
				}
			}
			if ( $how_to_video ) {
				$howToSchema['video'] = $how_to_video;
			}
		}

		if ( ! empty( $metaData['totalTime'] ) ) {
			$howToSchema['totalTime'] = $helper->sanitizeOutPut( $metaData['totalTime'] );
		}

		return apply_filters( 'rtseo_snippet_how_to', $howToSchema, $metaData );
	}

	/**
	 * Build About schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_about_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$schema_id   = $this->get_id_with_hash( 'about' . $schema_key_id );
		$aboutSchema = [
			'@type' => 'AboutPage',
			'@id'   => $schema_id,
		];

		if ( ! empty( $metaData['name'] ) ) {
			$aboutSchema['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$aboutSchema['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                  = $helper->imageInfo( $image_id );
			$aboutSchema['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$aboutSchema['url'] = get_permalink( $this->post_id );

		if ( isset( $metaData['sameAs'] ) && ! empty( $metaData['sameAs'] ) ) {
			$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
			if ( ! empty( $sameAs ) ) {
				$aboutSchema['sameAs'] = $sameAs;
			}
		}
		$aboutSchema['about']    = [
			'@id' => $site_schema_id,
		];
		$aboutSchema['isPartOf'] = [
			'@id' => home_url( '#website' ),
		];

		return apply_filters( 'rtseo_snippet_about', $aboutSchema, $metaData );
	}

	/**
	 * Build Contact schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_contact_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$schema_id     = $this->get_id_with_hash( 'contact' . $schema_key_id );
		$contactSchema = [
			'@type' => 'ContactPage',
			'@id'   => $schema_id,
		];

		if ( ! empty( $metaData['name'] ) ) {
			$contactSchema['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$contactSchema['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                    = $helper->imageInfo( $image_id );
			$contactSchema['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$contactSchema['url']      = get_permalink( $this->post_id );
		$contactSchema['about']    = [
			'@id' => $site_schema_id,
		];
		$contactSchema['isPartOf'] = [
			'@id' => home_url( '#website' ),
		];

		return apply_filters( 'rtseo_snippet_contact', $contactSchema, $metaData );
	}

	/**
	 * Build Person schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_person_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$schema_id    = $this->get_id_with_hash( 'person' . $schema_key_id );
		$personSchema = [
			'@type' => 'Person',
			'@id'   => $schema_id,
			'url'   => $this->get_id_with_hash(),
		];

		if ( ! empty( $metaData['name'] ) ) {
			$personSchema['@id']  = $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['name'] ) );
			$personSchema['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                   = $helper->imageInfo( $image_id );
			$personSchema['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		if ( ! empty( $metaData['telephone'] ) ) {
			$personSchema['telephone'] = $helper->sanitizeOutPut( $metaData['telephone'] );
		}
		if ( ! empty( $metaData['email'] ) ) {
			$personSchema['email'] = $helper->sanitizeOutPut( $metaData['email'] );
		}
		$personSchema['url'] = get_permalink( $this->post_id );
		if ( ! empty( $metaData['jobTitle'] ) ) {
			$personSchema['jobTitle'] = $helper->sanitizeOutPut( $metaData['jobTitle'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$personSchema['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		if ( ! empty( $metaData['birthPlace'] ) ) {
			$personSchema['birthPlace'] = $helper->sanitizeOutPut( $metaData['birthPlace'] );
		}
		if ( ! empty( $metaData['birthDate'] ) ) {
			$personSchema['birthDate'] = $helper->sanitizeOutPut( $metaData['birthDate'] );
		}
		if ( ! empty( $metaData['gender'] ) ) {
			$personSchema['gender'] = $helper->sanitizeOutPut( $metaData['gender'] );
		}
		if ( ! empty( $metaData['nationality'] ) ) {
			$personSchema['nationality'] = $helper->sanitizeOutPut( $metaData['nationality'] );
		}
		if ( isset( $metaData['sameAs'] ) && ! empty( $metaData['sameAs'] ) ) {
			$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
			if ( ! empty( $sameAs ) ) {
				$personSchema['sameAs'] = $sameAs;
			}
		}
		if ( ! empty( $metaData['addresses'] ) ) {
			$addresses = [];
			$address_i = 1;
			foreach ( $metaData['addresses'] as $address ) {
				if ( ! empty( $address['addressLocality'] ) || ! empty( $address['addressRegion'] )
					 || ! empty( $address['postalCode'] )
					 || ! empty( $address['streetAddress'] )
				) {
					if ( ! function_exists( 'rtrsp' ) && $address_i > 1 ) {
						break;
					}

					$addresses[] = [
						'@type'           => 'PostalAddress',
						'addressLocality' => $helper->sanitizeOutPut( $address['addressLocality'] ),
						'addressRegion'   => $helper->sanitizeOutPut( $address['addressRegion'] ),
						'postalCode'      => $helper->sanitizeOutPut( $address['postalCode'] ),
						'streetAddress'   => $helper->sanitizeOutPut( $address['streetAddress'] ),
						'addressCountry'  => $helper->sanitizeOutPut( $address['addressCountry'] ),
					];
					$address_i++;
				}
			}

			if ( $addresses ) {
				$personSchema['address'] = $addresses;
			}
		}

		$personSchema['worksFor'] = [ '@id' => $site_schema_id ];

		return apply_filters( 'rtseo_snippet_person', $personSchema, $metaData );
	}

	/**
	 * Build Mosque schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_mosque_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$schema_id       = $this->get_id_with_hash( 'mosque' . $schema_key_id );
		$mosque          = [];
		$mosque['@type'] = 'Mosque';
		$mosque['@id']   = $schema_id;
		$mosque['url']   = $this->get_id_with_hash();
		if ( ! empty( $metaData['name'] ) ) {
			$mosque['name'] = esc_html( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$mosque['description'] = esc_html( $metaData['description'] );
		}
		if ( ! empty( $metaData['capacity'] ) ) {
			$mosque['maximumAttendeeCapacity'] = absint( $metaData['capacity'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img             = $helper->imageInfo( $image_id );
			$mosque['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		if ( ! empty( $metaData['address'] ) || ! empty( $metaData['address'][0] ) ) {
			$mosque['address']['@type'] = 'PostalAddress';
			$mosque_address             = $metaData['address'][0];
			if ( ! empty( $mosque_address['address-country'] ) ) {
				$mosque['address']['addressCountry'] = esc_html( $mosque_address['address-country'] );
			}
			if ( ! empty( $mosque_address['address-locality'] ) ) {
				$mosque['address']['addressLocality'] = esc_html( $mosque_address['address-locality'] );
			}
			if ( ! empty( $mosque_address['address-region'] ) ) {
				$mosque['address']['addressRegion'] = esc_html( $mosque_address['address-region'] );
			}
			if ( ! empty( $mosque_address['postal-code'] ) ) {
				$mosque['address']['postalCode'] = esc_html( $mosque_address['postal-code'] );
			}
		}
		$mosque['mainEntityOfPage'] = [
			'@id' => $this->get_id_with_hash( 'webpage' ),
		];
		if ( ! empty( $metaData['sameAs'] ) ) {
			$mosque['sameAs'] = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
		}

		return apply_filters( 'rtseo_snippet_mosque', $mosque, $metaData );
	}

	/**
	 * Build Church schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_church_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$church          = [];
		$church['@type'] = 'Church';
		$schema_id       = $this->get_id_with_hash( 'church' . $schema_key_id );
		$church['@id']   = $schema_id;
		$church['url']   = $this->get_id_with_hash();
		if ( ! empty( $metaData['name'] ) ) {
			$church['name'] = esc_html( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$church['description'] = esc_html( $metaData['description'] );
		}
		if ( ! empty( $metaData['capacity'] ) ) {
			$church['maximumAttendeeCapacity'] = absint( $metaData['capacity'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img             = $helper->imageInfo( $image_id );
			$church['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		if ( ! empty( $metaData['address'] ) || ! empty( $metaData['address'][0] ) ) {
			$church['address']['@type'] = 'PostalAddress';
			$mosque_address             = $metaData['address'][0];
			if ( ! empty( $mosque_address['address-country'] ) ) {
				$church['address']['addressCountry'] = esc_html( $mosque_address['address-country'] );
			}
			if ( ! empty( $mosque_address['address-locality'] ) ) {
				$church['address']['addressLocality'] = esc_html( $mosque_address['address-locality'] );
			}
			if ( ! empty( $mosque_address['address-region'] ) ) {
				$church['address']['addressRegion'] = esc_html( $mosque_address['address-region'] );
			}
			if ( ! empty( $mosque_address['postal-code'] ) ) {
				$church['address']['postalCode'] = esc_html( $mosque_address['postal-code'] );
			}
		}
		$church['mainEntityOfPage'] = [
			'@id' => $this->get_id_with_hash( 'webpage' ),
		];
		if ( ! empty( $metaData['sameAs'] ) ) {
			$church['sameAs'] = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
		}

		return apply_filters( 'rtseo_snippet_church', $church, $metaData );
	}

	/**
	 * Build HinduTemple schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_hindutemple_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$hindutemple          = [];
		$hindutemple['@type'] = 'HinduTemple';
		$schema_id            = $this->get_id_with_hash( 'hindutemple' . $schema_key_id );
		$hindutemple['@id']   = $schema_id;
		$hindutemple['url']   = $this->get_id_with_hash();
		if ( ! empty( $metaData['name'] ) ) {
			$hindutemple['name'] = esc_html( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$hindutemple['description'] = esc_html( $metaData['description'] );
		}
		if ( ! empty( $metaData['capacity'] ) ) {
			$hindutemple['maximumAttendeeCapacity'] = absint( $metaData['capacity'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                  = $helper->imageInfo( $image_id );
			$hindutemple['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		if ( ! empty( $metaData['address'] ) || ! empty( $metaData['address'][0] ) ) {
			$hindutemple['address']['@type'] = 'PostalAddress';
			$mosque_address                  = $metaData['address'][0];
			if ( ! empty( $mosque_address['address-country'] ) ) {
				$hindutemple['address']['addressCountry'] = esc_html( $mosque_address['address-country'] );
			}
			if ( ! empty( $mosque_address['address-locality'] ) ) {
				$hindutemple['address']['addressLocality'] = esc_html( $mosque_address['address-locality'] );
			}
			if ( ! empty( $mosque_address['address-region'] ) ) {
				$hindutemple['address']['addressRegion'] = esc_html( $mosque_address['address-region'] );
			}
			if ( ! empty( $mosque_address['postal-code'] ) ) {
				$hindutemple['address']['postalCode'] = esc_html( $mosque_address['postal-code'] );
			}
		}
		$hindutemple['mainEntityOfPage'] = [
			'@id' => $this->get_id_with_hash( 'webpage' ),
		];
		if ( ! empty( $metaData['sameAs'] ) ) {
			$hindutemple['sameAs'] = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
		}

		return apply_filters( 'rtseo_snippet_hindutemple', $hindutemple, $metaData );
	}

	/**
	 * Build BuddhistTemple schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_buddhisttemple_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$buddhisttemple          = [];
		$buddhisttemple['@type'] = 'BuddhistTemple';
		$schema_id               = $this->get_id_with_hash( 'buddhisttemple' . $schema_key_id );
		$buddhisttemple['@id']   = $schema_id;
		$buddhisttemple['url']   = $this->get_id_with_hash();
		if ( ! empty( $metaData['name'] ) ) {
			$buddhisttemple['name'] = esc_html( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$buddhisttemple['description'] = esc_html( $metaData['description'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                     = $helper->imageInfo( $image_id );
			$buddhisttemple['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		if ( ! empty( $metaData['capacity'] ) ) {
			$buddhisttemple['maximumAttendeeCapacity'] = absint( $metaData['capacity'] );
		}
		if ( ! empty( $metaData['address'] ) || ! empty( $metaData['address'][0] ) ) {
			$buddhisttemple['address']['@type'] = 'PostalAddress';
			$mosque_address                     = $metaData['address'][0];
			if ( ! empty( $mosque_address['address-country'] ) ) {
				$buddhisttemple['address']['addressCountry'] = esc_html( $mosque_address['address-country'] );
			}
			if ( ! empty( $mosque_address['address-locality'] ) ) {
				$buddhisttemple['address']['addressLocality'] = esc_html( $mosque_address['address-locality'] );
			}
			if ( ! empty( $mosque_address['address-region'] ) ) {
				$buddhisttemple['address']['addressRegion'] = esc_html( $mosque_address['address-region'] );
			}
			if ( ! empty( $mosque_address['postal-code'] ) ) {
				$buddhisttemple['address']['postalCode'] = esc_html( $mosque_address['postal-code'] );
			}
		}
		$buddhisttemple['mainEntityOfPage'] = [
			'@id' => $this->get_id_with_hash( 'webpage' ),
		];
		if ( ! empty( $metaData['sameAs'] ) ) {
			$buddhisttemple['sameAs'] = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
		}

		return apply_filters( 'rtseo_snippet_buddhisttemple', $buddhisttemple, $metaData );
	}

	/**
	 * Build TechArticle schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_tech_article_schema( $metaData, $helper, $schema_key_id ) {
		$schema_id   = $this->get_id_with_hash( 'tech_article' . $schema_key_id );
		$techarticle = [
			'@type' => 'TechArticle',
			'@id'   => $schema_id,
		];
		if ( ! empty( $metaData['name'] ) ) {
			$techarticle['headline'] = $helper->sanitizeOutPut( $metaData['name'] );
		}

		$techarticle['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];

		if ( ! empty( $metaData['author'] ) ) {
			$techarticle['author']['name'] = $helper->sanitizeOutPut( $metaData['author'] );
			if ( ! empty( $metaData['author_type'] ) ) {
				$techarticle['author']['@type'] = $helper->sanitizeOutPut( $metaData['author_type'] );
			}
			if ( ! empty( $metaData['author_url'] ) ) {
				$techarticle['author']['url'] = $helper->sanitizeOutPut( $metaData['author_url'], 'url' );
			}
			if ( ! empty( $metaData['auth_description'] ) ) {
				$techarticle['author']['description'] = $helper->sanitizeOutPut( $metaData['auth_description'] );
			}
		} else {
			$post = get_post( $this->post_id );
			if ( $post && ! empty( $author_name = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
				$author_url            = trailingslashit( get_author_posts_url( $post->post_author ) );
				$techarticle['author'] = [
					'@type' => 'Person',
					'@id'   => $author_url . '#person-' . sanitize_title( $author_name ),
					'url'   => $author_url,
					'name'  => $helper->sanitizeOutPut( $author_name ),
				];
			}
		}

		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                  = $helper->imageInfo( $image_id );
			$techarticle['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$techarticle['datePublished'] = get_the_date( DATE_W3C, $this->post_id );
		$techarticle['dateModified']  = get_the_modified_date( DATE_W3C, $this->post_id );

		$techarticle['publisher'] = [
			'@id' => $this->get_site_schema_id(),
		];

		if ( ! empty( $metaData['description'] ) ) {
			$techarticle['description'] = $this->clean_schema_text( $metaData['description'] );
		} else {
			$techarticle['description'] = $this->clean_schema_text( get_the_excerpt( $this->post_id ) );
		}
		if ( ! empty( $metaData['articleBody'] ) ) {
			$techarticle['articleBody'] = $this->clean_schema_text( $metaData['articleBody'] );
		} else {
			$post_content = get_post_field( 'post_content', $this->post_id );
			if ( ! empty( $post_content ) ) {
				$techarticle['articleBody'] = $this->clean_schema_text( $post_content );
			}
		}
		if ( ! empty( $techarticle['articleBody'] ) ) {
			$techarticle['wordCount'] = str_word_count( wp_strip_all_tags( $techarticle['articleBody'] ) );
		}
		if ( ! empty( $metaData['keywords'] ) ) {
			$techarticle['keywords'] = $helper->sanitizeOutPut( $metaData['keywords'] );
		}

		return apply_filters( 'rtseo_snippet_tech_article', $techarticle, $metaData );
	}

	/**
	 * Build MedicalWebPage schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 * @param  string    $site_schema_id  Site schema ID.
	 *
	 * @return array
	 */
	private function build_medical_webpage_schema( $metaData, $helper, $schema_key_id, $site_schema_id ) {
		$schema_id       = $this->get_id_with_hash( 'medical_webpage' . $schema_key_id );
		$medical_webpage = [
			'@type' => 'MedicalWebPage',
			'@id'   => $schema_id,
			'url'   => $this->get_id_with_hash(),
		];
		if ( ! empty( $metaData['headline'] ) ) {
			$medical_webpage['headline'] = $helper->sanitizeOutPut( $metaData['headline'] );
		}

		if ( ! empty( $metaData['specialty_url'] ) ) {
			$medical_webpage['specialty'] = $helper->sanitizeOutPut( $metaData['specialty_url'], 'url' );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                      = $helper->imageInfo( $image_id );
			$medical_webpage['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		$medical_webpage['datePublished'] = get_the_date( DATE_W3C, $this->post_id );
		$medical_webpage['dateModified']  = get_the_modified_date( DATE_W3C, $this->post_id );

		if ( ! empty( $metaData['lastreviewed'] ) ) {
			$medical_webpage['lastReviewed'] = $helper->sanitizeOutPut( $metaData['lastreviewed'] );
		}

		if ( ! empty( $metaData['maincontentofpage'] ) ) {
			$medical_webpage['mainContentOfPage'] = $helper->sanitizeOutPut( $metaData['maincontentofpage'] );
		}
		if ( ! empty( $metaData['about'] ) ) {
			$medical_webpage['about']['@type'] = 'MedicalCondition';
			$medical_webpage['about']['name']  = $helper->sanitizeOutPut( $metaData['about'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$medical_webpage['description'] = $helper->sanitizeOutPut( $metaData['description'] );
		}
		if ( ! empty( $metaData['keywords'] ) ) {
			$medical_webpage['keywords'] = $helper->sanitizeOutPut( $metaData['keywords'] );
		}
		if ( ! empty( $metaData['medicalAudience'] ) ) {
			$medical_webpage['medicalAudience'] = $helper->sanitizeOutPut( $metaData['medicalAudience'] );
		}
		$medical_webpage['publisher'] = [
			'@id' => $site_schema_id,
		];

		return apply_filters( 'rtseo_snippet_medical_webpage', $medical_webpage, $metaData );
	}

	/**
	 * Build WebPage schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_web_page_schema( $metaData, $helper, $schema_key_id ) {
		$web_page_id            = $this->get_id_with_hash( 'webpage' );
		$web_page_schema        = [
			'@type' => 'WebPage',
			'@id'   => $web_page_id,
		];
		$web_page_schema['url'] = get_permalink( $this->post_id );

		if ( ! empty( $metaData['name'] ) ) {
			$web_page_schema['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}

		if ( ! empty( $metaData['description'] ) ) {
			$web_page_schema['description'] = $helper->sanitizeOutPut( $metaData['description'] );
		}

		if ( ! empty( $metaData['language'] ) ) {
			$web_page_schema['inLanguage'] = $helper->sanitizeOutPut( $metaData['language'] );
		}
		$web_page_schema['datePublished'] = get_the_date( DATE_W3C, $this->post_id );
		$web_page_schema['dateModified']  = get_the_modified_date( DATE_W3C, $this->post_id );
		$image_id                         = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                                   = $helper->imageInfo( $image_id );
			$web_page_schema['primaryImageOfPage'] = [
				'@type'  => 'ImageObject',
				'@id'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		if ( ! empty( $metaData['images'] ) ) {
			$imgs = [];
			foreach ( $metaData['images'] as $img ) {
				$img    = $helper->imageInfo( absint( $img['image'] ) );
				$imgs[] = [
					'@type'  => 'ImageObject',
					'@id'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
					'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
					'height' => $img['height'],
					'width'  => $img['width'],
				];
			}

			if ( ! empty( $imgs ) ) {
				$web_page_schema['image'] = $imgs;
			}
		}
		$web_page_schema['isPartOf']   = [
			'@id' => home_url( '#website' ),
		];
		$web_page_schema['breadcrumb'] = [
			'@id' => $this->get_id_with_hash( 'breadcrumb' . $schema_key_id ),
		];

		return apply_filters( 'rtseo_snippet_web_page', $web_page_schema, $metaData );
	}

	/**
	 * Build ProfilePage schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_profile_page_schema( $metaData, $helper, $schema_key_id ) {
		$profile_page = [];
		$mainEntity   = [];
		$schema_id    = $this->get_id_with_hash( 'profile_page' . $schema_key_id );
		if ( ! empty( $metaData['profileFor'] ) ) {
			if ( 'Person' === $metaData['profileFor'] ) {
				$mainEntity['@type'] = 'Person';
				if ( ! empty( $metaData['gender'] ) ) {
					$mainEntity['gender'] = $helper->sanitizeOutPut( $metaData['gender'] );
				}
			} elseif ( 'Organization' === $metaData['profileFor'] ) {
				$mainEntity['@type'] = 'Organization';
			}
		}
		if ( ! empty( $metaData['name'] ) ) {
			$mainEntity['@id']  = $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['name'] ) );
			$mainEntity['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['alternateName'] ) ) {
			$mainEntity['alternateName'] = $helper->sanitizeOutPut( $metaData['alternateName'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$mainEntity['description'] = $helper->sanitizeOutPut( $metaData['description'] );
		}
		if ( ! empty( $metaData['sameAs'] ) ) {
			$mainEntity['sameAs'] = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['sameAs'] ) );
		}

		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                 = $helper->imageInfo( $image_id );
			$mainEntity['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		if ( ! empty( $metaData['memberOfList'] ) ) {
			$memberof = [];
			foreach ( $metaData['memberOfList'] as $member ) {
				$mmbr = [
					'@type' => $member['type'] ?? 'Organization',
				];
				if ( ! empty( $member['name'] ) ) {
					$mmbr['name'] = $helper->sanitizeOutPut( $member['name'] );
				}
				$memberof[] = $mmbr;
			}
			if ( ! empty( $memberof ) ) {
				$mainEntity['memberOf'] = $memberof;
			}
		}

		if ( ! empty( $metaData['worksFor'] ) ) {
			$worksFor = [];
			foreach ( $metaData['worksFor'] as $details ) {
				$for = [
					'@type' => $details['type'] ?? 'Organization',
				];
				if ( ! empty( $details['name'] ) ) {
					$for['name'] = $helper->sanitizeOutPut( $details['name'] );
				}
				if ( ! empty( $details['url'] ) ) {
					$for['url'] = $helper->sanitizeOutPut( $details['url'], 'url' );
				}
				if ( ! empty( $details['logo'] ) ) {
					$img         = $helper->imageInfo( absint( $details['logo'] ) );
					$for['logo'] = [
						'@type'  => 'ImageObject',
						'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
						'height' => $img['height'],
						'width'  => $img['width'],
					];
				}
				if ( ! empty( $details['sameAs'] ) ) {
					$for['sameAs'] = Functions::get_same_as( $helper->sanitizeOutPut( $details['sameAs'] ) );
				}
				$department = [];
				if ( ! empty( $details['department_name'] ) ) {
					$department['name'] = $helper->sanitizeOutPut( $details['department_name'] );
				}
				if ( ! empty( $details['department_url'] ) ) {
					$department['url'] = $helper->sanitizeOutPut( $details['department_url'], 'url' );
				}
				if ( ! empty( $department ) ) {
					$for['department'] = [ '@type' => 'Organization' ] + $department;
				}

				$address = [];
				if ( ! empty( $details['streetAddress'] ) ) {
					$address['streetAddress'] = $helper->sanitizeOutPut( $details['streetAddress'] );
				}
				if ( ! empty( $details['addressLocality'] ) ) {
					$address['addressLocality'] = $helper->sanitizeOutPut( $details['addressLocality'] );
				}
				if ( ! empty( $details['region'] ) ) {
					$address['addressRegion'] = $helper->sanitizeOutPut( $details['region'] );
				}
				if ( ! empty( $details['postalCode'] ) ) {
					$address['postalCode'] = $helper->sanitizeOutPut( $details['postalCode'] );
				}
				if ( ! empty( $details['addressCountry'] ) ) {
					$address['addressCountry'] = $helper->sanitizeOutPut( $details['addressCountry'] );
				}
				if ( ! empty( $address ) ) {
					$for['address'] = [ '@type' => 'PostalAddress' ] + $address;
				}

				$worksFor[] = $for;
			}
			if ( ! empty( $worksFor ) ) {
				$mainEntity['worksFor'] = $worksFor;
			}
		}

		// End Main $mainEntity.
		$profile_page['dateCreated']  = get_the_date( DATE_W3C, $this->post_id );
		$profile_page['dateModified'] = get_the_modified_date( DATE_W3C, $this->post_id );
		if ( ! empty( $mainEntity ) ) {
			$profile_page['mainEntity'] = $mainEntity;
		}
		$profile_page = array_merge(
			[
				'@type' => 'ProfilePage',
				'@id'   => $schema_id,
				'url'   => $this->get_id_with_hash(),
			],
			$profile_page,
		);

		return apply_filters( 'rtseo_snippet_profile_page', $profile_page, $metaData );
	}

	/**
	 * Build review schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_review_schema( $metaData, $helper, $schema_key_id ) {
		$review = [
			'@type' => 'Review',
		];
		if ( ! empty( $metaData['itemName'] ) ) {
			$review['itemReviewed'] = [
				'@type' => 'product',
				'name'  => $helper->sanitizeOutPut( $metaData['itemName'] ),
			];
		}
		if ( ! empty( $metaData['ratingValue'] ) ) {
			$review['reviewRating'] = [
				'@type'       => 'Rating',
				'bestRating'  => absint( $metaData['bestRating'] ),
				'worstRating' => absint( $metaData['worstRating'] ),
				'ratingValue' => esc_attr( $metaData['ratingValue'] ),
			];
		}
		if ( ! empty( $metaData['name'] ) ) {
			$review['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['author'] ) ) {
			$review['author'] = [
				'@type' => 'Person',
				'name'  => $helper->sanitizeOutPut( $metaData['author'] ),
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['author'] ) ),
			];
		}
		if ( ! empty( $metaData['reviewBody'] ) ) {
			$review['reviewBody'] = $helper->sanitizeOutPut( $metaData['reviewBody'] );
		}
		if ( ! empty( $metaData['datePublished'] ) ) {
			$review['datePublished'] = $helper->sanitizeOutPut( $metaData['datePublished'] );
		}
		$review['publisher'] = [
			'@id' => $this->get_site_schema_id(),
		];

		return apply_filters( 'rtseo_snippet_review', $review, $metaData );
	}

	/**
	 * Build service schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 *
	 * @return array
	 */
	private function build_service_schema( $metaData, $helper ) {
		$permalink = get_permalink( $this->post_id );
		$schema_id = $permalink . '#service';
		$service   = [
			'@type' => 'Service',
			'@id'   => $schema_id,
			'url'   => $permalink,
		];
		if ( ! empty( $metaData['name'] ) ) {
			$service['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$service['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		if ( ! empty( $metaData['serviceType'] ) ) {
			$service['serviceType'] = $helper->sanitizeOutPut( $metaData['serviceType'] );
		}
		if ( ! empty( $metaData['award'] ) ) {
			$service['award'] = $helper->sanitizeOutPut( $metaData['award'] );
		}
		if ( ! empty( $metaData['category'] ) ) {
			$service['category'] = $helper->sanitizeOutPut( $metaData['category'] );
		}
		if ( ! empty( $metaData['additionalType'] ) ) {
			$service['additionalType'] = $helper->sanitizeOutPut( $metaData['additionalType'] );
		}
		if ( ! empty( $metaData['alternateName'] ) ) {
			$service['alternateName'] = $helper->sanitizeOutPut( $metaData['alternateName'] );
		}
		if ( ! empty( $metaData['provider_name'] ) ) {
			$service['provider'] = [
				'@type' => $metaData['provider_type'] ?? 'Organization',
				'name'  => $helper->sanitizeOutPut( $metaData['provider_name'] ),
				'url'   => $helper->sanitizeOutPut( $metaData['provider_url'], 'url' ),
			];
			if ( 'Person' === $service['provider']['@type'] ) {
				$service['provider']['sameAs'] = $service['provider']['url'];
				unset( $service['provider']['url'] );
			} elseif ( ! empty( $metaData['provider_slogan'] ) ) {
				$service['provider']['slogan'] = $metaData['provider_slogan'];
			}
		}
		if ( ! empty( $metaData['latitude'] ) && ! empty( $metaData['longitude'] ) ) {
			$service['areaServed'] = [
				'@type'       => 'GeoCircle',
				'geoMidpoint' => [
					'@type'     => 'GeoCoordinates',
					'latitude'  => $helper->sanitizeOutPut( $metaData['latitude'] ),
					'longitude' => $helper->sanitizeOutPut( $metaData['longitude'] ),
				],
			];
		}
		if ( ! empty( $metaData['geoRadius'] ) ) {
			$service['areaServed']['geoRadius'] = absint( $metaData['geoRadius'] );
		}

		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img = $helper->imageInfo( $image_id );
			if ( $img ) {
				$service['image'] = [
					'@type'       => 'ImageObject',
					'url'         => $helper->sanitizeOutPut( $img['url'], 'url' ),
					'description' => $helper->sanitizeOutPut( $img['title'] ),
					'height'      => $img['height'],
					'width'       => $img['width'],
				];
			}
		}
		$service['mainEntityOfPage'] = $permalink;

		return apply_filters( 'rtseo_snippet_service', $service, $metaData );
	}

	/**
	 * Build music schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 *
	 * @return array
	 */
	private function build_music_schema( $metaData, $helper ) {
		$music          = [];
		$music['@type'] = $helper->sanitizeOutPut( $metaData['musicType'] );
		if ( ! empty( $metaData['name'] ) ) {
			$music['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$music['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img            = $helper->imageInfo( $image_id );
			$music['image'] = $helper->sanitizeOutPut( $img['url'], 'url' );
		}
		if ( ! empty( $metaData['sameAs'] ) ) {
			$music['sameAs'] = $helper->sanitizeOutPut( $metaData['sameAs'], 'url' );
		}

		$music['url'] = get_permalink( $this->post_id );

		return apply_filters( 'rtseo_snippet_music', $music, $metaData );
	}

	/**
	 * Build movie schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_movie_schema( $metaData, $helper, $schema_key_id ) {
		$output    = [];
		$permalink = get_permalink( $this->post_id );
		$schema_id = $permalink . '#movie';
		$movie     = [
			'@type' => 'Movie',
			'@id'   => $schema_id,
			'url'   => $permalink,
		];

		if ( ! empty( $metaData['name'] ) ) {
			$movie['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$movie['description'] = $helper->sanitizeOutPut( $metaData['description'] );
		}
		if ( ! empty( $metaData['duration'] ) ) {
			$movie['duration'] = $helper->sanitizeOutPut( $metaData['duration'] );
		}
		if ( ! empty( $metaData['dateCreated'] ) ) {
			$movie['dateCreated'] = $helper->sanitizeOutPut( $metaData['dateCreated'] );
		}
		if ( ! empty( $metaData['trailer_url'] ) ) {
			$movie['trailer'] = [
				'@type'    => 'VideoObject',
				'embedUrl' => $helper->sanitizeOutPut( $metaData['trailer_url'] ),
			];
		}
		if ( ! empty( $metaData['trailer_title'] ) ) {
			$movie['trailer']['name'] = $helper->sanitizeOutPut( $metaData['trailer_title'] );
		}
		if ( ! empty( $metaData['trailer_description'] ) ) {
			$movie['trailer']['description'] = $helper->sanitizeOutPut( $metaData['trailer_description'] );
		}
		if ( ! empty( $metaData['trailer_uploadDate'] ) ) {
			$movie['trailer']['uploadDate'] = $helper->sanitizeOutPut( $metaData['trailer_uploadDate'] );
		}
		if ( ! empty( $metaData['trailer_thumbnailUrl'] ) ) {
			$movie['trailer']['thumbnailUrl'] = $helper->sanitizeOutPut( $metaData['trailer_thumbnailUrl'], 'url' );
		}

		if ( ! empty( $metaData['genre'] ) ) {
			$movie['genre'] = explode( ',', $helper->sanitizeOutPut( $metaData['genre'] ) );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img            = $helper->imageInfo( $image_id );
			$movie['image'] = $helper->sanitizeOutPut( $img['url'], 'url' );
		}

		if ( ! empty( $metaData['director'] ) ) {
			$movie['director'] = [
				'@type' => 'Person',
				'name'  => $helper->sanitizeOutPut( $metaData['director'] ),
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['director'] ) ),
			];
		}
		if ( ! empty( $metaData['author'] ) ) {
			$authorArray = explode(
				"\r\n",
				$this->clean_schema_text( $metaData['author'] ),
			);
			$author      = [];
			if ( ! empty( $authorArray ) && is_array( $authorArray ) && count( $authorArray ) ) {
				foreach ( $authorArray as $authorName ) {
					$author[] = [
						'@type' => 'Person',
						'name'  => $authorName,
						'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $authorName ) ),
					];
				}
			}
			$movie['creator'] = $author;
		}
		if ( ! empty( $metaData['actor'] ) ) {
			$actorArray = explode(
				"\r\n",
				$this->clean_schema_text( $metaData['actor'] ),
			);
			$actor      = [];
			if ( ! empty( $actorArray ) && is_array( $actorArray ) && count( $actorArray ) ) {
				foreach ( $actorArray as $actorName ) {
					$actor[] = [
						'@type' => 'Person',
						'name'  => $actorName,
						'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $actorName ) ),
					];
				}
			}
			$movie['actor'] = $actor;
		}

		if ( ! empty( $metaData['image'] ) ) {
			$img            = $helper->imageInfo( absint( $metaData['image'] ) );
			$movie['image'] = $helper->sanitizeOutPut( $img['url'], 'url' );
		}
		if ( ! empty( $metaData['aggregate_ratingValue'] ) ) {
			$movie['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => esc_attr( $metaData['aggregate_ratingValue'] ),
			];
			if ( ! empty( $metaData['aggregate_bestRating'] ) ) {
				$movie['aggregateRating']['bestRating'] = absint( $metaData['aggregate_bestRating'], 'number' );
			}
			if ( ! empty( $metaData['aggregate_worstRating'] ) ) {
				$movie['aggregateRating']['worstRating'] = absint( $metaData['aggregate_worstRating'], 'number' );
			}
			if ( ! empty( $metaData['aggregate_ratingCount'] ) ) {
				$movie['aggregateRating']['reviewCount'] = absint( $metaData['aggregate_ratingCount'], 'number' );
			}
		}
		$movie_review = [];
		if ( isset( $metaData['review_active'] ) && $metaData['review_active'] == 'show' ) {
			$movie_review = [
				'@type' => 'Review',
			];

			if ( ! empty( $movie['url'] ) ) {
				$type_for_id         = is_array( $movie['@type'] ) ? $movie['@type'][0] : $movie['@type'];
				$movie_review['@id'] = $movie['url'] . '#review-' . strtolower( $type_for_id ) . $schema_key_id;
				$movie['review']     = [
					'@id' => $movie_review['@id'],
				];
			}
			if ( isset( $metaData['review_datePublished'] ) && ! empty( $metaData['review_datePublished'] ) ) {
				$movie_review['datePublished'] = $helper->sanitizeOutPut( $metaData['review_datePublished'] );
			}
			if ( isset( $metaData['review_body'] ) && ! empty( $metaData['review_body'] ) ) {
				$movie_review['reviewBody'] = $this->clean_schema_text( $metaData['review_body'] );
			}

			if ( ! empty( $metaData['review_author'] ) ) {
				$movie_review['author'] = [
					'@type' => 'Person',
					'name'  => $helper->sanitizeOutPut( $metaData['review_author'] ),
					'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['review_author'] ) ),
				];

				if ( isset( $metaData['review_author_sameAs'] ) && ! empty( $metaData['review_author_sameAs'] ) ) {
					$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['review_author_sameAs'] ) );
					if ( ! empty( $sameAs ) ) {
						$movie_review['author']['sameAs'] = $sameAs;
					}
				}
			}
			if ( isset( $metaData['review_publisher'] ) && ! empty( $metaData['review_publisher'] ) ) {
				$movie_review['publisher'] = [
					'@type' => 'Organization',
					'name'  => $helper->sanitizeOutPut( $metaData['review_publisher'] ),
				];
				if ( isset( $metaData['review_publisherImage'] ) && ! empty( $metaData['review_publisherImage'] ) ) {
					$img                               = $helper->imageInfo( absint( $metaData['review_publisherImage'] ) );
					$movie_review['publisher']['logo'] = [
						'@type'       => 'ImageObject',
						'url'         => $helper->sanitizeOutPut( $img['url'], 'url' ),
						'description' => $helper->sanitizeOutPut( $img['title'] ),
						'height'      => $img['height'],
						'width'       => $img['width'],
					];
				}
			}
			if ( ! empty( $metaData['review_ratingValue'] ) ) {
				$movie_review['reviewRating'] = [
					'@type'       => 'Rating',
					'ratingValue' => esc_attr( $metaData['review_ratingValue'] ),
				];
				if ( ! empty( $metaData['aggregate_bestRating'] ) ) {
					$movie_review['reviewRating']['bestRating'] = $helper->sanitizeOutPut( $metaData['aggregate_bestRating'], 'number' );
				}
				if ( ! empty( $metaData['aggregate_worstRating'] ) ) {
					$movie_review['reviewRating']['worstRating'] = $helper->sanitizeOutPut( $metaData['aggregate_worstRating'], 'number' );
				}
			}
		}

		$output[] = apply_filters( 'rtseo_snippet_movie', $movie, $metaData );
		if ( ! empty( $movie_review ) ) {
			$output[] = apply_filters( 'rtseo_snippet_movie_review', $movie_review, $metaData );
		}

		return $output;
	}

	/**
	 * Build standalone video schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_video_schema( $metaData, $helper, $schema_key_id ) {
		$permalink = get_permalink( $this->post_id );
		$schema_id = $permalink . '#video';
		$video     = [
			'@type'            => 'VideoObject',
			'@id'              => $schema_id,
			'url'              => $permalink,
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => $this->get_id_with_hash( 'webpage' ),
			],
		];
		if ( ! empty( $metaData['name'] ) ) {
			$video['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$video['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		if ( ! empty( $metaData['thumbnailUrl'] ) ) {
			$video['thumbnailUrl'] = $helper->sanitizeOutPut( $metaData['thumbnailUrl'], 'url' );
		}
		if ( ! empty( $metaData['uploadDate'] ) ) {
			$video['uploadDate'] = $helper->sanitizeOutPut( $metaData['uploadDate'] );
		}
		if ( ! empty( $metaData['duration'] ) ) {
			$video['duration'] = $helper->sanitizeOutPut( $metaData['duration'] );
		}
		if ( ! empty( $metaData['contentUrl'] ) ) {
			$video['contentUrl'] = $helper->sanitizeOutPut( $metaData['contentUrl'], 'url' );
		}
		if ( ! empty( $metaData['embedUrl'] ) ) {
			$video['embedUrl'] = $helper->sanitizeOutPut( $metaData['embedUrl'], 'url' );
		}
		if ( ! empty( $metaData['inLanguage'] ) ) {
			$video['inLanguage'] = $helper->sanitizeOutPut( $metaData['inLanguage'] );
		}
		$video['isFamilyFriendly'] = 'yes' === ( $metaData['isFamilyFriendly'] ?? 'yes' );

		if ( ! empty( $metaData['creator_name'] ) ) {
			$video['publisher'] = [
				'@type' => $metaData['author_type'] ?? 'Person',
				'name'  => $helper->sanitizeOutPut( $metaData['creator_name'] ),
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['creator_name'] ) ),
			];
			if ( ! empty( $metaData['publisherImage'] ) ) {
				$img                        = $helper->imageInfo( absint( $metaData['publisherImage'] ) );
				$video['publisher']['logo'] = [
					'@type'       => 'ImageObject',
					'url'         => $helper->sanitizeOutPut( $img['url'], 'url' ),
					'description' => $helper->sanitizeOutPut( $img['title'] ),
					'height'      => $img['height'],
					'width'       => $img['width'],
				];
			}
		}
		if ( ! empty( $metaData['genre'] ) ) {
			$video['genre'] = $helper->sanitizeOutPut( $metaData['genre'] );
		}

		return apply_filters( 'rtseo_snippet_video', $video, $metaData );
	}

	/**
	 * Build standalone audio schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_audio_schema( $metaData, $helper, $schema_key_id ) {
		$permalink = get_permalink( $this->post_id );
		$audio_id  = $permalink . '#audio';
		$audio     = [
			'@type'            => 'AudioObject',
			'@id'              => $audio_id,
			'url'              => $permalink,
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => $this->get_id_with_hash( 'webpage' ),
			],
		];
		if ( ! empty( $metaData['name'] ) ) {
			$audio['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$audio['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		if ( ! empty( $metaData['duration'] ) ) {
			$audio['duration'] = $helper->sanitizeOutPut( $metaData['duration'] );
		}
		if ( ! empty( $metaData['contentUrl'] ) ) {
			$audio['contentUrl'] = $helper->sanitizeOutPut( $metaData['contentUrl'], 'url' );
		}
		if ( ! empty( $metaData['encodingFormat'] ) ) {
			$audio['encodingFormat'] = $helper->sanitizeOutPut( $metaData['encodingFormat'] );
		}
		if ( ! empty( $metaData['thumbnailUrl'] ) ) {
			$audio['thumbnailUrl'] = $helper->sanitizeOutPut( $metaData['thumbnailUrl'], 'url' );
		}
		if ( ! empty( $metaData['uploadDate'] ) ) {
			$audio['uploadDate'] = $helper->sanitizeOutPut( $metaData['uploadDate'] );
		}
		if ( ! empty( $metaData['inLanguage'] ) ) {
			$audio['inLanguage'] = $helper->sanitizeOutPut( $metaData['inLanguage'] );
		}

		$audio['isFamilyFriendly'] = 'yes' === ( $metaData['isFamilyFriendly'] ?? 'yes' );

		if ( ! empty( $metaData['creator_name'] ) ) {
			$audio['creator'] = [
				'@type' => $metaData['author_type'] ?? 'Person',
				'name'  => $helper->sanitizeOutPut( $metaData['creator_name'] ),
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['creator_name'] ) ),
			];
		}
		if ( ! empty( $metaData['genre'] ) ) {
			$audio['genre'] = $helper->sanitizeOutPut( $metaData['genre'] );
		}
		$audio['mainEntityOfPage'] = [
			'@id' => $this->get_id_with_hash( 'webpage' ),
		];

		return apply_filters( 'rtseo_snippet_audio', $audio, $metaData );
	}

	/**
	 * Build event schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_event_schema( $metaData, $helper, $schema_key_id ) {
		$output    = [];
		$permalink = get_permalink( $this->post_id );
		$event_id  = $permalink . '#event';
		$event     = [
			'@type' => 'Event',
			'@id'   => $event_id,
			'url'   => $permalink,
		];
		if ( ! empty( $metaData['name'] ) ) {
			$event['name'] = $helper->sanitizeOutPut( $metaData['name'] );
		}
		if ( ! empty( $metaData['startDate'] ) ) {
			$event['startDate'] = $helper->sanitizeOutPut( $metaData['startDate'] );
		}
		if ( ! empty( $metaData['endDate'] ) ) {
			$event['endDate'] = $helper->sanitizeOutPut( $metaData['endDate'] );
		}
		if ( ! empty( $metaData['description'] ) ) {
			$event['description'] = $this->clean_schema_text( $metaData['description'] );
		}
		if ( ! empty( $metaData['performerName'] ) ) {
			$event['performer'] = [
				'@type' => 'Person',
				'name'  => $helper->sanitizeOutPut( $metaData['performerName'] ),
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['performerName'] ) ),
			];
		}

		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img = $helper->imageInfo( $image_id );
			if ( $img ) {
				$event['image'] = [
					'@type'   => 'ImageObject',
					'url'     => $helper->sanitizeOutPut( $img['url'], 'url' ),
					'caption' => $helper->sanitizeOutPut( $img['caption'] ),
					'height'  => $img['height'],
					'width'   => $img['width'],
				];
			}
		}
		$address = [];

		if ( ! empty( $metaData['locationAddress'] ) ) {
			$address['streetAddress'] = $metaData['locationAddress'];
		}
		if ( ! empty( $metaData['addressLocality'] ) ) {
			$address['addressLocality'] = $metaData['addressLocality'];
		}
		if ( ! empty( $metaData['addressRegion'] ) ) {
			$address['addressRegion'] = $metaData['addressRegion'];
		}
		if ( ! empty( $metaData['postalCode'] ) ) {
			$address['postalCode'] = $metaData['postalCode'];
		}
		if ( ! empty( $metaData['addressCountry'] ) ) {
			$address['addressCountry'] = $metaData['addressCountry'];
		}
		if ( ! empty( $address ) ) {
			$event['location'] = [
				'@type'   => 'Place',
				'name'    => $helper->sanitizeOutPut( $metaData['locationName'] ?? '' ),
				'address' => [ '@type' => 'PostalAddress' ] + $address,
			];
		}
		if ( ! empty( $metaData['price'] ) ) {
			$event['offers'] = [
				'@type' => 'Offer',
				'price' => $helper->sanitizeOutPut( $metaData['price'] ),
			];
			if ( ! empty( $metaData['priceCurrency'] ) ) {
				$event['offers']['priceCurrency'] = $helper->sanitizeOutPut( $metaData['priceCurrency'] );
			}
			$event['offers']['url'] = $permalink;
			if ( ! empty( $metaData['availability'] ) ) {
				$event['offers']['availability'] = $helper->sanitizeOutPut( $metaData['availability'] );
			}
			if ( ! empty( $metaData['validFrom'] ) ) {
				$event['offers']['validFrom'] = $helper->sanitizeOutPut( $metaData['validFrom'] );
			}
			if ( ! empty( $metaData['priceValidUntil'] ) ) {
				$event['offers']['priceValidUntil'] = $helper->sanitizeOutPut( $metaData['priceValidUntil'] );
			}
		}
		if ( ! empty( $metaData['eventStatus'] ) ) {
			$event['eventStatus'] = $helper->sanitizeOutPut( $metaData['eventStatus'] );
		}
		if ( ! empty( $metaData['eventAttendanceMode'] ) ) {
			$event['eventAttendanceMode'] = $helper->sanitizeOutPut( $metaData['eventAttendanceMode'] );
		}
		if ( ! empty( $metaData['organizerName'] ) ) {
			$event['organizer'] = [
				'@type' => 'Organization',
				'name'  => $helper->sanitizeOutPut( $metaData['organizerName'] ),
				'url'   => $permalink,
			];
		}
		if ( ! empty( $metaData['aggregate_ratingValue'] ) ) {
			$event['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => esc_attr( $metaData['aggregate_ratingValue'] ),
				'bestRating'  => absint( $metaData['review_bestRating'] ?? 1 ),
				'worstRating' => absint( $metaData['review_worstRating'] ?? 1 ),
				'ratingCount' => absint( $metaData['aggregate_ratingCount'] ?? 1 ),
			];
		}
		$event_review = [];
		if ( isset( $metaData['review_active'] ) && $metaData['review_active'] == 'show' ) {
			$event_review = [
				'@type' => 'Review',
			];
			if ( ! empty( $event['url'] ) ) {
				$type_for_id         = is_array( $event['@type'] ) ? $event['@type'][0] : $event['@type'];
				$event_review['@id'] = $event['url'] . '#review-' . strtolower( $type_for_id ) . $schema_key_id;
				$event['review']     = [
					'@id' => $event_review['@id'],
				];
			}
			if ( isset( $metaData['review_datePublished'] ) && ! empty( $metaData['review_datePublished'] ) ) {
				$event_review['datePublished'] = $helper->sanitizeOutPut( $metaData['review_datePublished'] );
			}
			if ( isset( $metaData['review_body'] ) && ! empty( $metaData['review_body'] ) ) {
				$event_review['reviewBody'] = $this->clean_schema_text( $metaData['review_body'] );
			}

			if ( ! empty( $metaData['review_author'] ) ) {
				$event_review['author'] = [
					'@type' => 'Person',
					'name'  => $helper->sanitizeOutPut( $metaData['review_author'] ),
					'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['review_author'] ) ),
				];

				if ( isset( $metaData['review_author_sameAs'] ) && ! empty( $metaData['review_author_sameAs'] ) ) {
					$sameAs = Functions::get_same_as( $helper->sanitizeOutPut( $metaData['review_author_sameAs'] ) );
					if ( ! empty( $sameAs ) ) {
						$event_review['author']['sameAs'] = $sameAs;
					}
				}
			}

			if ( ! empty( $metaData['review_ratingValue'] ) ) {
				$event_review['reviewRating'] = [
					'@type'       => 'Rating',
					'ratingValue' => esc_attr( $metaData['review_ratingValue'] ),
				];
				if ( ! empty( $metaData['review_bestRating'] ) ) {
					$event_review['reviewRating']['bestRating'] = $helper->sanitizeOutPut( $metaData['review_bestRating'], 'number' );
				}
				if ( ! empty( $metaData['review_worstRating'] ) ) {
					$event_review['reviewRating']['worstRating'] = $helper->sanitizeOutPut( $metaData['review_worstRating'], 'number' );
				}
			}
		}
		$output[] = apply_filters( 'rtseo_snippet_event', $event, $metaData );
		if ( ! empty( $event_review ) ) {
			$output[] = apply_filters( 'rtseo_snippet_event_review', $event_review, $metaData );
		}

		return $output;
	}

	/**
	 * Build blog posting schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_blog_posting_schema( $metaData, $helper, $schema_key_id ) {
		$output      = [];
		$schema_id   = $this->get_id_with_hash( 'blog_posting' . $schema_key_id );
		$blogPosting = [
			'@type' => 'BlogPosting',
			'@id'   => $schema_id,
		];
		if ( ! empty( $metaData['headline'] ) ) {
			$blogPosting['headline'] = $helper->sanitizeOutPut( $metaData['headline'] );
		}
		if ( ! empty( $metaData['author'] ) ) {
			$blogPosting['author'] = [
				'@type' => 'Person',
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['author'] ) ),
				'name'  => $helper->sanitizeOutPut( $metaData['author'] ),
			];

			if ( ! empty( $metaData['author_url'] ) ) {
				$blogPosting['author']['url'] = $helper->sanitizeOutPut( $metaData['author_url'], 'url' );
			}
		} else {
			$post = get_post( $this->post_id );
			if ( $post && ! empty( $author_name = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
				$author_url            = trailingslashit( get_author_posts_url( $post->post_author ) );
				$blogPosting['author'] = [
					'@type' => 'Person',
					'@id'   => $author_url . '#person-' . sanitize_title( $author_name ),
					'url'   => $author_url,
					'name'  => $helper->sanitizeOutPut( $author_name ),
				];
			}
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                  = $helper->imageInfo( $image_id );
			$blogPosting['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$blogPosting['datePublished'] = get_the_date( DATE_W3C, $this->post_id );
		$blogPosting['dateModified']  = get_the_modified_date( DATE_W3C, $this->post_id );
		$blogPosting['publisher']     = [
			'@id' => $this->get_site_schema_id(),
		];
		if ( ! empty( $metaData['description'] ) ) {
			$blogPosting['description'] = $this->clean_schema_text( $metaData['description'] );
		} else {
			$blogPosting['description'] = $this->clean_schema_text( get_the_excerpt( $this->post_id ) );
		}

		if ( ! empty( $metaData['articleBody'] ) ) {
			$blogPosting['articleBody'] = $this->clean_schema_text( Functions::filter_content( $metaData['articleBody'], 500 ) );
		} else {
			$post_content = get_post_field( 'post_content', $this->post_id );
			if ( ! empty( $post_content ) ) {
				$blogPosting['articleBody'] = $this->clean_schema_text( $post_content );
			}
		}
		if ( ! empty( $blogPosting['articleBody'] ) ) {
			$blogPosting['wordCount'] = str_word_count( wp_strip_all_tags( $blogPosting['articleBody'] ) );
		}
		$blogPosting_video = $this->build_embedded_video_schema( $metaData, $helper, $blogPosting['@id'], 'blogpostingvideo-' . $schema_key_id );
		if ( ! empty( $blogPosting_video ) ) {
			$blogPosting['video'] = [
				'@id' => $blogPosting_video['@id'],
			];
		}
		$blogPosting_audio = $this->build_embedded_audio_schema( $metaData, $helper, $blogPosting['@id'], 'blogpostingaudio-' . $schema_key_id );
		if ( ! empty( $blogPosting_audio ) ) {
			$blogPosting['audio'] = [
				'@id' => $blogPosting_audio['@id'],
			];
		}
		$blogPosting['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];
		$output[]                        = apply_filters( 'rtseo_snippet_blog_posting', $blogPosting, $metaData );

		if ( $blogPosting_video ) {
			$output[] = apply_filters( 'rtseo_snippet_blog_posting_video', $blogPosting_video, $metaData );
		}
		if ( $blogPosting_audio ) {
			$output[] = apply_filters( 'rtseo_snippet_blog_posting_audio', $blogPosting_audio, $metaData );
		}

		return $output;
	}

	/**
	 * Build news article schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_news_article_schema( $metaData, $helper, $schema_key_id ) {
		$output      = [];
		$schema_id   = $this->get_id_with_hash( 'news_article' . $schema_key_id );
		$newsArticle = [
			'@type' => 'NewsArticle',
			'@id'   => $schema_id,
		];
		if ( ! empty( $metaData['headline'] ) ) {
			$newsArticle['headline'] = $helper->sanitizeOutPut( $metaData['headline'] );
		}

		if ( ! empty( $metaData['author'] ) ) {
			$newsArticle['author'] = [
				'@type' => 'Person',
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['author'] ) ),
				'name'  => $helper->sanitizeOutPut( $metaData['author'] ),
			];

			if ( ! empty( $metaData['author_url'] ) ) {
				$newsArticle['author']['url'] = $helper->sanitizeOutPut( $metaData['author_url'], 'url' );
			}
		} else {
			$post = get_post( $this->post_id );
			if ( $post && ! empty( $author_name = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
				$author_url            = trailingslashit( get_author_posts_url( $post->post_author ) );
				$newsArticle['author'] = [
					'@type' => 'Person',
					'@id'   => $author_url . '#person-' . sanitize_title( $author_name ),
					'url'   => $author_url,
					'name'  => $helper->sanitizeOutPut( $author_name ),
				];
			}
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img                  = $helper->imageInfo( $image_id );
			$newsArticle['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$newsArticle['datePublished'] = get_the_date( DATE_W3C, $this->post_id );
		$newsArticle['dateModified']  = get_the_modified_date( DATE_W3C, $this->post_id );

		$newsArticle['publisher'] = [
			'@id' => $this->get_site_schema_id(),
		];

		if ( ! empty( $metaData['description'] ) ) {
			$newsArticle['description'] = $this->clean_schema_text( $metaData['description'] );
		} else {
			$newsArticle['description'] = $this->clean_schema_text( get_the_excerpt( $this->post_id ) );
		}

		if ( ! empty( $metaData['articleBody'] ) ) {
			$newsArticle['articleBody'] = $this->clean_schema_text( Functions::filter_content( $metaData['articleBody'], 500 ) );
		} else {
			$post_content = get_post_field( 'post_content', $this->post_id );
			if ( ! empty( $post_content ) ) {
				$newsArticle['articleBody'] = $this->clean_schema_text( $post_content );
			}
		}
		if ( ! empty( $newsArticle['articleBody'] ) ) {
			$newsArticle['wordCount'] = str_word_count( wp_strip_all_tags( $newsArticle['articleBody'] ) );
		}
		$newsArticle_video = $this->build_embedded_video_schema( $metaData, $helper, $newsArticle['@id'], 'newsarticlevideo-' . $schema_key_id );
		if ( ! empty( $newsArticle_video ) ) {
			$newsArticle['video'] = [
				'@id' => $newsArticle_video['@id'],
			];
		}
		$newsArticle_audio = $this->build_embedded_audio_schema( $metaData, $helper, $newsArticle['@id'], 'newsarticleaudio-' . $schema_key_id );
		if ( ! empty( $newsArticle_audio ) ) {
			$newsArticle['audio'] = [
				'@id' => $newsArticle_audio['@id'],
			];
		}

		$newsArticle['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];

		$output[] = apply_filters( 'rtseo_snippet_news_article', $newsArticle, $metaData );
		if ( ! empty( $newsArticle_video ) ) {
			$output[] = apply_filters( 'rtseo_snippet_news_article_video', $newsArticle_video, $metaData );
		}
		if ( ! empty( $newsArticle_audio ) ) {
			$output[] = apply_filters( 'rtseo_snippet_news_article_audio', $newsArticle_audio, $metaData );
		}

		return $output;
	}

	/**
	 * Build article schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_article_schema( $metaData, $helper, $schema_key_id ) {
		$output    = [];
		$schema_id = $this->get_id_with_hash( 'article' . $schema_key_id );
		$article   = [
			'@type' => 'Article',
			'@id'   => $schema_id,
		];
		if ( ! empty( $metaData['headline'] ) ) {
			$article['headline'] = $helper->sanitizeOutPut( $metaData['headline'] );
		}
		if ( ! empty( $metaData['author'] ) ) {
			$article['author'] = [
				'@type' => 'Person',
				'@id'   => $this->get_id_with_hash( 'person-' . sanitize_title( $metaData['author'] ) ),
				'name'  => $helper->sanitizeOutPut( $metaData['author'] ),
			];

			if ( ! empty( $metaData['author_url'] ) ) {
				$article['author']['url'] = $helper->sanitizeOutPut( $metaData['author_url'], 'url' );
			}
		} else {
			$post = get_post( $this->post_id );
			if ( $post && ! empty( $author_name = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
				$author_url        = trailingslashit( get_author_posts_url( $post->post_author ) );
				$article['author'] = [
					'@type' => 'Person',
					'@id'   => $author_url . '#person-' . sanitize_title( $author_name ),
					'url'   => $author_url,
					'name'  => $helper->sanitizeOutPut( $author_name ),
				];
			}
		}
		$article['publisher'] = [
			'@id' => $this->get_site_schema_id(),
		];

		if ( ! empty( $metaData['alternativeHeadline'] ) ) {
			$article['alternativeHeadline'] = $helper->sanitizeOutPut( $metaData['alternativeHeadline'] );
		}
		$image_id = get_post_thumbnail_id( $this->post_id );
		if ( $image_id ) {
			$img              = $helper->imageInfo( $image_id );
			$article['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$article['datePublished'] = get_the_date( DATE_W3C, $this->post_id );
		$article['dateModified']  = get_the_modified_date( DATE_W3C, $this->post_id );
		if ( ! empty( $metaData['description'] ) ) {
			$article['description'] = $this->clean_schema_text( $metaData['description'] );
		} else {
			$article['description'] = $this->clean_schema_text( get_the_excerpt( $this->post_id ) );
		}

		if ( ! empty( $metaData['articleBody'] ) ) {
			$article['articleBody'] = $this->clean_schema_text( $metaData['articleBody'] );
		} else {
			$post_content = get_post_field( 'post_content', $this->post_id );
			if ( ! empty( $post_content ) ) {
				$article['articleBody'] = $this->clean_schema_text( $post_content );
			}
		}
		if ( ! empty( $article['articleBody'] ) ) {
			$article['wordCount'] = str_word_count( wp_strip_all_tags( $article['articleBody'] ) );
		}
		$article_video = $this->build_embedded_video_schema( $metaData, $helper, $article['@id'], 'articlevideo-' . $schema_key_id );
		if ( ! empty( $article_video ) ) {
			$article['video'] = [
				'@id' => $article_video['@id'],
			];
		}
		$article_audio = $this->build_embedded_audio_schema( $metaData, $helper, $article['@id'], 'articleaudio-' . $schema_key_id );
		if ( ! empty( $article_audio ) ) {
			$article['audio'] = [
				'@id' => $article_audio['@id'],
			];
		}
		$article['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];
		$output[]                    = apply_filters( 'rtseo_snippet_article', $article, $metaData );
		if ( ! empty( $article_video ) ) {
			$output[] = apply_filters( 'rtseo_snippet_article_video', $article_video, $metaData );
		}
		if ( ! empty( $article_audio ) ) {
			$output[] = apply_filters( 'rtseo_snippet_article_audio', $article_audio, $metaData );
		}

		return $output;
	}

	/**
	 * Build embedded video schema for article types.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $parent_id  Parent schema ID for isPartOf.
	 * @param  string    $video_id_prefix  Optional video @id prefix (null to omit @id).
	 *
	 * @return array Empty array if no valid video, otherwise VideoObject schema.
	 */
	private function build_embedded_video_schema( $metaData, $helper, $parent_id, $video_id_prefix ) {
		$video_schema = [];
		if ( isset( $metaData['video'] ) && is_array( $metaData['video'] ) ) {
			foreach ( $metaData['video'] as $key => $video_single ) {
				if ( $video_single['name'] && ( $video_single['embedUrl'] || $video_single['contentUrl'] ) ) {
					$video_single_schema = [
						'@type'       => 'VideoObject',
						'@id'         => $this->get_id_with_hash( $video_id_prefix . $key ),
						'isPartOf'    => [
							'@id' => $parent_id,
						],
						'name'        => $video_single['name'] ? $helper->sanitizeOutPut( $video_single['name'] ) : null,
						'description' => $video_single['description'] ? $helper->sanitizeOutPut( $video_single['description'] ) : null,
						'uploadDate'  => $video_single['uploadDate'] ? $helper->sanitizeOutPut( $video_single['uploadDate'] ) : null,
						'duration'    => $video_single['duration'] ? $helper->sanitizeOutPut( $video_single['duration'] ) : null,
					];
					if ( ! empty( $video_single['embedUrl'] ) ) {
						$video_single_schema['embedUrl'] = $helper->sanitizeOutPut( $video_single['embedUrl'] );
					}
					if ( ! empty( $video_single['contentUrl'] ) ) {
						$video_single_schema['contentUrl'] = $helper->sanitizeOutPut( $video_single['contentUrl'] );
					}
					if ( ! empty( $video_single['thumbnailUrl'] ) ) {
						$img                                 = $helper->imageInfo( absint( $video_single['thumbnailUrl'] ) );
						$video_single_schema['thumbnailUrl'] = $helper->sanitizeOutPut( $img['url'], 'url' );
					}
					$video_schema = $video_single_schema;
				}
			}
		}

		return $video_schema;
	}

	/**
	 * Build embedded audio schema for article types.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $parent_id  Parent schema ID for isPartOf.
	 * @param  string    $audio_id_prefix  Audio @id prefix.
	 *
	 * @return array Empty array if no valid audio, otherwise AudioObject schema.
	 */
	private function build_embedded_audio_schema( $metaData, $helper, $parent_id, $audio_id_prefix ) {
		$audio_schema = [];
		if ( isset( $metaData['audio'] ) && is_array( $metaData['audio'] ) ) {
			foreach ( $metaData['audio'] as $key => $audio_single ) {
				if ( $audio_single['name'] && $audio_single['contentUrl'] ) {
					$audio_single_schema = [
						'@type'          => 'AudioObject',
						'@id'            => $this->get_id_with_hash( $audio_id_prefix . $key ),
						'isPartOf'       => [
							'@id' => $parent_id,
						],
						'name'           => $audio_single['name'] ? $helper->sanitizeOutPut( $audio_single['name'] ) : null,
						'description'    => $audio_single['description'] ? $helper->sanitizeOutPut( $audio_single['description'] ) : null,
						'duration'       => $audio_single['duration'] ? $helper->sanitizeOutPut( $audio_single['duration'] ) : null,
						'contentUrl'     => $audio_single['contentUrl'] ? $helper->sanitizeOutPut( $audio_single['contentUrl'] ) : null,
						'encodingFormat' => $audio_single['encodingFormat'] ? $helper->sanitizeOutPut( $audio_single['encodingFormat'] ) : null,
					];
					$audio_schema        = $audio_single_schema;
				}
			}
		}

		return $audio_schema;
	}

	/**
	 * Build breadcrumb schema.
	 *
	 * @param  array     $metaData  Meta data.
	 * @param  Functions $helper  Helper instance.
	 * @param  int       $schema_key_id  Schema key ID.
	 *
	 * @return array
	 */
	private function build_breadcrumb_schema( $metaData, $helper, $schema_key_id ) {
		$schema_id = $this->get_id_with_hash( 'breadcrumb' . $schema_key_id );
		if ( ! empty( $metaData['url'] ) ) {
			$schema_id = $helper->sanitizeOutPut( $metaData['url'] ) . '#breadcrumb';
		}
		$breadcrumbSchema = [
			'@type' => 'BreadcrumbList',
			'@id'   => $schema_id,
		];
		if ( isset( $metaData['items'] ) && is_array( $metaData['items'] ) ) {
			$breadcrumbs_schema = [];
			$pos                = 0;
			foreach ( $metaData['items'] as $breadcrumb_item ) {
				$name = ! empty( $breadcrumb_item['name'] )
					? trim( html_entity_decode( wp_strip_all_tags( $breadcrumb_item['name'] ), ENT_QUOTES, 'UTF-8' ) )
					: '';
				$url  = ! empty( $breadcrumb_item['item'] )
					? trim( $helper->sanitizeOutPut( $breadcrumb_item['item'] ) )
					: '';

				// Skip items with empty name or URL.
				if ( '' === $name || '' === $url ) {
					continue;
				}

				$pos++;
				$breadcrumbs_schema[] = [
					'@type'    => 'ListItem',
					'position' => $pos,
					'name'     => $name,
					'item'     => $url,
				];
			}

			$breadcrumbSchema['itemListElement'] = $breadcrumbs_schema;
		}

		return apply_filters( 'rtseo_snippet_breadcrumb', $breadcrumbSchema, $metaData );
	}
	/**
	 * Get clean plain-text for schema output.
	 *
	 * Strips HTML, decodes entities, and normalizes whitespace so JSON-LD
	 * output never contains &amp;amp; or stray \r\n gaps.
	 *
	 * @param string $text Raw text (excerpt or content).
	 * @param int    $max_len Maximum character length.
	 *
	 * @return string
	 */
	private function clean_schema_text( $text, $max_len = 0 ) {
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( $max_len > 0 ) {
			$text = mb_substr( $text, 0, $max_len );
		}

		return $text;
	}
	/**
	 * Build auto Article schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_article_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$article = [
			'@type' => 'Article',
			'@id'   => $this->get_id_with_hash( $schemaCat . '0' ),
		];
		if ( ! empty( $post->post_title ) ) {
			$article['headline'] = $helper->sanitizeOutPut( $post->post_title );
		}
		$post_content = $this->clean_schema_text( $post->post_content );
		if ( ! empty( $post->post_excerpt ) ) {
			$article['description'] = $this->clean_schema_text( $post->post_excerpt );
		} else {
			if ( $post_content ) {
				$article['description'] = substr( $post_content, 0, 250 );
			}
		}
		if ( ! empty( $author = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
			$author_url        = trailingslashit( get_author_posts_url( $post->post_author ) );
			$article['author'] = [
				'@type' => 'Person',
				'@id'   => $author_url . '#person-' . sanitize_title( $author ),
				'url'   => $author_url,
				'name'  => $helper->sanitizeOutPut( $author ),
			];
		}
		if ( ! empty( $image_id = get_post_thumbnail_id( $post->ID ) ) ) {
			$img              = $helper->imageInfo( absint( $image_id ) );
			$article['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		$article['datePublished'] = get_the_date( DATE_W3C, $post_id );
		$article['dateModified']  = get_the_modified_date( DATE_W3C, $post_id );

		if ( ! empty( $post->post_content ) ) {
			$article['articleBody'] = $post_content;
			$article['wordCount']   = str_word_count( $post_content );
		}
		$article['publisher']        = [
			'@id' => $site_schema_id,
		];
		$article['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];
		return apply_filters( 'rtseo_auto_schema_snippet_article', $article, $metaData );
	}

	/**
	 * Build auto TechArticle schema.
	 *
	 * @param  string    $schemaCat      Schema category.
	 * @param  \WP_Post  $post           Post object.
	 * @param  int       $post_id        Post ID.
	 * @param  Functions $helper         Helper instance.
	 * @param  string    $site_schema_id Site schema ID.
	 * @param  array     $metaData       Meta data.
	 *
	 * @return array
	 */
	private function build_auto_tech_article_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$techArticle = [
			'@type' => 'TechArticle',
			'@id'   => $this->get_id_with_hash( $schemaCat . '0' ),
		];

		if ( ! empty( $post->post_title ) ) {
			$techArticle['headline'] = $helper->sanitizeOutPut( $post->post_title );
		}
		$post_content = $this->clean_schema_text( $post->post_content );
		if ( ! empty( $author = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
			$author_url            = trailingslashit( get_author_posts_url( $post->post_author ) );
			$techArticle['author'] = [
				'@type' => 'Person',
				'@id'   => $author_url . '#person-' . sanitize_title( $author ),
				'url'   => $author_url,
				'name'  => $helper->sanitizeOutPut( $author ),
			];
		}

		if ( ! empty( $image_id = get_post_thumbnail_id( $post->ID ) ) ) {
			$img                  = $helper->imageInfo( absint( $image_id ) );
			$techArticle['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}

		$techArticle['datePublished'] = get_the_date( DATE_W3C, $post_id );
		$techArticle['dateModified']  = get_the_modified_date( DATE_W3C, $post_id );

		$raw_excerpt = ! empty( $post->post_excerpt ) ? $post->post_excerpt : $post_content;
		if ( $raw_excerpt ) {
			$techArticle['description'] = $this->clean_schema_text( $raw_excerpt, 250 );
		}

		if ( ! empty( $post->post_content ) ) {
			$techArticle['articleBody'] = $post_content;
			$techArticle['wordCount']   = str_word_count( $post_content );
		}

		// TechArticle-specific: extract tags as keywords.
		$tags = get_the_tags( $post_id );
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			$techArticle['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
		}

		$techArticle['publisher']        = [
			'@id' => $site_schema_id,
		];
		$techArticle['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];
		return apply_filters( 'rtseo_auto_schema_snippet_tech_article', $techArticle, $metaData );
	}

	/**
	 * Build auto NewsArticle schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_news_article_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$newsArticle = [
			'@type' => 'NewsArticle',
			'@id'   => $this->get_id_with_hash( $schemaCat . '0' ),
		];
		if ( ! empty( $post->post_title ) ) {
			$newsArticle['headline'] = $helper->sanitizeOutPut( $post->post_title );
		}
		$post_content = $this->clean_schema_text( $post->post_content );
		if ( ! empty( $post->post_excerpt ) ) {
			$newsArticle['description'] = $this->clean_schema_text( $post->post_excerpt );
		} else {
			if ( $post_content ) {
				$newsArticle['description'] = substr( $post_content, 0, 250 );
			}
		}
		if ( ! empty( $author = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
			$author_url            = trailingslashit( get_author_posts_url( $post->post_author ) );
			$newsArticle['author'] = [
				'@type' => 'Person',
				'@id'   => $author_url . '#person-' . sanitize_title( $author ),
				'url'   => $author_url,
				'name'  => $helper->sanitizeOutPut( $author ),
			];
		}
		if ( ! empty( $image_id = get_post_thumbnail_id( $post->ID ) ) ) {
			$img                  = $helper->imageInfo( absint( $image_id ) );
			$newsArticle['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$newsArticle['datePublished'] = get_the_date( DATE_W3C, $post_id );
		$newsArticle['dateModified']  = get_the_modified_date( DATE_W3C, $post_id );
		if ( ! empty( $post->post_content ) ) {
			$newsArticle['articleBody'] = $post_content;
			$newsArticle['wordCount']   = str_word_count( $post_content );
		}
		$newsArticle['publisher']        = [
			'@id' => $site_schema_id,
		];
		$newsArticle['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];

		return apply_filters( 'rtseo_auto_schema_snippet_news_article', $newsArticle, $metaData );
	}

	/**
	 * Build auto BlogPosting schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_blog_posting_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$blogPosting = [
			'@type' => 'BlogPosting',
			'@id'   => $this->get_id_with_hash( $schemaCat . '0' ),
		];
		if ( ! empty( $post->post_title ) ) {
			$blogPosting['headline'] = $helper->sanitizeOutPut( $post->post_title );
		}
		$post_content = $this->clean_schema_text( $post->post_content );
		$raw_excerpt = ! empty( $post->post_excerpt )
			? wp_strip_all_tags( $post->post_excerpt )
			: ( $post_content ? $post_content : '' );
		if ( $raw_excerpt ) {
			$raw_excerpt = html_entity_decode( $raw_excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$raw_excerpt = preg_replace( '/\s+/', ' ', trim( $raw_excerpt ) );
			$blogPosting['description'] = mb_substr( $raw_excerpt, 0, 250 );
		}
		if ( ! empty( $author = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
			$author_url            = trailingslashit( get_author_posts_url( $post->post_author ) );
			$blogPosting['author'] = [
				'@type' => 'Person',
				'@id'   => $author_url . '#person-' . sanitize_title( $author ),
				'url'   => $author_url,
				'name'  => $helper->sanitizeOutPut( $author ),
			];
		}
		if ( ! empty( $image_id = get_post_thumbnail_id( $post->ID ) ) ) {
			$img                  = $helper->imageInfo( absint( $image_id ) );
			$blogPosting['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$blogPosting['datePublished'] = get_the_date( DATE_W3C, $post_id );
		$blogPosting['dateModified']  = get_the_modified_date( DATE_W3C, $post_id );
		if ( ! empty( $post->post_content ) ) {
			$blogPosting['articleBody'] = $post_content;
			$blogPosting['wordCount']   = str_word_count( $post_content );
		}
		$blogPosting['publisher']        = [
			'@id' => $site_schema_id,
		];
		$blogPosting['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];
		return apply_filters( 'rtseo_auto_schema_snippet_blog_posting', $blogPosting, $metaData );
	}

	/**
	 * Build auto Book schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_book_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$schema_id = $this->get_id_with_hash( $schemaCat . '0' );
		$book      = [
			'@type'         => 'Book',
			'@id'           => $schema_id,
			'name'          => get_the_title(),
			'url'           => get_permalink(),
			'publisher'     => [
				'@id' => $site_schema_id,
			],
			'datePublished' => $helper->sanitizeOutPut( get_the_date( 'c' ) ),
		];
		if ( ! empty( $author = get_the_author_meta( 'display_name', $post->post_author ) ) ) {
			$author_url     = trailingslashit( get_author_posts_url( $post->post_author ) );
			$book['author'] = [
				'@type' => 'Person',
				'@id'   => $author_url . '#person-' . sanitize_title( $author ),
				'url'   => $author_url,
				'name'  => $helper->sanitizeOutPut( $author ),
			];
		}
		// Optional but recommended for Rich Results.
		if ( ! empty( $image_id = get_post_thumbnail_id( $post->ID ) ) ) {
			$img           = $helper->imageInfo( absint( $image_id ) );
			$book['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		$book['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];

		return apply_filters( 'rtseo_auto_schema_snippet_book', $book, $metaData );
	}

	/**
	 * Build auto Event schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_event_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$schema_graph_list = [];
		$schema_id         = $this->get_id_with_hash( $schemaCat . '0' );
		$event             = [
			'@type'               => 'Event',
			'@id'                 => $schema_id,
			'name'                => get_the_title(),
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'eventStatus'         => 'https://schema.org/EventScheduled',
		];
		$event['location'] = [
			'@id' => $site_schema_id,
		];
		$image_id          = get_post_thumbnail_id( $post->ID );
		if ( ! empty( $image_id ) ) {
			$img            = $helper->imageInfo( absint( $image_id ) );
			$event['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		// Ensure The Events Calendar is active.
		if ( function_exists( 'tribe_get_start_date' ) ) {
			$event['startDate'] = tribe_get_start_date( $this->post_id, false, 'c' );
			$event['endDate']   = tribe_get_end_date( $this->post_id, false, 'c' );
		}
		$event['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];
		$event                     = apply_filters( 'rtseo_auto_schema_snippet_event', $event, $metaData );
		if ( ! empty( $event['startDate'] ) ) {
			$schema_graph_list[] = $event;
		}

		return $schema_graph_list;
	}

	/**
	 * Build auto Video schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_video_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$schema_graph_list = [];
		$video_url         = '';
		// 1. Check for <video> tag in content
		if ( preg_match( '/<video.*?src=["\'](.*?)["\'].*?>/i', $post->post_content, $matches ) ) {
			$video_url = $matches[1];
		}
		$duration = 'PT1M0S';
		// 2. Check for first iframe (YouTube / Vimeo)
		if ( empty( $video_url ) && preg_match( '/<iframe.*?src=["\'](.*?)["\'].*?<\/iframe>/i', $post->post_content, $matches ) ) {
			$video_url = $matches[1];
			if ( preg_match( '/duration=["\']([\d:.]+)["\']/', $matches[0], $dur_match ) ) {
				$duration = $dur_match[1]; // format HH:MM:SS or MM:SS.
			}
		}
		// 3. Fallback to featured video URL if your theme provides it
		if ( empty( $video_url ) && function_exists( 'get_post_video_url' ) ) {
			$video_url = get_post_video_url( $post_id ); // example helper.
		}
		if ( ! empty( $video_url ) ) {
			$video_schema = [
				'@type'            => 'VideoObject',
				'@id'              => $this->get_id_with_hash( $schemaCat . '0' ),
				'name'             => get_the_title( $post_id ),
				'description'      => ! empty( $post->post_excerpt )
					? $this->clean_schema_text( $post->post_excerpt, 250 )
					: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ),
				'contentUrl'       => esc_url( $video_url ),
				'embedUrl'         => esc_url( $video_url ),
				'uploadDate'       => get_the_date( DATE_W3C, $post_id ),
				'publisher'        => [
					'@id' => $site_schema_id,
				],
				'mainEntityOfPage' => [
					'@type' => 'WebPage',
					'@id'   => $this->get_id_with_hash( 'webpage' ),
				],
			];
			// Thumbnail: featured image if exists.
			if ( $image_id = get_post_thumbnail_id( $post_id ) ) {
				$img = $helper->imageInfo( absint( $image_id ) );
				if ( $img && ! empty( $img['url'] ) ) {
					$video_schema['thumbnailUrl'] = $img['url'];
				}
			}
			// Add duration if detected
			if ( ! empty( $duration ) ) {
				// Convert HH:MM:SS or MM:SS to ISO 8601 duration
				$parts = explode( ':', $duration );
				if ( count( $parts ) === 3 ) {
					$video_schema['duration'] = 'PT' . intval( $parts[0] ) . 'H' . intval( $parts[1] ) . 'M' . intval( $parts[2] ) . 'S';
				} elseif ( count( $parts ) === 2 ) {
					$video_schema['duration'] = 'PT' . intval( $parts[0] ) . 'M' . intval( $parts[1] ) . 'S';
				} else {
					$video_schema['duration'] = $duration;
				}
			}
			$schema_graph_list[] = apply_filters( 'rtseo_auto_schema_snippet_video', $video_schema, $metaData );
		}

		return $schema_graph_list;
	}

	/**
	 * Build auto Person schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_person_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$person_schema = [
			'@type'    => 'Person',
			'@id'      => $this->get_id_with_hash( $schemaCat . '0' ),
			'name'     => $helper->sanitizeOutPut( $post->post_title ),
			'worksFor' => [
				'@id' => $site_schema_id,
			],
		];
		// Optional: description / content.
		if ( ! empty( $post->post_excerpt ) ) {
			$person_schema['description'] = $this->clean_schema_text( $post->post_excerpt, 250 );
		} elseif ( ! empty( $post->post_content ) ) {
			$person_schema['description'] = $this->clean_schema_text( $post->post_content, 200 );
		}
		// Optional: featured image as profile image.
		$image_id = get_post_thumbnail_id( $post->ID );
		if ( ! empty( $image_id ) ) {
			$img            = $helper->imageInfo( absint( $image_id ) );
			$event['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		// Optional: URL of the post as profile URL.
		$person_schema['url']              = get_permalink( $post_id );
		$person_schema['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => $this->get_id_with_hash( 'webpage' ),
		];

		return apply_filters( 'rtseo_auto_schema_snippet_person', $person_schema, $metaData );
	}

	/**
	 * Build auto Service schema.
	 *
	 * @param  string    $schemaCat  Schema category.
	 * @param  \WP_Post  $post  Post object.
	 * @param  int       $post_id  Post ID.
	 * @param  Functions $helper  Helper instance.
	 * @param  string    $site_schema_id  Site schema ID.
	 * @param  array     $metaData  Meta data.
	 *
	 * @return array
	 */
	private function build_auto_service_schema( $schemaCat, $post, $post_id, $helper, $site_schema_id, $metaData ) {
		$service_schema = [
			'@type'    => 'Service',
			'@id'      => $this->get_id_with_hash( $schemaCat . '0' ),
			'name'     => $helper->sanitizeOutPut( $post->post_title ),
			'url'      => get_permalink( $post_id ),
			'provider' => [
				'@id' => $site_schema_id,
			],
		];
		// Optional: description from excerpt or filtered content.
		if ( ! empty( $post->post_excerpt ) ) {
			$service_schema['description'] = $this->clean_schema_text( $post->post_excerpt, 250 );
		} elseif ( ! empty( $post->post_content ) ) {
			$service_schema['description'] = $this->clean_schema_text( $post->post_content, 200 );
		}
		// Optional: featured image.
		$image_id = get_post_thumbnail_id( $post->ID );
		if ( ! empty( $image_id ) ) {
			$img                     = $helper->imageInfo( absint( $image_id ) );
			$service_schema['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $helper->sanitizeOutPut( $img['url'], 'url' ),
				'height' => $img['height'],
				'width'  => $img['width'],
			];
		}
		return apply_filters( 'rtseo_auto_schema_snippet_service', $service_schema, $metaData );
	}
}
