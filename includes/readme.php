<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Parse the ReadMe URL
 *
 * @since  2.4
 *
 * @param  string $url URL of the readme.txt file
 *
 * @return array|bool  Processed readme.txt
 */
function _edd_sl_readme_parse( $url = '' ) {

	require_once EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-parser.php';

	$request = wp_remote_get(
		$url, array(
			'timeout'         => 15,
			'sslverify'       => false,
			'sslcertificates' => null,
		)
	);

	if ( ! empty( $request ) && ! is_wp_error( $request ) ) {

		$body = $request['body'];

		$Parser = new EDD_SL_Readme_Parser( $body );

		return $Parser->parse_data();
	}

	return false;
}


/**
 * Fetch the readme.txt data from cache or fresh.
 *
 * Use `cache` query string to force a fresh download of the readme.
 *
 * @since  2.4
 * @uses   _edd_sl_readme_parse()    Process the readme data
 *
 * @param  string $readme_url URL of the readme.
 *
 * @return boolean|array             False if not exists, array of data if exists.
 */
function _edd_sl_get_readme_data( $readme_url = '', $post_id = null ) {

	// Use cached readme for this version
	$readme = get_transient( _edd_sl_readme_get_transient_key( $post_id ) );

	// If the cache doesn't exist or overridden
	if ( empty( $readme ) || isset( $_REQUEST['cache'] ) ) {

		if ( $readme = _edd_sl_readme_parse( $readme_url ) ) {

			// Store the parsed readme for a week.
			set_transient( _edd_sl_readme_get_transient_key( $post_id ), $readme, HOUR_IN_SECONDS * 6 );
		}
	}

	return $readme;
}

/**
 * Tap into the filter to use data from a readme.txt file
 *
 * @since  2.4
 * @since  3.5 Added $download_beta parameter
 * @see    EDD_Software_Licensing::get_latest_version_remote()
 *
 * @param  array   $original_response License response array
 * @param  WP_Post $download          Post object of the Download item
 * @param  bool    $download_beta     If true, the current request is asking for a beta version
 *
 * @return array                    Modified array, if readme exists. Otherwise, original array is returned.
 */
function edd_sl_readme_modify_license_response( $original_response = array(), $download = null, $download_beta = false ) {
/*
	if ( is_admin() || defined( 'DOING_AJAX' ) ) {
		// Prevent errors and send headers
		ini_set( 'display_errors', 0 );
		ini_set( 'log_errors', 1 );
		error_reporting( 0 );
		define( 'DOING_AJAX', true );
		@header( 'Content-type: text/plain' );
		@send_nosniff_header();
	}
*/
	// Get the URL to use in the WP.org validator
	$readme_url = get_post_meta( $download->ID, '_edd_readme_location', true );

	// If the URL doesn't exist, get outta here.
	if ( empty( $readme_url ) ) {
		return $original_response;
	}

	// Fetch the cached/fresh readme data
	$readme = _edd_sl_get_readme_data( $readme_url, $download->ID );

	// The readme didn't exist or process. Return existing response.
	if ( empty( $readme ) ) {
		return $original_response;
	}

	$response = $original_response;

	// Modify the homepage linked to in the Update Notice
	$response['homepage'] = edd_sl_readme_get_download_homepage( $download->ID );

	// Get download banner image
	$response['banners'] = edd_sl_readme_get_download_banners( $download->ID );

	// Set the new version
	$response['new_version'] = edd_software_licensing()->get_latest_version( $download->ID );
	if ( get_post_meta( $download->ID, '_edd_sl_beta_enabled', true ) && $download_beta ) {
		$beta_version = edd_software_licensing()->get_beta_download_version( $download->ID );
		if ( version_compare( $beta_version, $response['new_version'], '>' ) ) {
			$response['new_version'] = $beta_version;
		}
	}

	// The original response sections
	$response['sections'] = maybe_unserialize( @$response['sections'] );

	// Get the override readme sections settings
	if ( $readme_sections = get_post_meta( $download->ID, '_edd_readme_sections', true ) ) {

		// The beta version has its own changelog that should be used
		if ( $download_beta ) {
			unset( $readme_sections['changelog'] );
		}

		// We loop through the settings sections and make overwrite the
		// existing sections with the custom readme.txt sections.
		foreach ( (array) $readme_sections as $section ) {
			if ( array_key_exists( $section, $readme['sections'] ) ) {
				$response['sections'][ $section ] = $readme['sections'][ "$section" ];
			}
		}
	}

	// Reserialize it
	$response['sections'] = serialize( $response['sections'] );

	if ( ! empty( $readme['tested_up_to'] ) ) {
		$response['tested'] = $readme['tested_up_to'];
	}

	// Get the override readme meta settings
	if ( $readme_meta = get_post_meta( $download->ID, '_edd_readme_meta', true ) ) {

		// We loop through the settings sections and make overwrite the
		// existing sections with the custom readme.txt sections.
		foreach ( (array) $readme_meta as $meta ) {
			if ( array_key_exists( $meta, $readme ) ) {
				$response[ $meta ] = $readme[ "$meta" ];
			}
		}
	}

	if ( get_post_meta( $download->ID, '_edd_readme_plugin_added', true ) ) {
		$response['added'] = date( 'Y-m-d', strtotime( $download->post_date_gmt, current_time( 'timestamp' ) ) );
	}

	if ( get_post_meta( $download->ID, '_edd_readme_plugin_last_updated', true ) ) {
		$response['last_updated'] = apply_filters( 'edd_sl_readme_last_updated', human_time_diff( strtotime( $download->post_modified_gmt, current_time( 'timestamp' ) ), current_time( 'timestamp', 1 ) ) . ' ago', $download );
	}

	// Remove empty items
	$response = array_filter( $response );

	// Filter this if you want to.
	return apply_filters( 'edd_sl_license_readme_response', $response, $download, $readme, $download_beta );

}

