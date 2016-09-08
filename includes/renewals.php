<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

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
 * Retrieve renewal notices
 *
 * @since 3.0
 * @return array Renewal notice periods
 */
function edd_sl_get_renewal_notice_periods() {
	$periods = array(
		'+1day'    => __( 'One day before expiration', 'edd_sl' ),
		'+2days'   => __( 'Two days before expiration', 'edd_sl' ),
		'+3days'   => __( 'Three days before expiration', 'edd_sl' ),
		'+1week'   => __( 'One week before expiration', 'edd_sl' ),
		'+2weeks'  => __( 'Two weeks before expiration', 'edd_sl' ),
		'+1month'  => __( 'One month before expiration', 'edd_sl' ),
		'+2months' => __( 'Two months before expiration', 'edd_sl' ),
		'+3months' => __( 'Three months before expiration', 'edd_sl' ),
		'expired'  => __( 'At the time of expiration', 'edd_sl' ),
		'-1day'    => __( 'One day after expiration', 'edd_sl' ),
		'-2days'   => __( 'Two days after expiration', 'edd_sl' ),
		'-3days'   => __( 'Three days after expiration', 'edd_sl' ),
		'-1week'   => __( 'One week after expiration', 'edd_sl' ),
		'-2weeks'  => __( 'Two weeks after expiration', 'edd_sl' ),
		'-1month'  => __( 'One month after expiration', 'edd_sl' ),
		'-2months' => __( 'Two months after expiration', 'edd_sl' ),
		'-3months' => __( 'Three months after expiration', 'edd_sl' ),
	);
	return apply_filters( 'edd_sl_get_renewal_notice_periods', $periods );
}

/**
 * Retrieve the renewal label for a notice
 *
 * @since 3.0
 * @return String
 */
function edd_sl_get_renewal_notice_period_label( $notice_id = 0 ) {

	$notice  = edd_sl_get_renewal_notice( $notice_id );
	$periods = edd_sl_get_renewal_notice_periods();
	$label   = $periods[ $notice['send_period'] ];

	return apply_filters( 'edd_sl_get_renewal_notice_period_label', $label, $notice_id );
}

/**
 * Retrieve a renewal notice
 *
 * @since 3.0
 * @return array Renewal notice details
 */
function edd_sl_get_renewal_notice( $notice_id = 0 ) {

	$notices  = edd_sl_get_renewal_notices();

	$defaults = array(
		'subject'      => __( 'Your License Key is About to Expire', 'edd_sl' ),
		'send_period'  => '+1month',
		'message'      => 'Hello {name},

Your license key for {product_name} is about to expire.

If you wish to renew your license, simply click the link below and follow the instructions.

Your license expires on: {expiration}.

Your expiring license key is: {license_key}.

Renew now: {renewal_link}.'
	);

	$notice   = isset( $notices[ $notice_id ] ) ? $notices[ $notice_id ] : $notices[0];

	$notice   = wp_parse_args( $notice, $defaults );

	return apply_filters( 'edd_sl_renewal_notice', $notice, $notice_id );

}

/**
 * Retrieve renewal notice periods
 *
 * @since 3.0
 * @return array Renewal notices defined in settings
 */
function edd_sl_get_renewal_notices() {
	$notices = get_option( 'edd_sl_renewal_notices', array() );

	if( empty( $notices ) ) {

		$message = 'Hello {name},

Your license key for {product_name} is about to expire.

If you wish to renew your license, simply click the link below and follow the instructions.

Your license expires on: {expiration}.

Your expiring license key is: {license_key}.

Renew now: {renewal_link}.';

		$notices[0] = array(
			'send_period' => '+1month',
			'subject'     => __( 'Your License Key is About to Expire', 'edd_sl' ),
			'message'     => $message
		);

	}

	return apply_filters( 'edd_sl_get_renewal_notices', $notices );
}


