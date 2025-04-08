<?php
/**
 * Deprecated functions.
 *
 * @package EDD_Software_Licensing
 * @since   3.8.12
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Disable core discounts on renewals, if enabled
 *
 * @since  3.5
 * @deprecated 3.8.12
 * @return void
 */
function edd_sl_remove_discounts_field() {
	if ( edd_get_option( 'edd_sl_disable_discounts', false ) && EDD()->session->get( 'edd_is_renewal' ) == '1' ) {
		remove_action( 'edd_checkout_form_top', 'edd_discount_field', -1 );
	}
}

/**
 * Prevent adding discounts through direct linking, if enabled
 *
 * @since  3.5
 * @deprecated 3.8.12
 * @return void
 */
function edd_sl_disable_url_discounts() {
	if ( edd_get_option( 'edd_sl_disable_discounts', false ) && EDD()->session->get( 'edd_is_renewal' ) == '1' ) {
		remove_action( 'init', 'edd_listen_for_cart_discount', 0 );
	}
}

/**
 * Remove existing discounts if renewal is set
 *
 * @since  3.5
 * @deprecated 3.8.12
 * @return void
 */
function edd_sl_remove_discounts() {
	if ( edd_get_option( 'edd_sl_disable_discounts', false ) && EDD()->session->get( 'edd_is_renewal' ) == '1' ) {
		add_filter( 'edd_cart_has_discounts', '__return_false' );
		edd_unset_all_cart_discounts();
	}
}
