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
	);

	if ( current_user_can( 'delete_licenses' ) ) {
		$default_views['delete'] = 'edd_sl_licenses_delete_view';
	}

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
	if ( current_user_can( 'delete_licenses' ) ) {
		$tabs['delete'] = array( 'dashicon' => 'dashicons-trash', 'title' => __( 'Delete', 'edd_sl' ) );
	}

	return $tabs;
}
add_filter( 'edd_sl_license_tabs', 'edd_sl_register_delete_license_tab', PHP_INT_MAX, 1 );

/**
 * Forces the Cache-Control header on our license views in admin to send the no-store header
 * which prevents the back-forward cache (bfcache) from storing a copy of this page in local
 * cache. This helps make sure that page elements modified via AJAX and DOM manipulations aren't
 * incorrectly shown as if they never changed.
 *
 * See: https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1346#issuecomment-382159918
 *
 * @since 3.6.1
 * @param array $headers An array of nocache headers.
 *
 * @return array
 */
function _edd_sl_bfcache_buster( $headers ) {
	if ( ! is_admin() ) {
		return $headers;
	}

	$post_type  = isset( $_GET['post_type'] )  ? strtolower( $_GET['post_type'] )  : false;
	$page       = isset( $_GET['page'] )       ? strtolower( $_GET['page'] )       : false;

	if( false === $post_type || false === $page )  {
		return $headers;
	}

	if ( 'download' === $post_type && 'edd-licenses' === $page ) {
		$headers['Cache-Control'] = 'no-cache, must-revalidate, max-age=0, no-store';
	}

	return $headers;
}
add_filter( 'nocache_headers', '_edd_sl_bfcache_buster', 10, 1 );
