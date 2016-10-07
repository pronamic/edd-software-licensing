<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Retrieve upgrade paths for a Download
 *
 * @since 3.3
 * @return array
 */
function edd_sl_get_upgrade_paths( $download_id = 0 ) {

	if( empty( $download_id ) ) {
		return false;
	}

	$paths = get_post_meta( $download_id, '_edd_sl_upgrade_paths', true );

	/* paths look like this
	$paths = array(
		array(
			'download_id' => $download_id,
			'price_id'    => 2,
			'discount'    => 0,
			'pro_rated'   => false
		),
		array(
			'download_id' => $download_id,
			'price_id'    => 3,
			'discount'    => 10,
			'pro_rated'   => true
		)
	);
	*/

	return apply_filters( 'edd_sl_get_upgrade_paths', $paths, $download_id );
}

/**
 * Retrieve the details for a specific upgrade path
 *
 * @since 3.3
 * @return array
 */
function edd_sl_get_upgrade_path( $download_id = 0, $upgrade_id = 0 ) {

	$upgrade  = false;
	$upgrades = edd_sl_get_upgrade_paths( $download_id );

	if( isset( $upgrades[ $upgrade_id ] ) ) {
		$upgrade = $upgrades[ $upgrade_id ];
	}

	return apply_filters( 'edd_sl_get_upgrade_path', $upgrade, $download_id, $upgrade_id );

}

/**
 * Retrieve the possible upgrades for a license
 *
 * @since 3.3
 * @return array
 */
function edd_sl_get_license_upgrades( $license_id = 0 ) {

	$upgrade_paths = false;

	if ( ! empty( $license_id ) ) {
		$payment_id = edd_software_licensing()->get_payment_id( $license_id );
		$payment    = new EDD_Payment( $payment_id );

		if ( 'publish' === $payment->status ) {

			$download_id = edd_software_licensing()->get_download_id( $license_id );
			$price_id    = edd_software_licensing()->get_price_id( $license_id );

			$upgrade_paths = edd_sl_get_upgrade_paths( $download_id );

			if ( is_array( $upgrade_paths ) ) {

				foreach ( $upgrade_paths as $key => $path ) {

					if ( edd_has_variable_prices( $download_id ) ) {

						// If there is a different product in the upgrade paths, upgrade is available
						if ( (int) $path['download_id'] === (int) $download_id ) {

							// If same download but with a more expensive price ID is in upgrade paths, upgrade is available
							if ( (int) $path['price_id'] !== (int) $price_id ) {

								if ( edd_get_price_option_amount( $path['download_id'], $path['price_id'] ) <= edd_get_price_option_amount( $download_id, $price_id ) ) {

									unset( $upgrade_paths[$key] );

								}

							} else {

								if ( edd_get_price_option_amount( $path['download_id'], $path['price_id'] ) <= edd_get_price_option_amount( $download_id, $price_id ) ) {

									unset( $upgrade_paths[$key] );

								}

							}

						}

					} else {

						// If there is a different product in the upgrade paths, upgrade is available
						if ( (int) $path['download_id'] === (int) $download_id ) {

							unset( $upgrade_paths[$key] );

						}

					}

				}

			}

		}

	}

	return apply_filters( 'edd_sl_get_license_upgrade_paths', $upgrade_paths, $license_id );
}

/**
 * Determine if there are upgrades available for a license
 *
 * @since 3.3
 * @return bool
 */
