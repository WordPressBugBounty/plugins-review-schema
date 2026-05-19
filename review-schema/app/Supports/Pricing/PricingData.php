<?php
/**
 * Pricing Data Value Object
 *
 * Immutable data transfer object that holds pricing information
 * resolved from a compatible third-party plugin.
 *
 * @package Rtrs\Supports\Pricing
 */

namespace Rtrs\Supports\Pricing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PricingData
 *
 * Represents a resolved price and currency pair with metadata
 * about the source provider and product availability.
 */
class PricingData {

	/**
	 * Product price value.
	 *
	 * @var string
	 */
	private $price;

	/**
	 * ISO 4217 3-letter currency code (e.g. USD, EUR).
	 *
	 * @var string
	 */
	private $price_currency;

	/**
	 * Schema.org availability URL.
	 *
	 * @var string
	 */
	private $availability;

	/**
	 * Date until which the price is valid (Y-m-d format).
	 *
	 * @var string
	 */
	private $price_valid_until;

	/**
	 * Slug of the provider that resolved this pricing.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * PricingData constructor.
	 *
	 * @param array $args {
	 *     Pricing arguments.
	 *
	 *     @type string $price              Product price.
	 *     @type string $price_currency     ISO 4217 currency code.
	 *     @type string $availability       Schema.org availability URL.
	 *     @type string $price_valid_until  Price valid until date (Y-m-d).
	 *     @type string $provider           Provider slug.
	 * }
	 */
	public function __construct( array $args = [] ) {
		$this->price             = isset( $args['price'] ) ? (string) $args['price'] : '0';
		$this->price_currency    = ! empty( $args['price_currency'] ) ? strtoupper( $args['price_currency'] ) : 'USD';
		$this->availability      = ! empty( $args['availability'] ) ? $args['availability'] : 'https://schema.org/InStock';
		$this->price_valid_until = ! empty( $args['price_valid_until'] ) ? $args['price_valid_until'] : '';
		$this->provider          = ! empty( $args['provider'] ) ? $args['provider'] : '';
	}

	/**
	 * Get product price.
	 *
	 * @return string
	 */
	public function get_price() {
		return $this->price;
	}

	/**
	 * Get currency code.
	 *
	 * @return string
	 */
	public function get_price_currency() {
		return $this->price_currency;
	}

	/**
	 * Get schema.org availability URL.
	 *
	 * @return string
	 */
	public function get_availability() {
		return $this->availability;
	}

	/**
	 * Get price valid until date.
	 *
	 * @return string
	 */
	public function get_price_valid_until() {
		return $this->price_valid_until;
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_provider() {
		return $this->provider;
	}

	/**
	 * Check if pricing data has a valid price.
	 *
	 * @return bool
	 */
	public function has_price() {
		return '' !== $this->price;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array() {
		return [
			'price'             => $this->price,
			'price_currency'    => $this->price_currency,
			'availability'      => $this->availability,
			'price_valid_until' => $this->price_valid_until,
			'provider'          => $this->provider,
		];
	}
}
