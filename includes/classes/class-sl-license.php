<?php
/**
 * License
 *
 * This class is for working with licenses in EDD Software Licensing
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

final class EDD_SL_License {

	/**
	 * The Payment ID
	 *
	 * @since  3.5
	 * @var    integer
	 */
	public $ID        = 0;
	protected $_ID    = 0;
	protected $exists = false;

	protected $parent      = 0;
	protected $post_parent = 0; // Needed for backwards compatibility and magic __get
	protected $name        = null;
	protected $key         = null;

	protected $user_id     = null;
	protected $customer_id = null;
	protected $payment_id  = null;
	protected $payment_ids = null;
	protected $cart_index  = null;

	protected $download;
	protected $download_id = null;
	protected $price_id    = null;

	protected $activation_limit = null;
	protected $sites            = null;
	protected $activation_count = null;

	protected $expiration  = null;
	protected $is_lifetime = null;

	protected $status      = null;
	protected $post_status = null;
	protected $old_status  = null;

	protected $child_licenses = null;

	/**
	 * EDD_SL_License constructor.
	 *
	 * @param int $license_id The license post_id or license key to instantiate.
	 */
	public function __construct( $license_id = false ) {

		if ( ! empty( $license_id ) ) {
			$license = get_post( $license_id );
			if ( ! is_a( $license, 'WP_Post' ) || 'edd_license' !== $license->post_type ) {
				$this->exists = false;
				return;
			}
		} else {
			$this->exists = false;
			return;
		}

		$this->setup( $license );
	}

	/**
	 * Magic GET function
	 *
	 * @since  3.5
	 *
	 * @param  string $key  The property.
	 * @pram   mixed  $args Array of possible arguments to pass.
	 * @return mixed        The value.
	 */
	public function __get( $key ) {

		if ( method_exists( $this, 'get_' . $key ) ) {

			$value = call_user_func( array( $this, 'get_' . $key ) );

		} else {

			$value = $this->$key;

		}

		return $value;
	}

	/**
	 * Magic SET function
	 *
	 * Sets up the pending array for the save method.
	 *
	 * @since  3.5
	 *
	 * @param string $key   The property name.
	 * @param mixed  $value The value of the property.
	 */
	public function __set( $key, $value ) {
		$ignore = array( '_ID' );

		if ( $key === 'status' ) {
			$this->old_status = $this->status;
		}

		if ( ! in_array( $key, $ignore ) && method_exists( $this, 'set_' . $key ) ) {
			$method = 'set_' . $key;
			call_user_func_array( array( $this, $method ), array( $value ) );
		} else {
			$this->update_meta( $key, $value );
		}

		if ( '_ID' !== $key ) {
			$this->$key = $value;
		}

	}

	/**
	 * Magic ISSET function, which allows empty checks on protected elements.
	 *
	 * @since  3.5
	 *
	 * @param  string $name The attribute to get.
	 * @return boolean       If the item is set or not.
	 */
	public function __isset( $name ) {
		if ( property_exists( $this, $name ) ) {
			return false === empty( $this->$name );
		} else {
			return null;
		}
	}

	/**
	 * Given a license WP_Post object, setup the license.
	 *
	 * @since 3.5
	 *
	 * @param $license A WP_Post object of the license.
	 */
	private function setup( $license ) {

		$this->ID   = absint( $license->ID );
		$this->_ID  = absint( $license->ID );
		$this->name = $license->post_title;

		$this->parent      = $license->post_parent;
		$this->status      = $this->get_status();
		$this->post_status = $license->post_status;

		$this->payment_id  = $this->get_payment_id();
		$this->payment_ids = $this->get_payment_ids();
		$this->cart_index  = $this->get_cart_index();

		$this->user_id     = $this->get_user_id();
		$this->customer_id = $this->get_customer_id();

		$this->download_id = $this->get_download_id();
		$this->download    = new EDD_SL_Download( $this->download_id );

		// Need top get the fallback code from edd_software_licensing()->get_price_id() into here.
		$this->price_id = $this->download->has_variable_prices() ? $this->get_price_id() : false;

		$this->key         = $this->get_key();
		$this->expiration  = $this->get_expiration();
		$this->is_lifetime = $this->get_is_lifetime();

		$this->sites            = $this->get_sites();
		$this->activation_count = $this->get_activation_count();
		$this->activation_limit = $this->get_activation_limit();

		$this->exists = true;

	}

	/**
	 * Generate a license key for the download ID provided. Also generates any child license keys if the download is
	 * a bundled product.
	 *
	 * @since 3.5
	 * @param int       $download_id The Download ID to generate a license key for.
	 * @param int       $payment_id  The Payment ID associated with the license.
	 * @param bool|int  $price_id    The Price ID for the item to generate a key for
	 * @param int       $cart_index  The cart index for the key being generated
	 * @param array     $options     Array of options (parent_license_id, activation_limit, is_lifetime, license_length)
	 *
	 * @return array $keys    License keys created during this process
	 */
	public function create( $download_id = 0, $payment_id = 0, $price_id = false, $cart_index = 0, $options = array() ) {
		$keys = array();

		$payment              = new EDD_Payment( $payment_id );
		$purchased_download   = new EDD_SL_Download( $download_id );
		$licensing_enabled    = $purchased_download->licensing_enabled();
		$has_variable_prices  = $purchased_download->has_variable_prices();
		$is_bundle            = $purchased_download->is_bundled_download();
		$bundle_licensing     = ( $is_bundle && $licensing_enabled );
		$existing_license_ids = isset( $options['existing_license_ids'] ) ? $options['existing_license_ids'] : array();
		$parent_license_id    = isset( $options['parent_license_id'] ) ? $options['parent_license_id'] : 0;
		$activation_limit     = isset( $options['activation_limit'] )  ? $options['activation_limit']  : false;
		$license_length       = isset( $options['license_length'] )    ? $options['license_length']    : false;
		$expiration_date      = isset( $options['expiration_date'] )   ? $options['expiration_date']   : false;
		$is_lifetime          = isset( $options['is_lifetime'] ) && ! is_null( $options['is_lifetime'] )   ? $options['is_lifetime']       : null;
		$downloads            = array();

		if ( ! $purchased_download->is_bundled_download() && ! $purchased_download->licensing_enabled() ) {
			return $keys;
		}

		if ( $is_bundle ) {

			if( method_exists( $purchased_download, 'get_variable_priced_bundled_downloads' ) ) {

				$downloads = $purchased_download->get_variable_priced_bundled_downloads( $price_id );

			} else {

				$downloads = $purchased_download->get_bundled_downloads();

			}

			if ( $has_variable_prices ) {
				$activation_limit = false === $activation_limit ? $purchased_download->get_activation_limit( $price_id )  : $activation_limit;
				$is_lifetime      = null === $is_lifetime      ? $purchased_download->is_price_lifetime( $price_id ) : $is_lifetime;
			} else {
				$is_lifetime      = null === $is_lifetime      ? $purchased_download->is_lifetime() : $is_lifetime;
			}

		} else {

			if ( $has_variable_prices ) {
				$is_lifetime = null === $is_lifetime ? $purchased_download->is_price_lifetime( $price_id ) : $is_lifetime;
			} else {
				$is_lifetime = null === $is_lifetime ? $purchased_download->is_lifetime() : $is_lifetime;
			}

		}

		// No option was supplied, and we didn't find anything about it being lifetime, just set to false.
		$is_lifetime = null === $is_lifetime ? false : $is_lifetime;

		if ( $purchased_download->licensing_enabled() && empty( $this->key ) ) {

			$needs_license = true;

			if ( $is_bundle && ! $bundle_licensing ) {
				$needs_license = false;
			}

			if ( $needs_license ) {
				$license_title = $purchased_download->get_name() . ' - ' . $payment->email;

				$license_args = array(
					'post_type'   => 'edd_license',
					'post_title'  => $license_title,
					'post_status' => 'publish',
					'post_date'   => get_post_field( 'post_date', $payment->ID, 'raw' ),
					'post_parent' => $parent_license_id,
				);

				$this->ID  = wp_insert_post( apply_filters( 'edd_sl_insert_license_args', $license_args ) );

				$keys[] = $this->ID;


				$license_key = $purchased_download->get_new_license_key();

				if( ! $license_key ) {

					// No predefined license key available, generate a random one.
					$license_key = EDD_Software_Licensing()->generate_license_key( $this->ID, $purchased_download->ID, $payment->ID, $cart_index );

				}

				$this->key         = $license_key;
				$this->download_id = $download_id;
				$this->download    = $purchased_download;
				$this->cart_index  = $cart_index;

				// Setup post meta.
				$this->set_download_id( $purchased_download->ID );

				if( false !== $price_id ) {
					$this->set_price_id( $price_id );
				}

				add_post_meta( $this->ID, '_edd_sl_cart_index', $cart_index );
				add_post_meta( $this->ID, '_edd_sl_payment_id', $payment->ID );
				add_post_meta( $this->ID, '_edd_sl_key', $this->key );
				add_post_meta( $this->ID, '_edd_sl_user_id', $payment->user_id );
				add_post_meta( $this->ID, '_edd_sl_status', 'inactive' );

				if ( $parent_license_id && false !== $activation_limit ) {
					add_post_meta( $this->ID, '_edd_sl_limit', $activation_limit );
				}

				// Get the purchase date so we can set the correct license expiration date.
				$payment_meta  = $payment->get_meta();
				$purchase_date = null;
				if ( ! empty( $payment_meta['date'] ) ) {
					$purchase_date = strtotime( $payment_meta['date'], current_time( 'timestamp' ) );
				}

				// Get license length.
				$license_length = empty( $license_length ) ? $this->license_length() : $license_length;

				if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {

					if ( empty( $expiration_date ) ) {
						// Set license expiration date.
						$expiration_date = strtotime( $license_length, $purchase_date );
					}

					if( $expiration_date > strtotime( '+24 hours', current_time( 'timestamp' ) ) ) {
						// Force it to end of day if expiration is more than 24 hours in the future.
						$expiration_date = date( 'Y-n-d 23:59:59', $expiration_date );

						// Convert back into timestamp.
						$expiration_date = strtotime( $expiration_date, current_time( 'timestamp' ) );
					}

					$this->set_expiration( $expiration_date );
				} else {
					$this->set_is_lifetime( true );
				}

				// Only need this for backwards compatible hooks.
				$type = $is_bundle ? 'bundle' : 'default';
				do_action( 'edd_sl_store_license', $this->ID, $purchased_download->ID, $payment->ID, $type );

			}
		}

		$existing_licenses = edd_software_licensing()->get_licenses_of_purchase( $payment_id );
		if ( $is_bundle && $existing_licenses ) {
			$bundled_products = array_map( 'absint', $purchased_download->get_bundled_downloads() );

			foreach( $bundled_products as $bundled_product_id ) {

				if ( in_array( $bundled_product_id, $existing_license_ids ) ) {
					continue;
				}

				// Search the existing license keys on this purchase to see if it belongs to a bundled product
				foreach ( $existing_licenses as $existing_license ) {
					if ( $existing_license->download_id !== $bundled_product_id ) {
						continue;
					}

					$existing_license_ids[] = $existing_license->ID;
				}

			}
		}

		// We're relating some existing license IDs to this license.
		if ( ! empty( $existing_license_ids ) ) {
			foreach ( $existing_license_ids as $convert_to_child ) {
				$new_child_license = edd_software_licensing()->get_license( $convert_to_child );
				$new_child_license->set_parent( $this->ID );

				// Reset the child licenses so that we pull them from the Database again.
				$this->child_licenses = null;
			}
		}

		$child_license_download_ids = array();
		if ( $purchased_download->licensing_enabled() ) {
			$existing_child_licenses    = $this->get_child_licenses();
			$child_license_download_ids = wp_list_pluck( $existing_child_licenses, 'download_id' );
		}

		// If we have a bundle download, process the licenses.
		foreach ( $downloads as $d_id ) {

			$price_id_pos = strpos( $d_id, '_' );
			if( false !== $price_id_pos ) {
				$d_price_id = substr( $d_id, $price_id_pos + 1, strlen( $d_id ) );
			}

			$download = new EDD_SL_Download( $d_id );

			if ( ! $download->licensing_enabled() ) {
				continue;
			}

			if ( in_array( $download->ID, $child_license_download_ids ) ) {
				continue;
			}

			if( false === $price_id_pos ) {
				$d_price_id = edd_get_default_variable_price( $download->ID );
			}

			// Generate a license for a child license.
			$child_license_args = array(
				'activation_limit'  => $activation_limit,
				'is_lifetime'       => $is_lifetime,
				'parent_license_id' => $this->ID,
				'license_length'    => $license_length,
				'expiration_date'   => $expiration_date,
			);

			$child_license = new EDD_SL_License();
			$child_license->create( $download->ID, $payment->ID, $d_price_id, $cart_index, $child_license_args );

			$keys[] = $child_license->ID;

		}

		if ( $purchased_download->licensing_enabled() ) {
			$license_post = get_post( $this->ID );
			$this->setup( $license_post );

			// Load the newly created license into the object cache.
			EDD_Software_Licensing()->get_license( $this->ID );
			$this->exists = true;
		}

		return $keys;
	}

	/**
	 * A helper function to update multiple meta keys at once
	 *
	 * @since 3.5
	 *
	 * @param array $data Key/Value array of meta_key => meta_value
	 * @return array      Array of Meta keys as the index and boolean if that key was updated or not
	 */
	public function update( $data = array() ) {
		$updated = array();
		foreach ( $data as $key => $value ) {
			$updated[ $key ] = $this->update_meta( $key, $value );
		}

		return $updated;
	}

	/**
	 * Renew a license key
	 *
	 * @since 3.5
	 *
	 * @return bool If the license expiration was updated or not.
	 */
	public function renew( $payment_id = 0 ) {

		if ( $this->is_lifetime ) {
			return false;
		}

		do_action( 'edd_sl_pre_license_renewal', $this->ID );

		if ( $this->download->is_lifetime() ) {
			$new_expiration = 'lifetime';
			$updated        = $this->set_is_lifetime( true );
		} else {
			$expiration     = $this->get_expiration();

			if ( $expiration < current_time( 'timestamp' ) ) {
				$expiration = current_time( 'timestamp' );
			}

			$new_expiration = strtotime( '+' . $this->license_length() , $expiration );

			if( $new_expiration > strtotime( '+24 hours', current_time( 'timestamp' ) ) ) {
				// Force it to end of day if expiration is more than 24 hours in the future
				$new_expiration = date( 'Y-m-d 23:59:59', $new_expiration );
				// Convert back into timestamp
				$new_expiration = strtotime( $new_expiration, current_time( 'timestamp' ) );
			}

			$updated        = $this->update_meta( '_edd_sl_expiration', $new_expiration );
		}

		if ( $updated ) {

			if ( 'lifetime' === $new_expiration ) {
				$this->is_lifetime = true;
			} else {
				$this->expiration = $new_expiration;
			}

			if ( ! empty( $payment_id ) ) {
				add_post_meta( $this->ID, '_edd_sl_payment_id', $payment_id );
			}

			$child_licenses = $this->get_child_licenses();
			// If this item has child licenses, renew them as well
			if ( ! empty( $child_licenses ) ) {
				foreach ( $this->child_licenses as $child_license ) {

					if ( $this->is_lifetime ) {
						$child_license->set_is_lifetime( true );
					} else {
						$child_license->renew();
					}
				}
			}

		}

		do_action( 'edd_sl_post_license_renewal', $this->ID, $new_expiration );

		return $updated;
	}

	/**
	 * Enable a license by setting to active or inactive depending on number of sites activated
	 *
	 * @since 3.5
	 * @return bool
	 */
	public function enable() {
		$updated = wp_update_post( array( 'ID' => $this->ID, 'post_status' => 'publish' ) );
		if ( $updated ) {
			$status  = $this->activation_count > 0 ? 'active' : 'inactive';
			$this->update_meta( '_edd_sl_status', $status );
			$this->set_status( $status );

			$child_licenses = $this->get_child_licenses();
			foreach ( $child_licenses as $child_license ) {
				$child_license->enable();
			}
		}

		return (bool) $updated;
	}

	/**
	 * Disable a license
	 *
	 * @since 3.5
	 * @return bool
	 */
	public function disable() {
		$updated = wp_update_post( array( 'ID' => $this->ID, 'post_status' => 'draft' ) );

		if ( $updated ) {
			$this->update_meta( '_edd_sl_status', 'inactive' );
			$this->set_status( 'inactive' );

			$child_licenses = $this->get_child_licenses();
			foreach ( $child_licenses as $child_license ) {
				$child_license->disable();
			}
		}

		return (bool) $updated;
	}

	/**
	 * Get a post meta item for the payment
	 *
	 * @since  3.5
	 *
	 * @param  string  $meta_key The Meta Key
	 * @param  boolean $single   Return single item or array
	 *
	 * @return mixed             The value from the post meta
	 */
	public function get_meta( $meta_key = '', $single = true ) {
		$meta = get_post_meta( $this->ID, $meta_key, $single );

		return apply_filters( 'edd_sl_license_meta_' . $meta_key, $meta, $this->ID );
	}

	/**
	 * Update license meta
	 *
	 * @since 3.5
	 * @param string $meta_key  The Meta Key to update
	 * @param string $value     The value to update
	 * @param string $old_value The old value (optional)
	 *
	 * @return bool|int
	 */
	public function update_meta( $meta_key = '', $value = '', $old_value = '' ) {

		if ( empty( $meta_key ) ) {
			return false;
		}

		$meta_keys = $this->property_map();

		// If the meta_key isn't in the array of matched meta keys, look up by property name.
		if ( ! in_array( $meta_key, $meta_keys ) ) {

			$property_key = $this->get_property_meta_key( $meta_key );

			// If the we cannot find a meta key from a property name, we don't have a valid meta key to update.
			if ( false !== $property_key ) {
				$meta_key = $property_key;
			}
		}

		if ( ! empty( $old_value ) ) {
			$updated = update_post_meta( $this->ID, $meta_key, $value, $old_value );
		} else {
			$updated = update_post_meta( $this->ID, $meta_key, $value );
		}

		return $updated;
	}

	/**
	 * Returns if the license has been generated yet.
	 *
	 * @since 3.5
	 * @return bool
	 */
	public function get_exists() {
		return $this->exists;
	}

	/**
	 * Get the name of the license 'title'
	 *
	 * @since 3.5
	 * @param bool $full If we should return just the product name or the name and price ID name
	 *
	 * @return string
	 */
	public function get_name( $full = true ) {
		$name = $this->name;
		if ( false === $full ) {
			$dash_pos = strrpos( $name, '-' );
			if ( false !== $dash_pos ) {
				$name = substr( $name, 0, $dash_pos );
			}
		}

		return $name;
	}

	/**
	 * Return the 'post_title' attribute, by generating the name
	 *
	 * @since 3.5
	 * @return string
	 */
	private function get_post_title() {
		return $this->get_name();
	}

	/**
	 * Return the post parent, if one exists. Used for parent/child licenses
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_post_parent() {
		return $this->parent;
	}

	/**
	 * Get the User ID associated with this license, if one exists
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_user_id() {
		if ( ! is_null( $this->user_id ) ) {
			return $this->user_id;
		}

		$user_id = $this->get_meta( '_edd_sl_user_id' );

		if ( empty( $user_id ) || $user_id < 0 ) {

			$payment_id = $this->get_payment_id();
			$payment    = new EDD_Payment( $payment_id );
			$user_id    = $payment->user_id;

			if ( ! empty( $user_id ) ) {
				$this->user_id = $user_id;
				$this->update_meta( '_edd_sl_user_id', $user_id );
			}

		}
		$this->user_id = $user_id;

		return (int) $this->user_id;
	}

	/**
	 * Get the customer ID associated with the license
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_customer_id() {
		if ( ! is_null( $this->customer_id ) ) {
			return $this->customer_id;
		}

		$payment = new EDD_Payment( $this->payment_id );
		$this->customer_id = (int) $payment->customer_id;
		return (int) $this->customer_id;
	}

	/**
	 * Get the primary payment ID associated with the license
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_payment_id() {
		if ( ! is_null( $this->payment_id ) ) {
			return $this->payment_id;
		}

		$this->payment_id = $this->get_meta( '_edd_sl_payment_id' );

		return $this->payment_id;
	}

	/**
	 * Get the Payment IDs associated with the license.
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_payment_ids() {
		if ( ! is_null( $this->payment_ids ) ) {
			return $this->payment_ids;
		}

		$this->payment_ids = $this->get_meta( '_edd_sl_payment_id', false );
		sort( $this->payment_ids, SORT_NUMERIC );

		return $this->payment_ids;
	}

	/**
	 * Get the cart index associated with the license
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_cart_index() {
		if ( ! is_null( $this->cart_index ) ) {
			return $this->cart_index;
		}

		$this->cart_index = $this->get_meta( '_edd_sl_cart_index' );

		return (int) $this->cart_index;
	}
	/**
	 * Get the Download ID associated with the license.
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_download_id() {
		if ( ! is_null( $this->download_id ) ) {
			return $this->download_id;
		}

		$this->download_id = $this->get_meta( '_edd_sl_download_id' );

		return (int) $this->download_id;
	}

	/**
	 * Get the price ID associated with the license.
	 *
	 * @since 3.5
	 * @return int The Price ID
	 */
	private function get_price_id() {
		if ( ! is_null( $this->price_id ) ) {
			return $this->price_id;
		}

		$price_id = $this->get_meta( '_edd_sl_download_price_id' );

		if( '' === $price_id ) {

			$payment     = new EDD_Payment( $this->payment_id );
			foreach( $payment->downloads as $payment_item ) {

				if( (int) $payment_item['id'] !== (int) $this->download_id ) {
					continue;
				}

				if( isset( $payment_item['options']['price_id'] ) ) {
					$price_id = $payment_item['options']['price_id'];
					$this->update_meta( '_edd_sl_download_price_id', $price_id );
					break;
				}

			}

		}

		$prices = $this->download->get_prices();
		if ( ! isset( $prices[ $price_id ] ) ) {
			// Price ID no longer exists, fallback to default
			$price_id = edd_get_default_variable_price( $this->download_id );
			$this->update_meta( '_edd_sl_download_price_id', $price_id );
		}

		$this->price_id = $price_id;
		return $this->price_id;
	}

	/**
	 * Get the license key for the license
	 *
	 * @since 3.5
	 * @return string
	 */
	private function get_key() {
		if ( ! is_null( $this->key ) ) {
			return $this->key;
		}

		$this->key = $this->get_meta( '_edd_sl_key' );

		return $this->key;
	}

	/**
	 * Get the sites activated for the license.
	 *
	 * @since 3.5
	 * @return array
	 */
	private function get_sites() {
		if ( ! is_null( $this->sites ) ) {
			return $this->sites;
		}

		$sites = get_post_meta( $this->ID, '_edd_sl_sites', true );

		if( empty( $sites ) ) {
			$sites = array();
		}

		$sites = array_map( array( edd_software_licensing(), 'clean_site_url' ), $sites );
		$sites = array_map( 'trailingslashit', $sites );

		return array_unique( apply_filters( 'edd_sl_get_sites', $sites, $this->ID ) );
	}

	/**
	 * Get the activation limit for the license.
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_activation_limit( $force_lookup = false ) {
		if ( ! is_null( $this->activation_limit ) && ! $force_lookup ) {
			return $this->activation_limit;
		} else {
			$limit = $this->get_meta( '_edd_sl_limit' );
		}

		if ( '' === $limit ) {
			if ( $this->parent && get_post_status( $this->parent ) ) {
				$parent_license = edd_software_licensing()->get_license( $this->parent );
				$limit          = $parent_license->get_activation_limit();
			} else {
				$limit    = $this->download->get_activation_limit( $this->price_id );
			}
		}

		$this->activation_limit = apply_filters( 'edd_get_license_limit', $limit, $this->download_id, $this->ID, $this->price_id );

		return (int) $this->activation_limit;
	}

	/**
	 * Set the license activation limit.
	 *
	 * If the license has child keys, each child will be updated with the same limit.
	 *
	 * @since 3.5.6
	 * @param int $limit The new activation limit
	 *
	 * @return bool
	 */
	private function set_activation_limit( $limit = 0 ) {

		if ( empty( $this->ID ) ) {
			return false;
		}

		do_action( 'edd_sl_pre_set_activation_limit', $this->ID, $limit );

		$this->activation_limit = $limit;
		$this->update_meta( 'activation_limit', $limit );

		do_action( 'edd_sl_post_set_activation_limit', $this->ID, $limit );

		$child_licenses = $this->get_child_licenses();
		if ( ! empty( $child_licenses ) ) {
			foreach ( $child_licenses as $child_license ) {
				$child_license->update_meta( 'activation_limit', $limit );
			}
		}

		return true;
	}

	/**
	 * Removes any license limit set in post meta and determines it via the logic in get_activation_limit()
	 *
	 * @since 3.5
	 * @return bool
	 */
	public function reset_activation_limit() {
		$updated = delete_post_meta( $this->ID, '_edd_sl_limit' );

		if ( $updated ) {
			// Reset it to null.
			$this->activation_limit = null;

			// Now let the logic handle what the new limit should be.
			$this->activation_limit = $this->get_activation_limit( true );
		}

		return $updated;
	}

	/**
	 * Set the activation count of a license (for when url checking is disabled)
	 *
	 * @since 3.5.7
	 * @param $count
	 *
	 * @return bool|int Returns true or false if if updated, and the new meta ID if the meta did not exist prior to this.
	 */
	private function set_activation_count( $count ) {
		if ( ! edd_software_licensing()->force_increase() ) {
			return false;
		}

		$count = $count > 0 ? absint( $count ) : 0;

		$this->activation_count = $count;
		return $this->update_meta( '_edd_sl_activation_count', $count );
	}

	/**
	 * Get the current activation count on the license.
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_activation_count() {
		if ( ! is_null( $this->activation_count ) ) {
			return $this->activation_count;
		}

		$count = 0;

		if( edd_software_licensing()->force_increase() ) {
			$count = absint( get_post_meta( $this->ID, '_edd_sl_activation_count', true ) );
		} else {
			$sites = $this->get_sites();
			$bypass_local = edd_get_option( 'edd_sl_bypass_local_hosts', false );

			if ( $bypass_local ) {
				foreach ( $sites as $site ) {
					if ( ! edd_software_licensing()->is_local_url( $site ) ) {
						$count++;
					}
				}
			} else {
				$count = count( $sites );
			}

		}

		$this->activation_count = apply_filters( 'edd_sl_get_site_count', $count, $this->ID );
		return $this->activation_count;
	}

	/**
	 * Get the expiration of the license.
	 *
	 * @since 3.5
	 * @return mixed
	 */
	private function get_expiration() {
		if ( ! is_null( $this->expiration ) ) {
			return $this->expiration;
		}

		if ( $this->get_is_lifetime() ) {
			$expiration = 'lifetime';
		} else {
			$expiration = $this->get_meta( '_edd_sl_expiration' );
		}

		$this->expiration = $expiration;

		return $this->expiration;
	}

	/**
	 * Get if the license is a lifetime license
	 *
	 * @since 3.5
	 * @return bool
	 */
	private function get_is_lifetime() {
		if ( ! is_null( $this->is_lifetime ) ) {
			return $this->is_lifetime;
		}

		$is_lifetime       = $this->get_meta( '_edd_sl_is_lifetime' );
		$this->is_lifetime = empty( $is_lifetime ) ? false : true;

		return $this->is_lifetime;
	}

	/**
	 * Get the current status of the license.
	 *
	 * @since 3.5
	 * @return string
	 */
	private function get_status() {
		if ( ! is_null( $this->status ) ) {
			return $this->status;
		}

		$status          = strtolower( get_post_meta( $this->ID, '_edd_sl_status', true ) );
		$license_expires = $this->get_expiration();

		if ( is_numeric( $license_expires ) && $license_expires < current_time( 'timestamp' ) && 'expired' !== $status ) {
			$this->old_status = $status;
			$this->set_status( 'expired' );
		} elseif ( 'expired' === $status && $license_expires > current_time( 'timestamp' ) ) {
			$status = $this->get_activation_count() >= 1 ? 'active' : 'inactive';
			$this->set_status( $status  );
		} else {
			$this->status = $status;
		}

		return $this->status;
	}

	/**
	 * Check if license is expired
	 *
	 * @since 3.5.9
	 *
	 * @return bool
	 */
	public function is_expired() {
		return 'expired' === $this->get_status();
	}

	/**
	 * Get the 'post_status' value of the license post object
	 *
	 * @since 3.5
	 * @return string
	 */
	public function get_post_status() {
		return $this->post_status;
	}

	/**
	 * Get the license status formatted for display.
	 *
	 * @since 3.5
	 * @return string
	 */
	public function get_display_status() {
		$status = $this->get_status();

		switch ( $status ) {

			case 'active' :
				$status = __( 'Active', 'edd_sl' );
				break;

			case 'inactive' :
				$status = __( 'Inactive', 'edd_sl' );
				break;

			case 'expired' :
				$status = __( 'Expired', 'edd_sl' );

				if ( edd_sl_renewals_allowed() ) {
					$renewal_link = $this->get_renewal_url();
					$status .= ', <a href="' . esc_url( $renewal_link ) . '" title="' . __( 'Renew this license', 'edd_sl' ) . '">' . __( 'renew now', 'edd_sl' ) . '</a>';
				}
				break;

		}

		return $status;
	}

	/**
	 * Get the URL used to add a license renewal to the cart
	 *
	 * @since 3.5
	 * @return string
	 */
	public function get_renewal_url() {
		$args = array(
			'edd_license_key' => $this->key,
			'download_id'     => $this->download_id,
		);

		$url = add_query_arg( $args, edd_get_checkout_uri() );

		return apply_filters( 'edd_sl_get_renewal_url', $url, $this->ID, $this );
	}

	/**
	 * Get the URL used to add a license renewal to the cart
	 *
	 * @since 3.5.11
	 * @return string
	 */
	public function get_unsubscribe_url() {

		$args = array(
			'edd_action'  => 'license_unsubscribe',
			'license_id'  => $this->ID,
			'license_key' => $this->key,
		);

		$url = add_query_arg( $args, home_url() );

		return apply_filters( 'edd_sl_get_unsubscribe_url', $url, $this->ID, $this );
	}

	/**
	 * Return a list of EDD_SL_License objects for the child licenses
	 *
	 * @since 3.5
	 * @return array Returns an array of EDD_SL_License objects
	 */
	public function get_child_licenses() {
		if ( ! is_null( $this->child_licenses ) ) {
			return $this->child_licenses;
		}

		$child_licenses = array();

		$args = array(
			'post_parent'    => $this->ID,
			'post_type'      => 'edd_license',
			'posts_per_page' => - 1,
			'post_status'    => 'any',
		);

		$children  = get_posts( $args );
		if ( $children ) {
			foreach ( $children as $child ) {
				$child_license = edd_software_licensing()->get_license( $child->ID );
				if ( $child_license ) {
					$child_licenses[] = $child_license;
				}
			}
		}

		$this->child_licenses = $child_licenses;

		return apply_filters( 'edd_sl_get_child_licenses', $this->child_licenses, $this->ID );
	}

	/**
	 * Given a site URL, see if it's been activated for the license
	 *
	 * @since 3.5
	 * @param string $site_url
	 *
	 * @return bool
	 */
	public function is_site_active( $site_url ) {
		if( edd_software_licensing()->force_increase() ) {
			return true; // Licenses are not tied to URLs
		}

		$site_url = trailingslashit( edd_software_licensing()->clean_site_url( $site_url ) );

		$ret   = in_array( $site_url, $this->get_sites() );
		return (bool) apply_filters( 'edd_sl_is_site_active', $ret, $this->ID, $site_url );
	}

	/**
	 * Get the activation limit.
	 *
	 * @since 3.5
	 * @return mixed
	 */
	public function license_limit() {
		$limit = $this->get_activation_limit();
		if ( $limit <= 0 ) {
			$limit = __( 'Unlimited', 'edd_sl' );
		}

		return $limit;
	}

	/**
	 * Returns if the license is at it's activation limit.
	 *
	 * @since 3.5
	 * @return bool
	 */
	public function is_at_limit() {
		$ret = false;
		if ( $this->get_activation_limit() > 0 && $this->get_activation_count() >= $this->get_activation_limit() ) {
			$ret = true;
		}

		return (bool) apply_filters( 'edd_sl_license_at_limit', $ret, $this->ID, $this->get_activation_limit(), $this->download_id );
	}

	/**
	 * Get the string equivalent of the length of the license. For example: +1years
	 *
	 * @since 3.5
	 * @return string
	 */
	public function license_length() {
		$download_id = $this->download_id;
		$price_id    = $this->price_id;

		if ( $this->parent && get_post_status( $this->parent ) ) {
			$parent_license = edd_software_licensing()->get_license( $this->parent );
			$download_id    = $parent_license->download_id;
			$price_id       = $parent_license->price_id;
		}

		$download = new EDD_SL_Download( $download_id );

		if ( $download->has_variable_prices() ) {
			$download_is_lifetime = $download->is_price_lifetime( $price_id );
		} else {
			$download_is_lifetime = $download->is_lifetime();
		}

		if ( ! empty( $download_is_lifetime ) ) {
			$expiration = 'lifetime';
		} else {
			$exp_unit   = $download->get_expiration_unit();
			$exp_length = $download->get_expiration_length();

			if( empty( $exp_unit ) ) {
				$exp_unit = 'years';
			}

			if( empty( $exp_length ) ) {
				$exp_length = '1';
			}

			$expiration = '+' . $exp_length . ' ' . $exp_unit;
		}

		$expiration = apply_filters( 'edd_sl_license_exp_lengh', $expiration, $this->payment_id, $download_id, $this->ID ); // for backward compatibility
		$expiration = apply_filters( 'edd_sl_license_exp_length', $expiration, $this->payment_id, $download_id, $this->ID );

		return $expiration;
	}

	/**
	 * Used to display the term for a given license to the user
	 *
	 * @since 3.5.7
	 * @return string
	 */
	public function license_term() {
		$download_id = $this->download_id;
		$price_id    = $this->price_id;

		if ( $this->parent && get_post_status( $this->parent ) ) {
			$parent_license = edd_software_licensing()->get_license( $this->parent );
			$download_id    = $parent_license->download_id;
			$price_id       = $parent_license->price_id;
		}

		$download = new EDD_SL_Download( $download_id );
		$download_is_lifetime = false !== $price_id ? $download->is_price_lifetime( $price_id ) : $download->is_lifetime();

		if ( ! empty( $download_is_lifetime ) ) {
			$term = __( 'Lifetime', 'edd_sl' );
		} else {
			$exp_unit   = $download->get_expiration_unit_nicename();
			$exp_length = $download->get_expiration_length();

			if( empty( $exp_unit ) ) {
				$exp_unit = __( 'Years', 'edd_sl' );
			}

			if( empty( $exp_length ) ) {
				$exp_length = '1';
			}

			$term = $exp_length . ' ' . $exp_unit;
		}

		$term = apply_filters( 'edd_sl_license_term', $term, $this->payment_id, $download_id, $this->ID );

		return $term;
	}

	/**
	 * Add a given site to the list of activated sites for the license.
	 *
	 * @since 3.5
	 * @param $site
	 *
	 * @return array|bool
	 */
	public function add_site( $site ) {

		$added = false;
		if ( $this->is_at_limit() && ( ! is_admin() && ! current_user_can( 'manage_shop_settings' ) ) ) {
			return $added;
		}

		if ( edd_software_licensing()->force_increase() ) {
			$this->set_activation_count( $this->activation_count + 1 );
			return $added;
		}

		$site    = trailingslashit( edd_software_licensing()->clean_site_url( $site ) );
		$sites   = $this->get_sites();
		$sites[] = $site;
		$sites   = array_unique( $sites );

		$this->__set( 'sites', $sites );

		if ( ! edd_software_licensing()->is_local_url( $site ) ) {
			$this->activation_count = count( $this->sites );
		}

		$added = ! empty( $this->sites );

		return $added;
	}

	/**
	 * Remove a site from the list of activated sites on the license.
	 *
	 * @since 3.5
	 * @param $site
	 *
	 * @return bool|int
	 */
	public function remove_site( $site ) {

		$site    = trailingslashit( edd_software_licensing()->clean_site_url( $site ) );
		$sites   = $this->get_sites();
		$removed = false;

		if ( edd_software_licensing()->force_increase() ) {
			$this->set_activation_count( $this->activation_count - 1 );
			return $removed;
		}

		$found = array_search( $site, $sites );

		if ( false !== $found ) {

			unset( $sites[ $found ] );

			$this->__set( 'sites', $sites );

			if ( ! edd_software_licensing()->is_local_url( $site ) ) {
				$this->activation_count = count( $this->sites );
			}

			$removed = true;

		}

		return $removed;
	}

	/**
	 * Setter for sites
	 *
	 * @since 3.5.9
	 * @param $sites
	 *
	 * @return bool
	 */
	private function set_sites( $sites = array() ) {

		$updated = $this->update_meta( 'sites', $sites );

		if( $updated ) {

			$this->sites = $sites;

		}

		return $updated;

	}

	/**
	 * Set the post_parent attribute on the license.
	 *
	 * @since 3.5
	 * @param int $parent_id
	 *
	 * @return bool
	 */
	private function set_parent( $parent_id = 0 ) {
		$updated = false;

		$args = array(
			'ID'          => $this->ID,
			'post_parent' => $parent_id,
		);

		$post_id = wp_update_post( $args, true );
		if ( ! is_wp_error( $post_id ) ) {
			$this->parent      = $parent_id;
			$this->post_parent = $parent_id;
			$updated = true;
		} else {
			$updated = false;
		}

		return $updated;
	}

	/**
	 * Wrapper for the set_parent method, to support backwards compatibility.
	 *
	 * @since 3.5
	 * @param int $parent_id
	 *
	 * @return bool
	 */
	private function set_post_parent( $parent_id = 0 ) {
		return $this->set_parent( $parent_id );
	}

	/**
	 * Set a the 'lifetime' status of a license.
	 *
	 * @since 3.5
	 * @param bool $is_lifetime
	 *
	 * @return bool|int
	 */
	private function set_is_lifetime( $is_lifetime = false ) {

		do_action( 'edd_sl_pre_set_lifetime', $this->ID );

		if ( true === $is_lifetime ) {
			// Remove the expiration date
			delete_post_meta( $this->ID, '_edd_sl_expiration' );

			// Set the status
			$status = $this->activation_count > 0 ? 'active': 'inactive';
			$this->set_status( $status );

			$this->is_lifetime = true;

			$updated = $this->update_meta( '_edd_sl_is_lifetime', $is_lifetime );
		} else {
			$this->is_lifetime = false;
			$updated = delete_post_meta( $this->ID, '_edd_sl_is_lifetime' );
		}

		if ( $updated ) {
			$child_licenses = $this->get_child_licenses();
			foreach ( $child_licenses  as $child_license ) {
				$child_license->is_lifetime = $is_lifetime;
				delete_post_meta( $child_license->ID, '_edd_sl_expiration' );
			}
		}

		do_action( 'edd_sl_post_set_lifetime', $this->ID );

		return $updated;

	}

	/**
	 * Set the post_status of a license.
	 *
	 * @since 3.5
	 * @param string $post_status
	 *
	 * @return int|WP_Error
	 */
	private function set_post_status( $post_status = 'publish' ) {
		return wp_update_post( array( 'ID' => $this->ID, 'post_status' => $post_status ) );
	}

	/**
	 * Set the license status.
	 *
	 * @since 3.5
	 * @param string $status
	 *
	 * @return bool|int
	 */
	private function set_status( $status = 'inactive' ) {
		if( strtolower( $this->status ) === strtolower( $status ) ) {
			return; // Statuses are the same
		}

		do_action( 'edd_sl_pre_set_status', $this->ID, $status );

		$updated = $this->update_meta( '_edd_sl_status', $status );

		if ( $updated ) {

			$this->status = $status;

			if ( 'active' == $status ) {
				edd_software_licensing()->log_license_activation( $this->ID, $_SERVER );
			}

			if ( 'inactive' == $status ) {
				edd_software_licensing()->log_license_deactivation( $this->ID, $_SERVER );
			}

			if ( 'expired' == $status ) {
				// Determine if we should send an email when a license key is marked as expired
				$notice_on_expired = false;
				$notices = edd_sl_get_renewal_notices();

				foreach( $notices as $key => $notice ) {

					if( 'expired' == $notice['send_period'] ) {
						$edd_sl_emails = new EDD_SL_Emails;
						$edd_sl_emails->send_renewal_reminder( $this->ID, $key );
					}

				}

			}

			$child_licenses = $this->get_child_licenses();

			foreach ( $child_licenses as $child_license ) {
				$child_license->status = $status;
			}

		}

		do_action( 'edd_sl_post_set_status', $this->ID, $status );

		return $updated;
	}

	/**
	 * Set the license expiration.
	 *
	 * @since 3.5
	 * @param bool $expiration
	 *
	 * @return bool
	 */
	private function set_expiration( $expiration = false ) {

		if ( empty( $this->ID ) ) {
			return false;
		}

		// $expiration should be a valid timestamp
		do_action( 'edd_sl_pre_set_expiration', $this->ID, $expiration );
		$this->update_meta( 'expiration', $expiration );

		// Change status to expired when expiration date is in the past
		if( $expiration < current_time( 'timestamp' ) ) {
			$this->set_status( 'expired' );
		} else if ( 'expired' == $this->status && $expiration > current_time( 'timestamp' ) ) {
			$this->set_status( 'inactive' );
		}

		delete_post_meta( $this->ID, '_edd_sl_is_lifetime' );

		$this->expiration  = $expiration;
		$this->is_lifetime = false;

		do_action( 'edd_sl_post_set_expiration', $this->ID, $expiration );

		$child_licenses = $this->get_child_licenses();
		if ( ! empty( $child_licenses ) ) {
			foreach ( $child_licenses as $child_license ) {
				$child_license->expiration = $expiration;
				$child_license->update_meta( '_edd_sl_expiration', $expiration );
			}
		}
	}

	/**
	 * Set the download ID associated with the license.
	 *
	 * @since 3.5
	 * @param int $download_id
	 *
	 * @return bool|int
	 */
	private function set_download_id( $download_id = 0 ) {
		$updated = $this->update_meta( '_edd_sl_download_id', $download_id );

		if ( $updated ) {
			$this->download_id = $download_id;
			$this->download    = new EDD_SL_Download( $download_id );
		}

		return $updated;
	}

	/**
	 * Set the price ID associated with the license.
	 *
	 * @since 3.5
	 * @param $price_id
	 *
	 * @return bool|int
	 */
	private function set_price_id( $price_id ) {
		$updated = $this->update_meta( '_edd_sl_download_price_id', $price_id );

		if ( $updated ) {
			$this->price_id = $price_id;
		}

		return $updated;
	}

	/**
	 * Set the post_title of the license post.
	 * @param $name
	 *
	 * @return int|WP_Error
	 */
	private function set_name( $name ) {
		$updated = wp_update_post( array( 'ID' => $this->ID, 'post_title' => $name ) );

		if ( $updated ) {
			$this->post_title = $name;
			$this->name       = $name;
		}

		return $updated;
	}

	/**
	 * A list of attributes and their associated post_meta keys. To aid developers in mapping the new class.
	 *
	 * @since 3.5
	 * @return array
	 */
	private function property_map() {
		$mapping = array(
			'download_id'      => '_edd_sl_download_id',
			'price_id'         => '_edd_sl_download_price_id',
			'sites'            => '_edd_sl_sites',
			'status'           => '_edd_sl_status',
			'is_lifetime'      => '_edd_sl_is_lifetime',
			'expiration'       => '_edd_sl_expiration',
			'cart_index'       => '_edd_sl_cart_index',
			'payment_id'       => '_edd_sl_payment_id',
			'key'              => '_edd_sl_key',
			'user_id'          => '_edd_sl_user_id',
			'activation_limit' => '_edd_sl_limit',
			'activation_count' => '_edd_sl_activation_count',
		);

		return apply_filters( 'edd_sl_property_postmeta_map', $mapping, $this );
	}

	/**
	 * Given a property, get the associated post_meta key.
	 *
	 * @since 3.5
	 * @param string $property
	 *
	 * @return bool|mixed
	 */
	public function get_property_meta_key( $property = '' ) {
		if ( empty( $property ) ) {
			return false;
		}

		$properties = $this->property_map();
		if ( ! isset( $properties[ $property ] ) ) {
			return false;
		}

		return $properties[ $property ];
	}
}
