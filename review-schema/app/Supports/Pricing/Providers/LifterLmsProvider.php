<?php
/**
 * Lifter LMS Pricing Provider
 *
 * Extracts pricing data from Lifter LMS courses and memberships.
 * Lifter LMS stores pricing via access plans linked to courses.
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
 * Class LifterLmsProvider
 *
 * Resolves pricing from Lifter LMS course access plans.
 */
class LifterLmsProvider implements PricingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'lifter_lms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Lifter LMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'LLMS_PLUGIN_FILE' ) || class_exists( 'LifterLMS' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_post( $post_id ) {
		$post_type = get_post_type( $post_id );

		return in_array( $post_type, [ 'course', 'llms_membership' ], true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pricing( $post_id ) {
		$plans = $this->get_access_plans( $post_id );

		if ( empty( $plans ) ) {
			return new PricingData( [
				'price'          => '0',
				'price_currency' => $this->get_currency(),
				'availability'   => 'https://schema.org/InStock',
				'provider'       => $this->get_slug(),
			] );
		}

		// Find the lowest priced plan.
		$lowest_price = PHP_FLOAT_MAX;

		foreach ( $plans as $plan ) {
			if ( ! is_a( $plan, 'LLMS_Access_Plan' ) ) {
				continue;
			}

			$is_free = $plan->is_free();

			if ( $is_free ) {
				$lowest_price = 0;
				break;
			}

			$plan_price = $plan->get_price( 'price', [], 'float' );

			// Check for sale price.
			if ( $plan->is_on_sale() ) {
				$plan_price = $plan->get_price( 'sale_price', [], 'float' );
			}

			if ( $plan_price < $lowest_price ) {
				$lowest_price = $plan_price;
			}
		}

		if ( PHP_FLOAT_MAX === $lowest_price ) {
			return null;
		}

		return new PricingData( [
			'price'             => (string) $lowest_price,
			'price_currency'    => $this->get_currency(),
			'availability'      => 'https://schema.org/InStock',
			'price_valid_until' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'provider'          => $this->get_slug(),
		] );
	}

	/**
	 * Get access plans for a course or membership.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Array of LLMS_Access_Plan objects.
	 */
	private function get_access_plans( $post_id ) {
		if ( ! function_exists( 'llms_get_post' ) ) {
			return [];
		}

		$course = llms_get_post( $post_id );

		if ( ! $course || ! method_exists( $course, 'get_access_plans' ) ) {
			return [];
		}

		return $course->get_access_plans();
	}

	/**
	 * Get the Lifter LMS currency.
	 *
	 * @return string ISO 4217 currency code.
	 */
	private function get_currency() {
		if ( function_exists( 'get_lifterlms_currency' ) ) {
			return get_lifterlms_currency();
		}

		return get_option( 'lifterlms_currency', 'USD' );
	}
}
