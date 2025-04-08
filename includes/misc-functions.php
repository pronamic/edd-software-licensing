<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * After a payment has been marked as complete, check to see if it was an upgrade or renewal and add appropriate license meta.
 *
 * @since 3.6
 *
 * @param $payment_id
 * @param $payment
 * @param $customer
 */
function edd_sl_set_upgrade_renewal_dates( $payment_id, $payment, $customer ) {
	$is_renewal = $payment->get_meta( '_edd_sl_upgraded_payment_id' );
	$is_upgrade = $payment->get_meta( '_edd_sl_is_renewal' );

	if ( empty( $is_renewal ) && empty( $is_upgrade ) ) {
		return;
	}

	foreach ( $payment->cart_details as $cart_item ) {

		$license_id = ! empty( $cart_item['item_number']['options']['license_id'] )
			? intval( $cart_item['item_number']['options']['license_id'] )
			: false;

		if ( empty( $license_id ) ) {
			return;
		}

		$license = edd_software_licensing()->get_license( $license_id );
		if ( false === $license ) {
			return;
		}

		if ( ! empty( $cart_item['item_number']['options']['is_renewal'] ) ) {
			$license->add_meta( '_edd_sl_renewal_date', $payment->completed_date );

			// Add the meta to all child licenses as well.
			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$child_license->add_meta( '_edd_sl_renewal_date', $payment->completed_date );
				}
			}
		} elseif ( ! empty( $cart_item['item_number']['options']['is_upgrade'] ) ) {
			$license->add_meta( '_edd_sl_upgrade_date', $payment->completed_date );

			// Add the meta to all child licenses as well.
			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$child_license->add_meta( '_edd_sl_upgrade_date', $payment->completed_date );
				}
			}
		}
	}
}
add_action( 'edd_complete_purchase', 'edd_sl_set_upgrade_renewal_dates', 10, 3 );

/**
 * Listen for calls to get_post_meta and see if we need to filter them.
 *
 * @since  3.4.8
 * @param  mixed  $value       The value get_post_meta would return if we don't filter.
 * @param  int    $object_id   The object ID post meta was requested for.
 * @param  string $meta_key    The meta key requested.
 * @param  bool   $single      If the person wants the single value or an array of the value
 * @return mixed               The value to return
 */
function edd_sl_get_meta_backcompat( $value, $object_id, $meta_key, $single ) {

	global $wpdb;

	$meta_keys = array( '_edd_sl_site_count' );

	if ( ! in_array( $meta_key, $meta_keys ) ) {
		return $value;
	}

	switch ( $meta_key ) {

		case '_edd_sl_site_count':
			$value           = edd_software_licensing()->get_site_count( $object_id );
			$edd_is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $edd_is_checkout ) {
				// Throw deprecated notice if WP_DEBUG is defined and on
				trigger_error( __( 'The _edd_sl_site_count postmeta is <strong>deprecated</strong> since EDD Software Licensing 2.4.8! Use edd_software_licensing->get_site_count( $license_id ) instead.', 'edd_sl' ) );

				$backtrace = debug_backtrace();
				trigger_error( print_r( $backtrace, 1 ) );
			}

			break;

	}

	// If the 'single' param is false, we need to make this a single item array with the value within it
	if ( false === $single ) {
		$value = array( $value );
	}

	return $value;
}
add_filter( 'get_post_metadata', 'edd_sl_get_meta_backcompat', 10, 4 );

/**
 * Stores the payment IDs that are created during the migration to custom tables in Version 3.6
 *
 * @since 3.6
 *
 * @param $payment_id
 * @param $payment_data
 */
function _eddsl_migration_log_payment_ids( $payment_id, $payment_object ) {
	$is_migrating = get_option( 'edd_sl_is_migrating_licenses', false );
	if ( empty( $is_migrating ) ) {
		return;
	}

	$payments_during_migration   = get_option( 'edd_sl_payments_saved_during_migration', array() );
	$payments_during_migration[] = $payment_id;
	$payments_during_migration   = array_unique( $payments_during_migration );

	update_option( 'edd_sl_payments_saved_during_migration', $payments_during_migration );
}
add_action( 'edd_payment_saved', '_eddsl_migration_log_payment_ids', 99, 2 );

