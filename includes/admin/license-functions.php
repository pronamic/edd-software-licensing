<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Register a view for the single license view
 *
 * @since  3.5
 * @param  array $views An array of existing views
 * @return array        The altered list of views
 */
function edd_sl_register_default_license_views( $views ) {
	$default_views = array(
		'overview' => 'edd_sl_licenses_view',
		'logs'     => 'edd_sl_licenses_logs_view',
		'delete'   => 'edd_sl_licenses_delete_view'
	);

	return array_merge( $views, $default_views );
}
add_filter( 'edd_sl_license_views', 'edd_sl_register_default_license_views', 1, 1 );


/**
 * Register a tab for the single license view
 *
 * @since  3.5
 * @param  array $tabs An array of existing tabs
 * @return array       The altered list of tabs
 */
function edd_sl_register_default_license_tabs( $tabs ) {

	$default_tabs = array(
		'overview' => array( 'dashicon' => 'dashicons-lock', 'title' => __( 'Details', 'edd_sl' ) ),
		'logs'     => array( 'dashicon' => 'dashicons-book', 'title' => __( 'Logs', 'edd_sl' ) ),
	);

	return array_merge( $tabs, $default_tabs );
}
add_filter( 'edd_sl_license_tabs', 'edd_sl_register_default_license_tabs', 1, 1 );


/**
 * Register the Delete icon as late as possible so it's at the bottom
 *
 * @since  3.5
 * @param  array $tabs An array of existing tabs
 * @return array       The altered list of tabs, with 'delete' at the bottom
 */
function edd_sl_register_delete_license_tab( $tabs ) {
	$tabs['delete'] = array( 'dashicon' => 'dashicons-trash', 'title' => __( 'Delete', 'edd_sl' ) );

	return $tabs;
}
add_filter( 'edd_sl_license_tabs', 'edd_sl_register_delete_license_tab', PHP_INT_MAX, 1 );
