<?php

namespace Rtrs\Helpers;

use Rtrs\Models\Field;
use Rtrs\Modules\Review\Admin\Meta\AffiliateOptions;
use Rtrs\Modules\Review\Admin\Meta\MetaOptions;
use Rtrs\Modules\Review\Admin\Meta\ReviewMeta;
use Rtrs\Modules\Schema\Admin\Meta\FaqPageMeta;
use Rtrs\Modules\Schema\Admin\Meta\SchemaMeta;
use Rtrs\Modules\Schema\Helpers\SchemaFns;
use WP_Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Functions {
	/**
	 * AI sparkle-cluster icon markup.
	 *
	 * Single source of truth for the "AI" glyph used across the settings nav,
	 * the metabox "Generate with AI" tab and every "…with AI" action button
	 * (rendered in JS via the localized `aiIcon` value).
	 *
	 * @param int $width  SVG width attribute (px).
	 * @param int $height SVG height attribute (px).
	 * @return string SVG markup.
	 */
	public static function aiIconSvg( $width = 12, $height = 14 ) {
		$paths = [
			'M6.37822 4.38293L6.95178 5.97575C7.5889 7.74356 8.98101 9.13566 10.7488 9.77279L12.3416 10.3463C12.4852 10.3985 12.4852 10.6021 12.3416 10.6535L10.7488 11.227C8.98101 11.8642 7.5889 13.2563 6.95178 15.0241L6.37822 16.6169C6.32608 16.7605 6.12252 16.7605 6.07109 16.6169L5.49753 15.0241C4.86041 13.2563 3.4683 11.8642 1.70049 11.227L0.107676 10.6535C-0.0358919 10.6013 -0.0358919 10.3978 0.107676 10.3463L1.70049 9.77279C3.4683 9.13566 4.86041 7.74356 5.49753 5.97575L6.07109 4.38293C6.12252 4.23865 6.32608 4.23865 6.37822 4.38293Z',
			'M13.548 0.555177L13.8387 1.36158C14.1616 2.25656 14.8666 2.96154 15.7615 3.28439L16.568 3.5751C16.6408 3.60152 16.6408 3.70438 16.568 3.73081L15.7615 4.02151C14.8666 4.34436 14.1616 5.04934 13.8387 5.94432L13.548 6.75073C13.5216 6.82358 13.4187 6.82358 13.3923 6.75073L13.1016 5.94432C12.7788 5.04934 12.0738 4.34436 11.1788 4.02151L10.3724 3.73081C10.2995 3.70438 10.2995 3.60152 10.3724 3.5751L11.1788 3.28439C12.0738 2.96154 12.7788 2.25656 13.1016 1.36158L13.3923 0.555177C13.4187 0.481608 13.5223 0.481608 13.548 0.555177Z',
			'M13.548 14.2498L13.8387 15.0562C14.1616 15.9512 14.8666 16.6562 15.7615 16.979L16.568 17.2697C16.6408 17.2962 16.6408 17.399 16.568 17.4254L15.7615 17.7161C14.8666 18.039 14.1616 18.744 13.8387 19.639L13.548 20.4454C13.5216 20.5182 13.4187 20.5182 13.3923 20.4454L13.1016 19.639C12.7788 18.744 12.0738 18.039 11.1788 17.7161L10.3724 17.4254C10.2995 17.399 10.2995 17.2962 10.3724 17.2697L11.1788 16.979C12.0738 16.6562 12.7788 15.9512 13.1016 15.0562L13.3923 14.2498C13.4187 14.177 13.5223 14.177 13.548 14.2498Z',
		];

		$svg = sprintf(
			'<svg width="%d" height="%d" viewBox="0 0 17 21" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			absint( $width ),
			absint( $height )
		);
		foreach ( $paths as $d ) {
			$svg .= '<path fill="currentColor" d="' . $d . '"></path>';
		}
		$svg .= '</svg>';

		return $svg;
	}

	/*
	 * Review Enabled
	 */
	public static function review_enabled() {
		$general_options = get_option(
			'rtrs_general_settings',
			[
				'review_enabled' => '',
				'schema_enabled' => 'yes',
			]
		);
		return 'yes' === ( $general_options['review_enabled'] ?? '' );
	}
	/*
	 * Schema Enabled
	 */
	public static function schema_enabled() {
		$general_options = get_option(
			'rtrs_general_settings',
			[
				'review_enabled' => '',
				'schema_enabled' => 'yes',
			]
		);
		return 'yes' === ( $general_options['schema_enabled'] ?? '' );
	}
	/*
	* Review Enabled
	*/
	public static function affiliate_enabled() {
		$general_options = get_option( 'rtrs_general_settings' );
		return 'yes' === ( $general_options['affiliate_enabled'] ?? '' );
	}
	/**
	 * Get single page meta options.
	 *
	 * Collects review and schema meta fields based on enabled modules.
	 *
	 * @return array
	 */
	public static function single_page_meta_for_review_and_schema() {
		$fields = [];
		if ( self::review_enabled() ) {
			$review_meta = ReviewMeta::getInstance();
			$fields      = array_merge( $fields, $review_meta->sectionReviewFields() );
		}
		if ( self::schema_enabled() ) {
			$schema_meta = SchemaMeta::getInstance();
			$fields      = array_merge( $fields, $schema_meta->sectionSchemaFields() );

			$faqpage_meta = FaqPageMeta::getInstance();
			$fields       = array_merge( $fields, $faqpage_meta->faqPageFields() );
		}
		return $fields;
	}

	/**
	 * Get single page meta options.
	 *
	 * Collects review and schema meta fields based on enabled modules.
	 *
	 * @return array
	 */
	public static function affiliate_meta() {
		$fields = [];
		if ( self::review_enabled() ) {
			$meta_options = AffiliateOptions::getInstance();
			$meta_options = $meta_options->allMetaFields();
			$fields       = array_merge( $fields, $meta_options );
		}

		return $fields;
	}
	/**
	 * Get single page meta options.
	 *
	 * Collects review and schema meta fields based on enabled modules.
	 *
	 * @return array
	 */
	public static function post_type_rtrs_meta() {
		$fields = [];
		if ( self::review_enabled() ) {
			$meta_options = new MetaOptions();
			$fields       = $meta_options->allMetaFields();
		}
		return $fields;
	}
	/**
	 * Check if the stored license is valid.
	 *
	 * @return bool
	 */
	public static function has_valid_license() {
		static $cached_result = null;
		// Return cached result if already calculated in this request.
		if ( null !== $cached_result ) {
			return $cached_result;
		}
		$settings      = self::get_option( 'rtrs_tools_settings' );
		$cached_result = ( ! empty( $settings['license_status'] ) && 'valid' === $settings['license_status'] );
		return $cached_result;
	}

	/**
	 * Has Role.
	 *
	 * @return void
	 */
	public static function get_current_user_roles() {
		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$roles = (array) $user->roles;
			return array_values( $roles );
		} else {
			return [];
		}
	}
	/**
	 * Undocumented function
	 *
	 * @return array
	 */
	public static function get_available_roles() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		return $wp_roles->get_names();
	}


	public static function get_nonce() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_REQUEST[ rtrs()->getNonceId() ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ rtrs()->getNonceId() ] ) ) : null;
	}

	public static function locate_template( $name ) {
		// Look within passed path within the theme - this is priority.
		$template = [];

		$template[] = rtrs()->get_template_path() . $name . '.php';

		if ( ! $template_file = locate_template( apply_filters( 'rtrs_locate_template_names', $template ) ) ) {
			$template_file = RTRS_PATH . "templates/$name.php";
		}

		return apply_filters( 'rtrs_locate_template', $template_file, $name );
	}

	/**
	 * Remove any character that is not alphanumeric, /, _, or -.
	 *
	 * @param string $name Name to sanitize.
	 *
	 * @return array|string|string[]|null
	 */
	private static function sanitize_file_name( $name ) {
		// Remove anything that is not alphanumeric, _, -, or /.
		$name = preg_replace( '/[^a-zA-Z0-9\/_\-]/', '', $name );
		// Prevent directory traversal.
		$name = str_replace( [ '../', '..' ], '', $name );
		// Replace multiple slashes with a single slash.
		$name = preg_replace( '/\/+/', '/', $name );
		return trim( $name, '/' ); // Trim leading and trailing slashes.
	}

	/**
	 * Get template part (for templates like the shop-loop).
	 *
	 * RTRS_TEMPLATE_DEBUG_MODE will prevent overrides in themes from taking priority.
	 *
	 * @param mixed  $slug Template slug.
	 * @param string $name Template name (default: '').
	 */
	public static function get_template_part( $slug, $args = null, $include = true ) {
		$slug = self::sanitize_file_name( $slug );
		// load template from theme if exist
		$template = RTRS_TEMPLATE_DEBUG_MODE ? '' : locate_template(
			[
				"{$slug}.php",
				rtrs()->get_template_path() . "{$slug}.php",
			]
		);

		// load template from pro plugin if exist
		if ( ! $template && function_exists( 'rtrsp' ) ) {
			$fallback = rtrs()->plugin_path() . '-pro' . "/templates/{$slug}.php";
			$template = file_exists( $fallback ) ? $fallback : '';
		}

		// load template from current plugin if exist
		if ( ! $template ) {
			$fallback = rtrs()->plugin_path() . "/templates/{$slug}.php";
			$template = file_exists( $fallback ) ? $fallback : '';
		}

		// Allow 3rd party plugins to filter template file from their plugin.
		$template = apply_filters( 'rtrs_get_template_part', $template, $slug );

		if ( $template ) {
			if ( ! empty( $args ) && is_array( $args ) ) {
				extract( $args, EXTR_SKIP ); // @codingStandardsIgnoreLine
			}

			// load_template($template, false, $args);
			if ( $include ) {
				include $template;
			} else {
				return $template;
			}
		}
	}

	public static function doing_it_wrong( $function, $message, $version ) {
		// @codingStandardsIgnoreStart
		$message .= ' Backtrace: ' . wp_debug_backtrace_summary();
		_doing_it_wrong($function, $message, $version);
	}

	public static function is_plugin_active($plugin) {
		return in_array($plugin, apply_filters('active_plugins', get_option('active_plugins')));
	}

	public static function get_template($fileName, $args = null) {
		if (! empty($args) && is_array($args)) {
			extract( $args, EXTR_SKIP ); // @codingStandardsIgnoreLine
		}

		$located = self::locate_template($fileName);

		if (! file_exists($located)) {
			self::doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: %s: template file name */
					__( '%s does not exist.', 'review-schema' ),
					'<code>' . $located . '</code>'
				),
				'1.0'
			);

			return;
		}

		// Allow 3rd party plugin filter template file from their plugin.
		$located = apply_filters('rtrs_get_template', $located, $fileName, $args);

		do_action('rtrs_before_template_part', $fileName, $located, $args);

		include $located;

		do_action('rtrs_after_template_part', $fileName, $located, $args);
	}

	/**
	 * @param $id
	 *
	 * @return bool|mixed|void
	 */
	public static function get_default_placeholder_url() {
		$placeholder_url = RTRS_URL . '/assets/imgs/placeholder.jpg';

		return apply_filters('rtrs_default_placeholder_url', $placeholder_url);
	}

	/**
	 * is_edit_page
	 * function to check if the current page is a post edit page.
	 *
	 * @param  string  $new_edit what page to check for accepts new - new post page ,edit - edit post page, null for either
	 *
	 * @return bool
	 */
	public static function is_edit_page($new_edit = null) {
		global $pagenow;
		//make sure we are on the backend
		if (! is_admin()) {
			return false;
		}

		if ($new_edit == 'edit') {
			return in_array($pagenow, ['post.php']);
		} elseif ($new_edit == 'new') { //check for new post page
			return in_array($pagenow, ['post-new.php']);
		} else { //check for either new or edit
			return in_array($pagenow, ['post.php', 'post-new.php']);
		}
	}

	/**
	 * @param $id
	 *
	 * @return bool|mixed|void
	 */
	public static function get_option($id) {
		if (! $id) {
			return false;
		}
		$settings = get_option($id, []);

		return apply_filters($id, $settings);
	}

	/**
	 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
	 * Non-scalar values are ignored.
	 *
	 * @param string|array $var Data to sanitize.
	 *
	 * @return string|array
	 */
	public static function clean($var) {
		if (is_array($var)) {
			return array_map([self::class, 'clean'], $var);
		} else {
			return is_scalar($var) ? sanitize_text_field($var) : $var;
		}
	}

	/**
	 * @param $id
	 *
	 * @return bool|mixed|void
	 */
	public function fieldGenerator($fields = [], $multi = false) {
		$html = null;
		if (is_array($fields) && ! empty($fields)) {
			$rtField = new Field();
			if ($multi) {
				foreach ($fields as $field) {
					$html .= $rtField->Field($field);
				}
			} else {
				$html .= $rtField->Field($fields);
			}
		}

		return $html;
	}

	/**
	 *  Check review enable.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function allReviewType() {
		$args = [
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'post_type'      => rtrs()->getPostType(),
		];
		$query              = new \WP_Query($args);
		$active_review_type = [];
		while ($query->have_posts()): $query->the_post();
		$active_review_type[] = get_post_meta(get_the_ID(), 'rtrs_post_type', true);
		endwhile;
		wp_reset_postdata();

		return $active_review_type;
	}

	/**
	 *  Check review enable.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	private static $enable_post_type_schema = null;

    /**
     * Get the default schema type for a given post type.
     *
     * @param string|null $p_type The post type slug.
     *
     * @return string|false Schema type string or false if not configured.
     */
    private static $get_default_schema_by_post_type = null;

    public static function getDefaultSchemaByPostType( $p_type = null ) {
        if ( null !== self::$get_default_schema_by_post_type ) {
            return self::$get_default_schema_by_post_type;
        }

        $config = SchemaFns::getPostTypeAutoSchemaConfig( $p_type );

        if ( ! empty( $config['schema_type'] ) ) {
            self::$get_default_schema_by_post_type = $config['schema_type'];
        } else {
            self::$get_default_schema_by_post_type = false;
        }

        return self::$get_default_schema_by_post_type;
    }

	/**
	 *  Check review enable.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	private static $enable_post_type = null;

    /**
     * Check if review is enabled for the given post type.
     *
     * @param string|null $p_type Target post type (e.g., 'page').
     * @return bool True if enabled, false otherwise.
     */
    public static function isEnableReviewByPostType( $p_type = null ) {

        // Return cached result if available.
        if ( self::$enable_post_type !== null ) {
            return self::$enable_post_type;
        }
        if ( ! Functions::review_enabled() ){
            self::$enable_post_type = false;
            return self::$enable_post_type;
        }
        global $post;
        $current_post_id = is_object( $post ) && isset( $post->ID ) ? $post->ID : 0;
        // Query only IDs to reduce memory usage.
        $args = [
            'posts_per_page' => -1,
            'post_type'      => rtrs()->getPostType(),
            'post_status'    => 'publish',
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
            'meta_query'     => [
                [
                    'key'     => 'rtrs_post_type',
                    'value'   => $p_type,
                    'compare' => '=',
                ],
                [
                    'key'     => 'rtrs_support',
                    'value'   => 1,
                    'compare' => '=',
                ],
            ],
        ];
        // rtrs_support
        $post_ids = get_posts( $args );
        // No matched review-config posts.
        if ( empty( $post_ids ) ) { // Empty for not match post type.
            self::$enable_post_type = false;
            return self::$enable_post_type ;
        }
        // For pages → check if the specific page is allowed.
        if ( 'page' === $p_type ) {
            foreach ( $post_ids as $id ) {
                $page_ids = get_post_meta( $id, 'rtrs_page_id', false );
                // If empty → global enable
                if ( empty( $page_ids ) ) {
                    self::$enable_post_type = true;
                    break;
                }
                // If current page is in allowed list
                if ( in_array( $current_post_id, array_map( 'absint', $page_ids ), true ) ) {
                    self::$enable_post_type = true;
                    break;
                }
                // No match
                self::$enable_post_type = false;
            }
            return self::$enable_post_type;
        }
        self::$enable_post_type = true;
        return self::$enable_post_type ;
    }

	/**
	 *  Get all post meta.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	private static $post_meta = null;

	public static function getMetaByPostType($p_type = null) {
		if (self::$post_meta != null) {
			return self::$post_meta;
		}

		$args = [
			'posts_per_page' => 1,
			'post_type'      => rtrs()->getPostType(),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
			'meta_query'     => [
				[
					'key'     => 'rtrs_post_type',
					'value'   => $p_type,
					'compare' => '=',
				],
			],
		];
		// Read the matched config post directly from the query result.
		// Do NOT use the_post()/wp_reset_postdata() here: on admin edit
		// screens there is no main loop to restore, so it corrupts the
		// global $post and breaks other metaboxes (e.g. featured image).
		$query     = new \WP_Query($args);
		$all_metas = null;
		if (! empty($query->posts)) {
			$sc_id              = $query->posts[0]->ID;
			$all_metas          = get_post_meta($sc_id);
			$all_metas['sc_id'] = $sc_id;
			self::$post_meta    = $all_metas;
		}

		return $all_metas;
	}

	/**
	 *  Get Criteria.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function getCriteriaByPostType($p_type = null) {
		if (! $p_type) {
			$p_type = get_post_type();
		}

		$args = [
			'posts_per_page' => 1,
			'post_type'      => rtrs()->getPostType(),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
			'meta_query'     => [
				[
					'key'     => 'rtrs_post_type',
					'value'   => $p_type,
					'compare' => '=',
				],
			],
		];

		$query          = new \WP_Query($args);
		$multi_criteria = [];
		while ($query->have_posts()): $query->the_post();
		$multi_criteria = get_post_meta(get_the_ID(), 'multi_criteria', true);
		endwhile;
		wp_reset_postdata();

		return $multi_criteria;
	} 
	 
	/**
	 *  Default settings schema
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	private static $default_setting_schema = null;
	public static function default_setting_schema() {
		if (self::$default_setting_schema != null) {
			return self::$default_setting_schema;
		}

		$new_default   = [];
		$post_type     = Functions::getPostTypes(false, false);
		foreach (array_keys($post_type) as $value) {
			switch ($value) {
				case 'post':
					$new_default[] = [
						'post_type'   => $value,
						'schema_type' => 'blog_posting',
					];
					break;

				case 'page':
					$new_default[] = [
						'post_type'   => $value,
						'schema_type' => 'article',
					];
					break;

				case 'product':
				case 'download':
				case 'fluent-products':
				case 'sc_product':
				case 'wpdmpro':
					$new_default[] = [
						'post_type'   => $value,
						'schema_type' => 'product',
					];
					break;

				default:
					$new_default[] = [
						'post_type'   => $value,
						'schema_type' => 'article',
					];

					break;
			}
		}
		 
		return self::$default_setting_schema = $new_default;
	}

	/**
	 *  String to slug convert.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function slugify($string) {
		if ( self::is_english( $string ) ) {
			return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
		} else {
			return sanitize_title( $string );
		}
	}
	/**
	 * Undocumented function
	 *
	 * @param [type] $str text.
	 * @return boolean
	 */
	public static function is_english( $str ) {
        if ( ! function_exists( 'mb_convert_encoding' ) ) {
            // Fallback: assume string is English or just return true
            return ( preg_match( '/^[\x00-\x7F]*$/', $str ?? '' ) === 1 );
        }
		$strUtf8 = mb_convert_encoding($str ?? '', 'UTF-8', 'ISO-8859-1');

		if (strlen( $str ?? '' ) !== strlen( $strUtf8 )) {
			return false;
		} else {
			return true;
		}
	}
	/**
	 * Sanitize out put.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public function sanitizeOutPut($value, $type = 'text') {
		$newValue = null;
		// Allow a numeric 0 (e.g. a free price) through; a bare if ($value)
		// check treats 0/'0' as empty and would wrongly return null.
		if ( is_numeric( $value ) || ! empty( $value ) ) {
			if ($type == 'text') {
				$newValue = wp_strip_all_tags(stripslashes($value));
            } elseif ($type == 'url') {
                $newValue = esc_url(stripslashes($value));
            } elseif ($type == 'number') {
                $newValue = (float) $value; // returns float automatically
			} elseif ($type == 'textarea') {
				$newValue = esc_textarea(stripslashes($value));
			} else {
				$newValue = wp_strip_all_tags(stripslashes($value));
			}
		}

		return $newValue;
	}

	/**
	 * Image information.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
    public function imageInfo( $attachment_id ) {
        $data = [];
        $post = get_post( $attachment_id );
        $data['id']          = $attachment_id;
        $data['url']         = wp_get_attachment_url( $attachment_id );
        $data['title']       = wp_strip_all_tags( $post->post_title ?? '' );
        $data['caption']     = wp_strip_all_tags( $post->post_excerpt ?? '' );
        $data['description'] = wp_strip_all_tags( $post->post_content ?? '' );
        // alt text is stored in post meta
        $data['alt'] = wp_strip_all_tags(
                get_post_meta( $attachment_id, '_wp_attachment_image_alt', true )
        );
        // metadata for width/height
        $meta = wp_get_attachment_metadata( $attachment_id );
        $data['width']  = isset($meta['width'])  ? absint($meta['width'])  : 0;
        $data['height'] = isset($meta['height']) ? absint($meta['height']) : 0;
        return $data;
    }


	/**
	 *  Google rich snippet auto category.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function rich_snippet_auto_cats() {
		$pro_label = '';
		if (! function_exists('rtrsp')) {
			$pro_label = ' [Pro]'; //don't need to translate
		}
		$auto_cat = [
			''             => esc_html__('--Select Schema Type--', 'review-schema'),
			'article'      => esc_html__('Article', 'review-schema'),
			'news_article' => esc_html__('Article - News Article', 'review-schema'),
            'tech_article' => esc_html__('Tech Article', 'review-schema'),// Todo: Need Check.
			'blog_posting' => esc_html__('Article - Blog posting', 'review-schema'),
            'book'       => esc_html__( 'Book', 'review-schema' ),
            'event'      => esc_html__( 'Event', 'review-schema' ),
            'video'      => esc_html__( 'Video', 'review-schema' ), // If Page Has Video, then will add auto video schema.
            'person'     => esc_html__( 'Person', 'review-schema' ),
            'service'    => esc_html__( 'Service', 'review-schema' ),
            'software_app'   => esc_html__( 'Software Application', 'review-schema' ) . $pro_label,
            'job_posting' => esc_html__( 'Job Posting', 'review-schema' ) . $pro_label,
            // 'recipe'     => esc_html__( 'Recipe', 'review-schema' ). $pro_label, // Is it not possible To show as auto schema. many error show.
            'Restaurant' => esc_html__( 'Restaurant', 'review-schema' ). $pro_label,
			'product'      => esc_html__('Product', 'review-schema') . $pro_label,
		];
        $has_course = false;
		if ( is_plugin_active('learnpress/learnpress.php') ) {
            $has_course = true;
		}
        if ( !$has_course &&  is_plugin_active('tutor/tutor.php')) {
            $has_course = true;
        }
        if ( !$has_course &&  is_plugin_active('academy/academy.php')) {
            $has_course = true;
        }
        if (!$has_course && is_plugin_active('lifterlms/lifterlms.php')){
            $has_course = true;
        }
        if ($has_course){
            $auto_cat['course'] = esc_html__('Course', 'review-schema') . $pro_label;
        }
		return apply_filters('rtrs_rich_snippet_auto_cats', $auto_cat);
	}

	/**
	 *  Google rich snippet category.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function rich_snippet_cats() {
		$pro_label = '';
		if (! function_exists('rtrsp')) {
			$pro_label = ' [Pro]'; //don't need to translate
		}
		return apply_filters('rtrs_rich_snippet_cats', [
            'article'              => esc_html__('Article', 'review-schema'), // Done
            'tech_article'         => esc_html__('TechArticle', 'review-schema'), // Done
            'news_article'         => esc_html__('NewsArticle', 'review-schema'), // Done
            'blog_posting'         => esc_html__('BlogPosting', 'review-schema'), // Done
            'event'                => esc_html__('Event', 'review-schema'), // Done
            'local_business'       => esc_html__('LocalBusiness', 'review-schema') . $pro_label, // Done
			'faq'                  => esc_html__('FAQPage', 'review-schema'), // Done
			'service'              => esc_html__('Service', 'review-schema'), // Done
			'question_answer'      => esc_html__('QAPage - formerly Q&A ( Deprecated ) Use FAQPage Schema', 'review-schema'), // Done
			'how_to'               => esc_html__('HowTo', 'review-schema'), // Done
			'about'                => esc_html__('About', 'review-schema'),  // Done
			'contact'              => esc_html__('Contact', 'review-schema'), // Done
			'person'               => esc_html__('Person', 'review-schema'), // Done
			'movie'                => esc_html__('Movie', 'review-schema'), // Done
			'audio'                => esc_html__('Audio', 'review-schema'), // Done
			'video'                => esc_html__('Video', 'review-schema'), // Done
			'breadcrumb'           => esc_html__('Breadcrumb', 'review-schema'), // Done
            'mosque'  			   => esc_html__('Mosque', 'review-schema'), // Done
            'church'  			   => esc_html__('Church', 'review-schema'), // Done
            'hindutemple'  		   => esc_html__('HinduTemple', 'review-schema'), // Done
            'buddhisttemple'  	   => esc_html__('BuddhistTemple', 'review-schema'), // Done
            'touristattraction'    => esc_html__('TouristAttraction', 'review-schema') . $pro_label, // Done
            'profile_page'  	   => esc_html__('ProfilePage', 'review-schema'), // Done
            'medical_webpage'  	   => esc_html__('MedicalWebPage', 'review-schema'), // Done
			'product'              => esc_html__('Product', 'review-schema') . $pro_label, // Done
			'book'                 => esc_html__('Book', 'review-schema') . $pro_label, // Done
			'real_state_listing'   => esc_html__('RealEstateListing', 'review-schema') . $pro_label, // Done
			'course'               => esc_html__('Course', 'review-schema') . $pro_label, // Done
			'job_posting'          => esc_html__('JobPosting', 'review-schema') . $pro_label, // Done
			'recipe'               => esc_html__('Recipe', 'review-schema') . $pro_label, // Done
			'software_app'         => esc_html__('SoftwareApplication', 'review-schema') . $pro_label, // Done
			'image_license'        => esc_html__('Image License (ImageObject)', 'review-schema') . $pro_label, // Done
            'Restaurant'           => esc_html__( 'Restaurant', 'review-schema' ) . $pro_label, // Done
			'special_announcement' => esc_html__('SpecialAnnouncement ( Deprecated )', 'review-schema') . $pro_label, // Done
            'vacation_rental'  	   => esc_html__('VacationRental', 'review-schema') . $pro_label, // Done
            'vehicle_listing'  	   => esc_html__('Vehicle listing', 'review-schema') . $pro_label, // Done
            'tv_series'            => esc_html__( 'TVSeries', 'review-schema' ) . $pro_label, // Done
            'PodcastEpisode'       => esc_html__( 'PodcastEpisode', 'review-schema' ) . $pro_label, // Done
            'DiscussionForumPosting' => esc_html__( 'DiscussionForumPosting', 'review-schema' ) . $pro_label, // Done
            'Dataset'               => esc_html__( 'Dataset ( Deprecated )', 'review-schema' ) . $pro_label,  // Done
            'TaxiService'           => esc_html__( 'TaxiService', 'review-schema' ) . $pro_label,  // Done
        ]);
	}

	/**
	 * Get all custom post types.
	 *
	 * @param none
	 *
	 * @return array
	 */
    /**
     * Get all custom post types.
     *
     * @param bool $key_only
     * @param bool $select_option
     *
     * @return array
     */
    public static function getPostTypes($key_only = false, $select_option = true)
    {
        global $wp_post_types;
        // Handle case where $wp_post_types is not available
        if (empty($wp_post_types) || !is_array($wp_post_types)) {
            return [];
        }
        $pre_post_types = $data = [];
        if ($select_option) {
            $data[] = esc_html__('Select', 'review-schema');
        }
        foreach ($wp_post_types as $key => $post_type) {
            // Skip if post type object is invalid
            if (!is_object($post_type) || !isset($post_type->label)) {
                continue;
            }
            // Skip if key is not a valid string
            if (empty($key) || !is_string($key)) {
                continue;
            }
            $pre_post_types[$key] = $post_type->label;
        }
        // Remove some post types
        $post_type_remove = [
                'rtrs',
                'rtrs_affiliate',
                'attachment',
                'nav_menu_item',
                'customize_changeset',
                'revision',
                'custom_css',
                'oembed_cache',
                'user_request',
                'wp_block',
                'product_variation',
                'shop_order',
                'shop_order_refund',
                'shop_coupon',
                'edd_log',
                'edd_payment',
                'edd_discount',
                'rtcl_cfg',
                'rtcl_cf',
                'rtcl_payment',
                'rtcl_pricing',
                'rtsb_builder',
                'acf-taxonomy',
                'acf-post-type',
                'acf-field-group',
                'acf-field',
                'elementor_library',
                'elementor_component',
                'e-landing-page',
                'wp_template',
                'lp_order',
                'wp_template_part',
                'wp_global_styles',
                'wp_navigation',
                'wp_font_family',
                'wp_font_face',
                'e-floating-buttons',
                'shop_order_placehold',
                'rm_content_editor',
                'rank_math_schema'
        ];

        // Ensure filter returns an array
        $post_type_remove = apply_filters('rtrs_post_type_remove', $post_type_remove);
        if (!is_array($post_type_remove)) {
            $post_type_remove = [];
        }
        foreach ($pre_post_types as $key => $posttype) {
            // Skip blacklisted post types
            if (in_array($key, $post_type_remove, true)) {
                continue;
            }
            // Skip if post type object is missing or public property is not set
            if (!isset($wp_post_types[$key]) || !is_object($wp_post_types[$key])) {
                continue;
            }
            // Skip non-public post types.
            if (empty($wp_post_types[$key]->public)) {
                continue;
            }
            // Skip post types hidden from menu (allow string values like submenu slugs)
            $show_in_menu = $wp_post_types[$key]->show_in_menu ?? null;
            if ($show_in_menu === false || $show_in_menu === null) {
                continue;
            }
            // Skip if label is not a valid string
            if (empty($posttype) || !is_string($posttype)) {
                continue;
            }
            if ($key_only) {
                $data[] = sanitize_key($key);
            } else {
                $data[sanitize_key($key)] = esc_html($posttype);
            }
        }
        // Ensure filter returns an array
        $result = apply_filters('rtrs_post_type', $data);
        if (!is_array($result)) {
            return $data; // Fall back to unfiltered data if filter breaks it
        }
        return $result;
    }

	/**
	 * Check purchased user.
	 *
	 * @param comment_id
	 *
	 * @return mixed
	 */
	public static function purchased_user($comment = null) {
		
		if( ! $comment ){
			return false;
		}
		$varified = false;

		$user_id    = $comment->user_id;
		$user_email = $comment->comment_author_email;
		$post_id    = $comment->comment_post_ID;
		$post_type  = get_post_type($post_id);

		if ($post_type == 'product') {
			$varified = wc_customer_bought_product($user_email, $user_id, $post_id);
		} elseif ($post_type == 'download') {
			$varified = edd_has_user_purchased($user_id, $post_id);
		}

		return $varified;
	}

    /**
     * Returns cleaned sameAs values as array or string.
     *
     * Removes whitespace from each URL and supports multi-line input.
     *
     * @param string $value Newline-separated URLs.
     * @return array|string|null
     */
    public static function get_same_as( $value ) {
        $sameAs = null;
        if ( $value ) {
            $lines = preg_split( '/\r\n|\r|\n/', $value );
            $lines = ! empty( $lines ) ? array_filter( $lines ) : [];
            $cleaned = [];
            foreach ( $lines as $line ) {
                $trimmed = esc_url_raw( trim( $line ) );
                if ( $trimmed !== '' ) {
                    $cleaned[] = $trimmed;
                }
            }
            if ( ! empty( $cleaned ) ) {
                $sameAs = count( $cleaned ) > 1 ? $cleaned : $cleaned[0];
            }
        }
        return $sameAs;
    }


	public static function filter_content($content, $limit = 0) {
		$content = preg_replace('#\[[^\]]+\]#', '', wp_strip_all_tags($content));
		$content = self::characterToHTMLEntity($content);
		if ($limit && strlen($content) > $limit) {
			$content = mb_substr($content, 0, $limit, 'utf-8');
			$content = preg_replace('/\W\w+\s*(\W*)$/', '$1', $content);
		}

		return $content;
	}

	public static function array_insert(&$array, $position, $insert_array) {
		$first_array = array_splice($array, 0, $position + 1);
		$array       = array_merge($first_array, $insert_array, $array);
	}

	public static function add_notice($message, $notice_type = 'success', $notice_id = null) {
		if (! did_action('rtrs_init')) {
			self::doing_it_wrong(__FUNCTION__, esc_html__('This function should not be called before rtrs_init.', 'review-schema'), '1.0.0');

			return;
		}

		$notices = rtrs()->session->get('rtrs_notices', []);

		$notices[$notice_type][] = apply_filters('rtrs_add_notice_' . $notice_type, $message, $notice_id);

		rtrs()->session->set('rtrs_notices', $notices);
	}

	public static function characterToHTMLEntity($str) {
		$replace = [
			"'", '&', '<', '>', '€', '‘', '’', '“', '”', '–', '—', '¡', '¢', '£', '¤', '¥', '¦', '§', '¨', '©', 'ª', '«', '¬', '®', '¯', '°', '±', '²', '³', '´', 'µ', '¶', '·', '¸', '¹', 'º', '»', '¼', '½', '¾', '¿', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', '×', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'Þ', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', '÷', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ', 'Œ', 'œ', '‚', '„', '…', '™', '•', '˜',
		];

		$search = [
			'&#8217;', '&amp;', '&lt;', '&gt;', '&euro;', '&lsquo;', '&rsquo;', '&ldquo;', '&rdquo;', '&ndash;', '&mdash;', '&iexcl;', '&cent;', '&pound;', '&curren;', '&yen;', '&brvbar;', '&sect;', '&uml;', '&copy;', '&ordf;', '&laquo;', '&not;', '&reg;', '&macr;', '&deg;', '&plusmn;', '&sup2;', '&sup3;', '&acute;', '&micro;', '&para;', '&middot;', '&cedil;', '&sup1;', '&ordm;', '&raquo;', '&frac14;', '&frac12;', '&frac34;', '&iquest;', '&Agrave;', '&Aacute;', '&Acirc;', '&Atilde;', '&Auml;', '&Aring;', '&AElig;', '&Ccedil;', '&Egrave;', '&Eacute;', '&Ecirc;', '&Euml;', '&Igrave;', '&Iacute;', '&Icirc;', '&Iuml;', '&ETH;', '&Ntilde;', '&Ograve;', '&Oacute;', '&Ocirc;', '&Otilde;', '&Ouml;', '&times;', '&Oslash;', '&Ugrave;', '&Uacute;', '&Ucirc;', '&Uuml;', '&Yacute;', '&THORN;', '&szlig;', '&agrave;', '&aacute;', '&acirc;', '&atilde;', '&auml;', '&aring;', '&aelig;', '&ccedil;', '&egrave;', '&eacute;', '&ecirc;', '&euml;', '&igrave;', '&iacute;', '&icirc;', '&iuml;', '&eth;', '&ntilde;', '&ograve;', '&oacute;', '&ocirc;', '&otilde;', '&ouml;', '&divide;', '&oslash;', '&ugrave;', '&uacute;', '&ucirc;', '&uuml;', '&yacute;', '&thorn;', '&yuml;', '&OElig;', '&oelig;', '&sbquo;', '&bdquo;', '&hellip;', '&trade;', '&bull;', '&asymp;',
		];

		//REPLACE VALUES
		$str = str_replace($search, $replace, $str);

		//RETURN FORMATED STRING
		return $str;
	}

	/**
	 *  Format bye.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function format_bytes($bytes) {
		$label = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		for ($i = 0; $bytes >= 1024 && $i < (count($label) - 1); $bytes /= 1024, $i++);

		return  round($bytes, 2) . ' ' . $label[$i];
	}

	/**
	 * Generate ShortCode CSS.
	 *
	 * @param int $scID
	 *
	 * @return void
	 */  

	public static function generatorShortCodeCss($scID, $post_type) {
		global $wp_filesystem;
		// Initialize the WP filesystem, no more using 'file-put-contents' function
		if ( empty($wp_filesystem) ) {
			require_once (ABSPATH . '/wp-admin/includes/file.php');
			WP_Filesystem();
		}
		
		$upload_dir = wp_upload_dir(); 
		$upload_basedir = $upload_dir['basedir'] ;
		$cssFile = $upload_basedir . '/review-schema/sc.css'; 
		if ( $css = rtrs()->render($post_type . '-sc-css', compact('scID'), true) ) { 
			$css = sprintf('/*' . $post_type . '-sc-%2$d-start*/%1$s/*' . $post_type . '-sc-%2$d-end*/', $css, $scID);
			if ( file_exists($cssFile) && ($oldCss = $wp_filesystem->get_contents($cssFile)) ) {
				if ( strpos($oldCss, '/*' . $post_type . '-sc-' . $scID . '-start') !== false ) {
					$oldCss = preg_replace('/\/\*' . $post_type . '-sc-' . $scID . '-start[\s\S]+?' . $post_type . '-sc-' . $scID . '-end\*\//', '', $oldCss);
					$oldCss = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $oldCss);
				}
				$css = $oldCss . $css;				
			} else if ( ! file_exists( $cssFile ) ) {
				$upload_basedir_trailingslashit = trailingslashit( $upload_basedir ); 
				$wp_filesystem->mkdir( $upload_basedir_trailingslashit. 'review-schema' );
			}
			if( ! $wp_filesystem->put_contents( $cssFile, $css  ) ){
				error_log(print_r('SchemaEngine AI: Error Generated css file ',true));
			}
		} 
	}

	/**
	 * Remove Generate ShortCode CSS.
	 *
	 * @param int $scID
	 *
	 * @return void
	 */
	public static function removeGeneratorShortCodeCss($scID, $post_type) {
		global $wp_filesystem;
		// Initialize the WP filesystem, no more using 'file-put-contents' function
		if (empty($wp_filesystem)) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload_dir = wp_upload_dir(); 
		$upload_basedir = $upload_dir['basedir'];
		$cssFile = $upload_basedir . '/review-schema/sc.css';

		if (file_exists($cssFile) && ($oldCss = $wp_filesystem->get_contents($cssFile)) && strpos($oldCss, '/*' . $post_type . '-sc-' . $scID . '-start') !== false) {
			$css = preg_replace('/\/\*' . $post_type . '-sc-' . $scID . '-start[\s\S]+?' . $post_type . '-sc-' . $scID . '-end\*\//', '', $oldCss);
			$css = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", '', $css);

			$wp_filesystem->put_contents($cssFile, $css);
		}
	}
    /*
     * All Pages
     */
    public static function allPages() {
        $page_array    = [];
        $page_array[0] = esc_html__( 'Select', 'review-schema' );
        $all_pages     = get_pages();
        foreach ( $all_pages as $page ) {
            $page_array[ $page->ID ] = $page->post_title;
        }

        return apply_filters( 'rtrs_pages', $page_array );
    }

    /**
     * Get site types for per-post local business schema.
     *
     * @return array
     */
    public static function getSiteTypes() {
        $siteTypes = [
            'Organization'   => 'Organization',
            'LocalBusiness'  => self::getSiteSubTypesLocalBusiness(),
            'NGO'            => 'NGO',
            'CivicStructure' => [
                'Museum' => 'Museum',
            ],
        ];

        return apply_filters( 'rtseo_site_types', $siteTypes );
    }
    /**
     * Get site sub-types for Organization.
     *
     * @return array
     */
    public static function getSiteSubTypesOrganization() {
        $siteTypes = [
            'Organization'           => [
                'Corporation'            => 'Corporation',
                'OnlineBusiness'         => 'OnlineBusiness',
                'Consortium'             => 'Consortium',
                'Airline'                => 'Airline',
                'GovernmentOrganization' => 'GovernmentOrganization',
                'NGO'                    => 'NGO',
                'NewsMediaOrganization'  => 'NewsMediaOrganization',
                'PoliticalParty'         => 'PoliticalParty',
                'ResearchOrganization'   => 'ResearchOrganization',
                'SportsOrganization'     => [
                    'SportsTeam' => 'SportsTeam',
                ],
                'WorkersUnion'           => 'WorkersUnion',
                'CivicStructure'         => [
                    'Museum' => 'Museum',
                ],
                'MedicalOrganization'    => [
                    'Hospital'       => 'Hospital',
                    'VeterinaryCare' => 'VeterinaryCare',
                ],
                'PerformingGroup'        => [
                    'DanceGroup'   => 'DanceGroup',
                    'MusicGroup'   => 'MusicGroup',
                    'TheaterGroup' => 'TheaterGroup',
                ],
                'Project'                => [
                    'FundingScheme'  => 'FundingScheme',
                    'ResearchProject' => 'ResearchProject',
                ],
                'EducationalOrganization' => [
                    'CollegeOrUniversity' => 'CollegeOrUniversity',
                    'School'              => [
                        'ElementarySchool' => 'ElementarySchool',
                        'HighSchool'       => 'HighSchool',
                        'MiddleSchool'     => 'MiddleSchool',
                        'Preschool'        => 'Preschool',
                    ],
                ],
                'LocalBusiness' => Functions::getSiteSubTypesLocalBusiness(),
            ],

        ];
        return apply_filters( 'rtrs_sub_types_organization', $siteTypes );
    }
    /**
     * Get site sub-types for Local Business.
     *
     * @return array
     */
    public static function getSiteSubTypesLocalBusiness() {
        $siteTypes = [
            'AnimalShelter'               => 'AnimalShelter',
            'ArchiveOrganization'         => 'ArchiveOrganization',
            'ChildCare'                   => 'ChildCare',
            'DryCleaningOrLaundry'        => 'DryCleaningOrLaundry',
            'EmploymentAgency'            => 'EmploymentAgency',
            'InternetCafe'                => 'InternetCafe',
            'Library'                     => 'Library',
            'RecyclingCenter'             => 'RecyclingCenter',
            'SelfStorage'                 => 'SelfStorage',
            'TaxiService' => 'TaxiService',
            'AutomotiveBusiness'          => [
                'AutoBodyShop'     => 'AutoBodyShop',
                'AutoDealer'       => 'AutoDealer',
                'AutoPartsStore'   => 'AutoPartsStore',
                'AutoRental'       => 'AutoRental',
                'AutoRepair'       => 'AutoRepair',
                'AutoWash'         => 'AutoWash',
                'GasStation'       => 'GasStation',
                'MotorcycleDealer' => 'MotorcycleDealer',
                'MotorcycleRepair' => 'MotorcycleRepair',
            ],
            'FinancialService'            => [
                'AccountingService' => 'AccountingService',
                'AutomatedTeller'   => 'AutomatedTeller',
                'BankOrCreditUnion' => 'BankOrCreditUnion',
                'InsuranceAgency'   => 'InsuranceAgency',
            ],
            'FoodEstablishment'           => [
                'Bakery'             => 'Bakery',
                'BarOrPub'           => 'BarOrPub',
                'Brewery'            => 'Brewery',
                'CafeOrCoffeeShop'   => 'CafeOrCoffeeShop',
                'Distillery'         => 'Distillery',
                'FastFoodRestaurant' => 'FastFoodRestaurant',
                'IceCreamShop'       => 'IceCreamShop',
                'Restaurant'         => 'Restaurant',
                'Winery'             => 'Winery',
            ],
            'GovernmentOffice'            => [
                'FireStation'   => 'FireStation',
                'PoliceStation' => 'PoliceStation',
                'PostOffice'    => 'PostOffice',
            ],
            'HealthAndBeautyBusiness'     => [
                'BeautySalon'  => 'BeautySalon',
                'DaySpa'       => 'DaySpa',
                'HairSalon'    => 'HairSalon',
                'HealthClub'   => 'HealthClub',
                'NailSalon'    => 'NailSalon',
                'TattooParlor' => 'TattooParlor',
            ],
            'HomeAndConstructionBusiness' => [
                'Electrician'       => 'Electrician',
                'GeneralContractor' => 'GeneralContractor',
                'HVACBusiness'      => 'HVACBusiness',
                'HousePainter'      => 'HousePainter',
                'Locksmith'         => 'Locksmith',
                'MovingCompany'     => 'MovingCompany',
                'Plumber'           => 'Plumber',
                'RoofingContractor' => 'RoofingContractor',
            ],
            'LegalService'                => [
                'Attorney' => 'Attorney',
                'Notary'   => 'Notary',
            ],
            'LodgingBusiness'             => [
                'BedAndBreakfast' => 'BedAndBreakfast',
                'Campground'      => 'Campground',
                'Hostel'          => 'Hostel',
                'Hotel'           => 'Hotel',
                'Motel'           => 'Motel',
                'Resort'          => 'Resort',
                'RVPark'          => 'RVPark',
            ],
            'MedicalBusiness'             => [
                'CommunityHealth'  => 'CommunityHealth',
                'DiagnosticLab'    => 'DiagnosticLab',
                'Dentist'          => 'Dentist',
                'Dermatology'      => 'Dermatology',
                'DietNutrition'    => 'DietNutrition',
                'EmergencyService' => 'EmergencyService',
                'Geriatric'        => 'Geriatric',
                'Gynecologic'      => 'Gynecologic',
                'MedicalClinic'    => 'MedicalClinic',
                'Midwifery'        => 'Midwifery',
                'Nursing'          => 'Nursing',
                'Obstetric'        => 'Obstetric',
                'Oncologic'        => 'Oncologic',
                'Optician'         => 'Optician',
                'Optometric'       => 'Optometric',
                'Otolaryngologic'  => 'Otolaryngologic',
                'Pediatric'        => 'Pediatric',
                'Pharmacy'         => 'Pharmacy',
                'Physician'        => 'Physician',
                'Physiotherapy'    => 'Physiotherapy',
                'PlasticSurgery'   => 'PlasticSurgery',
                'Podiatric'        => 'Podiatric',
                'PrimaryCare'      => 'PrimaryCare',
                'Psychiatric'      => 'Psychiatric',
                'PublicHealth'     => 'PublicHealth',
            ],
            'ProfessionalService'         => [
                'RealEstateAgent'          => 'RealEstateAgent',
                'TouristInformationCenter' => 'TouristInformationCenter',
                'TravelAgency'             => 'TravelAgency',
            ],
            'Store'                       => [
                'BikeStore'            => 'BikeStore',
                'BookStore'            => 'BookStore',
                'ClothingStore'        => 'ClothingStore',
                'ComputerStore'        => 'ComputerStore',
                'ConvenienceStore'     => 'ConvenienceStore',
                'DepartmentStore'      => 'DepartmentStore',
                'ElectronicsStore'     => 'ElectronicsStore',
                'Florist'              => 'Florist',
                'FurnitureStore'       => 'FurnitureStore',
                'GardenStore'          => 'GardenStore',
                'GroceryStore'         => 'GroceryStore',
                'HardwareStore'        => 'HardwareStore',
                'HobbyShop'            => 'HobbyShop',
                'HomeGoodsStore'       => 'HomeGoodsStore',
                'JewelryStore'         => 'JewelryStore',
                'LiquorStore'          => 'LiquorStore',
                'MensClothingStore'    => 'MensClothingStore',
                'MobilePhoneStore'     => 'MobilePhoneStore',
                'MovieRentalStore'     => 'MovieRentalStore',
                'MusicStore'           => 'MusicStore',
                'OfficeEquipmentStore' => 'OfficeEquipmentStore',
                'OutletStore'          => 'OutletStore',
                'PawnShop'             => 'PawnShop',
                'PetStore'             => 'PetStore',
                'ShoeStore'            => 'ShoeStore',
                'SportingGoodsStore'   => 'SportingGoodsStore',
                'TireShop'             => 'TireShop',
                'ToyStore'             => 'ToyStore',
                'WholesaleStore'       => 'WholesaleStore',
            ],
            'SportsActivityLocation'      => [
                'BowlingAlley'       => 'BowlingAlley',
                'ExerciseGym'        => 'ExerciseGym',
                'GolfCourse'         => 'GolfCourse',
                'PublicSwimmingPool'  => 'PublicSwimmingPool',
                'SkiResort'          => 'SkiResort',
                'SportsClub'         => 'SportsClub',
                'StadiumOrArena'     => 'StadiumOrArena',
                'TennisComplex'      => 'TennisComplex',
            ],
            'EntertainmentBusiness'       => [
                'AdultEntertainment' => 'AdultEntertainment',
                'AmusementPark'      => 'AmusementPark',
                'ArtGallery'         => 'ArtGallery',
                'Casino'             => 'Casino',
                'ComedyClub'         => 'ComedyClub',
                'MovieTheater'       => 'MovieTheater',
                'NightClub'          => 'NightClub',
            ],
        ];

        return apply_filters( 'rtrs_sub_types_local_business', $siteTypes );
    }

	/**
	 * Check if a schema type is LocalBusiness or a subtype of LocalBusiness.
	 *
	 * @param string $type Schema type to check.
	 *
	 * @return bool
	 */
	public static function isLocalBusinessType( $type ) {
		if ( 'LocalBusiness' === $type ) {
			return true;
		}

		$local_business_types = self::getSiteSubTypesLocalBusiness();

		return self::typeExistsInArray( $type, $local_business_types );
	}

	/**
	 * Recursively check if a type key exists in a nested array.
	 *
	 * @param string $type  Schema type to find.
	 * @param array  $types Nested array of types.
	 *
	 * @return bool
	 */
	private static function typeExistsInArray( $type, $types ) {
		foreach ( $types as $key => $value ) {
			if ( $key === $type ) {
				return true;
			}
			if ( is_array( $value ) && self::typeExistsInArray( $type, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the @type value for schema output.
	 *
	 * LocalBusiness subtypes include 'LocalBusiness' and Organization subtypes
	 * include 'Organization' in the @type array so properties like priceRange,
	 * contactPoint remain valid for all sub-categories.
	 *
	 * @param string $category Schema category.
	 *
	 * @return string|array
	 */
	public static function getSchemaType( $category ) {
		if ( in_array( $category, [ 'Organization', 'Person', 'LocalBusiness' ], true ) ) {
			return $category;
		}

		if ( self::isLocalBusinessType( $category ) ) {
			return [ $category, 'LocalBusiness' ];
		}

		return [ $category, 'Organization' ];
	}

	/**
	 * Check if the given type is a FoodEstablishment or its subtype.
	 *
	 * @param string $type Schema type to check.
	 *
	 * @return bool
	 */
	public static function isFoodEstablishmentType( $type ) {
		return in_array( $type, self::getFoodEstablishmentTypes(), true );
	}

	/**
	 * FoodEstablishment and its subtypes.
	 *
	 * These types support servesCuisine / menu / hasMenu / acceptsReservations.
	 *
	 * @since 3.0.3
	 * @return array
	 */
	public static function getFoodEstablishmentTypes() {
		return [
			'FoodEstablishment',
			'Bakery',
			'BarOrPub',
			'Brewery',
			'CafeOrCoffeeShop',
			'Distillery',
			'FastFoodRestaurant',
			'IceCreamShop',
			'Restaurant',
			'Winery',
		];
	}

	/**
	 * Check if the given type is a LodgingBusiness or its subtype.
	 *
	 * These types support the checkinTime / checkoutTime properties.
	 *
	 * @param string $type Schema type to check.
	 *
	 * @return bool
	 */
	public static function isLodgingBusinessType( $type ) {
		return in_array( $type, self::getLodgingBusinessTypes(), true );
	}

	/**
	 * LodgingBusiness and its subtypes.
	 *
	 * These types support the checkinTime / checkoutTime properties.
	 *
	 * @since 3.0.3
	 * @return array
	 */
	public static function getLodgingBusinessTypes() {
		return [
			'LodgingBusiness',
			'BedAndBreakfast',
			'Campground',
			'Hostel',
			'Hotel',
			'Motel',
			'Resort',
			'RVPark',
		];
	}

	/**
	 * Check if the given type is a MedicalOrganization subtype.
	 *
	 * These types also inherit from LocalBusiness in Schema.org
	 * and support the priceRange property.
	 *
	 * @param string $type Schema type to check.
	 *
	 * @return bool
	 */
	public static function isMedicalOrgType( $type ) {
		$medical_types = [
			'Hospital',
			'Pharmacy',
			'Physician',
		];

		return in_array( $type, $medical_types, true );
	}

	/**
	 * Check if the given type is a MedicalOrganization / MedicalBusiness type.
	 *
	 * These types support the medicalSpecialty property.
	 *
	 * @param string $type Schema type to check.
	 *
	 * @return bool
	 */
	public static function isMedicalType( $type ) {
		return in_array( $type, self::getMedicalTypes(), true );
	}

	/**
	 * MedicalOrganization / MedicalBusiness types.
	 *
	 * These types support the medicalSpecialty property.
	 *
	 * @since 3.0.3
	 * @return array
	 */
	public static function getMedicalTypes() {
		return [
			'MedicalOrganization',
			'Hospital',
			'VeterinaryCare',
			'MedicalBusiness',
			'CommunityHealth',
			'DiagnosticLab',
			'Dentist',
			'Dermatology',
			'DietNutrition',
			'EmergencyService',
			'Geriatric',
			'Gynecologic',
			'MedicalClinic',
			'Midwifery',
			'Nursing',
			'Obstetric',
			'Oncologic',
			'Optician',
			'Optometric',
			'Otolaryngologic',
			'Pediatric',
			'Pharmacy',
			'Physician',
			'Physiotherapy',
			'PlasticSurgery',
			'Podiatric',
			'PrimaryCare',
			'Psychiatric',
			'PublicHealth',
		];
	}

	/**
	 * Flat list of LocalBusiness and every subtype offered in Site Info.
	 *
	 * The nested tree from getSiteSubTypesLocalBusiness() is unusable in a
	 * settings `depends` rule, which compares against a flat value list.
	 *
	 * @since 3.0.3
	 * @return array
	 */
	public static function getLocalBusinessTypeList() {
		$flat = [ 'LocalBusiness' ];

		$collect = function ( $types ) use ( &$collect, &$flat ) {
			foreach ( $types as $key => $value ) {
				if ( is_array( $value ) ) {
					$flat[] = $key;
					$collect( $value );
					continue;
				}
				$flat[] = $value;
			}
		};
		$collect( self::getSiteSubTypesLocalBusiness() );

		return array_values( array_unique( $flat ) );
	}

	/**
	 * Category-conditional field groups for the per-page LocalBusiness metabox.
	 *
	 * Keys match the `rtrs-lb-{key}` holder classes in SchemaMeta so the metabox
	 * JS can show/hide a field for the exact same type list the schema builder
	 * gates its output on.
	 *
	 * @since 3.0.3
	 * @return array Group key => list of schema types.
	 */
	public static function getLocalBusinessCondTypes() {
		return [
			'food'    => self::getFoodEstablishmentTypes(),
			'lodging' => self::getLodgingBusinessTypes(),
			'medical' => self::getMedicalTypes(),
		];
	}

	/**
	 * Schema.org MedicalSpecialty enumeration values.
	 *
	 * @return array Key => label pairs for the medicalSpecialty field.
	 */
	public static function getMedicalSpecialties() {
		$specialties = [
			'Anesthesia',
			'Cardiovascular',
			'CommunityHealth',
			'Dentistry',
			'Dermatology',
			'DietNutrition',
			'Emergency',
			'Endocrine',
			'Gastroenterologic',
			'Genetic',
			'Geriatric',
			'Gynecologic',
			'Hematologic',
			'Infectious',
			'LaboratoryScience',
			'Midwifery',
			'Musculoskeletal',
			'Neurologic',
			'Nursing',
			'Obstetric',
			'Oncologic',
			'Optometric',
			'Otolaryngologic',
			'Pathology',
			'Pediatric',
			'PharmacySpecialty',
			'Physiotherapy',
			'PlasticSurgery',
			'Podiatric',
			'PrimaryCare',
			'Psychiatric',
			'PublicHealth',
			'Pulmonary',
			'Radiography',
			'Renal',
			'RespiratoryTherapy',
			'Rheumatologic',
			'SpeechPathology',
			'Surgical',
			'Toxicologic',
			'Urologic',
		];

		return array_combine( $specialties, $specialties );
	}

	public static function getCountryList() {
		$countryList = [
			''   => 'Select Country',
			'AF' => 'Afghanistan',
			'AX' => 'Aland Islands',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua and Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia, Plurinational State of',
			'BQ' => 'Bonaire, Sint Eustatius and Saba',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei Darussalam',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo, the Democratic Republic of the',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => 'Côte d Ivoire',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CW' => 'Curaçao',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands (Malvinas)',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island and McDonald Islands',
			'VA' => 'Holy See (Vatican City State)',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran, Islamic Republic of',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KP' => "Korea, Democratic People's Republic of",
			'KR' => 'Korea, Republic of,',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Lao Peoples Democratic Republic',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macao',
			'MK' => 'Macedonia, the former Yugoslav Republic of',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia, Federated States of',
			'MD' => 'Moldova, Republic of',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestine, State of',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russian Federation',
			'RW' => 'Rwanda',
			'BL' => 'Saint Barthélemy',
			'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
			'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia',
			'MF' => 'Saint Martin (French part)',
			'PM' => 'Saint Pierre and Miquelon',
			'VC' => 'Saint Vincent and the Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome and Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SX' => 'Sint Maarten (Dutch part)',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia and the South Sandwich Islands',
			'SS' => 'South Sudan',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard and Jan Mayen',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syrian Arab Republic',
			'TW' => 'Taiwan, Province of China',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania, United Republic of',
			'TH' => 'Thailand',
			'TL' => 'Timor-Leste',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks and Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UM' => 'United States Minor Outlying Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela, Bolivarian Republic of',
			'VN' => 'Viet Nam',
			'VG' => 'Virgin Islands, British',
			'VI' => 'Virgin Islands, U.S.',
			'WF' => 'Wallis and Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		];

		return apply_filters('rtseo_country_list', $countryList);
	}

	public static function getLanguageList() {
		$language_list = [
			'Akan',
			'Amharic',
			'Arabic',
			'Assamese',
			'Awadhi',
			'Azerbaijani',
			'Balochi',
			'Belarusian',
			'Bengali',
			'Bhojpuri',
			'Burmese',
			'Cantonese',
			'Cebuano',
			'Chewa',
			'Chhattisgarhi',
			'Chittagonian',
			'Czech',
			'Deccan',
			'Dhundhari',
			'Dutch',
			'English',
			'French',
			'Fula',
			'Gan',
			'German',
			'Greek',
			'Gujarati',
			'Haitian Creole',
			'Hakka',
			'Haryanvi',
			'Hausa',
			'Hiligaynon',
			'Hindi / Urdu',
			'Hmong',
			'Hungarian',
			'Igbo',
			'Ilokano',
			'Italian',
			'Japanese',
			'Javanese',
			'Jin',
			'Kannada',
			'Kazakh',
			'Khmer',
			'Kinyarwanda',
			'Kirundi',
			'Konkani',
			'Korean',
			'Kurdish',
			'Madurese',
			'Magahi',
			'Maithili',
			'Malagasy',
			'Malay/Indonesian',
			'Malayalam',
			'Mandarin',
			'Marathi',
			'Marwari',
			'Min Bei',
			'Min Dong',
			'Min Nan',
			'Mossi',
			'Nepali',
			'Oriya',
			'Oromo',
			'Pashto',
			'Persian',
			'Polish',
			'Portuguese',
			'Punjabi',
			'Quechua',
			'Romanian',
			'Russian',
			'Saraiki',
			'Serbo-Croatian',
			'Shona',
			'Sindhi',
			'Sinhalese',
			'Somali',
			'Spanish',
			'Sundanese',
			'Swahili',
			'Swedish',
			'Sylheti',
			'Tagalog',
			'Tamil',
			'Telugu',
			'Thai',
			'Turkish',
			'Ukrainian',
			'Uyghur',
			'Uzbek',
			'Vietnamese',
			'Wu',
			'Xhosa',
			'Xiang',
			'Yoruba',
			'Zulu',
		];

		$language_with_key = [];

		foreach ($language_list as $value) {
			$language_with_key[$value] = $value;
		}

		return apply_filters('rtseo_language_list', $language_with_key);
	}

	/**
	 * Render the SchemaEngine AI logo icon block.
	 *
	 * Outputs a div with classes `aise-header__icon rtrs-logo` containing
	 * the plugin logo image. Suitable for use inside any admin view or template.
	 *
	 * @param bool $echo Whether to echo the HTML. Default true. Pass false to return.
	 * @return string|void HTML string when $echo is false, void otherwise.
	 */
	public static function get_logo_html( $echo = true ) {
		$html = sprintf(
			'<div class="aise-php aise-header__icon rtrs-logo"><img alt="%s" src="%s" width="40" height="40" /></div>',
			esc_attr__( 'SchemaEngine AI', 'review-schema' ),
			esc_url( rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ) )
		);

		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with esc_attr__ and esc_url.
			return;
		}

		return $html;
	}

    /* Get plugin install button
     *
     * @param $slug
     */
    public static function get_plugin_install_button( $slug ) {
        $plugin_file = $slug . '/' . $slug . '.php';
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        if ( is_plugin_active( $plugin_file ) ) {
            $label = 'Activated';
            $class = 'success-class';
            $icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        } elseif ( file_exists( $plugin_path ) ) {
            $label = 'Activate';
            $class = 'not-activated';
            $icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
        } else {
            $label = 'Install';
            $class = 'install-plugins';
            $icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        }
        ?>
        <a data-slug="<?php echo esc_attr( $slug ); ?>"
           href="https://wordpress.org/plugins/<?php echo esc_attr( $slug ); ?>/"
           target="_blank"
           class="rtrs-admin-btn <?php echo esc_attr( $class ); ?>">
            <?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded. ?>
            <?php echo esc_html( $label ); ?>
        </a>
        <?php
    }

}