/**
 * Returns an array of platforms that can be used with a product's requirements.
 *
 * @since 3.8
 *
 * @return array Filtered array of required platforms.
 */
function edd_sl_get_platforms() {
	$platforms = array(
		'php' => 'PHP',
		'wp'  => 'WordPress',
	);

	/**
	 * Modify required platforms
	 *
	 * @since 3.8
	 *
	 * @param array Array of platforms
	 */
	return apply_filters( 'edd_sl_platforms', $platforms );
}

/**
 * Gets the license length for a download.
 *
 * @since 3.7.3
 * @param int         $download_id The download ID.
 * @param boolean|int $price_id    The price ID for the download (optional).
 *
 * @return string  Returns "lifetime" or a PHP time string.
 */
function edd_sl_get_product_license_length( $download_id, $price_id = false ) {
	$download = new EDD_SL_Download( $download_id );
	if ( is_numeric( $price_id ) ) {
		$is_lifetime = $download->is_price_lifetime( $price_id );
	} else {
		$is_lifetime = $download->is_lifetime();
	}
	if ( $is_lifetime ) {
		return 'lifetime';
	}
	$exp_unit   = $download->get_expiration_unit( $price_id );
	$exp_length = $download->get_expiration_length( $price_id );

	return '+' . $exp_length . ' ' . $exp_unit;
}

/** Internal Functions only: Not for 3rd party use. */

/**
 * This is not a true background processor, but something to initially help us clean up the '_edd_sl_legacy_id' license meta.
 *
 * Note: We're using the wp_schedule_single_event here to avoid having a forever running cron event that's scheduled. This way
 * once we are done processing all the legacy license meta, the cron event will just not be re-scheduled.
 *
 * Once the first EDD daily events cron runs after the update, we check if we've already scheduled a single cleanup, if not we check
 * if there is any legacy meta to remove, and if there is, we schedule a single event in 10 minutes.
 *
 * @since 3.8.8
 */
function _edd_sl_schedule_cleanup_legacy_ids() {
	// If we're done here.
	if ( edd_has_upgrade_completed( 'sl_remove_legacy_license_id_meta' ) ) {
		return;
	}

	// If we've already scheduled the cleanup, no need to schedule it again.
	if ( wp_next_scheduled( 'edd_sl_cleanup_legacy_ids' ) ) {
		return;
	}

	// See if we have any license meta with the key of _edd_sl_legacy_id.
	global $wpdb;
	$meta_table      = edd_software_licensing()->license_meta_db->table_name;
	$has_legacy_meta = $wpdb->get_var( "SELECT meta_id FROM {$meta_table} WHERE meta_key = '_edd_sl_legacy_id' LIMIT 1" );

	if ( empty( $has_legacy_meta ) ) {
		_edd_sl_legacy_ids_cleanup_complete( false );
		return;
	}

	$notifications = _edd_sl_edd_notifications();

	if ( false !== $notifications ) {
		$initial_notification = $notifications->get_item_by( 'remote_id', 'sl-legacyid-running' );
		if ( empty( $initial_notification ) ) {
			$notifications->maybe_add_local_notification(
				array(
					'remote_id'  => 'sl-legacyid-running',
					'buttons'    => '',
					'conditions' => '',
					'type'       => 'info',
					'title'      => __( 'Database Optimization in Progress', 'edd_sl' ),
					'content'    => __( 'Software Licensing is currently optimizing your license meta table in the background. This process may take a while to complete depending on the number of licenses on your site. We\'ll let you know when the process is complete.', 'edd_sl' ),
				)
			);
		}
	} else {
		update_option( 'edd_sl_legacy_id_cleanup_running', 1, false );
	}

	// ...And schedule a single event a minute from now to start the processing of this data.
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'edd_sl_cleanup_legacy_ids' );
}
add_action( 'edd_daily_scheduled_events', '_edd_sl_schedule_cleanup_legacy_ids' );

