<?php
/**
 * Migrate Software Licenses
 *
 * This moves the licenses, their meta, and activated URLs to custom tables
 *
 * @subpackage  Admin/Classes/EDD_SL_License_Migration
 * @copyright   Copyright (c) 2015, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_SL_License_Migration Class
 *
 * @since 3.6
 */
class EDD_SL_License_Migration extends EDD_Batch_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions
	 * @var string
	 * @since 3.6
	 */
	public $export_type = '';

	/**
	 * Allows for a non-download batch processing to be run.
	 * @since  3.6
	 * @var boolean
	 */
	public $is_void = true;

	/**
	 * Sets the number of items to pull on each step
	 * @since  3.6
	 * @var integer
	 */
	public $per_step = 25;

	/**
	 * Get the Export Data
	 *
	 * @access public
	 * @since 3.6
	 * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @return array $data The data for the CSV file
	 */
	public function get_data() {
		global $wpdb;

		$items = $this->get_stored_data( 'edd_sl_legacy_license_ids' );

		if ( ! is_array( $items ) ) {
			return false;
		}

		$offset     = ( $this->step - 1 ) * $this->per_step;
		$step_items = array_slice( $items, $offset, $this->per_step );

		if ( $step_items ) {

			// Force Debug Mode on so we can log failures.
			add_filter( 'edd_is_debug_mode', '__return_true' );

			// Don't throw deprecated notices while migrating.
			add_filter( 'eddsl_show_deprecated_notices', '__return_false' );

			// Make sure to turn off our caching layers.
			if ( ! defined( 'DOING_SL_MIGRATION' ) ) {
				define( 'DOING_SL_MIGRATION', true );
			}

			$licenses_db     = edd_software_licensing()->licenses_db;
			$license_meta_db = edd_software_licensing()->license_meta_db;
			$activations_db  = edd_software_licensing()->activations_db;

			foreach ( $step_items as $item ) {

				$license_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'edd_license' AND ID = %d", $item ), 0 );

				// Prevent an already migrated item from being migrated.
				$migrated = $wpdb->get_var( "SELECT meta_id FROM {$license_meta_db->table_name} WHERE meta_key = '_edd_sl_legacy_id' AND meta_value = $license_post->ID" );
				if ( ! empty( $migrated ) ) {
					continue;
				}

				$meta_items  = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", $license_post->ID ) );
				$post_meta   = array();
				$payment_ids = array();
				foreach ( $meta_items as $meta_item ) {
					if ( '_edd_sl_payment_id' === $meta_item->meta_key ) {
						$payment_ids = array_merge( $payment_ids, array( absint( $meta_item->meta_value ) ) );
					} else {
						$post_meta[ $meta_item->meta_key ] = maybe_unserialize( $meta_item->meta_value );
					}
				}

				if ( empty( $post_meta['_edd_sl_key'] ) ) {
					edd_debug_log( sprintf( __( 'Legacy License ID %d failed: Missing license key', 'edd_sl'), $license_post->ID ) );
					continue;
				}

				// Sort the Payment IDs ascending so that the initial payment is the first one
				sort( $payment_ids );

				$download_id = isset( $post_meta['_edd_sl_download_id'] )       ? absint( $post_meta['_edd_sl_download_id'] )       : false;
				$price_id    = isset( $post_meta['_edd_sl_download_price_id'] ) ? absint( $post_meta['_edd_sl_download_price_id'] ) : false;

				$payment_id  = 0;
				$customer_id = 0;
				$cart_index  = 0;
				if ( ! empty( $payment_ids ) ) {

					$payment_ids = array_filter( $payment_ids );

					foreach( $payment_ids as $payment_id ) {

						$payment = edd_get_payment( $payment_id );
						if ( false !== $payment ) {
							$payment_id  = $payment->ID;
							$customer_id = $payment->customer_id;

							if ( ! isset( $post_meta['_edd_sl_cart_index'] ) ) {

								if ( false !== $payment ) {

									foreach ( $payment->cart_details as $index => $item ) {

										if ( (int) $item[ 'id' ] !== (int) $download_id ) {
											continue;
										}

										if ( false !== $price_id ) {

											$found_price_id = false;

											if ( ! isset( $item['item_number']['options'] ) || ! isset( $item['item_number']['options']['price_id'] ) ) {
												$found_price_id = isset( $item['options'] ) && isset( $item['options']['price_id'] ) ? $item['options']['price_id'] : false;
											} else {
												$found_price_id = $item['item_number']['options']['price_id'];
											}

											if ( (int) $found_price_id !== (int) $price_id ) {
												continue;
											}
										}

										$cart_index = $index;
										break;

									}
								}

							} else {
								$cart_index = (int) $post_meta['_edd_sl_cart_index'];
							}

							break;
						}

					}

				}

				$is_lifetime = isset( $post_meta['_edd_sl_is_lifetime'] ) && 1 === (int) $post_meta['_edd_sl_is_lifetime'];
				$expiration  = null;
				if ( $is_lifetime ) {
					$expiration = 0;
				} elseif ( ! empty( $post_meta['_edd_sl_expiration'] ) ) {
					$expiration = absint( $post_meta['_edd_sl_expiration'] );
				}

				$status = 'inactive';
				if ( 'draft' === $license_post->post_status ) {
					$status = 'disabled';
				} elseif ( 'private' === $license_post->post_status ) {
					$status = 'private';
				} else {
					if ( ! $is_lifetime && $expiration < current_time( 'timestamp' ) ) {
						$status = 'expired';
					} elseif ( isset( $post_meta['_edd_sl_sites'] ) && count( $post_meta['_edd_sl_sites'] ) > 0 ) {
						$status = 'active';
					}
				}

				// Prevent the use of -1 for the user ID (legacy value).
				$user_id = isset( $post_meta['_edd_sl_user_id'] ) && $post_meta['_edd_sl_user_id'] > 0 ? $post_meta['_edd_sl_user_id'] : 0;

				if ( empty( $customer_id ) ) {

					if ( ! empty( $user_id ) ) {
						$customer_id = EDD()->customers->get_column_by( 'id', 'user_id', $user_id );
					}

					// If we do not have a user ID or no customer record was found via the user ID, look for a customer from the associated payments
					if ( ! empty( $payment_ids ) && ( empty( $user_id ) || empty( $customer_id ) ) ) {

						foreach( $payment_ids as $payment_id ) {

							$customer_id = get_post_meta( $payment_id, '_edd_payment_customer_id', true );
							if ( ! empty( $customer_id ) ) {
								break;
							}

						}

					}

				}

				$license_data = array(
					'id'           => null,
					'license_key'  => $post_meta['_edd_sl_key'],
					'status'       => $status,
					'download_id'  => false !== $download_id ? $download_id : 0,
					'price_id'     => false !== $price_id ? $price_id : null,
					'payment_id'   => $payment_id,
					'cart_index'   => $cart_index,
					'date_created' => $license_post->post_date,
					'expiration'   => $expiration,
					'parent'       => $license_post->post_parent,
					'customer_id'  => $customer_id,
					'user_id'      => $user_id,
				);

				$column_formats = $licenses_db->get_columns();
				$inserted       = $wpdb->insert( $licenses_db->table_name, $license_data, $column_formats );

				if ( false !== $inserted ) {
					$new_license_id = $wpdb->insert_id;
				}

				if ( ! empty( $new_license_id ) ) {
					$new_license_ids[ (int) $license_post->ID ] = (int) $new_license_id;

					// Store the legacy parent ID so we can update the record at a later time.
					if ( ! empty( $license_post->post_parent ) ) {
						$child_licenses[ $new_license_id ] = $license_post->post_parent;
					}

					if ( count( $payment_ids ) > 1 ) {
						unset( $payment_ids[ 0 ] );
						foreach ( $payment_ids as $payment_id ) {
							$license_meta_db->add_meta( $new_license_id, '_edd_sl_payment_id', absint( $payment_id ) ) ;

							// We need to go directly to the db here to avoid any class performance impacts.
							// TODO: Update this for EDD 3.0 custom tables.
							$meta_data = $wpdb->get_row( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE post_id = {$payment_id} AND meta_key = '_edd_payment_meta'" );

							if ( ! empty( $meta_data ) ) {

								$payment_meta = maybe_unserialize( $meta_data->meta_value );
								if ( ! empty( $payment_meta ) ) {

									// Update the downloads array.
									if ( isset( $payment_meta['downloads'] ) && is_array( $payment_meta['downloads'] ) ) {
										foreach ( $payment_meta['downloads'] as $key => $item ) {

											if ( isset( $item['item_number'] ) ) {
												$item_number     = true;
												$item_license_id = ! empty( $item['item_number']['options']['license_id'] ) ? intval( $item['item_number']['options']['license_id'] ) : 0;
											} else {
												$item_number     = false;
												$item_license_id = ! empty( $item['options']['license_id'] ) ? intval( $item['options']['license_id'] ) : 0;
											}

											if ( empty( $item_license_id ) || $item_license_id !== (int) $license_post->ID ) {
												continue;
											}

											if ( $item_number ) {
												$payment_meta['downloads'][ $key ]['item_number']['options']['license_id'] = $new_license_id;
											} else {
												$payment_meta['downloads'][ $key ]['options']['license_id'] = $new_license_id;
											}

										}
									}

									// Update the cart_details array.
									if ( isset( $payment_meta['cart_details'] ) && is_array( $payment_meta['cart_details'] ) ) {

										foreach ( $payment_meta['cart_details'] as $key => $item ) {

											if ( isset( $item['item_number'] ) ) {
												$item_number     = true;
												$item_license_id = ! empty( $item['item_number']['options']['license_id'] ) ? intval( $item['item_number']['options']['license_id'] ) : 0;
											} else {
												$item_number     = false;
												$item_license_id = ! empty( $item['options']['license_id'] ) ? intval( $item['options']['license_id'] ) : 0;
											}

											if ( empty( $item_license_id ) || $item_license_id !== (int) $license_post->ID ) {
												continue;
											}

											if ( $item_number ) {
												$payment_meta['cart_details'][ $key ]['item_number']['options']['license_id'] = $new_license_id;
											} else {
												$payment_meta['cart_details'][ $key ]['options']['license_id'] = $new_license_id;
											}

											// Add the license meta for the upgrade or renewal date
											$action = false;
											if ( $item_number ) {
												if ( ! empty( $payment_meta['cart_details'][ $key ]['item_number']['options']['is_upgrade'] ) ) {
													$action = 'upgrade';
												} elseif ( ! empty( $payment_meta['cart_details'][ $key ]['item_number']['options']['is_renewal'] ) ) {
													$action = 'renewal';
												}
											} else {
												if ( ! empty( $payment_meta['cart_details'][ $key ]['options']['is_upgrade'] ) ) {
													$action = 'upgrade';
												} elseif ( ! empty( $payment_meta['cart_details'][ $key ]['options']['is_renewal'] ) ) {
													$action = 'renewal';
												}
											}

											$completed_date = get_post_meta( $payment_id, '_edd_completed_date', true );

											if ( ! empty( $action ) && ! empty( $completed_date ) ) {
												$license_meta_db->add_meta( $new_license_id, '_edd_sl_' . $action . '_date', $completed_date );
											}

										}

									}

									// Update by direct query to avoid any issues with class instantiation.
									$payment_meta = serialize( wp_slash( $payment_meta ) );
									$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '{$payment_meta}' WHERE meta_id = {$meta_data->meta_id} LIMIT 1" );

								}

							}
						}
					}

					$sites = array();
					if ( ! empty( $post_meta['_edd_sl_sites'] ) ) {

						$sites = array();
						foreach ( $post_meta['_edd_sl_sites'] as $site ) {
							$sites[] = edd_software_licensing()->clean_site_url( $site );
						}

					}

					if ( ! empty( $sites ) ) {
						// Scrub any possible duplicates from bad data.
						$sites = array_unique( $sites );

						foreach ( $sites as $site ) {
							$column_formats  = $activations_db->get_columns();
							$is_local        = edd_software_licensing()->is_local_url( $site );
							$activation_data = array(
								'site_id'    => null,
								'site_name'  => $site,
								'license_id' => $new_license_id,
								'activated'  => '1',
								'is_local'   => $is_local ? '1' : '0',
							);

							$wpdb->insert( $activations_db->table_name, $activation_data, $column_formats );
						}
					}

					// If an activation count is defined, check to see if it is the same as the download, and unset if true.
					if ( isset( $post_meta['_edd_sl_limit'] ) && $download_id ) {
						$download = new EDD_SL_Download( $download_id );
						if ( $download->ID === (int) $download_id ) {
							$activation_limit = $download->get_activation_limit( $price_id );
							if ( (int) $activation_limit === (int) $post_meta['_edd_sl_limit'] ) {
								unset( $post_meta['_edd_sl_limit'] );
							}
						}
					}

					// Unset the now defunct post meta items so they don't get set.
					unset( $post_meta['_edd_sl_key'] );
					unset( $post_meta['_edd_sl_user_id'] );
					unset( $post_meta['_edd_sl_download_id'] );
					unset( $post_meta['_edd_sl_download_price_id'] );
					unset( $post_meta['_edd_sl_sites'] );
					unset( $post_meta['_edd_sl_expiration'] );
					unset( $post_meta['_edd_sl_is_lifetime'] );
					unset( $post_meta['_edd_sl_cart_index'] );
					unset( $post_meta['_edd_sl_status'] );
					unset( $post_meta['_edd_sl_license_sites'] ); // This is a legacy data point that is no longer used.
					unset( $post_meta['post_title'] ); // This was a bug in EDD SL Master that is logging titles as meta.

					foreach ( $post_meta as $key => $value ) {
						$license_meta_db->add_meta( $new_license_id, $key, $value );
					}

					$license_meta_db->add_meta( $new_license_id, '_edd_sl_legacy_id', $license_post->ID );

					/**
					 * Allow developers to hook into this upgrade routine for this result, so they can move any meta they want.
					 * Developers: keep in mind any custom meta data has already been migrated over, this is just for any further
					 * customizations.
					 */
					do_action( 'eddsl_migrate_license', $license_post->ID, $new_license_id );

				} else {
					edd_debug_log( sprintf( __( 'Legacy License ID %d failed: Failed to insert new license record', 'edd_sl'), $license_post->ID ) );
				}

			}

			return true;

		}

		return false;

	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since 3.6
	 * @return int
	 */
	public function get_percentage_complete() {

		$items = $this->get_stored_data( 'edd_sl_legacy_license_ids', false );
		$total = count( $items );

		$percentage = 100;

		if( $total > 0 ) {
			$percentage = ( ( $this->per_step * $this->step ) / $total ) * 100;
		}

		if( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Set the properties specific to the payments export
	 *
	 * @since 3.6
	 * @param array $request The Form Data passed into the batch processing
	 */
	public function set_properties( $request ) {}

	/**
	 * Process a step
	 *
	 * @since 3.6
	 * @return bool
	 */
	public function process_step() {
		if ( ! $this->can_export() ) {
			wp_die( __( 'You do not have permission to migrate licenses.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 403 ) );
		}

		$had_data = $this->get_data();

		if( $had_data ) {
			$this->done = false;
			return true;
		} else {
			$this->done    = true;
			$new_count     = edd_software_licensing()->licenses_db->count();
			$old_count     = count( $this->get_stored_data( 'edd_sl_legacy_license_ids' ) );

			$this->delete_data( 'edd_sl_legacy_license_ids' );
			$this->message = sprintf( __( 'Licenses database upgrade complete. Migrated <span class="edd-sl-new-count">%d</span> of <span class="edd-sl-old-count">%d</span> licenses.', 'edd_sl' ), $new_count, $old_count );
			edd_set_upgrade_complete( 'migrate_licenses' );

			// Don't throw deprecated notices while migrating.
			add_filter( 'eddsl_show_deprecated_notices', '__return_false' );

			// Now find any renewal payments that came in during the migration and do any necessary changes to them.
			// The collision rate here is pretty low, in our 4 hour migration, only 3 payments were affected.
			$payments_during_migration = get_option( 'edd_sl_payments_saved_during_migration', array() );
			if ( ! empty( $payments_during_migration ) ) {

				foreach ( $payments_during_migration as $payment_id ) {

					// Allows developers to do anything they'd like to do.
					do_action( 'edd_sl_payment_created_during_migration', $payment_id );

					$payment = edd_get_payment( $payment_id );

					if ( false === $payment ) {
						continue;
					}

					// This was an automatic renewal, make sure that the meta exists and the licenses were renewed.
					if ( ! empty( $payment->parent_payment ) ) {

						// For every item in the renewal payment.
						foreach ( $payment->downloads as $item ) {

							// Get the license from the initial payment.
							$licenses = edd_software_licensing()->get_license_by_purchase( $payment->parent_payment, $item['id'] );

							// Should only be one, but just in case use a 'foreach'.
							foreach ( $licenses as $license ) {

								$license = edd_software_licensing()->get_license( $license );
								$license->add_meta( '_edd_sl_payment_id', $payment_id );

							}

						}

					}

				}

			}

			delete_option( 'edd_sl_is_migrating_licenses' );
			return false;
		}
	}

	public function headers() {
		ignore_user_abort( true );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}
	}

	/**
	 * Perform the export
	 *
	 * @access public
	 * @since 3.6
	 * @return void
	 */
	public function export() {

		// Set headers
		$this->headers();

		edd_die();
	}

	public function pre_fetch() {

		// Create the tables if necessary
		$licenses_db = edd_software_licensing()->licenses_db;
		if ( ! $licenses_db->table_exists( $licenses_db->table_name ) ) {
			@$licenses_db->create_table();
		}

		$license_meta_db = edd_software_licensing()->license_meta_db;
		if ( ! $license_meta_db->table_exists( $license_meta_db->table_name ) ) {
			@$license_meta_db->create_table();
		}

		$activations_db = edd_software_licensing()->activations_db;
		if ( ! $activations_db->table_exists( $activations_db->table_name ) ) {
			@$activations_db->create_table();
		}

		if ( $this->step == 1 ) {
			$this->delete_data( 'edd_sl_legacy_license_ids' );
			update_option( 'edd_sl_is_migrating_licenses', 1 );
		}

		$items = get_option( 'edd_sl_legacy_license_ids', false );

		if ( false === $items ) {
			$license_ids = array();

			$args = apply_filters( 'eddsl_license_migration_args', array(
				'post_type'      => 'edd_license',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$license_ids = get_posts( $args );
			$this->store_data( 'edd_sl_legacy_license_ids', $license_ids );
		}

	}

	/**
	 * Given a key, get the information from the Database Directly
	 *
	 * @since  3.6
	 * @param  string $key The option_name
	 * @return mixed       Returns the data from the database
	 */
	private function get_stored_data( $key ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = '%s'", $key ) );

		if ( empty( $value ) ) {
			return false;
		}

		$maybe_json = json_decode( $value );
		if ( ! is_null( $maybe_json ) ) {
			$value = json_decode( $value, true );
		}

		return $value;
	}

	/**
	 * Give a key, store the value
	 *
	 * @since  3.6
	 * @param  string $key   The option_name
	 * @param  mixed  $value  The value to store
	 * @return void
	 */
	private function store_data( $key, $value ) {
		global $wpdb;

		$value = is_array( $value ) ? wp_json_encode( $value ) : esc_attr( $value );

		$data = array(
			'option_name'  => $key,
			'option_value' => $value,
			'autoload'     => 'no',
		);

		$formats = array(
			'%s', '%s', '%s',
		);

		$wpdb->replace( $wpdb->options, $data, $formats );
	}

	/**
	 * Delete an option
	 *
	 * @since  3.6
	 * @param  string $key The option_name to delete
	 * @return void
	 */
	private function delete_data( $key ) {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => $key ) );
	}

}
