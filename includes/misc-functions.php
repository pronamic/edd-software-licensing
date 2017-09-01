<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

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
