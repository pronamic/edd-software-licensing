<?php

/**
 * Add Commissions link
 * *
 * @access      private
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
 * Remvoes the License Renewal Notice menu link
 *
 * @access      private
 * @since       3.0
 * @return      void
*/
function edd_sl_hide_renewal_notice_page() {
	remove_submenu_page( 'edit.php?post_type=download', 'edd-license-renewal-notice' );
}

/**
 * Renders the main Licenses admin page
 *
 * @access      private
 * @since       1.0
 * @return      void
*/
function edd_sl_licenses_page() {

	?>
	<div class="wrap">

		<div id="icon-edit" class="icon32"><br/></div>
		<h2><?php _e( 'Easy Digital Download Licenses', 'edd_sl' ); ?></h2>
		<?php edd_sl_show_errors(); ?>
		<?php if( ! empty( $_GET['action'] ) && 'manage_sites' == $_GET['action'] ) : ?>
			<?php $license_id = absint( $_GET['license_id'] ); ?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-1">
					<div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<h3 class="hndle">
								<span><?php printf( __( 'Active Sites for %s', 'edd_sl' ), edd_software_licensing()->get_license_key( $license_id ) ); ?></span>
							</h3>
							<table class="wp-list-table widefat fixed">
							<?php
							$sites = edd_software_licensing()->get_sites( $license_id );
							if( ! empty( $sites ) ) :
								$i = 0;
								foreach( $sites as $site ) : ?>
								<?php $site_url = strpos( $site, 'http' ) !== false ? $site : 'http://' . $site; ?>
								<tr class="row<?php if( $i % 2 == 0 ) { echo ' alternate'; } ?>">
									<td><a href="<?php echo $site_url; ?>" target="_blank"><?php echo $site; ?></a></td>
									<td><a href="<?php echo wp_nonce_url( add_query_arg( array( 'edd_action' => 'deactivate_site', 'site_url' => $site ) ), 'edd_deactivate_site_nonce' ); ?>"><?php _e( 'Deactivate Site', 'edd_sl' ); ?></a></td>
								</tr>
								<?php
								$i++;
								endforeach;
							else : ?>
							<tr class="row"><td colspan="2"><?php _e( 'This license has not been activated on any sites', 'edd_sl' ); ?></td></tr>
							<?php endif; ?>
							</table>
							<form method="post">
								<div class="inside">
									<p><?php _e( 'Use this form to add a new site URL for this license. Once added, the site will be considered active and will be included in the total site count.', 'edd_sl' ); ?></p>
									<p>
										<input type="text" name="site_url"  value="http://"/>
										<span class="description"><?php _e( 'Enter the site URL including http://', 'edd_sl' ); ?></span>
									</p>
									<p>
										<?php wp_nonce_field( 'edd_add_site_nonce', 'edd_add_site_nonce' ); ?>
										<input type="hidden" name="edd_action" value="insert_site"/>
										<input type="hidden" name="license_id" value="<?php echo esc_attr( $license_id ); ?>"/>
										<input type="submit" class="button-primary button" value="<?php _e( 'Add Site', 'edd_sl' ); ?>"/>
									</p>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

		<?php elseif( ! empty( $_GET['action'] ) && 'edit_exp' == $_GET['action'] ) : ?>

			<?php
			$license_id   = absint( $_GET['license_id'] );
			$is_lifetime  = edd_software_licensing()->is_lifetime_license( $license_id );
			$expiration = '';
			if ( ! $is_lifetime ) {
				$expiration   = edd_software_licensing()->get_license_expiration( $license_id );
				$expiration   = date( 'm/d/Y', $expiration );
			}
			$child_args   = array(
				'post_type'      => 'edd_license',
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => -1,
				'post_parent'    => $license_id
			);
			$has_children = get_posts( $child_args );
			?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-1">
					<div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<h3 class="hndle">
								<span><?php printf( __( 'Edit Expiration for %s', 'edd_sl' ), edd_software_licensing()->get_license_key( $license_id ) ); ?></span>
							</h3>
							<form method="post">
								<div class="inside">
									<p>
										<input id="edit_expiration_date" <?php echo ( $is_lifetime ) ? 'disabled="disabled"' : ''; ?> type="text" name="expiration" class="edd_datepicker" value="<?php echo esc_attr( $expiration ); ?>"/>
										<span class="description"><?php _e( 'Choose a new expiration date.', 'edd_sl' ); ?></span>
									</p>
									<p>
										<input id="edit_expiration_is_lifetime" type="checkbox" name="lifetime" value="1" <?php checked( true, $is_lifetime, true ); ?> />
										<label for="edit_expiration_is_lifetime"><?php _e( 'Lifetime License', 'edd_sl' ); ?></label>
									</p>
									<?php if ( ! empty( $has_children ) ) : ?>
									<p>
										<span class="edd-notice">
											<?php _e( 'This license key is associated with the purchase of a bundled product. Changing its expiration date will alter the expiration of all associated license keys.', 'edd_sl' ); ?>
										</span>
									</p>
									<?php endif; ?>
									<p>
										<?php wp_nonce_field( 'edd_edit_exp_nonce', 'edd_edit_exp_nonce' ); ?>
										<input type="hidden" name="edd_action" value="update_license_expiration"/>
										<input type="hidden" name="license_id" value="<?php echo esc_attr( $license_id ); ?>"/>
										<input type="submit" class="button-primary button" value="<?php _e( 'Update Expiration', 'edd_sl' ); ?>"/>
									</p>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

		<?php else : ?>
			<style>
				.column-status, .column-count { width: 100px; }
				.column-limit { width: 150px; }
			</style>
			<form id="licenses-filter" method="get">

				<input type="hidden" name="post_type" value="download" />
				<input type="hidden" name="page" value="edd-licenses" />
				<?php
				$licenses_table = new EDD_SL_List_Table();
				$licenses_table->prepare_items();
				$licenses_table->search_box( 'search', 'edd_sl_search' );
				$licenses_table->views();
				$licenses_table->display();
				?>
			</form>

		<?php endif; ?>

	</div>
	<?php

	$redirect = get_transient( '_edd_sl_bulk_actions_redirect' );

	if( false !== $redirect ) : delete_transient( '_edd_sl_bulk_actions_redirect' ) ?>
	<script type="text/javascript">
	window.location = "<?php echo admin_url( 'edit.php?post_type=download&page=edd-licenses' ); ?>";
	</script>
	<?php endif;
}

