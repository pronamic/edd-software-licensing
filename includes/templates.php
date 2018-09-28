<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

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

	$is_upgrade_page = isset( $_GET['view'] ) && $_GET['view'] == 'upgrades';
	$is_manage_page  = isset( $_GET['action'] ) && $_GET['action'] == 'manage_licenses';
	if ( $is_upgrade_page || $is_manage_page ) {
		return;
	}

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


/**
 * Display license keys on the [edd_receipt] short code
 *
 * @access      private
 * @since       1.3.6
 * @return      void
 */

function edd_sl_show_keys_on_receipt( $payment, $edd_receipt_args ) {

	if( empty( $payment ) || empty( $payment->ID ) ) {
		return;
	}

	$licensing = edd_software_licensing();
	$licenses  = apply_filters( 'edd_sl_licenses_of_purchase', $licensing->get_licenses_of_purchase( $payment->ID ), $payment, $edd_receipt_args );

	if( ! empty( $licenses ) ) {
		echo '<tr class="edd_license_keys">';
			echo '<td colspan="2"><strong>' . __( 'License Keys:', 'edd_sl' ) . '</strong></td>';
		echo '</tr>';
		foreach( $licenses as $license ) {
			echo '<tr class="edd_license_key">';
				echo '<td>';
					echo '<span class="edd_sl_license_title">' . $license->get_download()->get_name() . '</span>&nbsp;';
					if( $license->get_download()->has_variable_prices() ) {
						echo '<span class="edd_sl_license_price_option">&ndash;&nbsp;' . edd_get_price_option_name( $license->get_download()->ID, $license->price_id ) . '</span>';
					}
					if( 'expired' == $license->status ) {
						echo '<span class="edd_sl_license_key_expired">&nbsp;(' . __( 'expired', 'edd_sl' ) . ')</span>';
					} elseif( 'disabled' === $license->status ) {
						echo '<span class="edd_sl_license_key_revoked">&nbsp;(' . __( 'disabled', 'edd_sl' ) . ')</span>';
					}
				echo '</td>';
				if( $license ) {
					echo '<td>';
						echo '<span class="edd_sl_license_key">' . $license->key . '</span>';
					echo '</td>';
				} else {
					echo '<td><span class="edd_sl_license_key edd_sl_none">' . __( 'none', 'edd_sl' ) . '</span></td>';
				}
			echo '</tr>';
		}
	}
}
add_action( 'edd_payment_receipt_after', 'edd_sl_show_keys_on_receipt', 10, 2 );

/**
 * Hide download links for expired licenses on purchase receipt page
 *
 * @since       2.3
 * @since       3.6 - Updated to use EDD_Software_Licensing->license_can_download to support multiple licenses for same ID
 *
 * @param       bool $show If we should show or hide the links to download on the purchase receipt
 * @param       int  $item The Item ID that was purchased (download ID)
 * @param       array $receipt_args Array of arguments for the item, of which we use `id` for the Payment/Order ID
 *
 * @return      bool
 */
function edd_sl_hide_downloads_on_expired( $show, $item, $receipt_args ) {
	$can_download = edd_software_licensing()->license_can_download( $item, '', $receipt_args['id'], array() );
	if ( true === $can_download['success'] ) {
		$show = true;
	} elseif ( false === $can_download['success'] ) {
		$show = false;
	}
	return $show;
}
add_filter( 'edd_receipt_show_download_files', 'edd_sl_hide_downloads_on_expired', 10, 3 );
