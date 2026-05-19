<?php
/**
 * WooCommerce Pricing Provider
 *
 * Extracts pricing data from WooCommerce products.
 * Supports simple and variable product types.
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
 * Class WooCommerceProvider
 *
 * Resolves pricing from WooCommerce products using the WC Product API.
 */
class WooCommerceProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'WooCommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'product' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return null;
		}

		$price    = $product->get_price();
		$currency = get_woocommerce_currency();

		if ( '' === $price ) {
			return null;
		}

		// Determine availability.
		$stock_status = $product->get_stock_status();
		$availability = $this->map_availability( $stock_status );

		// Sale end date for priceValidUntil.
		$sale_end = '';
		$sale_to  = $product->get_date_on_sale_to();

		if ( $sale_to ) {
			$sale_end = $sale_to->date( 'Y-m-d' );
		}

		return new PricingData( [
			'price'             => $price,
			'price_currency'    => $currency,
			'availability'      => $availability,
			'price_valid_until' => $sale_end ? $sale_end : gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Map WooCommerce stock status to schema.org availability.
	 *
	 * @param string $stock_status WooCommerce stock status.
	 *
	 * @return string Schema.org availability URL.
	 */
	private function map_availability( $stock_status ) {
		$map = [
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/BackOrder',
		];

		return $map[ $stock_status ] ?? 'https://schema.org/InStock';
	}
}
