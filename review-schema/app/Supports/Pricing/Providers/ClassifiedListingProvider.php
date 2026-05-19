<?php
/**
 * Classified Listing Pricing Provider
 *
 * Extracts pricing data from Classified Listing plugin listings.
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
 * Class ClassifiedListingProvider
 *
 * Resolves pricing from Classified Listing (rtcl) listings.
 */
class ClassifiedListingProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'classified_listing';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Classified Listing';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return class_exists( 'Rtcl' ) || defined( 'RTCL_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'rtcl_listing' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		$price      = get_post_meta( $post_id, '_price', true );
		$price_type = get_post_meta( $post_id, '_price_type', true );

		if ( '' === $price || false === $price ) {
			// Negotiable or contact-based listing.
			return new PricingData( [
				'price'          => '0',
				'price_currency' => $this->get_currency(),
				'availability'   => 'https://schema.org/InStock',
				'provider'       => $this->get_slug(),
			] );
		}

		$currency = $this->get_currency();

		return new PricingData( [
			'price'             => (string) $price,
			'price_currency'    => $currency,
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Get Classified Listing currency.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		if ( function_exists( 'rtcl' ) ) {
			$general = rtcl()->getOption( 'currency', 'USD' );

			return ! empty( $general ) ? $general : 'USD';
		}

		$options = get_option( 'rtcl_general_settings', [] );

		return ! empty( $options['currency'] ) ? $options['currency'] : 'USD';
	}
}