function edd_sl_license_has_upgrades( $license_id = 0 ) {

	$ret = false;

	if ( empty( $license_id ) ) {
		$ret = false;
	}

	$has_parent = get_post_field( 'post_parent', $license_id );

	if ( empty( $has_parent ) ) {
		$download_id   = edd_software_licensing()->get_download_id( $license_id );
		$price_id      = edd_software_licensing()->get_price_id( $license_id );

		$upgrade_paths = edd_sl_get_upgrade_paths( $download_id );
		$payment_id    = edd_software_licensing()->get_payment_id( $license_id );
		$payment       = new EDD_Payment( $payment_id );

		if ( is_array( $upgrade_paths ) && 'publish' === $payment->status ) {

			foreach( $upgrade_paths as $path ) {

				if ( edd_has_variable_prices( $download_id ) ) {

					// If there is a different product in the upgrade paths, upgrade is available
					if ( (int) $path['download_id'] !== (int) $download_id ) {

						$ret = true;

					} else {

						// If same download but with a more expensive price ID is in upgrade paths, upgrade is available
						if ( (int) $path['price_id'] !== (int) $price_id ) {

							if( edd_get_price_option_amount( $path['download_id'], $path['price_id'] ) > edd_get_price_option_amount( $download_id, $price_id ) ) {

								$ret = true;

							}

						}

					}

				} else {

					// If there is a different product in the upgrade paths, upgrade is available
					if( (int) $path['download_id'] !== (int) $download_id ) {
						$ret = true;
					}

				}

			}

		}
	}

	return apply_filters( 'edd_sl_license_has_upgrades', $ret, $license_id );
}

/**
 * Retrieve the upgrade URL for a license
 *
 * @since 3.3
 * @return string
 */
function edd_sl_get_license_upgrade_url( $license_id = 0, $upgrade_id = 0 ) {

	$url         = home_url();
	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$upgrades    = edd_sl_get_upgrade_paths( $download_id );

	if( is_array( $upgrades ) && isset( $upgrades[ $upgrade_id ] ) ) {

		$url = wp_nonce_url( add_query_arg( array(
			'edd_action' => 'sl_license_upgrade',
			'license_id' => $license_id,
			'upgrade_id' => $upgrade_id
		), edd_get_checkout_uri() ), 'edd_sl_upgrade_nonce' );

	}

	return apply_filters( 'edd_sl_license_upgrade_url', $url, $license_id, $upgrade_id );
}

/**
 * Retrieve the cost to upgrade a license
 *
 * @since 3.3
 * @return float
 */
function edd_sl_get_license_upgrade_cost( $license_id = 0, $upgrade_id = 0 ) {

	$url         = home_url();
	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$upgrades    = edd_sl_get_upgrade_paths( $download_id );

	if( edd_has_variable_prices( $download_id ) ) {

		$price_id = edd_software_licensing()->get_price_id( $license_id );

		if( false !== $price_id && '' !== $price_id ) {

			$old_price = edd_get_price_option_amount( $download_id, $price_id );

		} else {

			$old_price = edd_get_lowest_price_option( $download_id );

		}

	} else {

		$old_price = edd_get_download_price( $download_id );

	}


	if( isset( $upgrades[ $upgrade_id ][ 'price_id' ] ) && false !== $upgrades[ $upgrade_id ][ 'price_id' ] ) {

		$new_price = edd_get_price_option_amount( $upgrades[ $upgrade_id ][ 'download_id' ], $upgrades[ $upgrade_id ][ 'price_id' ] );

	} else {

		$new_price = edd_get_download_price( $upgrades[ $upgrade_id ][ 'download_id' ] );

	}

	$cost = $new_price;

	if( ! empty( $upgrades[ $upgrade_id ][ 'pro_rated' ] ) ) {

		$cost -= $old_price;

	}


	if( isset( $upgrades[ $upgrade_id ][ 'discount' ] ) ) {

		$cost -= $upgrades[ $upgrade_id ][ 'discount' ];

	}

	if( $cost < 0 ) {
		$cost = 0;
	}

	return apply_filters( 'edd_sl_license_upgrade_cost', $cost, $license_id, $upgrade_id );
}

/**
 * Add license upgrade to the cart
 *
 * @since 3.3
 * @return void
 */