add_filter( 'edd_sl_license_response', 'edd_sl_readme_modify_license_response', 10, 3 );

/**
 * Get the custom homepage for the download. If not set, return download item URL.
 *
 * @since  2.4
 *
 * @param  int $download_id Download ID
 *
 * @return string              URL of download.
 */
function edd_sl_readme_get_download_homepage( $download_id ) {

	$custom_homepage = get_post_meta( $download_id, '_edd_readme_plugin_homepage', true );

	return empty( $custom_homepage ) ? get_permalink( $download_id ) : $custom_homepage;

}

/**
 * Get an array of banner images.
 *
 * The array can be empty; WordPress will check whether it is set in wp-admin/includes/plugin-install.php
 * The banner image URLs are sanitized on WordPress' end
 *
 * @param int     $download_id Download ID
 * @param boolean $serialize   Whether to serialize the banner array, which is required for backward compatibility with
 *                             earlier EDDSL versions
 *
 * @return array Banners array with `high` and `low` keys with banner image URLs for the download, if set
 */
function edd_sl_readme_get_download_banners( $download_id, $serialize = true ) {

	$plugin_banner_high = get_post_meta( $download_id, '_edd_readme_plugin_banner_high', true );
	$plugin_banner_low  = get_post_meta( $download_id, '_edd_readme_plugin_banner_low', true );

	$banners = array(
		'high' => $plugin_banner_high,
		'low'  => $plugin_banner_low,
	);

	return $serialize ? serialize( $banners ) : $banners;
}

/**
 * The readme.txt files are cached. This outputs the cache status and a button to clear the cache.
 */
function edd_sl_render_readme_cache_status() {

	$deleted = null; // delete_transient returns false, so we don't want to use false
	if ( isset( $_REQUEST['refresh'] ) && $_REQUEST['refresh'] === 'readmecache' ) {
		$deleted = delete_transient( _edd_sl_readme_get_transient_key() );
	}

	if ( ! empty( $deleted ) ) {
		echo '<div class="updated inline">';
		echo wpautop( __( 'The cache has been deleted.', 'edd_sl' ) );
		echo '</div>';
	} elseif ( ! is_null( $deleted ) ) {
		echo '<div class="error inline">';
		echo wpautop( __( 'There was an error when deleting the cache. It may have already been deleted.', 'edd_sl' ) );
		echo '</div>';
	}

	$readme = get_transient( _edd_sl_readme_get_transient_key() );

	// The readme has been cached. Show the reset
	if ( ! empty( $readme ) ) {
		$message = sprintf( __( 'the file has been cached. %1$sClear cached file%2$s', 'edd_sl' ), '<a href="' . esc_url( add_query_arg( array( 'refresh' => 'readmecache' ) ) ) . '#edd_readme_cache" class="button button-secondary">', '</a>' );
	} else {
		$message = __( 'the file is not cached.', 'edd_sl' );
	}

	echo '<div class="alignright" id="edd_readme_cache">';
	printf( wpautop( '<strong>%s</strong> %s' ), __( 'Cache:', 'edd_sl' ), $message );
	echo '</div>';
}

/**
 * Get the cache key for the cached readme
 *
 * @param  int $post_id The ID of the download
 *
 * @return string          Transient key
 */
function _edd_sl_readme_get_transient_key( $post_id = null ) {

	global $post;

	// Get the download ID
	$post_id = empty( $post_id ) ? $post->ID : $post_id;

	$download = new EDD_SL_Download( $post_id );

	// Get the version of the plugin
	$version = empty( $version ) ? $download->get_version() : $version;

	// Use the URL as part of the transient key.
	$url_hash = hash( 'adler32', get_post_meta( $post_id, '_edd_readme_location', true ) );

	return sprintf( 'readme_%d_%s_%s', $post_id, $version, $url_hash );
}

/**
 * Verify that a string is structured as an URL.
 *
 * It checks if after parsing the URL that the `scheme` and `host` keys are set
 * and that the scheme is either `http` or `https`.
 *
 * @param  string $url String to check
 *
 * @return boolean         True: URL is valid, False: URL is not valid.
 */
function edd_sl_is_valid_readme_url( $url ) {

	// Test if the $url string is formatted as an URL
	$test_url = parse_url( $url );

	return ( isset( $test_url['scheme'] ) && isset( $test_url['host'] ) && in_array(
		$test_url['scheme'], array(
			'http',
			'https',
		)
	) );
}
