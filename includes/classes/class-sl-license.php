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

class EDD_SL_License {

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
	protected $license_key = null;

	protected $user_id     = null;
	protected $customer_id = null;
	protected $customer    = null;
	protected $payment_id  = null;
	protected $payment_ids = null;
	protected $cart_index  = null;

	protected $download    = null;
	protected $download_id = null;
	protected $price_id    = null;

	protected $activation_limit = null;
	protected $sites            = null;
	protected $activation_count = null;

	protected $date_created = null;
	protected $expiration   = null;
	protected $is_lifetime  = null;

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

			if ( ! is_numeric( $license_id ) ) {
				$license = edd_software_licensing()->licenses_db->get_column_by( 'id', 'license_key', sanitize_text_field( $license_id ) );
			} else {
				$license = edd_software_licensing()->licenses_db->get( $license_id );
			}

			if ( empty( $license ) ) {
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

		// Only real properties can be saved.
		$keys = array_keys( get_class_vars( get_called_class() ) );

		if ( ! in_array( $key, $keys ) ) {
			return false;
		}

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
	 * Given results from the EDD_SL_License_DB::get() method.
	 *
	 * @since 3.5
	 *
	 * @param $license Data from the custom database table.
	 */
	private function setup( $license ) {

		if ( is_numeric( $license ) ) {
			$license = edd_software_licensing()->licenses_db->get( $license );
		}

		if ( isset( $license->status ) && 'private' === $license->status ) {
			return;
		}

		foreach ( get_object_vars( $license ) as $key => $value ) {
			$this->{$key} = $value;
		}

		$this->ID     = absint( $license->id );
		$this->_ID    = absint( $license->id );

		$this->payment_ids = $this->get_payment_ids();

		$this->price_id   = is_numeric( $this->price_id ) ? $this->price_id : false;
		$this->expiration = (int) $this->expiration;

		$this->key         = $license->license_key;
		$this->is_lifetime = 0 === $this->expiration ? true : false;

		$this->sites            = $this->get_sites();
		$this->activation_limit = $this->get_activation_limit();

		$this->maybe_backfill_customer();
		$this->maybe_backfill_payment();

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

				// Get the purchase date so we can set the correct license expiration date.
				$payment_meta  = $payment->get_meta();
				$purchase_date = null;
				if ( ! empty( $payment_meta['date'] ) ) {
					$purchase_date = strtotime( $payment_meta['date'], current_time( 'timestamp' ) );
				}

				// Force a `false` price ID when there is no variable pricing.
				if ( ! $purchased_download->has_variable_prices() ) {
					$price_id = false;
				}

				$license_args = array(
					'license_key'  => '',
					'status'       => 'inactive',
					'download_id'  => $purchased_download->ID,
					'price_id'     => false === $price_id ? NULL : $price_id,
					'payment_id'   => $payment_id,
					'cart_index'   => $cart_index,
					'date_created' => current_time( 'mysql' ),
					'expiration'   => NULL,
					'parent'       => $parent_license_id,
					'customer_id'  => $payment->customer_id,
					'user_id'      => $payment->user_id,
				);

				$this->ID  = edd_software_licensing()->licenses_db->insert( apply_filters( 'edd_sl_insert_license_args', $license_args ), 'license' );
				$new_data  = edd_software_licensing()->licenses_db->get( $this->ID );
				foreach ( get_object_vars( $new_data ) as $key => $value ) {
					$this->{$key} = $value;
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

				} else {
					$expiration_date = 0;
				}

				$license_key = $this->generate_key( $purchased_download, $payment, $cart_index );

				$this->update(
					array(
						'license_key' => $license_key,
						'expiration'  => $expiration_date,
						'status'      => ( ! empty( $expiration_date ) && $expiration_date < current_time( 'timestamp' ) ) ? 'expired' : 'inactive',
					)
				);

				$keys[] = $this->ID;

				if ( $parent_license_id && false !== $activation_limit ) {
					$this->update_meta( '_edd_sl_limit', $activation_limit );
				}

				// Only need this for backwards compatible hooks.
				$type = $is_bundle ? 'bundle' : 'default';
				do_action( 'edd_sl_store_license', $this->ID, $purchased_download->ID, $payment->ID, $type );

			}
		}

