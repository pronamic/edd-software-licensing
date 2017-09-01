<?php

class EDD_SL_Emails {

	function __construct() {

		add_action( 'edd_add_email_tags', array( $this, 'add_email_tag' ), 100 );

	}

	public function add_email_tag() {

		edd_add_email_tag( 'license_keys', __( 'Show all purchased licenses', 'edd_sl' ), array( $this, 'licenses_tag' ) );

	}

	public function licenses_tag( $payment_id = 0 ) {

		$keys_output  = '';
		$license_keys = edd_software_licensing()->get_licenses_of_purchase( $payment_id );

		if( $license_keys ) {
			foreach( $license_keys as $license ) {

				$price_name  = '';

				if( $license->price_id ) {

					$price_name = " - " . edd_get_price_option_name( $license->download_id, $license->price_id );

				}

				$keys_output .=  $license->download->get_name() . $price_name . ": " . $license->key . "\n\r";
			}
		}

		return $keys_output;

	}

	public function send_renewal_reminder( $license_id = 0, $notice_id = 0 ) {

		if( empty( $license_id ) ) {
			return false;
		}

		$send    = true;
		$license = edd_software_licensing()->get_license( $license_id );

		if( $license->is_lifetime ) {
			$send = false;
		}

		if( $this->is_unsubscribed( $license ) ) {
			$send = false;
		}

		$send = apply_filters( 'edd_sl_send_renewal_reminder', $send, $license->ID, $notice_id );

		if( ! $license || ! $send || ! empty( $license->parent ) ) {
			return false;
		}

		$customer = false;
		if ( class_exists( 'EDD_Customer' ) ) {
			$customer = new EDD_Customer( $license->customer_id );
		}

		if( empty( $customer->id ) ) {
			// Remove the post title to get just the email
			$title      = $license->get_name();
			$title_pos  = strpos( $title, '-' ) + 1;
			$length     = strlen( $title );
			$email_to   = substr( $title, $title_pos, $length );
		}

		$email_to   = ! empty( $customer->id ) ? $customer->email : $email_to;

		$notice     = edd_sl_get_renewal_notice( $notice_id );
		$message    = ! empty( $notice['message'] ) ? $notice['message'] : __( "Hello {name},\n\nYour license key for {product_name} is about to expire.\n\nIf you wish to renew your license, simply click the link below and follow the instructions.\n\nYour license expires on: {expiration}.\n\nYour expiring license key is: {license_key}.\n\nRenew now: {renewal_link}.", "edd_sl" );
		$message    = $this->filter_reminder_template_tags( $message, $license->ID );

		$subject    = ! empty( $notice['subject'] ) ? $notice['subject'] : __( 'Your License Key is About to Expire', 'edd_sl' );
		$subject    = $this->filter_reminder_template_tags( $subject, $license->ID );


		$message = stripslashes( $message );
		$subject = stripslashes( $subject );

		if( class_exists( 'EDD_Emails' ) ) {

			$sent = EDD()->emails->send( $email_to, $subject, $message );

		} else {

			$from_name  = get_bloginfo( 'name' );
			$from_email = get_bloginfo( 'admin_email' );
			$headers    = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
			$headers   .= "Reply-To: ". $from_email . "\r\n";

			$sent = wp_mail( $email_to, $subject, $message, $headers );

		}

		if( $sent ) {

			$log_id = wp_insert_post(
				array(
					'post_title'   => __( 'LOG - Renewal Notice Sent', 'edd_sl' ),
					'post_name'    => 'log-notice-sent-' . $license->ID . '-' . md5( current_time( 'timestamp' ) ),
					'post_type'	   => 'edd_license_log',
					'post_status'  => 'publish'
				 )
			);

			add_post_meta( $log_id, '_edd_sl_log_license_id', $license->ID );
			add_post_meta( $log_id, '_edd_sl_renewal_notice_id', $notice_id );

			wp_set_object_terms( $log_id, 'renewal_notice', 'edd_log_type', false );

			$license->update_meta( sanitize_key( '_edd_sl_renewal_sent_' . $notice['send_period'] ), current_time( 'timestamp' ) ); // Prevent renewal notices from being sent more than once

		}

		return $sent;
	}

	public function filter_reminder_template_tags( $text = '', $license_id = 0 ) {
		$license = edd_software_licensing()->get_license( $license_id );

		// Retrieve the customer name
		if ( $license->user_id ) {
			$user_data     = get_userdata( $license->user_id );
			$customer_name = $user_data->display_name;
		} else {
			$user_info  = edd_get_payment_meta_user_info( $license->payment_id );
			if ( isset( $user_info[ 'first_name' ] ) ) {
				$customer_name = $user_info[ 'first_name' ];
			} else {
				$customer_name = $user_info[ 'email' ];
			}
		}

		$expiration      = date_i18n( get_option( 'date_format' ), $license->expiration );
		$discount        = edd_sl_get_renewal_discount_percentage( $license_id );

		// $renewal_link is actually just a URL. Not renamed for historical reasons.
		$renewal_link    = apply_filters( 'edd_sl_renewal_link', $license->get_renewal_url() );
		$current_time    = current_time( 'timestamp' );
		$time_diff       = human_time_diff( $license->expiration, $current_time );

		if( $license->expiration < $current_time ) {
			$time_diff = sprintf( __( 'expired %s ago', 'edd_sl' ), $time_diff );
		} else {
			$time_diff = sprintf( __( 'expires in %s', 'edd_sl' ), $time_diff );
		}

		$text = str_replace( '{name}',             $customer_name,  $text );
		$text = str_replace( '{license_key}',      $license->key,    $text );
		$text = str_replace( '{product_name}',     $license->download->get_name(),   $text );
		$text = str_replace( '{expiration}',       $expiration,     $text );
		$text = str_replace( '{expiration_time}',  $time_diff,      $text );
		if ( ! empty( $discount ) ) {
			$text = str_replace( '{renewal_discount}', $discount . '%', $text );
		};
		$html_link = sprintf( '<a href="%s">%s</a>', $renewal_link, $renewal_link );
		$text = str_replace( '{renewal_link}',       $html_link, $text );
		$text = str_replace( '{renewal_url}',        $renewal_link, $text );
		$text = str_replace( '{unsubscribe_url}',    $license->get_unsubscribe_url(), $text );

		return apply_filters( 'edd_sl_renewal_message', $text, $license->ID );
	}

	/**
	 * Determine if email notifications for this license are disabled
	 *
	 * @since  3.5.11
	 *
	 * @param  object $license EDD_SL_License object
	 *
	 * @return bool
	 */
	public function is_unsubscribed( EDD_SL_License $license ) {
		return (bool) $license->get_meta( 'edd_sl_unsubscribed', true );
	}

}
$edd_sl_emails = new EDD_SL_Emails;
