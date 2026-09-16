<?php
/**
 * Null Value Stripper.
 *
 * Schema builders assign values that can be null — Functions::sanitizeOutPut()
 * returns null for empty input, and several builders write an explicit
 * `: null` for absent repeater fields. Encoded as JSON-LD those become
 * `"postalCode": null` or `"url": null`, which Google's structured-data
 * validators report as errors even though a JSON-LD processor would drop them.
 *
 * Rather than guard every builder, this removes null-valued keys once, on the
 * assembled graph, for the traditional and AI render paths alike. Only a
 * strict null is removed: 0, '0', false and '' are legitimate schema values
 * and are left untouched.
 *
 * @package Rtrs\Modules\Schema\Hooks
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NullValueStripper
 *
 * Removes null-valued keys from the schema graph before it is rendered.
 */
class NullValueStripper {

	use SingletonTrait;

	/**
	 * Filter priority on both graph filters.
	 *
	 * Runs last, after DuplicateEntityFilter at 101, so nodes removed there
	 * are never walked and any key a later callback added is still cleaned.
	 */
	const PRIORITY = 102;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function __instance() {
		add_filter( 'rtrs_schema_graph_data', [ $this, 'filter_graph' ], self::PRIORITY );
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'filter_graph' ], self::PRIORITY );
	}

	/**
	 * Filter callback — drop every null-valued key in the graph.
	 *
	 * @param array $schema_graph_list The schema graph list.
	 *
	 * @return array Modified schema graph list.
	 */
	public function filter_graph( $schema_graph_list ) {
		if ( ! is_array( $schema_graph_list ) ) {
			return $schema_graph_list;
		}

		return self::strip_nulls( $schema_graph_list );
	}

	/**
	 * Recursively remove keys whose value is strictly null.
	 *
	 * Sequential arrays are re-indexed after a removal so they still encode as
	 * a JSON array rather than collapsing into an object with numeric keys.
	 *
	 * @param array $value Array to clean.
	 *
	 * @return array
	 */
	public static function strip_nulls( $value ) {
		$is_list = self::is_list( $value );
		$clean   = [];

		foreach ( $value as $key => $item ) {
			if ( null === $item ) {
				continue;
			}
			$clean[ $key ] = is_array( $item ) ? self::strip_nulls( $item ) : $item;
		}

		return $is_list ? array_values( $clean ) : $clean;
	}

	/**
	 * Whether an array is a sequential list.
	 *
	 * array_is_list() is PHP 8.1+; this plugin supports PHP 7.4.
	 *
	 * @param array $value Array to test.
	 *
	 * @return bool
	 */
	private static function is_list( array $value ) {
		if ( [] === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
