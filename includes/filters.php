<?php
/**
 * Software Licensing Filters.
 *
 * @package     EDD_Software_Licensing
 * @subpackage  SoftwareLicensing
 * @copyright   Copyright (c) 2017, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append beta file download links on the front end when `EDD_Download::get_files` is called
 *
 * @since 3.6
 *
 * @param $files             The existing files on the download
 * @param $download_id       The download ID to get files for
 * @param $variable_price_id The variable price ID supplied (not used for betas)
 *
 * @return array
 */
function edd_sl_add_beta_files( $files, $download_id, $variable_price_id ) {

	// Only execute this on the front end, or via AJAX
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return $files;
	}

	$download = new EDD_SL_Download( $download_id );

	$files = is_array( $files ) ? $files : array();

	if ( $download->has_beta() ) {
		$beta_files = $download->get_beta_files();
		$files      = array_merge( $files, $beta_files );
	}

	return $files;
}
add_filter( 'edd_download_files', 'edd_sl_add_beta_files', 10, 3 );

/**
 * Listen for calls to get_post_meta and see if we need to filter them.
 *
 * @since  3.4
 * @param  mixed  $value       The value get_post_meta would return if we don't filter.
 * @param  int    $object_id   The object ID post meta was requested for.
 * @param  string $meta_key    The meta key requested.
 * @param  bool   $single      If the person wants the single value or an array of the value
 * @return mixed               The value to return
 */
function _eddsl_get_meta_backcompat( $value, $object_id, $meta_key, $single ) {
	global $wpdb;

	$meta_keys = apply_filters( 'eddsl_post_meta_backwards_compat_keys', array_keys( eddsl_legacy_meta_property_map() ) );

	if ( ! in_array( $meta_key, $meta_keys ) ) {
		return $value;
	}

	if ( '_edd_sl_limit' === $meta_key ) {
		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM $wpdb->posts WHERE ID = %d", $object_id ) );
		if ( 'download' === $post_type ) {
			return $value;
		}
	}

	$edd_is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
	$show_notice     = apply_filters( 'eddsl_show_deprecated_notices', ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $edd_is_checkout && ! isset( $_GET['payment-mode'] ) ) );
	$license         = edd_software_licensing()->licenses_db->get( $object_id );

	if ( empty( $license->id ) ) {

		$object_id = eddsl_get_new_license_id_from_legacy_id( $object_id );
		if ( ! empty( $object_id ) ) {
			$license = edd_software_licensing()->licenses_db->get( $object_id );
		} else {
			return $value;
		}

		if ( empty( $license->id ) ) {
			return $value;
		}

	}

	$property = eddsl_get_property_from_legacy_key( $meta_key );

	if ( ! empty( $property ) ) {

		switch ( $property ) {

			case 'payment_ids':
				if ( $single ) {
					$value = $license->payment_id;
				} else {
					$meta_table = edd_software_licensing()->license_meta_db->table_name;
					$initial_id = $license->payment_id;
					$other_ids  = $wpdb->get_col( "SELECT meta_value FROM {$meta_table} WHERE meta_key = '_edd_sl_payment_id' AND license_id = {$object_id}");

					$value = array_merge( array( $initial_id ), $other_ids );
				}
				break;

			default:
				$value = $license->$property;
				break;
		}
		$message = sprintf( __( 'The %s postmeta is <strong>deprecated</strong> since EDD Software Licensing 3.6! Use the EDD_SL_License object to get the %s property, instead.', 'edd_sl' ), $meta_key, $property );

	} else {

		// Developers can hook in here with add_filter( 'eddsl_get_post_meta_backwards_compat-meta_key... in order to
		// Filter their own meta values for backwards compatibility calls to get_post_meta instead of EDD_SL_License::get_meta
		$value = apply_filters( 'eddsl_get_post_meta_backwards_compat-' . $meta_key, $value, $object_id );

	}

	if ( ! empty( $message ) && $show_notice ) {
		// Throw deprecated notice if WP_DEBUG is defined and on
		trigger_error( $message );

		$backtrace = debug_backtrace();
		trigger_error( print_r( $backtrace, 1 ) );
	}

	// Since the payments IDs are a mixture of non-array and array data, don't nest it in an array.
	if ( 'payment_ids' === $property && ! $single ) {
		return $value;
	} else {
		return array( $value );
	}
}
add_filter( 'get_post_metadata', '_eddsl_get_meta_backcompat', 99, 4 );

