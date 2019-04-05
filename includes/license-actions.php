<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Processes the Add Site button
 *
 * @since       2.4
 * @return      void
*/
function edd_sl_process_add_site() {
	if ( ! wp_verify_nonce( $_POST['edd_add_site_nonce'], 'edd_add_site_nonce' ) ) {
		return;
	}

	if ( ! empty( $_POST['license_id'] ) && empty( $_POST['license'] ) ) {
		// In 3.5, we switched from checking for license_id to just license. Fallback check for backwards compatibility
		$_POST['license'] = $_POST['license_id'];
	}

	$license_id  = absint( $_POST['license'] );
	$license     = edd_software_licensing()->get_license( $license_id );
	if ( $license_id !== $license->ID ) {
		return;
	}

	if ( ( is_admin() && ! current_user_can( 'manage_licenses'  ) ) || ( ! is_admin() && $license->user_id != get_current_user_id() ) ) {
		return;
	}

	$site_url = sanitize_text_field( $_POST['site_url'] );

	if ( $license->is_at_limit() && ! current_user_can( 'manage_licenses' ) ) {
		// The license is at its activation limit so stop and show an error
		wp_safe_redirect( add_query_arg( 'edd_sl_error', 'at_limit' ) ); exit;
	}

	if ( $license->add_site( $site_url ) ) {

		$license->status = 'active';

		if ( is_admin() ) {
			$redirect = admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license_id=' . $license->ID );
		} else {
			$redirect = remove_query_arg( array( 'edd_action', 'site_url', 'edd_sl_error', '_wpnonce' ) );
		}

	} else {
		$redirect = add_query_arg( 'edd_sl_error', 'error_adding_site' );
	}

	wp_safe_redirect( $redirect ); exit;
}
add_action( 'edd_insert_site', 'edd_sl_process_add_site' );


/**
 * Processes the Deactivate Site button
 *
 * @since       2.4
 * @return      void
*/
function edd_sl_process_deactivate_site() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_deactivate_site_nonce' ) ) {
		return;
	}

	$license_id = absint( $_GET['license'] );
	$license    = edd_software_licensing()->get_license( $license_id );

	if ( $license_id !== $license->ID ) {
		return;
	}

	if ( ( is_admin() && ! current_user_can( 'manage_licenses' ) ) || ( ! is_admin() && $license->user_id != get_current_user_id() ) ) {
		return;
	}

	$site_url = ! empty( $_GET['site_url'] ) ? urldecode( $_GET['site_url'] ) : false;
	$site_id  = ! empty( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : false;

	if ( empty( $site_url ) && empty( $site_id ) ) {
		wp_die( __( 'Invalid site specified.', 'edd_sl' ) );
	}

	$site = ! empty( $site_id ) ? $site_id : $site_url;

	$license->remove_site( $site );

	$url = remove_query_arg( array( 'edd_action', 'site_url', 'edd_sl_error', '_wpnonce', 'license' ) );
	wp_safe_redirect( $url ); exit;
}
add_action( 'edd_deactivate_site', 'edd_sl_process_deactivate_site' );

/**
 * Removes the filter that disables quantities on checkout for renewals if the row is a renewal, so that the next
 * row can determine if it needs to show or hide the quantity field.
 *
 * @since 3.5.17
 * @param $item
 * @param $key
 *
 * @return void
 */
function edd_sl_remove_quantity_filter( $item, $key ) {
	global $edd_sl_cart_item_quantity_removed;

	if ( $edd_sl_cart_item_quantity_removed ) {
		remove_filter( 'edd_item_quantities_enabled', '__return_false' );
		$edd_sl_cart_item_quantity_removed = false;
	}
}
add_action( 'edd_cart_actions', 'edd_sl_remove_quantity_filter', 10, 2 );

/**
 * When a user is verified after guest purchases, attach the license keys to the user ID.
 *
 * @since 3.6.4
 *
 * @param int   $user_id           The user ID that was verified.
 * @param bool  $update_successful If verification was successful.
 */
function edd_sl_attach_licenses_to_verified_user( $user_id, $update_successful ) {
	if ( empty( $update_successful ) ) {
		return;
	}

	$customer = new EDD_Customer( $user_id, true );
	$args     = array(
		'number'      => -1,
		'customer_id' => $customer->id,
	);
	$licenses = edd_software_licensing()->licenses_db->get_licenses( $args );

	foreach ( $licenses as $license ) {
		/* @var $license EDD_SL_License */

		// If this license is already associated with the user ID do not run an update call.
		if ( (int) $license->user_id === (int) $user_id ) {
			continue;
		}

		$license->update( array( 'user_id' => $user_id ) );
	}
}
add_action( 'edd_post_set_user_to_active', 'edd_sl_attach_licenses_to_verified_user', 10, 2 );
