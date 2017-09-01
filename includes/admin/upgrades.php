<?php

function edd_sl_register_upgrades_page() {

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	add_submenu_page( null, __( 'EDD SL Upgrades', 'edd_sl' ), __( 'EDD Upgrades', 'edd_sl' ), 'install_plugins', 'edd-sl-upgrades', 'edd_sl_upgrades_screen' );
}
add_action( 'admin_menu', 'edd_sl_register_upgrades_page', 10 );

function edd_sl_upgrades_screen() {
	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$counts = wp_count_posts( 'edd_license' );
	$total  = 0;
	foreach ( $counts as $count ) {
		$total += $count;
	}
	$total_steps = round( ( $total / 100 ), 0 );
	if ( ( $total_steps * 100 ) < $total ) {
		$total_steps++;
	}
?>
	<div class="wrap">
		<h2><?php _e( 'Software Licensing - Upgrades', 'edd_sl' ); ?></h2>
		<div id="edd-upgrade-status">
			<p><?php _e( 'The upgrade process is running, please be patient. This could take several minutes to complete while license keys are upgraded in batches of 100.', 'edd_sl' ); ?></p>
			<p><strong><?php printf( __( 'Step %d of approximately %d running', 'edd_sl' ), $step, $total_steps ); ?>
		</div>
		<script type="text/javascript">
			document.location.href = "index.php?edd_action=<?php echo esc_html( $_GET['edd_upgrade'] ); ?>&step=<?php echo absint( $_GET['step'] ); ?>";
		</script>
	</div>
<?php
}

/**
 * Triggers all upgrade functions
 *
 * @since 2.2
 * @return void
*/
function edd_sl_show_upgrade_notice() {

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	$edd_sl_version = get_option( 'edd_sl_version' );

	if( ! $edd_sl_version ){
		edd_sl_v22_upgrades();
	}

	if ( version_compare( $edd_sl_version, '2.4', '<' ) && ! isset( $_GET['edd_upgrade'] ) ) {
		printf(
			'<div class="updated"><p>' . __( 'The License Keys in Easy Digital Downloads needs to be upgraded, click <a href="%s">here</a> to start the upgrade.', 'edd_sl' ) . '</p></div>',
			esc_url( add_query_arg( array( 'edd_action' => 'upgrade_site_urls' ), admin_url() ) )
		);
	}

	if ( version_compare( $edd_sl_version, '2.5', '<' ) && ! isset( $_GET['edd_upgrade'] ) ) {
		printf(
			'<div class="updated"><p>' . __( 'The License Keys in Easy Digital Downloads needs to be upgraded, click <a href="%s">here</a> to start the upgrade.', 'edd_sl' ) . '</p></div>',
			esc_url( add_query_arg( array( 'edd_action' => 'upgrade_license_price_ids' ), admin_url() ) )
		);
	}

	if ( version_compare( $edd_sl_version, '3.0', '<' ) && ! isset( $_GET['edd_upgrade'] ) && edd_get_option( 'edd_sl_send_renewal_reminders' ) ) {
		printf(
			'<div class="updated"><p>' . __( 'The renewal notices in Easy Digital Downloads needs to be upgraded, click <a href="%s">here</a> to start the upgrade.', 'edd_sl' ) . '</p></div>',
			esc_url( add_query_arg( array( 'edd_action' => 'upgrade_renewal_notices' ), admin_url() ) )
		);
	}

	if( function_exists( 'edd_has_upgrade_completed' ) && function_exists( 'edd_maybe_resume_upgrade' ) ) {
		$resume_upgrade = edd_maybe_resume_upgrade();
		if ( empty( $resume_upgrade ) ) {

			if ( version_compare( $edd_sl_version, '3.2', '<' ) || ! edd_has_upgrade_completed( 'sl_add_bundle_licenses' ) ) {
				$notice  = '<div class="notice notice-info"><p>';
				$notice .= __( 'Easy Digital Downloads - Software Licensing now supports Bundle Licenses. This is an opt-in upgrade, so please choose one of the following:', 'edd_sl' );
				$notice .= '<p>';
				$notice .= sprintf( __( 'If you want to read more on this new feature, <a href="%s" target="_blank">click here</a>.', 'edd_sl' ), 'http://docs.easydigitaldownloads.com/article/698-bundle-licensing' );
				$notice .= '</p>';
				$notice .= '<ol>';
				$notice .= sprintf( __( '<li>Make <a href="%s">no changes</a></li>', 'edd_sl' ), esc_url( admin_url( 'index.php?page=edd-upgrades&edd-upgrade=sl_add_bundle_licenses&custom=0' ) ) );
				$notice .= __( '<li>After you enable licensing for the bundles of your choice, <a href="#" onClick="jQuery(\'#generate-licenses-button-wrapper\').toggle(); return false;">generate licenses for bundles with Licensing enabled</a>.</li>', 'edd_sl' );
				$notice .= '</ol>';
				$notice .= '</p></div>';
				$notice .= '<div class="notice notice-info" style="display:none;" id="generate-licenses-button-wrapper">';
				$notice .= '<p>' . __( 'Before clicking this button, verify that all bundles you want to generate licenses for have Licensing enabled, and their license length is correct.', 'edd_sl' ) . '</p>';
				$notice .= sprintf( __( '<a href="%s" class="button-primary">', 'edd_sl' ), esc_url( admin_url( 'index.php?page=edd-upgrades&edd-upgrade=sl_add_bundle_licenses&custom=1' ) ) );
				$notice .= __( 'Generate Bundle Licenses', 'edd_sl' );
				$notice .= '</a>';
				$notice .= '</p></div>';
				echo $notice;
			}

			if ( version_compare( $edd_sl_version, '3.4.8', '<' ) || ! edd_has_upgrade_completed( 'sl_deprecate_site_count_meta' ) ) {
				printf(
					'<div class="updated"><p>' . __( 'The Software Licensing post meta needs to be upgraded, click <a href="%s">here</a> to start the upgrade.', 'edd_sl' ) . '</p></div>',
					esc_url( add_query_arg( array( 'edd_action' => 'sl_deprecate_site_count_meta' ), admin_url() ) )
				);
			}

		}
	}
}
add_action( 'admin_notices', 'edd_sl_show_upgrade_notice' );

