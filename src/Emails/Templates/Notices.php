<?php

namespace EDD\SoftwareLicensing\Emails\Templates;

defined( 'ABSPATH' ) || exit;

use EDD\Emails\Templates\EmailTemplate;

/**
 * Class Notices
 *
 * @since 3.8.12
 * @package EDD\SoftwareLicensing\Emails\Templates
 */
class Notices extends EmailTemplate {

	/**
	 * The array key for the reminder.
	 *
	 * @since 3.8.12
	 * @var int
	 */
	protected $key;

	/**
	 * Whether this email can be previewed.
	 *
	 * @since 3.8.12
	 * @var bool
	 */
	protected $can_preview = true;

	/**
	 * Whether this email can be tested.
	 *
	 * @since 3.8.12
	 * @var bool
	 */
	protected $can_test = true;

	/**
	 * The email recipient.
	 *
	 * @since 3.8.12
	 * @var string
	 */
	protected $recipient = 'customer';

	/**
	 * The email sender.
	 *
	 * @since 3.8.12
	 * @var string
	 */
	protected $sender = 'software_licensing';

	/**
	 * The email context.
	 *
	 * @since 3.8.12
	 * @var string
	 */
	protected $context = 'license';

	/**
	 * The email meta.
	 *
	 * @since 3.8.12
	 * @var string
	 */
	protected $meta = array(
		'type'   => 'notice',
		'period' => null,
	);

	/**
	 * The email notice.
	 *
	 * @since 3.8.12
	 * @var array
	 */
	private $notice;

	/**
	 * Notices constructor.
	 *
	 * @param int $key
	 */
	public function __construct( $key = 0, $email = null ) {
		parent::__construct( $key, $email );
		$this->key      = str_replace( 'license_', '', $key );
		$this->email_id = 'license_' . $this->key;
		if ( 'new' === $this->key ) {
			$this->can_view = false;
		}
	}

	/**
	 * Name of the template.
	 *
	 * @since 3.8.12
	 * @return string
	 */
	public function get_name() {
		return __( 'License Renewal Notice', 'edd_sl' );
	}

	/**
	 * Description of the email.
	 *
	 * @since 3.8.12
	 * @return string
	 */
	public function get_description() {
		$description = __( 'This is a dynamic notice added by Software Licensing. It will send to customers at the time you select.', 'edd_sl' );
		if ( class_exists( 'EDD_Recurring' ) ) {
			$description .= '<br>';
			$description .= __( 'NOTE: If the product is a Recurring product and the customer\'s subscription is still active, the Software Licensing renewal reminders will not be sent. Instead, the Subscription Reminder Notices registered by Recurring Payments will be used. However, if the customer\'s subscription is cancelled or expired, they will be sent these emails.', 'edd_sl' );
		}

		return $description;
	}

	/**
	 * Gets a custom label for the email context.
	 *
	 * @return string
	 */
	public function get_context_label(): string {
		$periods = edd_sl_get_renewal_notice_periods();
		$label   = $periods[ $this->get_metadata( 'period' ) ];

		return apply_filters( 'edd_sl_get_renewal_notice_period_label', $label, $this->key );
	}

	/**
	 * Gets the email defaults.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'subject' => __( 'Your License Key is About to Expire', 'edd_sl' ),
			'content' => 'Hello {name},

Your license key for {product_name} is about to expire.

If you wish to renew your license, simply click the link below and follow the instructions.

Your license expires on: {expiration}.

Your expiring license key is: {license_key}.

Renew now: {renewal_link}.',
			'status'  => 0,
			'period'  => '+1month',
		);
	}

	/**
	 * The email properties that can be edited.
	 *
	 * @return array
	 */
	protected function get_editable_properties(): array {
		return array(
			'content',
			'subject',
			'status',
		);
	}

	/**
	 * Gets the row actions for the email.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	public function get_row_actions() {
		if ( ! $this->can_view ) {
			return array();
		}

		$row_actions          = parent::get_row_actions();
		$row_actions['trash'] = array(
			'text' => __( 'Delete', 'edd_sl' ),
			'url'  => wp_nonce_url(
				add_query_arg(
					array(
						'edd-action' => 'delete_renewal_notice',
						'notice-id'  => urlencode( $this->key ),
					),
				)
			),
		);

		return $row_actions;
	}

	/**
	 * Gets the preview data for the email.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	protected function get_preview_data() {
		$preview_data = PreviewData::get_notice_data();
		if ( ! empty( $preview_data ) ) {
			$preview_data[] = $this->get_email();
		}

		return $preview_data;
	}

	/* Legacy */
	/**
	 * Determines whether the legacy email data is set.
	 *
	 * @since 3.8.12
	 * @return bool
	 */
	public function has_legacy_data(): bool {
		$notices = get_option( 'edd_sl_renewal_notices', array() );

		return array_key_exists( $this->key, $notices );
	}

	/**
	 * Removes the legacy options from `edd_settings`.
	 *
	 * @since 3.8.12
	 * @return void
	 */
	public function remove_legacy_data() {
		if ( ! $this->get_email()->id ) {
			return;
		}
		$notices = get_option( 'edd_sl_renewal_notices', array() );
		if ( ! array_key_exists( $this->key, $notices ) ) {
			return;
		}

		unset( $notices[ $this->key ] );
		if ( ! empty( $notices ) ) {
			update_option( 'edd_sl_renewal_notices', $notices );
		} else {
			delete_option( 'edd_sl_renewal_notices' );
		}
	}

	/**
	 * Gets a legacy option.
	 *
	 * @since 3.8.12
	 * @param string $key The email template key.
	 * @return mixed
	 */
	protected function get_legacy( $key ) {
		$notice = $this->get_notice();
		if ( 'content' === $key ) {
			$key = 'message';
		}
		if ( 'status' === $key ) {
			return (bool) edd_get_option( 'edd_sl_send_renewal_reminders', false );
		}
		if ( 'period' === $key ) {
			return isset( $notice['send_period'] ) ? $notice['send_period'] : '+1month';
		}

		return isset( $notice[ $key ] ) ? $notice[ $key ] : '';
	}

	/**
	 * Gets the option names for this email.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	protected function get_options(): array {
		return array();
	}

	/**
	 * Gets the renewal notice (legacy).
	 *
	 * @since 3.8.12
	 * @return array
	 */
	private function get_notice() {
		if ( is_null( $this->notice ) ) {
			$this->notice = edd_sl_get_renewal_notice( absint( $this->key ) );
		}

		return $this->notice;
	}
}
