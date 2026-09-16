<?php
/**
 * Duplicate Entity Filter.
 *
 * The global site entity (Organization / LocalBusiness) is emitted on every
 * page by Schema::site_schema(). A post can additionally carry its own
 * business node, because the per-page "Local business schema" metabox offers
 * the same type vocabulary as the Site Info setting — so an editor can pick
 * "Organization" on the About page and end up with the same company twice in
 * the graph.
 *
 * This filter keeps the global node (it is the canonical publisher target)
 * and removes a page-level business node only when identity signals — url,
 * sameAs, name, telephone, address — prove it is the same entity. The @type
 * is a gate, never the decision: two Organization nodes may be a parent
 * company and its subsidiary, and a Restaurant branch page is a legitimately
 * separate entity from the site's Organization.
 *
 * @package Rtrs\Modules\Schema\Hooks
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DuplicateEntityFilter
 *
 * Removes page-level business nodes that duplicate the global site entity
 * from both the traditional and the AI schema graph.
 */
class DuplicateEntityFilter {

	use SingletonTrait;

	/**
	 * Number of weak identity signals that together prove a duplicate.
	 */
	const WEAK_MATCH_THRESHOLD = 2;

	/**
	 * Legal-form suffixes stripped before comparing entity names.
	 *
	 * @var array
	 */
	const NAME_SUFFIXES = [
		'ltd',
		'limited',
		'llc',
		'inc',
		'incorporated',
		'co',
		'corp',
		'corporation',
		'gmbh',
		'bv',
		'plc',
		'pty',
		'pvt',
		'sa',
		'ag',
	];

	/**
	 * Flat, lowercased list of Organization/LocalBusiness family types.
	 *
	 * @var array|null
	 */
	private static $business_types = null;

