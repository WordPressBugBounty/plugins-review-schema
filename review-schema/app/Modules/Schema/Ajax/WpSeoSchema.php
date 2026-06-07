<?php
/**
 * WP SEO Structured Data Schema Migration.
 *
 * Handles schema data migration from the WP SEO Structured Data Schema plugin
 * into the RT Review Schema plugin via background cron processing.
 *
 * @package Rtrs\Modules\Schema\Ajax
 */

namespace Rtrs\Modules\Schema\Ajax;

use KcSeoOptions;
use Rtrs\Modules\Schema\Admin\Meta\SchemaMeta;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WpSeoSchema
 *
 * Migrates settings and per-post schema meta from WP SEO Structured Data Schema.
 * Processes one post per cron event to avoid timeouts on large sites.
 */
class WpSeoSchema {

	use SingletonTrait;

	const PROGRESS_KEY = 'rtrs_WPSEMPlugins_migration_progress';
	const BATCH_SIZE   = 10;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Start the migration.
	 *
	 * Runs settings migration immediately, then schedules
	 * per-post schema migration via cron (one post at a time).
	 *
	 * @return bool True on success.
	 */
	public function start() {
		$this->migrate_settings();

		global $wpdb, $KcSeoWPSchema;

		$main_settings = get_option( $KcSeoWPSchema->options['main_settings'] );
		$post_types    = ! empty( $main_settings['post-type'] ) ? $main_settings['post-type'] : [];

		if ( empty( $post_types ) ) {
			update_option(
				self::PROGRESS_KEY,
				[
					'status'    => 'completed',
					'processed' => 0,
					'total'     => 0,
				]
			);
			return true;
		}

		$type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// $type_placeholders is generated %s sequence built from $post_types count; all values are passed via $wpdb->prepare().
		$total             = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ($type_placeholders) AND post_status = 'publish'",
				...$post_types
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! $total ) {
			update_option(
				self::PROGRESS_KEY,
				[
					'status'    => 'completed',
					'processed' => 0,
					'total'     => 0,
				]
			);
			return true;
		}

		update_option(
			self::PROGRESS_KEY,
			[
				'last_post_id' => 0,
				'processed'    => 0,
				'total'        => $total,
				'post_types'   => $post_types,
				'status'       => 'running',
			]
		);

