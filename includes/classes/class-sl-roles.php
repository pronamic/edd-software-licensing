<?php
/**
 * Roles and Capabilities
 *
 * @package     SoftwareLicensing
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2018, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_SL_Roles Class
 *
 * This class handles adding capabilities to the Easy Digital Downloads core roles
 *
 *
 * @since 3.6
 */
class EDD_SL_Roles {

	/**
	 * Get things going
	 *
	 * @since 3.6
	 */
	public function __construct() {}

	/**
	 * Add new shop-specific capabilities
	 *
	 * @access public
	 * @since 3.6
	 * @global WP_Roles $wp_roles
	 * @return void
	 */
	public function add_caps() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {
			$wp_roles->add_cap( 'shop_manager', 'view_licenses' );
			$wp_roles->add_cap( 'shop_manager', 'manage_licenses' );
			$wp_roles->add_cap( 'shop_manager', 'delete_licenses' );

			$wp_roles->add_cap( 'administrator', 'view_licenses' );
			$wp_roles->add_cap( 'administrator', 'manage_licenses' );
			$wp_roles->add_cap( 'administrator', 'delete_licenses' );

			$wp_roles->add_cap( 'shop_worker', 'view_licenses' );
			$wp_roles->add_cap( 'shop_worker', 'manage_licenses' );
		}
	}

	/**
	 * Remove core post type capabilities (called on uninstall)
	 *
	 * @access public
	 * @since 3.6
	 * @return void
	 */
	public function remove_caps() {

		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {
			$wp_roles->remove_cap( 'shop_manager', 'view_licenses' );
			$wp_roles->remove_cap( 'shop_manager', 'manage_licenses' );
			$wp_roles->remove_cap( 'shop_manager', 'delete_licenses' );

			$wp_roles->remove_cap( 'administrator', 'view_licenses' );
			$wp_roles->remove_cap( 'administrator', 'manage_licenses' );
			$wp_roles->remove_cap( 'administrator', 'delete_licenses' );

			$wp_roles->remove_cap( 'shop_worker', 'view_licenses' );
			$wp_roles->remove_cap( 'shop_worker', 'manage_licenses' );
		}
	}
}
