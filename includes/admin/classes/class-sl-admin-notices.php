<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Software_Licensing_Admin_Notices {

	/**
	 * EDD_Software_Licensing_Admin_Notices constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initializes action hooks.
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'future_requirements' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Shows a notice if upcoming future requirements have not been met.
	 *
	 * @since 3.7.2
	 */
	public function future_requirements() {
		$requirements = new EDD_SL_Requirements();

		// @todo make these version numbers constants in future requirements class
		$required_versions = array(
			'php'                    => array(
				'minimum' => '7.4',
				'name'    => 'PHP',
				'local'   => true,
			),
			'wp'                     => array(
				'minimum' => '5.8',
				'name'    => 'WordPress',
				'local'   => true,
			),
			'easy-digital-downloads' => array(
				'minimum' => '3.2',
				'name'    => 'Easy Digital Downloads',
				'local'   => true,
			),
		);

		// This will be used to build a unique dismiss key for this exact set of requirements.
		$version_pieces = array();

		foreach ( $required_versions as $required_id => $required_properties ) {
			$requirements->add_requirement( $required_id, $required_properties );

			$version_pieces[] = sprintf( '%s-%s', $required_id, $required_properties['minimum'] );
		}

		if ( $requirements->met() ) {
			return;
		}

		$dismiss_key = sanitize_key( 'sl_requirements_' . implode( '-', $version_pieces ) );
		if ( get_user_meta( get_current_user_id(), "_edd_{$dismiss_key}_dismissed", true ) ) {
			return;
		}

		$errors = $requirements->get_errors();

		$dismiss_notice_url = wp_nonce_url(
			add_query_arg(
				array(
					'edd_action' => 'dismiss_notices',
					'edd_notice' => urlencode( $dismiss_key ),
				)
			),
			'edd_notice_nonce'
		);
		?>
		<div class="notice notice-warning edd-notice">
			<h2><?php esc_html_e( 'System Upgrades Required for Software Licensing', 'edd_sl' ); ?></h2>
			<p>
				<?php echo wp_kses( __( 'Your site needs to upgrade to <strong>at least</strong> the following in order to be ready for Software Licensing 3.9:', 'edd_sl' ), array( 'strong' => array() ) ); ?>
			</p>
			<ul>
				<?php foreach ( $errors->get_error_messages() as $message ) : ?>
				<li><?php echo wp_kses_post( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php echo wp_kses( __( 'While these versions are the <em>minimum</em> we will be supporting, we encourage you to update to the most recent versions available.', 'edd_sl' ), array( 'em' => array() ) ); ?>
			</p>

			<p>
				<a href="<?php echo esc_url( $dismiss_notice_url ); ?>"><?php esc_html_e( 'Dismiss Notice', 'edd_sl' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders admin notices.
	 */
	public function notices() {
		if( ! isset( $_GET['page'] ) || $_GET['page'] != 'edd-licenses' ) {
			return;
		}

		if( empty( $_GET['edd-message'] ) ) {
			return;
		}

		$type    = 'updated';
		$message = '';

		switch ( strtolower( $_GET['edd-message'] ) ) {

			case 'deactivate':
				$message = __( 'License deactivated successfully.', 'edd_sl' );
				break;

			case 'activate':
				$message = __( 'License activated successfully.', 'edd_sl' );
				break;

			case 'enable':
				$message = __( 'License enabled successfully.', 'edd_sl' );
				break;

			case 'disable':
				$message = __( 'License disabled successfully.', 'edd_sl' );
				break;

			case 'renew':
				$message = __( 'License renewed successfully.', 'edd_sl' );
				break;

			case 'renewal_notice':
				$message = __( 'Renewal notice sent successfully.', 'edd_sl' );
				break;

			case 'license-updated':
				$message = __( 'License updated successfully.', 'edd_sl' );
				break;

			case 'delete':
				$message = __( 'License deleted successfully.', 'edd_sl' );

				break;

			case 'delete-error':
				$message = __( 'Error deleting license.', 'edd_sl' );

				break;

			case 'send-notice':
				$message = __( 'License renewal notice sent successfully.', 'edd_sl' );

				break;

			case 'set-lifetime':
				$message = __( 'License expiration set to lifetime successfully.', 'edd_sl' );
				break;

			case 'license-subscribed':
				$message = __( 'License successfully subscribed to email notices.', 'edd_sl' );
				break;

			case 'license-unsubscribed':
				$message = __( 'License successfully unsubscribed from email notices.', 'edd_sl' );
				break;
		}

		if ( ! empty( $message ) ) {
			echo '<div class="' . esc_attr( $type ) . '"><p>' . $message . '</p></div>';
		}

	}

}
$edd_software_licensing_admin_notices = new EDD_Software_Licensing_Admin_Notices;
