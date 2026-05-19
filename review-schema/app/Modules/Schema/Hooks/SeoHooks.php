<?php
namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoHooks {

	use SingletonTrait;

	private function __construct() {
		add_action( 'plugins_loaded', [ __CLASS__, 'plugins_loaded' ] );
	}

	static function isYoastActive() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "active_plugins" is a WP core filter; we apply it here to detect active plugins.
		if ( in_array( 'wordpress-seo/wp-seo.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			return true;
		}
		return false;
	}

	static function isWcActive() {
		return class_exists( 'woocommerce' );
	}

	public static function isEddActive() {
		return class_exists( 'Easy_Digital_Downloads' );
	}
	public static function isRankMathActive() {
		return class_exists( 'RankMath' );
	}

    public static function isSureCartActive() {
        return defined( 'SURECART_PLUGIN_FILE' );
    }

	public static function plugins_loaded() {
		$settings = get_option( 'rtrs_schema_tpp_settings' );
		if ( self::isYoastActive() ) {
			if ( isset( $settings['yoast_search_schema'] ) && $settings['yoast_search_schema'] == 'yes' ) {
				add_filter( 'disable_wpseo_json_ld_search', '__return_true' );
			}
			if ( isset( $settings['yoast_schema'] ) && $settings['yoast_schema'] == 'yes' ) {
				add_filter( 'wpseo_json_ld_output', [ __CLASS__, 'disable_yoast_schema_data' ], 10 );
				add_filter( 'wpseo_schema_graph_pieces', '__return_empty_array' );
			}
		}

		if ( self::isWcActive() ) {
			if ( isset( $settings['wc_schema'] ) && $settings['wc_schema'] == 'yes' ) {
				add_filter(
					'woocommerce_structured_data_type_for_page',
					[
						__CLASS__,
						'remove_product_structured_data',
					],
					10,
					2
				);
				add_action( 'init', [ __CLASS__, 'remove_output_structured_data' ] );
			}
		}
		if ( self::isEddActive() ) {
			if ( isset( $settings['edd_schema'] ) && $settings['edd_schema'] == 'yes' ) {
				add_filter( 'edd_add_schema_microdata', '__return_false' ); // Legacy EDD < 3.0
				add_action( 'init', [ __CLASS__, 'remove_edd_structured_data' ] ); // EDD 3.0+
			}
		}

		if ( self::isRankMathActive() ) {
			if ( isset( $settings['rank_math_schema'] ) && $settings['rank_math_schema'] == 'yes' ) {
				add_filter( 'rank_math/json_ld', '__return_empty_array', 99, 2 );
			}
		}

        if (self::isSureCartActive()) {
            if ( isset( $settings['sure_cart_schema'] ) && $settings['sure_cart_schema'] == 'yes' ) {
                add_filter( 'sc_display_product_json_ld_schema', '__return_false' );
                add_filter( 'sc_display_instant_checkout_json_ld_schema', '__return_false' );
            }
        }
	}

	public static function disable_yoast_schema_data( $data ) {
		$data = [];
		return $data;
	}

	/**
	 * Remove all product structured data.
	 */
	static function remove_product_structured_data( $types ) {
		if ( ( $index = array_search( 'product', $types ) ) !== false ) {
			unset( $types[ $index ] );
		}
		return $types;
	}

	/**
	 * Remove EDD 3.0+ JSON-LD structured data.
	 */
	static function remove_edd_structured_data() {
		if ( function_exists( 'EDD' ) && isset( EDD()->structured_data ) ) {
			remove_action( 'wp_footer', [ EDD()->structured_data, 'output_structured_data' ] );
		}
	}

	/* Remove the default WooCommerce 3 JSON/LD structured data */
	static function remove_output_structured_data() {
		remove_action(
			'wp_footer',
			[
				WC()->structured_data,
				'output_structured_data',
			],
			10
		); // This removes structured data from all frontend pages
		remove_action(
			'woocommerce_email_order_details',
			[
				WC()->structured_data,
				'output_email_structured_data',
			],
			30
		); // This removes structured data from all Emails sent by WooCommerce
	}
}
