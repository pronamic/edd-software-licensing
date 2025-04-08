<?php
/**
 * Payment Filters
 *
 * This file handles adding filters to Downloads > Payment History
 *
 * @package     EDDSoftwareLicensing
 * @copyright   Copyright (c) 2016, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function edd_sl_payment_upgrade_filters() {

	if( did_action( 'edd_sl_payment_filter_fields' ) ) {
		return;
	}

	$checked = ! empty( $_GET['meta_key'] ) ? sanitize_key( $_GET['meta_key'] ) : '';
	?>

	<label>
		<input type="radio" name="meta_key" value=""<?php checked( '', $checked ); ?> />
		<?php _e( 'All Payments', 'edd_sl' ); ?>
	</label>
	<label>
		<input type="radio" name="meta_key" value="_edd_sl_upgraded_payment_id"<?php checked( '_edd_sl_upgraded_payment_id', $checked ); ?> />
		<?php _e( 'Upgrades', 'edd_sl' ); ?>
	</label>
	<label>
		<input type="radio" name="meta_key" value="_edd_sl_is_renewal"<?php checked( '_edd_sl_is_renewal', $checked ); ?> />
		<?php _e( 'Renewals', 'edd_sl' ); ?>
	</label>

<?php

	do_action( 'edd_sl_payment_filter_fields' );
}
add_action( 'edd_payment_advanced_filters_after_fields', 'edd_sl_payment_upgrade_filters' );
add_action( 'edd_payment_advanced_filters_row', 'edd_sl_payment_upgrade_filters' );

/**
 * Filter the payment counts for upgrades and renewals
 *
 * @since 3.5.7
 * @param string $join
 *
 * @return string
 */
function edd_sl_payment_count_filters( $join = '' ) {
	global $wpdb;
	$filter = ! empty( $_GET['meta_key'] ) ? sanitize_key( $_GET['meta_key'] ) : '';

	if ( ! empty( $filter ) ) {
		$join .= " INNER JOIN $wpdb->postmeta m ON m.meta_key = '" . $filter . "' AND m.post_id = p.ID ";
	}

	return $join;
}
add_filter( 'edd_count_payments_join', 'edd_sl_payment_count_filters', 10, 1 );

/**
 * Modifies the orders list table query in EDD 3.0 to filter by renewal or upgrade.
 *
 * @param array                       $clauses Query clauses.
 * @param \EDD\Database\Queries\Order $query   Query class.
 *
 * @since 3.7.1
 * @return array
 */
function edd_sl_filter_orders_list_table_query( $clauses, $query ) {
	// Make sure we only run this on the orders admin table.
	if ( ! function_exists( 'edd_is_admin_page' ) || ! edd_is_admin_page( 'payments', 'list-table' ) ) {
		return $clauses;
	}

	$meta_key = ! empty( $_GET['meta_key'] ) ? sanitize_key( $_GET['meta_key'] ) : false;
	if ( empty( $meta_key ) || ! in_array( $meta_key, array( '_edd_sl_upgraded_payment_id', '_edd_sl_is_renewal' ) ) ) {
		return $clauses;
	}

	global $wpdb;

	$clauses['join'] .= $wpdb->prepare(
		" INNER JOIN {$wpdb->edd_ordermeta} sl_om ON sl_om.meta_key = %s AND sl_om.edd_order_id = {$query->table_alias}.id ",
		$meta_key
	);

	return $clauses;
}
add_filter( 'edd_orders_query_clauses', 'edd_sl_filter_orders_list_table_query', 10, 2 );

/**
 * Modifies the orders list table status column to show an icon for upgrades and renewals.
 *
 * @since 3.8.11
 *
 * @param string $status      The status output.
 * @param int    $order_id    The order ID.
 * @param string $column_name The column name.
 *
 * @return string
 */
function edd_sl_filter_order_list_table_status( $status, $order_id, $column_name ) {
	if ( ! class_exists( 'EDD\Utils\StatusBadge' ) ) {
		return $status;
	}

	if ( 'status' !== $column_name ) {
		return $status;
	}

	$order = edd_get_order( $order_id );

	// If the order status is `edd_subscription`, it's a renewal. We don't need to alter the status output.
	if ( 'edd_subscription' === $order->status ) {
		return $status;
	}

	$icon = '';

	// Determine if this was an upgrade or renewal.
	$upgrade = edd_get_order_meta( $order_id, '_edd_sl_upgraded_payment_id', true );
	$renewal = edd_get_order_meta( $order_id, '_edd_sl_is_renewal', true );

	// If this wasn't an upgrade or renewal, just return.
	if ( empty( $upgrade ) && empty( $renewal ) ) {
		return $status;
	}

	if ( ! empty( $upgrade ) ) {
		$icon = 'arrow-up-alt';
	}

	if ( ! empty( $renewal ) ) {
		$icon = 'update-alt';
	}

	$status_badge_args = array(
		'status' => $order->status,
		'label'  => edd_get_payment_status_label( $order->status ),
		'icon'   => $icon,
		'class'  => "edd-admin-order-status-badge--{$order->status}",
	);

	$status_badge = new EDD\Utils\StatusBadge( $status_badge_args );

	return $status_badge->get();
}
add_filter( 'edd_payments_table_column', 'edd_sl_filter_order_list_table_status', 10, 3 );
