<?php
/**
 * Schema Classifier - Step 1 of the two-step AI approach.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

defined( 'ABSPATH' ) || exit;

class SchemaClassifier {

	/**
	 * @var AIClient
	 */
	private $ai_client;

	const SUPPORTED_TYPES = [
		'Article',
		'TechArticle',
		'NewsArticle',
		'BlogPosting',
		'FAQPage',
		'HowTo',
		'Product',
		'Recipe',
		'Event',
		'LocalBusiness',
		'Restaurant',
		'VideoObject',
		'AudioObject',
		'Person',
		'Course',
		'Service',
		'QAPage',
		'AboutPage',
		'ContactPage',
		'Movie',
		'Mosque',
		'Church',
		'HinduTemple',
		'BuddhistTemple',
		'ProfilePage',
		'MedicalWebPage',
		'Book',
		'RealEstateListing',
		'JobPosting',
		'SoftwareApplication',
		'ImageObject',
		'SpecialAnnouncement',
		'VacationRental',
		'Vehicle',
		'TVSeries',
		'PodcastEpisode',
		'DiscussionForumPosting',
		'Dataset',
		'TaxiService',
		'WebPage',
	];

	public function __construct() {
		$this->ai_client = new AIClient();
	}

	/**
	 * @param $compact_payload
	 *
	 * @return array|\WP_Error
	 */
	public function classify( $compact_payload ) {
		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $compact_payload );

		$result = $this->ai_client->complete( $system_prompt, $user_prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->normalize_result( $result );
	}

	/**
	 * @return string
	 */
	private function build_system_prompt() {
		$types = implode( ', ', self::SUPPORTED_TYPES );

		return "You are a structured data (Schema.org) expert. Your job is to analyze content signals and determine the most appropriate schema type(s) for a web page.\n\n"
			. "Supported schema types: {$types}\n\n"
			. "Rules:\n"
			. "1. Return a JSON object with \"primary_type\", \"secondary_types\" (array), and \"confidence\" (0-100).\n"
			. "2. \"primary_type\" is the single best schema type.\n"
			. "3. \"secondary_types\" are additional schemas that should also be applied.\n"
			. "4. \"confidence\" reflects how certain you are about the primary type (0-100).\n"
			. "5. Do NOT include BreadcrumbList, WebPage, WebSite, or Organization in secondary_types — these are generated separately as global schemas.\n"
			. "6. If the content is a standard blog post, use \"BlogPosting\" (not \"Article\").\n"
			. "7. If FAQ patterns are detected, include \"FAQPage\" (as primary or secondary).\n"
			. "8. If unsure, default to \"WebPage\" with lower confidence.\n\n"
			. "Respond ONLY with valid JSON. No explanations.\n\n"
			. "Example output:\n"
			. "{\n"
			. "  \"primary_type\": \"BlogPosting\",\n"
			. "  \"secondary_types\": [\"FAQPage\"],\n"
			. "  \"confidence\": 85,\n"
			. "  \"reasoning\": \"Blog post with FAQ section detected\"\n"
			. "}";
	}

	/**
	 * @param $payload
	 *
	 * @return string
	 */
	private function build_user_prompt( $payload ) {
		$data = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return "Analyze this content and classify the schema type:\n\n{$data}";
	}

	/**
	 * @param $result
	 *
	 * @return array
	 */
	private function normalize_result( $result ) {
		$primary = $result['primary_type'] ?? 'WebPage';

		if ( ! in_array( $primary, self::SUPPORTED_TYPES, true ) ) {
			$primary = 'WebPage';
		}

		$secondary = $result['secondary_types'] ?? [];

		// Filter: must be a supported type and not a global schema type.
		$global_types = [ 'BreadcrumbList', 'WebSite', 'Organization' ];
		$secondary    = array_filter( $secondary, function ( $type ) use ( $global_types ) {
			return in_array( $type, self::SUPPORTED_TYPES, true ) && ! in_array( $type, $global_types, true );
		} );

		return [
			'primary_type'    => $primary,
			'secondary_types' => array_values( $secondary ),
			'confidence'      => min( 100, max( 0, (int) ( $result['confidence'] ?? 50 ) ) ),
			'reasoning'       => sanitize_text_field( $result['reasoning'] ?? '' ),
		];
	}
}