function edd_sl_add_upgrade_to_cart( $data ) {

	if( ! is_user_logged_in() ) {
		return;
	}

	if( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'edd_sl_upgrade_nonce' ) ) {
		return;
	}

	// Only allow upgrading when the payment ID for the license is completed
	$payment_id = edd_software_licensing()->get_payment_id( $data['license_id'] );
	$payment    = new EDD_Payment( $payment_id );
	if ( 'publish' !== $payment->status ) {
		return;
	}

	$download_id = edd_software_licensing()->get_download_id( $data['license_id'] );

	$cart_contents = edd_get_cart_contents();
	$allow_upgrade = true;

	if ( is_array( $cart_contents ) ) {
		foreach ( $cart_contents as $item ) {
			if ( $item['id'] == $download_id && ! empty( $item['options']['is_renewal'] ) ) {
				$allow_upgrade = false;
				break;
			}
		}
	}

	if( 'expired' === edd_software_licensing()->get_license_status( $data['license_id'] ) ) {

		$is_expired    = true;
		$allow_upgrade = false;

	}

	if ( $allow_upgrade ) {

		// If this license ID is already in the cart, remove it to add the new choice
		foreach ( $cart_contents as $key => $item ) {
			if ( isset( $item['options']['license_id'] ) && $item['options']['license_id'] == $data['license_id'] ) {
				edd_remove_from_cart( $key );
				break;
			}
		}

		$upgrade     = edd_sl_get_upgrade_path( $download_id, $data['upgrade_id'] );
		$options     = array(
			'price_id'   => $upgrade['price_id'],
			'is_upgrade' => true,
			'upgrade_id' => $data['upgrade_id'],
			'license_id' => $data['license_id'],
			'cost'       => edd_sl_get_license_upgrade_cost( $data['license_id'], $data['upgrade_id'] ),
		);

		edd_add_to_cart( $upgrade['download_id'], $options );

	} else {

		if( ! empty( $is_expired ) ) {

			edd_set_error( 'edd-sl-expired-license', __( 'Your license key is expired. It must be renewed before it can be upgraded.', 'edd_sl' ) );

		} else {

			edd_set_error( 'edd-sl-unique-action', __( 'License renewals and upgrades must be purchased separately. Please complete your license renewal before upgrading it.', 'edd_sl' ) );

		}

	}

	wp_redirect( edd_get_checkout_uri() );
	exit;

}
add_action( 'edd_sl_license_upgrade', 'edd_sl_add_upgrade_to_cart' );

/**
 * @since 3.3
 * @param $price float The current item price
 * @param $download_id int Download product ID
 * @param $options array the cart item options
 * @return float
 */
function edd_sl_license_upgrade_cart_item_price( $price, $download_id, $options ) {

	if( empty( $options['is_upgrade'] ) || ! isset( $options['upgrade_id'] ) ) {
		return $price;
	}

	return $options['cost'];
}
add_filter( 'edd_cart_item_price', 'edd_sl_license_upgrade_cart_item_price', 10, 3 );

/**
 * @since 3.3
 * @param $price float The current item price
 * @param $download_id int Download product ID
 * @param $options array the cart item options
 * @return float
 */
function edd_sl_license_upgrade_cart_item_price_label( $label, $download_id, $options ) {

	if( empty( $options['is_upgrade'] ) || ! isset( $options['upgrade_id'] ) ) {
		return $label;
	}

	return $label . ' - ' . __( '<em>license upgrade</em>', 'edd_sl' );
}
add_filter( 'edd_cart_item_price_label', 'edd_sl_license_upgrade_cart_item_price_label', 10, 3 );

/**
 * Do not permit renewals if there is an upgrade in the cart
 *
 * @since 3.3
 * @return bool
 */
function edd_sl_disable_renewals_on_upgrades( $ret ) {

	$cart_items = edd_get_cart_contents();
	if( $cart_items ) {
		foreach( $cart_items as $item ) {
			if( isset( $item['options']['is_upgrade'] ) ) {
				return false;
			}
		}
	}

	return $ret;

}
add_filter( 'edd_sl_renewals_allowed', 'edd_sl_disable_renewals_on_upgrades' );

