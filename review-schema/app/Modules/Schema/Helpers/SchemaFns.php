<?php


namespace Rtrs\Modules\Schema\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SchemaFns {

	/**
	 * Known ecommerce post types that default to product schema.
	 *
	 * @var array<string, bool>
	 */
	private static $ecommerce_post_types = [
		'product'         => true,
		'download'        => true,
		'fluent-products' => true,
		'sc_product'      => true,
		'wpdmpro'         => true,
	];

	/**
	 * Cached post type settings option.
	 *
	 * @var array|null
	 */
	private static $post_type_settings_cache = null;

	/**
	 * Get cached post type settings.
	 *
	 * @return array
	 */
	public static function getPostTypeSettings() {
		if ( null === self::$post_type_settings_cache ) {
			self::$post_type_settings_cache = get_option( 'rtrs_schema_post_types_settings', [] );
		}

		return self::$post_type_settings_cache;
	}

	/**
	 * Check if a post type is supported for auto schema generation.
	 *
	 * Returns the post type settings array when auto-generate is enabled.
	 * Falls back to product schema for known ecommerce post types.
	 *
	 * @param string|null $post_type The post type to check.
	 *
	 * @return array|false Post type settings or false if not supported.
	 */
	public static function getPostTypeAutoSchemaConfig( $post_type = null ) {
		if ( ! $post_type ) {
			return false;
		}

		$settings = self::getPostTypeSettings();

		$auto_generate = $settings[ $post_type . '_auto_generate' ] ?? '';
		$schema_type   = $settings[ $post_type . '_schema_type' ] ?? '';

		if ( ! empty( $schema_type ) ) {
			return [
				'schema_type'   => $schema_type,
				'auto_generate' => $auto_generate,
			];
		}

		// Fallback: auto-generate product schema for known ecommerce post types without explicit settings.
		if ( isset( self::$ecommerce_post_types[ $post_type ] ) && empty( $schema_type ) ) {
			return [
				'schema_type'   => 'product',
				'auto_generate' => '1',
			];
		}

		return false;
	}

	/**
	 * Get schema types that support the subjectOf property.
	 *
	 * @return array List of schema keys that should link to the FAQ.
	 */
	public static function get_subject_of_eligible_types() {
		return [
			'Article',
			'TechArticle',
			'NewsArticle',
			'BlogPosting',
			'Product',
			'Service',
			'Recipe',
			'Course',
			'Book',
			'SoftwareApplication',
			'HowTo',
			'Event',
			'VacationRental',
			'Restaurant',
		];
	}
	/**
	 * Find the index of a schema entry by its @type value.
	 * Supports both string and array @type values.
	 *
	 * @param  array  $schemaOutput Schema output to search.
	 * @param  string $type         The @type value to find.
	 *
	 * @return int|false Index if found, false otherwise.
	 */
	public static function find_schema_index_by_type( array $schemaOutput, string $type ) {
		foreach ( $schemaOutput as $index => $schema ) {
			if ( in_array( $type, (array) ( $schema['@type'] ?? [] ), true ) ) {
				return $index;
			}
		}
		return false;
	}
	/**
	 * Link FAQ schema to the graph — adds subjectOf to main content schema
	 * and mainEntity to WebPage.
	 *
	 * @param array  $schema_graph_list The schema graph list.
	 * @param string $faq_schema_id     The FAQ schema @id.
	 * @return array Modified schema graph list.
	 */
	public static function link_faq_to_graph( $schema_graph_list, $faq_schema_id ) {
		// Auto-detect FAQ @id from the graph when not provided.
		if ( empty( $faq_schema_id ) ) {
			foreach ( $schema_graph_list as $s ) {
				if ( 'FAQPage' === ( $s['@type'] ?? '' ) && ! empty( $s['@id'] ) ) {
					$faq_schema_id = $s['@id'];
					break;
				}
			}
		}

		if ( empty( $faq_schema_id ) ) {
			return $schema_graph_list;
		}

		$faq_ref       = [ '@id' => $faq_schema_id ];
		$content_types = self::get_subject_of_eligible_types();
		foreach ( $schema_graph_list as &$schema ) {
			$type = $schema['@type'] ?? '';

			if ( ! $type ) {
				continue;
			}
			// Add subjectOf to main content schema (Article, BlogPosting, etc.).
			if ( in_array( $type, $content_types, true ) ) {
				if ( ! self::has_ref_id( $schema, 'subjectOf', $faq_schema_id ) ) {
					if ( ! empty( $schema['subjectOf'] ) ) {
						if ( isset( $schema['subjectOf']['@id'] ) ) {
							$schema['subjectOf'] = [ $schema['subjectOf'], $faq_ref ];
						} else {
							$schema['subjectOf'][] = $faq_ref;
						}
					} else {
						$schema['subjectOf'] = $faq_ref;
					}
				}
			}
		}
		return $schema_graph_list;
	}

	/**
	 * Link media schemas (VideoObject/AudioObject) to Product and SoftwareApplication
	 * entries in the graph via the subjectOf property.
	 *
	 * @param array $schema_graph_list The schema graph list.
	 *
	 * @return array Modified schema graph list.
	 */
	public static function link_media_to_graph( $schema_graph_list ) {
		$media_types  = [ 'VideoObject', 'AudioObject' ];
		$target_types = [ 'Product', 'SoftwareApplication' ];

		// Collect @id from all media entries.
		$media_ids = [];
		foreach ( $schema_graph_list as $schema ) {
			$type = $schema['@type'] ?? '';
			if ( in_array( $type, $media_types, true ) && ! empty( $schema['@id'] ) ) {
				$media_ids[] = $schema['@id'];
			}
		}

		if ( empty( $media_ids ) ) {
			return $schema_graph_list;
		}

		foreach ( $schema_graph_list as &$schema ) {
			$type = $schema['@type'] ?? '';

			// Support both string and array @type (e.g. ['Product', 'SoftwareApplication']).
			$types = (array) $type;
			if ( ! array_intersect( $types, $target_types ) ) {
				continue;
			}

			foreach ( $media_ids as $media_id ) {
				if ( self::has_ref_id( $schema, 'subjectOf', $media_id ) ) {
					continue;
				}

				$ref = [ '@id' => $media_id ];

				if ( ! empty( $schema['subjectOf'] ) ) {
					if ( isset( $schema['subjectOf']['@id'] ) ) {
						$schema['subjectOf'] = [ $schema['subjectOf'], $ref ];
					} else {
						$schema['subjectOf'][] = $ref;
					}
				} else {
					$schema['subjectOf'] = $ref;
				}
			}
		}

		return $schema_graph_list;
	}

	/**
	 * Check if a schema property already contains a reference to a given @id.
	 *
	 * @param array  $schema  Schema node.
	 * @param string $prop    Property name (e.g. 'subjectOf', 'mainEntity').
	 * @param string $ref_id  The @id to check for.
	 *
	 * @return bool True if the @id is already referenced.
	 */
	private static function has_ref_id( $schema, $prop, $ref_id ) {
		if ( empty( $schema[ $prop ] ) ) {
			return false;
		}

		$value = $schema[ $prop ];

		// Single reference: { "@id": "..." }
		if ( isset( $value['@id'] ) ) {
			return $value['@id'] === $ref_id;
		}

		// Array of references: [ { "@id": "..." }, ... ]
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) && ( $item['@id'] ?? '' ) === $ref_id ) {
					return true;
				}
			}
		}

		return false;
	}
}