/**
 * Listen for calls to add_post_meta and see if we need to filter them.
 *
 * @since  3.4
 * @param mixed   $check       Comes in 'null' but if returned not null, WordPress Core will not interact with the postmeta table
 * @param  int    $object_id   The object ID post meta was requested for.
 * @param  string $meta_key    The meta key requested.
 * @param  mixed  $meta_value  The value get_post_meta would return if we don't filter.
 * @param  bool   $unique      Determines if the meta key should be unique or allow multiple entries for the meta_key
 * @return mixed               Returns 'null' if no action should be taken and WordPress core can continue, or non-null to avoid postmeta
 */
function _eddsl_add_meta_backcompat( $check, $object_id, $meta_key, $meta_value, $unique ) {
	global $wpdb;

	$meta_keys = apply_filters( 'eddsl_post_meta_backwards_compat_keys', array_keys( eddsl_legacy_meta_property_map() ) );

	if ( ! in_array( $meta_key, $meta_keys ) ) {
		return $check;
	}

	if ( '_edd_sl_limit' === $meta_key ) {
		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM $wpdb->posts WHERE ID = %d", $object_id ) );
		if ( 'download' === $post_type ) {
			return $check;
		}
	}

	$edd_is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
	$show_notice     = apply_filters( 'eddsl_show_deprecated_notices', ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $edd_is_checkout && ! isset( $_GET['payment-mode'] ) ) );
	$license         = edd_software_licensing()->licenses_db->get( $object_id );

	if ( empty( $license->id ) ) {

		$object_id = eddsl_get_new_license_id_from_legacy_id( $object_id );
		if ( ! empty( $object_id ) ) {
			$license = edd_software_licensing()->licenses_db->get( $object_id );
		} else {
			return $check;
		}

		if ( empty( $license->id ) ) {
			return $check;
		}

	}

	$property = eddsl_get_property_from_legacy_key( $meta_key );
	if ( ! empty( $property ) ) {

		switch ( $property ) {

			case 'payment_ids':
				if ( empty( $license->payment_id ) ) {
					$license->payment_id = $meta_value;
				} else {
					edd_software_licensing()->license_meta_db->add_meta( $license->ID, '_edd_sl_payment_id', $meta_value );
				}

				$check = true;
				break;

			default:
				$license->$property = $meta_value;

				$check = true;
				break;
		}
		$message = sprintf( __( 'The %s postmeta is <strong>deprecated</strong> since EDD Software Licensing 3.6! Use the EDD_SL_License object to set the %s property, instead.', 'edd_sl' ), $meta_key, $property );

	} else {

		// Developers can hook in here with add_filter( 'eddsl_add_post_meta_backwards_compat-meta_key... in order to
		// Filter their own meta values for backwards compatibility calls to get_post_meta instead of EDD_SL_License::add_meta
		$check = apply_filters( 'eddsl_add_post_meta_backwards_compat-' . $meta_key, $check, $object_id );

	}

	if ( ! empty( $message ) && $show_notice ) {
		// Throw deprecated notice if WP_DEBUG is defined and on
		trigger_error( $message );

		$backtrace = debug_backtrace();
		trigger_error( print_r( $backtrace, 1 ) );
	}

	return $check;

}
add_filter( 'add_post_metadata', '_eddsl_add_meta_backcompat', 99, 5 );

/**
 * Listen for calls to update_post_meta and see if we need to filter them.
 *
 * @since  3.4
 * @param mixed   $check       Comes in 'null' but if returned not null, WordPress Core will not interact with the postmeta table
 * @param  int    $object_id   The object ID post meta was requested for.
 * @param  string $meta_key    The meta key requested.
 * @param  mixed  $meta_value  The value get_post_meta would return if we don't filter.
 * @param  mixed  $prev_value  The previous value of the meta
 * @return mixed               Returns 'null' if no action should be taken and WordPress core can continue, or non-null to avoid postmeta
 */