/**
 * Process the license upgrade during purchase
 *
 * @since 3.3
 * @return void
 */
function edd_sl_process_license_upgrade( $download_id = 0, $payment_id = 0, $type = 'default', $cart_item = array(), $cart_index = 0 ) {

	// Bail if this is not a renewal item
	if( empty( $cart_item['item_number']['options']['is_upgrade'] ) ) {
		return;
	}

	$license_id      = $cart_item['item_number']['options']['license_id'];
	$upgrade_id      = $cart_item['item_number']['options']['upgrade_id'];
	$old_payment_ids = get_post_meta( $license_id, '_edd_sl_payment_id' );
	$old_payment_id  = end( $old_payment_ids ); // We only want the most recent one
	$old_download_id = edd_software_licensing()->get_download_id( $license_id );
	$old_price_id    = edd_software_licensing()->get_price_id( $license_id );
	$purchase_date   = get_post_field( 'post_date', $old_payment_id );
	$upgrade         = edd_sl_get_upgrade_path( $old_download_id, $upgrade_id );

	if( edd_is_bundled_product( $download_id ) && ! edd_is_bundled_product( $old_download_id ) ) {

		// Upgrade to a bundle from a standard license

		$downloads         = array();
		$bundle_licensing  = (bool) get_post_meta( $download_id, '_edd_sl_enabled', true );
		$parent_license_id = 0;
		$activation_limit  = false;
		$user_info         = edd_get_payment_meta_user_info( $payment_id );

		if ( $bundle_licensing ) {
			$downloads[] = $download_id;
		}

		$downloads = array_merge( $downloads, edd_get_bundled_products( $download_id ) );

		if ( edd_has_variable_prices( $download_id ) ) {
			$activation_limit = edd_software_licensing()->get_price_activation_limit( $download_id, $cart_item['item_number']['options']['price_id'] );
			$is_lifetime      = edd_software_licensing()->get_price_is_lifetime( $download_id, $cart_item['item_number']['options']['price_id'] );
		}


		foreach ( $downloads as $d_id ) {

			if( (int) $d_id === (int) $old_download_id ) {
				continue;
			}

			if ( ! get_post_meta( $d_id, '_edd_sl_enabled', true ) ) {
				continue;
			}

			$license_title = get_the_title( $d_id ) . ' - ' . $user_info['email'];

			$license_args = array(
				'post_type'   => 'edd_license',
				'post_title'  => $license_title,
				'post_status' => 'publish',
				'post_date'   => get_post_field( 'post_date', $payment_id, 'raw' )
			);

			if ( $parent_license_id ) {
				$license_args['post_parent'] = $parent_license_id;
			}

			$l_id = wp_insert_post( apply_filters( 'edd_sl_insert_license_args', $license_args ) );

			if ( $bundle_licensing && $download_id == $d_id && ! $parent_license_id ) {
				$parent_license_id = $l_id;
			}

			$license_key = edd_software_licensing()->get_new_download_license_key( $d_id );

			if( ! $license_key ) {
				// No predefined license key available, generate a random one
				$license_key = edd_software_licensing()->generate_license_key( $l_id, $d_id, $payment_id, $cart_index );
			}

			$price_id = isset( $cart_item['item_number']['options']['price_id'] ) ? (int) $cart_item['item_number']['options']['price_id'] : false;

			add_post_meta( $l_id, '_edd_sl_download_id', $d_id );

			if( false !== $price_id ) {
				add_post_meta( $l_id, '_edd_sl_download_price_id', $price_id );
			}

			add_post_meta( $l_id, '_edd_sl_cart_index', $cart_index );
			add_post_meta( $l_id, '_edd_sl_payment_id', $payment_id );
			add_post_meta( $l_id, '_edd_sl_key', $license_key );
			add_post_meta( $l_id, '_edd_sl_user_id', $user_info['id'] );
			add_post_meta( $l_id, '_edd_sl_status', 'inactive' );

			if ( $parent_license_id && ! empty( $activation_limit ) ) {
				add_post_meta( $l_id, '_edd_sl_limit', $activation_limit );
			}

			// Get license length
			$license_length = edd_software_licensing()->get_license_length( $l_id, $payment_id, $d_id );

			if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {
				// Set license expiration date
				delete_post_meta( $l_id, '_edd_sl_is_lifetime' );
				edd_software_licensing()->set_license_expiration( $l_id, strtotime( $license_length, strtotime( $purchase_date ) ) );
			} else {
				edd_software_licensing()->set_license_as_lifetime( $l_id );
			}

			do_action( 'edd_sl_store_license', $l_id, $d_id, $payment_id, $type );

		}

		// Now update the original license

		wp_update_post( array( 'ID' => $license_id, 'post_parent' => $parent_license_id ) );

		update_post_meta( $license_id, '_edd_sl_cart_index', $cart_index );
		add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );

	} else if ( edd_is_bundled_product( $download_id ) && edd_is_bundled_product( $old_download_id ) ) {

		// Bundle to Bundle upgrade

		// Change out the details on the bundle license
		$new_title = get_the_title( $download_id );

		if( edd_has_variable_prices( $download_id ) ) {
			$new_title .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
		}

		$new_title .= ' - ' . edd_get_payment_user_email( $payment_id );

		wp_update_post( array( 'ID' => $license_id, 'post_title' => $new_title ) );

		update_post_meta( $license_id, '_edd_sl_cart_index', $cart_index );
		add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );
		update_post_meta( $license_id, '_edd_sl_download_id', $download_id );

		if( edd_has_variable_prices( $download_id ) ) {

			$limit       = edd_software_licensing()->get_price_activation_limit( $download_id, $upgrade['price_id'] );
			$is_lifetime = edd_software_licensing()->get_price_is_lifetime( $download_id, $upgrade['price_id'] );

			update_post_meta( $license_id, '_edd_sl_download_price_id', $upgrade['price_id'] );

		} else {

			$limit = edd_software_licensing()->get_license_limit( $download_id, $license_id );

		}

		update_post_meta( $license_id, '_edd_sl_limit', $limit );

		$license_length = edd_software_licensing()->get_license_length( $license_id, $payment_id, $download_id );

		if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {
			// Set license expiration date
			delete_post_meta( $license_id, '_edd_sl_is_lifetime' );
			edd_software_licensing()->set_license_expiration( $license_id, strtotime( $license_length, strtotime( $purchase_date ) ) );
		} else {
			edd_software_licensing()->set_license_as_lifetime( $license_id );
		}

		// Now see if the bundle contains any new items we need to add, or update existing items if the bundles have overlap in the downloads
		$downloads         = array();
		$bundle_licensing  = (bool) get_post_meta( $download_id, '_edd_sl_enabled', true );
		$user_info         = edd_get_payment_meta_user_info( $payment_id );

		if ( $bundle_licensing ) {
			$downloads[] = $download_id;
		}

		$downloads = array_merge( $downloads, edd_get_bundled_products( $download_id ) );

		foreach ( $downloads as $d_id ) {

			if( (int) $d_id === (int) $old_download_id ) {
				continue;
			}

			if ( ! get_post_meta( $d_id, '_edd_sl_enabled', true ) ) {
				continue;
			}

			$existing_license = edd_software_licensing()->get_license_by_purchase( $old_payment_id, $d_id );
			if ( ! empty( $existing_license ) ) {
				continue;
			}

			$license_title    = get_the_title( $d_id ) . ' - ' . $user_info['email'];

			$license_args = array(
				'post_type'   => 'edd_license',
				'post_title'  => $license_title,
				'post_status' => 'publish',
				'post_date'   => get_post_field( 'post_date', $payment_id, 'raw' ),
				'post_parent' => $license_id,
			);

			$l_id        = wp_insert_post( apply_filters( 'edd_sl_insert_license_args', $license_args ) );
			$license_key = edd_software_licensing()->get_new_download_license_key( $d_id );

			if( ! $license_key ) {
				// No predefined license key available, generate a random one
				$license_key = edd_software_licensing()->generate_license_key( $l_id, $d_id, $payment_id, $cart_index );
			}

			$price_id = isset( $cart_item['item_number']['options']['price_id'] ) ? (int) $cart_item['item_number']['options']['price_id'] : false;

			add_post_meta( $l_id, '_edd_sl_download_id', $d_id );

			if( false !== $price_id ) {
				add_post_meta( $l_id, '_edd_sl_download_price_id', $price_id );
			}

			add_post_meta( $l_id, '_edd_sl_cart_index', $cart_index );
			add_post_meta( $l_id, '_edd_sl_payment_id', $payment_id );
			add_post_meta( $l_id, '_edd_sl_key', $license_key );
			add_post_meta( $l_id, '_edd_sl_user_id', $user_info['id'] );
			add_post_meta( $l_id, '_edd_sl_status', 'inactive' );
			add_post_meta( $l_id, '_edd_sl_site_count', 0 );

			if ( ! empty( $limit ) ) {
				add_post_meta( $l_id, '_edd_sl_limit', $limit );
			}

			// Get license length
			$license_length = edd_software_licensing()->get_license_length( $l_id, $payment_id, $d_id );

			if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {
				// Set license expiration date
				delete_post_meta( $l_id, '_edd_sl_is_lifetime' );
				edd_software_licensing()->set_license_expiration( $l_id, strtotime( $license_length, strtotime( $purchase_date ) ) );
			} else {
				edd_software_licensing()->set_license_as_lifetime( $l_id );
			}

			do_action( 'edd_sl_store_license', $l_id, $d_id, $payment_id, $type );

		}


	} else {

		// Standard license upgrade

		$new_title = get_the_title( $download_id );

		if( edd_has_variable_prices( $download_id ) ) {
			$new_title .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
		}

		$new_title .= ' - ' . edd_get_payment_user_email( $payment_id );

		wp_update_post( array( 'ID' => $license_id, 'post_title' => $new_title ) );

		update_post_meta( $license_id, '_edd_sl_cart_index', $cart_index );
		add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );
		update_post_meta( $license_id, '_edd_sl_download_id', $download_id );

		if( edd_has_variable_prices( $download_id ) ) {

			$limit       = edd_software_licensing()->get_price_activation_limit( $download_id, $upgrade['price_id'] );
			$is_lifetime = edd_software_licensing()->get_price_is_lifetime( $download_id, $upgrade['price_id'] );

			update_post_meta( $license_id, '_edd_sl_download_price_id', $upgrade['price_id'] );

		} else {

			$limit = edd_software_licensing()->get_license_limit( $download_id, $license_id );

		}

		update_post_meta( $license_id, '_edd_sl_limit', $limit );

		$license_length = edd_software_licensing()->get_license_length( $license_id, $payment_id, $download_id );

		if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {
			// Set license expiration date
			delete_post_meta( $license_id, '_edd_sl_is_lifetime' );
			edd_software_licensing()->set_license_expiration( $license_id, strtotime( $license_length, strtotime( $purchase_date ) ) );
		} else {
			edd_software_licensing()->set_license_as_lifetime( $license_id );
		}

	}

	// Now store upgrade details / notes on payments

	$old_product = get_the_title( $old_download_id );
	if( edd_has_variable_prices( $old_download_id ) && false !== $old_price_id ) {
		$old_product .= ' - ' . edd_get_price_option_name( $old_download_id, $old_price_id );
	}

	$new_product = get_the_title( $download_id );
	if( edd_has_variable_prices( $download_id ) ) {
		$new_product .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
	}

	$note = sprintf( __( 'License upgraded from %s to %s', 'edd_sl' ), $old_product, $new_product );

	edd_insert_payment_note( $payment_id, $note );

	update_post_meta( $payment_id, '_edd_sl_upgraded_payment_id', $old_payment_id );
	update_post_meta( $old_payment_id, '_edd_sl_upgraded_to_payment_id', $payment_id );

	$args = array(
		'payment_id'       => $payment_id,
		'old_payment_id'   => $old_payment_id,
		'download_id'      => $download_id,
		'old_download_id'  => $old_download_id,
		'old_price_id'     => $old_price_id,
		'upgrade_id'       => $upgrade_id,
		'upgrade_price_id' => false
	);

	if ( isset( $upgrade[ 'price_id' ] ) ) {
		$args[ 'upgrade_price_id' ] = $upgrade[ 'price_id' ];
	}

	/**
	 * Fires after a license is upgraded
	 *
	 * @since 3.4.7
	 *
	 * @param int $license_id ID of license being upgraded
	 * @param array $args
	 */
	do_action( 'edd_sl_license_upgraded', $license_id, $args );

}
add_action( 'edd_complete_download_purchase', 'edd_sl_process_license_upgrade', 0, 5 );

