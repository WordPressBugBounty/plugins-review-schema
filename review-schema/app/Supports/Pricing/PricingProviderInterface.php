<?php
/**
 * Pricing Provider Interface
 *
 * Contract that all pricing provider implementations must follow.
 * Each provider encapsulates pricing extraction logic for a specific
 * third-party WordPress plugin (e.g. WooCommerce, EDD, LMS plugins).
 *
 * @package Rtrs\Supports\Pricing
 */

namespace Rtrs\Supports\Pricing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface PricingProviderInterface
 *
 * Defines the contract for dynamic pricing data extraction
 * from compatible WordPress plugins.
 */
interface PricingProviderInterface {

	/**
	 * Get the unique provider slug.
	 *
	 * Used for identification and the `rtrs_pricing_providers` filter.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Get the human-readable provider label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Check whether the provider's plugin is active and available.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Check whether this provider can resolve pricing for the given post.
	 *
	 * @param int $post_id WordPress post ID.
	 *
	 * @return bool
	 */
	public function supports_post( $post_id );

	/**
	 * Resolve pricing data for the given post.
	 *
	 * @param int $post_id WordPress post ID.
	 *
	 * @return PricingData|null Pricing data or null if unavailable.
	 */
	public function get_pricing( $post_id );
}
