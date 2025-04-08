<?php

class EDD_SL_Emails {

	/**
	 * @var bool If true, then exceptions will be thrown on failures.
	 * @since 3.8.3
	 */
	protected $throw_exceptions = false;

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'display_renewal_email_preview' ) );
	}

	/**
	 * Enables exceptions on errors.
	 *
	 * @since 3.8.3
	 *
	 * @return $this
	 */
	public function with_exceptions() {
		$this->throw_exceptions = true;

		return $this;
	}

	/**
	 * Sends a renewal reminder email to the customer.
	 *
	 * @since <unknown>
	 *
	 * @param int $license_id The license ID.
	 * @param int $notice_id  The notice ID.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function send_renewal_reminder( $license_id = 0, $notice_id = 0 ) {

		if ( empty( $license_id ) ) {
			if ( $this->throw_exceptions ) {
				throw new \Exception( __( 'Reminder not sent: no license key provided.', 'edd_sl' ) );
			}

			return false;
		}

		$notice = edd_software_licensing()->notices->get_notice( $notice_id );
		if ( ! $notice || ! edd_software_licensing()->notices->is_enabled( $notice ) ) {
			if ( $this->throw_exceptions ) {
				throw new \Exception( esc_html__( 'Reminder not sent: renewal reminders are not enabled.', 'edd_sl' ) );
			}

			return false;
		}

		$send    = true;
		$license = edd_software_licensing()->get_license( $license_id );
		if ( false === $license ) {
			return false;
		}

		$exception_message = __( 'Reminder not sent: unexpected sending failure.', 'edd_sl' );

		if ( $license->is_lifetime ) {
			$exception_message = __( 'License never expires.', 'edd_sl' );
			$send              = false;
		}

		if ( $this->is_unsubscribed( $license ) ) {
			$exception_message = __( 'Reminder not sent: customer is not subscribed to reminder emails.', 'edd_sl' );
			$send              = false;
		}

		if ( 'disabled' === $license->status ) {
			$exception_message = __( 'Reminder not sent: this license key is disabled.', 'edd_sl' );
			$send              = false;
		}

		$send = apply_filters( 'edd_sl_send_renewal_reminder', $send, $license->ID, $notice_id );

		if ( ! $license || ! $send || ! empty( $license->parent ) ) {
			if ( $this->throw_exceptions ) {
				throw new \Exception( $exception_message );
			}

			return false;
		}

		if ( edd_sl_are_email_templates_registered() ) {
			$email_object = edd_get_email( $notice_id );
			$email        = EDD\Emails\Registry::get( $notice_id, array( $license->ID, $license, $email_object ) );
			$sent         = $email->send();
			if ( $sent ) {
				$period = edd_get_email_meta( $email->id, 'period', true );
				$license->update_meta( sanitize_key( '_edd_sl_renewal_sent_' . $period ), time() ); // Prevent renewal notices from being sent more than once
			}
		} else {

			$customer = edd_get_customer( $license->customer_id );
			if ( empty( $customer->email ) ) {
				if ( $this->throw_exceptions ) {
					throw new \Exception( esc_html__( 'Reminder not sent: no customer email address.', 'edd_sl' ) );
				}

				return false;
			}

			$message = ! empty( $notice['message'] ) ? $notice['message'] : __( "Hello {name},\n\nYour license key for {product_name} is about to expire.\n\nIf you wish to renew your license, simply click the link below and follow the instructions.\n\nYour license expires on: {expiration}.\n\nYour expiring license key is: {license_key}.\n\nRenew now: {renewal_link}.", 'edd_sl' );
			$message = $this->filter_reminder_template_tags( $message, $license->ID, $license );
			$subject = ! empty( $notice['subject'] ) ? $notice['subject'] : __( 'Your License Key is About to Expire', 'edd_sl' );
			$subject = $this->filter_reminder_template_tags( $subject, $license->ID, $license );
			$message = stripslashes( $message );
			$subject = stripslashes( $subject );
			$period  = $notice['send_period'];

			$sent = EDD()->emails->send( $customer->email, $subject, $message );
		}

		if ( ! $sent && $this->throw_exceptions ) {
			throw new \Exception( esc_html__( 'Reminder not sent: email failed to send.', 'edd_sl' ) );
		}

		if ( $sent ) {
			$log_id = $license->add_log( __( 'LOG - Renewal Notice Sent', 'edd_sl' ), __( 'Sent via the send_renewal_reminder method.', 'edd_sl' ), 'renewal_notice' );
			add_post_meta( $log_id, '_edd_sl_renewal_notice_id', $notice_id );
			$license->update_meta( sanitize_key( '_edd_sl_renewal_sent_' . $period ), time() ); // Prevent renewal notices from being sent more than once
		}

		return $sent;
	}

	public function filter_reminder_template_tags( $text = '', $license_id = 0, $license = null ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}
		if ( false === $license ) {
			return $text;
		}

		// If the email templates are registered, then we can use the edd_do_email_tags() function.
		if ( edd_sl_are_email_templates_registered() ) {
			return apply_filters( 'edd_sl_renewal_message', edd_do_email_tags( $text, $license->ID, $license, 'license' ), $license->ID );
		}

		$expiration = date_i18n( get_option( 'date_format' ), $license->expiration );
		$discount   = edd_sl_get_renewal_discount_percentage( $license->ID );

		// $renewal_link is actually just a URL. Not renamed for historical reasons.
		$renewal_link = apply_filters( 'edd_sl_renewal_link', $license->get_renewal_url() );
		$current_time = current_time( 'timestamp' );
		$time_diff    = human_time_diff( $license->expiration, $current_time );

		if ( $license->expiration < $current_time ) {
			$time_diff = sprintf( __( 'expired %s ago', 'edd_sl' ), $time_diff );
		} else {
			$time_diff = sprintf( __( 'expires in %s', 'edd_sl' ), $time_diff );
		}

		$text = str_replace( '{name}', edd_email_tag_first_name( $license->payment_id ), $text );
		$text = str_replace( '{fullname}', edd_email_tag_fullname( $license->payment_id ), $text );
		$text = str_replace( '{license_key}', $license->key, $text );
		$text = str_replace( '{product_name}', $license->get_download()->get_name(), $text );
		$text = str_replace( '{expiration}', $expiration, $text );
		$text = str_replace( '{expiration_time}', $time_diff, $text );
		if ( ! empty( $discount ) ) {
			$text = str_replace( '{renewal_discount}', $discount . '%', $text );
		}
		$html_link = sprintf( '<a href="%s">%s</a>', $renewal_link, $renewal_link );
		$text      = str_replace( '{renewal_link}', $html_link, $text );
		$text      = str_replace( '{renewal_url}', $renewal_link, $text );
		$text      = str_replace( '{unsubscribe_url}', $license->get_unsubscribe_url(), $text );

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

	/**
	 * Renders a preview for a renewal email.
	 *
	 * @since 3.7
	 * @return void
	 */
	public function display_renewal_email_preview() {

		if ( empty( $_GET['edd-action'] ) || ! isset( $_GET['notice-id'] ) || ! is_numeric( $_GET['notice-id'] ) ) {
			return;
		}

		if ( 'edd_sl_preview_notice' !== $_GET['edd-action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		$data = edd_sl_get_renewal_notice( (int) $_GET['notice-id'] );
		if ( empty( $data['message'] ) ) {
			wp_die( esc_html__( 'The email message has no content.', 'edd_sl' ) );
		}

		EDD()->emails->heading = $this->preview_reminder_template_tags( $data['subject'] );

		echo EDD()->emails->build_email( $this->preview_reminder_template_tags( $data['message'], $data['send_period'] ) );
		exit;
	}

	/**
	 * Replaces email template tags with license data for the preview email.
	 *
	 * @since 3.7
	 *
	 * @param string  $text        The email subject/body text.
	 * @param string  $send_period The timeframe for sending the email. Default is one month before expiration.
	 * @return string
	 */
	private function preview_reminder_template_tags( $text = '', $send_period = '+30 days' ) {
		if ( 'expired' === $send_period ) {
			$send_period = 'today';
		}
		$expiration   = strtotime( $send_period );
		$discount     = edd_get_option( 'edd_sl_renewal_discount', 0 );
		$site_link    = home_url();
		$current_time = current_time( 'timestamp' );
		$time_diff    = human_time_diff( $expiration, $current_time );

		if ( $expiration < $current_time ) {
			/* translators: how long ago the license expired. */
			$time_diff = sprintf( __( 'expired %s ago', 'edd_sl' ), $time_diff );
		} else {
			/* translators: how long until the license expires. */
			$time_diff = sprintf( __( 'expires in %s', 'edd_sl' ), $time_diff );
		}

		$text = edd_email_preview_template_tags( $text );
		$text = str_replace( '{license_key}', __( 'Sample License Key', 'edd_sl' ), $text );
		$text = str_replace( '{product_name}', __( 'Sample Product Name', 'edd_sl' ), $text );
		$text = str_replace( '{expiration}', date_i18n( get_option( 'date_format' ), $expiration ), $text );
		$text = str_replace( '{expiration_time}', $time_diff, $text );
		if ( ! empty( $discount ) ) {
			$text = str_replace( '{renewal_discount}', $discount . '%', $text );
		}
		$html_link = sprintf( '<a>%s</a>', $site_link );
		$text      = str_replace( '{renewal_link}', $html_link, $text );
		$text      = str_replace( '{renewal_url}', $site_link, $text );
		$text      = str_replace( '{unsubscribe_url}', $site_link, $text );

		/**
		 * Filters the renewal message text.
		 *
		 * @param string $text       The message text.
		 * @param int $license_id The license ID.
		 */
		return apply_filters( 'edd_sl_renewal_message', $text, 0 );
	}

	/**
	 * Add {license_keys} Email Tag.
	 *
	 * @since 2.4
	 * @deprecated 3.8.12
	 * @access public
	 */
	public function add_email_tag() {
		$tags = new \EDD\SoftwareLicensing\Emails\Tags();
		$tags->add_email_tag();
	}

	/**
	 * Get the license keys for a purchase.
	 *
	 * @since unknown
	 * @deprecated 3.8.12
	 * @param int $payment_id The order ID.
	 * @return string
	 */
	public function licenses_tag( $payment_id = 0 ) {
		$tags = new \EDD\SoftwareLicensing\Emails\Tags();

		return $tags->licenses_tag( $payment_id );
	}
}
$edd_sl_emails = new EDD_SL_Emails();
