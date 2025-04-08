<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns if renewals are enabled
 *
 * @return bool True if enabled, false if not
 */
function edd_sl_renewals_allowed() {
	global $edd_options;

	$ret = isset( $edd_options['edd_sl_renewals'] );

	return apply_filters( 'edd_sl_renewals_allowed', $ret );
}

/**
 * Adds the renewal form to the checkout screen.
 *
 * @since 1.6
 * @return void
 */
function edd_sl_renewal_form() {

	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	edd_get_template_part( 'license', 'renewal-form' );
}
add_action( 'edd_before_purchase_form', 'edd_sl_renewal_form', -1 );

/**
 * Handles the template redirect if renewing a license key.
 *
 * @return void
 */
function edd_sl_listen_for_renewal_checkout() {

	if ( ! function_exists( 'edd_is_checkout' ) || ! edd_is_checkout() ) {
		return;
	}

	if ( empty( $_GET['edd_license_key'] ) ) {
		return;
	}

	$added      = edd_sl_add_renewal_to_cart( sanitize_text_field( $_GET['edd_license_key'] ), true );
	$url        = edd_get_checkout_uri();
	$query_args = false;
	if ( is_wp_error( $added ) ) {
		$query_args = array(
			'edd-sl-error' => urlencode( $added->get_error_code() ),
			'message'      => urlencode( $added->get_error_message() ),
		);
	}
	$redirect = wp_validate_redirect( $url, edd_get_checkout_uri() );

	if ( $query_args ) {
		$redirect = add_query_arg(
			$query_args,
			$redirect
		);
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'template_redirect', 'edd_sl_listen_for_renewal_checkout' );

/**
 * Prevent unmatched emails from checkout out
 *
 * @since 3.5
 * @param array $valid_data
 * @param array $posted
 * @return void
 */
function edd_sl_match_renewal_email( $valid_data, $posted ) {
	if ( ! edd_get_option( 'edd_sl_email_matching', false ) ) {
		return;
	}

	$keys = EDD()->session->get( 'edd_renewal_keys' );

	if ( ! $keys || count( $keys ) == 0 ) {
		return;
	}

	foreach ( $keys as $key ) {
		$license_id = edd_software_licensing()->get_license_by_key( $key );
		$emails     = edd_software_licensing()->get_emails_for_license( $license_id );

		if ( ! in_array( $posted['edd_email'], $emails ) ) {
			edd_set_error( 'email_match', sprintf( __( 'The specified email is not authorized to renew license %s.', 'edd_sl' ), $key ) );
		}
	}
}
add_action( 'edd_checkout_error_checks', 'edd_sl_match_renewal_email', 10, 2 );

/**
 * Maybe adds the license renewal to the cart.
 * On success, redirects to the cart; on failure, redirects to the current page with errors.
 *
 * @since 3.4
 * @param array $data
 * @return void
 */
function edd_sl_apply_license_renewal( $data ) {

	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$license    = ! empty( $data['edd_license_key'] ) ? sanitize_text_field( $data['edd_license_key'] ) : false;
	$added      = edd_sl_add_renewal_to_cart( $license, true );
	$url        = edd_get_checkout_uri();
	$query_args = false;
	if ( ! $added || is_wp_error( $added ) ) {
		// `wp_get_raw_referer` was added in WP 4.5; this check is required as we still support 4.4.
		if ( function_exists( 'wp_get_raw_referer' ) ) {
			$url = wp_get_raw_referer();
		} elseif ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
				$url = wp_unslash( $_REQUEST['_wp_http_referer'] );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$url = wp_unslash( $_SERVER['HTTP_REFERER'] );
		}
		$query_args = array(
			'edd-sl-error' => urlencode( $added->get_error_code() ),
			'message'      => urlencode( $added->get_error_message() ),
		);
	}
	$redirect = wp_validate_redirect( $url, edd_get_checkout_uri() );

	if ( $query_args ) {
		$redirect = add_query_arg(
			$query_args,
			$redirect
		);
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'edd_apply_license_renewal', 'edd_sl_apply_license_renewal' );

/**
 * Adds a license key renewal to the cart
 *
 * @since  3.4
 * @param  integer $license_id The ID of the license key to add
 * @param  bool    $by_key     Set to true if passing actual license key as $license_id
 * @return bool|WP_Error $success    True if the renewal was added to the cart, WP_Error is not successful
 */
function edd_sl_add_renewal_to_cart( $license_id = 0, $by_key = false ) {

	if ( ! edd_sl_renewals_allowed() ) {
		return new WP_Error( 'renewals_disabled', __( 'Renewals are not allowed on this site.', 'edd_sl' ) );
	}

	$license = edd_software_licensing()->get_license( $license_id, $by_key );

	if ( false === $license ) {
		return new WP_Error( 'missing_license', __( 'No license ID supplied or invalid key provided', 'edd_sl' ) );
	}

	if ( 'disabled' === $license->status ) {
		return new WP_Error( 'license_disabled', __( 'The supplied license has been disabled and cannot be renewed', 'edd_sl' ) );
	}

	$success = false;
	$payment = function_exists( 'edd_get_order' ) ? edd_get_order( $license->payment_id ) : edd_get_payment( $license->payment_id );

	if ( ! in_array( $payment->status, array( 'publish', 'complete', 'partially_refunded' ), true ) ) {
		return new WP_Error( 'payment_not_complete', __( 'The purchase record for this license is not marked as complete', 'edd_sl' ) );
	}

	if ( 'publish' !== $license->get_download()->post_status ) {
		return new WP_Error( 'license_disabled', __( 'The download for this license is not published', 'edd_sl' ) );
	}

	$parent_license = ! empty( $license->parent ) ? edd_software_licensing()->get_license( $license->parent ) : false;

	// This license key is part of a bundle, setup the parent.
	if ( $license->parent && false !== $parent_license ) {
		$license = $parent_license;
	}

	$options = array(
		'is_renewal'  => true,
		'license_id'  => $license->ID,
		'license_key' => $license->key,
	);

	// if product has variable prices, find previous used price id and add it to cart.
	if ( $license->get_download()->has_variable_prices() ) {
		$prices = $license->get_download()->prices;

		/**
		 * Detect a missing Price ID configuration.
		 *
		 * A store could swtich a product from single to variable pricing after it's been sold, or, remove a price ID after it's
		 * been sold, so we'll try and detect the closest matching price ID...if we can't we'll use the default.
		 */
		$license_price_id = is_numeric( $license->price_id ) ? intval( $license->price_id ) : false;
		if ( false === $license_price_id || ! array_key_exists( $license_price_id, $prices ) ) {
			$price_id = false;

			// We'll start by looking to see if there is a new price ID that has the same activation limit as this licnese.
			foreach ( $prices as $key => $price ) {
				// If the license's activation limit matches this price, we'll assume this price is the one that should be renewed.
				if ( intval( $price['license_limit'] ) === $license->get_activation_limit() ) {
					$price_id = intval( $key );
					break;
				}
			}

			// If we didn't find a matching price ID by the activation limit, we'll just rely on the default price ID.
			if ( false === $price_id ) {
				$price_id = edd_get_default_variable_price( $license->download_id );
			}
		} else {
			$price_id = intval( $license->price_id );
		}

		$options['price_id'] = $price_id;
	}

	if ( empty( $license->download_id ) ) {
		return new WP_Error( 'no_download_id', __( 'There does not appear to be a download ID attached to this license key', 'edd_sl' ) );
	}

	// Make sure it's not already in the cart.
	$cart_key = edd_sl_get_item_position_in_cart( $license->download_id, $options );

	if ( edd_item_in_cart( $license->download_id, $options ) && false !== $cart_key ) {

		edd_remove_from_cart( $cart_key );

	}

	edd_add_to_cart( $license->download_id, $options );

	$success                          = true;
	$options['_edd_sl_renewal_added'] = true;

	// Confirm item was added to cart successfully.
	if ( ! edd_item_in_cart( $license->download_id, $options ) ) {
		return new WP_Error( 'not_in_cart', __( 'The download for this license is not in the cart or could not be added', 'edd_sl' ) );
	}

	// Get updated cart key.
	$cart_key = edd_sl_get_item_position_in_cart( $license->download_id, $options );

	if ( true === $success ) {

		$keys              = edd_sl_get_renewal_keys();
		$keys[ $cart_key ] = $license->key;

		EDD()->session->set( 'edd_is_renewal', '1' );

		$session_keys = EDD()->session->get( 'edd_renewal_keys' );

		if ( ! $session_keys || ( is_array( $session_keys ) && ! in_array( $license->key, $session_keys ) ) ) {
			EDD()->session->set( 'edd_renewal_keys', $keys );
		}

		do_action( 'edd_sl_renewals_added_to_cart', $keys );
		return true;

	}

	return new WP_Error( 'renewal_error', __( 'Something went wrong while attempting to apply the renewal', 'edd_sl' ) );
}

add_filter( 'edd_item_in_cart', 'edd_sl_is_item_in_cart', 10, 3 );
/**
 * To allow multiple renewals, we need to modify the check for whether an item is in the cart already.
 * Instead of just checking the download ID, we are checking for the license key.
 *
 * @since 3.6.10
 * @param boolean $ret
 * @param integer $download_id
 * @param array   $options
 * @return boolean
 */
function edd_sl_is_item_in_cart( $ret, $download_id, $options ) {
	if ( empty( $options['is_renewal'] ) ) {
		return $ret;
	}
	if ( ! empty( $options['_edd_sl_renewal_added'] ) ) {
		return true;
	}
	$keys = edd_sl_get_renewal_keys();
	if ( empty( $keys ) ) {
		return $ret;
	}
	if ( ! in_array( $options['license_key'], $keys, true ) ) {
		return false;
	}

	return $ret;
}

/**
 * Check for the cart position of the license being renewed.
 * Replaces edd_get_item_position_in_cart as that checks for the download/price ID,
 * and we need to check for the license ID instead.
 *
 * @param integer $download_id
 * @param array   $options
 * @return integer|boolean
 */
function edd_sl_get_item_position_in_cart( $download_id = 0, $options = array() ) {
	$cart = edd_get_cart_contents();

	if ( ! is_array( $cart ) ) {
		return false;
	}

	foreach ( $cart as $position => $item ) {
		if ( $item['id'] != $download_id ) {
			continue;
		}
		if ( ! isset( $item['options']['license_key'] ) ) {
			return $position;
		}
		if ( isset( $options['license_key'] ) && $options['license_key'] === $item['options']['license_key'] ) {
			return $position;
		}
	}

	return false;
}

/**
 * Display renewal details inline in cart
 *
 * @since 3.5
 * @param array $item The cart line item
 * @return void
 */
function edd_sl_renewal_details_cart_item( $item ) {
	global $edd_sl_cart_item_quantity_removed;
	if ( empty( $item['options']['is_renewal'] ) || empty( $item['options']['license_key'] ) ) {
		return;
	}

	$license_key = edd_software_licensing()->get_license( $item['options']['license_id'] );
	if ( ! $license_key ) {
		return;
	}

	$license_key_output = ! is_user_logged_in() || (int) get_current_user_id() !== (int) $license_key->user_id ? edd_sl_mask_license_key( $license_key->key ) : $license_key->key;
	?>
		<div class="edd-sl-renewal-details edd-sl-renewal-details-cart">
				<span class="edd-sl-renewal-label"><?php esc_html_e( 'Renewing', 'edd_sl' ); ?>:</span>
				<span class="edd-sl-renewal-key"><?php echo esc_html( $license_key_output ); ?></span>
		</div>
	<?php
	$edd_sl_cart_item_quantity_removed = true;
	add_filter( 'edd_item_quantities_enabled', '__return_false' );
}
add_action( 'edd_checkout_cart_item_title_after', 'edd_sl_renewal_details_cart_item' );

/**
 * Given an error status for applying a renewal, redirect accordingly
 *
 * @since  2.3.7
 * @param  integer $error_id The error status code
 * @return void              Executes a redirect to the cart with the proper error message displayed
 */
function edd_sl_redirect_on_renewal_error( $error_id ) {

	$error_id = (string) is_numeric( $error_id ) ? $error_id : 1;

	$redirect = add_query_arg( 'edd-sl-error', $error_id, edd_get_checkout_uri() );
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * When discounts are disabled for renewals, ensure that any discount is not valid when a renewal is in the cart.
 *
 * @param bool   $is_valid      Whether the discount is valid.
 * @param int    $discount_id   The discount ID.
 * @param string $discount_code The discount code.
 * @param string $user          User info.
 * @param bool   $set_error     Whether to display/set an error.
 * @return bool
 */
function edd_sl_maybe_invalidate_discount_for_renewals( $is_valid, $discount_id, $discount_code, $user, $set_error = true ) {
	// If the discount is already not valid or the renewal flag is not set, return the current status.
	if ( ! $is_valid || empty( EDD()->session->get( 'edd_is_renewal' ) ) ) {
		return $is_valid;
	}

	if ( edd_get_option( 'edd_sl_disable_discounts', false ) ) {
		$is_valid = false;
		if ( $set_error ) {
			// Override the generic discount error message.
			edd_set_error( 'edd-discount-error', __( 'Discounts are not allowed on license renewals.', 'edd_sl' ) );
		}
	}

	return $is_valid;
}
add_filter( 'edd_is_discount_valid', 'edd_sl_maybe_invalidate_discount_for_renewals', 10, 5 );

/**
 * Remove existing discounts if renewal is set.
 *
 * @since  3.5
 * @since 3.8.12 Updated to run on the `edd_cart_has_discounts` filter.
 * @return void
 */
function edd_sl_remove_discounts_on_renewals( $has_discounts ) {
	if ( edd_get_option( 'edd_sl_disable_discounts', false ) && ! empty( EDD()->session->get( 'edd_is_renewal' ) ) ) {
		edd_unset_all_cart_discounts();
		return false;
	}

	return $has_discounts;
}
add_filter( 'edd_cart_has_discounts', 'edd_sl_remove_discounts_on_renewals' );

/**
 * @since 3.0.2
 * @param $discount float The current discount amount on the item in the cart
 * @param $item array the cart item array
 * @return float
 */
function edd_sl_cart_details_item_discount( $discount, $item ) {

	if ( ! edd_sl_renewals_allowed() ) {
		return $discount;
	}

	if ( ! empty( $item['options']['is_renewal'] ) && isset( $item['options']['license_key'] ) ) {

		$discount += edd_sl_get_renewal_discount_amount( $item, $item['options']['license_key'] );

	}

	return $discount;
}
add_filter( 'edd_get_cart_content_details_item_discount_amount', 'edd_sl_cart_details_item_discount', 10, 2 );

/**
 * @since 3.4
 * @param $item array the cart item array
 * @return float
 */
function edd_sl_get_renewal_discount_amount( $item = array(), $license_key = '' ) {

	$discount = 0.00;
	$license  = edd_software_licensing()->get_license( $license_key, true );

	if ( false === $license ) {
		return $discount;
	}

	if ( ! empty( $item['options']['is_renewal'] ) ) {
		if ( $license->get_download()->has_variable_prices() ) {

			$prices = edd_get_variable_prices( $item['id'] );
			if ( false !== $license->price_id && '' !== $license->price_id && isset( $prices[ $license->price_id ] ) ) {

				$price = edd_get_price_option_amount( $item['id'], $license->price_id );

			} else {

				$price = edd_get_lowest_price_option( $item['id'] );

			}
		} else {

			$price = edd_get_download_price( $item['id'] );

		}

		$renewal_discount_percentage = edd_sl_get_renewal_discount_percentage( $license->ID );

		if ( $renewal_discount_percentage ) {
			$renewal_discount = ( $price * ( $renewal_discount_percentage / 100 ) );

			// todo: fix this. number_format returns a string. we should not perform math on strings.
			$renewal_discount = number_format( $renewal_discount, 2, '.', '' );
			$discount        += $renewal_discount;
		}
	}

	$license_key = ! empty( $license->key ) ? $license->key : '';

	return apply_filters( 'edd_sl_get_renewal_discount_amount', $discount, $license_key, $item );
}

function edd_sl_cancel_license_renewal() {

	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$cart_items = edd_get_cart_contents();

	foreach ( $cart_items as $key => $item ) {

		if ( isset( $cart_items[ $key ]['options']['license_id'] ) ) {

			unset( $cart_items[ $key ]['options']['license_id'] );

		}

		if ( isset( $cart_items[ $key ]['options']['license_key'] ) ) {

			unset( $cart_items[ $key ]['options']['license_key'] );

		}

		if ( isset( $cart_items[ $key ]['options']['is_renewal'] ) ) {

			unset( $cart_items[ $key ]['options']['is_renewal'] );

		}
	}

	// We've removed renewal flags, update cart and session flags
	EDD()->session->set( 'edd_cart', $cart_items );
	EDD()->session->set( 'edd_is_renewal', null );
	EDD()->session->set( 'edd_renewal_keys', null );

	do_action( 'edd_sl_renewals_removed_from_cart' );

	wp_redirect( edd_get_checkout_uri() );
	exit;
}
add_action( 'edd_cancel_license_renewal', 'edd_sl_cancel_license_renewal' );

/**
 * Removes a license key from the renewal list when the item is removed from the cart
 *
 * @since 3.4
 * @return void
 */
function edd_sl_remove_key_on_remove_from_cart( $cart_key = 0, $item_id = 0 ) {

	$cart_items = edd_get_cart_contents();

	$keys = array();

	foreach ( $cart_items as $key => $item ) {

		if ( ! empty( $item['options']['license_key'] ) && ! empty( $item['options']['is_renewal'] ) ) {
			$keys[ $key ] = $item['options']['license_key'];
		}
	}

	EDD()->session->set( 'edd_renewal_keys', array_values( $keys ) );

	if ( empty( $keys ) ) {
		EDD()->session->set( 'edd_is_renewal', null );
	} else {
		$cart_items = edd_get_cart_content_details();

		foreach ( $keys as $id => $key ) {
			$download_id = edd_software_licensing()->get_download_id_by_license( $key );
			unset( $keys[ $id ] );

			foreach ( $cart_items as $cart_key => $item ) {
				if ( $download_id == $item['id'] ) {
					$keys[ $cart_key ] = $key;
				}
			}
		}

		EDD()->session->set( 'edd_renewal_keys', $keys );
	}
}
add_action( 'edd_post_remove_from_cart', 'edd_sl_remove_key_on_remove_from_cart', 10, 2 );

function edd_sl_set_renewal_flag( $payment_id, $payment_data ) {

	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$payment      = new EDD_Payment( $payment_id );
	$is_renewal   = false;
	$renewal_keys = array();

	foreach ( $payment->cart_details as $cart_item ) {

		if ( ! empty( $cart_item['item_number']['options']['is_renewal'] ) && ! empty( $cart_item['item_number']['options']['license_key'] ) ) {
			$renewal_keys[] = sanitize_text_field( $cart_item['item_number']['options']['license_key'] );

			// The payment was not originally flagged as a renewal, but we now have a renewal identified, so let's set the payment as a renewal
			if ( false === $is_renewal ) {
				$is_renewal = true;
			}
		}
	}

	if ( $is_renewal && ! empty( $renewal_keys ) ) {

		$payment->add_meta( '_edd_sl_is_renewal', '1', true );

		foreach ( $renewal_keys as $key ) {

			$payment->add_meta( '_edd_sl_renewal_key', $key );

		}

		EDD()->session->set( 'edd_is_renewal', null );
		EDD()->session->set( 'edd_renewal_keys', null );
	}
}
add_action( 'edd_insert_payment', 'edd_sl_set_renewal_flag', 10, 2 );

/**
 * Retrieve the license keys being renewed
 *
 * @since 3.4
 * @return array
 */
function edd_sl_get_renewal_keys() {
	$keys = (array) EDD()->session->get( 'edd_renewal_keys' );
	$keys = array_unique( array_filter( $keys ) );

	return (array) $keys;
}

/**
 * Return licenses that expire on the day determined by the period provided.
 *
 * This does not get all licenses between now and the period, but on the day, in the past or future for the period.
 *
 * Example:
 * A period of +1month will get all licenses that expire on the date 30 days from now.
 * A period of -1day will get all licenses that expired yesterday
 *
 * If you want all licenses that expired in a range, you can use the EDD_SL_License_DB class with the following arguments
 * 'expiration' => array(
 *     'start' => <unix timestamp of start date>,
 *     'end'   => <unix timestamp of end date>,
 * )
 *
 * @param string $period This is a PHP pseudo-date string used with `strototime` and can be provided values like
 *                       +1month (default), +2weeks, +1day, +1year, and also supports negative values to look backwards.
 *
 * @return array|bool    If found, it will return an array of license IDs, if none are found, it returns false.
 */
function edd_sl_get_expiring_licenses( $period = '+1month' ) {

	$args = array(
		'number'     => - 1,
		'fields'     => 'ids',
		'parent'     => 0,
		'expiration' => array(
			'start' => strtotime( $period . ' midnight', current_time( 'timestamp' ) ),
			'end'   => strtotime( $period . ' midnight', current_time( 'timestamp' ) ) + ( DAY_IN_SECONDS - 1 ),
		),
	);

	$args = apply_filters( 'edd_sl_expiring_licenses_args', $args );
	$keys = edd_software_licensing()->licenses_db->get_licenses( $args );

	if ( ! $keys ) {
		return false; // no expiring keys found
	}

	return $keys;
}

function edd_sl_check_for_expired_licenses() {

	$args = array(
		'number'     => -1,
		'parent'     => 0, // Child keys get expired during set_license_status()
		'expiration' => array(
			'start' => strtotime( '-1 Month' ),
			'end'   => current_time( 'timestamp' ),
		),
		'status'     => array( 'active', 'inactive', 'disabled' ),
	);

	$args     = apply_filters( 'edd_sl_expired_licenses_args', $args );
	$licenses = edd_software_licensing()->licenses_db->get_licenses( $args );

	if ( ! $licenses ) {
		return; // no expiring keys found
	}

	foreach ( $licenses as $license ) {
		$license->status = 'expired';
	}
}
add_action( 'edd_daily_scheduled_events', 'edd_sl_check_for_expired_licenses' );


function edd_sl_get_renewals_by_date( $day = null, $month = null, $year = null, $hour = null ) {

	$args = apply_filters(
		'edd_get_renewals_by_date',
		array(
			'number'   => -1,
			'status'   => array( 'revoked', 'publish', 'complete' ),
			'meta_key' => '_edd_sl_is_renewal',
			'year'     => $year,
			'month'    => $month,
			'fields'   => 'ids',
		),
		$day,
		$month,
		$year
	);

	if ( ! empty( $day ) ) {
		$args['day'] = $day;
	}

	if ( ! empty( $hour ) ) {
		$args['hour'] = $hour;
	}

	$query    = new EDD_Payments_Query( $args );
	$renewals = $query->get_payments();

	$return             = array();
	$return['earnings'] = 0;
	$return['count']    = count( $renewals );
	if ( $renewals ) {
		foreach ( $renewals as $renewal ) {
			$return['earnings'] += edd_get_payment_amount( $renewal->ID );
		}
	}
	return $return;
}

/**
 * Displays the renewal discount row on the cart
 *
 * @since 3.0.2
 * @return void
 */
function edd_sl_cart_items_renewal_row() {

	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	if ( ! EDD()->session->get( 'edd_is_renewal' ) ) {
		return;
	}

	// bail early if a renewal discount is not set (or set at 0)
	$discount_amount = edd_sl_get_renewal_cart_item_discount_amount();
	if ( ! $discount_amount ) {
		return;
	}

	$formatted_discount_amount = edd_currency_filter( edd_format_amount( $discount_amount ) );
	/* translators: %s is the renewal discount amount */
	$message = sprintf( __( 'License renewal discount: %s', 'edd_sl' ), $formatted_discount_amount );

	$checkout_has_blocks = function_exists( '\\EDD\\Blocks\\Checkout\\Functions\\checkout_has_blocks' ) && \EDD\Blocks\Checkout\Functions\checkout_has_blocks();
	if ( $checkout_has_blocks && ! edd_doing_ajax() ) {
		$checkout_has_blocks = has_block( 'edd/checkout' );
	}

	if ( $checkout_has_blocks ) {
		?>
		<div class="edd-blocks-cart__row edd-blocks-cart__row-footer edd_cart_footer_row edd_sl_renewal_row">
			<?php echo wp_kses_post( $message ); ?>
		</div>
		<?php
	} else {
		?>
		<tr class="edd_cart_footer_row edd_sl_renewal_row">
			<td colspan="3"><?php echo wp_kses_post( $message ); ?></td>
		</tr>
		<?php
	}
}
add_action( 'edd_cart_items_after', 'edd_sl_cart_items_renewal_row' );

/**
 * Displays Yes/No if a payment was a renewal
 *
 * @since 3.0.2
 * @return void
 */
function edd_sl_payment_details_inner( $payment_id = 0 ) {

	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$was_renewal = edd_get_payment_meta( $payment_id, '_edd_sl_is_renewal', true );
	?>
	<div class="edd-admin-box-inside">
		<p>
			<strong><?php _e( 'Was renewal:', 'edd_sl' ); ?></strong>&nbsp;
			<span><?php echo $was_renewal ? __( 'Yes', 'edd_sl' ) : __( 'No', 'edd_sl' ); ?></span>
		</p>
	</div>
	<?php
}
add_action( 'edd_view_order_details_update_inner', 'edd_sl_payment_details_inner' );

/**
 * Get the discount rate for renewals (as a percentage, eg 40%)
 *
 * @since 3.4
 * @since 3.6.5 Supports returning 0 when a product has renewal discounts disabled.
 * @return int
 */
function edd_sl_get_renewal_discount_percentage( $license_id = 0, $download_id = 0 ) {

	// Check if the product has an individual discount amount
	if ( $download_id == 0 ) {
		$download_id = edd_software_licensing()->get_download_id( $license_id );
	}

	$renewals_disabled = get_post_meta( $download_id, '_edd_sl_disable_renewal_discount', true );
	if ( ! empty( $renewals_disabled ) ) {
		return 0;
	}

	$renewal_discount = edd_sanitize_amount( get_post_meta( $download_id, '_edd_sl_renewal_discount', true ) );

	if ( $renewal_discount == 0 ) {
		$renewal_discount = edd_get_option( 'edd_sl_renewal_discount', false );
	}

	// make sure this is a percentage, like 40%
	if ( $renewal_discount < 1 ) {
		$renewal_discount *= 100;
	}

	return floatval( apply_filters( 'edd_sl_renewal_discount_percentage', (int) $renewal_discount, $license_id ) );
}

/**
 * Default array of dynamic email strings
 *
 * @return array
 * @since 3.5
 */
function edd_sl_dynamic_email_strings() {
	$strings  = array(
		'{name}'            => __( 'The customer\'s first name', 'edd_sl' ),
		'{fullname}'        => __( 'The customer\'s full name', 'edd_sl' ),
		'{license_key}'     => __( 'The license key that needs renewed', 'edd_sl' ),
		'{product_name}'    => __( 'The name of the product the license key belongs to', 'edd_sl' ),
		'{expiration}'      => __( 'The expiration date for the license key', 'edd_sl' ),
		'{renewal_link}'    => __( 'URL to the renewal checkout page', 'edd_sl' ),
		'{renewal_url}'     => __( 'Raw URL of the renewal checkout page', 'edd_sl' ),
		'{unsubscribe_url}' => __( 'Raw URL to unsubscribe from email notifications for the license', 'edd_sl' ),
	);
	$discount = edd_get_option( 'edd_sl_renewal_discount', false );
	if ( ! empty( $discount ) ) {
		$strings['{renewal_discount}'] = __( 'The renewal discount, including the `%` symbol.', 'edd_sl' );
	}
	return apply_filters( 'edd_sl_dynamic_email_strings', $strings );
}

/**
 * Get the total cart discount from license renewals.
 *
 * @since 3.5
 * @return int $discount_amount The total discount from all license renewals for the current cart.
 */
function edd_sl_get_renewal_cart_item_discount_amount() {

	$cart_items      = edd_get_cart_contents();
	$discount_amount = 0;

	foreach ( $cart_items as $key => $item ) {

		if ( empty( $item['options']['license_key'] ) || empty( $item['options']['license_key'] ) ) {
			continue;
		}

		$discount_amount += edd_sl_get_renewal_discount_amount( $item, $item['options']['license_key'] );
	}

	return $discount_amount;
}


/**
 * Process an opt-out of license renewal emails for a license
 *
 * @since 3.5.11
 */
function edd_sl_process_renewal_email_unsubscribe() {

	if ( empty( $_GET['license_id'] ) ) {
		return;
	}

	if ( empty( $_GET['license_key'] ) ) {
		return;
	}

	$license_id  = absint( $_GET['license_id'] );
	$license_key = sanitize_text_field( $_GET['license_key'] );
	$license     = edd_software_licensing()->get_license( $license_id );

	if ( false === $license ) {
		return;
	}

	if ( strtolower( $license->key ) !== strtolower( $license_key ) ) {
		return;
	}

	$license->update_meta( 'edd_sl_unsubscribed', current_time( 'timestamp' ) );

	do_action( 'edd_sl_license_unsubscribed', $license );

	wp_die( __( 'You have been successfully unsubscribed from renewal notification emails for this license key.', 'edd_sl' ), __( 'Unsubscribed', 'edd_sl' ), 200 );
}
add_action( 'edd_license_unsubscribe', 'edd_sl_process_renewal_email_unsubscribe' );

/**
 * When the cart is emptied, clear out any session data contianing renewals.
 *
 * @since 2.5.19
 * @return void
 */
function edd_sl_clear_cart_renewal() {
	$contains_renewal = EDD()->session->get( 'edd_is_renewal' );

	if ( ! empty( $contains_renewal ) ) {
		EDD()->session->set( 'edd_is_renewal', null );
		EDD()->session->set( 'edd_renewal_keys', null );
	}
}
add_action( 'edd_empty_cart', 'edd_sl_clear_cart_renewal' );

/**
 * Choose the correct action hook to use for refunds in EDD 3.0/2.x
 *
 * @since 3.7.2
 */
$hook = 'edd_post_refund_payment';
if ( function_exists( 'edd_get_order_items' ) ) {
	$hook = 'edd_refund_order';
}
add_action( $hook, 'edd_sl_rollback_expiration_on_renewal_refund' );

/**
 * Rolls a license expiration date back when refunding a renewal payment.
 *
 * @since 3.6
 *
 * @param EDD_Payment|int $payment Payment object (2.x) or order ID (3.0).
 */
function edd_sl_rollback_expiration_on_renewal_refund( $payment ) {

	$order_id         = ! empty( $payment->ID ) ? $payment->ID : $payment;
	$order_is_renewal = edd_get_payment_meta( $order_id, '_edd_sl_is_renewal', true );
	if ( ! $order_is_renewal ) {
		return;
	}

	// EDD 3.0
	if ( function_exists( 'edd_get_order_items' ) && is_numeric( $payment ) ) {
		$items = edd_get_order_items(
			array(
				'order_id' => $order_id,
			)
		);
		if ( ! $items ) {
			return;
		}
		foreach ( $items as $item ) {
			// If the item itself has not been refunded, don't tinker with the license.
			if ( 'refunded' !== $item->status ) {
				continue;
			}
			$is_renewal = edd_get_order_item_meta( $item->id, '_option_is_renewal', true );
			if ( ! $is_renewal ) {
				continue;
			}
			$license_id = edd_get_order_item_meta( $item->id, '_option_license_id', true );
			$license    = edd_software_licensing()->get_license( (int) $license_id );
			if ( false !== $license && 'lifetime' !== $license->expiration ) {
				$license->expiration = strtotime( '-' . $license->license_length(), $license->expiration );
			}
		}
	} else {

		foreach ( $payment->cart_details as $cart_item ) {
			if ( is_array( $cart_item['item_number']['options'] ) ) {

				// See if the `is_renewal` key exists and if the license_id exists, since these were added later, they may not on some legacy payments.
				if ( array_key_exists( 'is_renewal', $cart_item['item_number']['options'] ) && ! empty( $cart_item['item_number']['options']['license_id'] ) ) {

					$license = edd_software_licensing()->get_license( (int) $cart_item['item_number']['options']['license_id'] );

					if ( false !== $license && 'lifetime' !== $license->expiration ) {
						$license->expiration = strtotime( '-' . $license->license_length(), $license->expiration );
					}
				}
			}
		}
	}
}
