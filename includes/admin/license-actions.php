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

	$edd_sl_licenses_page = add_submenu_page( 'edit.php?post_type=download', __( 'Easy Digital Download Licenses', 'edd_sl' ), __( 'Licenses', 'edd_sl' ), 'edit_products', 'edd-licenses', 'edd_sl_licenses_page' );
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
	if ( empty( $_GET['license'] ) || empty( $_GET['action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_shop_payments' ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_sl_license_nonce' ) ) {
		return;
	}

	$action = sanitize_text_field( $_GET['action'] );
	$id     = (int) $_GET['license'];

	$license = edd_software_licensing()->get_license( $id );

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
			wp_delete_post( $id );
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
 * Update expiration date
 *
 * @since 3.5
 * @return void
 */
function edd_sl_process_license_exp_update() {
	if ( ! current_user_can( 'edit_shop_payments' ) ) {
		return;
	}

	if ( ! isset( $_POST['exp_date'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['edd-sl-update-license-exp-nonce'], 'edd-sl-update-license-exp' ) ) {
		wp_die( __( 'Nonce verification failed', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 403 ) );
	}

	$expiration = strtotime( $_POST['exp_date'] . ' 23:59:59' );
	$license_id = (int) $_POST['license_id'];

	$license = edd_software_licensing()->get_license( $license_id );

	if ( false !== $license ) {

		$license->expiration = $expiration;

	}

	wp_redirect( add_query_arg( array( 'edd-message' => 'exp-update' ) ) );
	exit;
}
add_action( 'admin_init', 'edd_sl_process_license_exp_update', 1 );


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

		if ( ! current_user_can( 'edit_shop_payments', $payment_id ) ) {
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

	if ( ! current_user_can( 'edit_shop_payments', $payment_id ) ) {
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

	wp_delete_post( $_POST['license_id'] );

	wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-licenses&edd-message=delete' ) );
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
	$log_id = wp_insert_post(
		array(
			'post_title'   => sprintf( __( 'Missing License Generated: %s' ), $license_id ),
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

	// Make sure the current user can manage shop payments
	if ( ! current_user_can( 'edit_shop_payments' ) ) {
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

	// Make sure the current user can manage shop payments
	if ( ! current_user_can( 'edit_shop_payments' ) ) {
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
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
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
	$post_types[] = 'edd_license';
	$post_types[] = 'edd_license_log';
	return $post_types;
}
add_filter( 'edd_reset_store_post_types', 'edd_sl_reset_post_types', 10, 1 );
