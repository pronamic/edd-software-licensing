<?php
/**
 * Easy Digital Downloads WP-CLI Tools for Software Licensing
 *
 * This class provides an integration point with the WP-CLI plugin allowing
 * access to EDD from the command line.
 *
 * @package     EDD
 * @subpackage  SoftawreLicensing/Integrations/CLI
 * @copyright   Copyright (c) 2015, Chris Klosowski
 * @license     http://opensource.org/license/gpl-2.0.php GNU Public License
 * @since       3.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

WP_CLI::add_command( 'edd-sl', 'EDD_SL_CLI' );

/**
 * Work with EDD through WP-CLI
 *
 * EDD_CLI Class
 *
 * Adds CLI support to EDD through WP-CL
 *
 * @since   1.0
 */
class EDD_SL_CLI extends EDD_CLI {
	/**
	 * Migrate the Software Licensing to the custom tables
	 *
	 * ## OPTIONS
	 *
	 * --force=<boolean>: If the routine should be run even if the upgrade routine has been run already
	 *
	 * ## EXAMPLES
	 *
	 * wp edd-sl migrate_licenses
	 * wp edd-sl migrate_licenses --force
	 */
	public function migrate_licenses( $args, $assoc_args ) {
		global $wpdb;

		// Force Debug Mode on so we can log failures.
		add_filter( 'edd_is_debug_mode', '__return_true' );

		// Don't throw deprecated notices while migrating.
		add_filter( 'eddsl_show_deprecated_notices', '__return_false' );

		// Make sure to turn off our caching layers.
		if ( ! defined( 'DOING_SL_MIGRATION' ) ) {
			define( 'DOING_SL_MIGRATION', true );
		}

		$force = isset( $assoc_args[ 'force' ] ) ? true : false;

		$upgrade_completed = edd_has_upgrade_completed( 'migrate_licenses' );

		if ( ! $force && $upgrade_completed ) {
			WP_CLI::error( __( 'The software licenses custom database migration has already been run. To do this anyway, use the --force argument.', 'edd_sl' ) );
		}

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

		$sql     = "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_license'";
		$results = $wpdb->get_col( $sql, 0 );
		$total   = count( $results );

		if ( ! empty( $total ) ) {

			update_option( 'edd_sl_is_migrating_licenses', 1 );

			// Store any licenses that will need a parent/child relationship to be corrected.
			$child_licenses = array();

			// Store all the new licenses, Key = Legacy License ID, Value = new License ID
			$new_license_ids = array();

			$progress = new \cli\progress\Bar( 'Migrating Licenses', $total );

			foreach ( $results as $post_id ) {

				$license_post = $wpdb->get_row( "SELECT * FROM $wpdb->posts WHERE ID = {$post_id} LIMIT 1" );

				// Prevent an already migrated item from being migrated.
				$migrated = $wpdb->get_var( "SELECT meta_id FROM {$license_meta_db->table_name} WHERE meta_key = '_edd_sl_legacy_id' AND meta_value = $license_post->ID" );
				if ( ! empty( $migrated ) ) {
					$progress->tick();
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

											$completed_date = edd_get_payment_completed_date( $payment_id );
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
					if ( isset( $post_meta['_edd_sl_limit'] ) ) {
						$download = new EDD_SL_Download( $post_meta['_edd_sl_download_id'] );
						if ( $download->ID === (int) $post_meta['_edd_sl_download_id'] ) {
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

				}

				$progress->tick();
			}

			edd_set_upgrade_complete( 'migrate_licenses' );
			$progress->finish();

			// Now migrate any parent/child relationships if necessary
			if ( ! empty( $child_licenses ) ) {
				$child_progress = new \cli\progress\Bar( 'Fixing parent/child license associations', count( $child_licenses ) );

				foreach ( $child_licenses as $child_license_id => $legacy_parent_id ) {
					// If a new license didn't get created for this legacy ID, move along.
					if ( ! isset( $new_license_ids[ $legacy_parent_id ] ) ) {
						continue;
					}

					$new_parent_id         = $new_license_ids[ $legacy_parent_id ];
					$wpdb->query( "UPDATE {$licenses_db->table_name} SET parent = {$new_parent_id} WHERE id = {$child_license_id} LIMIT 1 ");

					$child_progress->tick();
				}

				$child_progress->finish();
			}
			edd_set_upgrade_complete( 'migrate_license_parent_child' );

			// Now that we're completed, migrate all license logs to the new license IDs from their Post IDs
			$license_logs_query = "SELECT meta_id, meta_value as legacy_license_id FROM {$wpdb->postmeta} WHERE meta_key = '_edd_sl_log_license_id'";
			$license_logs_meta  = $wpdb->get_results( $license_logs_query );
			if ( ! empty( $license_logs_meta ) ) {
				$log_progress = new \cli\progress\Bar( 'Updating license log relationships.', count( $license_logs_meta ) );

				foreach ( $license_logs_meta as $license_log_meta ) {
					// If a new license didn't get created for this legacy ID, move along.
					if ( ! isset( $new_license_ids[ $license_log_meta->legacy_license_id ] ) ) {
						continue;
					}

					$new_license_id = $new_license_ids[ $license_log_meta->legacy_license_id ];
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %d WHERE meta_id = %d", $new_license_id, $license_log_meta->meta_id ) );
					$log_progress->tick();
				}

				$log_progress->finish();
			}
			edd_set_upgrade_complete( 'migrate_license_logs' );


			// Now find any renewal payments that came in during the migration and do any necessary changes to them.
			// The collision rate here is pretty low, in our 4 hour migration, only 3 payments were affected.
			$payments_during_migration = get_option( 'edd_sl_payments_saved_during_migration', array() );
			if ( ! empty( $payments_during_migration ) ) {

				$processing_payments = new \cli\progress\Bar( 'Processing payments made during migration.', count( $payments_during_migration ) );

				foreach ( $payments_during_migration as $payment_id ) {

					// Allows developers to do anything they'd like to do.
					do_action( 'edd_sl_payment_saved_during_migration', $payment_id );

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
								$license->add_meta( '_edd_sl_payment_id', $payment->ID );

							}

						}

					}

					$processing_payments->tick();

				}

				$processing_payments->finish();

			}

			WP_CLI::line( __( 'License Migration complete.', 'edd_sl' ) );
			$new_count = $licenses_db->count();
			$old_count = $wpdb->get_col( "SELECT count(ID) FROM $wpdb->posts WHERE post_type ='edd_license'", 0 );
			WP_CLI::line( __( 'Old License Count: ', 'edd_sl' ) . $old_count[ 0 ] );
			WP_CLI::line( __( 'New License Count: ', 'edd_sl' ) . $new_count );

			update_option( 'edd_sl_version', preg_replace( '/[^0-9.].*/', '', EDD_SL_VERSION ) );

			delete_option( 'edd_sl_is_migrating_licenses' );

			WP_CLI::confirm( __( 'Remove legacy licenses?', 'edd_sl' ), $remove_args = array() );
			WP_CLI::line( __( 'Removing old license data.', 'edd_sl' ) );

			$license_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_license'", 0 );
			$license_ids = implode( ', ', $license_ids );

			$delete_posts_query = "DELETE FROM $wpdb->posts WHERE ID IN ({$license_ids})";
			$wpdb->query( $delete_posts_query );

			$delete_postmeta_query = "DELETE FROM $wpdb->postmeta WHERE post_id IN ({$license_ids})";
			$wpdb->query( $delete_postmeta_query );
			edd_set_upgrade_complete( 'remove_legacy_licenses' );
		} else {
			WP_CLI::line( __( 'No licenses found.', 'edd_sl' ) );
			edd_set_upgrade_complete( 'migrate_licenses' );
			edd_set_upgrade_complete( 'migrate_license_parent_child' );
			edd_set_upgrade_complete( 'migrate_license_logs' );
			edd_set_upgrade_complete( 'remove_legacy_licenses' );
		}

	}

	/*
	 * Add URLs to licenses
	 *
	 * ## OPTIONS
	 *
	 * --number=<int>: The number of licenses to add URLs to
	 *
	 * ## EXAMPLES
	 *
	 * wp edd-sl activate_licenses
	 * wp edd-sl activate_licenses --number=100
	 */
	public function activate_licenses( $args, $assoc_args ) {
		$number  = isset( $assoc_args['number'] ) ? absint( $assoc_args['number'] ) : 1;

		$found_licenses = array();
		$i = 1;
		while ( $i <= $number ) {
			$args = array(
				'post_type'      => 'edd_license',
				'orderby'        => 'rand',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			);

			$licenses   = get_posts( $args );
			$license_id = (int) $licenses[0];

			if ( ! array_key_exists( $license_id, $found_licenses ) ) {
				$license = edd_software_licensing()->get_license( $license_id );
				$found_licenses[ $license_id ] = $license;
			} else {
				$license = $found_licenses[ $license_id ];
			}

			$site = $this->generate_site();
			$license->add_site( $site );
			WP_CLI::line( sprintf( __( 'URL %s added to %s', 'edd_sl' ), $site, $license->key ) );
			$i++;
		}
	}

	private function generate_site() {
		return $this->get_domain() . '.' . $this->get_tld();
	}
}
