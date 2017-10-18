<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Displays a history of all license keys for a customer
 *
 * @since 3.4
 */
function edd_sl_license_keys( $atts = array(), $content = array() ) {

	ob_start();

	if( edd_user_pending_verification() ) {

		edd_get_template_part( 'account', 'pending' );

	} else {

		edd_get_template_part( 'license', 'keys' );

	}

	return ob_get_clean();

}
add_shortcode( 'edd_license_keys', 'edd_sl_license_keys' );