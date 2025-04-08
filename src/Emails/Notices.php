<?php

namespace EDD\SoftwareLicensing\Emails;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Notices {

	/**
	 * The notices.
	 *
	 * @since 3.8.12
	 * @var array
	 */
	private $notices;

	/**
	 * Gets the notices.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	public function get_notices() {
		if ( ! isset( $this->notices ) ) {
			// Get notices from the database.
			$notices = $this->get_rows();
			$options = $this->get_legacy_notices();
			// Get notices saved as options.
			if ( $options ) {
				$notice_email_ids = wp_list_pluck( $notices, 'email_id' );
				foreach ( $options as &$notice ) {
					if ( $this->has_email_management() ) {
						if ( ! in_array( "license_{$notice['email_id']}", $notice_email_ids, true ) ) {
							$notice['email_id'] = "license_{$notice['email_id']}";
							$notices[]          = $notice;
						}
					} else {
						$notices[ $notice['email_id'] ] = $notice;
					}
				}
			}
			$this->notices = $notices;
		}

		return $this->notices;
	}

	/**
	 * Gets the registered notices.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	public function get_registered_notices() {
		$notices = $this->get_notices();
		if ( $this->has_email_management() ) {
			foreach ( $notices as $key => $email ) {
				try {
					if ( empty( $email->email_id ) ) {
						unset( $notices[ $key ] );
						continue;
					}
					if ( ! $email->get_template()->can_view ) {
						unset( $notices[ $key ] );
						continue;
					}
					if ( empty( $email->get_template()->meta['type'] ) || 'notice' !== $email->get_template()->meta['type'] ) {
						unset( $notices[ $key ] );
						continue;
					}
				} catch ( \Exception $e ) {
					// Do nothing.
				}
			}

			return $notices;
		}

		return $notices;
	}

	/**
	 * Gets the notice.
	 *
	 * @since 3.8.12
	 * @param string $email_id The email ID.
	 * @return \EDD\Emails\Email
	 */
	public function get_notice( $email_id ) {
		if ( $this->has_email_management() ) {
			return edd_get_email( $email_id );
		}

		return edd_sl_get_renewal_notice( $email_id );
	}

	/**
	 * Gets the notice for a period.
	 *
	 * @since 3.8.12
	 * @param string $period The period.
	 * @return \EDD\Emails\Email|false
	 */
	public function get_notice_for_period( $period = 'expired' ) {
		if ( $this->has_email_management() ) {
			$args                           = $this->get_query_args();
			$args['status']                 = 1;
			$args['meta_query']['relation'] = 'AND';
			$args['meta_query'][]           = array(
				'key'     => 'period',
				'value'   => $period,
				'compare' => '=',
			);
			$emails                         = edd_get_emails( $args );

			return ! empty( $emails ) ? $emails[0] : false;
		}

		// Legacy notices are all enabled or disabled together.
		if ( ! $this->is_legacy_notice_enabled() ) {
			return false;
		}

		$legacy_notices = $this->get_legacy_notices();
		foreach ( $legacy_notices as $notice ) {
			if ( $notice['send_period'] === $period && $this->is_enabled( $notice ) ) {
				return $notice;
			}
		}

		return false;
	}

	/**
	 * Gets the notice ID.
	 *
	 * @since 3.8.12
	 * @param \EDD\Emails\Email|array $notice The notice.
	 * @return int
	 */
	public function get_notice_id( $notice ) {
		return $this->is_notice_email( $notice ) ? $notice->email_id : $notice['email_id'];
	}

	/**
	 * Checks if the notice is enabled.
	 *
	 * @since 3.8.12
	 * @param \EDD\Emails\Email|array $notice The notice.
	 * @return bool
	 */
	public function is_enabled( $notice ) {
		return $this->is_notice_email( $notice ) ? $notice->is_enabled() : $this->is_legacy_notice_enabled();
	}

	/**
	 * Checks if legacy notices are enabled.
	 *
	 * @since 3.8.12
	 * @return bool
	 */
	private function is_legacy_notice_enabled() {
		return (bool) edd_get_option( 'edd_sl_send_renewal_reminders', false );
	}

	/**
	 * Gets the notice period.
	 *
	 * @since 3.8.12
	 * @param \EDD\Emails\Email|array $notice The notice.
	 * @return string
	 */
	public function get_notice_period( $notice ) {
		return $this->is_notice_email( $notice ) ? $notice->get_template()->get_metadata( 'period' ) : $notice['send_period'];
	}

	/**
	 * Gets the saved emails.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	private function get_rows() {
		if ( ! $this->has_email_management() ) {
			return array();
		}

		return edd_get_emails( $this->get_query_args() );
	}

	/**
	 * Gets the query args.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	private function get_query_args() {
		return array(
			'sender'     => 'software_licensing',
			'context'    => 'license',
			'meta_query' => array(
				array(
					'key'     => 'type',
					'value'   => 'notice',
					'compare' => '=',
				),
			),
			'orderby'    => 'meta_value',
			'order'      => 'DESC',
			'meta_key'   => 'period',
		);
	}

	/**
	 * Gets the legacy notices.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	private function get_legacy_notices() {
		if ( $this->has_email_management() ) {
			$notices = get_option( 'edd_sl_renewal_notices', array() );
			if ( empty( $notices ) ) {
				return array();
			}
		} else {
			$notices = edd_sl_get_renewal_notices();
		}
		foreach ( $notices as $key => &$notice ) {
			$notice['email_id'] = absint( $key );
		}

		return $notices;
	}

	/**
	 * Checks if the site has email management.
	 *
	 * @since 3.8.12
	 * @return bool
	 */
	private function has_email_management() {
		return function_exists( 'edd_get_email' );
	}

	/**
	 * Checks if the notice is an email.
	 *
	 * @since 3.8.12
	 * @param \EDD\Emails\Email|array $notice The notice.
	 * @return bool
	 */
	private function is_notice_email( $notice ) {
		return $notice instanceof \EDD\Emails\Email;
	}
}
