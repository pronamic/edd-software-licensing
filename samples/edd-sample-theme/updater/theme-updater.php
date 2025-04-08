<?php
/**
 * Easy Digital Downloads Theme Updater
 *
 * @package EDD Sample Theme
 */

// Includes the files needed for the theme updater
if ( ! class_exists( 'EDD_Theme_Updater_Admin' ) ) {
	include dirname( __FILE__ ) . '/theme-updater-admin.php';
}

// Loads the updater classes
$updater = new EDD_Theme_Updater_Admin(
	// Config settings
	array(
		'remote_api_url' => 'https://easydigitaldownloads.com', // Site where EDD is hosted
		'item_name'      => 'Theme Name', // Name of theme
		'theme_slug'     => 'theme-slug', // Theme slug
		'version'        => '1.0.0', // The current version of this theme
		'author'         => 'Easy Digital Downloads', // The author of this theme
		'download_id'    => '', // Optional, used for generating a license renewal link
		'renew_url'      => '', // Optional, allows for a custom license renewal link
		'beta'           => false, // Optional, set to true to opt into beta versions
		'item_id'        => '',
	),
	// Strings
	array(
		'theme-license'             => __( 'Theme License', 'edd-sample-theme' ),
		'enter-key'                 => __( 'Enter your theme license key.', 'edd-sample-theme' ),
		'license-key'               => __( 'License Key', 'edd-sample-theme' ),
		'license-action'            => __( 'License Action', 'edd-sample-theme' ),
		'deactivate-license'        => __( 'Deactivate License', 'edd-sample-theme' ),
		'activate-license'          => __( 'Activate License', 'edd-sample-theme' ),
		'status-unknown'            => __( 'License status is unknown.', 'edd-sample-theme' ),
		'renew'                     => __( 'Renew?', 'edd-sample-theme' ),
		'unlimited'                 => __( 'unlimited', 'edd-sample-theme' ),
		'license-key-is-active'     => __( 'License key is active.', 'edd-sample-theme' ),
		/* translators: the license expiration date */
		'expires%s'                 => __( 'Expires %s.', 'edd-sample-theme' ),
		'expires-never'             => __( 'Lifetime License.', 'edd-sample-theme' ),
		/* translators: 1. the number of sites activated 2. the total number of activations allowed. */
		'%1$s/%2$-sites'            => __( 'You have %1$s / %2$s sites activated.', 'edd-sample-theme' ),
		'activation-limit'          => __( 'Your license key has reached its activation limit.', 'edd-sample-theme' ),
		/* translators: the license expiration date */
		'license-key-expired-%s'    => __( 'License key expired %s.', 'edd-sample-theme' ),
		'license-key-expired'       => __( 'License key has expired.', 'edd-sample-theme' ),
		/* translators: the license expiration date */
		'license-expired-on'        => __( 'Your license key expired on %s.', 'edd-sample-theme' ),
		'license-keys-do-not-match' => __( 'License keys do not match.', 'edd-sample-theme' ),
		'license-is-inactive'       => __( 'License is inactive.', 'edd-sample-theme' ),
		'license-key-is-disabled'   => __( 'License key is disabled.', 'edd-sample-theme' ),
		'license-key-invalid'       => __( 'Invalid license.', 'edd-sample-theme' ),
		'site-is-inactive'          => __( 'Site is inactive.', 'edd-sample-theme' ),
		/* translators: the theme name */
		'item-mismatch'             => __( 'This appears to be an invalid license key for %s.', 'edd-sample-theme' ),
		'license-status-unknown'    => __( 'License status is unknown.', 'edd-sample-theme' ),
		'update-notice'             => __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'edd-sample-theme' ),
		'error-generic'             => __( 'An error occurred, please try again.', 'edd-sample-theme' ),
	)
);