	/**
	 * Initialize hooks.
	 *
	 * Priority 101 puts this after the Pro graph normalizers (99 and 100) so
	 * the graph is final before entities are compared.
	 *
	 * @return void
	 */
	private function __instance() {
		add_filter( 'rtrs_schema_graph_data', [ $this, 'filter_graph' ], 101 );
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'filter_graph' ], 101 );
	}

	/**
	 * Filter callback — drop duplicate page-level business nodes.
	 *
	 * @param array $schema_graph_list The schema graph list.
	 *
	 * @return array Modified schema graph list.
	 */
	public function filter_graph( $schema_graph_list ) {
		return self::deduplicate( $schema_graph_list );
	}

	/**
	 * Remove business nodes that duplicate the canonical site entity.
	 *
	 * @param array $graph Schema graph list.
	 *
	 * @return array Graph with duplicates removed.
	 */
	public static function deduplicate( $graph ) {
		if ( ! is_array( $graph ) || count( $graph ) < 2 ) {
			return $graph;
		}

		$canonical_id = self::get_canonical_id();
		if ( '' === $canonical_id ) {
			return $graph;
		}

		$canonical = null;
		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || empty( $node['@id'] ) || ! self::is_business_node( $node ) ) {
				continue;
			}
			if ( self::normalize_id( $node['@id'] ) === $canonical_id ) {
				$canonical = $node;
				break;
			}
		}

		// No global entity in this graph — nothing to deduplicate against.
		if ( null === $canonical ) {
			return $graph;
		}

		$duplicate_ids = [];
		$remove_ids    = [];

		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || ! self::is_business_node( $node ) ) {
				continue;
			}
			$node_id = ! empty( $node['@id'] ) ? self::normalize_id( $node['@id'] ) : '';

			// Nodes are removed by @id, so an @id-less node cannot be removed —
			// and dropping its paired Review alone would orphan the reference
			// held by the surviving node. Leave such a pair untouched.
			if ( '' === $node_id || $node_id === $canonical_id ) {
				continue;
			}
			if ( ! self::is_same_entity( $canonical, $node ) ) {
				continue;
			}

			$duplicate_ids[] = $node_id;
			$remove_ids[]    = $node_id;

			// A business node in review mode owns a sibling Review node.
			foreach ( self::get_review_ids( $node ) as $review_id ) {
				$remove_ids[] = $review_id;
			}
		}

		if ( empty( $remove_ids ) ) {
			return $graph;
		}

		$kept = [];
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && ! empty( $node['@id'] )
				&& in_array( self::normalize_id( $node['@id'] ), $remove_ids, true )
			) {
				continue;
			}
			$kept[] = $node;
		}

		// Keep the graph self-consistent: anything that pointed at a removed
		// business node now points at the surviving canonical entity.
		foreach ( $duplicate_ids as $duplicate_id ) {
			$kept = self::repoint_references( $kept, $duplicate_id, $canonical['@id'] );
		}

		return array_values( $kept );
	}

	/**
	 * Check whether a node belongs to the Organization/LocalBusiness family.
	 *
	 * @param array $node Schema node.
	 *
	 * @return bool
	 */
	public static function is_business_node( $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			return false;
		}

		$family = self::get_business_types();
		foreach ( self::get_type_list( $node ) as $type ) {
			if ( in_array( strtolower( $type ), $family, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decide whether two business nodes describe the same entity.
	 *
	 * One strong signal (shared @id, identical url, overlapping sameAs) or two
	 * weak signals (name, telephone, address) confirm a duplicate. The @type
	 * is deliberately not part of the comparison.
	 *
	 * @param array $canonical Canonical (global) node.
	 * @param array $candidate Page-level node.
	 *
	 * @return bool
	 */
	public static function is_same_entity( $canonical, $candidate ) {
		if ( ! is_array( $canonical ) || ! is_array( $candidate ) ) {
			return false;
		}

		// Strong: same @id. Two nodes sharing an @id are one node by definition,
		// so this is settled before any inferred signal is weighed.
		if ( ! empty( $canonical['@id'] ) && ! empty( $candidate['@id'] )
			&& self::normalize_id( $canonical['@id'] ) === self::normalize_id( $candidate['@id'] )
		) {
			return true;
		}

		// Veto: a contradicting phone or address proves two distinct entities,
		// whatever else matches. A chain shares its brand name, its central
		// phone number and its social profiles across every branch page — only
		// the location tells them apart.
		if ( self::has_conflicting_identity( $canonical, $candidate ) ) {
			return false;
		}

		// Strong: same url — unless the two entities name themselves
		// differently. The per-page "Web URL" field carries no guidance about
		// whose URL belongs in it, so an editor describing a partner or
		// subsidiary may well enter the site's own homepage. A shared url
		// then proves nothing on its own and must be corroborated below.
		$canonical_url = self::normalize_url( $canonical['url'] ?? '' );
		$candidate_url = self::normalize_url( $candidate['url'] ?? '' );
		if ( '' !== $canonical_url && $canonical_url === $candidate_url
			&& ! self::has_conflicting_name( $canonical, $candidate )
		) {
			return true;
		}

		// Strong: overlapping sameAs profiles.
		$shared = array_intersect(
			self::normalize_same_as( $canonical['sameAs'] ?? [] ),
			self::normalize_same_as( $candidate['sameAs'] ?? [] )
		);
		if ( ! empty( $shared ) ) {
			return true;
		}

		$weak = 0;

		// Weak: name, compared against every name the canonical entity uses.
		$candidate_name = self::normalize_name( $candidate['name'] ?? '' );
		if ( '' !== $candidate_name ) {
			foreach ( [ 'name', 'legalName', 'alternateName' ] as $key ) {
				if ( self::normalize_name( $canonical[ $key ] ?? '' ) === $candidate_name ) {
					++$weak;
					break;
				}
			}
		}

		// Weak: telephone.
		$candidate_phone = self::normalize_phone( $candidate['telephone'] ?? '' );
		if ( '' !== $candidate_phone && self::normalize_phone( $canonical['telephone'] ?? '' ) === $candidate_phone ) {
			++$weak;
		}

		// Weak: postal address.
		$candidate_address = self::normalize_address( $candidate['address'] ?? [] );
		if ( ! empty( $candidate_address )
			&& ! empty( array_intersect( self::normalize_address( $canonical['address'] ?? [] ), $candidate_address ) )
		) {
			++$weak;
		}

		return $weak >= self::WEAK_MATCH_THRESHOLD;
	}

	/**
	 * Check whether two business nodes name themselves differently.
	 *
	 * A conflict needs a name on both sides: an unnamed node is unknown, not
	 * different. The candidate is compared against every name the canonical
	 * entity uses, so a page node carrying the legal name rather than the
	 * trading name still agrees. Legal-form suffixes are already normalized
	 * away, so "Acme Ltd" and "Acme Limited" do not conflict.
	 *
	 * @param array $canonical Canonical (global) node.
	 * @param array $candidate Page-level node.
	 *
	 * @return bool True when both name themselves and none of the names match.
	 */
	private static function has_conflicting_name( $canonical, $candidate ) {
		$candidate_name = self::normalize_name( $candidate['name'] ?? '' );
		if ( '' === $candidate_name ) {
			return false;
		}

		$canonical_named = false;
		foreach ( [ 'name', 'legalName', 'alternateName' ] as $key ) {
			$canonical_name = self::normalize_name( $canonical[ $key ] ?? '' );
			if ( '' === $canonical_name ) {
				continue;
			}
			if ( $canonical_name === $candidate_name ) {
				return false;
			}
			$canonical_named = true;
		}

		return $canonical_named;
	}

	/**
	 * Check whether two business nodes contradict each other on location.
	 *
	 * Only a signal present on BOTH sides can contradict: a missing phone or
	 * address is unknown, not different. Addresses are compared as sets, so a
	 * branch that matches any one of several head-office addresses agrees.
	 *
	 * @param array $canonical Canonical (global) node.
	 * @param array $candidate Page-level node.
	 *
	 * @return bool True when the two cannot be the same entity.
	 */
	private static function has_conflicting_identity( $canonical, $candidate ) {
		$canonical_phone = self::normalize_phone( $canonical['telephone'] ?? '' );
		$candidate_phone = self::normalize_phone( $candidate['telephone'] ?? '' );
		if ( '' !== $canonical_phone && '' !== $candidate_phone && $canonical_phone !== $candidate_phone ) {
			return true;
		}

		$canonical_address = self::normalize_address( $canonical['address'] ?? [] );
		$candidate_address = self::normalize_address( $candidate['address'] ?? [] );
		if ( ! empty( $canonical_address ) && ! empty( $candidate_address )
			&& empty( array_intersect( $canonical_address, $candidate_address ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Normalize a node @type into a flat list of type strings.
	 *
	 * @param array $node Schema node.
	 *
	 * @return array
	 */
	public static function get_type_list( $node ) {
		$type = $node['@type'] ?? '';
		if ( is_string( $type ) ) {
			return '' === $type ? [] : [ $type ];
		}
		if ( ! is_array( $type ) ) {
			return [];
		}

		return array_values( array_filter( $type, 'is_string' ) );
	}

	/**
	 * Normalize a graph @id for comparison.
	 *
	 * @param string $id Schema @id.
	 *
	 * @return string
	 */
	public static function normalize_id( $id ) {
		if ( ! is_string( $id ) || '' === $id ) {
			return '';
		}

		$parts    = explode( '#', $id, 2 );
		$base     = self::normalize_url( $parts[0] );
		$fragment = isset( $parts[1] ) ? strtolower( $parts[1] ) : '';

		return '' === $fragment ? $base : $base . '#' . $fragment;
	}

	/**
	 * Normalize a URL for comparison (scheme, www and trailing slash agnostic).
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function normalize_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$url = strtolower( trim( $url ) );
		$url = preg_replace( '#^https?://#', '', $url );
		$url = preg_replace( '#^www\.#', '', $url );

		return rtrim( $url, '/' );
	}

	/**
	 * Normalize an entity name (case, punctuation and legal-form agnostic).
	 *
	 * @param string $name Entity name.
	 *
	 * @return string
	 */
	public static function normalize_name( $name ) {
		if ( ! is_string( $name ) || '' === $name ) {
			return '';
		}

		$name = strtolower( $name );
		$name = str_replace( [ '&', '+' ], ' and ', $name );
		$name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );

		// Strip trailing legal-form suffixes ("Acme Ltd" === "Acme").
		$words = explode( ' ', $name );
		while ( ! empty( $words ) && count( $words ) > 1
			&& in_array( end( $words ), self::NAME_SUFFIXES, true )
		) {
			array_pop( $words );
		}

		return implode( ' ', $words );
	}

	/**
	 * Normalize a phone number to its last nine digits.
	 *
	 * @param string $phone Phone number.
	 *
	 * @return string
	 */
	public static function normalize_phone( $phone ) {
		if ( ! is_string( $phone ) || '' === $phone ) {
			return '';
		}

		$digits = preg_replace( '/\D+/', '', $phone );
		if ( strlen( $digits ) < 7 ) {
			return '';
		}

		return substr( $digits, -9 );
	}

	/**
	 * Normalize one or more PostalAddress nodes into comparable strings.
	 *
	 * @param array|string $address PostalAddress node, list of nodes, or string.
	 *
	 * @return array
	 */
	public static function normalize_address( $address ) {
		if ( empty( $address ) || ! is_array( $address ) ) {
			return [];
		}

		// A single PostalAddress node, or a list of them.
		$list = isset( $address['@type'] ) || isset( $address['streetAddress'] ) ? [ $address ] : $address;

		$normalized = [];
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$street = self::normalize_text( $item['streetAddress'] ?? '' );
			if ( '' === $street ) {
				continue;
			}
			$area = self::normalize_text( $item['postalCode'] ?? '' );
			if ( '' === $area ) {
				$area = self::normalize_text( $item['addressLocality'] ?? '' );
			}
			if ( '' === $area ) {
				continue;
			}
			$normalized[] = $street . '|' . $area;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize a sameAs value into a comparable list of URLs.
	 *
	 * @param array|string $same_as sameAs value.
	 *
	 * @return array
	 */
	public static function normalize_same_as( $same_as ) {
		if ( is_string( $same_as ) ) {
			$same_as = [ $same_as ];
		}
		if ( ! is_array( $same_as ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $same_as as $url ) {
			$url = self::normalize_url( is_string( $url ) ? $url : '' );
			if ( '' !== $url ) {
				$normalized[] = $url;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Collect the @id of every Review node owned by a business node.
	 *
	 * @param array $node Business node.
	 *
	 * @return array Normalized review @id list.
	 */
	private static function get_review_ids( $node ) {
		if ( empty( $node['review'] ) || ! is_array( $node['review'] ) ) {
			return [];
		}

		$reviews = isset( $node['review']['@id'] ) ? [ $node['review'] ] : $node['review'];

		$ids = [];
		foreach ( $reviews as $review ) {
			if ( is_array( $review ) && ! empty( $review['@id'] ) ) {
				$ids[] = self::normalize_id( $review['@id'] );
			}
		}

		return $ids;
	}

	/**
	 * Re-point every reference to a removed node at the surviving node.
	 *
	 * @param array  $graph Schema graph list.
	 * @param string $from  Normalized @id of the removed node.
	 * @param string $to    Raw @id of the surviving node.
	 *
	 * @return array
	 */
	private static function repoint_references( $graph, $from, $to ) {
		array_walk_recursive(
			$graph,
			function ( &$value, $key ) use ( $from, $to ) {
				if ( '@id' === $key && is_string( $value ) && self::normalize_id( $value ) === $from ) {
					$value = $to;
				}
			}
		);

		return $graph;
	}

	/**
	 * Normalized @id of the global site entity, as built by Schema::site_schema().
	 *
	 * Mirrors Schema::get_site_schema_id(); kept in sync with that method.
	 *
	 * @return string
	 */
	private static function get_canonical_id() {
		$settings = get_option( 'rtrs_schema_settings' );
		$settings = is_array( $settings ) ? $settings : [];
		$category = ! empty( $settings['site_category'] ) ? $settings['site_category'] : 'localBusiness';

		return self::normalize_id( home_url( '#' . strtolower( $category ) ) );
	}

	/**
	 * Flat, lowercased list of every Organization/LocalBusiness family type.
	 *
	 * @return array
	 */
	private static function get_business_types() {
		if ( null !== self::$business_types ) {
			return self::$business_types;
		}

		$types = [ 'Organization', 'LocalBusiness' ];
		$types = array_merge( $types, Functions::getLocalBusinessTypeList() );
		$types = array_merge( $types, self::flatten_types( Functions::getSiteSubTypesOrganization() ) );

		self::$business_types = array_values( array_unique( array_map( 'strtolower', $types ) ) );

		return self::$business_types;
	}

	/**
	 * Flatten a nested schema-type tree into a list of type names.
	 *
	 * @param array $tree Nested type tree.
	 *
	 * @return array
	 */
	private static function flatten_types( $tree ) {
		$flat = [];
		foreach ( (array) $tree as $key => $value ) {
			if ( is_string( $key ) ) {
				$flat[] = $key;
			}
			if ( is_array( $value ) ) {
				$flat = array_merge( $flat, self::flatten_types( $value ) );
				continue;
			}
			if ( is_string( $value ) ) {
				$flat[] = $value;
			}
		}

		return $flat;
	}

	/**
	 * Lowercase and collapse a free-text value for comparison.
	 *
	 * @param string $text Text value.
	 *
	 * @return string
	 */
	private static function normalize_text( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}

		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}
}
