<?php

namespace EDD\SoftwareLicensing\Emails\Types;

defined( 'ABSPATH' ) || exit;

use EDD\Emails\Types\Email;

/**
 * Class Notices
 * This class is currently only used to allow the core email preview to work.
 *
 * @since 3.8.12
 * @package EDD\SoftwareLicensing\Emails\Types
 */
class Notices extends Email {

	/**
	 * The email context.
	 *
	 * @since 3.8.12
	 * @var string
	 */
	protected $context = 'license';

	/**
	 * The email recipient type.
	 *
	 * @since 3.8.12
	 * @var string
	 */
	protected $recipient_type = 'customer';

	/**
	 * License ID (for preview data).
	 * @var int
	 */
	protected $license_id;

	/**
	 * License object (for preview data).
	 * @var string
	 */
	protected $license;

	/**
	 * Notices constructor.
	 *
	 * @param int            $license_id
	 * @param EDD_SL_License $license
	 * @param string         $key
	 */
	public function __construct( $license_id, $license, $email ) {
		$this->license_id = $license_id;
		$this->license    = $license;
		$this->email      = $email;
		$this->id         = $email->email_id;
	}

	/**
	 * Set the email subject.
	 *
	 * @since 3.8.12
	 *
	 * @return void
	 */
	protected function set_subject() {
		$subject       = $this->get_template()->subject;
		$subject       = $this->process_tags( $subject, $this->license_id, $this->license );
		$this->subject = $this->maybe_apply_filters( $subject );
	}

	/**
	 * Set the email subject.
	 *
	 * @since 3.8.12
	 *
	 * @return void
	 */
	protected function set_message() {
		$message       = $this->maybe_apply_autop( $this->get_raw_body_content() );
		$message       = $this->process_tags( $message, $this->license_id, $this->license );
		$this->message = $this->maybe_apply_filters( $message );
	}

	/**
	 * Set the email recipient.
	 *
	 * @since 3.8.12
	 * @return void
	 */
	protected function set_to_email() {
		if ( empty( $this->send_to ) ) {
			$customer      = edd_get_customer( $this->license->customer_id );
			$this->send_to = $customer->email;
		}
	}

	/**
	 * Applies the legacy subject/content filter.
	 *
	 * @since 3.8.12
	 * @param string $content
	 * @return string
	 */
	private function maybe_apply_filters( $content ) {
		if ( false === has_filter( 'edd_sl_renewal_message' ) ) {
			return $content;
		}

		return apply_filters( 'edd_sl_renewal_message', $content, $this->license_id );
	}
}
