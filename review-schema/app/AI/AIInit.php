<?php
/**
 * SchemaEngine AI - Bootstrap and initialization.
 *
 * Consolidates all AI schema functionality: component wiring,
 * asset enqueuing, metabox registration, and helper methods.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Hooks\ElementorFaq;
use Rtrs\Modules\Seo\Analyzers\SeoAnalyzer;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIInit {

	use SingletonTrait;

	/**
	 * Post meta key for stored schema data.
	 *
	 * @var string
	 */
	const META_KEY = '_aise_schema_data';

	/**
	 * Post meta key for detected schema type.
	 *
	 * @var string
	 */
	const TYPE_META_KEY = '_aise_schema_type';

	/**
	 * Post meta key for classification confidence score.
	 *
	 * @var string
	 */
	const CONFIDENCE_META_KEY = '_aise_confidence';

	/**
	 * Options key in wp_options table.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'rtrs_ai_settings';

	/**
	 * Initialize AI module components and hooks.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->initComponents();
		$this->initHooks();
	}

	/**
	 * Boot plugin components that register their own hooks.
	 *
	 * @return void
	 */
	private function initComponents() {
		( new RestApi() )->register_hooks();
		( new SchemaRenderer() )->register_hooks();
	}

	/**
	 * Register WordPress hooks for asset loading and metabox.
	 *
	 * @return void
	 */
	private function initHooks() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueueEditorAssets' ] );
		add_action( 'add_meta_boxes', [ $this, 'addClassicEditorMetabox' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueClassicEditorAssets' ] );

		// Tutor LMS course builder support.
		add_action( 'tutor_course_builder_footer', [ $this, 'renderTutorCourseBuilderPanel' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueTutorCourseBuilderAssets' ] );

		// FluentCart product builder support.
		add_action( 'fluent_cart/admin_js_loaded', [ $this, 'renderFluentCartProductPanel' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueFluentCartProductAssets' ] );

		// SureCart product builder support.
		add_action( 'admin_footer', [ $this, 'renderSureCartProductPanel' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueSureCartProductAssets' ] );

		// Elementor editor SEO report panel.
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueueElementorSeoAssets' ] );
		add_action( 'elementor/editor/footer', [ $this, 'renderElementorSeoPanel' ] );
	}

	// -------------------------------------------------------------------------
	// Static helpers (replace former global functions)
	// -------------------------------------------------------------------------

	/**
	 * Normalize schema data retrieved from post meta.
	 *
	 * Handles the case where data is stored as a JSON string
	 * instead of an array. Unwraps @graph wrapper if present.
	 *
	 * @param mixed $data Raw value from get_post_meta().
	 *
	 * @return array Normalized array of schema items, or empty array.
	 */
	public static function normalizeSchemaData( $data ) {
		if ( is_string( $data ) && '' !== $data ) {
			$decoded = json_decode( $data, true );

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$data = $decoded;
			} else {
				return [];
			}
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			return [];
		}

		// Unwrap @graph wrapper if present.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			$data = $data['@graph'];
		}

		// If it's a single schema object (has @type), wrap it in an array.
		if ( isset( $data['@type'] ) ) {
			$data = [ $data ];
		}

		return $data;
	}

	/**
	 * Get a specific plugin setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 *
	 * @return mixed Setting value.
	 */
	public static function getSetting( $key, $default = '' ) {
		$options = get_option( self::OPTION_NAME, [] );

		return $options[ $key ] ?? $default;
	}

	/**
	 * Check if an AI API key has been configured for the selected provider.
	 *
	 * @return bool True if API key exists for the current provider.
	 */
	public static function hasApiKey() {
		$provider = self::getSetting( 'api_provider', 'openai' );

		// Check provider-specific API key.
		switch ( $provider ) {
			case 'anthropic':
				$api_key = self::getSetting( 'anthropic_api_key', '' );
				break;
			case 'gemini':
				$api_key = self::getSetting( 'gemini_api_key', '' );
				break;
			default:
				$api_key = self::getSetting( 'openai_api_key', '' );
				break;
		}

		// Backward compatibility: fall back to the old unified 'api_key' setting.
		if ( empty( $api_key ) ) {
			$api_key = self::getSetting( 'api_key', '' );
		}

		return ! empty( $api_key );
	}

	/**
	 * Get supported post types for schema generation.
	 *
	 * @return array List of supported post type slugs.
	 */
	public static function getSupportedPostTypes() {
		// Public post types minus the globally-ignored ones (shared with the
		// internal-linking system so both exclude the same content).
		$defaults = \Rtrs\Helpers\ContentIgnore::allowed_post_types();

		/**
		 * Filter the list of post types that support AI schema generation.
		 *
		 * @param array $post_types List of post type slugs.
		 */
		return apply_filters( 'rtrs_ai_supported_post_types', array_values( $defaults ) );
	}

	/**
	 * Get entity/publisher data from the main schema settings (rtrs_schema_settings).
	 *
	 * Builds a complete entity structure from the Schema tab settings,
	 * including addresses, geo, opening hours, contact points, etc.
	 *
	 * @return array Complete entity data for AI schema generation.
	 */
	public static function getEntityData() {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$settings = get_option( 'rtrs_schema_settings', [] );
		$helper   = new \Rtrs\Helpers\Functions();
		$site_url = rtrim( home_url( '/' ), '/' );

		// Base entity type.
		$site_category = $settings['site_category'] ?? 'Organization';
		$is_person     = 'Person' === $site_category;

		// Specific @type: organization_category (e.g. PoliticalParty, Restaurant) or fallback.
		$entity_type = $is_person ? 'Person' : ( $settings['organization_category'] ?? 'Organization' );
		if ( empty( $entity_type ) || 'none' === $entity_type ) {
			$entity_type = $is_person ? 'Person' : 'Organization';
		}

		$fragment  = $is_person ? 'person' : 'organization';
		$entity_id = $site_url . '/#' . $fragment;

		// Name.
		$name = ! empty( $settings['name'] ) ? $settings['name'] : get_bloginfo( 'name' );

		// Image (attachment ID → URL).
		$image = [];
		if ( ! empty( $settings['image'] ) ) {
			$img = $helper->imageInfo( absint( $settings['image'] ) );
			if ( ! empty( $img['url'] ) ) {
				$image[] = $img['url'];
			}
		}

		// Logo (attachment ID → ImageObject).
		$logo = [];
		if ( ! $is_person && ! empty( $settings['logo'] ) ) {
			$img = $helper->imageInfo( absint( $settings['logo'] ) );
			if ( ! empty( $img['url'] ) ) {
				$logo = [
					'@type'  => 'ImageObject',
					'url'    => $img['url'],
					'height' => $img['height'] ?? 0,
					'width'  => $img['width'] ?? 0,
				];
			}
		}

		// Addresses.
		$addresses = [];
		if ( ! empty( $settings['addresses'] ) && is_array( $settings['addresses'] ) ) {
			foreach ( $settings['addresses'] as $key => $address ) {
				if ( empty( $address['addressLocality'] ) && empty( $address['streetAddress'] ) ) {
					continue;
				}
				$addresses[] = array_filter(
					[
						'@type'           => 'PostalAddress',
						'@id'             => $entity_id . '-address-' . $key,
						'streetAddress'   => $address['streetAddress'] ?? '',
						'addressLocality' => $address['addressLocality'] ?? '',
						'addressRegion'   => $address['addressRegion'] ?? '',
						'postalCode'      => $address['postalCode'] ?? '',
						'addressCountry'  => $address['addressCountry'] ?? '',
					]
				);
			}
		}

		// Geo coordinates.
		$geo = [];
		if ( ! empty( $settings['latitude'] ) || ! empty( $settings['longitude'] ) ) {
			$geo = [
				'@type'       => 'GeoCircle',
				'@id'         => $entity_id . '-geocircle',
				'geoMidpoint' => [
					'@type'     => 'GeoCoordinates',
					'latitude'  => $settings['latitude'] ?? '',
					'longitude' => $settings['longitude'] ?? '',
				],
				'geoRadius'   => ! empty( $settings['radius'] ) ? absint( $settings['radius'] ) : 50,
			];
		}

		// Opening hours.
		$opening_hours = [];
		if ( ! empty( $settings['openingHours'] ) && is_array( $settings['openingHours'] ) ) {
			foreach ( $settings['openingHours'] as $entry ) {
				$day = $entry['day'] ?? '';
				if ( empty( $day ) ) {
					continue;
				}
				$opening_hours[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => [ $day ],
					'opens'     => $entry['opens'] ?? '',
					'closes'    => $entry['closes'] ?? '',
				];
			}
		}

		// Contact points.
		$contact_points = [];
		if ( ! empty( $settings['contactPoint'] ) && is_array( $settings['contactPoint'] ) ) {
			foreach ( $settings['contactPoint'] as $point ) {
				$cp = array_filter(
					[
						'@type'       => 'ContactPoint',
						'telephone'   => $point['telephone'] ?? '',
						'contactType' => $point['contactType'] ?? '',
						'areaServed'  => $point['areaServed'] ?? '',
					]
				);
				if ( ! empty( $point['language'] ) ) {
					$cp['availableLanguage'] = array_map( 'trim', explode( ',', $point['language'] ) );
				}
				$contact_points[] = $cp;
			}
		}

		// Social profiles (from sub-option).
		$social_settings = get_option( 'rtrs_schema_social_profiles_settings', [] );
		$same_as         = [];
		if ( ! empty( $social_settings['social_profiles'] ) && is_array( $social_settings['social_profiles'] ) ) {
			foreach ( $social_settings['social_profiles'] as $profile ) {
				if ( ! empty( $profile['url'] ) ) {
					$same_as[] = $profile['url'];
				}
			}
		}

		$cached = [
			'entity_type'         => $entity_type,
			'entity_id'           => $entity_id,
			'entity_url'          => $site_url . '/',
			'name'                => $name,
			'alternateName'       => $settings['alternateName'] ?? '',
			'description'         => $settings['description'] ?? '',
			'image'               => $image,
			'logo'                => $logo,
			'priceRange'          => $settings['priceRange'] ?? '',
			'telephone'           => $settings['telephone'] ?? '',
			'addresses'           => $addresses,
			'geo'                 => $geo,
			'openingHours'        => $opening_hours,
			'contactPoints'       => $contact_points,
			'same_as'             => $same_as,
			'servesCuisine'       => $settings['servesCuisine'] ?? '',
			'menu'                => $settings['menu'] ?? '',
			'acceptsReservations' => ! empty( $settings['acceptsReservations'] ),
		];

		return $cached;
	}

	/**
	 * Map rich_snippet_cats keys to Schema.org type names.
	 *
	 * @var array
	 */
	const SNIPPET_TO_SCHEMA_TYPE = [
		'article'                => 'Article',
		'tech_article'           => 'TechArticle',
		'news_article'           => 'NewsArticle',
		'blog_posting'           => 'BlogPosting',
		'event'                  => 'Event',
		'faq'                    => 'FAQPage',
		'service'                => 'Service',
		'question_answer'        => 'QAPage',
		'how_to'                 => 'HowTo',
		'about'                  => 'AboutPage',
		'contact'                => 'ContactPage',
		'person'                 => 'Person',
		'movie'                  => 'Movie',
		'audio'                  => 'AudioObject',
		'video'                  => 'VideoObject',
		'mosque'                 => 'Mosque',
		'church'                 => 'Church',
		'hindutemple'            => 'HinduTemple',
		'buddhisttemple'         => 'BuddhistTemple',
		'profile_page'           => 'ProfilePage',
		'medical_webpage'        => 'MedicalWebPage',
		'product'                => 'Product',
		'book'                   => 'Book',
		'real_state_listing'     => 'RealEstateListing',
		'course'                 => 'Course',
		'job_posting'            => 'JobPosting',
		'recipe'                 => 'Recipe',
		'software_app'           => 'SoftwareApplication',
		'image_license'          => 'ImageObject',
		'Restaurant'             => 'Restaurant',
		'special_announcement'   => 'SpecialAnnouncement',
		'vacation_rental'        => 'VacationRental',
		'vehicle_listing'        => 'Vehicle',
		'tv_series'              => 'TVSeries',
		'PodcastEpisode'         => 'PodcastEpisode',
		'DiscussionForumPosting' => 'DiscussionForumPosting',
		'Dataset'                => 'Dataset',
		'TaxiService'            => 'TaxiService',
	];

	/**
	 * Build schema type options for JS from rich_snippet_cats().
	 *
	 * Skips BreadcrumbList (global schema) and maps keys to Schema.org types.
	 *
	 * @return array Array of { value: string, label: string } items.
	 */
	public static function getSchemaTypeOptions() {
		$cats = \Rtrs\Helpers\Functions::rich_snippet_cats();
		$map  = self::SNIPPET_TO_SCHEMA_TYPE;
		$opts = [];

		foreach ( $cats as $key => $label ) {
			// Skip breadcrumb — it's a global schema, not content.
			// Skip local_business — the entity is built from site settings and
			// SchemaExtractor strips LocalBusiness from AI output, so it is a
			// metabox-only type.
			if ( in_array( $key, [ 'breadcrumb', 'local_business' ], true ) ) {
				continue;
			}

			$schema_type = $map[ $key ] ?? $key;

			// Strip [Pro] suffix from label for clean display.
			$clean_label = preg_replace( '/\s*\[Pro\]\s*$/', '', $label );

			$opts[] = [
				'value' => $schema_type,
				'label' => $clean_label,
			];
		}

		return $opts;
	}

	/**
	 * Get pro-only schema type values derived from rich_snippet_cats().
	 *
	 * Identifies types whose label contains [Pro] and maps them to
	 * Schema.org type names (matching schemaTypes[].value in JS).
	 * Always includes 'auto_detect' as a pro-only feature.
	 *
	 * @return array List of pro-gated schema type values.
	 */
	public static function getProSchemaTypes() {
		$cats = \Rtrs\Helpers\Functions::rich_snippet_cats();
		$map  = self::SNIPPET_TO_SCHEMA_TYPE;
		$pro  = [ 'auto_detect' ];

		foreach ( $cats as $key => $label ) {
			// Same non-AI types getSchemaTypeOptions() skips — they are never
			// offered in the panel, so they must not gate AI generation either
			// (local_business is Pro in the metabox, not in the AI panel).
			if ( in_array( $key, [ 'breadcrumb', 'local_business' ], true ) ) {
				continue;
			}
			if ( strpos( $label, '[Pro]' ) !== false ) {
				$pro[] = $map[ $key ] ?? $key;
			}
		}

		return $pro;
	}

	/**
	 * Get translatable UI strings for the AI editor panels.
	 *
	 * Shared by both the block editor sidebar and classic editor metabox.
	 * Uses __() (not esc_html__) to avoid double-encoding — JS handles HTML escaping.
	 *
	 * @return array Keyed array of translated strings.
	 */
	public static function getI18nStrings() {
		return [
			// Shared AI glyph (raw SVG markup) used on every "…with AI" button.
			'aiIcon'                    => \Rtrs\Helpers\Functions::aiIconSvg(),

			// Deep link to the SEO Report settings (XML sitemap URL lives there).
			'seoReportUrl'              => admin_url( 'admin.php?page=review-schema#/seo_report' ),
			'seoReportSettingsLabel'    => __( 'Open SEO Report settings', 'review-schema' ),

			// Panel header / branding.
			'schemaEngineAi'            => __( 'SchemaEngine AI', 'review-schema' ),
			'structuredDataPoweredByAi' => __( 'Structured data powered by AI', 'review-schema' ),

			// Status labels.
			'noSchemaGenerated'         => __( 'No schema generated', 'review-schema' ),
			'analyzingContent'          => __( 'Analyzing content', 'review-schema' ),
			'savingPage'                => __( 'Saving page', 'review-schema' ),
			'generatedNotSavedYet'      => __( 'Generated — not saved yet', 'review-schema' ),
			'schemaActive'              => __( 'Schema active', 'review-schema' ),

			// MultiSelect / Type dropdown.
			'autoDetectAi'              => __( 'Auto-detect (AI)', 'review-schema' ),
			'selectSchemaType'          => __( 'Select schema type...', 'review-schema' ),
			'selectSchemaTypeRequired'  => __( 'Select at least one schema type to generate.', 'review-schema' ),
			'remove'                    => __( 'Remove', 'review-schema' ),
			'clearAll'                  => __( 'Clear all', 'review-schema' ),
			'searchSchemaTypes'         => __( 'Search schema types...', 'review-schema' ),
			'noMatchingSchemaTypes'     => __( 'No matching schema types', 'review-schema' ),
			'schemaTypeLabel'           => __( 'Schema Type', 'review-schema' ),

			// Generate button.
			'generateSchema'            => __( 'Generate Schema', 'review-schema' ),
			'regenerateSchema'          => __( 'Regenerate Schema', 'review-schema' ),
			'generating'                => __( 'Generating', 'review-schema' ),

			// FAQ Content button.
			'generateFaqContent'        => __( 'Generate FAQ Content', 'review-schema' ),
			'faqGenerated'              => __( 'FAQ content inserted into editor.', 'review-schema' ),
			'faqGenerationFailed'       => __( 'FAQ generation failed.', 'review-schema' ),
			'reviewFaqContent'          => __( 'Review Generated FAQ', 'review-schema' ),
			'insertFaq'                 => __( 'Insert FAQ', 'review-schema' ),
			'faqInsertedElementor'      => __( 'FAQ added to the editor. Click Update to save the page.', 'review-schema' ),
			'faqSectionTitle'           => __( 'Frequently Asked Questions', 'review-schema' ),
			'faqSectionLead'            => __( 'We\'ve gathered the questions people ask most and answered them clearly below. Take a moment to browse through them to better understand how everything works and what to expect. If you still can\'t find what you\'re looking for, don\'t hesitate to reach out — our team is always happy to help.', 'review-schema' ),
			'faqSavedToMeta'            => __( 'FAQ saved to metabox.', 'review-schema' ),

			// Confirm / Error / Success messages.
			'confirmRegenerate'         => __( 'This will regenerate the schema and replace the current one. Continue?', 'review-schema' ),
			'schemaGeneratedReview'     => __( 'Schema generated. Review and click "Save Schema".', 'review-schema' ),
			'generationFailed'          => __( 'Generation failed.', 'review-schema' ),
			'schemaSavedSuccessfully'   => __( 'Schema saved successfully!', 'review-schema' ),
			'saveFailed'                => __( 'Save failed.', 'review-schema' ),
			'confirmDeleteAll'          => __( 'Delete all schema data for this post?', 'review-schema' ),
			'schemaDeleted'             => __( 'Schema deleted.', 'review-schema' ),
			'invalidJson'               => __( 'Invalid JSON. Please fix syntax errors.', 'review-schema' ),
			'schemaSaved'               => __( 'Schema saved!', 'review-schema' ),

			// Clipboard toasts.
			'copiedToClipboard'         => __( 'Copied to clipboard!', 'review-schema' ),

			// Notice messages.
			'schemaStoredNotice'        => __( 'AI-generated schema is stored in the database. Editing post content will not update the schema automatically. Click "Regenerate Schema" to update it.', 'review-schema' ),
			'apiKeyNotConfigured'       => __( 'API key not configured.', 'review-schema' ),
			'configureNow'              => __( 'Configure now', 'review-schema' ),
			'openAiSettings'            => __( 'Open AI Settings', 'review-schema' ),

			// Modal UI labels.
			'jsonLdSchema'              => __( 'JSON-LD Schema', 'review-schema' ),
			'close'                     => __( 'Close', 'review-schema' ),
			'preview'                   => __( 'Preview', 'review-schema' ),
			'edit'                      => __( 'Edit', 'review-schema' ),
			'pro'                       => __( 'Pro', 'review-schema' ),
			'proFeature'                => __( 'Pro feature', 'review-schema' ),
			'applyWithAi'               => __( 'Generate with AI', 'review-schema' ),
			'applyTitleWithAi'          => __( 'Apply title with AI', 'review-schema' ),
			'applyMetaWithAi'           => __( 'Apply meta with AI', 'review-schema' ),
			'refining'                  => __( 'Refining…', 'review-schema' ),
			'suggestLeadWithAi'         => __( 'Improve with AI', 'review-schema' ),
			'configure'                 => __( 'Configure', 'review-schema' ),
			'cancel'                    => __( 'Cancel', 'review-schema' ),
			'aiNotConfigured'           => __( 'AI is not configured', 'review-schema' ),
			'aiNotConfiguredBody'       => __( 'Enable AI and add a provider API key in the plugin settings to use AI features.', 'review-schema' ),
			'proRequired'               => __( 'This is a Pro feature — upgrade to use it.', 'review-schema' ),
			'aiSetupRequired'           => __( 'Enable AI and add a provider API key in the plugin settings.', 'review-schema' ),
			'copy'                      => __( 'Copy', 'review-schema' ),
			'copied'                    => __( 'Copied', 'review-schema' ),
			'verifyWithAi'              => __( 'Review with AI', 'review-schema' ),
			'reverifyWithAi'            => __( 'Refresh AI review', 'review-schema' ),
			'removeAiReview'            => __( 'Remove AI review', 'review-schema' ),
			'verifying'                 => __( 'Reviewing…', 'review-schema' ),
			'aiVerified'                => __( 'AI reviewed', 'review-schema' ),
			'seoFieldNotFound'          => __( 'Open the "Search Engine Listing" section, then generate again.', 'review-schema' ),
			'seoTitleLabel'             => __( 'SEO title', 'review-schema' ),
			'metaDescLabel'             => __( 'Meta description', 'review-schema' ),
			'aiNotesTitle'              => __( 'AI review notes', 'review-schema' ),
			'aiReviewFailed'            => __( 'AI review failed', 'review-schema' ),
			'saving'                    => __( 'Saving…', 'review-schema' ),
			'saved'                     => __( 'Saved', 'review-schema' ),
			'discard'                   => __( 'Discard', 'review-schema' ),
			'save'                      => __( 'Save', 'review-schema' ),

			// Apply-with-AI confirm modal + post-save notice.
			'confirmChangeTitle'        => __( 'Confirm change', 'review-schema' ),
			'confirm'                   => __( 'Confirm', 'review-schema' ),
			'currentValue'              => __( 'Current value', 'review-schema' ),
			'newValue'                  => __( 'New value', 'review-schema' ),
			'savesTo'                   => __( 'Saves to', 'review-schema' ),
			'destPostTitle'             => __( 'Post title', 'review-schema' ),
			'destYoastTitle'            => __( 'Yoast SEO title', 'review-schema' ),
			'destRankmathTitle'         => __( 'Rank Math SEO title', 'review-schema' ),
			'destSeoTitle'              => __( 'SEO meta title', 'review-schema' ),
			'destMetaDesc'              => __( 'Meta description', 'review-schema' ),
			'destFocusKeyword'          => __( 'Focus keyword', 'review-schema' ),
			'noticeFocusKeyword'        => __( 'Focus keyword saved.', 'review-schema' ),
			'noticePostTitle'           => __( 'Post title updated.', 'review-schema' ),
			'noticeYoastTitle'          => __( 'Yoast SEO title updated.', 'review-schema' ),
			'noticeRankmathTitle'       => __( 'Rank Math SEO title updated.', 'review-schema' ),
			'noticeSeoTitle'            => __( 'SEO meta title updated.', 'review-schema' ),
			'noticeMetaDesc'            => __( 'Meta description updated.', 'review-schema' ),
			'alsoUpdatePostTitle'       => __( 'Also update the post title', 'review-schema' ),
			'noticeTitleAndPost'        => __( 'SEO title and post title updated.', 'review-schema' ),
			'noticeFirstPara'           => __( 'First paragraph updated in the editor — click Update to save.', 'review-schema' ),
			'noticeSubheading'          => __( 'Subheading updated in the editor — click Update to save.', 'review-schema' ),
			'noticeKeywordDensity'      => __( 'Paragraph updated to reduce keyword repetition — click Update to save.', 'review-schema' ),
			'applySubheadingTitle'      => __( 'Apply subheading', 'review-schema' ),
			'newSubheading'             => __( 'New subheading', 'review-schema' ),
			'replacesSubheading'        => __( 'Replaces this subheading', 'review-schema' ),
			'replaceSubheading'         => __( 'Replace subheading', 'review-schema' ),
			'noSubheadingFound'         => __( 'No subheading found in your content. Copy the text and paste it where you want a heading.', 'review-schema' ),
			'noticeCopied'              => __( 'Copied — paste it where you need it.', 'review-schema' ),
			'copy'                      => __( 'Copy', 'review-schema' ),
			'aiError'                   => __( 'Something went wrong. Please try again.', 'review-schema' ),
			/* translators: %s: qualitative impact level (High, Medium, or Low). */
			'estImpact'                 => __( 'Estimated impact: %s', 'review-schema' ),
			'impactHigh'                => __( 'High', 'review-schema' ),
			'impactMedium'              => __( 'Medium', 'review-schema' ),
			'impactLow'                 => __( 'Low', 'review-schema' ),
			'test'                      => __( 'Test', 'review-schema' ),
			'cancel'                    => __( 'Cancel', 'review-schema' ),
			'saveChanges'               => __( 'Save Changes', 'review-schema' ),
			'saveSchema'                => __( 'Save Schema', 'review-schema' ),
			'lines'                     => __( 'lines', 'review-schema' ),
			'schemas'                   => __( 'schema(s)', 'review-schema' ),

			// Results / analysis section.
			'aiConfidence'              => __( 'AI Confidence', 'review-schema' ),
			'detectedSchemaTypes'       => __( 'Detected Schema Types', 'review-schema' ),
			'validSchema'               => __( 'Valid Schema', 'review-schema' ),
			'validationIssues'          => __( 'Validation Issues', 'review-schema' ),
			'qualityScore'              => __( 'Quality Score', 'review-schema' ),

			// Action buttons.
			'viewSchema'                => __( 'View', 'review-schema' ),
			'editSchema'                => __( 'Edit', 'review-schema' ),
			'discard'                   => __( 'Discard', 'review-schema' ),
			'deleteSchema'              => __( 'Delete Schema', 'review-schema' ),
			'testInGoogleRichResults'   => __( 'Test in Google Rich Results', 'review-schema' ),

			// Elementor SEO report panel.
			'seoReportTitle'            => __( 'SEO Report', 'review-schema' ),
			'refresh'                   => __( 'Refresh', 'review-schema' ),
			'contentAnalysis'           => __( 'Content Analysis', 'review-schema' ),
			'seoReportEmpty'            => __( 'No SEO data available. Save the page, then refresh.', 'review-schema' ),
			'suggestionsLabel'          => __( 'Suggestions', 'review-schema' ),
			'issuesLabel'               => __( 'issue(s)', 'review-schema' ),
			'setFocusKeywordHint'       => __( 'Set a focus keyword to score this dimension.', 'review-schema' ),
		];
	}

	// -------------------------------------------------------------------------
	// Block Editor (Gutenberg) assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue block editor sidebar assets.
	 *
	 * @return void
	 */
	public function enqueueEditorAssets() {
		$screen = get_current_screen();

		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		if ( ! in_array( $screen->post_type, self::getSupportedPostTypes(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			rtrs()->get_assets_version( 'ai/css/editor-panel.css' )
		);

		wp_enqueue_script(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/editor-panel.js' ),
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-blocks', 'wp-hooks' ],
			rtrs()->get_assets_version( 'ai/js/editor-panel.js' ),
			true
		);

		$post_id     = get_the_ID();
		$schema_data = self::normalizeSchemaData( get_post_meta( $post_id, self::META_KEY, true ) );

		$faq_data         = get_post_meta( $post_id, '_rtrs_faqpage_data', true );
		$has_faq_data_gut = ! empty( $faq_data ) && is_array( $faq_data );

		/** Merge content schemas with global schemas for initial validation/evaluation. */
		$initial_validation = null;
		$initial_evaluation = null;
		$full_graph         = [];

		if ( ! empty( $schema_data ) || $has_faq_data_gut ) {
			$renderer       = new SchemaRenderer();
			$global_schemas = $renderer->build_global_schemas( $post_id, $schema_data );
			$full_graph     = array_merge( $schema_data, $global_schemas );
			$full_graph     = apply_filters( 'rtrs_ai_schema_before_render', $full_graph, $post_id );

			$validator          = new SchemaValidator();
			$initial_validation = $validator->validate( $full_graph );

			$initial_evaluation = apply_filters( 'rtrs_ai_schema_evaluation', RestApi::get_dummy_evaluation(), $full_graph );
		}

		wp_localize_script(
			'rtrs-ai-editor-panel',
			'aiseData',
			[
				'restUrl'        => rest_url( 'rtrs-ai/v1/' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'postId'         => $post_id,
				'schemaData'     => $schema_data,
				'fullGraph'      => $full_graph,
				'schemaType'     => get_post_meta( $post_id, self::TYPE_META_KEY, true ),
				'confidence'     => get_post_meta( $post_id, self::CONFIDENCE_META_KEY, true ),
				'hasApiKey'      => self::hasApiKey(),
				'aiSettingsUrl'  => admin_url( 'admin.php?page=review-schema#/ai' ),
				'logoUrl'        => rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ),
				'validation'     => $initial_validation,
				'evaluation'     => $initial_evaluation,
				'seo'            => AiVerifyStore::restore( 'seo', $post_id, SeoAnalyzer::analyze( $post_id ) ),
				'aeo'            => AiVerifyStore::restore( 'aeo', $post_id, \Rtrs\Modules\Aeo\Analyzers\AeoAnalyzer::analyze( $post_id ) ),
				'geo'            => AiVerifyStore::restore( 'geo', $post_id, \Rtrs\Modules\Geo\Analyzers\GeoAnalyzer::analyze( $post_id ) ),
				'schemaTypes'    => array_merge(
					[
						[
							'value' => 'auto_detect',
							'label' => __( 'Auto-detect (AI)', 'review-schema' ),
						],
					],
					self::getSchemaTypeOptions()
				),
				'proSchemaTypes' => self::getProSchemaTypes(),
				'isPro'              => function_exists( 'rtrsp' ),
				'seoActivePlugin'    => self::getActiveSeoPlugin(),
				'aiEnabled'          => 'yes' === self::getSetting( 'ai_enabled', 'no' ),
				'schemaEnabled'      => \Rtrs\Helpers\Functions::schema_enabled(),
				'isElementorPost'    => ElementorFaq::is_elementor_post( $post_id ),
				'hasElementorFaq'    => ElementorFaq::has_elementor_faq( $post_id ),
				'faqCount'           => (int) self::getSetting( 'faq_count', 5 ),
				'i18n'               => self::getI18nStrings(),
			]
		);
	}

	/**
	 * Detect the active SEO plugin for the "Apply with AI" confirm/notice UI.
	 *
	 * @return string 'yoast' | 'rank_math' | 'none'.
	 */
	private static function getActiveSeoPlugin() {
		if ( \Rtrs\Modules\Schema\Hooks\SeoHooks::isYoastActive() ) {
			return 'yoast';
		}
		if ( \Rtrs\Modules\Schema\Hooks\SeoHooks::isRankMathActive() ) {
			return 'rank_math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'seopress';
		}
		if ( function_exists( 'genesis' ) || defined( 'PARENT_THEME_VERSION' ) ) {
			return 'genesis';
		}
		return 'none';
	}

	// -------------------------------------------------------------------------
	// Classic Editor metabox + assets
	// -------------------------------------------------------------------------

	/**
	 * Register Classic Editor metabox.
	 *
	 * Only registers when the block editor is NOT active for the current post.
	 *
	 * @param string   $post_type Current post type slug.
	 * @param \WP_Post $post      Current post object.
	 *
	 * @return void
	 */
	public function addClassicEditorMetabox( $post_type, $post ) {
		if ( 'yes' !== self::getSetting( 'ai_enabled', 'no' ) ) {
			return;
		}
		if ( use_block_editor_for_post( $post ) ) {
			return;
		}
		if ( rtrs()->getPostType() === $post_type || 'rtrs_affiliate' === $post_type ) {
			return;
		}
		add_meta_box(
			'rtrs-ai-schema-metabox',
			__( 'SchemaEngine AI', 'review-schema' ),
			[ $this, 'renderClassicEditorMetabox' ],
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render Classic Editor metabox — outputs a single container div.
	 *
	 * @param \WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public function renderClassicEditorMetabox( $post ) {
		echo '<div id="aise-classic-panel"></div>';
	}

	/**
	 * Enqueue Classic Editor assets (CSS + JS).
	 *
	 * @return void
	 */
	public function enqueueClassicEditorAssets() {
		$screen = get_current_screen();

		if ( ! $screen || $screen->is_block_editor() || 'post' !== $screen->base ) {
			return;
		}

		if ( ! in_array( $screen->post_type, self::getSupportedPostTypes(), true ) ) {
			return;
		}

		/** Shared panel styles. */
		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			RTRS_VERSION
		);

		/** Classic editor metabox overrides. */
		wp_enqueue_style(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/classic-editor-panel.css' ),
			[ 'rtrs-ai-editor-panel' ],
			RTRS_VERSION
		);

		/** Classic editor vanilla JS panel. */
		wp_enqueue_script(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/classic-editor-panel.js' ),
			[ 'wp-api-fetch', 'wp-hooks' ],
			RTRS_VERSION,
			true
		);

		/** Resolve the post ID. */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) :
				   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Admin screen read-only check; no state change.
				   ( isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : get_the_ID() );

		wp_localize_script(
			'rtrs-ai-classic-editor-panel',
			'aiseData',
			$this->buildBasePanelData( $post_id )
		);
	}

	// -------------------------------------------------------------------------
	// Elementor editor SEO report panel
	// -------------------------------------------------------------------------

	/**
	 * Resolve the post ID being edited in the Elementor editor.
	 *
	 * Returns 0 (skip) unless Elementor is active, AI is enabled, the current
	 * user can edit the post, and the post type supports the AI panel.
	 *
	 * @return int Post ID, or 0 when the panel should not load.
	 */
	private function getElementorEditPostId() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return 0;
		}

		if ( 'yes' !== self::getSetting( 'ai_enabled', 'no' ) ) {
			return 0;
		}

		$post_id = (int) \Elementor\Plugin::$instance->editor->get_post_id();

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return 0;
		}

		if ( ! in_array( get_post_type( $post_id ), self::getSupportedPostTypes(), true ) ) {
			return 0;
		}

		return $post_id;
	}

	/**
	 * Enqueue the Elementor SEO report assets.
	 *
	 * Hooked to: elementor/editor/after_enqueue_scripts
	 *
	 * @return void
	 */
	public function enqueueElementorSeoAssets() {
		$post_id = $this->getElementorEditPostId();

		if ( ! $post_id ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		/** Shared generate-panel styles (aise-*) + classic panel overrides. */
		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			RTRS_VERSION
		);

		wp_enqueue_style(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/classic-editor-panel.css' ),
			[ 'rtrs-ai-editor-panel' ],
			RTRS_VERSION
		);

		wp_enqueue_style(
			'rtrs-ai-elementor-panel',
			rtrs()->get_assets_uri( 'ai/css/elementor-panel.css' ),
			[ 'rtrs-ai-classic-editor-panel' ],
			RTRS_VERSION
		);

		/**
		 * The full schema generate panel (brand, schema type, FAQ settings,
		 * action cards, Generate → preview → save) is the same vanilla-JS module
		 * used by the classic editor, Tutor, FluentCart and SureCart. Mounting it
		 * into the Elementor drawer gives the Elementor panel full generate parity.
		 */
		wp_enqueue_script(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/classic-editor-panel.js' ),
			[ 'wp-api-fetch', 'wp-hooks' ],
			RTRS_VERSION,
			true
		);

		wp_enqueue_script(
			'rtrs-ai-elementor-panel',
			rtrs()->get_assets_uri( 'ai/js/elementor-panel.js' ),
			[ 'wp-api-fetch', 'rtrs-ai-classic-editor-panel' ],
			RTRS_VERSION,
			true
		);

		wp_localize_script(
			'rtrs-ai-classic-editor-panel',
			'aiseData',
			array_merge(
				$this->buildBasePanelData( $post_id ),
				[
					'isElementor'   => true,
					'elementorIcon' => rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ),
					'editPostUrl'   => get_edit_post_link( $post_id, 'raw' ),
					'siteSchemaUrl' => admin_url( 'admin.php?page=review-schema#/schema' ),
					// The Elementor drawer renders its own SEO/AEO/GEO report, so
					// suppress the classic module's duplicate report inside the
					// mounted generate panel.
					'suppressReport' => true,
				]
			)
		);

		/**
		 * Elementor V2 top bar (App Bar) launcher button.
		 *
		 * Registers into the LEFT tools cluster (toolsMenu), beside the Design
		 * System button — not the right-side utilities row, which only fits four
		 * inline icons and overflowed native controls into the "⋮" menu. When the
		 * App Bar package is unavailable the panel falls back to its floating FAB.
		 */
		if ( wp_script_is( 'elementor-v2-editor-app-bar', 'registered' ) ) {
			wp_enqueue_script(
				'rtrs-ai-elementor-appbar',
				rtrs()->get_assets_uri( 'ai/js/elementor-appbar.js' ),
				[ 'react', 'elementor-v2-editor-app-bar', 'elementor-v2-icons', 'rtrs-ai-elementor-panel' ],
				RTRS_VERSION,
				true
			);
		}
	}

	/**
	 * Render the Elementor SEO report mount container.
	 *
	 * The floating launcher and slide-in drawer are built by the JS into this
	 * root, matching the plugin's other footer-panel surfaces.
	 *
	 * Hooked to: elementor/editor/footer
	 *
	 * @return void
	 */
	public function renderElementorSeoPanel() {
		if ( ! $this->getElementorEditPostId() ) {
			return;
		}

		echo '<div id="rtrs-elementor-seo"></div>';
	}

	// -------------------------------------------------------------------------
	// Tutor LMS Course Builder support
	// -------------------------------------------------------------------------

	/**
	 * Check if current admin page is the Tutor LMS course builder with a course ID.
	 *
	 * @return int|false Course post ID or false.
	 */
	private function getTutorCourseId() {
		global $pagenow;

		if ( ! is_admin() || 'admin.php' !== $pagenow ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'create-course' !== $page ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

		if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
			return false;
		}

		return $course_id;
	}

	/**
	 * Render AI panel container on the Tutor course builder page.
	 *
	 * Hooked to: tutor_course_builder_footer
	 *
	 * @return void
	 */
	public function renderTutorCourseBuilderPanel() {
		$course_id = $this->getTutorCourseId();

		if ( ! $course_id ) {
			return;
		}

		echo '<div id="aise-tutor-wrapper" class="aise-tutor-wrapper aise-tutor-wrapper--collapsed">';
		echo '<button type="button" id="aise-tutor-toggle" class="aise-tutor-toggle">';
		echo '<span class="aise-tutor-toggle__icon dashicons dashicons-schema"></span>';
		echo '<span class="aise-tutor-toggle__label">' . esc_html__( 'SchemaEngine AI', 'review-schema' ) . '</span>';
		echo '<span class="aise-tutor-toggle__arrow dashicons dashicons-arrow-up-alt2"></span>';
		echo '</button>';
		echo '<div id="aise-classic-panel" class="aise-tutor-panel"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue AI panel assets on the Tutor course builder page.
	 *
	 * Hooked to: admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function enqueueTutorCourseBuilderAssets() {
		$course_id = $this->getTutorCourseId();

		if ( ! $course_id ) {
			return;
		}

		/** Shared panel styles. */
		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			RTRS_VERSION
		);

		/** Classic editor metabox overrides. */
		wp_enqueue_style(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/classic-editor-panel.css' ),
			[ 'rtrs-ai-editor-panel' ],
			RTRS_VERSION
		);

		/** Classic editor vanilla JS panel. */
		wp_enqueue_script(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/classic-editor-panel.js' ),
			[ 'wp-api-fetch', 'wp-hooks' ],
			RTRS_VERSION,
			true
		);

		wp_localize_script(
			'rtrs-ai-classic-editor-panel',
			'aiseData',
			array_merge(
				$this->buildBasePanelData( $course_id ),
				[
					'isTutor' => true, // Dedicated builder surface; show the SEO/AEO/GEO report.
				]
			)
		);

		/** Toggle script for the collapsible Tutor panel. */
		wp_add_inline_script(
			'rtrs-ai-classic-editor-panel',
			'(function(){' .
				'var toggle=document.getElementById("aise-tutor-toggle");' .
				'var wrapper=document.getElementById("aise-tutor-wrapper");' .
				'var panel=document.getElementById("aise-classic-panel");' .
				'if(!toggle||!wrapper||!panel)return;' .
				'var maxH=window.innerHeight-100;' .
				'toggle.addEventListener("click",function(){' .
					'var isOpen=panel.classList.contains("aise-tutor-panel--open");' .
					'if(isOpen){' .
						'panel.style.maxHeight=panel.scrollHeight+"px";' .
						'panel.offsetHeight;' .
						'panel.style.maxHeight="0";' .
						'panel.classList.remove("aise-tutor-panel--open");' .
						'wrapper.classList.add("aise-tutor-wrapper--collapsed");' .
					'}else{' .
						'wrapper.classList.remove("aise-tutor-wrapper--collapsed");' .
						'panel.classList.add("aise-tutor-panel--open");' .
						'var h=panel.scrollHeight;' .
						'panel.style.maxHeight=Math.min(h,maxH)+"px";' .
						'panel.addEventListener("transitionend",function fn(){' .
							'panel.removeEventListener("transitionend",fn);' .
							'panel.style.maxHeight=maxH+"px";' .
						'});' .
					'}' .
				'});' .
			'})();'
		);
	}

	// -------------------------------------------------------------------------
	// FluentCart Product Builder support
	// -------------------------------------------------------------------------

	/**
	 * Check if current admin page is the FluentCart product editor.
	 *
	 * FluentCart uses a SPA admin interface. Product editing happens at:
	 * admin.php?page=fluent-cart#/products/{product_id}/...
	 *
	 * Since URL hash is not available server-side, we detect the FluentCart
	 * admin page and let JS handle product ID detection.
	 *
	 * @return bool True if on FluentCart admin page.
	 */
	private function isFluentCartAdminPage() {
		if ( ! is_admin() ) {
			return false;
		}

		global $pagenow;

		if ( 'admin.php' !== $pagenow ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		return 'fluent-cart' === $page;
	}

	/**
	 * Render AI panel container on the FluentCart product builder page.
	 *
	 * Hooked to: fluent_cart/admin_js_loaded
	 *
	 * @param mixed $app FluentCart app instance.
	 *
	 * @return void
	 */
	public function renderFluentCartProductPanel( $app ) {
		if ( ! $this->isFluentCartAdminPage() ) {
			return;
		}

		echo '<div id="aise-fluentcart-wrapper" class="aise-fluentcart-wrapper aise-fluentcart-wrapper--collapsed" style="display:none;">';
		echo '<button type="button" id="aise-fluentcart-toggle" class="aise-fluentcart-toggle">';
		echo '<span class="aise-fluentcart-toggle__icon dashicons dashicons-schema"></span>';
		echo '<span class="aise-fluentcart-toggle__label">' . esc_html__( 'SchemaEngine AI', 'review-schema' ) . '</span>';
		echo '<span class="aise-fluentcart-toggle__arrow dashicons dashicons-arrow-up-alt2"></span>';
		echo '</button>';
		echo '<div id="aise-classic-panel" class="aise-fluentcart-panel"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue AI panel assets on the FluentCart product builder page.
	 *
	 * Hooked to: admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function enqueueFluentCartProductAssets() {
		if ( ! $this->isFluentCartAdminPage() ) {
			return;
		}

		/** Shared panel styles. */
		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			RTRS_VERSION
		);

		/** Classic editor metabox overrides. */
		wp_enqueue_style(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/classic-editor-panel.css' ),
			[ 'rtrs-ai-editor-panel' ],
			RTRS_VERSION
		);

		/** FluentCart-specific styles. */
		wp_enqueue_style(
			'rtrs-ai-fluentcart-panel',
			rtrs()->get_assets_uri( 'ai/css/fluentcart-panel.css' ),
			[ 'rtrs-ai-classic-editor-panel' ],
			RTRS_VERSION
		);

		/** Dashicons for icons. */
		wp_enqueue_style( 'dashicons' );

		/** Classic editor vanilla JS panel. */
		wp_enqueue_script(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/classic-editor-panel.js' ),
			[ 'wp-api-fetch', 'wp-hooks' ],
			RTRS_VERSION,
			true
		);

		/**
		 * FluentCart-specific JS for product detection and panel initialization.
		 * We pass initial data without a post ID; JS will detect the product ID
		 * from the URL hash and fetch schema data via REST API.
		 */
		wp_localize_script(
			'rtrs-ai-classic-editor-panel',
			'aiseData',
			$this->buildFluentCartPanelData()
		);

		/** FluentCart product detection and panel toggle script. */
		wp_enqueue_script(
			'rtrs-ai-fluentcart-panel',
			rtrs()->get_assets_uri( 'ai/js/fluentcart-panel.js' ),
			[ 'rtrs-ai-classic-editor-panel' ],
			RTRS_VERSION,
			true
		);
	}

	/**
	 * Build localized data for FluentCart panel without a specific product ID.
	 *
	 * Since FluentCart uses hash-based routing, we can't determine the product ID
	 * server-side. The JS will detect it and fetch product-specific data via REST.
	 *
	 * @return array Localized data for aiseData.
	 */
	private function buildFluentCartPanelData() {
		// FluentCart is a hash-routed SPA: the product ID is unknown server-side,
		// so start from the shared base with post ID 0 and let the JS detect the
		// product and fetch its schema data via REST. Reuses the base payload so
		// the panel exposes the same actions and SEO report as every other surface.
		return array_merge(
			$this->buildBasePanelData( 0 ),
			[
				'isFluentCart' => true, // Flag for JS to handle specially.
			]
		);
	}

	// -------------------------------------------------------------------------
	// SureCart Product Builder support
	// -------------------------------------------------------------------------

	/**
	 * Check if current admin page is the SureCart product editor.
	 *
	 * SureCart uses query parameter-based routing:
	 * - admin.php?page=sc-products&action=edit&id={product_id} (edit existing)
	 * - admin.php?page=sc-products&action=edit (add new)
	 *
	 * @return bool True if on SureCart product edit page.
	 */
	private function isSureCartProductEditPage() {
		if ( ! is_admin() ) {
			return false;
		}

		global $pagenow;

		if ( 'admin.php' !== $pagenow ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// Must be on sc-products page with edit action.
		return 'sc-products' === $page && 'edit' === $action;
	}

	/**
	 * Get the WordPress post ID for a SureCart product.
	 *
	 * SureCart uses its own platform IDs (e.g., 'prod_abc123') in the URL,
	 * not WordPress post IDs. We need to look up the WordPress post by the
	 * 'sc_id' meta key.
	 *
	 * @return int WordPress post ID or 0 if not found.
	 */
	private function getSureCartProductId() {
		if ( ! $this->isSureCartProductEditPage() ) {
			return 0;
		}

		// The 'id' parameter is the SureCart platform ID, not WordPress post ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen read-only check; no state change.
		$sc_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

		if ( empty( $sc_id ) ) {
			return 0;
		}

		// Check user permission first.
		if ( ! current_user_can( 'edit_sc_products' ) ) {
			return 0;
		}

		// Look up the WordPress post by SureCart ID stored in meta.
		$posts = get_posts( [
			'post_type'      => 'sc_product',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary meta query for plugin feature.
			'meta_key'       => 'sc_id',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Necessary meta query for plugin feature.
			'meta_value'     => $sc_id,
			'fields'         => 'ids',
		] );

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Render AI panel container on the SureCart product edit page.
	 *
	 * Hooked to: admin_footer
	 *
	 * @return void
	 */
	public function renderSureCartProductPanel() {
		if ( ! $this->isSureCartProductEditPage() ) {
			return;
		}

		echo '<div id="aise-surecart-wrapper" class="aise-surecart-wrapper aise-surecart-wrapper--collapsed">';
		echo '<button type="button" id="aise-surecart-toggle" class="aise-surecart-toggle">';
		echo '<span class="aise-surecart-toggle__icon dashicons dashicons-schema"></span>';
		echo '<span class="aise-surecart-toggle__label">' . esc_html__( 'SchemaEngine AI', 'review-schema' ) . '</span>';
		echo '<span class="aise-surecart-toggle__arrow dashicons dashicons-arrow-up-alt2"></span>';
		echo '</button>';
		echo '<div id="aise-classic-panel" class="aise-surecart-panel"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue AI panel assets on the SureCart product edit page.
	 *
	 * Hooked to: admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function enqueueSureCartProductAssets() {
		if ( ! $this->isSureCartProductEditPage() ) {
			return;
		}

		/** Shared panel styles. */
		wp_enqueue_style(
			'rtrs-ai-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/editor-panel.css' ),
			[],
			self::assetVersion( 'ai/css/editor-panel.css' )
		);

		/** Classic editor metabox overrides. */
		wp_enqueue_style(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/css/classic-editor-panel.css' ),
			[ 'rtrs-ai-editor-panel' ],
			self::assetVersion( 'ai/css/classic-editor-panel.css' )
		);

		/** SureCart-specific styles. */
		wp_enqueue_style(
			'rtrs-ai-surecart-panel',
			rtrs()->get_assets_uri( 'ai/css/surecart-panel.css' ),
			[ 'rtrs-ai-classic-editor-panel' ],
			self::assetVersion( 'ai/css/surecart-panel.css' )
		);

		/** Dashicons for icons. */
		wp_enqueue_style( 'dashicons' );

		/** Classic editor vanilla JS panel. */
		wp_enqueue_script(
			'rtrs-ai-classic-editor-panel',
			rtrs()->get_assets_uri( 'ai/js/classic-editor-panel.js' ),
			[ 'wp-api-fetch', 'wp-hooks' ],
			self::assetVersion( 'ai/js/classic-editor-panel.js' ),
			true
		);

		/**
		 * Pass product data to JS. Since SureCart uses query parameter routing,
		 * we can get the product ID server-side from the URL.
		 */
		$product_id = $this->getSureCartProductId();

		wp_localize_script(
			'rtrs-ai-classic-editor-panel',
			'aiseData',
			$this->buildSureCartPanelData( $product_id )
		);

		/** SureCart panel toggle script. */
		wp_enqueue_script(
			'rtrs-ai-surecart-panel',
			rtrs()->get_assets_uri( 'ai/js/surecart-panel.js' ),
			[ 'rtrs-ai-classic-editor-panel' ],
			self::assetVersion( 'ai/js/surecart-panel.js' ),
			true
		);
	}

	/**
	 * Cache-busting version for a built asset.
	 *
	 * The plugin version (RTRS_VERSION) is pinned per release, so rebuilt panel
	 * bundles keep the same `?ver=` and browsers serve the stale cached copy.
	 * Using the file's modification time guarantees a fresh URL whenever the
	 * asset actually changes, falling back to the plugin version if unreadable.
	 *
	 * @param string $relative Asset path relative to the plugin's `assets/` dir.
	 * @return string
	 */
	private static function assetVersion( $relative ) {
		$file = RTRS_PATH . 'assets/' . ltrim( $relative, '/' );

		return file_exists( $file ) ? (string) filemtime( $file ) : RTRS_VERSION;
	}

	/**
	 * Build localized data for SureCart panel.
	 *
	 * SureCart uses query parameter routing (admin.php?page=sc-products&action=edit&id=123)
	 * so we can determine the product ID server-side.
	 *
	 * @param int $product_id The product post ID (0 for new products).
	 *
	 * @return array Localized data for aiseData.
	 */
	private function buildSureCartPanelData( $product_id = 0 ) {
		// SureCart resolves a real WordPress post ID server-side, so the shared
		// base builder supplies the complete payload — schema actions, FAQ and
		// Elementor detection, plus the validation/SEO report — identical to the
		// classic editor and FluentCart panels.
		return array_merge(
			$this->buildBasePanelData( $product_id ),
			[
				'isSureCart' => true, // Flag for JS to handle specially.
			]
		);
	}

	// -------------------------------------------------------------------------
	// Shared helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the shared, complete localized data payload for every AI panel.
	 *
	 * Single source of truth for the classic editor, Tutor course builder,
	 * FluentCart and SureCart integrations. Guarantees every surface receives
	 * the same schema-generation actions and SEO/validation report data.
	 *
	 * @param int $post_id The post ID (0 when unknown, e.g. FluentCart SPA).
	 *
	 * @return array Localized data for aiseData.
	 */
	private function buildBasePanelData( $post_id ) {
		$schema_data = self::normalizeSchemaData( get_post_meta( $post_id, self::META_KEY, true ) );

		$faq_data     = get_post_meta( $post_id, '_rtrs_faqpage_data', true );
		$has_faq_data = false;
		if ( ! empty( $faq_data ) && is_array( $faq_data ) ) {
			foreach ( $faq_data as $item ) {
				if ( ! empty( $item['question'] ) ) {
					$has_faq_data = true;
					break;
				}
			}
		}

		$initial_validation = null;
		$initial_evaluation = null;
		$full_graph         = [];

		// Build full graph when content schemas exist or when FAQ data is stored
		// (FAQPage may be the only generated type and is stripped from content schemas).
		if ( ! empty( $schema_data ) || $has_faq_data ) {
			$renderer       = new SchemaRenderer();
			$global_schemas = $renderer->build_global_schemas( $post_id, $schema_data );
			$full_graph     = array_merge( $schema_data, $global_schemas );
			$full_graph     = apply_filters( 'rtrs_ai_schema_before_render', $full_graph, $post_id );

			$validator          = new SchemaValidator();
			$initial_validation = $validator->validate( $full_graph );

			$initial_evaluation = apply_filters( 'rtrs_ai_schema_evaluation', RestApi::get_dummy_evaluation(), $full_graph );
		}

		return [
			'restUrl'        => rest_url( 'rtrs-ai/v1/' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'postId'         => $post_id,
			'schemaData'     => $schema_data,
			'fullGraph'      => $full_graph,
			'schemaType'     => get_post_meta( $post_id, self::TYPE_META_KEY, true ),
			'confidence'     => get_post_meta( $post_id, self::CONFIDENCE_META_KEY, true ),
			'hasApiKey'      => self::hasApiKey(),
			'logoUrl'        => rtrs()->get_assets_uri( 'imgs/icon-128x128.gif' ),
			'validation'     => $initial_validation,
			'evaluation'     => $initial_evaluation,
			'settingsUrl'    => admin_url( 'admin.php?page=review-schema&tab=ai' ),
			'aiSettingsUrl'  => admin_url( 'admin.php?page=review-schema#/ai' ),
			'schemaTypes'    => array_merge(
				[
					[
						'value' => 'auto_detect',
						'label' => __( 'Auto-detect (AI)', 'review-schema' ),
					],
				],
				self::getSchemaTypeOptions()
			),
			'proSchemaTypes' => self::getProSchemaTypes(),
			'isPro'              => function_exists( 'rtrsp' ),
			'aiEnabled'          => 'yes' === self::getSetting( 'ai_enabled', 'no' ),
			'schemaEnabled'      => \Rtrs\Helpers\Functions::schema_enabled(),
			'hasFaqData'         => $has_faq_data,
			'isElementorPost'    => ElementorFaq::is_elementor_post( $post_id ),
			'hasElementorFaq'    => ElementorFaq::has_elementor_faq( $post_id ),
			'faqCount'           => (int) self::getSetting( 'faq_count', 5 ),
			'i18n'               => self::getI18nStrings(),
		];
	}
}
