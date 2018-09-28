<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Remvoes the License Renewal Notice menu link
 *
 * @since       3.0
 * @return      void
*/
function edd_sl_hide_renewal_notice_page() {
	remove_submenu_page( 'edit.php?post_type=download', 'edd-license-renewal-notice' );
}


/**
 * Add Commissions link
 *
 * @since       1.0
 * @return      void
*/
function edd_sl_add_licenses_link() {
	global $edd_sl_licenses_page;

	$edd_sl_licenses_page = add_submenu_page( 'edit.php?post_type=download', __( 'Easy Digital Download Licenses', 'edd_sl' ), __( 'Licenses', 'edd_sl' ), 'view_licenses', 'edd-licenses', 'edd_sl_licenses_page' );
	$edd_sl_licenses_page = add_submenu_page( 'edit.php?post_type=download', __( 'License Renewal Notice', 'edd_sl' ), __( 'License Renewal Notice', 'edd_sl' ), 'manage_shop_settings', 'edd-license-renewal-notice', 'edd_sl_license_renewal_notice_edit' );

	add_action( 'admin_head', 'edd_sl_hide_renewal_notice_page' );
}
add_action( 'admin_menu', 'edd_sl_add_licenses_link', 10 );


/**
 * Process license updates for the single view
 *
 * @since 3.5
 * @return void
 */
function edd_sl_process_license_update() {

	if ( ! current_user_can( 'manage_licenses' ) ) {
		return;
	}

	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_sl_license_nonce' ) ) {
		return;
	}

	if ( isset( $_GET['license'] ) ) {
		if ( is_numeric( $_GET['license'] ) ) {
			$new_license_id = edd_software_licensing()->license_meta_db->get_license_id( '_edd_sl_legacy_id', absint( $_GET['license'] ), true );
		}

		if ( empty( $new_license_id ) ) {
			return;
		}
	}

	if( ( ! isset( $_GET['license_id'] ) || ! is_numeric( $_GET['license_id'] ) ) && empty( $new_license_id ) ) {
		return;
	}

	$license_id  = isset( $_GET['license_id'] ) ? absint( $_GET['license_id'] ) : $new_license_id;

	$action = sanitize_text_field( $_GET['action'] );

	$license = edd_software_licensing()->get_license( $license_id );

	switch ( $action ) {
		case 'deactivate':
			$license->status = 'inactive';
			break;
		case 'activate':
			$license->status = 'active';
			break;
		case 'enable':
			$license->enable();
			break;
		case 'disable':
			$license->disable();
			break;
		case 'renew':
			if ( empty( $license->parent ) ) {
				$license->renew();
			}
			break;
		case 'delete':
			$license->delete();
			break;
		case 'set-lifetime':
			$license->is_lifetime = true;
			break;
	}

	wp_redirect( add_query_arg( array( 'action' => false, '_wpnonce' => false, 'edd-message' => $action ) ) );
	exit;
}
add_action( 'admin_init', 'edd_sl_process_license_update', 1 );

/**
 * Process the request to regenerate a license key.
 *
 * @since 3.6.1
 */
function edd_sl_process_regenerate_license_key() {

	if ( ! current_user_can( 'manage_licenses' ) ) {
		$response = array(
			'success' => false,
			'message' => __( 'You do not have permission to manage this license.', 'edd_sl' ),
		);

		echo json_encode( $response );
		die();
	}

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'edd-sl-regenerate-license' ) ) {
		$response = array(
			'success' => false,
			'message' => __( 'Nonce validation failed.', 'edd_sl' ),
		);

		echo json_encode( $response );
		die();
	}

	if ( ! isset( $_POST['license_id'] ) || ! is_numeric( $_POST['license_id'] ) ) {
		$response = array(
			'success' => false,
			'message' => __( 'No license provided.', 'edd_sl' ),
		);

		echo json_encode( $response );
		die();
	}

	$license = edd_software_licensing()->get_license( $_POST['license_id'] );
	if ( false === $license ) {
		$response = array(
			'success' => false,
			'message' => __( 'Invalid license supplied.', 'edd_sl' ),
		);

		echo json_encode( $response );
		die();
	}


	$generated = $license->regenerate_key();

	if ( $generated ) {
		$response = array(
			'success' => true,
			'message' => __( 'License key generated successfully.', 'edd_sl' ),
			'key'     => $license->key,
		);

		echo json_encode( $response );
		die();
	} else {
		$response = array(
			'success' => false,
			'message' => __( 'Failed to generate a new license key.', 'edd_sl' ),
		);

		echo json_encode( $response );
		die();
	}

}
add_action( 'wp_ajax_edd_sl_regenerate_license', 'edd_sl_process_regenerate_license_key' );


