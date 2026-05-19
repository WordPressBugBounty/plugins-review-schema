<?php
/**
 * Tutor LMS Pricing Provider
 *
 * Extracts pricing data from Tutor LMS courses.
 * Tutor LMS uses WooCommerce or EDD as payment gateways,
 * so pricing is resolved through the linked product.
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
 * Class TutorLmsProvider
 *
 * Resolves pricing from Tutor LMS courses via their linked
 * WooCommerce or EDD product.
 */
class TutorLmsProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'tutor_lms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Tutor LMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'TUTOR_VERSION' ) || function_exists( 'tutor' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'courses' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		// Check if it's a paid course.
		$course_price_type = get_post_meta( $post_id, '_tutor_course_price_type', true );

		if ( 'free' === $course_price_type || empty( $course_price_type ) ) {
			return new PricingData( [
				'price'          => '0',
				'price_currency' => $this->get_currency(),
				'availability'   => 'https://schema.org/InStock',
				'provider'       => $this->get_slug(),
			] );
		}

		// Tutor LMS links to a WooCommerce product for paid courses.
		$product_id = get_post_meta( $post_id, '_tutor_course_product_id', true );

		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product && '' !== $product->get_price() ) {
				$sale_end = '';
				$sale_to  = $product->get_date_on_sale_to();

				if ( $sale_to ) {
					$sale_end = $sale_to->date( 'Y-m-d' );
				}

				return new PricingData( [
					'price'             => $product->get_price(),
					'price_currency'    => get_woocommerce_currency(),
					'availability'      => 'https://schema.org/InStock',
					'price_valid_until' => $sale_end ? $sale_end : gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
					'provider'          => $this->get_slug(),
				] );
			}
		}

		// Fallback: EDD product integration.
		$edd_product_id = get_post_meta( $post_id, '_tutor_course_product_id', true );

		if ( $edd_product_id && function_exists( 'edd_get_download_price' ) ) {
			$price    = edd_get_download_price( $edd_product_id );
			$currency = function_exists( 'edd_get_currency' ) ? edd_get_currency() : 'USD';

			if ( false !== $price && '' !== $price ) {
				return new PricingData( [
					'price'             => (string) $price,
					'price_currency'    => $currency,
					'availability'      => 'https://schema.org/InStock',
					'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
					'provider'          => $this->get_slug(),
				] );
			}
		}

		return null;
	}

	/**
	 * Get currency from WooCommerce or EDD if available.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return get_woocommerce_currency();
		}

		if ( function_exists( 'edd_get_currency' ) ) {
			return edd_get_currency();
		}

		return 'USD';
	}
}
