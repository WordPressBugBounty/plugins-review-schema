<?php
/**
 * Schema Extractor - Step 2 of the two-step AI approach.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

defined( 'ABSPATH' ) || exit;

class SchemaExtractor {

	/**
	 * Schema types that support aggregateRating per schema.org / Google Rich Results.
	 */
	const AGGREGATE_RATING_TYPES = [
		'Product',
		'ProductGroup',
		'LocalBusiness',
		'Restaurant',
		'SoftwareApplication',
		'WebApplication',
		'MobileApplication',
		'Movie',
		'Book',
		'Course',
		'Event',
		'Recipe',
		'Service',
		'TaxiService',
	];

	/**
	 * @var AIClient
	 */
	private $ai_client;

	private $entity_type = 'Organization';

	private $entity_id = '';

	private $faq_count = null;

	public function __construct() {
		$this->ai_client = new AIClient();
	}

	public function extract( $full_payload, $primary_type, $secondary_types = [], $options = [] ) {
		if ( ! empty( $options['faq_count'] ) ) {
			$this->faq_count = (int) $options['faq_count'];
		}

		$entity_data       = AIInit::getEntityData();
		$this->entity_type = $entity_data['entity_type'];

		$site_url        = rtrim( $full_payload['contextual']['site_url'] ?? home_url( '/' ), '/' );
		$fragment        = ( 'Person' === $this->entity_type ) ? 'person' : 'organization';
		$this->entity_id = $site_url . '/#' . $fragment;

		$system_prompt = $this->build_system_prompt( $primary_type, $secondary_types );
		$user_prompt   = $this->build_user_prompt( $full_payload );

		$result = $this->ai_client->complete( $system_prompt, $user_prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->normalize_schemas( $result, $full_payload );
	}

	private function build_system_prompt( $primary_type, $secondary_types ) {
		$global     = [ 'WebPage', 'WebSite', 'BreadcrumbList', 'Organization', 'LocalBusiness' ];
		$all_types  = array_diff( array_merge( [ $primary_type ], $secondary_types ), $global );

		if ( empty( $all_types ) ) {
			$all_types = [ 'Article' ];
		}

		$types_str      = implode( ', ', array_values( $all_types ) );
		$type_templates = [];
		foreach ( $all_types as $type ) {
			$tpl = $this->get_type_template( $type );
			if ( $tpl ) {
				$type_templates[] = $tpl;
			}
		}
		$type_templates_str = implode( "\n\n", $type_templates );
		$entity_frag        = ( 'Person' === $this->entity_type ) ? 'person' : 'organization';
		$today              = wp_date( 'Y-m-d' );

		return "You are a Schema.org structured data expert. Generate content-specific JSON-LD schema markup.\n"
			. "\n"
			. "Target schema types: {$types_str}\n"
			. "\n"
			. "Rules:\n"
			. "1. Return a single JSON object with \"@context\": \"https://schema.org\" and \"@graph\": [...].\n"
			. "2. Each item inside @graph must have \"@type\" and \"@id\". Do NOT add \"@context\" to individual @graph items.\n"
			. "3. Use ONLY real data from the provided content. NEVER fabricate or hallucinate values.\n"
			. "4. If a field cannot be determined from the content, OMIT it entirely — never use placeholder values.\n"
			. "5. All URLs must be absolute. Dates must be ISO 8601. Images must include url, width, height when available.\n"
			. "6. Generate a schema node for EACH requested type in the @graph array.\n"
			. "7. Do NOT generate WebPage, WebSite, BreadcrumbList, or Organization/entity nodes — these are built separately from site settings.\n"
			. "\n"
			. "@id naming convention (use the actual permalink from the content):\n"
			. "- Use {permalink}#{type_lowercase} pattern, e.g. {permalink}#article, {permalink}#faqpage, {permalink}#product, etc.\n"
			. "\n"
			. "Cross-references to global nodes (reference by @id — the actual nodes are built separately):\n"
			. "- mainEntityOfPage: {\"@id\": \"{permalink}#webpage\"}\n"
			. "- publisher:        {\"@id\": \"{site_url}#{$entity_frag}\"}\n"
			. "\n"
			. "Follow Google Rich Results requirements for each type.\n"
			. "\n"
			. "{$type_templates_str}\n"
			. "\n"
			. "Respond ONLY with valid JSON. No explanations or markdown.\n"
			. "\n"
			. "Example structure:\n"
			. "{\n"
			. "  \"@context\": \"https://schema.org\",\n"
			. "  \"@graph\": [\n"
			. "    {\n"
			. "      \"@type\": \"BlogPosting\",\n"
			. "      \"@id\": \"https://example.com/post/#article\",\n"
			. "      \"mainEntityOfPage\": {\"@id\": \"https://example.com/post/#webpage\"},\n"
			. "      \"publisher\": {\"@id\": \"https://example.com/#{$entity_frag}\"},\n"
			. "      \"headline\": \"Post Title\",\n"
			. "      \"datePublished\": \"{$today}\",\n"
			. "      \"author\": {\"@type\": \"Person\", \"name\": \"Author Name\"}\n"
			. "    }\n"
			. "  ]\n"
			. "}";
	}

	private function get_type_template( $type ) {
		$templates = [
			'Article'              => $this->get_article_template(),
			'TechArticle'          => $this->get_article_template(),
			'NewsArticle'          => $this->get_article_template(),
			'BlogPosting'          => $this->get_article_template(),
			'FAQPage'              => $this->get_faq_template(),
			'HowTo'                => $this->get_howto_template(),
			'Event'                => $this->get_event_template(),
			'VideoObject'          => $this->get_video_template(),
			'AudioObject'          => $this->get_audio_template(),
			'Person'               => $this->get_person_template(),
			'Service'              => $this->get_service_template(),
			'Movie'                => $this->get_movie_template(),
			'SoftwareApplication'  => $this->get_software_app_template(),
			'WebApplication'       => $this->get_software_app_template(),
			'MobileApplication'    => $this->get_software_app_template(),
		];

		$template = $templates[ $type ] ?? '';

		// Allow Pro to provide templates for pro-gated types (Product, Course, Book, etc.).
		return apply_filters( 'rtrs_ai_type_template', $template, $type, $this->entity_type );
	}

	private function get_article_template() {
		return "Required fields for Article/BlogPosting:\n"
			. "- headline (max 110 chars)\n"
			. "- author (Person with name and url)\n"
			. "- datePublished (ISO 8601)\n"
			. "- dateModified (ISO 8601)\n"
			. "- image (ImageObject with url, width, height)\n"
			. "- publisher (Organization with name and logo)\n"
			. "- description (max 160 chars)\n"
			. "- articleBody (full text content of the article, plain text without HTML)\n"
			. "- mainEntityOfPage (url)\n"
			. "- wordCount";
	}

	/**
	 * Cap a FAQPage's questions to the configured FAQ count.
	 *
	 * The model is asked for exactly N pairs but may return more; this
	 * enforces the limit deterministically so the generated/saved schema
	 * never exceeds the "Number of FAQs" setting.
	 *
	 * @param array $schema FAQPage schema, modified by reference.
	 *
	 * @return void
	 */
	private function limit_faq_questions( &$schema ) {
		if ( empty( $schema['mainEntity'] ) || ! is_array( $schema['mainEntity'] ) ) {
			return;
		}

		// A single Question object is already within the limit.
		if ( isset( $schema['mainEntity']['@type'] ) ) {
			return;
		}

		$limit = max( 1, (int) ( $this->faq_count ?? AIInit::getSetting( 'faq_count', 5 ) ) );

		if ( count( $schema['mainEntity'] ) > $limit ) {
			$schema['mainEntity'] = array_slice( array_values( $schema['mainEntity'] ), 0, $limit );
		}
	}

	private function get_faq_template() {
		$faq_count = max( 1, (int) ( $this->faq_count ?? AIInit::getSetting( 'faq_count', 5 ) ) );

		// Answer engines only credit "strong coverage" at 3+ pairs, so the FAQ
		// must never come back short of that (bounded by the requested count when
		// the user deliberately asks for fewer). Guarantees an issue-free FAQ.
		$faq_min = min( $faq_count, 3 );

		return "Required fields for FAQPage:\n"
			. "- mainEntity: array of Question objects\n"
			. "- Each Question needs: name (the question text), acceptedAnswer with @type Answer and text\n"
			. "- Generate {$faq_count} relevant question/answer pairs grounded in the content. This is a hard requirement: always produce a complete set of at least {$faq_min} distinct pairs — never fewer — and never more than {$faq_count}. If the body is thin, cover closely related sub-questions a reader would still ask rather than returning fewer pairs\n"
			. "- AVOID DUPLICATES: if the content data includes `structural.faq_patterns` (questions already present on the page), do NOT repeat, restate, or lightly reword any of them. Treat those as already covered and generate DISTINCT, complementary questions that address what the existing ones do not — a reader should never see the same question twice on the page\n"
			. "- Questions should be natural and reflect what a reader would likely ask about this topic\n"
			. "- Base the questions on the real, high-intent questions people ask about this topic on community platforms like Quora, Reddit, Google (People Also Ask), and related forums — phrase them the way real users search and ask\n"
			. "- Phrase every question.name as an actual question that ends with a question mark '?' (or opens with who/what/when/where/why/how/which/can/does/is/are/should)\n"
			. "- Begin with a direct answer to the question in the very first sentence.\n"
			. "- Write each acceptedAnswer.text as a self-contained, answer-first response of about 40-60 words (never fewer than 20). Lead with the direct answer in the first sentence, then add one or two supporting details grounded in the provided content — do not pad with invented facts\n"
			. "- Make every answer self-contained: name the subject explicitly in the first sentence. NEVER open an answer with a bare pronoun such as 'It', 'This', 'That', 'They', 'These', 'Those', 'There', 'Its' or 'Their' — the first sentence must read as a complete answer when quoted alone\n"
			. "- Write for voice assistants: use short, plain sentences. Keep sentences under 25 words and aim for an average of about 15-18 words. Prefer active voice; avoid passive constructions\n"
			. "- Keep each answer to a single tight paragraph (well under 90 words) so an answer engine can quote it whole\n"
			. "- Every answer must be complete and end with a concluding sentence; never end abruptly or leave the explanation unfinished.\n"
			. "- Do NOT copy or paraphrase the article's sentences verbatim and do NOT summarize the page; rephrase in fresh wording that directly answers the question\n"
			. "- Each answer must cover a distinct point; never repeat the same information, phrasing, or details across multiple answers\n"
			. "- Avoid one-line or single-sentence answers; an answer engine should be able to quote the text as a complete response";
	}

	private function get_howto_template() {
		return "Required fields for HowTo:\n"
			. "- name, description\n"
			. "- step: array of HowToStep objects with name, text, and position\n"
			. "- totalTime (ISO 8601 duration format, if determinable)\n"
			. "- image (if available)";
	}

	private function get_event_template() {
		return "Required fields for Event:\n"
			. "- name, description\n"
			. "- startDate, endDate (ISO 8601)\n"
			. "- location (Place with name and address)\n"
			. "- organizer (Organization or Person)\n"
			. "- offers (if ticket info available)\n"
			. "- eventStatus, eventAttendanceMode";
	}

	private function get_video_template() {
		return "Required fields for VideoObject:\n"
			. "- name, description\n"
			. "- thumbnailUrl, uploadDate\n"
			. "- contentUrl or embedUrl\n"
			. "- duration (ISO 8601 if known)";
	}

	private function get_person_template() {
		return "Required fields for Person:\n"
			. "- name, description\n"
			. "- image, url\n"
			. "- jobTitle (if available)\n"
			. "- sameAs (social links if available)";
	}

	private function get_audio_template() {
		return "Required fields for AudioObject:\n"
			. "- name, description\n"
			. "- contentUrl or encodingFormat\n"
			. "- duration (ISO 8601 if known)\n"
			. "- uploadDate";
	}

	private function get_service_template() {
		return "Required fields for Service:\n"
			. "- name, description\n"
			. "- provider (Organization or Person)\n"
			. "- serviceType, areaServed (if available)\n"
			. "- offers (if pricing available)";
	}

	private function get_movie_template() {
		return "Required fields for Movie:\n"
			. "- name, description, image\n"
			. "- director (Person)\n"
			. "- dateCreated\n"
			. "- actor (array of Person, if available)\n"
			. "- duration (ISO 8601 if known)\n"
			. "- aggregateRating (if reviews exist)";
	}

	private function get_software_app_template() {
		return "Required fields for SoftwareApplication:\n"
			. "- name, description\n"
			. "- applicationCategory\n"
			. "- operatingSystem (if available)\n"
			. "- offers (if pricing available)\n"
			. "- aggregateRating (if reviews exist)";
	}

	private function build_user_prompt( $payload ) {
		$data = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return "Generate JSON-LD schema from this content data:\n\n{$data}";
	}

	private function normalize_schemas( $result, $full_payload ) {
		if ( isset( $result['@graph'] ) && is_array( $result['@graph'] ) ) {
			$schemas = $result['@graph'];
		} elseif ( isset( $result['schemas'] ) && is_array( $result['schemas'] ) ) {
			$schemas = $result['schemas'];
		} else {
			$schemas = [ $result ];
		}

		$primary    = $full_payload['primary'] ?? [];
		$secondary  = $full_payload['secondary'] ?? [];
		$contextual = $full_payload['contextual'] ?? [];

		$permalink = trailingslashit( $primary['permalink'] ?? home_url( '/' ) );
		$site_url  = rtrim( $contextual['site_url'] ?? home_url( '/' ), '/' );

		$entity_data = AIInit::getEntityData();
		$site_name   = $entity_data['name'] ?: ( $contextual['site_name'] ?? get_bloginfo( 'name' ) );
		$entity_id   = $this->entity_id ?: ( $site_url . '/#organization' );

		$type_id_map = [
			'Article'              => $permalink . '#article',
			'TechArticle'          => $permalink . '#techarticle',
			'NewsArticle'          => $permalink . '#newsarticle',
			'BlogPosting'          => $permalink . '#article',
			'FAQPage'              => $permalink . '#faqpage',
			'HowTo'                => $permalink . '#howto',
			'Recipe'               => $permalink . '#recipe',
			'Event'                => $permalink . '#event',
			'VideoObject'          => $permalink . '#video',
			'AudioObject'          => $permalink . '#audio',
			'Product'              => $permalink . '#product',
			'ProductGroup'         => $permalink . '#productgroup',
			'Course'               => $permalink . '#course',
			'Person'               => $permalink . '#person',
			'Service'              => $permalink . '#service',
			'QAPage'               => $permalink . '#qapage',
			'Movie'                => $permalink . '#movie',
			'Book'                 => $permalink . '#book',
			'JobPosting'           => $permalink . '#jobposting',
			'SoftwareApplication'  => $permalink . '#softwareapplication',
			'WebApplication'       => $permalink . '#webapplication',
			'MobileApplication'    => $permalink . '#mobileapplication',
			'Restaurant'           => $permalink . '#restaurant',
			'ImageObject'          => $permalink . '#imageobject',
			'TVSeries'             => $permalink . '#tvseries',
			'PodcastEpisode'       => $permalink . '#podcastepisode',
			'DiscussionForumPosting' => $permalink . '#discussionforumposting',
			'Dataset'              => $permalink . '#dataset',
			'TaxiService'          => $permalink . '#taxiservice',
			'VacationRental'       => $permalink . '#vacationrental',
			'Vehicle'              => $permalink . '#vehicle',
			'RealEstateListing'    => $permalink . '#realestatelisting',
			'Mosque'               => $permalink . '#mosque',
			'Church'               => $permalink . '#church',
			'HinduTemple'          => $permalink . '#hindutemple',
			'BuddhistTemple'       => $permalink . '#buddhisttemple',
			'TouristAttraction'    => $permalink . '#touristattraction',
			'ProfilePage'          => $permalink . '#profilepage',
			'MedicalWebPage'       => $permalink . '#medicalwebpage',
			'AboutPage'            => $permalink . '#aboutpage',
			'ContactPage'          => $permalink . '#contactpage',
			'SpecialAnnouncement'  => $permalink . '#specialannouncement',
		];

		// Filter out global node types the AI may have generated despite instructions.
		$global_types   = [ 'WebPage', 'WebSite', 'BreadcrumbList' ];
		$norm_entity_id = $this->normalize_graph_id( $entity_id );

		$schemas = array_values( array_filter( $schemas, function ( $schema ) use ( $global_types, $norm_entity_id ) {
			$type = $schema['@type'] ?? '';

			// Always filter WebPage, WebSite, BreadcrumbList.
			if ( in_array( $type, $global_types, true ) ) {
				return false;
			}

			// Filter entity nodes that match the site's entity @id.
			if ( ! empty( $schema['@id'] ) && $this->normalize_graph_id( $schema['@id'] ) === $norm_entity_id ) {
				return false;
			}

			return true;
		} ) );

		foreach ( $schemas as &$schema ) {
			$type = $schema['@type'] ?? '';

			unset( $schema['@context'] );

			if ( empty( $schema['@id'] ) && isset( $type_id_map[ $type ] ) ) {
				$schema['@id'] = $type_id_map[ $type ];
			}

			switch ( $type ) {
				case 'Article':
				case 'BlogPosting':
					$this->fill_article( $schema, $primary, $secondary, $permalink, $entity_id );
					break;

				case 'Product':
					// Pro fills product data (name, description, offers, reviews, etc.).
					$schema = apply_filters( 'rtrs_ai_fill_product_schema', $schema, $primary, $secondary, $permalink, $entity_id );
					$this->fix_product_offers( $schema, $entity_id );

					// Allow Pro to upgrade Product to ProductGroup when product has variations.
					// WooCommerce and SureCart support true product variants → ProductGroup.
					// EDD and FluentCart variations are price tiers (not product variants) — handled as offers.
					$variations = $secondary['wc_product']['variations']
						?? $secondary['surecart_product']['variations']
						?? null;
					if ( $variations ) {
						$schema = apply_filters( 'rtrs_ai_convert_product_group', $schema, $variations, $primary, $entity_id );
					}
					break;

				case 'SoftwareApplication':
				case 'WebApplication':
				case 'MobileApplication':
					$this->fill_software_application( $schema, $primary, $secondary, $permalink, $entity_id );
					$this->fix_product_offers( $schema, $entity_id );
					break;

				case 'Course':
					// Pro fills course data (provider, offers, instructor, etc.).
					$schema = apply_filters( 'rtrs_ai_fill_course_schema', $schema, $primary, $secondary, $permalink, $entity_id );
					break;

				case 'FAQPage':
					// Hard-cap questions to the configured FAQ count — the model
					// is asked for exactly N but may return more.
					$this->limit_faq_questions( $schema );
					break;
			}

			// Inject aggregateRating only for types that support it per schema.org.
			if ( empty( $schema['aggregateRating'] ) && in_array( $type, self::AGGREGATE_RATING_TYPES, true ) ) {
				$rating_source = $secondary['rtcl_rating'] ?? null;

				// Tutor LMS course rating.
				if ( ! $rating_source && ! empty( $secondary['tutor_course']['rating'] ) ) {
					$rating_source = $secondary['tutor_course']['rating'];
				}

				// Review-schema plugin's own ratings (any post type with review enabled).
				if ( ! $rating_source && ! empty( $secondary['review_rating'] ) ) {
					$rating_source = $secondary['review_rating'];
				}

				if ( $rating_source && ! empty( $rating_source['average_rating'] ) ) {
					$schema['aggregateRating'] = [
						'@type'       => 'AggregateRating',
						'ratingValue' => (float) $rating_source['average_rating'],
						'bestRating'  => 5,
						'worstRating' => 1,
						'ratingCount' => $rating_source['rating_count'] ?? 0,
						'reviewCount' => $rating_source['review_count'] ?? $rating_source['rating_count'] ?? 0,
					];
				}
			}

			// Remove aggregateRating when null, not a valid array, or ratingCount/ratingValue is 0/empty.
			if ( array_key_exists( 'aggregateRating', $schema ) ) {
				if ( empty( $schema['aggregateRating'] ) || ! is_array( $schema['aggregateRating'] ) ) {
					unset( $schema['aggregateRating'] );
				} else {
					$ar           = $schema['aggregateRating'];
					$rating_value = isset( $ar['ratingValue'] ) ? (float) $ar['ratingValue'] : 0;
					$rating_count = isset( $ar['ratingCount'] ) ? (int) $ar['ratingCount'] : ( isset( $ar['reviewCount'] ) ? (int) $ar['reviewCount'] : 0 );

					if ( $rating_value <= 0 || $rating_count <= 0 ) {
						unset( $schema['aggregateRating'] );
					}
				}
			}

			// Limit individual reviews to max 2 best-rated to keep schema lightweight.
			if ( ! empty( $schema['review'] ) && is_array( $schema['review'] ) ) {
				$reviews = isset( $schema['review']['@type'] ) ? [ $schema['review'] ] : $schema['review'];
				usort(
					$reviews,
					function ( $a, $b ) {
						$ra = (float) ( $a['reviewRating']['ratingValue'] ?? 0 );
						$rb = (float) ( $b['reviewRating']['ratingValue'] ?? 0 );

						return $rb <=> $ra;
					}
				);
				$schema['review'] = array_slice( $reviews, 0, 2 );
			}

			// Remove duplicate priceSpecification when ListPrice equals SalePrice.
			if ( ! empty( $schema['offers'] ) && is_array( $schema['offers'] ) ) {
				$this->deduplicate_price_specifications( $schema['offers'] );
			}

			// Strip subjectOf — link_faq_to_graph() handles this at render time.
			unset( $schema['subjectOf'] );

			// Strip properties not recognized by this schema type per schema.org.
			$this->strip_invalid_properties( $schema );
		}
		unset( $schema );

		return apply_filters( 'rtrs_ai_generated_schemas', $schemas, $full_payload );
	}

	private function normalize_graph_id( $id ) {
		return preg_replace( '/\/#/', '#', $id );
	}

	private function fill_article( &$schema, $primary, $secondary, $permalink, $entity_id ) {
		if ( empty( $schema['headline'] ) && ! empty( $primary['title'] ) ) {
			$schema['headline'] = mb_substr( $primary['title'], 0, 110 );
		}

		if ( empty( $schema['datePublished'] ) && ! empty( $primary['publish_date'] ) ) {
			$schema['datePublished'] = $primary['publish_date'];
		}

		if ( empty( $schema['dateModified'] ) && ! empty( $primary['modified_date'] ) ) {
			$schema['dateModified'] = $primary['modified_date'];
		}

		if ( empty( $schema['author'] ) && ! empty( $primary['author']['name'] ) ) {
			$author = [
				'@type' => 'Person',
				'name'  => $primary['author']['name'],
			];

			if ( ! empty( $primary['author']['url'] ) ) {
				$author['url'] = $primary['author']['url'];
			}

			$schema['author'] = $author;
		}

		if ( empty( $schema['image'] ) && ! empty( $primary['featured_image']['url'] ) ) {
			$img = $primary['featured_image'];

			$schema['image'] = array_filter(
				[
					'@type'  => 'ImageObject',
					'url'    => $img['url'],
					'width'  => $img['width'] ?? null,
					'height' => $img['height'] ?? null,
				]
			);
		}

		if ( empty( $schema['description'] ) && ! empty( $primary['excerpt'] ) ) {
			$schema['description'] = mb_substr( $primary['excerpt'], 0, 160 );
		}

		if ( empty( $schema['url'] ) && ! empty( $primary['permalink'] ) ) {
			$schema['url'] = $primary['permalink'];
		}

		if ( empty( $schema['articleBody'] ) && ! empty( $primary['body'] ) ) {
			$schema['articleBody'] = $primary['body'];
		}

		if ( empty( $schema['wordCount'] ) && ! empty( $secondary['word_count'] ) ) {
			$schema['wordCount'] = (int) $secondary['word_count'];
		}

		if ( empty( $schema['mainEntityOfPage'] ) ) {
			$schema['mainEntityOfPage'] = [ '@id' => $permalink . '#webpage' ];
		}

		if ( empty( $schema['publisher'] ) ) {
			$schema['publisher'] = [ '@id' => $entity_id ];
		}
	}


	/**
	 * Fill missing SoftwareApplication fields from extracted EDD/WC product data.
	 */
	private function fill_software_application( &$schema, $primary, $secondary, $permalink, $entity_id = '' ) {
		$edd = $secondary['edd_product'] ?? [];
		$wc  = $secondary['wc_product'] ?? [];

		if ( empty( $schema['name'] ) && ! empty( $primary['title'] ) ) {
			$schema['name'] = $primary['title'];
		}

		if ( empty( $schema['description'] ) && ! empty( $primary['excerpt'] ) ) {
			$schema['description'] = mb_substr( $primary['excerpt'], 0, 200 );
		}

		if ( empty( $schema['url'] ) && ! empty( $primary['permalink'] ) ) {
			$schema['url'] = $primary['permalink'];
		}

		if ( empty( $schema['image'] ) && ! empty( $primary['featured_image']['url'] ) ) {
			$fi              = $primary['featured_image'];
			$schema['image'] = array_filter( [
				'@type'  => 'ImageObject',
				'url'    => $fi['url'],
				'width'  => $fi['width'] ?? null,
				'height' => $fi['height'] ?? null,
			] );
		}

		if ( empty( $schema['applicationCategory'] ) ) {
			$cats = $edd['categories'] ?? $wc['categories'] ?? [];
			if ( ! empty( $cats ) ) {
				$schema['applicationCategory'] = implode( ', ', $cats );
			} else {
				$schema['applicationCategory'] = 'BusinessApplication';
			}
		}

		if ( empty( $schema['operatingSystem'] ) ) {
			$schema['operatingSystem'] = 'All';
		}

		// Build offers from EDD or WC product data if AI didn't generate them.
		if ( empty( $schema['offers'] ) ) {
			$seller = [ '@id' => $entity_id ];

			// EDD variable pricing → multiple offers (one per pricing tier).
			if ( ! empty( $edd['variations']['items'] ) ) {
				$offers = [];

				foreach ( $edd['variations']['items'] as $item ) {
					$item_price    = $item['price'] ?? '';
					$item_currency = $item['currency'] ?? $edd['pricing']['currency'] ?? 'USD';

					if ( '' !== $item_price && (float) $item_price > 0 ) {
						$offer = [
							'@type'         => 'Offer',
							'price'         => $item_price,
							'priceCurrency' => $item_currency,
							'availability'  => 'https://schema.org/InStock',
							'url'           => $permalink,
							'seller'        => $seller,
						];

						if ( ! empty( $item['name'] ) ) {
							$offer['name'] = $item['name'];
						}

						$offers[] = $offer;
					}
				}

				if ( ! empty( $offers ) ) {
					$schema['offers'] = $offers;
				}
			} else {
				$price    = $edd['pricing']['price'] ?? $wc['pricing']['price'] ?? '';
				$currency = $edd['pricing']['currency'] ?? $wc['pricing']['currency'] ?? 'USD';

				if ( '' !== $price && (float) $price > 0 ) {
					$schema['offers'] = [
						[
							'@type'         => 'Offer',
							'price'         => $price,
							'priceCurrency' => $currency,
							'availability'  => 'https://schema.org/InStock',
							'url'           => $permalink,
							'seller'        => $seller,
						],
					];
				}
			}
		}

		// Ensure publisher/author references.
		if ( empty( $schema['author'] ) && ! empty( $primary['author']['name'] ) ) {
			$author = [ '@type' => 'Person', 'name' => $primary['author']['name'] ];
			if ( ! empty( $primary['author']['url'] ) ) {
				$author['url'] = $primary['author']['url'];
			}
			$schema['author'] = $author;
		}
	}

	/**
	 * Post-process Product offers to fix common AI output issues:
	 * - Ensure seller uses @id reference (not inline Organization).
	 * - Ensure price & priceCurrency are direct Offer/AggregateOffer children.
	 * - Add hasMerchantReturnPolicy and shippingDetails @id references (Pro).
	 */
	private function fix_product_offers( &$schema, $entity_id ) {
		if ( empty( $schema['offers'] ) || ! is_array( $schema['offers'] ) ) {
			return;
		}

		// Build return policy and shipping @id references when Pro is active.
		$return_policy_ref = [];
		$shipping_refs     = [];

		if ( function_exists( 'rtrsp' ) && class_exists( 'Rtrsp\\Helpers\\FnsPro' ) ) {
			$return_policy = \Rtrsp\Helpers\FnsPro::get_merchant_return_policy();
			if ( ! empty( $return_policy['@id'] ) ) {
				$return_policy_ref = [ '@id' => $return_policy['@id'] ];
			}

			$currency = 'USD';
			if ( function_exists( 'get_woocommerce_currency' ) ) {
				$currency = get_woocommerce_currency();
			} elseif ( class_exists( 'FluentCart\Api\CurrencySettings' ) ) {
				$currency = \FluentCart\Api\CurrencySettings::get( 'currency' ) ?: 'USD';
			}
			$shipping_details = \Rtrsp\Helpers\FnsPro::get_offer_shipping_details( $currency );
			if ( ! empty( $shipping_details ) ) {
				foreach ( $shipping_details as $sd ) {
					if ( ! empty( $sd['@id'] ) ) {
						$shipping_refs[] = [ '@id' => $sd['@id'] ];
					}
				}
			}
		}

		// Default priceValidUntil: 1 year from now.
		$default_price_valid_until = gmdate( 'Y-m-d', strtotime( '+1 year' ) );

		foreach ( $schema['offers'] as &$offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}

			// Fix seller: replace inline Organization with @id reference.
			if ( ! empty( $offer['seller'] ) && is_array( $offer['seller'] ) && empty( $offer['seller']['@id'] ) ) {
				$offer['seller'] = [ '@id' => $entity_id ];
			}

			if ( empty( $offer['seller'] ) ) {
				$offer['seller'] = [ '@id' => $entity_id ];
			}

			// Add priceValidUntil if missing (default: 1 year from now).
			if ( empty( $offer['priceValidUntil'] ) ) {
				$offer['priceValidUntil'] = $default_price_valid_until;
			}

			// Add return policy @id reference.
			if ( ! empty( $return_policy_ref ) && empty( $offer['hasMerchantReturnPolicy'] ) ) {
				$offer['hasMerchantReturnPolicy'] = $return_policy_ref;
			}

			// Add shipping details @id references.
			if ( ! empty( $shipping_refs ) && empty( $offer['shippingDetails'] ) ) {
				$offer['shippingDetails'] = $shipping_refs;
			}

			$offer_type = $offer['@type'] ?? '';

			// Ensure price & priceCurrency are direct children of Offer.
			if ( 'Offer' === $offer_type ) {
				if ( empty( $offer['price'] ) && ! empty( $offer['priceSpecification'] ) ) {
					foreach ( $offer['priceSpecification'] as $spec ) {
						if ( ! empty( $spec['price'] ) ) {
							$offer['price'] = $spec['price'];
							if ( ! empty( $spec['priceCurrency'] ) ) {
								$offer['priceCurrency'] = $spec['priceCurrency'];
							}
							break;
						}
					}
				}
			}

			// Ensure price & priceCurrency are direct children of AggregateOffer.
			if ( 'AggregateOffer' === $offer_type ) {
				if ( empty( $offer['price'] ) && ! empty( $offer['lowPrice'] ) ) {
					$offer['price'] = $offer['lowPrice'];
				}
				if ( empty( $offer['priceCurrency'] ) && ! empty( $offer['priceSpecification'] ) ) {
					foreach ( $offer['priceSpecification'] as $spec ) {
						if ( ! empty( $spec['priceCurrency'] ) ) {
							$offer['priceCurrency'] = $spec['priceCurrency'];
							break;
						}
					}
				}
			}
		}
		unset( $offer );
	}

	/**
	 * Remove properties not recognized by the given schema type per schema.org.
	 *
	 * Prevents Google Rich Results validation warnings caused by the AI
	 * adding properties that don't belong to a particular type.
	 *
	 * @param array $schema Schema node (passed by reference).
	 */
	/**
	 * Remove duplicate priceSpecification entries from offers.
	 *
	 * When ListPrice and SalePrice have the same price value (product not on sale),
	 * keep only one entry without priceType to avoid redundant data.
	 *
	 * @param array $offers Offers array (by reference).
	 */
	private function deduplicate_price_specifications( &$offers ) {
		// Single offer object.
		if ( isset( $offers['@type'] ) ) {
			$this->deduplicate_single_offer_specs( $offers );

			// AggregateOffer with nested offers.
			if ( ! empty( $offers['offers'] ) && is_array( $offers['offers'] ) ) {
				foreach ( $offers['offers'] as &$nested ) {
					if ( is_array( $nested ) ) {
						$this->deduplicate_single_offer_specs( $nested );
					}
				}
				unset( $nested );
			}

			return;
		}

		// Array of offers.
		if ( isset( $offers[0] ) ) {
			foreach ( $offers as &$offer ) {
				if ( is_array( $offer ) ) {
					$this->deduplicate_price_specifications( $offer );
				}
			}
			unset( $offer );
		}
	}

	/**
	 * Deduplicate priceSpecification on a single offer.
	 *
	 * @param array $offer Single offer (by reference).
	 */
	private function deduplicate_single_offer_specs( &$offer ) {
		if ( empty( $offer['priceSpecification'] ) || ! is_array( $offer['priceSpecification'] ) ) {
			return;
		}

		$specs = $offer['priceSpecification'];
		if ( count( $specs ) < 2 ) {
			return;
		}

		// Collect prices by priceType.
		$list_price = null;
		$sale_price = null;

		foreach ( $specs as $spec ) {
			$type = $spec['priceType'] ?? '';
			if ( 'https://schema.org/ListPrice' === $type ) {
				$list_price = (string) ( $spec['price'] ?? '' );
			} elseif ( 'https://schema.org/SalePrice' === $type ) {
				$sale_price = (string) ( $spec['price'] ?? '' );
			}
		}

		// If both exist with same price, keep only one entry (the current price).
		if ( null !== $list_price && null !== $sale_price && $list_price === $sale_price ) {
			$offer['priceSpecification'] = [
				array_filter(
					[
						'@type'                 => 'UnitPriceSpecification',
						'price'                 => $list_price,
						'priceCurrency'         => $specs[0]['priceCurrency'] ?? null,
						'valueAddedTaxIncluded' => $specs[0]['valueAddedTaxIncluded'] ?? null,
					]
				),
			];
		}
	}

	private function strip_invalid_properties( &$schema ) {
		$type = $schema['@type'] ?? '';

		if ( empty( $type ) ) {
			return;
		}

		$invalid = $this->get_invalid_properties( $type );

		foreach ( $invalid as $prop ) {
			unset( $schema[ $prop ] );
		}
	}

	/**
	 * Get properties that are NOT valid for the given schema type.
	 *
	 * @param string $type Schema type name.
	 *
	 * @return array List of invalid property names.
	 */
	private function get_invalid_properties( $type ) {
		// Properties only valid on CreativeWork subtypes.
		$creative_work_only = [ 'publisher', 'mainEntityOfPage', 'headline', 'wordCount' ];

		// Types that are NOT CreativeWork subtypes — these should not have publisher, headline, etc.
		$non_creative_work_types = [
			'Product',
			'ProductGroup',
			'Service',
			'TaxiService',
			'Event',
			'Person',
			'LocalBusiness',
			'Restaurant',
			'JobPosting',
			'VacationRental',
			'Vehicle',
			'Mosque',
			'Church',
			'HinduTemple',
			'BuddhistTemple',
		];

		$invalid = [];

		if ( in_array( $type, $non_creative_work_types, true ) ) {
			$invalid = array_merge( $invalid, $creative_work_only );
		}

		// aggregateRating is only valid on specific types.
		if ( ! in_array( $type, self::AGGREGATE_RATING_TYPES, true ) ) {
			$invalid[] = 'aggregateRating';
		}

		return $invalid;
	}

}
