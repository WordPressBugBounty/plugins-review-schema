<?php
/**
 * Academy LMS Pricing Provider
 *
 * Extracts pricing data from Academy LMS courses.
 * Academy LMS uses WooCommerce for payments and links
 * courses to WooCommerce products.
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
 * Class AcademyLmsProvider
 *
 * Resolves pricing from Academy LMS courses via linked WooCommerce products.
 */
class AcademyLmsProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'academy_lms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Academy LMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'ACADEMY_VERSION' ) || class_exists( 'Academy' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'academy_courses' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		$course_type = get_post_meta( $post_id, 'academy_course_type', true );

		if ( 'free' === $course_type || empty( $course_type ) ) {
			return new PricingData( [
				'price'          => '0',
				'price_currency' => $this->get_currency(),
				'availability'   => 'https://schema.org/InStock',
				'provider'       => $this->get_slug(),
			] );
		}

		// Academy LMS links paid courses to a WooCommerce product.
		$product_id = get_post_meta( $post_id, 'academy_course_product_id', true );

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

		return null;
	}

	/**
	 * Get currency code.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return get_woocommerce_currency();
		}

		return 'USD';
	}
}