/**
 * Update license information
 *
 * @since 3.5
 * @since 3.6   Function was updated to handle processing more information but function name left for backwards compatibility
 * @return void
 */
function edd_sl_process_license_exp_update() {
	if ( ! isset( $_POST['edd-sl-update-license-nonce'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_licenses' ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['edd-sl-update-license-nonce'], 'edd-sl-update-license' ) ) {
		wp_die( __( 'Nonce verification failed', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 403 ) );
	}

	$license_id = (int) $_POST['license_id'];
	$license    = edd_software_licensing()->get_license( $license_id );

	if ( false === $license ) {
		return;
	}

	if ( isset( $_POST['exp_date'] ) ) {
		$expiration          = strtotime( $_POST[ 'exp_date' ] . ' 23:59:59' );
		$license->expiration = $expiration;
	}

	if ( isset( $_POST['price_id'] ) && $license->get_download()->has_variable_prices() ) {
		$price_id = absint( $_POST['price_id'] );
		if ( (int) $price_id !== (int) $license->price_id ) {
			$old_price_id      = $license->price_id;
			$license->price_id = $price_id;
			$log_id = wp_insert_post(
				array(
					'post_title'   => sprintf( __( 'License Price ID modified to %d', 'edd_sl' ), $price_id ),
					'post_type'    => 'edd_license_log',
					'post_author'  => get_current_user_id(),
					'post_content' => json_encode( array(
						'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
						'REMOTE_ADDR'     => $_SERVER['REMOTE_ADDR'],
						'REQUEST_TIME'    => $_SERVER['REQUEST_TIME']
					) ),
					'post_status'  => 'publish'
				)
			);
			add_post_meta( $log_id, '_edd_sl_log_license_id', $license_id );

			// Make a note on the payment record.
			$payment = edd_get_payment( $license->payment_id );
			$payment->add_note( sprintf( __( 'License Price ID for %s changed from %d to %d.', 'edd_sl' ), $license->get_name( false ), $old_price_id, $price_id ) );
		}
	}

	wp_redirect( add_query_arg( array( 'edd-message' => 'license-updated' ) ) );
	exit;
}
add_action( 'edd_update_license', 'edd_sl_process_license_exp_update', 1 );


/**
 * Send a renewal notice
 *
 * @since 3.5
 * @return void
*/
function edd_sl_send_renewal_notice() {
	$return = array( 'success' => false );

	if ( ! empty( $_POST['license_id'] ) ) {
		$license_id = absint( $_POST['license_id'] );
		$payment_id = edd_software_licensing()->get_payment_id( $license_id );

		if ( ! current_user_can( 'manage_licenses', $payment_id ) ) {
			wp_die( __( 'You do not have permission to edit this license', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 403 ) );
		}


		$emails         = new EDD_SL_Emails;
		$notices        = edd_sl_get_renewal_notices();
		$send_notice_id = absint( $_POST['notice_id'] );

		if ( $emails->send_renewal_reminder( $license_id, $send_notice_id ) ) {
			$return['success'] = true;
			$return['url']     = admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license=' . $license_id . '&edd-message=send-notice' );
		}
	}

	echo json_encode( $return );
	die();
}
add_action( 'wp_ajax_edd_sl_send_renewal_notice', 'edd_sl_send_renewal_notice' );


/**
 * Delete a license
 *
 * @since 3.5
 * @return void
*/
function edd_sl_delete_license( $args ) {
	$payment_id = edd_software_licensing()->get_payment_id( $_POST['license_id'] );
	$license_id = absint( $_POST['license_id'] );
	$confirm    = ! empty( $args['edd-sl-license-delete-confirm'] ) ? true : false;
	$nonce      = $args['_wpnonce'];

	if ( ! current_user_can( 'delete_licenses', $payment_id ) ) {
		wp_die( __( 'You do not have permission to edit this license', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 403 ) );
	}

	if ( ! wp_verify_nonce( $nonce, 'delete-license' ) ) {
		wp_die( __( 'Cheatin\' eh?!', 'edd_sl' ) );
	}

	if ( ! $confirm ) {
		edd_set_error( 'license-delete-no-confirm', __( 'Please confirm you want to delete this license', 'edd_sl' ) );
	}

	if ( edd_get_errors() ) {
		wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license=' . $license_id ) );
		exit;
	}

	$deleted = edd_software_licensing()->licenses_db->delete( $license_id );
	$message = $deleted ? 'delete' : 'delete-error';

	wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-licenses&edd-message=' . $message ) );
	exit;
}
add_action( 'edd_sl_delete_license', 'edd_sl_delete_license' );

/**
 * Action to add the generated license to the license log when generating new keys for a Download
 *
 * @since       2.6
 * @return      void
*/
function edd_sl_log_generated_license( $license_id, $d_id, $payment_id, $type ) {
	$license = EDD_Software_Licensing()->get_license( $license_id );

	if ( $license ) {
		$license->add_log(
			sprintf( __( 'Missing License Generated: %s' ), $license_id ),
			array(
				'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
				'REMOTE_ADDR'     => $_SERVER['REMOTE_ADDR'],
				'REQUEST_TIME'    => $_SERVER['REQUEST_TIME']
			)
		);
	}
}


/**
 * Handle the ajax call to increase an activation limit
 *
 * @since       2.6
 * @return      void
*/
function edd_sl_ajax_increase_limit() {
	// If there is no download ID posted, breakout immediately because we cannot find the download
	if ( ! isset( $_POST['license'] ) ) {
		status_header( 404 );
		die();
	}

	// Make sure the current user can manage licenses.
	if ( ! current_user_can( 'manage_licenses' ) ) {
		status_header( 415 );
		die();
	}

	// Grab the license ID and make sure its an int
	$license_id  = intval( $_POST['license'] );
	$license     = edd_software_licensing()->get_license( $license_id );

	// Make sure the post we are looking at is a license, otherwise the post (media type) is unsupported!
	if ( ! $license ) {
		status_header( 415 );
		die();
	}

	$limit = $license->activation_limit;
	$limit++;

	$license->activation_limit = $limit;

	echo $limit;
	exit;
}
add_action( 'wp_ajax_edd_sl_increase_limit', 'edd_sl_ajax_increase_limit' );


/**
 * Handle the ajax call to decrease an activation limit
 *
 * @since       2.6
 * @return      void
*/
function edd_sl_ajax_decrease_limit() {
	// If there is no download ID posted, breakout immediately because we cannot find the download
	if ( ! isset( $_POST['license'] ) ) {
		status_header( 404 );
		die();
	}

	// Make sure the current user can manage licenses
	if ( ! current_user_can( 'manage_licenses' ) ) {
		status_header( 415 );
		die();
	}

	// Grab the license ID and make sure its an int
	$license_id  = intval( $_POST['license'] );
	$license     = edd_software_licensing()->get_license( $license_id );

	// Make sure the post we are looking at is a license, otherwise the post (media type) is unsupported!
	if ( ! $license ) {
		status_header( 415 );
		die();
	}

	$limit = $license->activation_limit;
	$limit--;

	if ( $limit < 1 ) {
		$limit = '0';
	}

	$license->activation_limit = $limit;


	if ( $limit > 0 ) {
		echo $limit;
	} else {
		echo __( 'Unlimited', 'edd_sl' );
	}
	exit;
}
add_action( 'wp_ajax_edd_sl_decrease_limit', 'edd_sl_ajax_decrease_limit' );


/**
 * Handle the AJAX call to fetch the license logs for a given license ID
 */
function edd_sl_ajax_get_license_logs() {
	if ( ! current_user_can( 'manage_licenses' ) ) {
		die( '-2' );
	}

	$license_id = absint( $_REQUEST['license_id'] );
	$logs       = edd_software_licensing()->get_license_logs( $license_id );

	if ( $logs ) {
		$html = '<ul>';
		foreach ( $logs as $log ) {

			if ( has_term( 'renewal_notice', 'edd_log_type', $log->ID ) ) {
				$html .= '<li>';
				$html .= '#' . esc_html( $log->ID ) . ' - ' . esc_html( get_the_title( $log->ID ) );
				$html .= '</li>';
			} else {
				$data = json_decode( get_post_field( 'post_content', $log->ID ) );
				$html .= '<li>';
				$html .= '#' . esc_html( $log->ID ) . ' - ' . esc_html( get_the_title( $log->ID ) );

				if ( isset( $data->HTTP_USER_AGENT ) ) {
					$html .= esc_html( $data->HTTP_USER_AGENT ) . ' - ';
				}
				if ( isset( $data->HTTP_USER_AGENT ) ) {
					$html .= 'IP: ' . esc_html( $data->REMOTE_ADDR ) . ' - ';
				}
				if ( isset( $data->HTTP_USER_AGENT ) ) {
					$html .= esc_html( date_i18n( get_option( 'date_format' ), $data->REQUEST_TIME ) . ' ' . date_i18n( get_option( 'time_format' ), $data->REQUEST_TIME ) );
				}
				$html .= '</li>';
			}
		}
		$html .= '</ul>';
	} else {
		$html = '<p>' . __( 'This license has no log entries', 'edd_sl' );
	}

	die( $html );
}
add_action( 'wp_ajax_edd_sl_get_license_logs', 'edd_sl_ajax_get_license_logs' );


/**
 * Add licenses and license logs to store reset
 *
 * @since  3.5
 * @param  array $post_types Current post types to remove in the reset
 * @return array             The post types with logs
 */
function edd_sl_reset_post_types( $post_types ) {
	$post_types[] = 'edd_license_log';
	return $post_types;
}
add_filter( 'edd_reset_store_post_types', 'edd_sl_reset_post_types', 10, 1 );

/**
 * Find any license IDs
 *
 * @since  3.6
 * @param  array $items Current items to remove from the reset
 * @return array        The items with any subscription customer entires
 */
function edd_sl_reset_delete_license_ids( $items ) {

	global $wpdb;

	$table       = edd_software_licensing()->licenses_db->table_name;
	$sql         = "SELECT id FROM {$table}";
	$license_ids = $wpdb->get_col( $sql );

	foreach ( $license_ids as $id ) {
		$items[] = array(
			'id'   => (int) $id,
			'type' => 'edd_license_id',
		);
	}

	return $items;
}
add_filter( 'edd_reset_store_items', 'edd_sl_reset_delete_license_ids', 10, 1 );

/**
 * Isolate any License IDs to remove from the db on reset
 *
 * @since  3.6
 * @param  string $type The type of item to remove from the initial findings
 * @param  array  $item The item to remove
 * @return string       The determine item type
 */
function edd_sl_reset_license_ids( $type, $item ) {

	if ( 'edd_license_id' === $item['type'] ) {
		$type = $item['type'];
	}

	return $type;

}
add_filter( 'edd_reset_item_type', 'edd_sl_reset_license_ids', 10, 2 );

/**
 * Add an SQL item to the reset process deleting any license data.
 *
 * @since  3.6
 * @param  array  $sql An Array of SQL statements to run
 * @param  string $ids The IDs to remove for the given item type
 * @return array       Returns the array of SQL statements with statements added
 */
function edd_sl_reset_license_queries( $sql, $ids ) {

	$license_table     = edd_software_licensing()->licenses_db->table_name;
	$meta_table        = edd_software_licensing()->license_meta_db->table_name;
	$activations_table = edd_software_licensing()->activations_db->table_name;

	$sql[] = "DELETE FROM {$license_table} WHERE id IN ($ids)";
	$sql[] = "DELETE FROM {$meta_table} WHERE license_id IN ($ids)";
	$sql[] = "DELETE FROM {$activations_table} WHERE license_id IN ($ids)";

	return $sql;

}
add_filter( 'edd_reset_add_queries_edd_license_id', 'edd_sl_reset_license_queries', 10, 2 );
