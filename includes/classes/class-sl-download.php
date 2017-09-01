<?php
/**
 * EDD_SL_Product
 *
 * This class is for working with downloads within the licensing scope
 *
 * @package     EDDSoftwareLicensing
 * @subpackage  Classes/License
 * @copyright   Copyright (c) 2016, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_SL_Download extends EDD_Download {

	/**
	 * Return a raw post title
	 *
	 * See https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1074
	 *
	 * @since 3.5.16
	 * @return string
	 */
	public function get_name() {
		return get_post_field( 'post_title', $this->ID, 'raw' );
	}

	/**
	 * Return if a download has licensing enabled.
	 *
	 * @since 3.5
	 * @return bool
	 */
	public function licensing_enabled() {
		return (bool) get_post_meta( $this->ID, '_edd_sl_enabled', true );
	}

	/**
	 * Get the activation limit for a licensed download.
	 *
	 * @since 3.5
	 * @param bool $price_id
	 *
	 * @return int|boolean
	 */
	public function get_activation_limit( $price_id = false ) {
		$limit = false;

		if ( false === $price_id ) {
			$limit = get_post_meta( $this->ID, '_edd_sl_limit', true );
		} else {
			$price_limit = $this->get_price_activation_limit( $price_id );

			if( false !== $price_limit ) {
				$limit = $price_limit;
			}
		}

		return apply_filters( 'edd_sl_download_license_limit', $limit, $this->ID, $price_id );
	}

	/**
	 * Get the activation limit for a price ID.
	 *
	 * @since 3.5
	 * @param $price_id
	 *
	 * @return bool|int
	 */
	public function get_price_activation_limit( $price_id ) {
		$prices = $this->get_prices();

		if ( isset( $prices[ $price_id ][ 'license_limit' ] ) ) {
			return absint( $prices[ $price_id ][ 'license_limit' ] );
		}

		return false;
	}

	/**
	 * Retrieve a pre-defined license key for this download.
	 *
	 * @since 3.5
	 *
	 * @return string|boolean
	 */
	public function get_new_license_key() {
		$keys = get_post_meta( $this->ID, '_edd_sl_keys', true );

		if( ! $keys ) {
			return false; // no available keys
		}

		$keys = array_map( 'trim', explode( "\n", $keys ) );
		$key  = $keys[0];
		unset( $keys[0] );
		update_post_meta( $this->ID, '_edd_sl_keys', implode( "\n", $keys ) );
		return $key;
	}

	/**
	 * Return if a download is a lifetime license.
	 *
	 * @since 3.5
	 *
	 * @return bool
	 */
	public function is_lifetime() {
		return (bool) get_post_meta( $this->ID, 'edd_sl_download_lifetime', true );
	}

	/**
	 * Return if a download price ID is a lifetime license.
	 *
	 * @since 3.5
	 * @param $price_id
	 *
	 * @return bool
	 */
	public function is_price_lifetime( $price_id ) {
		$prices = $this->get_prices();

		if ( ! empty( $prices[ $price_id ][ 'is_lifetime' ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the unit for licenses as days, weeks, months, or years
	 *
	 * @since 3.5
	 * @return mixed
	 */
	public function get_expiration_unit() {
		$exp_unit   = get_post_meta( $this->ID, '_edd_sl_exp_unit', true );
		return $exp_unit;
	}

	/**
	 * Return a expiration unit that is consistent with the length unit.
	 *
	 * @since 3.5.4
	 * @return string
	 */
	public function get_expiration_unit_nicename() {
		$exp_unit = $this->get_expiration_unit();

		switch( $exp_unit ) {
			case 'years':
				$singular = __( 'Year', 'edd_sl' );
				$plural   = __( 'Years', 'edd_sl' );
				break;

			case 'months':
				$singular = __( 'Month', 'edd_sl' );
				$plural   = __( 'Months', 'edd_sl' );
				break;

			case 'weeks':
				$singular = __( 'Week', 'edd_sl' );
				$plural   = __( 'Weeks', 'edd_sl' );
				break;

			case 'days':
				$singular = __( 'Day', 'edd_sl' );
				$plural   = __( 'Days', 'edd_sl' );
				break;
		}

		return ucfirst( _n( $singular, $plural, $this->get_expiration_length(), 'edd_sl' ) );
	}

	/**
	 * Return the numeric length of the download licenses.
	 *
	 * @since 3.5
	 * @return mixed
	 */
	public function get_expiration_length() {
		$exp_length = get_post_meta( $this->ID, '_edd_sl_exp_length', true );
		return $exp_length;
	}

}