function _eddsl_update_meta_backcompat( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
	global $wpdb;

	$meta_keys = apply_filters( 'eddsl_post_meta_backwards_compat_keys', array_keys( eddsl_legacy_meta_property_map() ) );

	if ( ! in_array( $meta_key, $meta_keys ) ) {
		return $check;
	}

	if ( '_edd_sl_limit' === $meta_key ) {
		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM $wpdb->posts WHERE ID = %d", $object_id ) );
		if ( 'download' === $post_type ) {
			return $check;
		}
	}

	$edd_is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
	$show_notice     = apply_filters( 'eddsl_show_deprecated_notices', ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $edd_is_checkout && ! isset( $_GET['payment-mode'] ) ) );
	$license         = edd_software_licensing()->licenses_db->get( $object_id );

	if ( empty( $license->id ) ) {

		$object_id = eddsl_get_new_license_id_from_legacy_id( $object_id );
		if ( ! empty( $object_id ) ) {
			$license = edd_software_licensing()->licenses_db->get( $object_id );
		} else {
			return $check;
		}

		if ( empty( $license->id ) ) {
			return $check;
		}

	}

	$property = eddsl_get_property_from_legacy_key( $meta_key );
	if ( ! empty( $property ) ) {

		switch ( $property ) {

			case 'payment_ids':
				if ( empty( $license->payment_id ) ) {
					$license->payment_id = $meta_value;
				} else {
					$license->add_meta( '_edd_sl_payment_id', $meta_value );
				}

				$check = true;
				break;

			default:
				$license->$property = $meta_value;

				$check = true;
				break;
		}
		$message = sprintf( __( 'The %s postmeta is <strong>deprecated</strong> since EDD Software Licensing 3.6! Use the EDD_SL_License object to update the %s property, instead.', 'edd_sl' ), $meta_key, $property );

	} else {

		// Developers can hook in here with add_filter( 'eddsl_update_post_meta_backwards_compat-meta_key... in order to
		// Filter their own meta values for backwards compatibility calls to get_post_meta instead of EDD_SL_License::add_meta
		$check = apply_filters( 'eddsl_update_post_meta_backwards_compat-' . $meta_key, $check, $object_id );

	}

	if ( ! empty( $message ) && $show_notice ) {
		// Throw deprecated notice if WP_DEBUG is defined and on
		trigger_error( $message );

		$backtrace = debug_backtrace();
		trigger_error( print_r( $backtrace, 1 ) );
	}

	return $check;

}
add_filter( 'update_post_metadata', '_eddsl_update_meta_backcompat', 99, 5 );

/**
 * Listen for calls to update_post_meta and see if we need to filter them.
 *
 * @since  3.4
 * @param mixed   $check       Comes in 'null' but if returned not null, WordPress Core will not interact with the postmeta table
 * @param  int    $object_id   The object ID post meta was requested for.
 * @param  string $meta_key    The meta key requested.
 * @param  mixed  $meta_value  The value get_post_meta would return if we don't filter.
 * @param  mixed  $delete_all  Delete all records found with meta_key
 * @return mixed               Returns 'null' if no action should be taken and WordPress core can continue, or non-null to avoid postmeta
 */
function _eddsl_delete_meta_backcompat( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
	global $wpdb;

	$meta_keys = apply_filters( 'eddsl_post_meta_backwards_compat_keys', array_keys( eddsl_legacy_meta_property_map() ) );

	if ( ! in_array( $meta_key, $meta_keys ) ) {
		return $check;
	}

	if ( '_edd_sl_limit' === $meta_key ) {
		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM $wpdb->posts WHERE ID = %d", $object_id ) );
		if ( 'download' === $post_type ) {
			return $check;
		}
	}

	$edd_is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
	$show_notice     = apply_filters( 'eddsl_show_deprecated_notices', ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $edd_is_checkout && ! isset( $_GET['payment-mode'] ) ) );
	$license         = edd_software_licensing()->licenses_db->get( $object_id );

	if ( empty( $license->id ) ) {

		$object_id = eddsl_get_new_license_id_from_legacy_id( $object_id );
		if ( ! empty( $object_id ) ) {
			$license = edd_software_licensing()->licenses_db->get( $object_id );
		} else {
			return $check;
		}

		if ( empty( $license->id ) ) {
			return $check;
		}

	}

	$property = eddsl_get_property_from_legacy_key( $meta_key );
	if ( ! empty( $property ) ) {
		$message = sprintf( __( 'The %s postmeta is <strong>deprecated</strong> since EDD Software Licensing 3.6! Use the EDD_SL_License object manage properties.', 'edd_sl' ), $meta_key, $property );
	} else {

		// Developers can hook in here with add_filter( 'eddsl_delete_post_meta_backwards_compat-meta_key... in order to
		// Filter their own meta values for backwards compatibility calls to get_post_meta instead of EDD_SL_License::add_meta
		$check = apply_filters( 'eddsl_delete_post_meta_backwards_compat-' . $meta_key, $check, $object_id );

	}

	if ( ! empty( $message ) && $show_notice ) {
		// Throw deprecated notice if WP_DEBUG is defined and on
		trigger_error( $message );

		$backtrace = debug_backtrace();
		trigger_error( print_r( $backtrace, 1 ) );
	}

	return $check;
}
add_filter( 'delete_post_metadata', '_eddsl_delete_meta_backcompat', 99, 5 );