/**
 * Sets renewal flags on all renewal purchases, if any
 *
 * @since 2.2
 * @return void
 */
function edd_sl_v22_upgrades() {

	global $wpdb;

	if ( get_option( 'edd_sl_renewals_upgraded' ) )
		return;

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}

	// Select all payments that had a renewal discount and update their post meta
	$payments = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value LIKE '%edd_sl_renewal%';" );
	if( $payments ) {
		foreach( $payments as $payment ) {

			if( edd_get_payment_meta( $payment, '_edd_sl_is_renewal', true ) ) {
				continue;
			}

			$args = array(
				'posts_per_page' => -1,
				'meta_key'       => '_edd_sl_payment_id',
				'meta_value'     => $payment,
				'post_type'      => 'edd_license',
				'fields'         => 'ids',
			);

			$keys = get_posts( $args );
			if( $keys ) {
				add_post_meta( $payment, '_edd_sl_is_renewal', '1', true );
				add_post_meta( $payment, '_edd_sl_renewal_key', edd_software_licensing()->get_license_key( $keys[0] ), true );
			}
		}
	}

	add_option( 'edd_sl_renewals_upgraded', '1' );
}

/**
 * Upgrades old license keys with the new site URL store
 *
 * @since 2.4
 * @return void
 */
function edd_sl_24_upgrade_site_urls() {

	$edd_sl_version = get_option( 'edd_sl_version' );

	if ( version_compare( $edd_sl_version, '2.4', '>=' ) ) {
		return;
	}

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$offset = $step == 1 ? 0 : $step * 100;

	$args   = array(
		'post_type'      => 'edd_license_log',
		'posts_per_page' => 100,
		'offset'         => $offset
	);

	$logs = get_posts( $args );

	if( $logs ) {
		foreach( $logs as $log ) {

			$license_id = get_post_meta( $log->ID, '_edd_sl_log_license_id', true );
			$urls       = wp_extract_urls( $log->post_content );
			$urls       = edd_sl_sanitize_urls( $urls );

			if( strpos( $log->post_title, 'License Activated' ) ) {

				foreach( $urls as $url ) {
					edd_software_licensing()->insert_site( $license_id, $url );
				}

			} else {

				foreach( $urls as $url ) {
					edd_software_licensing()->delete_site( $license_id, $url );
				}

			}

		}

		// Keys found so upgrade them
		$step++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-sl-upgrades',
			'edd_upgrade' => 'upgrade_site_urls',
			'step'        => $step
		), admin_url( 'index.php' ) );
		wp_safe_redirect( $redirect ); exit;

	} else {

		// No more keys found, update the DB version and finish up
		update_option( 'edd_sl_version', EDD_SL_VERSION );
		wp_redirect( admin_url() ); exit;
	}

}
add_action( 'edd_upgrade_site_urls', 'edd_sl_24_upgrade_site_urls' );

