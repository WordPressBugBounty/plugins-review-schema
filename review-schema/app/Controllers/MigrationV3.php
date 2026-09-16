<?php

namespace Rtrs\Controllers;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles version 3 migrations for schema settings in the RTRS plugin.
 */
class MigrationV3 {
	/**
	 * Class Constructor.
	 */
	public function __construct() {
		if ( ! get_option( 'rtrs_migrations_3_completed', false ) ) {
			$this->sechemaSettings();
		}
		if ( ! get_option( 'rtrs_migrations_3_comment_to_review_completed', false ) ) {
			$this->commentToReview();
		}
	}

	/**
	 * Collect schema settings per post type.
	 *
	 * @return void
	 */
	public function sechemaSettings() {
		$rtrs_schema_settings = get_option( 'rtrs_schema_settings', [] );
		$types                = $rtrs_schema_settings['post_type'] ?? [];

		/**
		 * Normalize schema settings array.
		 */
		if ( ! empty( $types ) && is_array( $types ) ) {
			$types = $this->convert_old_schema_settings_to_new_array( $types );
		}

		/**
		 * Query only required post types.
		 */
		$sc_post_ids = get_posts(
			[
				'post_type'      => rtrs()->getPostType(), // ✅ type added
				'posts_per_page' => -1,
				'post_status'    => [ 'publish', 'draft' ],
				'fields'         => 'ids',
			]
		);

		$general_options = get_option(
			'rtrs_general_settings',
			[
				'review_enabled' => '',
				'schema_enabled' => 'yes',
			]
		);
		if ( ! empty( $sc_post_ids ) ) {
			foreach ( $sc_post_ids as $id ) {
				/**
				 * Detect actual post type.
				 */
				$post_type = get_post_meta( $id, 'rtrs_post_type', true );
				if ( ! $post_type ) {
					continue;
				}
				$support = get_post_meta( $id, 'rtrs_support', true );
				if ( 'schema' === $support ) {
					delete_post_meta( $id, 'rtrs_post_type' );
					delete_post_meta( $id, 'rtrs_support' );
					update_post_meta( $id, 'rtrs_support', null );
					$general_options['schema_enabled'] = $general_options['schema_enabled'] ?? 'yes';
				}
				if ( 'review-schema' === $support || 'review' === $support ) {
					update_post_meta( $id, 'rtrs_support', 1 );
				}
				if ( 'review-schema' === $support ) {
					$general_options['review_enabled'] = $general_options['review_enabled'] ?? 'yes';
					$general_options['schema_enabled'] = $general_options['schema_enabled'] ?? 'yes';
				}
				if ( 'review' === $support ) {
					$general_options['review_enabled'] = $general_options['review_enabled'] ?? 'yes';
					unset( $types[ $post_type ] );
				}
				// Rich snippet override.
				if ( get_post_meta( $id, 'rich_snippet', true ) && get_post_meta( $id, 'rich_snippet_cat', true ) ) {
					 $types[ $post_type ]['schema_type']   = get_post_meta( $id, 'rich_snippet_cat', true );
					 $types[ $post_type ]['auto_generate'] = $types[ $post_type ]['auto_generate'] ?? $post_type;
				}
			}
		}

		// Migrate post type settings to new flat format in rtrs_schema_post_types_settings.
		$post_type_settings = get_option( 'rtrs_schema_post_types_settings', [] );
		if ( ! empty( $types ) && is_array( $types ) ) {
			foreach ( $types as $type_slug => $config ) {
				if ( ! empty( $config['schema_type'] ) ) {
					$post_type_settings[ $type_slug . '_schema_type' ] = $config['schema_type'];
				}
				if ( ! empty( $config['auto_generate'] ) ) {
					$post_type_settings[ $type_slug . '_auto_generate' ] = 'yes';
				}
			}
			update_option( 'rtrs_schema_post_types_settings', $post_type_settings );
		}

		// Remove post_type from schema settings (now stored separately).
		unset( $rtrs_schema_settings['post_type'] );

		/**
		 * Migrate openingHours from legacy textarea string to structured array.
		 * Old: "Monday 11:00-14:30\r\nTuesday 17:00-21:30"
		 * New: [ { day: 'Monday', opens: '11:00', closes: '14:30' }, ... ]
		 */
		if ( ! empty( $rtrs_schema_settings['openingHours'] ) && is_string( $rtrs_schema_settings['openingHours'] ) ) {
			$lines   = preg_split( '/\r\n|\r|\n/', $rtrs_schema_settings['openingHours'] );
			$entries = [];

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}

				$parts      = explode( ' ', $line, 2 );
				$day        = ! empty( $parts[0] ) ? sanitize_text_field( $parts[0] ) : '';
				$time_range = ! empty( $parts[1] ) ? explode( '-', $parts[1] ) : [];
				$opens      = ! empty( $time_range[0] ) ? sanitize_text_field( trim( $time_range[0] ) ) : '';
				$closes     = ! empty( $time_range[1] ) ? sanitize_text_field( trim( $time_range[1] ) ) : '';

				if ( $day ) {
					$entries[] = [
						'day'    => $day,
						'opens'  => $opens,
						'closes' => $closes,
					];
				}
			}

			$rtrs_schema_settings['openingHours'] = $entries;
		}

		update_option( 'rtrs_schema_settings', $rtrs_schema_settings );
		update_option( 'rtrs_general_settings', $general_options );
		$schemaWoocommerce = get_option( 'rtrs_woocommerce_settings', [] );
		update_option( 'rtrs_schema_ecommerce_settings', $schemaWoocommerce );

		$reviewMics = get_option( 'rtrs_misc_settings', [] );
		update_option( 'rtrs_review_misc_settings', $reviewMics );

		$reviewMedia = get_option( 'rtrs_media_settings', [] );
		update_option( 'rtrs_review_media_settings', $reviewMedia );

		update_option( 'rtrs_migrations_3_completed', true );
	}

	/**
	 * Convert indexed schema config array into post_type keyed array.
	 *
	 * @param array $items Raw migration array.
	 * @return array Converted array keyed by post_type.
	 */
	public function convert_old_schema_settings_to_new_array( array $items ): array {
		$converted = [];
		foreach ( $items as $item ) {
			if ( isset( $item['schema_type'] ) && isset( $item['auto_generate'] ) ) {
				return $items;
			}
			if ( empty( $item['post_type'] ) ) {
				continue;
			}
			$schema_type = $item['schema_type'] ?? '';
			if ( $schema_type && ! in_array( $schema_type, array_keys( Functions::rich_snippet_auto_cats() ), true ) ) {
				$schema_type = 'article';
			}
			$converted[ $item['post_type'] ] = [
				'schema_type'   => $schema_type,
				'auto_generate' => $item['post_type'],
			];
		}
		return $converted;
	}
	/**
	 * Convert comment type to `review` during migration.
	 *
	 * @return void
	 */
	public function commentToReview() {
		$items = get_comments(
			[
				'type'       => 'comment',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary meta query for plugin feature.
				'meta_query' => [
					[
						'key'     => 'rating',
						'value'   => [ 1, 5 ],
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					],
				],
			]
		);
		if ( empty( $items ) ) {
			update_option( 'rtrs_migrations_3_comment_to_review_completed', true );
			return;
		}
		foreach ( $items as $comment ) {
			/**
			 * Update comment type only (no trash/delete).
			 */
			wp_update_comment(
				[
					'comment_ID'   => $comment->comment_ID,
					'comment_type' => 'review',
				]
			);
		}
		update_option( 'rtrs_migrations_3_comment_to_review_completed', true );
	}
}
