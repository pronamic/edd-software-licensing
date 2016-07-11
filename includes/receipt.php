<?php

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
	$licenses  = $licensing->get_licenses_of_purchase( $payment->ID );

	if( ! empty( $licenses ) ) {
		echo '<tr class="edd_license_keys">';
			echo '<td colspan="2"><strong>' . __( 'License Keys:', 'edd_sl' ) . '</strong></td>';
		echo '</tr>';
		foreach( $licenses as $license ) {
			echo '<tr class="edd_license_key">';
				$key      = $licensing->get_license_key( $license->ID );
				$download = $licensing->get_download_id( $license->ID );
				$price_id = $licensing->get_price_id( $license->ID );

				echo '<td>';
					echo '<span class="edd_sl_license_title">' . get_the_title( $download ) . '</span>&nbsp;';
					if( edd_has_variable_prices( $download ) ) {
						echo '<span class="edd_sl_license_price_option">&ndash;&nbsp;' . edd_get_price_option_name( $download, $price_id ) . '</span>';
					}
					if( 'expired' == $licensing->get_license_status( $license->ID ) ) {
						echo '<span class="edd_sl_license_key_expired">&nbsp;(' . __( 'expired', 'edd_sl' ) . ')</span>';
					} elseif( 'draft' == $license->post_status ) {
						echo '<span class="edd_sl_license_key_revoked">&nbsp;(' . __( 'revoked', 'edd_sl' ) . ')</span>';
					}
				echo '</td>';
				if( $license ) {
					echo '<td>';
						echo '<span class="edd_sl_license_key">' . $key . '</span>';
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
 * @access      private
 * @since       2.3
 * @return      void
 */
function edd_sl_hide_downloads_on_expired( $show, $item, $receipt_args ) {
	$payment_id = $receipt_args['id'];
	$licenses   = edd_software_licensing()->get_licenses_of_purchase( $payment_id );
	if( ! empty( $licenses ) ) {
		foreach( $licenses as $license ) {
			if( 'expired' == edd_software_licensing()->get_license_status( $license->ID ) ) {
				$show = false;
				break;
			}
		}
	}
	return $show;
}
add_filter( 'edd_receipt_show_download_files', 'edd_sl_hide_downloads_on_expired', 10, 3 );