/**
 * Processes the Deactivate Site button
 *
 * @access      private
 * @since       2.4
 * @return      void
*/
function edd_sl_process_deactivate_site() {

	if( ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_deactivate_site_nonce' ) ) {
		return;
	}

	$license_id = absint( $_GET['license_id'] );
	$user_id    = edd_software_licensing()->get_user_id( $license_id );

	if( ! current_user_can( 'edit_shop_payments' ) && $user_id != get_current_user_id() ) {
		return;
	}

	$site_url   = urldecode( $_GET['site_url'] );

	edd_software_licensing()->delete_site( $license_id, $site_url );

	wp_safe_redirect( remove_query_arg( array( 'edd_action', 'site_url', 'edd_sl_error' ) ) ); exit;

}
add_action( 'edd_deactivate_site', 'edd_sl_process_deactivate_site' );

/**
 * Processes the Add Site button
 *
 * @access      private
 * @since       2.4
 * @return      void
*/
function edd_sl_process_add_site() {

	if( ! wp_verify_nonce( $_POST['edd_add_site_nonce'], 'edd_add_site_nonce' ) ) {
		return;
	}

	$license_id  = absint( $_GET['license_id'] );
	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$user_id     = edd_software_licensing()->get_user_id( $license_id );

	if( ! current_user_can( 'edit_shop_payments' ) && $user_id != get_current_user_id() ) {
		return;
	}

	$site_url = sanitize_text_field( $_POST['site_url'] );

	if( edd_software_licensing()->is_at_limit( $license_id, $download_id ) && ! current_user_can( 'edit_shop_payments' ) ) {

		// The license is at its activation limit so stop and show an error
		wp_safe_redirect( add_query_arg( 'edd_sl_error', 'at_limit' ) ); exit;

	}

	edd_software_licensing()->insert_site( $license_id, $site_url );
	edd_software_licensing()->set_license_status( $license_id, 'active' );

	if( is_admin() ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=download&page=edd-licenses&action=manage_sites&license_id=' . $license_id ) ); exit;
	}

	wp_safe_redirect( remove_query_arg( array( 'edd_action', 'site_url', 'edd_sl_error' ) ) ); exit;

}
add_action( 'edd_insert_site', 'edd_sl_process_add_site' );

/**
 * Processes the Update Expiration form
 *
 * @access      private
 * @since       2.4
 * @return      void
*/
function edd_sl_admin_update_expiration() {

	if( ! wp_verify_nonce( $_POST['edd_edit_exp_nonce'], 'edd_edit_exp_nonce' ) ) {
		return;
	}

	if( ! current_user_can( 'edit_shop_payments' ) ) {
		return;
	}

	$license_id  = absint( $_POST['license_id'] );
	$key         = edd_software_licensing()->get_license_key( $license_id );
	$is_lifetime = empty( $_POST['lifetime'] ) ? '0' : '1';

	if ( ! $is_lifetime ) {

		$expiration  = sanitize_text_field( $_POST['expiration'] ) . '23:59:59';
		$expiration  = strtotime( $expiration );

		if( 'expired' == edd_software_licensing()->get_license_status( $license_id ) && $expiration > time() ) {
			edd_software_licensing()->set_license_status( $license_id, 'active' );
		}

		edd_software_licensing()->set_license_expiration( $license_id, $expiration );
		delete_post_meta( $license_id, '_edd_sl_is_lifetime' );

		// Update expiration date for child licenses.
		$args = array(
			'post_type'      => 'edd_license',
			'post_parent'    => $license_id,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids'
		);

		$child_licenses = get_posts( $args );

		foreach ( $child_licenses as $child_id ) {
			delete_post_meta( $child_id, '_edd_sl_is_lifetime' );
		}

	} else {

		edd_software_licensing()->set_license_as_lifetime( $license_id );
		edd_software_licensing()->set_license_status( $license_id, 'active' );
		delete_post_meta( $license_id, '_edd_sl_expiration' );

		// Update expiration date for child licenses.
		$args = array(
			'post_type'      => 'edd_license',
			'post_parent'    => $license_id,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids'
		);

		$child_licenses = get_posts( $args );

		foreach ( $child_licenses as $child_id ) {
			delete_post_meta( $child_id, '_edd_sl_expiration' );
		}

	}


	wp_safe_redirect( admin_url( 'edit.php?post_type=download&page=edd-licenses&s=' . $key ) ); exit;

}
add_action( 'edd_update_license_expiration', 'edd_sl_admin_update_expiration' );