/**
 * Displays upgraded to / from indicators
 *
 * @since 3.3
 * @return void
 */
function edd_sl_payment_details_inner_upgrade_history( $payment_id = 0 ) {

	$upgraded_from = edd_get_payment_meta( $payment_id, '_edd_sl_upgraded_payment_id', true );
	$upgraded_to   = edd_get_payment_meta( $payment_id, '_edd_sl_upgraded_to_payment_id', true );

	if( $upgraded_from ) :

		$view_url = esc_url( admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' ) . $upgraded_from );
?>
	<div class="edd-admin-box-inside">
		<p>
			<?php printf( '<strong>%s:</strong> <a href="%s">#%s</a>', __( 'Upgraded from', 'edd_sl' ), $view_url, edd_get_payment_number( $upgraded_from ) ); ?>&nbsp;
		</p>
	</div>
<?php
	endif;

	if( $upgraded_to ) :

		$view_url = esc_url( admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' ) . $upgraded_to );
?>
	<div class="edd-admin-box-inside">
		<p>
			<?php printf( '<strong>%s:</strong> <a href="%s">#%s</a>', __( 'Upgraded to', 'edd_sl' ), $view_url, edd_get_payment_number( $upgraded_to ) ); ?>&nbsp;
		</p>
	</div>
<?php
	endif;
}
add_action( 'edd_view_order_details_payment_meta_after', 'edd_sl_payment_details_inner_upgrade_history' );

/**
 * Retrieve upgrade count and earnings for specific date
 *
 * @since 3.3
 * @return array
 */
function edd_sl_get_upgrades_by_date( $day = null, $month = null, $year = null, $hour = null  ) {

	$args = apply_filters( 'edd_get_upgrades_by_date', array(
		'nopaging'    => true,
		'post_type'   => 'edd_payment',
		'post_status' => array( 'revoked', 'publish' ),
		'meta_key'    => '_edd_sl_upgraded_payment_id',
		'year'        => $year,
		'monthnum'    => $month,
		'fields'      => 'ids'
	), $day, $month, $year );

	if ( ! empty( $day ) ) {
		$args['day'] = $day;
	}

	if ( ! empty( $hour ) ) {
		$args['hour'] = $hour;
	}

	$upgrades = get_posts( $args );

	$return   = array();
	$return['earnings'] = 0;
	$return['count']    = count( $upgrades );
	if ( $upgrades ) {
		foreach ( $upgrades as $upgrade ) {
			$return['earnings'] += edd_get_payment_amount( $upgrade );
		}
	}
	return $return;
}