		return true;
	}

	/**
	 * Process the next batch of posts in the migration queue.
	 *
	 * Called by the progress polling endpoint. Processes up to
	 * BATCH_SIZE posts per call to avoid request timeouts.
	 *
	 * @return void
	 */
	public function process_batch() {
		$progress = get_option( self::PROGRESS_KEY, [] );

		if ( empty( $progress ) || 'running' !== ( $progress['status'] ?? '' ) ) {
			return;
		}

		global $wpdb;

		$post_types   = $progress['post_types'] ?? [];
		$meta_key_map = $this->get_meta_key_map();
		$meta_keys    = array_keys( $meta_key_map );

		if ( empty( $post_types ) || empty( $meta_keys ) ) {
			$progress['status'] = 'completed';
			update_option( self::PROGRESS_KEY, $progress );
			return;
		}

		$type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$key_placeholders  = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );

		$batch_count = 0;

		while ( $batch_count < self::BATCH_SIZE ) {
			// Get the next post using cursor.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			// $type_placeholders built from $post_types; all values are passed via $wpdb->prepare().
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($type_placeholders) AND post_status = 'publish' ORDER BY ID ASC LIMIT 1",
					...array_merge( [ $progress['last_post_id'] ], $post_types )
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			if ( ! $post_id ) {
				// No more posts to process.
				$progress['status'] = 'completed';
				update_option( self::PROGRESS_KEY, $progress );
				return;
			}

			// Check this post's schema meta.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			// $key_placeholders built from $meta_keys; all values are passed via $wpdb->prepare().
			$active_meta_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ($key_placeholders) AND meta_value LIKE %s",
					...array_merge( [ $post_id ], $meta_keys, [ '%"active";s:1:"1"%' ] )
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			// Migrate this post's schema data if it has active schemas.
			if ( ! empty( $active_meta_keys ) ) {
				$schema_keys = [];
				foreach ( $active_meta_keys as $meta_key ) {
					if ( isset( $meta_key_map[ $meta_key ] ) ) {
						$rtrs_schema_type = $meta_key_map[ $meta_key ];
						$schema_keys[]    = $rtrs_schema_type;
						$metaData         = get_post_meta( $post_id, $meta_key, true );
						$this->migrate_post_schema_meta( $post_id, $rtrs_schema_type, $metaData );
					}
				}
				$schema_keys = array_unique( $schema_keys );
				if ( ! empty( $schema_keys ) ) {
					delete_post_meta( $post_id, '_rtrs_rich_snippet_cat' );
					foreach ( $schema_keys as $schema ) {
						add_post_meta( $post_id, '_rtrs_rich_snippet_cat', trim( $schema ) );
					}
					update_post_meta( $post_id, '_rtrs_custom_rich_snippet', 1 );
				}
			}

			// Move cursor forward.
			$progress['last_post_id'] = (int) $post_id;
			$progress['processed']   += 1;
			$batch_count++;
		}

		update_option( self::PROGRESS_KEY, $progress );
	}

	/**
	 * Migrate individual post schema meta data.
	 *
	 * @param int    $post_id          The post ID.
	 * @param string $rtrs_schema_type Schema type key (e.g. 'article', 'event').
	 * @param mixed  $_kseo_metaData   The source meta data array.
	 *
	 * @return void
	 */
	public function migrate_post_schema_meta( $post_id, $rtrs_schema_type, $_kseo_metaData ) {
		$generated_data = [];

		switch ( $rtrs_schema_type ) {
			case 'article':
				$generated_data = $this->get_article_schema_field_data_map( $_kseo_metaData );
				break;
			case 'news_article':
				$generated_data = $this->get_news_article_schema_field_data_map( $_kseo_metaData );
				break;
			case 'blog_posting':
				$generated_data = $this->get_blog_posting_schema_field_data_map( $_kseo_metaData );
				break;
			case 'tech_article':
				$generated_data = $this->get_tech_article_schema_field_data_map( $_kseo_metaData );
				break;
			case 'event':
				$generated_data = $this->get_event_schema_field_data_map( $_kseo_metaData );
				break;
			case 'faq':
				$generated_data = $this->get_faq_schema_field_data_map( $_kseo_metaData );
				break;
			case 'service':
				$generated_data = $this->get_service_schema_field_data_map( $_kseo_metaData );
				break;
			case 'question_answer':
				$generated_data = $this->get_question_answer_schema_field_data_map( $_kseo_metaData );
				break;
			case 'about':
				$generated_data = $this->get_about_schema_field_data_map( $_kseo_metaData );
				break;
			case 'contact':
				$generated_data = $this->get_contact_schema_field_data_map( $_kseo_metaData );
				break;
			case 'person':
				$generated_data = $this->get_person_schema_field_data_map( $_kseo_metaData );
				break;
			case 'movie':
				$generated_data = $this->get_movie_schema_field_data_map( $_kseo_metaData );
				break;
			case 'audio':
				$generated_data = $this->get_audio_schema_field_data_map( $_kseo_metaData );
				break;
			case 'video':
				$generated_data = $this->get_video_schema_field_data_map( $_kseo_metaData );
				break;
			case 'profile_page':
				$generated_data = $this->get_profile_page_schema_field_data_map( $_kseo_metaData );
				break;
			case 'medical_webpage':
				$generated_data = $this->get_medical_webpage_schema_field_data_map( $_kseo_metaData );
				break;
			case 'product':
				$generated_data = $this->get_product_schema_field_data_map( $_kseo_metaData );
				break;
			case 'course':
				$generated_data = $this->get_course_schema_field_data_map( $_kseo_metaData );
				break;
			case 'job_posting':
				$generated_data = $this->get_job_posting_schema_field_data_map( $_kseo_metaData );
				break;
			case 'recipe':
				$generated_data = $this->get_recipe_schema_field_data_map( $_kseo_metaData );
				break;
			case 'software_app':
				$generated_data = $this->get_software_app_schema_field_data_map( $_kseo_metaData );
				break;
			case 'book':
				$generated_data = $this->get_book_schema_field_data_map( $_kseo_metaData );
				break;
			case 'Restaurant':
				$generated_data = $this->get_restaurant_schema_field_data_map( $_kseo_metaData );
				break;
			case 'tv_series':
				$generated_data = $this->get_tv_series_schema_field_data_map( $_kseo_metaData );
				break;
			case 'vacation_rental':
				$generated_data = $this->get_vacation_rental_schema_field_data_map( $_kseo_metaData );
				break;
			case 'vehicle_listing':
				$generated_data = $this->get_vehicle_listing_schema_field_data_map( $_kseo_metaData );
				break;
		}

		if ( ! empty( $generated_data ) ) {
			$generated_data = $this->trim_recursive( $generated_data );
			$meta_key       = 'rtrs_' . $rtrs_schema_type . '_schema';
			update_post_meta( $post_id, $meta_key, $generated_data );
			update_post_meta( $post_id, '_rtrs_custom_rich_snippet', 1 );
		}
	}

	// ------------------------------------------------------------------
	// Helper: convert image URL to attachment ID.
	// ------------------------------------------------------------------

	/**
	 * Convert an image URL to a WordPress attachment ID.
	 *
	 * @param mixed $image Image URL string or empty value.
	 *
	 * @return int Attachment ID or 0.
	 */
	private function image_url_to_id( $image ) {
		if ( empty( $image ) ) {
			return 0;
		}
		if ( is_numeric( $image ) ) {
			return absint( $image );
		}
		$id = attachment_url_to_postid( $image );
		return $id ? $id : 0;
	}

	/**
	 * Recursively trim all string values in an array.
	 *
	 * Prevents trailing whitespace from being URL-encoded as %20.
	 *
	 * @param mixed $data Data to trim.
	 *
	 * @return mixed Trimmed data.
	 */
	private function trim_recursive( $data ) {
		if ( is_string( $data ) ) {
			$data = trim( $data );
			// Remove URL-encoded trailing spaces (%20).
			$data = preg_replace( '/(%20)+$/', '', $data );
			return $data;
		}
		if ( is_array( $data ) ) {
			return array_map( [ $this, 'trim_recursive' ], $data );
		}
		return $data;
	}

	/**
	 * Convert a date string to ISO 8601 (W3C) format.
	 *
	 * Handles formats like "2021-08-25 14:20:00" → "2021-08-25T14:20:00+06:00".
	 * Already-formatted ISO 8601 strings are returned as-is.
	 *
	 * @param string $date Date string.
	 *
	 * @return string ISO 8601 formatted date, or original string if parsing fails.
	 */
	private function to_iso8601( $date ) {
		if ( empty( $date ) ) {
			return '';
		}
		// Already in ISO 8601 format.
		if ( strpos( $date, 'T' ) !== false ) {
			return $date;
		}
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return $date;
		}
		return wp_date( DATE_W3C, $timestamp );
	}

	/**
	 * Extract the first element from a wp-seo group array.
	 *
	 * WP SEO stores non-duplicate groups as indexed arrays [0 => [...]].
	 *
	 * @param array  $source    The source meta data.
	 * @param string $group_key The group key (e.g. 'video', 'audio').
	 *
	 * @return array The first group element or empty array.
	 */
	private function extract_group( $source, $group_key ) {
		if ( ! empty( $source[ $group_key ] ) && is_array( $source[ $group_key ] ) ) {
			// If indexed array, take first element.
			if ( isset( $source[ $group_key ][0] ) && is_array( $source[ $group_key ][0] ) ) {
				return $source[ $group_key ][0];
			}
			return $source[ $group_key ];
		}
		return [];
	}

	/**
	 * Map video group fields from wp-seo to review-schema format.
	 *
	 * @param array $source Source meta data containing 'video' key.
	 *
	 * @return array Video data in review-schema format [0 => [...]].
	 */
	private function map_video_group( $source ) {
		if ( empty( $source['video'] ) || ! is_array( $source['video'] ) ) {
			return [];
		}
		$items = [];
		$list  = isset( $source['video'][0] ) && is_array( $source['video'][0] )
			? $source['video']
			: [ $source['video'] ];

		foreach ( $list as $v ) {
			$item = [
				'name'         => $v['name'] ?? '',
				'description'  => $v['description'] ?? '',
				'thumbnailUrl' => $this->image_url_to_id( $v['thumbnailUrl'] ?? '' ),
				'contentUrl'   => $v['contentUrl'] ?? '',
				'embedUrl'     => $v['embedUrl'] ?? '',
				'uploadDate'   => $this->to_iso8601( $v['uploadDate'] ?? '' ),
				'duration'     => $v['duration'] ?? '',
			];
			if ( array_filter( $item ) ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Map audio group fields from wp-seo to review-schema format.
	 *
	 * @param array $source Source meta data containing 'audio' key.
	 *
	 * @return array Audio data in review-schema format [0 => [...]].
	 */
	private function map_audio_group( $source ) {
		if ( empty( $source['audio'] ) || ! is_array( $source['audio'] ) ) {
			return [];
		}
		$items = [];
		$list  = isset( $source['audio'][0] ) && is_array( $source['audio'][0] )
			? $source['audio']
			: [ $source['audio'] ];

		foreach ( $list as $a ) {
			$item = [
				'name'           => $a['name'] ?? '',
				'description'    => $a['description'] ?? '',
				'duration'       => $a['duration'] ?? '',
				'contentUrl'     => $a['contentUrl'] ?? '',
				'encodingFormat' => $a['encodingFormat'] ?? '',
			];
			if ( array_filter( $item ) ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Map review sub-fields from wp-seo meta into review-schema format.
	 *
	 * @param array $source Source meta data with review_* keys.
	 *
	 * @return array Review fields for review-schema.
	 */
	private function map_review_fields( $source ) {
		$fields = [];
		if ( ! empty( $source['review_active'] ) ) {
			$fields['review_active']        = 'show';
			$fields['review_author']        = $source['review_author'] ?? '';
			$fields['review_author_sameAs'] = $source['review_author_sameAs'] ?? '';
			$fields['review_body']          = $source['review_body'] ?? '';
			$fields['review_datePublished'] = $this->to_iso8601( $source['review_datePublished'] ?? '' );
			$fields['review_ratingValue']   = $source['review_ratingValue'] ?? '';
			$fields['review_bestRating']    = $source['review_bestRating'] ?? '';
			$fields['review_worstRating']   = $source['review_worstRating'] ?? '';
			if ( ! empty( $source['review_sameAs'] ) ) {
				$fields['review_sameAs'] = $source['review_sameAs'];
			}
		}
		return $fields;
	}

	// ------------------------------------------------------------------
	// Schema field data mappers.
	// ------------------------------------------------------------------

	/**
	 * Map article schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_article_schema_field_data_map( $_kseo ) {
		$data = [
			'status'              => 'show',
			'headline'            => $_kseo['headline'] ?? '',
			'mainEntityOfPage'    => $_kseo['mainEntityOfPage'] ?? '',
			'author'              => $_kseo['author'] ?? '',
			'author_url'          => $_kseo['author_url'] ?? '',
			'image'               => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'datePublished'       => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
			'dateModified'        => $this->to_iso8601( $_kseo['dateModified'] ?? '' ),
			'description'         => $_kseo['description'] ?? '',
			'articleBody'         => $_kseo['articleBody'] ?? '',
			'alternativeHeadline' => $_kseo['alternativeHeadline'] ?? '',
		];

		$video = $this->map_video_group( $_kseo );
		if ( ! empty( $video ) ) {
			$data['video'] = $video;
		}
		$audio = $this->map_audio_group( $_kseo );
		if ( ! empty( $audio ) ) {
			$data['audio'] = $audio;
		}

		return [ $data ];
	}

	/**
	 * Map news article schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_news_article_schema_field_data_map( $_kseo ) {
		$data = [
			'status'         => 'show',
			'headline'       => $_kseo['headline'] ?? '',
			'author'         => $_kseo['author'] ?? '',
			'author_url'     => $_kseo['author_url'] ?? '',
			'image'          => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'datePublished'  => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
			'dateModified'   => $this->to_iso8601( $_kseo['dateModified'] ?? '' ),
			'description'    => $_kseo['description'] ?? '',
			'articleBody'    => $_kseo['articleBody'] ?? '',
		];

		$video = $this->map_video_group( $_kseo );
		if ( ! empty( $video ) ) {
			$data['video'] = $video;
		}
		$audio = $this->map_audio_group( $_kseo );
		if ( ! empty( $audio ) ) {
			$data['audio'] = $audio;
		}

		return [ $data ];
	}

	/**
	 * Map blog posting schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_blog_posting_schema_field_data_map( $_kseo ) {
		$data = [
			'status'           => 'show',
			'headline'         => $_kseo['headline'] ?? '',
			'mainEntityOfPage' => $_kseo['mainEntityOfPage'] ?? '',
			'author'           => $_kseo['author'] ?? '',
			'author_url'       => $_kseo['author_url'] ?? '',
			'image'            => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'datePublished'    => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
			'dateModified'     => $this->to_iso8601( $_kseo['dateModified'] ?? '' ),
			'description'      => $_kseo['description'] ?? '',
			'articleBody'      => $_kseo['articleBody'] ?? '',
		];

		$video = $this->map_video_group( $_kseo );
		if ( ! empty( $video ) ) {
			$data['video'] = $video;
		}
		$audio = $this->map_audio_group( $_kseo );
		if ( ! empty( $audio ) ) {
			$data['audio'] = $audio;
		}

		return [ $data ];
	}

	/**
	 * Map tech article schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_tech_article_schema_field_data_map( $_kseo ) {
		$data = [
			'status'           => 'show',
			'name'             => $_kseo['headline'] ?? '',
			'author_type'      => $_kseo['author_type'] ?? '',
			'author'           => $_kseo['author'] ?? '',
			'author_url'       => $_kseo['author_url'] ?? '',
			'auth_description' => $_kseo['author_description'] ?? '',
			'image'            => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'datePublished'    => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
			'dateModified'     => $this->to_iso8601( $_kseo['dateModified'] ?? '' ),
			'description'      => $_kseo['description'] ?? '',
			'articleBody'      => $_kseo['articleBody'] ?? '',
			'keywords'         => $_kseo['keywords'] ?? '',
		];

		$video = $this->map_video_group( $_kseo );
		if ( ! empty( $video ) ) {
			$data['video'] = $video;
		}
		$audio = $this->map_audio_group( $_kseo );
		if ( ! empty( $audio ) ) {
			$data['audio'] = $audio;
		}

		return [ $data ];
	}

	/**
	 * Map event schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_event_schema_field_data_map( $_kseo ) {
		$data = [
			'status'              => 'show',
			'name'                => $_kseo['name'] ?? '',
			'locationName'        => $_kseo['locationName'] ?? '',
			'locationAddress'     => $_kseo['locationAddress'] ?? '',
			'startDate'           => $this->to_iso8601( $_kseo['startDate'] ?? '' ),
			'endDate'             => $this->to_iso8601( $_kseo['endDate'] ?? '' ),
			'description'         => $_kseo['description'] ?? '',
			'performerName'       => $_kseo['performerName'] ?? '',
			'image'               => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'price'               => $_kseo['price'] ?? '',
			'priceCurrency'       => $_kseo['priceCurrency'] ?? '',
			'availability'        => $_kseo['availability'] ?? '',
			'eventStatus'         => $_kseo['eventStatus'] ?? '',
			'eventAttendanceMode' => $_kseo['EventAttendanceMode'] ?? '',
			'validFrom'           => $this->to_iso8601( $_kseo['validFrom'] ?? '' ),
			'url'                 => $_kseo['url'] ?? '',
			'organizerName'       => $_kseo['organizer'] ?? '',
			'organizerUrl'        => $_kseo['organizerUrl'] ?? '',
		];

		$data = array_merge( $data, $this->map_review_fields( $_kseo ) );

		return [ $data ];
	}

	/**
	 * Map FAQ schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_faq_schema_field_data_map( $_kseo ) {
		$faqs = [];
		if ( ! empty( $_kseo['faq_items'] ) && is_array( $_kseo['faq_items'] ) ) {
			foreach ( $_kseo['faq_items'] as $item ) {
				if ( ! empty( $item['question'] ) ) {
					$faqs[] = [
						'ques' => $item['question'],
						'ans'  => $item['answer'] ?? '',
					];
				}
			}
		}

		return [
			[
				'status' => 'show',
				'faqs'   => $faqs,
			],
		];
	}

	/**
	 * Map service schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_service_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'          => 'show',
				'name'            => $_kseo['name'] ?? '',
				'serviceType'     => $_kseo['serviceType'] ?? '',
				'additionalType'  => $_kseo['additionalType'] ?? '',
				'award'           => $_kseo['award'] ?? '',
				'category'        => $_kseo['category'] ?? '',
				'description'     => $_kseo['description'] ?? '',
				'image'           => $this->image_url_to_id( $_kseo['image'] ?? '' ),
				'mainEntityOfPage' => $_kseo['mainEntityOfPage'] ?? '',
				'url'             => $_kseo['url'] ?? '',
				'alternateName'   => $_kseo['alternateName'] ?? '',
			],
		];
	}

	/**
	 * Map question/answer schema fields.
	 *
	 * WP SEO stores Q&A in flat fields; review-schema uses an answers group.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_question_answer_schema_field_data_map( $_kseo ) {
		$answers = [];

		// Accepted answer.
		if ( ! empty( $_kseo['accepted_answer'] ) ) {
			$answers[] = [
				'text'        => $_kseo['accepted_answer'],
				'dateCreated' => $this->to_iso8601( $_kseo['accepted_answer_dateCreated'] ?? '' ),
				'upvoteCount' => $_kseo['accepted_answer_upvoteCount'] ?? '',
				'author'      => $_kseo['accepted_answer_author'] ?? '',
				'author_url'  => $_kseo['accepted_answer_url'] ?? '',
				'answerType'  => 'accepted',
			];
		}

		// Suggested answers.
		if ( ! empty( $_kseo['suggested_answer'] ) && is_array( $_kseo['suggested_answer'] ) ) {
			foreach ( $_kseo['suggested_answer'] as $sa ) {
				if ( is_array( $sa ) && ! empty( $sa['text'] ?? $sa['suggested_answer'] ?? '' ) ) {
					$answers[] = [
						'text'        => $sa['text'] ?? $sa['suggested_answer'] ?? '',
						'dateCreated' => $this->to_iso8601( $sa['suggested_answer_dateCreated'] ?? $sa['dateCreated'] ?? '' ),
						'upvoteCount' => $sa['suggested_answer_upvoteCount'] ?? $sa['upvoteCount'] ?? '',
						'author'      => $sa['suggested_answer_author'] ?? $sa['author'] ?? '',
						'author_url'  => $sa['suggested_answer_url'] ?? $sa['url'] ?? '',
						'answerType'  => 'normal',
					];
				}
			}
		}

		return [
			[
				'status'             => 'show',
				'name'               => $_kseo['question'] ?? '',
				'text'               => $_kseo['question_text'] ?? '',
				'answerCount'        => $_kseo['answerCount'] ?? '',
				'upvoteCount'        => $_kseo['question_upvoteCount'] ?? '',
				'dateCreated'        => $this->to_iso8601( $_kseo['question_dateCreated'] ?? '' ),
				'author'             => $_kseo['question_author'] ?? '',
				'question_author_url' => '',
				'answers'            => $answers,
			],
		];
	}

	/**
	 * Map about page schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_about_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'      => 'show',
				'name'        => $_kseo['name'] ?? '',
				'description' => $_kseo['description'] ?? '',
				'image'       => $this->image_url_to_id( $_kseo['image'] ?? '' ),
				'url'         => $_kseo['url'] ?? '',
				'sameAs'      => $_kseo['sameAs'] ?? '',
			],
		];
	}

	/**
	 * Map contact page schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_contact_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'      => 'show',
				'name'        => $_kseo['name'] ?? '',
				'description' => $_kseo['description'] ?? '',
				'image'       => $this->image_url_to_id( $_kseo['image'] ?? '' ),
				'url'         => $_kseo['url'] ?? '',
				'sameAs'      => $_kseo['sameAs'] ?? '',
			],
		];
	}

	/**
	 * Map person schema fields.
	 *
	 * WP SEO stores address fields flat; review-schema uses nested addresses group.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_person_schema_field_data_map( $_kseo ) {
		$addresses = [];
		if ( ! empty( $_kseo['streetAddress'] ) || ! empty( $_kseo['addressLocality'] ) ) {
			$addresses[] = [
				'streetAddress'   => $_kseo['streetAddress'] ?? '',
				'addressLocality' => $_kseo['addressLocality'] ?? '',
				'addressRegion'   => $_kseo['addressRegion'] ?? '',
				'postalCode'      => $_kseo['postalCode'] ?? '',
				'addressCountry'  => '',
			];
		}

		$data = [
			'status'      => 'show',
			'name'        => $_kseo['name'] ?? '',
			'image'       => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'telephone'   => $_kseo['telephone'] ?? '',
			'email'       => $_kseo['email'] ?? '',
			'jobTitle'    => $_kseo['jobTitle'] ?? '',
			'description' => '',
			'birthPlace'  => $_kseo['birthPlace'] ?? '',
			'birthDate'   => $this->to_iso8601( $_kseo['birthDate'] ?? '' ),
			'gender'      => $_kseo['gender'] ?? '',
			'nationality' => $_kseo['nationality'] ?? '',
			'sameAs'      => $_kseo['sameAs'] ?? '',
		];

		if ( ! empty( $addresses ) ) {
			$data['addresses'] = $addresses;
		}

		return [ $data ];
	}

	/**
	 * Map movie schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_movie_schema_field_data_map( $_kseo ) {
		$data = [
			'status'      => 'show',
			'name'        => $_kseo['name'] ?? '',
			'description' => $_kseo['description'] ?? '',
			'duration'    => $_kseo['duration'] ?? '',
			'dateCreated' => $this->to_iso8601( $_kseo['dateCreated'] ?? '' ),
			'image'       => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'director'    => $_kseo['director'] ?? '',
			'author'      => $_kseo['author'] ?? '',
			'actor'       => $_kseo['actor'] ?? '',
		];

		$data = array_merge( $data, $this->map_review_fields( $_kseo ) );

		return [ $data ];
	}

	/**
	 * Map standalone audio schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_audio_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'         => 'show',
				'name'           => $_kseo['name'] ?? '',
				'description'    => $_kseo['description'] ?? '',
				'duration'       => $_kseo['duration'] ?? '',
				'contentUrl'     => $_kseo['contentUrl'] ?? '',
				'encodingFormat' => $_kseo['encodingFormat'] ?? '',
			],
		];
	}

	/**
	 * Map standalone video schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_video_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'       => 'show',
				'name'         => $_kseo['name'] ?? '',
				'description'  => $_kseo['description'] ?? '',
				'thumbnailUrl' => $_kseo['thumbnailUrl'] ?? '',
				'uploadDate'   => $this->to_iso8601( $_kseo['uploadDate'] ?? '' ),
				'duration'     => $_kseo['duration'] ?? '',
				'contentUrl'   => $_kseo['contentUrl'] ?? '',
				'embedUrl'     => $_kseo['embedUrl'] ?? '',
			],
		];
	}

	/**
	 * Map profile page schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_profile_page_schema_field_data_map( $_kseo ) {
		$data = [
			'status'        => 'show',
			'profileFor'    => $_kseo['profileFor'] ?? '',
			'name'          => $_kseo['name'] ?? '',
			'alternateName' => $_kseo['alternateName'] ?? '',
			'gender'        => $_kseo['gender'] ?? '',
			'image'         => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'description'   => $_kseo['description'] ?? '',
			'sameAs'        => $_kseo['sameAs'] ?? '',
		];

		// Map worksFor group.
		if ( ! empty( $_kseo['worksFor'] ) && is_array( $_kseo['worksFor'] ) ) {
			$works_for = [];
			$list      = isset( $_kseo['worksFor'][0] ) && is_array( $_kseo['worksFor'][0] )
				? $_kseo['worksFor']
				: [ $_kseo['worksFor'] ];

			foreach ( $list as $wf ) {
				$works_for[] = [
					'name' => $wf['name'] ?? '',
					'url'  => $wf['url'] ?? '',
				];
			}
			$data['worksFor'] = $works_for;
		}

		return [ $data ];
	}

	/**
	 * Map medical webpage schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_medical_webpage_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'        => 'show',
				'name'          => $_kseo['headline'] ?? '',
				'specialty_url' => $_kseo['specialty_url'] ?? '',
				'image'         => $this->image_url_to_id( $_kseo['image'] ?? '' ),
				'datePublished' => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
				'dateModified'  => $this->to_iso8601( $_kseo['dateModified'] ?? '' ),
				'lastreviewed'  => $this->to_iso8601( $_kseo['lastreviewed'] ?? '' ),
				'about'         => $_kseo['about'] ?? '',
				'description'   => $_kseo['description'] ?? '',
				'keywords'      => $_kseo['keywords'] ?? '',
			],
		];
	}

	/**
	 * Map product schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_product_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'            => 'show',
				'name'              => $_kseo['name'] ?? '',
				'image'             => $this->image_url_to_id( $_kseo['image'] ?? '' ),
				'description'       => $_kseo['description'] ?? '',
				'sku'               => $_kseo['sku'] ?? '',
				'brand'             => $_kseo['brand'] ?? '',
				'identifier_type'   => $_kseo['identifier_type'] ?? '',
				'identifier'        => $_kseo['identifier'] ?? '',
				'reviewRatingValue' => $_kseo['reviewRatingValue'] ?? '',
				'reviewBestRating'  => $_kseo['reviewBestRating'] ?? '',
				'reviewWorstRating' => $_kseo['reviewWorstRating'] ?? '',
				'reviewAuthor'      => $_kseo['reviewAuthor'] ?? '',
				'ratingValue'       => $_kseo['ratingValue'] ?? '',
				'reviewCount'       => $_kseo['reviewCount'] ?? '',
				'priceCurrency'     => $_kseo['priceCurrency'] ?? '',
				'price'             => $_kseo['price'] ?? '',
				'priceValidUntil'   => $this->to_iso8601( $_kseo['priceValidUntil'] ?? '' ),
				'availability'      => $_kseo['availability'] ?? '',
				'itemCondition'     => $_kseo['itemCondition'] ?? '',
				'url'               => $_kseo['url'] ?? '',
			],
		];
	}

	/**
	 * Map course schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_course_schema_field_data_map( $_kseo ) {
		$data = [
			'status'          => 'show',
			'name'            => $_kseo['name'] ?? '',
			'description'     => $_kseo['description'] ?? '',
			'provider'        => $_kseo['provider'] ?? '',
			'courseMode'       => $_kseo['courseMode'] ?? '',
			'duration'        => $_kseo['duration'] ?? '',
			'repeatFrequency' => $_kseo['repeatFrequency'] ?? '',
			'repeatCount'     => $_kseo['repeatCount'] ?? '',
			'startDate'       => $this->to_iso8601( $_kseo['startDate'] ?? '' ),
			'endDate'         => $this->to_iso8601( $_kseo['endDate'] ?? '' ),
			'locationName'    => $_kseo['locationName'] ?? '',
			'locationAddress' => $_kseo['locationAddress'] ?? '',
			'image'           => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'category'        => $_kseo['category'] ?? '',
			'price'           => $_kseo['price'] ?? '',
			'priceCurrency'   => $_kseo['priceCurrency'] ?? '',
			'availability'    => $_kseo['availability'] ?? '',
			'url'             => $_kseo['url'] ?? '',
			'validFrom'       => $this->to_iso8601( $_kseo['validFrom'] ?? '' ),
			'performerType'   => $_kseo['performerType'] ?? '',
			'performerName'   => $_kseo['performerName'] ?? '',
		];

		$data = array_merge( $data, $this->map_review_fields( $_kseo ) );

		return [ $data ];
	}

	/**
	 * Map job posting schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_job_posting_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'                => 'show',
				'title'                 => $_kseo['title'] ?? '',
				'salaryAmount'          => $_kseo['salaryAmount'] ?? '',
				'currency'              => $_kseo['currency'] ?? '',
				'salaryAt'              => $_kseo['salaryAt'] ?? '',
				'validThrough'          => $this->to_iso8601( $_kseo['validThrough'] ?? '' ),
				'description'           => $_kseo['description'] ?? '',
				'employmentType'        => $_kseo['employmentType'] ?? '',
				'workHours'             => $_kseo['workHours'] ?? '',
				'hiringOrganization'    => $_kseo['hiringOrganization'] ?? '',
				'addressLocality'       => $_kseo['addressLocality'] ?? '',
				'addressRegion'         => $_kseo['addressRegion'] ?? '',
				'postalCode'            => $_kseo['postalCode'] ?? '',
				'streetAddress'         => $_kseo['streetAddress'] ?? '',
				'jobBenefits'           => $_kseo['jobBenefits'] ?? '',
				'educationRequirements' => $_kseo['educationRequirements'] ?? '',
				'experienceRequirements' => $_kseo['experienceRequirements'] ?? '',
				'industry'              => $_kseo['industry'] ?? '',
				'occupationalCategory'  => $_kseo['occupationalCategory'] ?? '',
				'qualifications'        => $_kseo['qualifications'] ?? '',
				'responsibilities'      => $_kseo['responsibilities'] ?? '',
				'skills'                => $_kseo['skills'] ?? '',
			],
		];
	}

	/**
	 * Map recipe schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_recipe_schema_field_data_map( $_kseo ) {
		$instructions = [];
		if ( ! empty( $_kseo['recipe_instructions'] ) && is_array( $_kseo['recipe_instructions'] ) ) {
			foreach ( $_kseo['recipe_instructions'] as $step ) {
				$instructions[] = [
					'name'  => $step['name'] ?? '',
					'text'  => $step['text'] ?? '',
					'image' => $this->image_url_to_id( $step['image'] ?? '' ),
					'url'   => $step['url'] ?? '',
				];
			}
		}

		$video_info = [];
		if ( ! empty( $_kseo['video_info'] ) && is_array( $_kseo['video_info'] ) ) {
			foreach ( $_kseo['video_info'] as $vi ) {
				$video_info[] = [
					'name'         => $vi['name'] ?? '',
					'description'  => $vi['description'] ?? '',
					'thumbnailUrl' => $this->image_url_to_id( $vi['thumbnailUrl'] ?? '' ),
					'contentUrl'   => $vi['contentUrl'] ?? '',
					'embedUrl'     => $vi['embedUrl'] ?? '',
					'uploadDate'   => $this->to_iso8601( $vi['uploadDate'] ?? '' ),
					'duration'     => $vi['duration'] ?? '',
				];
			}
		}

		$data = [
			'status'               => 'show',
			'name'                 => $_kseo['name'] ?? '',
			'author'               => $_kseo['author'] ?? '',
			'datePublished'        => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
			'image'                => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'description'          => $_kseo['description'] ?? '',
			'keywords'             => $_kseo['keywords'] ?? '',
			'recipeCategory'       => $_kseo['recipeCategory'] ?? '',
			'recipeCuisine'        => $_kseo['recipeCuisine'] ?? '',
			'prepTime'             => $_kseo['prepTime'] ?? '',
			'cookTime'             => $_kseo['cookTime'] ?? '',
			'recipeIngredient'     => $_kseo['recipeIngredient'] ?? '',
			'calories'             => $_kseo['calories'] ?? '',
			'fatContent'           => $_kseo['fatContent'] ?? '',
			'recipeYield'          => $_kseo['recipeYield'] ?? '',
			'suitableForDiet'      => $_kseo['suitableForDiet'] ?? '',
			'ratingValue'          => $_kseo['ratingValue'] ?? '',
			'reviewCount'          => $_kseo['reviewCount'] ?? '',
			'bestRating'           => $_kseo['bestRating'] ?? '',
			'worstRating'          => $_kseo['worstRating'] ?? '',
		];

		if ( ! empty( $instructions ) ) {
			$data['recipe_instructions'] = $instructions;
		}
		if ( ! empty( $video_info ) ) {
			$data['video_info'] = $video_info;
		}

		$data = array_merge( $data, $this->map_review_fields( $_kseo ) );

		return [ $data ];
	}

	/**
	 * Map software application schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_software_app_schema_field_data_map( $_kseo ) {
		$data = [
			'status'               => 'show',
			'name'                 => $_kseo['name'] ?? '',
			'description'          => $_kseo['description'] ?? '',
			'image'                => $this->image_url_to_id( $_kseo['image'] ?? '' ),
			'price'                => $_kseo['price'] ?? '',
			'priceCurrency'        => $_kseo['priceCurrency'] ?? '',
			'applicationCategory'  => $_kseo['applicationCategory'] ?? '',
			'operatingSystem'      => $_kseo['operatingSystem'] ?? '',
			'softwareVersion'      => $_kseo['softwareVersion'] ?? '',
			'downloadUrl'          => $_kseo['downloadUrl'] ?? '',
			'aggregate_ratingValue' => $_kseo['aggregate_ratingValue'] ?? '',
			'aggregate_bestRating'  => $_kseo['aggregate_bestRating'] ?? '',
			'aggregate_worstRating' => $_kseo['aggregate_worstRating'] ?? '',
			'aggregate_ratingCount' => $_kseo['aggregate_ratingCount'] ?? '',
		];

		$data = array_merge( $data, $this->map_review_fields( $_kseo ) );

		return [ $data ];
	}

	/**
	 * Map book schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_book_schema_field_data_map( $_kseo ) {
		$data = [
			'status'         => 'show',
			'name'           => $_kseo['name'] ?? '',
			'datePublished'  => $this->to_iso8601( $_kseo['datePublished'] ?? '' ),
			'author'         => $_kseo['author'] ?? '',
			'author_sameAs'  => $_kseo['author_sameAs'] ?? '',
			'bookFormat'     => $_kseo['bookFormat'] ?? '',
			'isbn'           => $_kseo['isbn'] ?? '',
			'workExample'    => $_kseo['workExample'] ?? '',
			'url'            => $_kseo['url'] ?? '',
			'sameAs'         => $_kseo['sameAs'] ?? '',
			'numberOfPages'  => $_kseo['numberOfPages'] ?? '',
			'copyrightHolder' => $_kseo['copyrightHolder'] ?? '',
			'copyrightYear'  => $_kseo['copyrightYear'] ?? '',
			'description'    => $_kseo['description'] ?? '',
			'genre'          => $_kseo['genre'] ?? '',
			'inLanguage'     => $_kseo['inLanguage'] ?? '',
		];

		$data = array_merge( $data, $this->map_review_fields( $_kseo ) );

		return [ $data ];
	}

	/**
	 * Map restaurant schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_restaurant_schema_field_data_map( $_kseo ) {
		$image_id = $this->image_url_to_id( $_kseo['image'] ?? '' );

		$data = [
			'status'        => 'show',
			'name'          => $_kseo['name'] ?? '',
			'description'   => $_kseo['description'] ?? '',
			'image'         => $image_id ? [ $image_id ] : [],
			'priceRange'    => $_kseo['priceRange'] ?? '',
			'telephone'     => $_kseo['telephone'] ?? '',
			'servesCuisine' => $_kseo['servesCuisine'] ?? '',
		];

		// Map address (wp-seo stores as single text field).
		if ( ! empty( $_kseo['address'] ) ) {
			$data['address'] = [
				0 => [
					'streetAddress'   => is_string( $_kseo['address'] ) ? $_kseo['address'] : '',
					'addressLocality' => '',
					'addressRegion'   => '',
					'postalCode'      => '',
					'addressCountry'  => '',
				],
			];
		}

		// Map opening hours (key must be openingHours with dayOfWeek sub-key).
		if ( ! empty( $_kseo['openingHours'] ) ) {
			$data['openingHours'] = $this->parse_opening_hours( $_kseo['openingHours'] );
		}

		// Map menu sections with menu items and nutrition.
		if ( ! empty( $_kseo['menuName'] ) || ! empty( $_kseo['menu_items'] ) ) {
			$menu_section = [
				'name' => $_kseo['menuName'] ?? '',
				'desc' => $_kseo['menuDescription'] ?? '',
			];

			// Menu section image.
			if ( ! empty( $_kseo['menuImage'] ) ) {
				$menu_img_id = $this->image_url_to_id( $_kseo['menuImage'] );
				if ( $menu_img_id ) {
					$menu_section['images'] = [ $menu_img_id ];
				}
			}

			// Menu offer availability dates.
			if ( ! empty( $_kseo['menuOfferAvailabilityStarts'] ) ) {
				$menu_section['availabilityStarts'] = $_kseo['menuOfferAvailabilityStarts'];
			}
			if ( ! empty( $_kseo['menuOfferAvailabilityEnds'] ) ) {
				$menu_section['availabilityEnds'] = $_kseo['menuOfferAvailabilityEnds'];
			}

			// Menu items with nutrition.
			if ( ! empty( $_kseo['menu_items'] ) && is_array( $_kseo['menu_items'] ) ) {
				$menu_items = [];
				foreach ( $_kseo['menu_items'] as $mi ) {
					$item = [
						'name'          => $mi['name'] ?? '',
						'desc'          => $mi['description'] ?? '',
						'price'         => $mi['offers_price'] ?? '',
						'priceCurrency' => $mi['offers_priceCurrency'] ?? '',
					];

					// Nutrition fields.
					$nutrition_map = [
						'nutrition_calories'              => 'calories',
						'nutrition_carbohydrateContent'   => 'carbohydrateContent',
						'nutrition_cholesterolContent'    => 'cholesterolContent',
						'nutrition_fatContent'            => 'fatContent',
						'nutrition_fiberContent'          => 'fiberContent',
						'nutrition_proteinContent'        => 'proteinContent',
						'nutrition_saturatedFatContent'   => 'saturatedFatContent',
						'nutrition_servingSize'           => 'servingSize',
						'nutrition_sodiumContent'         => 'sodiumContent',
						'nutrition_sugarContent'          => 'sugarContent',
						'nutrition_transFatContent'       => 'transFatContent',
						'nutrition_unsaturatedFatContent' => 'unsaturatedFatContent',
					];
					foreach ( $nutrition_map as $wp_seo_key => $rtrs_key ) {
						if ( ! empty( $mi[ $wp_seo_key ] ) ) {
							$item[ $rtrs_key ] = $mi[ $wp_seo_key ];
						}
					}

					$menu_items[] = $item;
				}
				$menu_section['menu_items'] = $menu_items;
			}

			$data['menu_sections'] = [ $menu_section ];
		}

		return [ $data ];
	}

	/**
	 * Map TV series (TVEpisode) schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_tv_series_schema_field_data_map( $_kseo ) {
		return [
			[
				'status'        => 'show',
				'name'          => $_kseo['name'] ?? '',
				'author'        => $_kseo['author'] ?? '',
				'actor'         => $_kseo['actor'] ?? '',
				'episodeNumber' => $_kseo['episodeNumber'] ?? '',
				'seasonNumber'  => $_kseo['seasonNumber'] ?? '',
				'seriesName'    => $_kseo['seriesName'] ?? '',
				'seriesURL'     => $_kseo['seriesURL'] ?? '',
				'startDate'     => $this->to_iso8601( $_kseo['startDate'] ?? '' ),
				'sameAs'        => $_kseo['sameAs'] ?? '',
				'url'           => $_kseo['url'] ?? '',
			],
		];
	}

	/**
	 * Map vacation rental schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_vacation_rental_schema_field_data_map( $_kseo ) {
		$data = [
			'status'                 => 'show',
			'additionalType'         => $_kseo['additionalType'] ?? '',
			'name'                   => $_kseo['name'] ?? '',
			'description'            => $_kseo['description'] ?? '',
			'priceRange'             => $_kseo['priceRange'] ?? '',
			'telephone'              => $_kseo['telephone'] ?? '',
			'identifier'             => $_kseo['identifier'] ?? '',
			'latitude'               => $_kseo['latitude'] ?? '',
			'longitude'              => $_kseo['longitude'] ?? '',
			'containsPlaceType'      => $_kseo['containsPlaceType'] ?? '',
			'occupancy'              => $_kseo['occupancy'] ?? '',
			'numberOfBathroomsTotal' => $_kseo['numberOfBathroomsTotal'] ?? '',
			'numberOfBedrooms'       => $_kseo['numberOfBedrooms'] ?? '',
			'numberOfRooms'          => $_kseo['numberOfRooms'] ?? '',
			'floorSizeValue'         => $_kseo['floorSizeValue'] ?? '',
			'unitCode'               => $_kseo['unitCode'] ?? '',
			'streetAddress'          => $_kseo['streetAddress'] ?? '',
			'addressLocality'        => $_kseo['addressLocality'] ?? '',
			'region'                 => $_kseo['region'] ?? '',
			'postalCode'             => $_kseo['postalCode'] ?? '',
			'addressCountry'         => $_kseo['addressCountry'] ?? '',
		];

		// Map beds group.
		if ( ! empty( $_kseo['beds'] ) && is_array( $_kseo['beds'] ) ) {
			$beds = [];
			foreach ( $_kseo['beds'] as $bed ) {
				$beds[] = [
					'numberOfBeds' => $bed['numberOfBeds'] ?? '',
					'typeOfBed'    => $bed['typeOfBed'] ?? '',
				];
			}
			$data['beds'] = $beds;
		}

		// Map amenity features group.
		if ( ! empty( $_kseo['amenityFeature'] ) && is_array( $_kseo['amenityFeature'] ) ) {
			$amenities = [];
			foreach ( $_kseo['amenityFeature'] as $amenity ) {
				$amenities[] = [
					'feature' => $amenity['feature'] ?? '',
					'value'   => $amenity['value'] ?? '',
				];
			}
			$data['amenityFeature'] = $amenities;
		}

		// Map images group.
		if ( ! empty( $_kseo['images'] ) && is_array( $_kseo['images'] ) ) {
			$images = [];
			foreach ( $_kseo['images'] as $img ) {
				$image_id = $this->image_url_to_id( $img['image'] ?? '' );
				if ( $image_id ) {
					$images[] = [
						'image' => $image_id,
					];
				}
			}
			if ( ! empty( $images ) ) {
				$data['images'] = $images;
			}
		}

		// Map reviews group.
		if ( ! empty( $_kseo['reviews'] ) && is_array( $_kseo['reviews'] ) ) {
			$reviews = [];
			foreach ( $_kseo['reviews'] as $review ) {
				$reviews[] = [
					'author'        => $review['author'] ?? '',
					'ratingValue'   => $review['ratingValue'] ?? '',
					'bestRating'    => $review['bestRating'] ?? '',
					'datePublished' => $this->to_iso8601( $review['datePublished'] ?? '' ),
				];
			}
			$data['reviews'] = $reviews;
		}

		// Map aggregate rating.
		if ( ! empty( $_kseo['aggregate_ratingValue'] ) ) {
			$data['aggregate_ratingValue'] = $_kseo['aggregate_ratingValue'];
			$data['aggregate_bestRating']  = $_kseo['aggregate_bestRating'] ?? '';
			$data['aggregate_worstRating'] = $_kseo['aggregate_worstRating'] ?? '';
			$data['aggregate_ratingCount'] = $_kseo['aggregate_ratingCount'] ?? '';
		}

		return [ $data ];
	}

	/**
	 * Map vehicle listing schema fields.
	 *
	 * @param array $_kseo Source meta data.
	 *
	 * @return array Review-schema formatted data.
	 */
	private function get_vehicle_listing_schema_field_data_map( $_kseo ) {
		$data = [
			'status'                 => 'show',
			'type'                   => $_kseo['type'] ?? '',
			'name'                   => $_kseo['name'] ?? '',
			'IdentificationNumber'   => $_kseo['IdentificationNumber'] ?? '',
			'url'                    => $_kseo['url'] ?? '',
			'description'            => $_kseo['description'] ?? '',
			'offers_price'           => $_kseo['offers_price'] ?? '',
			'priceCurrency'          => $_kseo['priceCurrency'] ?? '',
			'priceValidUntil'        => $this->to_iso8601( $_kseo['priceValidUntil'] ?? '' ),
			'availability'           => $_kseo['availability'] ?? '',
			'itemCondition'          => $_kseo['itemCondition'] ?? '',
			'brand'                  => $_kseo['brand'] ?? '',
			'model'                  => $_kseo['model'] ?? '',
			'vehicleConfiguration'   => $_kseo['vehicleConfiguration'] ?? '',
			'vehicleModelDate'       => $_kseo['vehicleModelDate'] ?? '',
			'mileageFromOdometer'    => $_kseo['mileageFromOdometer'] ?? '',
			'unitCode'               => $_kseo['unitCode'] ?? '',
			'color'                  => $_kseo['color'] ?? '',
			'vehicleInteriorColor'   => $_kseo['vehicleInteriorColor'] ?? '',
			'vehicleInteriorType'    => $_kseo['vehicleInteriorType'] ?? '',
			'bodyType'               => $_kseo['bodyType'] ?? '',
			'driveWheelConfiguration' => $_kseo['driveWheelConfiguration'] ?? '',
			'fuelType'               => $_kseo['fuelType'] ?? '',
			'vehicleTransmission'    => $_kseo['vehicleTransmission'] ?? '',
			'numberOfDoors'          => $_kseo['numberOfDoors'] ?? '',
			'vehicleSeatingCapacity' => $_kseo['vehicleSeatingCapacity'] ?? '',
			'shippingRate'           => $_kseo['shippingRate'] ?? '',
			'shippingDestination'    => $_kseo['shippingDestination'] ?? '',
			'addressRegion'          => $_kseo['addressRegion'] ?? '',
			'handlingTimeMinimum'    => $_kseo['handlingTimeMinimum'] ?? '',
			'handlingTimeMaximum'    => $_kseo['handlingTimeMaximum'] ?? '',
			'transitTimeMinimum'     => $_kseo['transitTimeMinimum'] ?? '',
			'transitTimeMaximum'     => $_kseo['transitTimeMaximum'] ?? '',
			'applicableCountry'      => $_kseo['applicableCountry'] ?? '',
			'merchantReturnDays'     => $_kseo['merchantReturnDays'] ?? '',
			'reviewRatingValue'      => $_kseo['reviewRatingValue'] ?? '',
			'reviewBestRating'       => $_kseo['reviewBestRating'] ?? '',
			'reviewWorstRating'      => $_kseo['reviewWorstRating'] ?? '',
			'reviewAuthor'           => $_kseo['reviewAuthor'] ?? '',
			'ratingValue'            => $_kseo['ratingValue'] ?? '',
			'reviewCount'            => $_kseo['reviewCount'] ?? '',
		];

		// Map images group.
		if ( ! empty( $_kseo['images'] ) && is_array( $_kseo['images'] ) ) {
			$images = [];
			foreach ( $_kseo['images'] as $img ) {
				$image_id = $this->image_url_to_id( $img['image'] ?? '' );
				if ( $image_id ) {
					$images[] = [
						'image' => $image_id,
					];
				}
			}
			if ( ! empty( $images ) ) {
				$data['images'] = $images;
			}
		}

		return [ $data ];
	}

	/**
	 * Build meta_key => rtrs_schema_key mapping.
	 *
	 * @return array [ 'prefix_article' => 'article', 'prefix_event' => 'event', ... ]
	 */
	private function get_meta_key_map() {
		global $KcSeoWPSchema;
		$map = [];
		foreach ( KcSeoOptions::getSchemaTypes() as $schemaID => $schema ) {
			$schema_key = $this->map_schema_key( $schemaID );
			if ( $schema_key ) {
				$map[ $KcSeoWPSchema->KcSeoPrefix . $schemaID ] = $schema_key;
			}
		}
		return $map;
	}

	/**
	 * Map a WP SEO schema type key to its RTRS schema key.
	 *
	 * @param string $cat_key The category key from KcSeoOptions (e.g. 'tech_article').
	 *
	 * @return string|false The matching RTRS schema key, or false if not found.
	 */
	public function map_schema_key( $cat_key ) {
		$map = [
			'article'                => 'article',
			'TechArticle'            => 'tech_article',
			'news_article'           => 'news_article',
			'blog_posting'           => 'blog_posting',
			'event'                  => 'event',
			'faq'                    => 'faq',
			'service'                => 'service',
			'question'               => 'question_answer',
			'how_to'                 => 'how_to',
			'about'                  => 'about',
			'contact'                => 'contact',
			'person'                 => 'person',
			'movie'                  => 'movie',
			'audio'                  => 'audio',
			'video'                  => 'video',
			'breadcrumb'             => 'breadcrumb',
			'mosque'                 => 'mosque',
			'church'                 => 'church',
			'hindutemple'            => 'hindutemple',
			'buddhisttemple'         => 'buddhisttemple',
			'touristattraction'      => 'touristattraction',
			'profilePage'            => 'profile_page',
			'MedicalWebPage'         => 'medical_webpage',
			'product'                => 'product',
			'book'                   => 'book',
			'real_state_listing'     => 'real_state_listing',
			'course'                 => 'course',
			'JobPosting'             => 'job_posting',
			'recipe'                 => 'recipe',
			'software_application'   => 'software_app',
			'image_license'          => 'image_license',
			'restaurant'             => 'Restaurant',
			'specialAnnouncement'    => 'special_announcement',
			'vacationRental'         => 'vacation_rental',
			'vehicleListing'         => 'vehicle_listing',
			'TVEpisode'              => 'tv_series',
			'PodcastEpisode'         => 'PodcastEpisode',
			'DiscussionForumPosting' => 'DiscussionForumPosting',
			'Dataset'                => 'Dataset',
			'TaxiService'            => 'TaxiService',
		];

		return $map[ $cat_key ] ?? false;
	}

	/**
	 * Migrate plugin-level settings (runs once, not per-post).
	 *
	 * Imports site settings, business info, social profiles,
	 * corporate contacts, and third-party compatibility options.
	 *
	 * @return void
	 */
	private function migrate_settings() {
		global $KcSeoWPSchema;
		$settings                = get_option( $KcSeoWPSchema->options['settings'] );
		$main_settings           = get_option( $KcSeoWPSchema->options['main_settings'] );
		$rtrs_schema_settings    = [];
		$rtrs_schema_cc_settings = [];
		$rtrs_schema_sp_settings = [];
		$tpp_settings            = [];

		if ( isset( $settings ) ) {
			// Site type / organization category.
			if ( ! empty( $settings['site_type'] ) ) {
				$rtrs_schema_settings['site_category']         = 'Organization';
				$rtrs_schema_settings['organization_category'] = $settings['site_type'];
			}

			// Site name.
			if ( ! empty( $settings['sitename'] ) ) {
				$rtrs_schema_settings['name'] = $settings['sitename'];
			}

			// Alternate name.
			if ( ! empty( $settings['siteaname'] ) ) {
				$rtrs_schema_settings['alternateName'] = $settings['siteaname'];
			}

			// Site image.
			if ( ! empty( $settings['site_image'] ) ) {
				$rtrs_schema_settings['image'] = $settings['site_image'];
			}

			// Organization logo.
			if ( ! empty( $settings['organization_logo'] ) ) {
				$rtrs_schema_settings['logo'] = $settings['organization_logo'];
			}

			// Price range.
			if ( ! empty( $settings['site_price_range'] ) ) {
				$rtrs_schema_settings['priceRange'] = $settings['site_price_range'];
			}

			// Telephone.
			if ( ! empty( $settings['site_telephone'] ) ) {
				$rtrs_schema_settings['telephone'] = $settings['site_telephone'];
			}

			// Addresses (primary + multiple).
			$address = [];
			if ( ! empty( $settings['address'] ) ) {
				$_address  = $settings['address'];
				$address[] = [
					'streetAddress'   => $_address['street'] ?? '',
					'addressLocality' => $_address['locality'] ?? '',
					'addressRegion'   => $_address['region'] ?? '',
					'postalCode'      => $_address['postalcode'] ?? '',
					'addressCountry'  => $_address['country'] ?? '',
				];
			}
			if ( ! empty( $settings['_multiple_address'] ) ) {
				foreach ( $settings['_multiple_address'] as $m_ad ) {
					$address[] = [
						'streetAddress'   => $m_ad['street'] ?? '',
						'addressLocality' => $m_ad['locality'] ?? '',
						'addressRegion'   => $m_ad['region'] ?? '',
						'postalCode'      => $m_ad['postalcode'] ?? '',
						'addressCountry'  => $m_ad['country'] ?? '',
					];
				}
			}
			if ( ! empty( $address ) ) {
				$rtrs_schema_settings['addresses'] = $address;
			}

			// Business info: latitude, longitude, radius, description.
			if ( ! empty( $settings['business_info'] ) ) {
				$biz = $settings['business_info'];
				if ( ! empty( $biz['latitude'] ) ) {
					$rtrs_schema_settings['latitude'] = $biz['latitude'];
				}
				if ( ! empty( $biz['longitude'] ) ) {
					$rtrs_schema_settings['longitude'] = $biz['longitude'];
				}
				if ( ! empty( $biz['geo_radius'] ) ) {
					$rtrs_schema_settings['radius'] = $biz['geo_radius'];
				}
				if ( ! empty( $biz['description'] ) ) {
					$rtrs_schema_settings['description'] = $biz['description'];
				}

				// Opening hours.
				if ( ! empty( $biz['openingHours'] ) ) {
					$rtrs_schema_settings['openingHours'] = $this->parse_opening_hours( $biz['openingHours'] );
				}
			}

			// Contact point: telephone, language, area served.
			$contact_point = [];
			if ( ! empty( $settings['contact']['telephone'] ) ) {
				$cp              = [];
				$cp['telephone'] = $settings['contact']['telephone'];

				if ( ! empty( $settings['availableLanguage'] ) && is_array( $settings['availableLanguage'] ) ) {
					$cp['language'] = implode( ', ', $settings['availableLanguage'] );
				}

				if ( ! empty( $settings['address']['country'] ) ) {
					$cp['areaServed'] = $settings['address']['country'];
				}

				$contact_point[] = $cp;
			}
			if ( ! empty( $contact_point ) ) {
				$rtrs_schema_settings['contactPoint'] = $contact_point;
			}

			// About page and contact page from main_settings.
			if ( ! empty( $main_settings['about_page'] ) ) {
				$rtrs_schema_settings['about_page'] = $main_settings['about_page'];
			}
			if ( ! empty( $main_settings['contact_page'] ) ) {
				$rtrs_schema_settings['contact_page'] = $main_settings['contact_page'];
			}

			// Social profiles migration.
			if ( ! empty( $settings['social'] ) && is_array( $settings['social'] ) ) {
				foreach ( $settings['social'] as $social ) {
					if ( ! empty( $social['link'] ) ) {
						$rtrs_schema_sp_settings['social_profiles'][] = [
							'url' => esc_url( $social['link'] ),
						];
					}
				}
			}

			// Contact Point migration.
			if ( ! empty( $settings['contact'] ) && is_array( $settings['contact'] ) ) {
				$contact = $settings['contact'];

				if ( ! empty( $contact['contactType'] ) ) {
					$rtrs_schema_cc_settings['type'] = $contact['contactType'];
				}
				if ( ! empty( $contact['telephone'] ) ) {
					$rtrs_schema_cc_settings['telephone'] = $contact['telephone'];
				}
				if ( ! empty( $contact['email'] ) ) {
					$rtrs_schema_cc_settings['email'] = $contact['email'];
				}
				if ( ! empty( $contact['contactOption'] ) ) {
					$rtrs_schema_cc_settings['contactOption'] = $contact['contactOption'];
				}
				if ( ! empty( $settings['availableLanguage'] ) ) {
					$rtrs_schema_cc_settings['availableLanguage'] = $settings['availableLanguage'];
				}
				if ( ! empty( $settings['area_served'] ) ) {
					$rtrs_schema_cc_settings['areaServed'] = $settings['area_served'];
				}
			}
		}

		if ( ! empty( $main_settings ) ) {
			if ( ! empty( $main_settings['yoast_wpseo_json_ld'] ) ) {
				$tpp_settings['yoast_schema'] = 'yes';
			}
			if ( ! empty( $main_settings['yoast_wpseo_json_ld_search'] ) ) {
				$tpp_settings['yoast_search_schema'] = 'yes';
			}
			if ( ! empty( $main_settings['wc_schema_disable'] ) ) {
				$tpp_settings['wc_schema'] = 'yes';
			}
			if ( ! empty( $main_settings['edd_schema_microdata'] ) ) {
				$tpp_settings['edd_schema'] = 'yes';
			}
			// Migrate post type settings to flat format.
			if ( ! empty( $main_settings['post-type'] ) ) {
				$post_type_settings = get_option( 'rtrs_schema_post_types_settings', [] );
				foreach ( $main_settings['post-type'] as $post_type ) {
					$schema_type = 'article';
					if ( 'product' === $post_type ) {
						$schema_type = 'product';
					}
					if ( 'course' === $post_type ) {
						$schema_type = 'course';
					}
					$post_type_settings[ $post_type . '_schema_type' ] = $schema_type;
				}
				update_option( 'rtrs_schema_post_types_settings', $post_type_settings );
			}
		}

		// Save main schema settings.
		if ( ! empty( $rtrs_schema_settings ) ) {
			update_option( 'rtrs_schema_settings', $rtrs_schema_settings );
		}
		if ( ! empty( $tpp_settings ) ) {
			update_option( 'rtrs_schema_tpp_settings', $tpp_settings );
		}
		if ( ! empty( $rtrs_schema_cc_settings ) ) {
			update_option( 'rtrs_schema_corporate_contacts_settings', $rtrs_schema_cc_settings );
		}
		if ( ! empty( $rtrs_schema_sp_settings ) ) {
			update_option( 'rtrs_schema_social_profiles_settings', $rtrs_schema_sp_settings );
		}
	}

	/**
	 * Parse opening hours string into structured array.
	 *
	 * Converts format like "Mo-Sa 11:00-14:30 Mo-Th 17:00-21:30 Fr-Sa 17:00-22:00"
	 * into array of [ 'day' => 'Monday', 'opens' => '11:00', 'closes' => '14:30' ].
	 *
	 * @param string $hours_string Opening hours string.
	 *
	 * @return array Structured opening hours.
	 */
	private function parse_opening_hours( $hours_string ) {
		$day_map  = [
			'Mo' => 'Monday',
			'Tu' => 'Tuesday',
			'We' => 'Wednesday',
			'Th' => 'Thursday',
			'Fr' => 'Friday',
			'Sa' => 'Saturday',
			'Su' => 'Sunday',
		];
		$day_keys = array_keys( $day_map );
		$entries  = [];

		// Match range format: "Mo-Sa 11:00-14:30" and comma-separated: "Mo,Tu,We 11:00-14:30".
		preg_match_all(
			'/((?:[A-Za-z]{2})(?:[,\-][A-Za-z]{2})*)\s+(\d{1,2}:\d{2})\s*(AM|PM)?\s*-\s*(\d{1,2}:\d{2})\s*(AM|PM)?/i',
			$hours_string,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $match ) {
			$days_part = $match[1];
			$opens     = $this->convert_to_24h( $match[2], $match[3] ?? '' );
			$closes    = $this->convert_to_24h( $match[4], $match[5] ?? '' );

			// Determine resolved day names.
			$resolved_days = [];

			if ( strpos( $days_part, ',' ) !== false ) {
				// Comma-separated: "Mo,Tu,We,Th,Fr".
				foreach ( explode( ',', $days_part ) as $abbr ) {
					$abbr = trim( $abbr );
					if ( isset( $day_map[ $abbr ] ) ) {
						$resolved_days[] = $day_map[ $abbr ];
					}
				}
			} elseif ( strpos( $days_part, '-' ) !== false ) {
				// Range: "Mo-Fr".
				$parts     = explode( '-', $days_part );
				$start_idx = array_search( $parts[0], $day_keys, true );
				$end_idx   = array_search( $parts[1], $day_keys, true );
				if ( false !== $start_idx && false !== $end_idx ) {
					for ( $i = $start_idx; $i <= $end_idx; $i++ ) {
						$resolved_days[] = $day_map[ $day_keys[ $i ] ];
					}
				}
			} else {
				// Single day: "Mo".
				if ( isset( $day_map[ $days_part ] ) ) {
					$resolved_days[] = $day_map[ $days_part ];
				}
			}

			foreach ( $resolved_days as $day_name ) {
				$entries[ $day_name ] = [
					'dayOfWeek' => $day_name,
					'opens'     => $opens,
					'closes'    => $closes,
				];
			}
		}

		return array_values( $entries );
	}

	/**
	 * Convert time to 24-hour format.
	 *
	 * @param string $time   Time string like "9:00" or "11:30".
	 * @param string $period AM/PM period (empty for already 24-hour times).
	 *
	 * @return string Time in 24-hour format like "09:00" or "17:00".
	 */
	private function convert_to_24h( $time, $period ) {
		if ( empty( $period ) ) {
			list( $h, $m ) = explode( ':', $time );
			return sprintf( '%02d:%s', (int) $h, $m );
		}

		list( $h, $m ) = explode( ':', $time );
		$h             = (int) $h;

		if ( strtoupper( $period ) === 'AM' ) {
			$h = ( 12 === $h ) ? 0 : $h;
		} else {
			$h = ( 12 === $h ) ? 12 : $h + 12;
		}

		return sprintf( '%02d:%s', $h, $m );
	}
}
