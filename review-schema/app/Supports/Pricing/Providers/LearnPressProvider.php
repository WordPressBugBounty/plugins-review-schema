<?php
/**
 * LearnPress Pricing Provider
 *
 * Extracts pricing data from LearnPress courses.
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
 * Class LearnPressProvider
 *
 * Resolves pricing from LearnPress course products.
 */
class LearnPressProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'learnpress';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'LearnPress';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'LP_PLUGIN_FILE' ) || class_exists( 'LearnPress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'lp_course' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		$price    = get_post_meta( $post_id, '_lp_price', true );
		$currency = $this->get_currency();

		// LearnPress stores sale price separately.
		$sale_price = get_post_meta( $post_id, '_lp_sale_price', true );

		$resolved_price = ( '' !== $sale_price && false !== $sale_price )
			? $sale_price
			: $price;

		if ( '' === $resolved_price || false === $resolved_price ) {
			// Free course.
			$resolved_price = '0';
		}

		$sale_end = '';

		if ( '' !== $sale_price && false !== $sale_price ) {
			$sale_end_date = get_post_meta( $post_id, '_lp_sale_end', true );
			$sale_end      = ! empty( $sale_end_date ) ? $sale_end_date : '';
		}

		return new PricingData( [
			'price'             => (string) $resolved_price,
			'price_currency'    => $currency,
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => $sale_end ? $sale_end : gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Get the LearnPress currency.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		if ( function_exists( 'learn_press_get_currency' ) ) {
			return learn_press_get_currency();
		}

		$settings = get_option( 'learn_press_currency', 'USD' );

		return ! empty( $settings ) ? $settings : 'USD';
	}
}