/**
 * Upgrades old license keys with the purchased price IDs
 *
 * @since 2.5
 * @return void
 */
function edd_sl_25_upgrade_license_ids() {

	$edd_sl_version = get_option( 'edd_sl_version' );

	if ( version_compare( $edd_sl_version, '2.5', '>=' ) ) {
		return;
	}

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$offset = $step == 1 ? 0 : $step * 100;

	$args   = array(
		'post_type'      => 'edd_license',
		'posts_per_page' => 100,
		'offset'         => $offset,
		'fields'         => 'ids'
	);

	$license_keys = get_posts( $args );

	if( $license_keys ) {

		foreach( $license_keys as $license ) {

			$price_id    = (int) edd_software_licensing()->get_price_id( $license );
			$download_id = (int) edd_software_licensing()->get_download_id( $license );
			$payment_id  = get_post_meta( $license, '_edd_sl_payment_id', true );
			$cart_items  = edd_get_payment_meta_cart_details( $payment_id );
			if( $cart_items ) {
				foreach( $cart_items as $cart_item ) {

					if( $download_id !== $cart_item['id'] )
						continue;

					$price_id = isset( $cart_item['item_number']['options']['price_id'] ) ? (int) $cart_item['item_number']['options']['price_id'] : false;

					if( false !== $price_id ) {
						update_post_meta( $license, '_edd_sl_download_price_id', $price_id );
					}
				}
			}

		}

		// Keys found so upgrade them
		$step++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-sl-upgrades',
			'edd_upgrade' => 'upgrade_license_price_ids',
			'step'        => $step
		), admin_url( 'index.php' ) );
		wp_safe_redirect( $redirect ); exit;

	} else {

		// No more keys found, update the DB version and finish up
		update_option( 'edd_sl_version', EDD_SL_VERSION );
		wp_redirect( admin_url() ); exit;
	}

}
add_action( 'edd_upgrade_license_price_ids', 'edd_sl_25_upgrade_license_ids' );

/**
 * Upgrades the renewal notice for the new renewal notices system in 3.0
 *
 * @since 3.0
 * @return void
 */