/**
 * Process on the 'one time' events to clean up the '_edd_sl_legacy_id' license meta.
 *
 * We're doing 100 at a time here in order to ensure that we don't overrun the Databsae or Cache services.
 *
 * After we process 100 rows to delete, we'll go ahead and schedule another event in 10 minutes to process the next 100.
 *
 * @since 3.8.8
 */
function _edd_sl_cleanup_legacy_ids() {
	// Since this hooks on an action, don't let it run if we're not in a cron.
	if ( ! edd_doing_cron() ) {
		return;
	}

	global $wpdb;
	$meta_table = edd_software_licensing()->license_meta_db->table_name;
	$wpdb->query( "DELETE FROM {$meta_table} WHERE meta_key = '_edd_sl_legacy_id' LIMIT 500" );

	// If we have more to process, schedule another event in 10 minutes and then leave this function.
	$has_legacy_meta = $wpdb->get_var( "SELECT meta_id FROM {$meta_table} WHERE meta_key = '_edd_sl_legacy_id' LIMIT 1" );
	if ( ! empty( $has_legacy_meta ) ) {
		wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS * 10 ), 'edd_sl_cleanup_legacy_ids' );
		return;
	}

	_edd_sl_legacy_ids_cleanup_complete();
}
add_action( 'edd_sl_cleanup_legacy_ids', '_edd_sl_cleanup_legacy_ids' );

/**
 * When the legacy ID cleanup process is complete, do some house keeping.
 *
 * Marks the sl_remove_legacy_license_id_meta upgrade as complete, dismisses the initial notification about
 * the process running, and then adds a new notification about the process being complete.
 *
 * @since 3.8.8
 *
 * @param bool $add_notification If we should add the notification to the database, defaults to true.
 */
function _edd_sl_legacy_ids_cleanup_complete( $add_notification = true ) {
	if ( ! function_exists( 'edd_set_upgrade_complete' ) ) {
		// Require the includes/admin/upgrads/upgrade-functions.php as this function isn't availalbe otherwise.
		require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrade-functions.php';
	}
	edd_set_upgrade_complete( 'sl_remove_legacy_license_id_meta' );

	if ( false === $add_notification ) {
		return;
	}

	$notifications = _edd_sl_edd_notifications();

	if ( false !== $notifications ) {
		$initial_notification = $notifications->get_item_by( 'remote_id', 'sl-legacyid-running' );
		if ( ! empty( $initial_notification ) ) {
			$notifications->update( $initial_notification->id, array( 'dismissed' => 1 ) );
		}

		$notifications->maybe_add_local_notification(
			array(
				'remote_id'  => 'sl-legacyid-done',
				'buttons'    => '',
				'conditions' => '',
				'type'       => 'success',
				'title'      => __( 'Database Optimization Complete!', 'edd_sl' ),
				'content'    => __( 'Software Licensing has completed the database optimization for the license meta table.', 'edd_sl' ),
			)
		);
	} else {
		// Remove the running option.
		delete_option( 'edd_sl_legacy_id_cleanup_running' );

		// Set a transient for the complete notice instead of an optoin, so it goes away on it's own.
		set_transient( 'edd_sl_legacy_id_cleanup_complete', 1, WEEK_IN_SECONDS );
	}
}

/**
 * In the event SL is installed a version of EDD that doesn't support local notifications (lower than 3.1.1) show admin notices.
 *
 * @since 3.8.8
 */
