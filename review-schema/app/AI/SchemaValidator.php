<?php

namespace Rtrs\AI;

/**
 * Schema Validator - Validates generated JSON-LD @graph schemas.
 *
 * Performs two validation passes:
 *  1. Graph-level: collects all @id values, checks required types, and verifies
 *     cross-reference integrity (every @id reference must resolve to an existing node).
 *  2. Item-level: validates @id presence/format and all required/recommended fields
 *     per Google Rich Results requirements for each @type.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class SchemaValidator {

	/**
	 * Validation errors (blocking issues).
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * Validation warnings (non-blocking recommendations).
	 *
	 * @var array
	 */
	private $warnings = [];

	/**
	 * All @id values found in the current @graph, used for cross-reference checks.
	 *
	 * @var array
	 */
	private $graph_ids = [];

	/**
	 * Validate a @graph items array or a full @graph wrapper object.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schemas @graph items array or {"@context":..., "@graph":[...]} wrapper.
	 *
	 * @return array Validation result: valid, errors, warnings, score.
	 */
	public function validate( $schemas ) {
		$this->errors    = [];
		$this->warnings  = [];
		$this->graph_ids = [];

		/** Unwrap @graph wrapper if present. */
		if ( is_array( $schemas ) && isset( $schemas['@graph'] ) ) {
			$schemas = $schemas['@graph'];
		}

		if ( ! is_array( $schemas ) || empty( $schemas ) ) {
			$this->errors[] = __( 'No schemas provided for validation.', 'review-schema' );

			return $this->build_result();
		}

		/**
		 * Pass 1 — collect all @id values present in the graph so cross-reference
		 * checks in Pass 2 can verify that every reference resolves to a real node.
		 */
		foreach ( $schemas as $schema ) {
			if ( ! empty( $schema['@id'] ) ) {
				$this->graph_ids[] = $schema['@id'];
			}
		}

		/** Pass 2 — validate each individual schema item. */
		foreach ( $schemas as $index => $schema ) {
			$this->validate_single( $schema, $index );
		}

		/** Pass 3 — graph-level structural checks. */
		$this->validate_graph_structure( $schemas );

		return $this->build_result();
	}

	// -------------------------------------------------------------------------
	// Graph-level checks
	// -------------------------------------------------------------------------

	/**
	 * Validate the overall @graph structure.
	 *
	 * Checks for recommended types and verifies that every cross-reference
	 * @id points to a node that actually exists in the graph.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schemas @graph items array.
	 *
	 * @return void
	 */
	private function validate_graph_structure( $schemas ) {
		$types = array_column( $schemas, '@type' );

		/** Recommended top-level types (WebPage or any subtype like ItemPage, CollectionPage, etc.). */
		$webpage_types = [ 'WebPage', 'ItemPage', 'CollectionPage', 'AboutPage', 'CheckoutPage', 'ContactPage', 'FAQPage', 'MedicalWebPage', 'ProfilePage', 'QAPage', 'RealEstateListing', 'SearchResultsPage' ];
		$has_webpage   = false;
		foreach ( $types as $type ) {
			if ( in_array( $type, $webpage_types, true ) ) {
				$has_webpage = true;
				break;
			}
		}
		if ( ! $has_webpage ) {
			$this->warnings[] = __( 'Graph: WebPage (or a subtype like ItemPage) is recommended in every @graph output.', 'review-schema' );
		}

		if ( ! in_array( 'BreadcrumbList', $types, true ) ) {
			$this->warnings[] = __( 'Graph: BreadcrumbList is recommended in every @graph output.', 'review-schema' );
		}

		/** Require at least one entity publisher node (Organization/Person or any subtype). */
		$has_entity = false;

		foreach ( $schemas as $schema ) {
			if ( $this->is_entity_node( $schema ) ) {
				$has_entity = true;
				break;
			}
		}

		if ( ! $has_entity ) {
			$this->warnings[] = __( 'Graph: An Organization or Person node is recommended as the publisher entity.', 'review-schema' );
		}

		/** Cross-reference integrity checks per type. */
		foreach ( $schemas as $schema ) {
			$type        = $schema['@type'] ?? '';
			$type_string = is_array( $type ) ? $type[0] : $type;
			$prefix      = "[{$type_string}]";

			if ( 'WebPage' === $type_string ) {
				$this->check_id_ref( $schema, 'breadcrumb', $prefix );
				$this->check_id_ref( $schema, 'isPartOf', $prefix );
			}

			if ( in_array( $type_string, [ 'Article', 'BlogPosting' ], true ) ) {
				$this->check_id_ref( $schema, 'mainEntityOfPage', $prefix );
				$this->check_id_ref( $schema, 'publisher', $prefix );
			}

			if ( 'WebSite' === $type_string ) {
				$this->check_id_ref( $schema, 'publisher', $prefix );
			}
		}
	}

	/**
	 * Check that a field's @id reference resolves to an existing @graph node.
	 *
	 * Only fires when the field value is a reference object `{"@id": "..."}`.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $schema Parent schema object.
	 * @param string $field  Field name to inspect.
	 * @param string $prefix Error prefix string.
	 *
	 * @return void
	 */
	private function check_id_ref( $schema, $field, $prefix ) {
		if ( empty( $schema[ $field ] ) ) {
			return;
		}

		$value = $schema[ $field ];

		/** Only check objects that are @id-only references (not inline schemas). */
		if ( ! is_array( $value ) || empty( $value['@id'] ) || count( $value ) > 2 ) {
			return;
		}

		if ( ! in_array( $value['@id'], $this->graph_ids, true ) ) {
			$this->warnings[] = sprintf(
				/* translators: 1: schema type prefix, 2: field name, 3: unresolved @id */
				__( '%1$s: \'%2$s\' references @id "%3$s" which does not exist in the @graph.', 'review-schema' ),
				$prefix,
				$field,
				$value['@id']
			);
		}
	}

	// -------------------------------------------------------------------------
	// Item-level checks
	// -------------------------------------------------------------------------

	/**
	 * Validate a single @graph item.
	 *
	 * Checks @id and @type presence, then dispatches to the type-specific method.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema Schema item.
	 * @param int   $index  Item index within the @graph array.
	 *
	 * @return void
	 */
	private function validate_single( $schema, $index ) {
		$type        = $schema['@type'] ?? '';
		$type_string = is_array( $type ) ? $type[0] : $type;
		$prefix      = sprintf( 'Item #%d [%s]', $index + 1, $type_string ?: 'unknown' );

		/** @id is required for every @graph node. */
		if ( empty( $schema['@id'] ) ) {
			$this->errors[] = "{$prefix}: Missing required '@id'";
		} elseif ( ! $this->is_url_with_fragment( $schema['@id'] ) ) {
			$this->warnings[] = "{$prefix}: '@id' should be an absolute URL with a fragment (e.g. https://example.com/page/#type)";
		}

		/** @context must NOT appear on individual @graph items. */
		if ( ! empty( $schema['@context'] ) ) {
			$this->warnings[] = "{$prefix}: '@context' should only appear on the outer @graph wrapper, not on individual items";
		}

		if ( empty( $type ) ) {
			$this->errors[] = "{$prefix}: Missing required '@type'";
			return;
		}

		/** Extract primary type if @type is an array (e.g. ['RVPark', 'LocalBusiness']). */
		$primary_type = is_array( $type ) ? $type[0] : $type;

		/** Dispatch to type-specific validator. */
		$method = 'validate_' . strtolower( str_replace( [ '/', ' ' ], '_', $primary_type ) );

		if ( method_exists( $this, $method ) ) {
			$this->$method( $schema, $prefix );
		} elseif ( $this->is_entity_node( $schema ) ) {
			$this->validate_organization( $schema, $prefix );
		}

		// Allow Pro to validate additional schema types (Product, Course, Recipe, etc.).
		do_action( 'rtrs_ai_validate_schema_type', $type, $schema, $prefix, $this );
	}

	// -------------------------------------------------------------------------
	// Type-specific validators
	// -------------------------------------------------------------------------

	/**
	 * Validate Product offers (single Offer, AggregateOffer, or array of either).
	 *
	 * @since 1.0.0
	 * @param array  $offers Offers data (single Offer, AggregateOffer, or array of Offers).
	 * @param string $prefix Error prefix.
	 * @return void
	 */
	public function validate_product_offers( $offers, $prefix ) {
		$offer_type = $offers['@type'] ?? '';

		if ( 'AggregateOffer' === $offer_type ) {
			$this->validate_aggregate_offer( $offers, $prefix );
			return;
		}

		/** Single Offer object. */
		if ( 'Offer' === $offer_type ) {
			$this->validate_single_offer( $offers, $prefix, 0 );
			return;
		}

		/** Array of Offer/AggregateOffer objects. */
		if ( isset( $offers[0] ) ) {
			foreach ( $offers as $i => $offer ) {
				$item_type = $offer['@type'] ?? '';

				if ( 'AggregateOffer' === $item_type ) {
					$this->validate_aggregate_offer( $offer, $prefix );
				} else {
					$this->validate_single_offer( $offer, $prefix, $i );
				}
			}
		}
	}

	/**
	 * Validate a single Offer object.
	 *
	 * Accepts price via top-level 'price' + 'priceCurrency' fields,
	 * or via 'priceSpecification' array of UnitPriceSpecification.
	 *
	 * @since 1.0.0
	 * @param array  $offer  Offer data.
	 * @param string $prefix Error prefix.
	 * @param int    $index  Offer index for messaging.
	 * @return void
	 */
	public function validate_single_offer( $offer, $prefix, $index ) {
		$label              = $index > 0 ? "Offer #{$index}" : 'Offer';
		$has_price          = isset( $offer['price'] ) && '' !== $offer['price'];
		$has_price_spec     = ! empty( $offer['priceSpecification'] ) && is_array( $offer['priceSpecification'] );

		/** Price is required via top-level price or priceSpecification. */
		if ( ! $has_price && ! $has_price_spec ) {
			$this->errors[] = "{$prefix}: {$label} missing required 'price' or 'priceSpecification'";
		}

		/** priceCurrency is required when using top-level price (not needed if only in priceSpecification). */
		if ( $has_price && ! $has_price_spec && empty( $offer['priceCurrency'] ) ) {
			$this->errors[] = "{$prefix}: {$label} missing required 'priceCurrency'";
		}

		/** Validate each priceSpecification entry. */
		if ( $has_price_spec ) {
			foreach ( $offer['priceSpecification'] as $si => $spec ) {
				$sn = (int) $si + 1;

				if ( ! isset( $spec['price'] ) || '' === $spec['price'] ) {
					$this->errors[] = "{$prefix}: {$label} priceSpecification #{$sn} missing 'price'";
				}

				if ( empty( $spec['priceCurrency'] ) ) {
					$this->errors[] = "{$prefix}: {$label} priceSpecification #{$sn} missing 'priceCurrency'";
				}
			}
		}

		if ( empty( $offer['availability'] ) ) {
			$this->warnings[] = "{$prefix}: {$label} missing recommended 'availability'";
		} elseif ( ! $this->is_schema_org_url( $offer['availability'] ) ) {
			$this->warnings[] = "{$prefix}: {$label} 'availability' should be a Schema.org URL (e.g. https://schema.org/InStock)";
		}

		if ( empty( $offer['url'] ) ) {
			$this->warnings[] = "{$prefix}: {$label} missing recommended 'url'";
		}

		if ( ! empty( $offer['priceValidUntil'] ) && ! $this->is_iso8601( $offer['priceValidUntil'] ) ) {
			$this->warnings[] = "{$prefix}: {$label} 'priceValidUntil' should be in ISO 8601 date format";
		}

		if ( empty( $offer['hasMerchantReturnPolicy'] ) ) {
			$this->warnings[] = "{$prefix}: {$label} missing recommended 'hasMerchantReturnPolicy'";
		}

		if ( empty( $offer['shippingDetails'] ) ) {
			$this->warnings[] = "{$prefix}: {$label} missing recommended 'shippingDetails'";
		}
	}

	/**
	 * Validate an AggregateOffer object.
	 *
	 * @since 1.0.0
	 * @param array  $offer  AggregateOffer data.
	 * @param string $prefix Error prefix.
	 * @return void
	 */
	public function validate_aggregate_offer( $offer, $prefix ) {
		if ( empty( $offer['lowPrice'] ) ) {
			$this->errors[] = "{$prefix}: AggregateOffer missing required 'lowPrice'";
		}

		if ( empty( $offer['priceCurrency'] ) ) {
			$this->errors[] = "{$prefix}: AggregateOffer missing required 'priceCurrency'";
		}

		if ( empty( $offer['highPrice'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer missing recommended 'highPrice'";
		}

		if ( empty( $offer['offerCount'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer missing recommended 'offerCount'";
		}

		if ( empty( $offer['availability'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer missing recommended 'availability'";
		}

		if ( empty( $offer['url'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer missing recommended 'url'";
		}

		if ( ! empty( $offer['priceValidUntil'] ) && ! $this->is_iso8601( $offer['priceValidUntil'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer 'priceValidUntil' should be in ISO 8601 date format";
		}

		if ( empty( $offer['hasMerchantReturnPolicy'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer missing recommended 'hasMerchantReturnPolicy'";
		}

		if ( empty( $offer['shippingDetails'] ) ) {
			$this->warnings[] = "{$prefix}: AggregateOffer missing recommended 'shippingDetails'";
		}

		/** Validate priceSpecification entries if present. */
		if ( ! empty( $offer['priceSpecification'] ) && is_array( $offer['priceSpecification'] ) ) {
			foreach ( $offer['priceSpecification'] as $si => $spec ) {
				$sn = (int) $si + 1;

				if ( ! isset( $spec['price'] ) || '' === $spec['price'] ) {
					$this->errors[] = "{$prefix}: AggregateOffer priceSpecification #{$sn} missing 'price'";
				}

				if ( empty( $spec['priceCurrency'] ) ) {
					$this->errors[] = "{$prefix}: AggregateOffer priceSpecification #{$sn} missing 'priceCurrency'";
				}
			}
		}
	}

	/**
	 * Validate an AggregateRating object.
	 *
	 * @since 1.0.0
	 * @param array  $rating AggregateRating data.
	 * @param string $prefix Error prefix.
	 * @return void
	 */
	public function validate_aggregate_rating( $rating, $prefix ) {
		if ( empty( $rating['ratingValue'] ) ) {
			$this->errors[] = "{$prefix}: 'aggregateRating.ratingValue' is required";
		}

		if ( empty( $rating['reviewCount'] ) && empty( $rating['ratingCount'] ) ) {
			$this->errors[] = "{$prefix}: 'aggregateRating' must include 'reviewCount' or 'ratingCount'";
		}

		if ( ! empty( $rating['bestRating'] ) && ! empty( $rating['ratingValue'] ) ) {
			if ( (float) $rating['ratingValue'] > (float) $rating['bestRating'] ) {
				$this->errors[] = "{$prefix}: 'aggregateRating.ratingValue' cannot exceed 'bestRating'";
			}
		}
	}

	/**
	 * Validate a single Review item within a Product.
	 *
	 * @since 1.0.0
	 * @param array  $review Review data.
	 * @param string $prefix Error prefix.
	 * @param int    $n      Review number (1-based).
	 * @return void
	 */
	public function validate_review_item( $review, $prefix, $n ) {
		if ( empty( $review['author'] ) ) {
			$this->errors[] = "{$prefix}: Review #{$n} missing required 'author'";
		} elseif ( is_array( $review['author'] ) && empty( $review['author']['name'] ) && empty( $review['author']['@id'] ) ) {
			$this->errors[] = "{$prefix}: Review #{$n} 'author' must have a 'name' or '@id'";
		}

		if ( empty( $review['reviewRating'] ) ) {
			$this->warnings[] = "{$prefix}: Review #{$n} missing recommended 'reviewRating'";
		} elseif ( is_array( $review['reviewRating'] ) && empty( $review['reviewRating']['ratingValue'] ) ) {
			$this->errors[] = "{$prefix}: Review #{$n} 'reviewRating' must have a 'ratingValue'";
		}

		if ( ! empty( $review['datePublished'] ) && ! $this->is_iso8601( $review['datePublished'] ) ) {
			$this->warnings[] = "{$prefix}: Review #{$n} 'datePublished' should be in ISO 8601 format";
		}
	}


	/**
	 * Validate Person schema.
	 *
	 * @since 1.0.0
	 * @param array  $schema Schema data.
	 * @param string $prefix Error prefix.
	 * @return void
	 */
	private function validate_person( $schema, $prefix ) {
		if ( empty( $schema['name'] ) ) {
			$this->errors[] = "{$prefix}: Person missing required 'name'";
		}

		foreach ( [ 'url', 'image' ] as $field ) {
			if ( empty( $schema[ $field ] ) ) {
				$this->warnings[] = "{$prefix}: Person missing recommended field '{$field}'";
			}
		}
	}


	/**
	 * Validate Organization — delegates to LocalBusiness validator.
	 *
	 * @since 1.0.0
	 * @param array  $schema Schema data.
	 * @param string $prefix Error prefix.
	 * @return void
	 */
	private function validate_organization( $schema, $prefix ) {
		if ( empty( $schema['name'] ) ) {
			$this->errors[] = "{$prefix}: Organization missing required 'name'";
		}

		foreach ( [ 'url', 'logo' ] as $field ) {
			if ( empty( $schema[ $field ] ) ) {
				$this->warnings[] = "{$prefix}: Organization missing recommended field '{$field}'";
			}
		}
	}


	// -------------------------------------------------------------------------
	// Public API for Pro validators
	// -------------------------------------------------------------------------

	/**
	 * Add a validation error (for use by Pro validators via do_action).
	 *
	 * @param string $message Error message.
	 */
	public function add_error( $message ) {
		$this->errors[] = $message;
	}

	/**
	 * Add a validation warning (for use by Pro validators via do_action).
	 *
	 * @param string $message Warning message.
	 */
	public function add_warning( $message ) {
		$this->warnings[] = $message;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Check whether a schema node is an entity/publisher node (Organization, Person, or any subtype).
	 *
	 * Uses the @id pattern (#organization / #person) rather than @type, because the @type
	 * can be any Organization subtype (Restaurant, Dentist, Corporation, etc.) set via settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema Schema item.
	 *
	 * @return bool
	 */
	private function is_entity_node( $schema ) {
		$id = $schema['@id'] ?? '';

		return (bool) preg_match( '/#(organization|person)$/i', $id );
	}

	/**
	 * Check whether a string is an absolute URL with a fragment identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The @id value to test.
	 *
	 * @return bool
	 */
	private function is_url_with_fragment( $id ) {
		return (bool) preg_match( '/^https?:\/\/.+#.+$/', $id );
	}

	/**
	 * Check whether a string is a valid ISO 8601 date/datetime.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Date string to test.
	 *
	 * @return bool
	 */
	private function is_iso8601( $value ) {
		return (bool) preg_match(
			'/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/',
			$value
		);
	}

	/**
	 * Check whether a string is a valid ISO 8601 duration (e.g. PT30M, P1DT2H).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Duration string to test.
	 *
	 * @return bool
	 */
	private function is_iso8601_duration( $value ) {
		return (bool) preg_match( '/^P(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/', $value );
	}

	/**
	 * Check whether a string is a valid Schema.org enumeration URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to test.
	 *
	 * @return bool
	 */
	private function is_schema_org_url( $url ) {
		return (bool) preg_match( '/^https?:\/\/schema\.org\//', $url );
	}

	// -------------------------------------------------------------------------
	// Result
	// -------------------------------------------------------------------------

	/**
	 * Build the final validation result array.
	 *
	 * @since 1.0.0
	 *
	 * @return array {valid, errors, warnings, score}
	 */
	private function build_result() {
		return [
			'valid'    => empty( $this->errors ),
			'errors'   => array_values( $this->errors ),
			'warnings' => array_values( $this->warnings ),
			'score'    => $this->calculate_score(),
		];
	}

	/**
	 * Calculate a quality score from 0–100.
	 *
	 * Deducts 15 points per error and 5 points per warning.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private function calculate_score() {
		$score  = 100;
		$score -= count( $this->errors )   * 15;
		$score -= count( $this->warnings ) * 5;

		return max( 0, min( 100, $score ) );
	}
}