function edd_sl_30_upgrade_renewal_notices() {

	$edd_sl_version = get_option( 'edd_sl_version' );

	if ( version_compare( $edd_sl_version, '3.0', '>=' ) ) {
		return;
	}

	@ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	$subject = edd_get_option( 'edd_sl_renewal_reminder_subject' );
	$message = edd_get_option( 'edd_sl_renewal_reminder' );

	$subject = ! empty( $subject ) ? $subject : __( 'Your License Key is About to Expire', 'edd_sl' );
	$period  = '+1month';
	$message = ! empty( $message ) ? $message : false;

	if( empty( $message ) ) {
		$message = 'Hello {name},

Your license key for {product_name} is about to expire.

If you wish to renew your license, simply click the link below and follow the instructions.

Your license expires on: {expiration}.

Your expiring license key is: {license_key}.

Renew now: {renewal_link}.';
	}

	$notices = array();
	$notices[0] = array(
		'subject'     => $subject,
		'message'     => $message,
		'send_period' => $period
	);

	update_option( 'edd_sl_renewal_notices', $notices );

	// Notices upgraded, redirect back to the dashboard
	update_option( 'edd_sl_version', EDD_SL_VERSION );
	wp_redirect( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions&section=software-licensing' ) ); exit;

}
add_action( 'edd_upgrade_renewal_notices', 'edd_sl_30_upgrade_renewal_notices' );


/**
 * Sanitize the URLs extracted from the server data
 * *
 * @access      private
 * @since       2.4
 * @return      array
*/
function edd_sl_sanitize_urls( $urls = array() ) {

	if( empty( $urls ) ) {
		return false;
	}

	foreach( $urls as $key => $url ) {

		// Exclude URLs that match the current site
		if( strpos( home_url(), $url ) !== false ) {
			unset( $urls[ $key ] );
			continue;
		}

		// Look for quotation marks and remove them
		$dirty  = strpos( $url, '"' );
		if( false !== $dirty ) {
			$url = substr( $url, 0, $dirty );
		}

		$urls[ $key ] = $url;

		if( strpos( $url, 'http' ) === false ) {
			unset( $urls[ $key ] );
		}

	}

	return $urls;

}

/**
 * Run the upgrade to generate licenses for past bundle purchases
 *
 * @since  3.2
 * @return void
 */
function edd_sl_v32_add_bundle_licenses() {
	global $wpdb;

	if( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( __( 'You do not have permission to do shop upgrades', 'edd' ), __( 'Error', 'edd' ), array( 'response' => 403 ) );
	}

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		@set_time_limit(0);
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$number = 25;
	$offset = $step == 1 ? 0 : ( $step - 1 ) * $number;

	// Get the upgrade type...if none provided, dismiss is used and no further action is taken
	$custom = isset( $_GET['custom'] ) ? absint( $_GET['custom'] ) : 0;

	if ( $step < 2 ) {

		// User chose to not run any upgrades
		if ( 0 === $custom ) {
			update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
			edd_set_upgrade_complete( 'sl_add_bundle_licenses' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() ); exit;
		}

		// Check if we have any payments before moving on
		$sql = "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_payment' LIMIT 1";
		$has_payments = $wpdb->get_col( $sql );

		if( empty( $has_payments ) ) {
			// We had no payments, just complete
			update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
			edd_set_upgrade_complete( 'sl_add_bundle_licenses' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() ); exit;
		}

		// Check if we have any bundle products as well
		$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_edd_bundled_products' LIMIT 1";
		$has_bundles = $wpdb->get_col( $sql );

		if ( empty( $has_bundles ) ) {
			// We had no bundles, just complete
			update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
			edd_set_upgrade_complete( 'sl_add_bundle_licenses' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() ); exit;
		}

	}

	$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_edd_bundled_products'";
	$bundle_download_ids = $wpdb->get_col( $sql );

	foreach ( $bundle_download_ids as $key => $bundle_id ) {

		$licenses_enabled = get_post_meta( $bundle_id, '_edd_sl_enabled', false );
		if ( empty( $licenses_enabled ) ) {
			unset( $bundle_download_ids[ $key ] );
		}

	}

	// If we ended up with no bundles that have licensing enabled, just exit as well
	if ( empty( $bundle_download_ids ) ) {
		update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
		edd_set_upgrade_complete( 'sl_add_bundle_licenses' );
		delete_option( 'edd_doing_upgrade' );
		wp_redirect( admin_url() ); exit;
	}

	$total = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;
	if ( empty( $total ) || $total <= 1 ) {
		$args = array(
			'number'   => -1,
			'download' => $bundle_download_ids
		);

		$all_payments = new EDD_Payments_Query( $args );
		$all_payments = $all_payments->get_payments();

		$total        = count( $all_payments );
	}

	$payment_args = array(
		'order'    => 'ASC',
		'orderby'  => 'ID',
		'number'   => $number,
		'page'     => $step,
		'download' => $bundle_download_ids,

	);

	$payments = new EDD_Payments_Query( $payment_args );
	$payments = $payments->get_payments();

	$bundle_contents = array();
	if( ! empty( $payments ) ) {

		foreach( $payments as $payment ) {

			$cart_details = edd_get_payment_meta_downloads( $payment->ID );

			foreach ( $cart_details as $cart_index => $cart_item ) {

				if ( ! in_array( $cart_item['id'], $bundle_download_ids ) ) {
					// Item is not a bundled product, move along
					continue;
				}

				// See if there is a bundle license already
				$bundle_license = edd_software_licensing()->get_license_by_purchase( $payment->ID, $cart_item['id'] );

				// It not create the bundle license
				if ( empty( $bundle_license ) ) {
					$bundle_license = edd_software_licensing()->generate_license( $cart_item['id'], $payment->ID, 'default', $cart_item, $cart_index );
					$bundle_license = edd_software_licensing()->get_license_by_purchase( $payment->ID, $cart_item['id'], $cart_index );
				}

				// If we haven't retrieved the bundle contents yet, get them
				if ( ! isset( $bundle_contents[ $cart_item['id'] ] ) ) {
					$bundle_contents[ $cart_item['id'] ] = edd_get_bundled_products( $cart_item['id'] );
				}

				// Get the products in the bundle
				$bundle_products = $bundle_contents[ $cart_item['id'] ];

				// For every bundle in the download, get the license for this payment, and update it's post_parent
				foreach ( $bundle_products as $license_download_id ) {
					$license = edd_software_licensing()->get_license_by_purchase( $payment->ID, $license_download_id );

					if ( empty ( $license ) ) {
						continue;
					}

					$update_args = array(
						'ID'          => $license->ID,
						'post_parent' => $bundle_license->ID
					);

					wp_update_post( $update_args );

				}

			}

		}

		// More Payments found so upgrade them
		$step++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-upgrades',
			'edd-upgrade' => 'sl_add_bundle_licenses',
			'step'        => $step,
			'number'      => $number,
			'total'       => $total,
			'custom'      => $custom
		), admin_url( 'index.php' ) );
		wp_safe_redirect( $redirect ); exit;

	} else {

		// No more data to update. Downloads have been altered or dismissed
		update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
		edd_set_upgrade_complete( 'sl_add_bundle_licenses' );
		delete_option( 'edd_doing_upgrade' );

		wp_redirect( admin_url() ); exit;
	}
}
add_action( 'edd_sl_add_bundle_licenses', 'edd_sl_v32_add_bundle_licenses' );

