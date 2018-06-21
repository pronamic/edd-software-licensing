<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * After a payment has been marked as complete, check to see if it was an upgrade or renewal and add appropriate license meta.
 *
 * @since 3.6
 *
 * @param $payment_id
 * @param $payment
 * @param $customer
 */
function edd_sl_set_upgrade_renewal_dates( $payment_id, $payment, $customer ) {
	$is_renewal = $payment->get_meta( '_edd_sl_upgraded_payment_id' );
	$is_upgrade = $payment->get_meta( '_edd_sl_is_renewal' );

	if ( empty( $is_renewal ) && empty( $is_upgrade ) ) {
		return;
	}

	foreach ( $payment->cart_details as $cart_item ) {

		$license_id = ! empty( $cart_item['item_number']['options']['license_id'] )
			? intval( $cart_item['item_number']['options']['license_id'] )
			: false;

		if ( empty( $license_id ) ) {
			return;
		}

		$license = edd_software_licensing()->get_license( $license_id );
		if ( false === $license ) {
			return;
		}

		if ( ! empty( $cart_item['item_number']['options']['is_renewal'] ) ) {
			$license->add_meta( '_edd_sl_renewal_date', $payment->completed_date );

			// Add the meta to all child licenses as well.
			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$child_license->add_meta( '_edd_sl_renewal_date', $payment->completed_date );
				}
			}

		} elseif ( ! empty( $cart_item['item_number']['options']['is_upgrade'] ) ) {
			$license->add_meta( '_edd_sl_upgrade_date', $payment->completed_date );

			// Add the meta to all child licenses as well.
			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$child_license->add_meta( '_edd_sl_upgrade_date', $payment->completed_date );
				}
			}
		}

	}
}
add_action( 'edd_complete_purchase', 'edd_sl_set_upgrade_renewal_dates', 10, 3 );

/**
 * Listen for calls to get_post_meta and see if we need to filter them.
 *
 * @since  3.4.8
 * @param  mixed  $value       The value get_post_meta would return if we don't filter.
 * @param  int    $object_id   The object ID post meta was requested for.
 * @param  string $meta_key    The meta key requested.
 * @param  bool   $single      If the person wants the single value or an array of the value
 * @return mixed               The value to return
 */
function edd_sl_get_meta_backcompat( $value, $object_id, $meta_key, $single ) {

	global $wpdb;

	$meta_keys = array( '_edd_sl_site_count' );

	if ( ! in_array( $meta_key, $meta_keys ) ) {
		return $value;
	}

	switch( $meta_key ) {

		case '_edd_sl_site_count':
			$value           = edd_software_licensing()->get_site_count( $object_id );
			$edd_is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $edd_is_checkout ) {
				// Throw deprecated notice if WP_DEBUG is defined and on
				trigger_error( __( 'The _edd_sl_site_count postmeta is <strong>deprecated</strong> since EDD Software Licensing 2.4.8! Use edd_software_licensing->get_site_count( $license_id ) instead.', 'edd_sl' ) );

				$backtrace = debug_backtrace();
				trigger_error( print_r( $backtrace, 1 ) );
			}

			break;

	}

	// If the 'single' param is false, we need to make this a single item array with the value within it
	if ( false === $single ) {
		$value = array( $value );
	}

	return $value;

}
add_filter( 'get_post_metadata', 'edd_sl_get_meta_backcompat', 10, 4 );

/**
 * Stores the payment IDs that are created during the migration to custom tables in Version 3.6
 *
 * @since 3.6
 *
 * @param $payment_id
 * @param $payment_data
 */
function _eddsl_migration_log_payment_ids( $payment_id, $payment_object ) {
	$is_migrating = get_option( 'edd_sl_is_migrating_licenses', false );
	if ( empty( $is_migrating ) ) {
		return;
	}

	$payments_during_migration   = get_option( 'edd_sl_payments_saved_during_migration', array() );
	$payments_during_migration[] = $payment_id;
	$payments_during_migration   = array_unique( $payments_during_migration );

	update_option( 'edd_sl_payments_saved_during_migration', $payments_during_migration );
}
add_action( 'edd_payment_saved', '_eddsl_migration_log_payment_ids', 99, 2 );