/**
 * A list of legacy meta_keys to match their properties in the EDD_SL_License object.
 *
 * @since 3.6
 * @return array
 */
function eddsl_legacy_meta_property_map() {
	return array(
		'_edd_sl_key'               => 'license_key', // We are using columns, not properties, and MySQL doesn't allow 'key' as a column.
		'_edd_sl_user_id'           => 'user_id',
		'_edd_sl_download_id'       => 'download_id',
		'_edd_sl_download_price_id' => 'price_id',
		'_edd_sl_sites'             => 'sites',
		'_edd_sl_expiration'        => 'expiration',
		'_edd_sl_is_lifetime'       => 'is_lifetime',
		'_edd_sl_cart_index'        => 'cart_index',
		'_edd_sl_status'            => 'status',
		'_edd_sl_limit'             => 'activation_limit',
		'_edd_sl_payment_id'        => 'payment_ids',
	);
}

/**
 * Given a legacy meta_key, get the property name.
 *
 * @since 3.6
 * @param string $legacy_key
 *
 * @return string
 */
function eddsl_get_property_from_legacy_key( $legacy_key = '' ) {
	if ( empty( $legacy_key ) ) {
		return $legacy_key;
	}

	$property_map = eddsl_legacy_meta_property_map();

	if ( empty( $property_map[ $legacy_key ] ) ) {
		return $legacy_key;
	}

	return sanitize_key( $property_map[ $legacy_key ] );
}

/**
 * A quick global cache of legacy license IDs and their new license IDs
 *
 * @since 3.6
 * @param $legacy_object_id
 *
 * @return mixed|null|string
 */
function eddsl_get_new_license_id_from_legacy_id( $legacy_object_id ) {
	global $wpdb, $legacy_license_ids;

	if ( is_null( $legacy_license_ids ) ) {
		$legacy_license_ids = array();
	}

	if ( array_key_exists( $legacy_object_id, $legacy_license_ids ) ) {
		$new_license_id = $legacy_license_ids[ $legacy_object_id ];
	} else {
		$meta_table     = edd_software_licensing()->license_meta_db->table_name;
		$new_license_id = $wpdb->get_var( "SELECT license_id FROM {$meta_table} WHERE meta_key = '_edd_sl_legacy_id' AND meta_value = {$legacy_object_id}" );
	}

	return $new_license_id;
}

function edd_sl_maybe_disable_backwards_compat() {
	$disable_backwards_compatibility = apply_filters( 'edd_sl_maybe_disable_backwards_compat', false );
	if ( ( defined( 'DOING_SL_MIGRATION' ) && DOING_SL_MIGRATION ) || $disable_backwards_compatibility ) {
		remove_filter( 'add_post_metadata', '_eddsl_add_meta_backcompat', 99, 5 );
		remove_filter( 'get_post_metadata', '_eddsl_get_meta_backcompat', 99, 4 );
		remove_filter( 'update_post_metadata', '_eddsl_update_meta_backcompat', 99, 5 );
		remove_filter( 'delete_post_metadata', '_eddsl_delete_meta_backcompat', 99, 5 );
	}
}
add_action( 'init', 'edd_sl_maybe_disable_backwards_compat' );