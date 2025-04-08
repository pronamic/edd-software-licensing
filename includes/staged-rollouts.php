<?php
/**
 * Staged Rollouts
 *
 * @package   edd-software-licensing
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.8
 */

/**
 * Process staged rollouts for user when edd_sl_license_response is called
 *
 * @since 3.8
 *
 * @param array           $response      Response.
 * @param EDD_SL_Download $download      Download object.
 * @param bool            $download_beta Whether there is a beta download available.
 * @param array           $data          Request data sent to check the item.
 *
 * @return array
 */
function edd_sl_staged_rollouts( $response, $download, $download_beta, $data ) {
	// Get customer ID from the license ID.
	$original_response_ver = $response['new_version'];
	$license_key           = isset( $data['license'] ) ? esc_attr( $data['license'] ) : false;

	if ( ! $license_key ) {
		return $response;
	}

	// Get the WordPress user ID.
	$license = EDD_Software_Licensing::instance()->get_license( $license_key, true );
	if ( false === $license ) {
		return $response;
	}

	$customer_id = isset( $license->customer_id ) ? absint( $license->customer_id ) : false;

	if ( empty( $data['item_name'] ) ) {
		$data['item_name'] = $download->get_name();
	}

	// Check if EDD download has Staged Rollouts enabled.
	$sr_enabled = get_post_meta( $download->ID, 'edd_sr_enabled', true ) ? true : false;

	if ( ! $sr_enabled ) {
		return $response;
	}

	// Get staged variables for the download.
	$identifier = $license_key;
	if ( ! empty( $data['url'] ) ) {
		$identifier .= $data['url'];
	}

	/**
	 * Filters the identifier used to generate the number for this request.
	 *
	 * @since 3.8.1
	 *
	 * @param string               $identifier Identifier used to generate the number.
	 * @param EDD_SL_License|false $license    License key object for this request.
	 * @param array                $data       Request data sent to check the item.
	 * @param array                $response   Response being sent back to the user.
	 */
	$identifier = apply_filters( 'edd_sl_staged_rollout_identifier', $identifier, $license, $data, $response );

	$staged_number   = edd_sl_generate_number_for_string( $identifier );
	$batch_enabled   = get_post_meta( $download->ID, 'edd_sr_batch_enabled', true ) ? true : false;
	$batch_min       = 1;
	$batch_max       = get_post_meta( $download->ID, 'edd_sr_batch_max', true );
	$version_enabled = get_post_meta( $download->ID, 'edd_sr_version_enabled', true ) ? true : false;
	$version         = get_post_meta( $download->ID, 'edd_sr_version', true );
	$is_above_below  = get_post_meta( $download->ID, 'edd_sr_version_limit', true );

	edd_debug_log( 'EDD Staged Rollouts: Staged number is ' . $staged_number . ' for identifier ' . $identifier );

	/**
	 * What could be used to deliver a different version?
	 *
	 * - staged number: $staged_number - each customer has a persistant, random number between 1 and 100
	 * - customer ID: $customer_id
	 * - URL: $data['url'] - full URL as in home()
	 * - current installed version: $data['version']
	 * - new version: $response['new_version']
	 * - download ID: $data['item_id'] - if given
	 * - plugin slug: $data['slug'] - slug of the plugin directory, always included in the EDD Updater class, could be different from the slug used for the download product on the website and not reliable if customers renamed the plugin directory
	 * - download slug: $response['slug'] - if the user request sent "plugin slug" then this value is used here. Otherwise, will use the download slug in the plugin store
	 */

	/**
	 * Test case:
	 * Geo Targeting add-on with new version v1.3.3
	 * - deliver only to batch 1-50
	 * - slug 'advanced-ads-geo'
	 * - versions are different from each other
	 */
	// if( isset( $response['slug'] ) && 'advanced-ads-geo' === $response['slug']
	//   && isset( $data['version'] ) && isset( $response['new_version'] )
	//   && $data['version'] !== $response['new_version'] ) {
	if (
		isset( $data['version'] ) && isset( $response['new_version'] )
		&& $data['version'] !== $response['new_version']
	) {

		if ( $batch_enabled ) {
			/**
			 * Filters whether this site is eligible to receive the new version.
			 * By default, this is true if the `$staged_number` is greater than or equal to
			 * the minimum, and less than or equal to the maximum.
			 *
			 * @since 3.8.1
			 *
			 * @param bool  $eligible_for_rollout Whether they are eligible.
			 * @param int   $staged_number        Number generated for this request.
			 * @param int   $batch_min            Required minimum number.
			 * @param int   $batch_max            Maximum allowed number.
			 * @param EDD_SL_License|false        License key object associated with this request.
			 * @param array $data                 Request data sent to check the item.
			 * @param array $response             Response being sent back to the user.
			 */
			$eligible_for_rollout = apply_filters(
				'edd_sl_staged_rollout_eligible_for_batch_update',
				$batch_min <= $staged_number && $staged_number <= $batch_max,
				$staged_number,
				$batch_min,
				$batch_max,
				$license,
				$data,
				$response
			);

			if ( $eligible_for_rollout ) {
				// This site is within the specified range and gets the update.
				edd_debug_log( "EDD Staged Rollouts: Customer $customer_id, batch # $staged_number, requested update information for {$data['item_name']} to version {$response['new_version']}" );
			} else {
				/*
				 * This site is outside of the specified range, so they do not get the update.
				 * We set `new_version` to the version number they provided via the API
				 * (essentially giving them no update).
				 */
				$version_for_site        =  isset( $data['version'] ) ? $data['version'] : false;
				$response['new_version'] = $version_for_site;
				edd_debug_log( "EDD Staged Rollouts: Customer $customer_id, batch # $staged_number, keeps version {$version_for_site} for {$data['item_name']}" );
			}
		}

		if ( $version_enabled ) {
			if ( 0 == $is_above_below && ( version_compare( $data['version'], $version, '<=' ) ) ) {
				$response['new_version'] = $original_response_ver;
				edd_debug_log( "EDD Staged Rollouts: Customer's {$data['item_name']} version {$data['version']} is less than or equal to the recommended version ($version) and will see the update" );
			} elseif ( 1 == $is_above_below && ( version_compare( $data['version'], $version, '>=' ) ) ) {
				$response['new_version'] = $original_response_ver;
				edd_debug_log( "EDD Staged Rollouts: Customer's {$data['item_name']} version {$data['version']} is greater than or equal to the recommended version ($version) and will see the update" );
			} else {
				// reset version since customer's version doesn't meet the minimum version level
				edd_debug_log( "EDD Staged Rollouts: Customer's {$data['item_name']} version {$data['version']} doesn't meet the version level for the update" );
				$response['new_version'] = $data['version'];
			}
		}
	}

	/**
	 * Good to know about the response:
	 *
	 * - 'new_version' is used by WordPress to determine if there is an update available
	 * - 'stable_version' could be omitted
	 * - 'name' shows up when the user clicks on "Show version x.y.z details", not in the plugin or updates list
	 */
	return $response;
}

/**
 * The priority needs to be higher than 10. Otherwise, it could happen that edd_sl_readme_modify_license_response() overwrites the response, if that feature is used
 */
add_filter( 'edd_sl_license_response', 'edd_sl_staged_rollouts', 11, 4 );

/**
 * Generates a random number between 1 and 100 for a given string (likely
 * a license key + URL combination).
 *
 * @since 3.8
 *
 * @param string $identifier
 *
 * @return int
 */
function edd_sl_generate_number_for_string( $identifier ) {
	$hash = crc32( $identifier );
	$number = abs( $hash % 100 );

	/**
	 * Filters the generated number.
	 *
	 * @since 3.8.1
	 *
	 * @param int    $number     Integer generated from the identifier.
	 * @param string $identifier Identifier unique to this request.
	 */
	return (int) apply_filters( 'edd_sl_staged_rollout_number', $number, $identifier );
}
