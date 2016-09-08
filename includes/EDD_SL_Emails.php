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
			foreach( $license_keys as $key ) {

				$price_name  = '';
				$download_id = edd_software_licensing()->get_download_id( $key->ID );
				$price_id    = edd_software_licensing()->get_price_id( $key->ID );

				if( $price_id ) {

					$price_name = " - " . edd_get_price_option_name( $download_id, $price_id );

				}

				$keys_output .=  get_the_title( $download_id ) . $price_name . ": " . get_post_meta( $key->ID, '_edd_sl_key', true ) . "\n\r";
			}
		}

		return $keys_output;

	}

	public function send_renewal_reminder( $license_id = 0, $notice_id = 0 ) {

		global $edd_options;

		if( empty( $license_id ) ) {
			return;
		}

		$send    = true;
		$license = get_post( $license_id );

		$send = apply_filters( 'edd_sl_send_renewal_reminder', $send, $license_id, $notice_id );

		if( ! $license || 'edd_license' != $license->post_type || ! $send || ! empty( $license->post_parent ) ) {
			return;
		}

		$customer = false;
		if ( class_exists( 'EDD_Customer' ) ) {

			$payment_id  = get_post_meta( $license->ID, '_edd_sl_payment_id', true );
			$customer_id = edd_get_payment_customer_id( $payment_id );
			$customer    = new EDD_Customer( $customer_id );

		}

		if( empty( $customer->id ) ) {

			// Remove the post title to get just the email
			$title      = $license->post_title;
			$title_pos  = strpos( $title, '-' ) + 1;
			$length     = strlen( $title );
			$email_to   = substr( $title, $title_pos, $length );

		}

		$email_to   = ! empty( $customer->id ) ? $customer->email : $email_to;

		$notice     = edd_sl_get_renewal_notice( $notice_id );
		$message    = ! empty( $notice['message'] ) ? $notice['message'] : __( "Hello {name},\n\nYour license key for {product_name} is about to expire.\n\nIf you wish to renew your license, simply click the link below and follow the instructions.\n\nYour license expires on: {expiration}.\n\nYour expiring license key is: {license_key}.\n\nRenew now: {renewal_link}.", "edd_sl" );
		$message    = $this->filter_reminder_template_tags( $message, $license_id );

		$subject    = ! empty( $notice['subject'] ) ? $notice['subject'] : __( 'Your License Key is About to Expire', 'edd_sl' );
		$subject    = $this->filter_reminder_template_tags( $subject, $license_id );


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
					'post_name'    => 'log-notice-sent-' . $license_id . '-' . md5( time() ),
					'post_type'	   => 'edd_license_log',
					'post_status'  => 'publish'
				 )
			);

			add_post_meta( $log_id, '_edd_sl_log_license_id', $license_id );
			add_post_meta( $log_id, '_edd_sl_renewal_notice_id', (int) $notice_id );

			wp_set_object_terms( $log_id, 'renewal_notice', 'edd_log_type', false );

			add_post_meta( $license_id, sanitize_key( '_edd_sl_renewal_sent_' . $notice['send_period'] ), time() ); // Prevent renewal notices from being sent more than once

		}

	}

	public function filter_reminder_template_tags( $text = '', $license_id = 0 ) {

		$payment_id = get_post_meta( $license_id, '_edd_sl_payment_id', true );
		$user_info  = edd_get_payment_meta_user_info( $payment_id );
		$user_id    = edd_get_payment_user_id( $payment_id );

		// Retrieve the customer name
		if ( $user_id ) {
			$user_data     = get_userdata( $user_id );
			$customer_name = $user_data->display_name;
		} elseif ( isset( $user_info['first_name'] ) ) {
			$customer_name = $user_info['first_name'];
		} else {
			$customer_name = $user_info['email'];
		}

		$license_key  = edd_software_licensing()->get_license_key( $license_id );
		$download_id  = get_post_meta( $license_id, '_edd_sl_download_id', true );
		$product_name = get_the_title( $download_id );
		$expiration   = edd_software_licensing()->get_license_expiration( $license_id );
		$expiration   = date_i18n( get_option( 'date_format' ), $expiration );
		$discount     = edd_sl_get_renewal_discount_percentage( $license_id );
		// $renewal_link is actually just a URL. Not renamed for historical reasons.
		$renewal_link = apply_filters( 'edd_sl_renewal_link', edd_get_checkout_uri( array(
			'edd_license_key' => $license_key,
			'download_id'     => $download_id
		) ) );

		$text = str_replace( '{name}',             $customer_name,  $text );
		$text = str_replace( '{license_key}',      $license_key,    $text );
		$text = str_replace( '{product_name}',     $product_name,   $text );
		$text = str_replace( '{expiration}',       $expiration,     $text );
		if ( ! empty( $discount ) ) {
			$text = str_replace( '{renewal_discount}', $discount . '%', $text );
		};
		$html_link = sprintf( '<a href="%s">%s</a>', $renewal_link, $renewal_link );
		$text = str_replace( '{renewal_link}',     $html_link,   $text );
		$text = str_replace( '{renewal_url}',     $renewal_link,   $text );

		return $text;
	}


}
$edd_sl_emails = new EDD_SL_Emails;