function _edd_sl_legacy_ids_cleanup_admin_notices() {
	// If the EDD version supports notifications...we don't need these admin notices.
	if ( false !== _edd_sl_edd_notifications() ) {
		return;
	}

	if ( get_option( 'edd_sl_legacy_id_cleanup_running' ) ) {
		?>
		<div class="notice notice-info edd-notice">
			<h2><?php esc_html_e( 'Easy Digital Downloads Database Optimization in Progress', 'edd_sl' ); ?></h2>
			<p>
				<?php esc_html_e( 'Software Licensing is currently optimizing your license meta table in the background. This process may take a while to complete depending on the number of licenses on your site. We\'ll let you know when the process is complete.', 'edd_sl' ); ?>
			</p>
		</div>
		<?php
	} elseif ( get_transient( 'edd_sl_legacy_id_cleanup_complete' ) ) {
		$dismiss_key = sanitize_key( '_sl_legacy_id_cleanup_complete' );
		if ( get_user_meta( get_current_user_id(), "_edd_{$dismiss_key}_dismissed", true ) ) {
			return;
		}

		$dismiss_notice_url = wp_nonce_url(
			add_query_arg(
				array(
					'edd_action' => 'dismiss_notices',
					'edd_notice' => rawurlencode( $dismiss_key ),
				)
			),
			'edd_notice_nonce'
		);
		?>
		<div class="notice notice-info edd-notice">
			<h2><?php esc_html_e( 'Database Optimization Complete!', 'edd_sl' ); ?></h2>
			<p>
				<?php esc_html_e( 'Software Licensing has completed the database optimization for the license meta table.', 'edd_sl' ); ?> <a class="button button-secondary" href="<?php echo esc_url( $dismiss_notice_url ); ?>"><?php esc_html_e( 'Dismiss Notice', 'edd_sl' ); ?></a>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', '_edd_sl_legacy_ids_cleanup_admin_notices' );

/**
 * Possibly loads the EDD Notifications Class.
 *
 * @since 3.8.8
 *
 * @return bool|EDD\Database\NotificationsDB False if the installation does not support the maybe_add_local_notification method, or the NotificationsDB class.
 */
function _edd_sl_edd_notifications() {
	if ( property_exists( EDD(), 'notifications' ) ) {
		$notifications = EDD()->notifications;
		if ( method_exists( $notifications, 'maybe_add_local_notification' ) ) {
			return $notifications;
		}
	}

	return false;
}

/**
 * Masks the output a license key.
 *
 * By default this will replace all but the last 6 characters with a *.
 *
 * The mask length can be filtered using the `edd_sl_mask_license_key_length` filter.
 * The mask character can be filtered using the `edd_sl_mask_license_key_character` filter.
 *
 * @since 3.8.11
 *
 * @param string $license_key The license key to mask.
 *
 * @return string The masked license key.
 */
function edd_sl_mask_license_key( $license_key ) {
	/**
	 * Filters the length of the license key to mask.
	 *
	 * @since 3.8.11
	 *
	 * @param int $mask_length The length of the license key to mask.
	 */
	$mask_length = apply_filters( 'edd_sl_mask_license_key_length', 6 );

	// If someone has custom license keys that are 6 or less characters, adjust to only show the last 2.
	if ( strlen( $license_key ) <= $mask_length ) {
		$mask_length = 2;
	}

	/**
	 * Filters the character to use when masking the license key.
	 *
	 * @since 3.8.11
	 *
	 * @param string $mask_char The character to use when masking the license key.
	 */
	$mask_char = apply_filters( 'edd_sl_mask_license_key_character', '•' );

	// Mask all characters except the last 6.
	$unmaksed       = substr( $license_key, -$mask_length );
	$masked_length  = strlen( substr( $license_key, 0, -$mask_length ) );
	$masked_license = str_repeat( $mask_char, $masked_length ) . $unmaksed;

	// Return the masked license key.
	return $masked_license;
}

/**
 * Checks if the EDD core Email Templates are registered.
 *
 * @since 3.8.12
 * @return bool True if the email templates are registered, false otherwise.
 */
function edd_sl_are_email_templates_registered() {
	return (bool) class_exists( '\\EDD\\Emails\\Email' );
}
