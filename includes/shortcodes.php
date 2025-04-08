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

add_shortcode( 'edd_renewal_form', 'edd_sl_show_renewal_shortcode' );
/**
 * Registers the [edd_renewal_form] shortcode to show the
 * renewal form.
 *
 * @return string
 * @since 3.7
 */
function edd_sl_show_renewal_shortcode() {
	if ( ! edd_sl_renewals_allowed() ) {
		return '';
	}

	ob_start();
	edd_sl_checkout_js( true );
	edd_get_template_part( 'license', 'renewal-form' );

	return ob_get_clean();
}
