<?php
/**
 * Pricing Collector
 *
 * Central registry that manages pricing providers and resolves
 * dynamic pricing, shipping, and return policy data for schema generation.
 * Automatically detects active plugins and delegates extraction to the
 * appropriate provider.
 *
 * @package Rtrs\Supports\Pricing
 */

namespace Rtrs\Supports\Pricing;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PricingCollector
 *
 * Singleton registry that:
 * - Registers built-in pricing providers on init.
 * - Allows third-party providers via the `rtrs_pricing_providers` filter.
 * - Hooks into `rtrs_schema_meta_before_output` to inject dynamic pricing,
 *   shipping details, and return policy for Product and SoftwareApplication.
 * - Hooks into `rtrs_section_schema_fields` to remove per-product fields
 *   that are resolved dynamically or from global settings.
 */
class PricingCollector {

	use SingletonTrait;

	/**
	 * Schema categories that support dynamic pricing injection.
	 *
	 * @var array
	 */
	const SUPPORTED_SCHEMA_TYPES = [ 'product', 'software_application', 'software_app' ];

	/**
	 * Registered pricing providers.
	 *
	 * @var PricingProviderInterface[]
	 */
	private $providers = [];

	/**
	 * Whether providers have been registered.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Pricing fields removed from Product AND SoftwareApp groups
	 * when a dynamic pricing provider supports the current post.
	 *
	 * @var array
	 */
	const DYNAMIC_PRICING_FIELDS = [
		'pricing_section',
		'price',
		'priceCurrency',
		'priceValidUntil',
		'availability',
	];

	/**
	 * Shipping and return-policy field names for the Product group.
	 *
	 * @var array
	 */
	const PRODUCT_SETTINGS_FIELDS = [
		'shipping_details',
		'override_shipping',
		'shippingRate',
		'shippingDestination',
		'addressRegion',
		'handlingTime',
		'handlingTimeMinimum',
		'handlingTimeMaximum',
		'transitTimeMinimum',
		'transitTimeMaximum',
		'MerchantReturnPolicy',
		'override_return_policy',
		'applicableCountry',
		'merchantReturnDays',
		'returnPolicyCategory',
		'returnMethod',
		'returnFees',
		'returnShippingFeesAmount',
		'returnShippingFeesCurrency',
		'returnPolicyLink',
	];

	/**
	 * Constructor.
	 *
	 * Hooks into the schema meta pipeline and admin field definitions.
	 */
	private function __construct() {
		add_filter( 'rtrs_schema_meta_before_output', [ $this, 'maybe_inject_pricing' ], 10, 3 );
		add_filter( 'rtrs_schema_meta_before_output', [ $this, 'maybe_inject_shipping_return' ], 11, 3 );
		add_filter( 'rtrs_section_schema_fields', [ $this, 'maybe_remove_dynamic_fields' ], 20 );
	}

	/**
	 * Boot all providers on first use.
	 *
	 * Lazy-loads providers to avoid running detection logic
	 * before all plugins are loaded.
	 *
	 * @return void
	 */
	private function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$default_providers = [
			new Providers\WooCommerceProvider(),
			new Providers\EddProvider(),
			new Providers\LearnPressProvider(),
			new Providers\TutorLmsProvider(),
			new Providers\AcademyLmsProvider(),
			new Providers\LifterLmsProvider(),
			new Providers\ClassifiedListingProvider(),
			new Providers\FluentCartProvider(),
			new Providers\SureCartProvider(),
			new Providers\DownloadManagerProvider(),
		];

		/**
		 * Filter the list of pricing providers.
		 *
		 * Allows third-party plugins to register additional providers.
		 *
		 * @param PricingProviderInterface[] $providers Array of provider instances.
		 */
		$providers = apply_filters( 'rtrs_pricing_providers', $default_providers );

