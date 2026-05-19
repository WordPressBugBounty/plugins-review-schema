<?php
/**
 * Easy Digital Downloads Pricing Provider
 *
 * Extracts pricing data from EDD downloads.
 * Supports both simple and variable pricing.
 *
 * @package Rtrs\Supports\Pricing\Providers
 */

namespace Rtrs\Supports\Pricing\Providers;

use Rtrs\Supports\Pricing\PricingData;
use Rtrs\Supports\Pricing\PricingProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EddProvider
 *
 * Resolves pricing from Easy Digital Downloads products.
 */
class EddProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'edd';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Easy Digital Downloads';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return class_exists( 'Easy_Digital_Downloads' ) && function_exists( 'edd_get_download_price' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'download' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		if ( function_exists( 'edd_has_variable_prices' ) && edd_has_variable_prices( $post_id ) ) {
			return $this->get_variable_pricing( $post_id );
		}

		$price = edd_get_download_price( $post_id );

		if ( false === $price || '' === $price ) {
			return null;
		}

		$currency = function_exists( 'edd_get_currency' ) ? edd_get_currency() : 'USD';

		return new PricingData( [
			'price'             => $price,
			'price_currency'    => $currency,
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Get pricing from variable-priced EDD downloads.
	 *
	 * Returns the lowest price tier.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return PricingData|null
	 */
	private function get_variable_pricing( $post_id ) {
		if ( ! function_exists( 'edd_get_variable_prices' ) ) {
			return null;
		}

		$prices = edd_get_variable_prices( $post_id );

		if ( empty( $prices ) || ! is_array( $prices ) ) {
			return null;
		}

		$lowest = PHP_FLOAT_MAX;

		foreach ( $prices as $tier ) {
			$amount = isset( $tier['amount'] ) ? floatval( $tier['amount'] ) : PHP_FLOAT_MAX;

			if ( $amount < $lowest ) {
				$lowest = $amount;
			}
		}

		if ( PHP_FLOAT_MAX === $lowest ) {
			return null;
		}

		$currency = function_exists( 'edd_get_currency' ) ? edd_get_currency() : 'USD';

		return new PricingData( [
			'price'             => (string) $lowest,
			'price_currency'    => $currency,
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}
}
