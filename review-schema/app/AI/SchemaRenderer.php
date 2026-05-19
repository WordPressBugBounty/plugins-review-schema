<?php
/**
 * Schema Renderer - Outputs JSON-LD schema in the page head.
 *
 * Merges content-specific schemas (saved in post meta) with global schemas
 * (Organization, WebSite, WebPage, BreadcrumbList) built on-demand from
 * site settings at render time.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Models\Schema;

defined( 'ABSPATH' ) || exit;

class SchemaRenderer {

	/**
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_head', [ $this, 'render_schema' ], 1 );
		add_action( 'save_post', [ $this, 'clear_schema_cache' ] );
	}

	/**
	 * @return void
	 */
	public function render_schema() {
		if ( ! is_singular() ) {
			return;
		}

		if ( 'yes' !== AIInit::getSetting( 'ai_enabled', 'no' ) ) {
			return;
		}

		// Do not emit schema JSON-LD if the Schema module is disabled,
		// even when AI is enabled for FAQ content generation.
		if ( ! \Rtrs\Helpers\Functions::schema_enabled() ) {
			return;
		}

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return;
		}

		$supported = AIInit::getSupportedPostTypes();

		if ( ! in_array( get_post_type( $post_id ), $supported, true ) ) {
			return;
		}

		$content_schemas = $this->get_schemas( $post_id );

		if ( empty( $content_schemas ) ) {
			return;
		}

		// Build global schemas on-demand from current settings.
		$global_schemas = $this->build_global_schemas( $post_id, $content_schemas );

		// Merge content + global into a single @graph.
		$schemas = array_merge( $content_schemas, $global_schemas );
		$schemas = apply_filters( 'rtrs_ai_schema_before_render', $schemas, $post_id );

		$graph = [
			'@context' => 'https://schema.org',
			'@graph'   => $schemas,
		];

		$json = wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( $json ) {
			echo "\n" . '<!-- Generated using SchemaEngine AI (v' . esc_html( RTRS_VERSION ) . ') by RadiusTheme -->' . "\n";

			// Prevent "</script>" sequence from breaking out of the JSON-LD script tag.
			$json = str_replace( '</', '<\/', $json );

			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $json is wp_json_encode()-produced JSON; HTML-escaping would break JSON-LD. The str_replace above neutralises </script> injection.
			);
		}
	}

	// -------------------------------------------------------------------------
	// Global schemas (built on-demand from settings + post data)
	// -------------------------------------------------------------------------

	/**
	 * Build global schema nodes from site settings and post data.
	 *
	 * @param  int   $post_id  Current post ID.
	 * @param  array $content_schemas  Content-specific schemas from post meta.
	 *
	 * @return array Array of global schema nodes.
	 */
	public function build_global_schemas( $post_id, $content_schemas = [] ) {
		$entity_data = AIInit::getEntityData();
		$site_url    = rtrim( home_url( '/' ), '/' );
		$permalink   = rtrim( get_permalink( $post_id ), '/' );
		$language    = get_locale();
		$entity_id   = $entity_data['entity_id'];

		$schema = new Schema();

		$globals = array_merge(
			$schema->site_schema(),
			[
				$schema->generate_breadcrumb_schema( $post_id ),
				$this->build_webpage_node( $post_id, $permalink, $site_url, $entity_id, $language, $content_schemas ),
			],
		);

		// Add MerchantReturnPolicy and OfferShippingDetails as global schemas for Product types.
		if ( function_exists( 'rtrsp' ) && class_exists( 'Rtrsp\\Helpers\\FnsPro' ) ) {
			$has_product = false;
			foreach ( $content_schemas as $cs ) {
				$type = $cs['@type'] ?? '';
				if ( in_array( $type, [ 'Product', 'ProductGroup' ], true ) ) {
					$has_product = true;
					break;
				}
			}

			if ( $has_product ) {
				$return_policy = \Rtrsp\Helpers\FnsPro::get_merchant_return_policy();
				if ( ! empty( $return_policy ) ) {
					$globals[] = $return_policy;
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
						$globals[] = $sd;
					}
				}
			}
		}

		return $globals;
	}

	/**
	 * Build the WebPage node.
	 *
	 * @param  int    $post_id  Post ID.
	 * @param  string $permalink  Post permalink without trailing slash.
	 * @param  string $site_url  Site URL without trailing slash.
	 * @param  string $entity_id  Entity @id reference.
	 * @param  string $language  Site language locale.
	 * @param  array  $content_schemas  Content schemas (for mainEntity links).
	 *
	 * @return array WebPage schema node.
	 */
	private function build_webpage_node( $post_id, $permalink, $site_url, $entity_id, $language, $content_schemas ) {
		$page_type       = 'WebPage';
		$item_page_types = [ 'Product', 'ProductGroup', 'SoftwareApplication' ];
		foreach ( $content_schemas as $schema ) {
			$type = $schema['@type'] ?? '';
			if ( is_array( $type ) ? ! empty( array_intersect( $type, $item_page_types ) ) : in_array( $type, $item_page_types, true ) ) {
				$page_type = 'ItemPage';
				break;
			}
		}

		$node = [
			'@type'         => $page_type,
			'@id'           => trailingslashit( $permalink ) . '#webpage',
			'name'          => get_the_title( $post_id ),
			'url'           => get_permalink( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
			'inLanguage'    => $language,
			'isPartOf'      => [ '@id' => $site_url . '/#website' ],
			'breadcrumb'    => [ '@id' => trailingslashit( $permalink ) . '#breadcrumb' ],
		];

		$schema_model          = new Schema( $post_id );
		$node['description'] = $schema_model->get_page_description();

		// Link content schemas as mainEntity.
		$main_entities = [];
		foreach ( $content_schemas as $schema ) {
			if ( ! empty( $schema['@id'] ) ) {
				$main_entities[] = [ '@id' => $schema['@id'] ];
			}
		}
		// / The Producr Schema Will Hide so dont use it
		if ( ! empty( $main_entities ) ) {
			$node['mainEntity'] = count( $main_entities ) === 1
				? $main_entities[0]
				: $main_entities;
		}

		return $node;
	}

	// -------------------------------------------------------------------------
	// Content schemas from post meta
	// -------------------------------------------------------------------------

	/**
	 * @param  int $post_id
	 *
	 * @return array
	 */
	private function get_schemas( $post_id ) {
		$cache_key = "rtrs_ai_schemas_{$post_id}";
		$cached    = wp_cache_get( $cache_key, 'rtrs_ai' );

		if ( false !== $cached ) {
			return $cached;
		}

		$schema_data = AIInit::normalizeSchemaData( get_post_meta( $post_id, AIInit::META_KEY, true ) );

		if ( empty( $schema_data ) ) {
			return [];
		}

		wp_cache_set( $cache_key, $schema_data, 'rtrs_ai', HOUR_IN_SECONDS );

		return $schema_data;
	}

	/**
	 * @param  int $post_id
	 *
	 * @return void
	 */
	public function clear_schema_cache( $post_id ) {
		wp_cache_delete( "rtrs_ai_schemas_{$post_id}", 'rtrs_ai' );
	}
}
