<?php
/**
 * Download Manager Pricing Provider
 *
 * Extracts pricing data from WordPress Download Manager (WPDM) products.
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
 * Class DownloadManagerProvider
 *
 * Resolves pricing from WordPress Download Manager premium packages.
 */
class DownloadManagerProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'download_manager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Download Manager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'WPDM_Version' ) || class_exists( 'WordPressDownloadManager' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'wpdmpro' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		// WPDM stores price in post meta.
		$price = get_post_meta( $post_id, '__wpdm_base_price', true );

		if ( '' === $price || false === $price ) {
			// Try the package price meta.
			$price = get_post_meta( $post_id, '__wpdm_package_price', true );
		}

		if ( '' === $price || false === $price ) {
			return new PricingData( [
				'price'          => '0',
				'price_currency' => $this->get_currency(),
				'availability'   => 'https://schema.org/InStock',
				'provider'       => $this->get_slug(),
			] );
		}

		// Remove any currency symbol from the price string.
		$numeric_price = preg_replace( '/[^0-9.]/', '', (string) $price );

		return new PricingData( [
			'price'             => $numeric_price,
			'price_currency'    => $this->get_currency(),
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Get Download Manager currency.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		$settings = get_option( '_wpdm_payment_currency', '' );

		if ( ! empty( $settings ) ) {
			return strtoupper( $settings );
		}

		// Fallback to WooCommerce if active.
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return get_woocommerce_currency();
		}

		return 'USD';
	}
}