/**
 * Action to add the generated license to the license log when generating new keys for a Download
 *
 * @access      private
 * @since       2.6
 * @return      void
*/
function edd_sl_log_generated_license( $license_id, $d_id, $payment_id, $type ) {
	$log_id = wp_insert_post(
		array(
			'post_title'   => sprintf( __( 'Missing License Generated: %s' ), $license_id ),
			'post_type'	   => 'edd_license_log',
			'post_author' => get_current_user_id(),
			'post_content' => json_encode( array(
				'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
				'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
				'REQUEST_TIME' => $_SERVER['REQUEST_TIME']
			) ),
			'post_status'  => 'publish'
		 )
	);
	add_post_meta( $log_id, '_edd_sl_log_license_id', $license_id );
}

/**
 * Handle the ajax call to increase an activation limit
 *
 * @access      private
 * @since       2.6
 * @return      void
*/
function edd_sl_ajax_increase_limit() {

	// If there is no download ID posted, breakout immediately because we cannot find the download
	if( ! isset( $_POST['license'] ) ) {
		status_header( 404 );
		die();
	}

	// Make sure the current user can manage shop payments
	if( ! current_user_can( 'edit_shop_payments' ) ) {
		status_header( 415 );
		die();
	}

	// Grab the license ID and make sure its an int
	$license_id = intval( $_POST['license'] );
	$download_id = intval( $_POST['download'] );

	// Make sure the post we are looking at is a license, otherwise the post (media type) is unsupported!
	if( get_post_type( $license_id ) !== 'edd_license' ) {
		status_header( 415 );
		die();
	}

	$limit = edd_software_licensing()->get_license_limit( $download_id, $license_id );
	$limit++;

	update_post_meta( $license_id, '_edd_sl_limit', $limit );

	echo $limit; exit;
}
add_action( 'wp_ajax_edd_sl_increase_limit', 'edd_sl_ajax_increase_limit' );

/**
 * Handle the ajax call to decrease an activation limit
 *
 * @access      private
 * @since       2.6
 * @return      void
*/
function edd_sl_ajax_decrease_limit() {

	// If there is no download ID posted, breakout immediately because we cannot find the download
	if( ! isset( $_POST['license'] ) ) {
		status_header( 404 );
		die();
	}

	// Make sure the current user can manage shop payments
	if( ! current_user_can( 'edit_shop_payments' ) ) {
		status_header( 415 );
		die();
	}

	// Grab the license ID and make sure its an int
	$license_id  = intval( $_POST['license'] );
	$download_id = intval( $_POST['download'] );

	// Make sure the post we are looking at is a license, otherwise the post (media type) is unsupported!
	if( get_post_type( $license_id ) !== 'edd_license' ) {
		status_header( 415 );
		die();
	}

	$limit = edd_software_licensing()->get_license_limit( $download_id, $license_id );
	$limit--;

	if( $limit < 1 ) {
		$limit = '0';
	}

	update_post_meta( $license_id, '_edd_sl_limit', (int)$limit );

	if( $limit > 0 ) {
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

	if( ! current_user_can( 'manage_shop_settings' ) ) {
		die( '-2' );
	}

	$license_id = absint( $_REQUEST['license_id'] );
	$logs = edd_software_licensing()->get_license_logs( $license_id );

	if( $logs ) {
		$html = '<ul>';
		foreach ( $logs as $log ) {

			if( has_term( 'renewal_notice', 'edd_log_type', $log->ID ) ) {

				$html .= '<li>';
				$html .= '#' . esc_html( $log->ID ) . ' - ' . esc_html( get_the_title( $log->ID ) );
				$html .= '</li>';

			} else {

				$data = json_decode( get_post_field( 'post_content', $log->ID ) );
				$html .= '<li>';
				$html .= '#' . esc_html( $log->ID ) . ' - ' . esc_html( get_the_title( $log->ID ) );
				if( isset( $data->HTTP_USER_AGENT ) ) {
					$html .= esc_html( $data->HTTP_USER_AGENT ) . ' - ';
				}
				if( isset( $data->HTTP_USER_AGENT ) ) {
					$html .= 'IP: ' . esc_html( $data->REMOTE_ADDR ) . ' - ';
				}
				if( isset( $data->HTTP_USER_AGENT ) ) {
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
