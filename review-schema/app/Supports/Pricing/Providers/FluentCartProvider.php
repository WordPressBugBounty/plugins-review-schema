<?php
/**
 * FluentCart Pricing Provider
 *
 * Extracts pricing data from FluentCart products.
 * FluentCart stores product details in a custom database table.
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
 * Class FluentCartProvider
 *
 * Resolves pricing from FluentCart products via their database records.
 */
class FluentCartProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'fluentcart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'FluentCart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'SUSPENDED_STARTER_STARTER_VERSION' )
			|| defined( 'FLUENT_CART_VERSION' )
			|| class_exists( 'FluentCart\App\App' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		return 'fluent-products' === get_post_type( $post_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		global $wpdb;

		// FluentCart stores product details in its own table.
		$table = $wpdb->prefix . 'fc_product_details';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Detecting whether FluentCart table exists at request time.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return null;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// $table is built from $wpdb->prefix + hard-coded suffix; not user-supplied. $post_id is bound via %d placeholder.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d LIMIT 1",
				$post_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $row ) {
			return $this->get_pricing_from_meta( $post_id );
		}

		$price    = isset( $row->price ) ? $this->cents_to_decimal( $row->price ) : '0';
		$currency = ! empty( $row->currency ) ? strtoupper( $row->currency ) : $this->get_currency();

		// Check for compare/sale price.
		if ( ! empty( $row->compare_price ) ) {
			$compare_price = $this->cents_to_decimal( $row->compare_price );

			if ( (float) $compare_price > (float) $price ) {
				$price = $price; // Use sale price (lower).
			}
		}

		return new PricingData( [
			'price'             => $price,
			'price_currency'    => $currency,
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Fallback: get pricing from post meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return PricingData|null
	 */
	private function get_pricing_from_meta( $post_id ) {
		$price = get_post_meta( $post_id, '_fc_price', true );

		if ( '' === $price || false === $price ) {
			return null;
		}

		return new PricingData( [
			'price'          => (string) $price,
			'price_currency' => $this->get_currency(),
			'availability'   => 'https://schema.org/InStock',
			'provider'       => $this->get_slug(),
		] );
	}

	/**
	 * Convert cents to decimal amount.
	 *
	 * @param int|string $cents Value in cents.
	 *
	 * @return string Decimal amount.
	 */
	private function cents_to_decimal( $cents ) {
		return number_format( (float) $cents / 100, 2, '.', '' );
	}

	/**
	 * Get FluentCart currency.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		$settings = get_option( 'fc_general_settings', [] );

		return ! empty( $settings['currency'] ) ? strtoupper( $settings['currency'] ) : 'USD';
	}
}
