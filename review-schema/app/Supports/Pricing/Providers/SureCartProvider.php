<?php
/**
 * SureCart Pricing Provider
 *
 * Extracts pricing data from SureCart products.
 * SureCart uses a headless API-based architecture with
 * local WordPress posts linked via sc_id meta.
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
 * Class SureCartProvider
 *
 * Resolves pricing from SureCart products via their price records.
 */
class SureCartProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'surecart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'SureCart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'SURECART_PLUGIN_FILE' ) || class_exists( 'SureCart' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'sc_product' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		// Try the SureCart API if available.
		if ( class_exists( '\SureCart\Models\Product' ) ) {
			return $this->get_pricing_via_api( $post_id );
		}

		// Fallback to stored metrics.
		return $this->get_pricing_from_meta( $post_id );
	}

	/**
	 * Get pricing via SureCart's internal API models.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return PricingData|null
	 */
	private function get_pricing_via_api( $post_id ) {
		$sc_id = get_post_meta( $post_id, 'sc_id', true );

		if ( empty( $sc_id ) ) {
			return null;
		}

		try {
			$product = \SureCart\Models\Product::find( $sc_id );

			if ( ! $product || empty( $product->prices ) || ! is_object( $product->prices ) ) {
				return null;
			}

			$prices = $product->prices->data ?? [];

			if ( empty( $prices ) ) {
				return null;
			}

			$lowest  = PHP_INT_MAX;
			$currency = 'USD';

			foreach ( $prices as $price_obj ) {
				$amount = $price_obj->amount ?? 0;

				if ( $amount < $lowest ) {
					$lowest   = $amount;
					$currency = $price_obj->currency ?? 'usd';
				}
			}

			if ( PHP_INT_MAX === $lowest ) {
				return null;
			}

			return new PricingData( [
				'price'             => $this->convert_amount( $lowest ),
				'price_currency'    => strtoupper( $currency ),
				'availability'      => 'https://schema.org/InStock',
				'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
				'provider'          => $this->get_slug(),
			] );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Fallback: get pricing from post meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return PricingData|null
	 */
	private function get_pricing_from_meta( $post_id ) {
		$metrics = get_post_meta( $post_id, 'sc_product_metrics', true );

		if ( empty( $metrics ) || ! is_array( $metrics ) ) {
			return null;
		}

		$price    = $metrics['min_price'] ?? $metrics['price'] ?? '';
		$currency = $metrics['currency'] ?? 'USD';

		if ( '' === $price ) {
			return null;
		}

		return new PricingData( [
			'price'          => $this->convert_amount( $price ),
			'price_currency' => strtoupper( $currency ),
			'availability'   => 'https://schema.org/InStock',
			'provider'       => $this->get_slug(),
		] );
	}

	/**
	 * Convert SureCart amount (cents) to decimal.
	 *
	 * @param int|string $amount Amount in cents.
	 *
	 * @return string Decimal amount.
	 */
	private function convert_amount( $amount ) {
		return number_format( (float) $amount / 100, 2, '.', '' );
	}
}
