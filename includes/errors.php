<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Prints error messages related to license keys, such as when activating a site
 *
 * @access      private
 * @since       2.7
 * @return      void
*/
function edd_sl_show_errors() {
	if( ! isset( $_GET['edd_sl_error'] ) ) {
		return;
	}

	$error = sanitize_text_field( $_GET['edd_sl_error'] );
	switch( $error ) {

		case 'at_limit' :
			$message = __( 'This license is at its activation limit. Deactivate a site before adding a new one.', 'edd_sl' );
			break;

		case 'error_adding_site' :
			$message = __( 'There was an error adding your site. Please try again.', 'edd_sl' );
			break;
	}

	if( ! empty( $error ) ) {

		echo '<div class="edd_errors"><p class="edd_error"><strong>' . __( 'Error:', 'edd_sl' ) . '&nbsp;</strong>' . $message . '</p></div>';

	}
}
