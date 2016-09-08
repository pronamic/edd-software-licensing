<?php

/**
 * Displays a history of all license keys for a customer
 *
 * @since 3.4
 */
function edd_sl_license_keys( $atts = array(), $content = array() ) {

	ob_start();

	edd_get_template_part( 'license', 'keys' );

	return ob_get_clean();

}
add_shortcode( 'edd_license_keys', 'edd_sl_license_keys' );

function edd_sl_add_key_column() {
	echo '<th class="edd_license_key">' . __( 'License Keys', 'edd_sl' ) . '</th>';
}
add_action( 'edd_purchase_history_header_after', 'edd_sl_add_key_column' );

/**
 * Displays a Manage Licenses link in purchase history
 *
 * @since 2.7
 */
function edd_sl_site_management_links( $payment_id, $purchase_data ) {

	$licensing = edd_software_licensing();
	$downloads = edd_get_payment_meta_downloads( $payment_id );
	if( $downloads) :
		$manage_licenses_url = esc_url( add_query_arg( array( 'action' => 'manage_licenses', 'payment_id' => $payment_id ) ) );
		echo '<td class="edd_license_key">';
			if( edd_is_payment_complete( $payment_id ) && $licensing->get_licenses_of_purchase( $payment_id ) ) {
				echo '<a href="' . esc_url( $manage_licenses_url ) . '">' . __( 'View Licenses', 'edd_sl' ) . '</a>';
			} else {
				echo '-';
			}
		echo '</td>';
	else:
		echo '<td>&mdash;</td>';
	endif;
}
add_action( 'edd_purchase_history_row_end', 'edd_sl_site_management_links', 10, 2 );

/**
 * Override the content of the purchase history page to show our license management UI
 *
 * @since 2.7
 */
function edd_sl_override_history_content( $content ) {

	if( empty( $_GET['action'] ) || 'manage_licenses' != $_GET['action'] ) {
		return $content;
	}

	if( empty( $_GET['payment_id'] ) ) {
		return $content;
	}

	if( ! in_the_loop() ) {
		return $content;
	}

	if( isset( $_GET['license_id'] ) && isset( $_GET['view'] ) && 'upgrades' == $_GET['view'] ) {

		ob_start();
		edd_get_template_part( 'licenses', 'upgrades' );
		$content = ob_get_clean();

	} else {

		$view = isset( $_GET['license_id'] ) ? 'single' : 'overview';

		ob_start();
		edd_get_template_part( 'licenses', 'manage-' . $view );
		$content = ob_get_clean();

	}

	return $content;

}
add_filter( 'the_content', 'edd_sl_override_history_content', 9999 );

/**
 * Adds our templates dir to the EDD template stack
 *
 * @since 2.7
 */
function edd_sl_add_template_stack( $paths ) {

	$paths[ 50 ] = EDD_SL_PLUGIN_DIR . 'templates/';

	return $paths;

}
add_filter( 'edd_template_paths', 'edd_sl_add_template_stack' );
