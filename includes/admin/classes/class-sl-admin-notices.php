<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Software_Licensing_Admin_Notices {

	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	public function notices() {
		if( ! isset( $_GET['page'] ) || $_GET['page'] != 'edd-licenses' ) {
			return;
		}

		if( empty( $_GET['edd-message'] ) ) {
			return;
		}

		$type    = 'updated';
		$message = '';

		switch( strtolower( $_GET['edd-message'] ) ) {

			case 'deactivate' :

				$message = __( 'License deactivated successfully', 'edd_sl' );

				break;

			case 'activate' :

				$message = __( 'License activated successfully', 'edd_sl' );

				break;

			case 'enable' :

				$message = __( 'License enabled successfully', 'edd_sl' );

				break;

			case 'disable' :

				$message = __( 'License disabled successfully', 'edd_sl' );

				break;

			case 'renew' :

				$message = __( 'License renewed successfully', 'edd_sl' );

				break;

			case 'renewal_notice' :

				$message = __( 'Renewal notice sent successfully', 'edd_sl' );

				break;

			case 'license-updated' :

				$message = __( 'License updated successfully', 'edd_sl' );

				break;

			case 'delete' :

				$message = __( 'License deleted successfully', 'edd_sl' );

				break;

			case 'delete-error' :

				$message = __( 'Error deleting license', 'edd_sl' );

				break;

			case 'send-notice' :

				$message = __( 'License renewal notice sent successfully', 'edd_sl' );

				break;

			case 'set-lifetime' :

				$message = __( 'License expiration set to lifetime successfully', 'edd_sl' );

				break;
		}

		if ( ! empty( $message ) ) {
			echo '<div class="' . esc_attr( $type ) . '"><p>' . $message . '</p></div>';
		}

	}

}
$edd_software_licensing_admin_notices = new EDD_Software_Licensing_Admin_Notices;