		foreach ( $providers as $provider ) {
			if ( $provider instanceof PricingProviderInterface ) {
				$this->providers[ $provider->get_slug() ] = $provider;
			}
		}
	}

	/**
	 * Get all registered providers.
	 *
	 * @return PricingProviderInterface[]
	 */
	public function get_providers() {
		$this->boot();

		return $this->providers;
	}

	/**
	 * Get only the active (available) providers.
	 *
	 * @return PricingProviderInterface[]
	 */
	public function get_active_providers() {
		$active = [];

		foreach ( $this->get_providers() as $slug => $provider ) {
			if ( $provider->is_active() ) {
				$active[ $slug ] = $provider;
			}
		}

		return $active;
	}

	/**
	 * Resolve pricing data for a given post.
	 *
	 * Iterates through active providers and returns pricing
	 * from the first provider that supports the post.
	 *
	 * @param int $post_id WordPress post ID.
	 *
	 * @return PricingData|null Resolved pricing or null.
	 */
	public function resolve( $post_id ) {
		foreach ( $this->get_active_providers() as $provider ) {
			if ( $provider->supports_post( $post_id ) ) {
				$pricing = $provider->get_pricing( $post_id );

				if ( $pricing instanceof PricingData && $pricing->has_price() ) {
					/**
					 * Filter the resolved pricing data.
					 *
					 * @param PricingData              $pricing  Resolved pricing data.
					 * @param int                      $post_id  Post ID.
					 * @param PricingProviderInterface $provider The provider that resolved the pricing.
					 */
					return apply_filters( 'rtrs_resolved_pricing_data', $pricing, $post_id, $provider );
				}
			}
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Frontend: Dynamic data injection into schema metadata.
	// -------------------------------------------------------------------------

	/**
	 * Inject dynamic pricing into schema metadata.
	 *
	 * Hooked to `rtrs_schema_meta_before_output` at priority 10.
	 *
	 * @param array  $meta       Schema metadata array.
	 * @param string $schema_cat Schema category slug.
	 * @param int    $post_id    WordPress post ID.
	 *
	 * @return array Modified metadata.
	 */
	public function maybe_inject_pricing( $meta, $schema_cat, $post_id ) {
		if ( ! in_array( $schema_cat, self::SUPPORTED_SCHEMA_TYPES, true ) ) {
			return $meta;
		}

		if ( ! empty( $meta['price'] ) ) {
			return $meta;
		}

		$pricing = $this->resolve( $post_id );

		if ( ! $pricing instanceof PricingData ) {
			return $meta;
		}

		$meta['price']         = $pricing->get_price();
		$meta['priceCurrency'] = $pricing->get_price_currency();

		if ( empty( $meta['availability'] ) && $pricing->get_availability() ) {
			$meta['availability'] = $pricing->get_availability();
		}

		if ( empty( $meta['priceValidUntil'] ) && $pricing->get_price_valid_until() ) {
			$meta['priceValidUntil'] = $pricing->get_price_valid_until();
		}

		/**
		 * Filter the schema metadata after dynamic pricing injection.
		 *
		 * @param array       $meta       Modified metadata.
		 * @param string      $schema_cat Schema category slug.
		 * @param int         $post_id    Post ID.
		 * @param PricingData $pricing    Resolved pricing data.
		 */
		return apply_filters( 'rtrs_schema_meta_after_pricing', $meta, $schema_cat, $post_id, $pricing );
	}

	/**
	 * Inject shipping details and return policy into product schema metadata.
	 *
	 * Hooked to `rtrs_schema_meta_before_output` at priority 11 (after pricing).
	 *
	 * Skips injection when per-product override flags are enabled,
	 * allowing the pro plugin's frontend methods to use per-product values.
	 *
	 * @param array  $meta       Schema metadata array.
	 * @param string $schema_cat Schema category slug.
	 * @param int    $post_id    WordPress post ID.
	 *
	 * @return array Modified metadata.
	 */
	public function maybe_inject_shipping_return( $meta, $schema_cat, $post_id ) {
		if ( 'product' !== $schema_cat ) {
			return $meta;
		}

		$ecommerce = get_option( 'rtrs_schema_ecommerce_settings', [] );

		// Skip return policy injection when per-product override is enabled.
		if ( empty( $meta['override_return_policy'] ) ) {
			$meta = $this->inject_return_policy( $meta, $ecommerce );
		}

		// Skip shipping injection when per-product override is enabled.
		if ( empty( $meta['override_shipping'] ) ) {
			$meta = $this->inject_shipping_details( $meta, $ecommerce );
		}

		return $meta;
	}

	/**
	 * Inject return policy data from global settings into metadata.
	 *
	 * @param array $meta       Schema metadata.
	 * @param array $ecommerce  E-commerce settings array.
	 *
	 * @return array Modified metadata.
	 */
	private function inject_return_policy( array $meta, array $ecommerce ) {
		$country = ! empty( $ecommerce['return_policy_country'] ) ? $ecommerce['return_policy_country'] : '';

		if ( empty( $country ) && function_exists( 'WC' ) && WC()->countries ) {
			$country = WC()->countries->get_base_country();
		}

		if ( empty( $country ) ) {
			$country = 'US';
		}

		$meta['applicableCountry'] = $country;

		$category = ! empty( $ecommerce['return_policy_category'] )
			? $ecommerce['return_policy_category']
			: 'MerchantReturnFiniteReturnWindow';

		$meta['returnPolicyCategory'] = 'https://schema.org/' . $category;

		if ( 'MerchantReturnFiniteReturnWindow' === $category ) {
			$days                       = isset( $ecommerce['return_policy_days'] ) && '' !== $ecommerce['return_policy_days']
				? absint( $ecommerce['return_policy_days'] )
				: 30;
			$meta['merchantReturnDays'] = $days;
		}

		$method               = ! empty( $ecommerce['return_policy_method'] ) ? $ecommerce['return_policy_method'] : 'ReturnByMail';
		$meta['returnMethod'] = 'https://schema.org/' . $method;

		$fees               = ! empty( $ecommerce['return_policy_fees'] ) ? $ecommerce['return_policy_fees'] : 'FreeReturn';
		$meta['returnFees'] = 'https://schema.org/' . $fees;

		return $meta;
	}

	/**
	 * Inject shipping details into metadata.
	 *
	 * Priority: WooCommerce shipping zones → global settings fallback.
	 *
	 * @param array $meta       Schema metadata.
	 * @param array $ecommerce  E-commerce settings array.
	 *
	 * @return array Modified metadata.
	 */
	private function inject_shipping_details( array $meta, array $ecommerce ) {
		$wc_shipping = $this->get_wc_shipping_data();

		if ( ! empty( $wc_shipping['rate'] ) && ! empty( $wc_shipping['country'] ) ) {
			$meta['shippingRate']        = $wc_shipping['rate'];
			$meta['shippingDestination'] = $wc_shipping['country'];
			if ( ! empty( $wc_shipping['region'] ) ) {
				$meta['addressRegion'] = $wc_shipping['region'];
			}
		}
		// Default shippingRate if not set (ensures shippingDetails is always present).
		if ( empty( $meta['shippingRate'] ) ) {
			$meta['shippingRate'] = '0';
		}

		// Default shippingDestination: settings → WC base country → 'US'.
		if ( empty( $meta['shippingDestination'] ) ) {
			$country = ! empty( $ecommerce['return_policy_country'] ) ? $ecommerce['return_policy_country'] : '';

			if ( empty( $country ) && function_exists( 'WC' ) && WC()->countries ) {
				$country = WC()->countries->get_base_country();
			}

			$meta['shippingDestination'] = ! empty( $country ) ? $country : 'US';
		}

		// Handling time: always from settings.
		$handling_min = isset( $ecommerce['handling_time_min'] ) && '' !== $ecommerce['handling_time_min']
			? absint( $ecommerce['handling_time_min'] )
			: 0;
		$handling_max = isset( $ecommerce['handling_time_max'] ) && '' !== $ecommerce['handling_time_max']
			? absint( $ecommerce['handling_time_max'] )
			: 1;

		$meta['handlingTimeMinimum'] = (string) $handling_min;
		$meta['handlingTimeMaximum'] = (string) $handling_max;

		// Transit time: always from settings.
		$transit_min = isset( $ecommerce['transit_time_min'] ) && '' !== $ecommerce['transit_time_min']
			? absint( $ecommerce['transit_time_min'] )
			: 3;
		$transit_max = isset( $ecommerce['transit_time_max'] ) && '' !== $ecommerce['transit_time_max']
			? absint( $ecommerce['transit_time_max'] )
			: 7;

		$meta['transitTimeMinimum'] = (string) $transit_min;
		$meta['transitTimeMaximum'] = (string) $transit_max;

		/**
		 * Filter the metadata after shipping/return injection.
		 *
		 * @param array $meta      Modified metadata.
		 * @param array $ecommerce E-commerce settings.
		 */
		return apply_filters( 'rtrs_schema_meta_after_shipping', $meta, $ecommerce );
	}

	/**
	 * Get shipping rate and destination from the first WooCommerce shipping zone.
	 *
	 * @return array{rate: string, country: string, region: string}|array Empty if unavailable.
	 */
	private function get_wc_shipping_data() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return [];
		}

		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone_data ) {
			$zone    = new \WC_Shipping_Zone( $zone_data['id'] );
			$methods = $zone->get_shipping_methods( true );

			if ( empty( $methods ) ) {
				continue;
			}

			$locations = $zone->get_zone_locations();
			$country   = '';
			$region    = '';

			foreach ( $locations as $location ) {
				if ( 'country' === $location->type ) {
					$country = $location->code;
					break;
				}

				if ( 'state' === $location->type ) {
					$parts = explode( ':', $location->code );
					if ( ! empty( $parts[0] ) ) {
						$country = $parts[0];
						$region  = $parts[1] ?? '';
					}
					break;
				}
			}

			if ( empty( $country ) ) {
				continue;
			}

			foreach ( $methods as $method ) {
				if ( 'flat_rate' === $method->id ) {
					$cost = $method->get_option( 'cost' );

					if ( is_numeric( $cost ) ) {
						return [
							'rate'    => $cost,
							'country' => $country,
							'region'  => $region,
						];
					}
				}

				if ( 'free_shipping' === $method->id ) {
					return [
						'rate'    => '0',
						'country' => $country,
						'region'  => $region,
					];
				}
			}
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// Admin: Remove per-product fields that are resolved dynamically.
	// -------------------------------------------------------------------------

	/**
	 * Remove dynamic/settings-based fields from schema metabox.
	 *
	 * Hooked to `rtrs_section_schema_fields` at priority 20.
	 *
	 * For Product schema group:
	 * - Pricing fields removed when a dynamic provider supports the post.
	 * - Shipping & return policy fields always removed (global settings).
	 *
	 * For SoftwareApp schema group:
	 * - Pricing fields removed when a dynamic provider supports the post.
	 *
	 * @param array $settings_fields Array of schema field groups.
	 *
	 * @return array Modified field groups.
	 */
	public function maybe_remove_dynamic_fields( $settings_fields ) {
		$post_id = $this->get_current_post_id();

		if ( ! $post_id ) {
			return $settings_fields;
		}

		$provider       = $this->get_supporting_provider( $post_id );
		$provider_label = $provider ? $provider->get_label() : '';

		foreach ( $settings_fields as &$field_group ) {
			if ( empty( $field_group['type'] ) || 'group' !== $field_group['type'] ) {
				continue;
			}

			if ( empty( $field_group['name'] ) || empty( $field_group['fields'] ) ) {
				continue;
			}

			$group_name = $field_group['name'];

			if ( 'rtrs_product_schema' === $group_name ) {
				$field_group['fields'] = $this->strip_product_fields(
					$field_group['fields'],
					$provider_label
				);
			} elseif ( 'rtrs_software_app_schema' === $group_name && $provider ) {
				$field_group['fields'] = $this->strip_fields_by_names(
					$field_group['fields'],
					self::DYNAMIC_PRICING_FIELDS,
					sprintf(
						/* translators: %s: plugin name */
						esc_html__( 'Pricing (auto-detected from %s)', 'review-schema' ),
						$provider_label
					),
					esc_html__( 'Price and currency are fetched dynamically from the connected plugin. No manual entry required.', 'review-schema' )
				);
			}
		}

		unset( $field_group );

		return $settings_fields;
	}

	/**
	 * Strip dynamic pricing fields from the Product group.
	 *
	 * Pricing fields are only removed when a dynamic provider is available.
	 * Shipping & return policy fields remain visible for per-product override.
	 *
	 * @param array  $fields         Field definitions.
	 * @param string $provider_label Provider label (empty if no provider).
	 *
	 * @return array Filtered fields.
	 */
	private function strip_product_fields( array $fields, $provider_label ) {
		// Pricing fields: only strip when provider is available.
		if ( ! empty( $provider_label ) ) {
			$fields = $this->strip_fields_by_names(
				$fields,
				self::DYNAMIC_PRICING_FIELDS,
				sprintf(
					/* translators: %s: plugin name */
					esc_html__( 'Pricing (auto-detected from %s)', 'review-schema' ),
					$provider_label
				),
				esc_html__( 'Price, currency, availability and price validity are fetched dynamically from the connected plugin. No manual entry required.', 'review-schema' )
			);
		}

		return $fields;
	}

	/**
	 * Remove fields by name and replace with a single heading notice.
	 *
	 * @param array  $fields      Field definitions.
	 * @param array  $field_names Names of fields to remove.
	 * @param string $label       Notice heading label.
	 * @param string $desc        Notice description text.
	 *
	 * @return array Filtered fields.
	 */
	private function strip_fields_by_names( array $fields, array $field_names, $label, $desc ) {
		$filtered     = [];
		$notice_added = false;

		foreach ( $fields as $field ) {
			$name = $field['name'] ?? '';

			if ( in_array( $name, $field_names, true ) ) {
				if ( ! $notice_added ) {
					$notice_added = true;
					$filtered[]   = [
						'type'  => 'heading',
						'name'  => 'dynamic_notice_' . md5( implode( ',', $field_names ) ),
						'label' => $label,
						'desc'  => $desc,
					];
				}

				continue;
			}

			$filtered[] = $field;
		}

		return $filtered;
	}

	/**
	 * Get the first active provider that supports the given post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return PricingProviderInterface|null
	 */
	public function get_supporting_provider( $post_id ) {
		foreach ( $this->get_active_providers() as $provider ) {
			if ( $provider->supports_post( $post_id ) ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Get the post ID of the post currently being edited.
	 *
	 * @return int Post ID or 0.
	 */
	private function get_current_post_id() {
		if ( ! is_admin() ) {
			return 0;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only post-ID lookup; result is always passed through absint(); no form data is mutated.
		if ( ! empty( $_GET['post'] ) ) {
			return absint( $_GET['post'] );
		}

		if ( ! empty( $_POST['post_ID'] ) ) {
			return absint( $_POST['post_ID'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		return get_the_ID() ?: 0;
	}
}
