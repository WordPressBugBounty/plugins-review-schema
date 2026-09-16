<?php
/**
 * Global content-exclusion rule shared by the SEO Report and the Internal
 * Linking system.
 *
 * A single source of truth deciding which post types are ignored so both
 * systems behave identically: an ignored type is never analyzed in the SEO
 * report, never a link target, and never a link/related-content suggestion.
 *
 * A type is ignored when ANY of these hold:
 *   - it is not publicly viewable on the front end (non-public / non-queryable);
 *   - it is a known page-builder / block / utility template type; or
 *   - the administrator selected it in the SEO Report "Ignore Content" setting.
 *
 * @package Rtrs\Helpers
 * @since   1.0.0
 */

namespace Rtrs\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentIgnore
 */
class ContentIgnore {

	/** Option that stores the admin-selected ignore list. */
	const OPTION = 'rtrs_seo_report_settings';

	/**
	 * Known page-builder / block / utility template post types that are never
	 * reader-facing content, even when registered as public.
	 *
	 * @return string[]
	 */
	private static function template_types() {
		return [
			'elementor_library', 'e-landing-page', 'e-floating-buttons',
			'bricks_template', 'fl-builder-template', 'fl-theme-layout',
			'themer_layout', 'ct_template', 'oceanwp_library',
			'elementskit_template', 'elementskit_content',
			'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation',
			'jet-engine', 'jet-theme-core', 'jet-menu',
			'rtsb_builder',
		];
	}

	/**
	 * Post types the administrator manually added to the ignore list.
	 *
	 * @return string[]
	 */
	public static function admin_ignored() {
		$value = \rtrs()->get_options( self::OPTION, [ 'ignore_post_types', [] ] );
		return is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : [];
	}

	/**
	 * Whether a post type must be excluded from SEO analysis and internal linking.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public static function is_ignored( $post_type ) {
		$post_type = (string) $post_type;
		if ( '' === $post_type ) {
			return false;
		}

		$ignored = false;

		// Default: not publicly viewable → ignored.
		if ( function_exists( 'is_post_type_viewable' ) && ! is_post_type_viewable( $post_type ) ) {
			$ignored = true;
		}
		// Known builder / block / utility templates → ignored.
		if ( ! $ignored && in_array( $post_type, self::template_types(), true ) ) {
			$ignored = true;
		}
		// Administrator-selected → ignored.
		if ( ! $ignored && in_array( $post_type, self::admin_ignored(), true ) ) {
			$ignored = true;
		}

		/**
		 * Filter whether a post type is ignored by the SEO report and internal
		 * linking. Return true to exclude it globally.
		 *
		 * @param bool   $ignored   Whether the type is ignored.
		 * @param string $post_type The post type name.
		 */
		return (bool) apply_filters( 'rtrs_is_ignored_post_type', $ignored, $post_type );
	}

	/**
	 * Public, non-ignored post types eligible for SEO analysis and as internal-
	 * link targets.
	 *
	 * @return string[]
	 */
	public static function allowed_post_types() {
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );

		$types = array_values(
			array_filter(
				array_values( $types ),
				function ( $type ) {
					return ! self::is_ignored( $type );
				}
			)
		);

		return $types;
	}

	/**
	 * Public post types offered as options in the "Ignore Content" setting
	 * (slug => singular label). Non-public types are always ignored and are not
	 * shown here.
	 *
	 * @return array<string,string>
	 */
	public static function selectable_post_types() {
		$objects = get_post_types( [ 'public' => true ], 'objects' );
		unset( $objects['attachment'] );

		$options = [];
		foreach ( $objects as $object ) {
			if ( in_array( $object->name, self::template_types(), true ) ) {
				continue; // Templates are always ignored — no need to offer them.
			}
			$label                     = isset( $object->labels->singular_name ) && '' !== $object->labels->singular_name ? $object->labels->singular_name : $object->name;
			$options[ $object->name ] = $label . ' (' . $object->name . ')';
		}

		return $options;
	}
}
