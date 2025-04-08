<?php

/**
 * Sends all enabled renewal notices on a daily basis.
 *
 * @return void
 */
function edd_sl_scheduled_reminders() {

	$edd_sl_emails = new EDD_SL_Emails();

	$notices = edd_software_licensing()->notices->get_notices();

	foreach ( $notices as $notice ) {

		if ( ! edd_software_licensing()->notices->is_enabled( $notice ) ) {
			continue;
		}

		$send_period = edd_software_licensing()->notices->get_notice_period( $notice );
		// Expired notices are triggered from the set_license_status() method of EDD_Software_Licensing.
		if ( 'expired' === $send_period ) {
			continue;
		}

		$keys = edd_sl_get_expiring_licenses( $send_period );
		if ( ! $keys ) {
			continue;
		}

		$notice_id = edd_software_licensing()->notices->get_notice_id( $notice );
		foreach ( $keys as $license_id ) {

			if ( ! apply_filters( 'edd_sl_send_scheduled_reminder_for_license', true, $license_id, $notice_id ) ) {
				continue;
			}

			$license = edd_software_licensing()->get_license( $license_id );

			if ( false === $license ) {
				continue;
			}

			// Sanity check to ensure we don't send renewal notices to people with lifetime licenses.
			if ( $license->is_lifetime ) {
				continue;
			}

			$sent_time = $license->get_meta( sanitize_key( '_edd_sl_renewal_sent_' . $send_period ) );
			if ( $sent_time ) {

				$expire_date = strtotime( $send_period, $sent_time );

				if ( current_time( 'timestamp' ) < $expire_date ) {

					// The renewal period isn't expired yet so don't send again.
					continue;

				}

				$license->delete_meta( sanitize_key( '_edd_sl_renewal_sent_' . $send_period ) );

			}

			$edd_sl_emails->send_renewal_reminder( $license->ID, $notice_id );
		}
	}
}
add_action( 'edd_daily_scheduled_events', 'edd_sl_scheduled_reminders' );

/**
 * Prevents non-published downloads from sending renewal notices
 *
 * @since 3.4
 * @return bool
 */
function edd_sl_exclude_non_published_download_renewals( $send = true, $license_id = 0, $notice_id = 0 ) {

	$license = edd_software_licensing()->get_license( $license_id );

	// If we failed to find a license, don't send anything.
	if ( false === $license ) {
		return false;
	}

	$status = get_post_field( 'post_status', $license->download_id );

	if ( $status && 'publish' !== $status ) {
		$send = false;
	}

	return $send;
}
add_filter( 'edd_sl_send_scheduled_reminder_for_license', 'edd_sl_exclude_non_published_download_renewals', 10, 3 );

/**
 * Controls display of dynamic strings on renewal notice form
 *
 * @since 3.5
 */
function edd_sl_output_dynamic_email_strings() {
	if ( edd_sl_are_email_templates_registered() ) {
		return;
	}
	echo '<ul>';
	foreach ( edd_sl_dynamic_email_strings() as $string => $label ) {
		echo '<li>' . esc_html( $string ) . ' ' . esc_html( $label ) . '</li>';
	}
	echo '</ul>';
}
add_action( 'edd_sl_after_renewal_notice_form', 'edd_sl_output_dynamic_email_strings' );

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
		'subject'     => __( 'Your License Key is About to Expire', 'edd_sl' ),
		'send_period' => '+1month',
		'message'     => 'Hello {name},

Your license key for {product_name} is about to expire.

If you wish to renew your license, simply click the link below and follow the instructions.

Your license expires on: {expiration}.

Your expiring license key is: {license_key}.

Renew now: {renewal_link}.',
		'enabled'     => true,
	);

	$notice = isset( $notices[ $notice_id ] ) ? $notices[ $notice_id ] : $defaults;
	$notice = wp_parse_args( $notice, $defaults );

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

	if ( empty( $notices ) && ! edd_sl_are_email_templates_registered() ) {

		$message = 'Hello {name},

Your license key for {product_name} is about to expire.

If you wish to renew your license, simply click the link below and follow the instructions.

Your license expires on: {expiration}.

Your expiring license key is: {license_key}.

Renew now: {renewal_link}.';

		$notices[0] = array(
			'send_period' => '+1month',
			'subject'     => __( 'Your License Key is About to Expire', 'edd_sl' ),
			'message'     => $message,
		);
	}

	return apply_filters( 'edd_sl_get_renewal_notices', $notices );
}