/**
 * Runs an upgrade routine to remove old _edd_sl_site_count meta in favor of the helper method in the main class:
 * edd_software_licensing->get_site_count( $license_id )
 *
 * @since  3.4.8
 * @return void
 */
function edd_sl_348_remove_site_count_meta() {
	global $wpdb;

	if( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( __( 'You do not have permission to do shop upgrades', 'edd' ), __( 'Error', 'edd' ), array( 'response' => 403 ) );
	}

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		@set_time_limit(0);
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$number = 25;
	$total  = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;
	if ( $step < 2 ) {

		// Check if we have any licenses before moving on
		$sql = "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_license' LIMIT 1";
		$has_licenses = $wpdb->get_col( $sql );

		if( empty( $has_licenses ) ) {
			// We had no licenses, just complete
			update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
			edd_set_upgrade_complete( 'sl_deprecate_site_count_meta' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() ); exit;
		}

		// Check if we have any _edd_sl_site_count meta items as well
		$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_edd_sl_site_count' LIMIT 1";
		$has_site_counts = $wpdb->get_col( $sql );

		if ( empty( $has_site_counts ) ) {
			// We had no site count meta, just complete
			update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
			edd_set_upgrade_complete( 'sl_deprecate_site_count_meta' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() ); exit;
		}

	}

	if ( false === $total ) {
		$sql   = "SELECT COUNT( post_id ) FROM $wpdb->postmeta WHERE meta_key = '_edd_sl_site_count' ";
		$total = $wpdb->get_var( $sql );
	}

	$sql      = $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_edd_sl_site_count' LIMIT %d;", $number );
	$meta_ids = $wpdb->get_col( $sql );

	if( ! empty( $meta_ids ) ) {

		$meta_ids = '"' . implode( '","', $meta_ids ) . '"';
		$sql      = "DELETE FROM $wpdb->postmeta WHERE meta_id IN ({$meta_ids})";

		$wpdb->query( $sql );

		// More Payments found so upgrade them
		$step++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-upgrades',
			'edd-upgrade' => 'sl_deprecate_site_count_meta',
			'step'        => $step,
			'number'      => $number,
			'total'       => $total,
		), admin_url( 'index.php' ) );
		wp_safe_redirect( $redirect ); exit;

	} else {

		// No more data to update. Downloads have been altered or dismissed
		update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );
		edd_set_upgrade_complete( 'sl_deprecate_site_count_meta' );
		delete_option( 'edd_doing_upgrade' );

		wp_redirect( admin_url() ); exit;
	}
}
add_action( 'edd_sl_deprecate_site_count_meta', 'edd_sl_348_remove_site_count_meta' );
