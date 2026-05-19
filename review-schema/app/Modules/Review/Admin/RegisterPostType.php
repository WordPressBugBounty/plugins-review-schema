<?php

namespace Rtrs\Modules\Review\Admin;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RegisterPostType {
	use SingletonTrait;

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ], 5 );
		add_filter( 'parent_file', [ $this, 'fix_parent_menu' ] );
		add_filter( 'submenu_file', [ $this, 'fix_submenu_highlight' ], 10, 2 );
	}

	/**
	 * Fix parent menu highlight for custom post type screens.
	 *
	 * @param string $parent_file The current parent file.
	 *
	 * @return string
	 */
	public function fix_parent_menu( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, [ rtrs()->getPostType(), rtrs()->getPostTypeAffiliate() ], true ) ) {
			return 'review-schema';
		}
		return $parent_file;
	}

	/**
	 * Fix submenu highlight for custom post type screens.
	 *
	 * @param string|null $submenu_file The current submenu file.
	 * @param string      $parent_file  The current parent file.
	 *
	 * @return string|null
	 */
	public function fix_submenu_highlight( $submenu_file, $parent_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $submenu_file;
		}
		if ( $screen->post_type === rtrs()->getPostType() ) {
			return 'edit.php?post_type=' . rtrs()->getPostType();
		}
		if ( $screen->post_type === rtrs()->getPostTypeAffiliate() ) {
			return 'edit.php?post_type=' . rtrs()->getPostTypeAffiliate();
		}
		return $submenu_file;
	}

	public static function register_post_types() {

		if ( ! is_blog_installed() || post_type_exists( rtrs()->getPostType() ) ) {
			return;
		}

		do_action( 'rtrs_register_post_type' );

		$labels = [
			'name'               => esc_html_x( 'Review Settings', 'Post Type General Name', 'review-schema' ),
			'singular_name'      => esc_html_x( 'Review Settings', 'Post Type Singular Name', 'review-schema' ),
			'menu_name'          => esc_html__( 'Review Settings', 'review-schema' ),
			'parent_item_colon'  => esc_html__( 'Parent Review Settings:', 'review-schema' ),
			'all_items'          => esc_html__( 'Setup Reviews', 'review-schema' ),
			'view_item'          => esc_html__( 'View Review Settings', 'review-schema' ),
			'add_new_item'       => esc_html__( 'Add New', 'review-schema' ),
			'add_new'            => esc_html__( 'Add New', 'review-schema' ),
			'edit_item'          => esc_html__( 'Edit Review Settings', 'review-schema' ),
			'update_item'        => esc_html__( 'Update Review Settings', 'review-schema' ),
			'search_items'       => esc_html__( 'Search Review Settings', 'review-schema' ),
			'not_found'          => esc_html__( 'No review settings found', 'review-schema' ),
			'not_found_in_trash' => esc_html__( 'No review settings found in Trash', 'review-schema' ),
		];

		$rtrs_support = [ 'title' ];
		$args         = [
			'label'               => esc_html__( 'Review Schema', 'review-schema' ),
			'description'         => esc_html__( 'Review Schema', 'review-schema' ),
			'labels'              => $labels,
			'supports'            => $rtrs_support,
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => current_user_can( 'administrator' ),
			'show_in_menu'        => 'review-schema',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'menu_icon'           => RTRS_URL . '/assets/imgs/icon-20x20.svg',
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
		];
		register_post_type( rtrs()->getPostType(), apply_filters( 'rtrs_register_post_type_args', $args ) );

		do_action( 'rtrs_after_register_post_type' );

		if ( Functions::affiliate_enabled() ) {
			do_action( 'rtrs_register_post_type_affilite' );
			$label = [
				'name'               => esc_html_x( 'Affiliate', 'Post Type General Name', 'review-schema' ),
				'singular_name'      => esc_html_x( 'Affiliate', 'Post Type Singular Name', 'review-schema' ),
				'menu_name'          => esc_html__( 'Affiliate', 'review-schema' ),
				'parent_item_colon'  => esc_html__( 'Parent Affiliate:', 'review-schema' ),
				'all_items'          => esc_html__( 'All Affiliates', 'review-schema' ),
				'view_item'          => esc_html__( 'View Affiliate', 'review-schema' ),
				'add_new_item'       => esc_html__( 'Add New Affiliate', 'review-schema' ),
				'add_new'            => esc_html__( 'New Affiliate', 'review-schema' ),
				'edit_item'          => esc_html__( 'Edit Affiliate', 'review-schema' ),
				'update_item'        => esc_html__( 'Update Affiliate', 'review-schema' ),
				'search_items'       => esc_html__( 'Search Affiliate', 'review-schema' ),
				'not_found'          => esc_html__( 'No affiliate found', 'review-schema' ),
				'not_found_in_trash' => esc_html__( 'No affiliate found in Trash', 'review-schema' ),
			];

			$rtrs_support = [ 'title' ];
			$args         = [
				'label'               => esc_html__( 'Affiliate', 'review-schema' ),
				'description'         => esc_html__( 'Affiliate', 'review-schema' ),
				'labels'              => $label,
				'supports'            => $rtrs_support,
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => current_user_can( 'administrator' ),
				'show_in_menu'        => 'review-schema',
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => true,
				'menu_position'       => 20,
				'menu_icon'           => RTRS_URL . '/assets/imgs/icon-20x20.svg',
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => 'page',
			];
			register_post_type( rtrs()->getPostTypeAffiliate(), apply_filters( 'rtrs_register_post_type_affilite_args', $args ) );
			do_action( 'rtrs_after_register_post_type_affilite' );
		}
	}
}