		$existing_licenses = edd_software_licensing()->get_licenses_of_purchase( $payment_id );
		if ( $is_bundle && $existing_licenses ) {
			$bundled_products = array_map( 'absint', $purchased_download->get_bundled_downloads() );
			$existing_license_product_ids = wp_list_pluck( $existing_licenses, 'download_id' );

			foreach ( $bundled_products as $bundled_product_id ) {

				if ( in_array( $bundled_product_id, $existing_license_product_ids, true ) ) {
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

			// Make sure we do not already have a child key for this product
			if ( in_array( $download->ID, $child_license_download_ids ) ) {
				continue;
			}

			// Make sure we do not already havev a key for this product
			if ( ! empty( $existing_license_product_ids ) && in_array( $download->ID, $existing_license_product_ids ) ) {
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
			$this->setup( $this->ID );

			// Load the newly created license into the object cache.
			$this->exists = true;
		}

		return $keys;
	}

	/**
	 * A helper function to update multiple values
	 *
	 * @since 3.5
	 * @since 3.6 - Updated for custom tables, now that most data is not in meta tables.
	 *
	 * @param array $data Key/Value array of property => value
	 * @return array      If the row was updated
	 */
	public function update( $data = array() ) {
		return edd_software_licensing()->licenses_db->update( $this->ID, $data );
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

		if ( $this->get_download()->is_lifetime() ) {
			$new_expiration = 0;
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

			$updated = $this->set_expiration( $new_expiration );
		}

		if ( $updated ) {
			edd_debug_log( sprintf( 'License %d renewed successfully via Payment %d.', $this->ID, $payment_id ) );

			$this->expiration = $new_expiration;

			if ( ! empty( $payment_id ) ) {
				$this->add_meta( '_edd_sl_payment_id', $payment_id, false );
			}

			$child_licenses = $this->get_child_licenses();
			// If this item has child licenses, renew them as well
			if ( ! empty( $child_licenses ) ) {
				foreach ( $this->child_licenses as $child_license ) {

					if ( $this->is_lifetime ) {
						$child_license->set_is_lifetime( true );
					} else {
						$child_license->renew( $payment_id );
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
		$status  = $this->activation_count > 0 ? 'active' : 'inactive';
		$updated = $this->set_status( $status );

		if ( $updated ) {
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
		$updated = $this->update( array( 'status' => 'disabled' ) );

		if ( $updated ) {
			$child_licenses = $this->get_child_licenses();
			foreach ( $child_licenses as $child_license ) {
				$child_license->disable();
			}
		}

		return (bool) $updated;
	}

	/**
	 * Delete a license record, the meta and any license activations.
	 *
	 * @since 3.6
	 * @return bool
	 */
	public function delete() {
		$license_deleted = edd_software_licensing()->licenses_db->delete( $this->ID );

		return $license_deleted;
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
		$meta = edd_software_licensing()->license_meta_db->get_meta( $this->ID, $meta_key, $single );

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
			$updated = edd_software_licensing()->license_meta_db->update_meta( $this->ID, $meta_key, $value, $old_value );
		} else {
			$updated = edd_software_licensing()->license_meta_db->update_meta( $this->ID, $meta_key, $value );
		}

		return $updated;
	}

	public function add_meta( $meta_key = '', $meta_value, $unique = false ) {
		return edd_software_licensing()->license_meta_db->add_meta( $this->ID, $meta_key, $meta_value, $unique );
	}

	public function delete_meta( $meta_key = '', $meta_value = '', $delete_all = false ) {
		return edd_software_licensing()->license_meta_db->delete_meta( $this->ID, $meta_key, $meta_value, $delete_all );
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
		$name = $this->get_download()->get_name();

		if ( true === $full ) {
			$price_name = edd_get_price_name( $this->download_id, array( 'price_id' => $this->price_id ) );
			if ( ! empty( $price_name ) ) {
				$name .= ' - ' . $price_name;
			}
		}

		return $name;
	}

	/**
	 * Generate a license key based off the download, payment, and cart index.
	 *
	 * @since 3.6.1
	 *
	 * @param EDD_SL_Download $purchased_download The EDD_SL_Download that was purchased.
	 * @param EDD_Payment     $payment The payment object to associate with this license key.
	 * @param int             $cart_index The cart index of the item.
	 * @param int             $timestamp Allows defining a specific numeric timestamp to 'salt' the license key with.
	 *
	 * @return string
	 */
	private function generate_key( $purchased_download, $payment, $cart_index, $timestamp = 0 ) {
		// Generate a license key.
		$license_key = $purchased_download->get_new_license_key();
		if( ! $license_key ) {

			// No predefined license key available, generate a random one.
			$license_key = EDD_Software_Licensing()->generate_license_key( $this->ID, $purchased_download->ID, $payment->ID, $cart_index, $timestamp );

		}

		return $license_key;
	}

	/**
	 * Allows regenerating a license key for a specific license, without altering any other data.
	 *
	 * @since 3.6.1
	 * @param int $timestamp A 'salt' to generate a timestamp with, will use the current timestamp if not defined.
	 *
	 * @return bool
	 */
	public function regenerate_key( $timestamp = 0 ) {
		$payment = edd_get_payment( $this->payment_id );
		$new_key = $this->generate_key( $this->get_download(), $payment, $this->cart_index, $timestamp );
		$updated = false;

		if ( ! empty( $new_key ) ) {
			$updated = $this->update( array( 'license_key' => $new_key ) );

			if ( $updated ) {
				$this->setup( $this->id );
				$log_id = wp_insert_post(
					array(
						'post_title'   => __( 'LOG - License Key Regenerated: ', 'edd_sl' ) . $this->id,
						'post_name'    => 'log-license-regenerated-' . $this->id . '-' . md5( current_time( 'timestamp' ) ),
						'post_type'    => 'edd_license_log',
						'post_content' => json_encode( array(
							'license_key' => $this->key,
							'user_id'     => get_current_user_id(),
						) ),
						'post_status'  => 'publish'
					)
				);

				add_post_meta( $log_id, '_edd_sl_log_license_id', $this->id );
			}
		}

		return $updated;
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

		$user_id = $this->user_id;

		if ( empty( $user_id ) || $user_id < 0 ) {

			$payment_id = $this->get_payment_id();
			$payment    = new EDD_Payment( $payment_id );
			$user_id    = $payment->user_id;

			if ( ! empty( $user_id ) ) {
				$this->user_id = $user_id;
				edd_software_licensing()->licenses_db->update( $this->ID, array( 'user_id' => $user_id ) );
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
		return (int) $this->customer_id;
	}

	/**
	 * Get the customer record associated with this license.
	 *
	 * @since 3.6
	 * @return EDD_Customer
	 */
	private function get_customer() {
		return new EDD_Customer( $this->customer_id );
	}

	/**
	 * Get the primary payment ID associated with the license
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_payment_id() {
		return (int) $this->payment_id;
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

		$renewal_and_upgrade_payments = $this->get_meta( '_edd_sl_payment_id', false );

		// We need to make sure we get back an array here, so we can use the array_merge function.
		if ( ! is_array( $renewal_and_upgrade_payments ) ) {
			$renewal_and_upgrade_payments = array();
		}

		$this->payment_ids = array_merge( array( $this->get_payment_id() ), $renewal_and_upgrade_payments );
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
		return (int) $this->cart_index;
	}

	private function set_cart_index( $cart_index = 0 ) {
		return $this->update( array( 'cart_index' => $cart_index ) );
	}

	/**
	 * Get the Download ID associated with the license.
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_download_id() {
		return (int) $this->download_id;
	}

	private function get_license_key() {
		return $this->license_key;
	}

	/**
	 * Get the license key for the license
	 *
	 * @since 3.5
	 * @return string
	 */
	private function get_key() {
		return $this->get_license_key();
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
			$limit = $this->get_default_activation_count();
		}

		$this->activation_limit = apply_filters( 'edd_get_license_limit', $limit, $this->download_id, $this->ID, $this->price_id );

		return (int) $this->activation_limit;
	}

	public function get_default_activation_count() {
		$limit    = $this->get_download()->get_activation_limit( $this->price_id );

		if ( $this->parent ) {
			$parent_license = edd_software_licensing()->get_license( $this->parent );

			if ( false !== $parent_license ) {
				$limit = $parent_license->activation_limit;
			}
		}

		return absint( $limit );
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

		$limit = absint( $limit );

		do_action( 'edd_sl_pre_set_activation_limit', $this->ID, $limit );

		if ( $limit === $this->get_default_activation_count() ) {
			$this->delete_meta( '_edd_sl_limit' );
		} else {
			$this->update_meta( '_edd_sl_limit', $limit );
		}

		$this->activation_limit = $limit;

		do_action( 'edd_sl_post_set_activation_limit', $this->ID, $limit );

		$child_licenses = $this->get_child_licenses();
		if ( ! empty( $child_licenses ) ) {
			foreach ( $child_licenses as $child_license ) {
				$child_license->set_activation_limit( $limit );
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

		// Delete any customized limit in the license meta.
		$this->delete_meta( '_edd_sl_limit' );

		// Reset it to null.
		$this->activation_limit = null;

		// Now let the logic handle what the new limit should be.
		$this->activation_limit = $this->get_activation_limit( true );

		return true;
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

		return $this->update_meta( '_edd_sl_activation_count', $count );
	}

	/**
	 * Get the current activation count on the license.
	 *
	 * @since 3.5
	 * @return int
	 */
	private function get_activation_count() {
		$count = 0;

		/**
		 * In the event a store does URL checking, has licenses activated, and then disables URL checking
		 * we have to verify the activation count meta is a numeric value first.
		 *
		 * If URL Checking is disabled, but the meta for the activation count is an empty string (no activations yet),
		 * we look up any URL based activations in order to compensate for that first non-URL checked activation.
		 */
		$count_from_meta = $this->get_meta( '_edd_sl_activation_count', true );

		if ( edd_software_licensing()->force_increase() && is_numeric( $count_from_meta )  ) {

			$count = absint( $count_from_meta );

		} else {

			$bypass_local = edd_get_option( 'edd_sl_bypass_local_hosts', false );
			$sites        = $this->get_sites();

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
		if ( $this->get_is_lifetime() ) {
			$expiration = 'lifetime';
		} else {
			$expiration = $this->expiration;
		}

		return $expiration;
	}

	/**
	 * Get if the license is a lifetime license
	 *
	 * @since 3.5
	 * @return bool
	 */
	private function get_is_lifetime() {
		if ( ! empty( $this->parent ) ) {
			$parent = edd_software_licensing()->get_license( $this->parent );
		}

		// If the parent is set and the parent license exists, use the parent, otherwise use the license's own expiration.
		$expiration = ! empty( $parent ) ? $parent->expiration : $this->expiration;

		// No more lifetime flags. If the expiration is 0, the license is lifetime.
		$this->is_lifetime = 0 === $expiration  ? true : false;

		return $this->is_lifetime;
	}

	/**
	 * Get the current status of the license.
	 *
	 * @since 3.5
	 * @return string
	 */
	private function get_status() {

		$status          = $this->status;
		$license_expires = $this->expiration;

		if ( ! empty( $license_expires ) && $license_expires < current_time( 'timestamp' ) && 'expired' !== $status ) {
			$this->old_status = $status;
			$this->set_status( 'expired' );
		} elseif ( 'expired' === $status && $license_expires > current_time( 'timestamp' ) ) {
			$status = $this->get_activation_count() >= 1 ? 'active' : 'inactive';
			$this->set_status( $status  );
		} else {

			if ( ! in_array( $status, array( 'disabled', 'expired' ) ) ) {

				if ( edd_software_licensing()->force_increase() ) {
					$count = $this->get_activation_count();
				} else {
					// Verify that if the license is not disabled, and we have activations, that the status is updated to `active`.
					$count_args = array(
						'license_id' => $this->ID,
						'activated'  => 1,
						'is_local'   => array( 0, 1 ),
					);

					$count = edd_software_licensing()->activations_db->count( $count_args );
				}


				$correct_status = $count >= 1 ? 'active' : 'inactive';
				if ( $correct_status !== $status ) {
					$status = $correct_status;
					$this->set_status( $status );
				}

			}

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
	 * @deprecated After moving to a custom table, post_status should not be relied upon. This is here for backwards
	 *             compatibility.
	 * @return string
	 */
	public function get_post_status() {
		_edd_deprecated_function( 'EDD_SL_License->post_status', '3.6', 'EDD_SL_License->status' );

		if ( in_array( $this->status, array( 'active', 'inactive', 'expired' ) ) ) {
			$this->post_status = 'publish';
		} else {
			// For anything else (revoked, disabled, etc) return 'draft'.
			$this->post_status = 'draft';
		}
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
			'parent' => $this->ID,
			'number' => - 1,
		);

		$this->child_licenses = edd_software_licensing()->licenses_db->get_licenses( $args );;

		return apply_filters( 'edd_sl_get_child_licenses', $this->child_licenses, $this->ID );
	}

	private function get_ID() {
		return $this->ID;
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

		$site_url  = trailingslashit( edd_software_licensing()->clean_site_url( $site_url ) );
		$is_active = (bool) edd_software_licensing()->activations_db->get_activations( array(
			'site_name'  => $site_url,
			'license_id' => $this->ID,
			'activated'  => 1,
		) );

		$is_active = ! empty( $is_active ) ? true : false;
		return (bool) apply_filters( 'edd_sl_is_site_active', $is_active, $this->ID, $site_url );
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

		if ( $this->parent ) {
			$parent_license = edd_software_licensing()->get_license( $this->parent );
			$download_id    = $parent_license->download_id;
			$price_id       = $parent_license->price_id;
		}

		$download = $this->get_download();

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

		if ( $this->parent ) {
			$parent_license = edd_software_licensing()->get_license( $this->parent );
			$download_id    = $parent_license->download_id;
			$price_id       = $parent_license->price_id;
		}

		$download = $this->get_download();
		if ( $download->has_variable_prices() ) {
			$download_is_lifetime = $download->is_price_lifetime( $price_id );
		} else {
			$download_is_lifetime = $download->is_lifetime();
		}


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
	 * Get the sites activated for the license.
	 *
	 * @since 3.5
	 * @since 3.6 - For backwards compatibility this will only return sites that are activated.
	 * @return array
	 */
	private function get_sites() {
		$args  = array( 'fields' => 'site_name', 'number' => -1, 'license_id' => $this->ID, 'activated' => 1 );
		$sites = edd_software_licensing()->activations_db->get_activations( $args );

		if( empty( $sites ) ) {
			$sites = array();
		}

		$sites = array_map( array( edd_software_licensing(), 'clean_site_url' ), $sites );
		$sites = array_map( 'trailingslashit', $sites );

		$this->sites = $sites;

		return array_unique( apply_filters( 'edd_sl_get_sites', $this->sites, $this->ID ) );
	}

	public function get_download() {
		if ( ! is_null( $this->download ) && is_object( $this->download ) ) {
			return $this->download;
		}

		$this->download = new EDD_SL_Download( $this->download_id );

		return $this->download;
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

		$added    = false;
		$is_local = edd_software_licensing()->is_local_url( $site );
		if ( ( $this->is_at_limit() && ! $is_local ) && ( ! is_admin() && ! current_user_can( 'manage_licenses' ) ) ) {
			return $added;
		}

		if ( edd_software_licensing()->force_increase() ) {
			$current_activation_count = $this->get_activation_count();
			$this->set_activation_count( $current_activation_count + 1 );
			return $added;
		}

		$site   = trailingslashit( edd_software_licensing()->clean_site_url( $site ) );

		if ( empty( $site ) || '/' === $site ) {
			return false;
		}

		$args   = array(
			'site_name'  => $site,
			'license_id' => $this->ID,
			'activated'  => array( 0, 1 ),
			'fields'     => 'site_id',
		);

		$exists = edd_software_licensing()->activations_db->get_activations( $args );

		if ( ! empty( $exists ) ) {
			$added = edd_software_licensing()->activations_db->update( $exists[0], array( 'activated' => 1 ) );
		} else {
			$added = edd_software_licensing()->activations_db->insert( array(
				'site_name'  => $site,
				'license_id' => (int) $this->ID,
				'activated'  => 1,
				'is_local'   => $is_local ? 1 : 0,
			), 'site_activation' );
		}

		$this->sites = $this->get_sites();

		return ! empty( $added );
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
		$removed = false;

		if ( edd_software_licensing()->force_increase() ) {
			$current_activation_count = $this->get_activation_count();
			$this->set_activation_count( $current_activation_count - 1 );
			return true;
		}

		$args   = array(
			'site_name'  => $site,
			'license_id' => $this->ID,
			'fields'     => 'site_id',
		);

		// Check for this site being active for the license
		$exists = edd_software_licensing()->activations_db->get_activations( $args );
		if ( ! empty( $exists ) ) {
			$removed = edd_software_licensing()->activations_db->update( $exists[0], array( 'activated' => 0 ) );
		}

		$count = $this->get_activation_count();

		if ( $removed && empty( $count ) ) {
			$this->set_status( 'inactive' );
		}

		return $removed;
	}

	public function update_site( $args = array() ) {

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
		$updated = edd_software_licensing()->licenses_db->update( $this->ID, array( 'parent' => $parent_id ) );

		if ( ! is_wp_error( $updated ) ) {
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
		return $this->set_parent_license( $parent_id );
	}

	private function set_parent_license( $parent_id = 0 ) {
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

		$updated = false;

		if ( true === $is_lifetime ) {

			$updated = edd_software_licensing()->licenses_db->update( $this->ID, array( 'expiration' => 0 ) );

			// Set the status
			$status = $this->activation_count > 0 ? 'active': 'inactive';
			$this->set_status( $status );

			$this->is_lifetime = true;

		} else {
			$this->is_lifetime = false;
		}

		if ( $updated ) {
			$child_licenses = $this->get_child_licenses();
			foreach ( $child_licenses  as $child_license ) {
				$child_license->set_is_lifetime( $is_lifetime );
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
	private function set_post_status( $post_status = 'active' ) {
		_edd_deprecated_function( 'EDD_SL_License::set_post_status', '3.6' );
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

		$updated = edd_software_licensing()->licenses_db->update( $this->ID, array( 'status' => $status ) );

		if ( $updated ) {

			$this->status = $status;

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
				$child_license->set_status( $status );
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
		$updated = $this->update( array( 'expiration' => $expiration ) );

		if ( $updated ) {
			// Change status to expired when expiration date is in the past
			if( $expiration < current_time( 'timestamp' ) ) {
				$this->set_status( 'expired' );
			} else {
				$this->set_status( 'active' );
			}

			$this->expiration  = $expiration;
			$this->is_lifetime = empty( $expiration ) ? true : false;

			do_action( 'edd_sl_post_set_expiration', $this->ID, $expiration );

			$child_licenses = $this->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$child_license->set_expiration( $expiration );
				}
			}
		}

		return $updated;
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
		$updated = $this->update( array( 'download_id' => $download_id ) );

		if ( $updated ) {
			$this->download_id = $download_id;
			$this->download    = null;
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
		$updated = $this->update( array( 'price_id' => $price_id ) );

		if ( $updated ) {
			$this->price_id = $price_id;
			$this->reset_activation_limit();

			if ( ! empty( $this->child_licenses ) ) {

				foreach ( $this->child_licenses as $child_license ) {

					if ( $child_license->download->has_variable_prices() ) {
						$child_license->update_meta( '_edd_sl_download_price_id', $price_id );
						$child_license->reset_activation_limit();
					}

				}

			}

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
		return false;
	}

	/**
	 * A list of attributes and their associated post_meta keys. To aid developers in mapping the new class.
	 *
	 * @since 3.5
	 * @return array
	 */
	private function property_map() {
		$mapping = array(
			'activation_limit' => '_edd_sl_limit',
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

	/**
	 * Add license log.
	 *
	 * @since 3.6
	 *
	 * @param $title
	 * @param string|array $message Message to add as a log. Arrays are converted to JSON.
	 * @param string|array $type Log type(s).
	 * @return int|WP_Error Log ID.
	 */
	public function add_log( $title, $message = null, $type = null ) {
		$log_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => 'edd-license-log-' . $this->ID . '-' . md5( current_time( 'timestamp' ) ),
				'post_type'    => 'edd_license_log',
				'post_content' => is_array( $message ) ? json_encode( $message ) : $message,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			)
		);

		add_post_meta( $log_id, '_edd_sl_log_license_id', $this->ID );

		if ( ! is_null( $type ) ) {
			wp_set_object_terms( $log_id, $type, 'edd_log_type', false );
		}

		return $log_id;
	}

	/**
	 * Get the license logs.
	 *
	 * @since 3.6
	 *
	 * @return array List of logs.
	 */
	public function get_logs() {
		$query_args = apply_filters( 'edd_sl_license_logs_query_args', array(
			'post_type'              => 'edd_license_log',
			'meta_key'               => '_edd_sl_log_license_id',
			'meta_value'             => $this->ID,
			'posts_per_page'         => 1000,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		) );

		$log_query = new WP_Query( $query_args );

		return apply_filters( 'edd_sl_get_license_logs', $log_query->posts );
	}

	/**
	 * Backfill customer ID if empty. In rare cases this was empty when keys were migrated in 3.6.
	 * See https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1300
	 *
	 * @since 3.6
	 *
	 * @return void
	 */
	private function maybe_backfill_customer() {

		if( empty( $this->customer_id ) ) {

			if( ! empty( $this->user_id ) ) {

				$customer = new EDD_Customer( $this->user_id, true );

				if( $customer && $customer->id > 0 ) {

					$this->customer_id = $customer->id;
					$this->update( array( 'customer_id' => $this->customer_id ) );

				}

			}

			// If we do not have a user ID or no customer record was found via the user ID, look for a customer from the associated payments
			if( ! empty( $this->payment_ids ) && ( empty( $this->user_id ) || empty( $customer->id ) ) ) {

				// Remove any payment IDs that came in as zero during migration
				$this->payment_ids = array_filter( $this->payment_ids );
				foreach( $this->payment_ids as $payment_id ) {

					$customer_id = edd_get_payment_customer_id( $payment_id );

					if( $customer_id ) {
						$this->customer_id = $customer_id;
						$this->update( array( 'customer_id' => $this->customer_id ) );
						break;
					}

				}

			}

		}

	}

	/**
	 * Backfill payment ID if empty. In rare cases this was empty when keys were migrated in 3.6.
	 * See https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1300
	 *
	 * @since 3.6
	 *
	 * @return void
	 */
	private function maybe_backfill_payment() {

		if( empty( $this->payment_id ) && ! empty( $this->payment_ids ) ) {

			// Remove any payment IDs that came in as zero during migration
			$this->payment_ids = array_filter( $this->payment_ids );
			$this->payment_id  = current( $this->payment_ids );

			$this->update( array( 'payment_id' => $this->payment_id ) );

		}

	}

}
