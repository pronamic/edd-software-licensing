<?php
/**
 * Installation
 *
 * @package   edd-software-licensing
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.8
 */

/**
 * Installs Software Licensing.
 *
 * @since unknown
 */
function edd_sl_install() {

	if ( ! function_exists( 'edd_set_upgrade_complete' ) ){
		require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrade-functions.php';
	}

	// When new upgrade routines are added, mark them as complete on fresh install.
	$upgrade_routines = array(
		'sl_add_bundle_licenses',
		'sl_deprecate_site_count_meta',
		'sl_remove_legacy_license_id_meta',
	);

	foreach ( $upgrade_routines as $upgrade ) {
		edd_set_upgrade_complete( $upgrade );
	}

	require_once EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-roles.php';
	$roles = new EDD_SL_Roles();
	$roles->add_caps();

}