function edd_sl_renewal_form() {

	if( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$renewal      = EDD()->session->get( 'edd_is_renewal' );
	$renewal_keys = edd_sl_get_renewal_keys();
	$preset_key   = ! empty( $_GET['key'] ) ? esc_html( urldecode( $_GET['key'] ) ) : '';
	$error        = ! empty( $_GET['edd-sl-error'] ) ? sanitize_text_field( $_GET['edd-sl-error'] ) : '';
	$color        = edd_get_option( 'checkout_color', 'blue' );
	$color        = ( $color == 'inherit' ) ? '' : $color;
	$style        = edd_get_option( 'button_style', 'button' );
	ob_start(); ?>
	<form method="post" id="edd_sl_renewal_form">
		<fieldset id="edd_sl_renewal_fields">
			<p id="edd_sl_show_renewal_form_wrap">
				<?php _e( 'Renewing a license key? <a href="#" id="edd_sl_show_renewal_form">Click to renew an existing license</a>', 'edd_sl' ); ?>
			</p>
			<p id="edd-license-key-container-wrap" class="edd-cart-adjustment" style="display:none;">
				<span class="edd-description"><?php _e( 'Enter the license key you wish to renew. Leave blank to purchase a new one.', 'edd_sl' ); ?></span>
				<input class="edd-input required" type="text" name="edd_license_key" autocomplete="off" placeholder="<?php _e( 'Enter your license key', 'edd_sl' ); ?>" id="edd-license-key" value="<?php echo $preset_key; ?>"/>
				<input type="hidden" name="edd_action" value="apply_license_renewal"/>
			</p>
			<p class="edd-sl-renewal-actions" style="display:none">
				<input type="submit" id="edd-add-license-renewal" disabled="disabled" class="edd-submit button <?php echo $color . ' ' . $style; ?>" value="<?php _e( 'Apply License Renewal', 'edd_sl' ); ?>"/>&nbsp;<span><a href="#" id="edd-cancel-license-renewal"><?php _e( 'Cancel', 'edd_sl' ); ?></a></span>
			</p>

			<?php if( ! empty( $renewal ) && ! empty( $renewal_keys ) ) : ?>
				<p id="edd-license-key-container-wrap" class="edd-cart-adjustment">
					<label class="edd-label" for="edd-license-key">
						<?php _e( 'License keys being renewed:', 'edd_sl' ); ?>
					</label>
					<?php foreach( $renewal_keys as $key ) :

					$license_id  = edd_software_licensing()->get_license_by_key( $key );
					$download_id = edd_software_licensing()->get_download_id( $license_id );
					$price_id    = edd_software_licensing()->get_price_id( $license_id );
					?>
						<span class="edd-renewing-key-title"><?php echo get_the_title( $download_id ); ?></span>
						<span class="edd-renewing-key-sep">&nbsp;&ndash;&nbsp;</span>
						<?php if( '' !== $price_id ) : ?>
							<span class="edd-renewing-key-price-option"><?php echo edd_get_price_option_name( $download_id, $price_id ); ?></span>
							<span class="edd-renewing-key-sep">&nbsp;&ndash;&nbsp;</span>
						<?php endif; ?>
						<span class="edd-renewing-key"><?php echo $key; ?></span><br/>
					<?php endforeach; ?>
					<span class="edd-description"><?php _e( 'You may renew multiple license keys at once.', 'edd_sl' ); ?></span>
				</p>
			<?php endif; ?>
		</fieldset>
		<?php if( ! empty( $error ) ) : ?>
			<div class="edd_errors">
				<p class="edd_error"><?php echo urldecode( $_GET['message'] ); ?></p>
			</div>
		<?php endif; ?>
	</form>
	<?php if( ! empty( $renewal ) && ! empty( $renewal_keys ) ) : ?>
	<form method="post" id="edd_sl_cancel_renewal_form">
		<p>
			<input type="hidden" name="edd_action" value="cancel_license_renewal"/>
			<input type="submit" class="edd-submit button" value="<?php _e( 'Cancel License Renewal', 'edd_sl' ); ?>"/>
		</p>
	</form>
	<?php
	endif;
	echo ob_get_clean();
}
add_action( 'edd_before_purchase_form', 'edd_sl_renewal_form', -1 );


function edd_sl_listen_for_renewal_checkout() {

	if( ! function_exists( 'edd_is_checkout' ) || ! edd_is_checkout() ) {
		return;
	}

	if( empty( $_GET['edd_license_key'] ) ) {
		return;
	}

	$added = edd_sl_add_renewal_to_cart( sanitize_text_field( $_GET['edd_license_key'] ), true );

	if( $added && ! is_wp_error( $added ) ) {

		$redirect = edd_get_checkout_uri();

	} else {

		$code     = $added->get_error_code();
		$message  = $added->get_error_message();
		$redirect = add_query_arg( array( 'edd-sl-error' => $code, 'message' => urlencode( $message ) ), edd_get_checkout_uri() );

	}

	wp_safe_redirect( $redirect ); exit;

}
add_action( 'template_redirect', 'edd_sl_listen_for_renewal_checkout' );

function edd_sl_apply_license_renewal( $data ) {

	if( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$license  = ! empty( $data['edd_license_key'] ) ? sanitize_text_field( $data['edd_license_key'] ) : false;
	$added    = edd_sl_add_renewal_to_cart( $license, true );

	if( $added && ! is_wp_error( $added ) ) {

		$redirect = edd_get_checkout_uri();

	} else {

		$code     = $added->get_error_code();
		$message  = $added->get_error_message();
		$redirect = add_query_arg( array( 'edd-sl-error' => $code, 'message' => urlencode( $message ) ), edd_get_checkout_uri() );

	}

	wp_safe_redirect( $redirect ); exit;
}
add_action( 'edd_apply_license_renewal', 'edd_sl_apply_license_renewal' );

/**
 * Adds a license key renewal to the cart
 *
 * @since  3.4
 * @param  integer       $license_id The ID of the license key to add
 * @param  bool          $by_key     Set to true if passing actual license key as $license_id
 * @return bool|WP_Error $success    True if the renewal was added to the cart, WP_Error is not successful
 */
function edd_sl_add_renewal_to_cart( $license_id = 0, $by_key = false ) {

	if( $by_key ) {

		$license_key = $license_id;
		$license_id  = edd_software_licensing()->get_license_by_key( $license_id );

	} else {

		$license_key = edd_software_licensing()->get_license_key( $license_id );

	}

	if( empty( $license_id ) ) {
		return new WP_Error( 'missing_license', __( 'No license ID supplied or invalid key provided', 'edd_sl' ) );
	}

	$license_post = get_post( $license_id );

	if ( ! $license_post ) {
		return new WP_Error( 'missing_license', __( 'No license ID supplied or invalid key provided', 'edd_sl' ) );
	}

	$success     = false;
	$payment_id  = get_post_meta( $license_id, '_edd_sl_payment_id', true );
	$payment     = get_post( $payment_id );
	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$download    = get_post( $download_id );

	if ( 'publish' !== $payment->post_status && 'complete' !== $payment->post_status ) {
		return new WP_Error( 'payment_not_complete', __( 'The purchase record for this license is not marked as complete', 'edd_sl' ) );
	}

	if ( 'publish' !== $license_post->post_status ) {
		return new WP_Error( 'license_disabled', __( 'The supplied license has been disabled and cannot be renewed', 'edd_sl' ) );
	}

	if ( 'publish' !== $download->post_status ) {
		return new WP_Error( 'license_disabled', __( 'The download for this license is not published', 'edd_sl' ) );
	}

	$license_parent = ! empty( $license_post->post_parent ) ? get_post( $license_post->post_parent ) : false ;

	// This license key is part of a bundle, setup the parent
	if ( $license_post->post_parent && ! empty( $license_parent ) ) {

		$parent_license_id  = $license_parent->ID;
		$parent_download_id = edd_software_licensing()->get_download_id( $parent_license_id );
		$parent_license_key = edd_software_licensing()->get_license_key( $parent_license_id );

		if ( ! edd_item_in_cart( $parent_download_id ) && ! edd_has_variable_prices( $download_id ) ) {
			edd_add_to_cart( $parent_download_id );
		}

		$license_id  = $parent_license_id;
		$license_key = edd_software_licensing()->get_license_key( $parent_license_id );
		$license     = $parent_license_key;
		$download_id = $parent_download_id;

	}

	$options = array( 'is_renewal' => true );

	// if product has variable prices, find previous used price id and add it to cart
	if ( edd_has_variable_prices( $download_id ) ) {
		$options['price_id'] = edd_software_licensing()->get_price_id( $license_id );
	}

	if( empty( $download_id ) ) {
		return new WP_Error( 'no_download_id', __( 'There does not appear to be a download ID attached to this license key', 'edd_sl' ) );
	}

	// Make sure it's not already in the cart
	$cart_key = edd_get_item_position_in_cart( $download_id, $options );

	if ( edd_item_in_cart( $download_id, $options ) && false !== $cart_key ) {

		edd_remove_from_cart( $cart_key );

	}

	edd_add_to_cart( $download_id, $options );

	$success = true;

	// Confirm item was added to cart successfully
	if( ! edd_item_in_cart( $download_id, $options ) ) {
		return new WP_Error( 'not_in_cart', __( 'The download for this license is not in the cart or could not be added', 'edd_sl' ) );
	}

	// Get updated cart key
	$cart_key = edd_get_item_position_in_cart( $download_id, $options );

	if( true === $success ) {

		$keys = edd_sl_get_renewal_keys();
		$keys[ $cart_key ] = $license_key;

		EDD()->session->set( 'edd_is_renewal', '1' );
		EDD()->session->set( 'edd_renewal_keys', $keys );

		return true;

	}

	return new WP_Error( 'renewal_error', __( 'Something went wrong while attempting to apply the renewal', 'edd_sl' ) );

}

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
	wp_safe_redirect( $redirect ); exit;

}

/**
 * @since 3.0.2
 * @param $discount float The current discount amount on the item in the cart
 * @param $item array the cart item array
 * @return float
 */
function edd_sl_cart_details_item_discount( $discount, $item ) {

	if( ! edd_sl_renewals_allowed() ) {
		return $discount;
	}

	if( ! EDD()->session->get( 'edd_is_renewal' ) ) {
		return $discount;
	}

	$renewal_keys = edd_sl_get_renewal_keys();

	$key      = false;
	$options  = isset( $item['options'] ) ? $item['options'] : array();
	$cart_key = edd_get_item_position_in_cart( $item['id'], $options );

	if ( isset( $renewal_keys[ $cart_key ] ) ) {
		$key = $renewal_keys[ $cart_key ];
	}

	if( ! $key ) {
		return $discount;
	}

	$discount += edd_sl_get_renewal_discount_amount( $item, $key );

	return $discount;
}
add_filter( 'edd_get_cart_content_details_item_discount_amount', 'edd_sl_cart_details_item_discount', 10, 2 );

/**
 * @since 3.4
 * @param $item array the cart item array
 * @return float
 */
function edd_sl_get_renewal_discount_amount( $item = array(), $license_key = '' ) {

	$discount   = 0.00;
	$license_id = edd_software_licensing()->get_license_by_key( $license_key );

	if( edd_has_variable_prices( $item['id'] ) ) {

		$price_id = (int) edd_software_licensing()->get_price_id( $license_id );
		$prices   = edd_get_variable_prices( $item['id'] );

		if( false !== $price_id && '' !== $price_id && isset( $prices[ $price_id ] ) ) {

			$price = edd_get_price_option_amount( $item['id'], $price_id );

		} else {

			$price = edd_get_lowest_price_option( $item['id'] );

		}

	} else {

		$price = edd_get_download_price( $item['id'] );

	}

	$renewal_discount_percentage = edd_sl_get_renewal_discount_percentage( $license_id );

	if( $renewal_discount_percentage ) {
		$renewal_discount = ( $price * ( $renewal_discount_percentage / 100 ) );

		// todo: fix this. number_format returns a string. we should not perform math on strings.
		$renewal_discount = number_format( $renewal_discount, 2, '.', '' );
		$discount += $renewal_discount;
	}

	return apply_filters( 'edd_sl_get_renewal_discount_amount', $discount, $license_key, $item );
}

function edd_sl_cancel_license_renewal() {

	if( ! edd_sl_renewals_allowed() ) {
		return;
	}

	EDD()->session->set( 'edd_is_renewal', null );
	EDD()->session->set( 'edd_renewal_keys', null );

	wp_redirect( edd_get_checkout_uri() ); exit;
}
add_action( 'edd_cancel_license_renewal', 'edd_sl_cancel_license_renewal' );

/**
 * Removes a license key from the renewal list when the item is removed from the cart
 *
 * @since 3.4
 * @return void
 */
function edd_sl_remove_key_on_remove_from_cart( $cart_key = 0, $item_id = 0 ) {

	$keys = edd_sl_get_renewal_keys();
	if( isset( $keys[ $cart_key ] ) ) {
		unset( $keys[ $cart_key ] );
		EDD()->session->set( 'edd_renewal_keys', array_values( $keys ) );
	}

	if( empty( $keys ) ) {
		EDD()->session->set( 'edd_is_renewal', null );
	}
}
add_action( 'edd_post_remove_from_cart', 'edd_sl_remove_key_on_remove_from_cart', 10, 2 );

function edd_sl_set_renewal_flag( $payment_id, $payment_data ) {

	if( ! edd_sl_renewals_allowed() ) {
		return;
	}

	$renewal      = EDD()->session->get( 'edd_is_renewal' );
	$renewal_keys = edd_sl_get_renewal_keys();

	if( ! empty( $renewal ) && ! empty( $renewal_keys ) ) {

		add_post_meta( $payment_id, '_edd_sl_is_renewal', '1', true );

		foreach( $renewal_keys as $id => $key ) {

			add_post_meta( $payment_id, '_edd_sl_renewal_key', $key );

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
	return (array) array_unique( array_filter( $keys ) );
}

function edd_sl_scheduled_reminders() {

	global $edd_options;

	if( ! isset( $edd_options['edd_sl_send_renewal_reminders'] ) ) {
		return;
	}

	$edd_sl_emails = new EDD_SL_Emails;

	$notices = edd_sl_get_renewal_notices();

	foreach( $notices as $notice_id => $notice ) {

		if( 'expired' == $notice['send_period'] ) {
			continue; // Expired notices are triggered from the set_license_status() method of EDD_Software_Licensing
		}

		$keys = edd_sl_get_expiring_licenses( $notice['send_period'] );

		if( ! $keys ) {
			continue;
		}

		foreach( $keys as $license_id ) {

			if ( ! apply_filters( 'edd_sl_send_scheduled_reminder_for_license', true, $license_id, $notice_id ) ) {
				continue;
			}

			$sent_time = get_post_meta( $license_id, sanitize_key( '_edd_sl_renewal_sent_' . $notice['send_period'] ), true );

			if( $sent_time ) {

				$expire_date = strtotime( $notice['send_period'], $sent_time );

				if( time() < $expire_date ) {

					// The renewal period isn't expired yet so don't send again
					continue;

				}

				delete_post_meta( $license_id, sanitize_key( '_edd_sl_renewal_sent_' . $notice['send_period'] ) );

			}

			$edd_sl_emails->send_renewal_reminder( $license_id, $notice_id );

		}

	}

}
add_action( 'edd_daily_scheduled_events', 'edd_sl_scheduled_reminders' );


function edd_sl_get_expiring_licenses( $period = '+1month' ) {

	$args = array(
		'post_type'              => 'edd_license',
		'nopaging'               => true,
		'fields'                 => 'ids',
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'post_parent'            => 0,
		'meta_query'             => array(
			'relation'           => 'AND',
			array(
				'key'            => '_edd_sl_expiration',
				'value'          => array(
					strtotime( $period . ' midnight' ),
					strtotime( $period . ' midnight' ) + ( DAY_IN_SECONDS - 1 ),
				),
				'compare'        => 'BETWEEN'
			)
		)
	);

	$args  = apply_filters( 'edd_sl_expiring_licenses_args', $args );

	$query = new WP_Query;
	$keys  = $query->query( $args );
	if( ! $keys )
		return false; // no expiring keys found

	return $keys;
}

function edd_sl_check_for_expired_licenses() {

	$args = array(
		'post_type'              => 'edd_license',
		'nopaging'               => true,
		'fields'                 => 'ids',
		'post_parent'            => 0, // Child keys get expired during set_license_Status()
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'meta_query'             => array(
			array(
				'key'            => '_edd_sl_expiration',
				'value'          => current_time( 'timestamp' ),
				'compare'        => '<'
			)
		)
	);

	$query = new WP_Query;
	$keys  = $query->query( $args );
	if( ! $keys ) {
		return; // no expiring keys found
	}

	foreach( $keys as $license_id ) {
		edd_software_licensing()->set_license_status( $license_id, 'expired' );
	}
}
add_action( 'edd_daily_scheduled_events', 'edd_sl_check_for_expired_licenses' );


function edd_sl_get_renewals_by_date( $day = null, $month = null, $year = null, $hour = null  ) {

	$args = apply_filters( 'edd_get_renewals_by_date', array(
		'nopaging'    => true,
		'post_type'   => 'edd_payment',
		'post_status' => array( 'revoked', 'publish' ),
		'meta_key'    => '_edd_sl_is_renewal',
		'meta_value'  => '1',
		'year'        => $year,
		'monthnum'    => $month,
		'fields'      => 'ids'
	), $day, $month, $year );

	if ( ! empty( $day ) ) {
		$args['day'] = $day;
	}

	if ( ! empty( $hour ) ) {
		$args['hour'] = $hour;
	}

	$renewals = get_posts( $args );

	$return   = array();
	$return['earnings'] = 0;
	$return['count']    = count( $renewals );
	if ( $renewals ) {
		foreach ( $renewals as $renewal ) {
			$return['earnings'] += edd_get_payment_amount( $renewal );
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

	if( ! edd_sl_renewals_allowed() ) {
		return;
	}

	if( ! EDD()->session->get( 'edd_is_renewal' ) ) {
		return;
	}

	// bail early if a renewal discount is not set (or set at 0)
	$renewal_discount = edd_sl_get_renewal_discount_percentage();
	if( empty( $renewal_discount ) ) {
		return;
	}

	$cart_items      = edd_get_cart_content_details();
	$renewals        = edd_sl_get_renewal_keys();
	$discount_amount = 0;

	foreach ( $cart_items as $key => $item ) {

		if( ! isset( $renewals[ $key ] ) ) {
			continue;
		}

		$discount_amount += edd_sl_get_renewal_discount_amount( $item, $renewals[ $key ] );
	}
	$discount_amount = edd_currency_filter( edd_format_amount( $discount_amount ) );
?>
	<tr class="edd_cart_footer_row edd_sl_renewal_row">
		<td colspan="3"><?php printf( __( 'License renewal discount: %s - %s', 'edd_sl' ), $renewal_discount . '%', $discount_amount ); ?></td>
	</tr>
<?php
}
add_action( 'edd_cart_items_after', 'edd_sl_cart_items_renewal_row' );

/**
 * Displays Yes/No if a payment was a renewal
 *
 * @since 3.0.2
 * @return void
 */
function edd_sl_payment_details_inner( $payment_id = 0 ) {

	if( ! edd_sl_renewals_allowed() ) {
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
 * Prevents non-published downloads from sending renewal notices
 *
 * @since 3.4
 * @return bool
 */
function edd_sl_exclude_non_published_download_renewals( $send = true, $license_id = 0, $notice_id = 0 ) {

	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$status      = get_post_field( 'post_status', $download_id );

	if( $status && 'publish' !== $status ) {
		$send = false;
	}

	return $send;
}
add_filter( 'edd_sl_send_scheduled_reminder_for_license', 'edd_sl_exclude_non_published_download_renewals', 10, 3 );

/**
 * Get the discount rate for renewals (as a percentage, eg 40%)
 *
 * @since 3.4
 * @return int
 */
function edd_sl_get_renewal_discount_percentage( $license_id = null ) {
	$renewal_discount = edd_get_option( 'edd_sl_renewal_discount', false );

	// make sure this is a percentage, like 40%
	if( $renewal_discount < 1 ) {
		$renewal_discount *= 100;
	}

	return (int) apply_filters( 'edd_sl_renewal_discount_percentage', $renewal_discount, $license_id );
}
