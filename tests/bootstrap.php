<?php

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME'] = '';
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

define( 'EDD_USE_PHP_SESSIONS', false );

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../edd-software-licenses.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

echo "Installing Easy Digital Downloads...\n";
activate_plugin( 'Easy-Digital-Downloads/easy-digital-downloads.php' );

echo "Installing Software Licensing...\n";
activate_plugin( 'EDD-Software-Licensing/edd-software-licenses.php' );

// Install Commissions

global $current_user, $edd_options;
$edd_options = get_option( 'edd_settings' );

$current_user = new WP_User(1);
$current_user->set_role('administrator');
wp_update_user( array( 'ID' => 1, 'first_name' => 'Admin', 'last_name' => 'User' ) );

// Include helpers
