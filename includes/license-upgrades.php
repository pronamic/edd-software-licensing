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

		$url = add_query_arg( array(
			'edd_action' => 'sl_license_upgrade',
			'license_id' => $license_id,
			'upgrade_id' => $upgrade_id
		), edd_get_checkout_uri() );

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

		$cost = edd_sl_get_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );

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
 * Calculate the prorated cost to upgrade a license
 *
 * Calculations are based on the time remaining on a license instead of a price comparison. To use the price comparison,
 * use `add_filter( 'edd_sl_license_upgrade_pro_rate_simple', '__return_true' );`
 *
 * @since 3.5
 * @param int $license_id ID of license being upgraded
 * @param float|int $old_price Price of the license being upgraded
 * @param float|int $new_price Price of the new license level
 * @return float The prorated cost to upgrade the license
 */
function edd_sl_get_pro_rated_upgrade_cost( $license_id = 0, $old_price, $new_price ) {
	$proration_method = edd_get_option( 'edd_sl_proration_method', 'cost-based' );
	$proration_method = apply_filters( 'edd_sl_proration_method', $proration_method, $license_id, $old_price, $new_price );

	// Check global setting and handle accordingly, if the filter is used
	// to fall back to simple pro-ration, return the simple new - old price
	if ( $proration_method == 'cost-based' || apply_filters( 'edd_sl_license_upgrade_pro_rate_simple', false ) ) {
		$prorated = edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	} else {
		$prorated = edd_sl_get_time_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	}

	return apply_filters( 'edd_sl_get_pro_rated_upgrade_cost', $prorated, $license_id, $old_price, $new_price );
}

/**
 * Calculate the prorated cost based on cost
 *
 * @since 3.5
 * @param int $license_id ID of license being upgraded
 * @param float|int $old_price Price of the license being upgraded
 * @param float|int $new_price Price of the new license level
 * @return float The prorated cost to upgrade the license
 */
function edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id = 0, $old_price, $new_price ) {
	$prorated = $new_price - $old_price;

	return apply_filters( 'edd_sl_get_cost_based_pro_rated_upgrade_cost', $prorated, $license_id, $old_price, $new_price );
}

/**
 * Calculate the prorated cost based on cost
 *
 * @since 3.5
 * @param int $license_id ID of license being upgraded
 * @param float|int $old_price Price of the license being upgraded
 * @param float|int $new_price Price of the new license level
 * @return float The prorated cost to upgrade the license
 */
function edd_sl_get_time_based_pro_rated_upgrade_cost( $license_id = 0, $old_price, $new_price ) {

	$license = edd_software_licensing()->get_license( $license_id );

	// If the license is lifetime, we cannot use time based pro-ration, so fall back to cost based.
	if ( $license->is_lifetime ) {
		return edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	}

	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$payment_id  = edd_software_licensing()->get_payment_id( $license_id );

	// Convert $license_length value from "+1 years" to # of seconds in that period of time, not a timestamp
	$current_time           = current_time( 'timestamp', true );
	$license_length         = edd_software_licensing()->get_license_length( $license_id, $payment_id, $download_id );
	$midnight_today         = strtotime( 'today midnight' );
	$license_length_seconds = strtotime( $license_length, $midnight_today ) - $midnight_today;
	$seconds_until_expires  = absint( edd_software_licensing()->get_license_expiration( $license_id ) - $current_time + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	$seconds_since_purchase = $license_length_seconds - $seconds_until_expires;
	$minimum_time           = apply_filters( 'edd_sl_get_time_based_pro_rated_minimum_time', DAY_IN_SECONDS );

	// If the license has been purchased within the minimum time fall back on cost-based
	if( $minimum_time >= $seconds_since_purchase ) {
		return edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	}

	// Get the percent of the remaining time as a decimal (.24 => 24% remaining)
	$percent_remaining_decimal = 1 - abs( ( $seconds_until_expires - $license_length_seconds ) / $license_length_seconds );

	// Take the difference in price, and multiply by remaining license time
	$prorated = ( $new_price - $old_price ) * $percent_remaining_decimal;

	$prorated = round( $prorated, edd_currency_decimal_filter() );

	return apply_filters( 'edd_sl_get_time_based_pro_rated_upgrade_cost', $prorated, $license_id, $old_price, $new_price );

}

/**
 * Add license upgrade to the cart
 *
 * @since 3.3
 * @return void
 */
function edd_sl_add_upgrade_to_cart( $data ) {

	// Only allow upgrading when the payment ID for the license is completed
	$payment_id = edd_software_licensing()->get_payment_id( $data['license_id'] );
	$payment    = new EDD_Payment( $payment_id );
	if ( 'publish' !== $payment->status ) {
		return;
	}

	$download_id = edd_software_licensing()->get_download_id( $data['license_id'] );

	$cart_contents = edd_get_cart_contents();
	$allow_upgrade = true;

	if( 'expired' === edd_software_licensing()->get_license_status( $data['license_id'] ) ) {

		$is_expired    = true;
		$allow_upgrade = false;

	}

	if ( $allow_upgrade ) {

		// If this license ID is already in the cart, remove it to add the new choice
		foreach ( $cart_contents as $key => $item ) {

			if ( isset( $item['options']['license_id'] ) && $item['options']['license_id'] == $data['license_id'] ) {

				// Replace any existing upgrades and/or renewals for this license
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
 * Validate license upgrade before permitting purchase to ensure license keys connected to user account can only be upgraded when logged in
 *
 * @since 3.5.4
 * @return void
 */
function edd_sl_validate_upgrade_in_cart( $valid_data, $posted ) {

	$cart_items = edd_get_cart_contents();
	if( ! $cart_items ) {
		return;
	}

	foreach( $cart_items as $item ) {

		if( ! isset( $item['options']['is_upgrade'] ) ) {
			continue;
		}

		$license_id = absint( $item['options']['license_id'] );
		$license    = edd_software_licensing()->get_license( $license_id );


		if( ! $license || ! $license->ID > 0 ) {
			continue;
		}

		if( empty( $license->user_id ) ) {
			continue;
		}

		$user_id = get_current_user_id();

		if( (int) $user_id !== (int) $license->user_id || ! is_user_logged_in() ) {

			edd_set_error( 'edd_sl_invalid_user_id', __( 'Please log into your account to upgrade your license', 'edd_sl' ) );
			break;

		}

	}
}
add_action( 'edd_checkout_error_checks', 'edd_sl_validate_upgrade_in_cart', 10, 2 );

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
	global $edd_sl_cart_item_quantity_removed;

	if( empty( $options['is_upgrade'] ) || ! isset( $options['upgrade_id'] ) ) {
		return $label;
	}

	$edd_sl_cart_item_quantity_removed = true;
	add_filter( 'edd_item_quantities_enabled', '__return_false' );
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

	// Bail if this is not an upgrade item
	if( empty( $cart_item['item_number']['options']['is_upgrade'] ) ) {
		return;
	}

	$license_id      = $cart_item['item_number']['options']['license_id'];
	$license         = edd_software_licensing()->get_license( $license_id );
	$old_cart_index  = $license->cart_index;

	$upgrade_id      = $cart_item['item_number']['options']['upgrade_id'];
	$old_payment_ids = $license->payment_ids;
	$old_payment_id  = end( $old_payment_ids ); // We only want the most recent one
	$old_download_id = $license->download_id;
	$old_price_id    = $license->price_id;

	$old_payment     = new EDD_Payment( $old_payment_id );
	$purchase_date   = $old_payment->date;
	$upgrade         = edd_sl_get_upgrade_path( $old_download_id, $upgrade_id );
	$price_id        = isset( $upgrade['price_id'] ) ? $upgrade['price_id'] : false;

	$old_download = new EDD_SL_Download( $old_download_id );
	$new_download = new EDD_SL_Download( $download_id );

	$old_payment  = new EDD_Payment( $old_payment_id );
	$new_payment  = new EDD_Payment( $payment_id );

	// Setup some checks if we need to modify the expiration date of the upgraded license.
	$expiration_change = false;
	$old_length = $license->license_length();
	$new_length = '+' . $new_download->get_expiration_length() . ' ' . $new_download->get_expiration_unit();

	// Normalize to numerical differences for easier comparision.
	$lengths = array(
		'old' => 'lifetime' !== $old_length ? strtotime( $old_length ) : 'lifetime',
		'new' => 'lifetime' !== $new_length ? strtotime( $new_length ) : 'lifetime',
	);

	if ( $lengths['old'] !== $lengths['new'] ) {
		$expiration_change = true;
	}

	if( $new_download->is_bundled_download() && ! $old_download->is_bundled_download() ) {

		// Upgrade to a bundle from a standard license.
		$downloads         = array();
		$bundle_licensing  = $new_download->licensing_enabled();
		$parent_license_id = 0;
		$activation_limit  = false;

		$new_bundle_downloads = $new_download->get_bundled_downloads();
		$keep_license         = in_array( $license->download_id, $new_bundle_downloads );

		if ( $new_download->has_variable_prices() ) {
			$activation_limit = $new_download->get_price_activation_limit( $cart_item['item_number']['options']['price_id'] );
			$is_lifetime      = $new_download->is_price_lifetime( $cart_item['item_number']['options']['price_id'] );
		}

		$options = array(
			'parent_license_id' => $parent_license_id,
			'activation_limit'  => isset( $activation_limit ) ? $activation_limit : false,
			'is_lifetime'       => isset( $is_lifetime ) ? $is_lifetime : null,
			'expiration_date'   => $license->expiration,
		);

		if ( $bundle_licensing ) {

			if ( $keep_license ) {
				// If this license exists in the new bundle, let's keep it around
				$options['existing_license_ids'] = array( $license->ID );
				$license->activation_limit       = $options['activation_limit'];
				$license->is_lifetime            = $options['is_lifetime'];

				// We need a new blank license, since we've prepared the existing one to be a child.
				$license = new EDD_SL_License();
			} else {
				// Change out the details on the bundle license
				$new_title = $new_download->get_name();

				if( $new_download->has_variable_prices() ) {
					$new_title .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
				}

				$new_title .= ' - ' . $new_payment->email;
				$license->name             = $new_title;
				$license->download_id      = $new_download->ID;
				$license->price_id         = $price_id;
				$license->activation_limit = $options['activation_limit'];
				if ( $options['is_lifetime'] ) {
					$license->is_lifetime = $options[ 'is_lifetime' ];
				}
				add_post_meta( $license->ID, '_edd_sl_payment_id', $new_payment->ID );
			}

			$license->create( $new_download->ID, $payment_id, $price_id, $cart_index, $options );

			if ( $expiration_change || empty( $license->expiration ) ) {
				$license_length = '+' . $new_download->get_expiration_length() . ' ' . $new_download->get_expiration_unit();
				$license->expiration = strtotime( $license_length, strtotime( $purchase_date ) );
			}

		} else {
			$downloads = $new_download->get_bundled_downloads();
			foreach ( $downloads as $d_id ) {

				if( (int) $d_id === (int) $old_download_id ) {
					continue;
				}

				$new_license = new EDD_SL_License();
				$new_license->create( $d_id, $payment_id, $price_id, $cart_index, $options );
			}
		}

	} else if ( $new_download->is_bundled_download() && $old_download->is_bundled_download() ) {

		// Bundle to Bundle upgrade

		// Change out the details on the bundle license
		$new_title = $new_download->get_name();

		if( $new_download->has_variable_prices() ) {
			$new_title .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
		}

		$new_title .= ' - ' . $new_payment->email;
		$license->name = $new_title;

		$license->update_meta( '_edd_sl_cart_index', $cart_index );
		add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );
		$license->download_id = $download_id;

		if( $new_download->has_variable_prices() ) {

			$limit       = $new_download->get_price_activation_limit( $upgrade['price_id'] );
			$is_lifetime = $new_download->is_price_lifetime( $upgrade['price_id'] );

			$license->price_id = $upgrade['price_id'];

		} else {

			$license->reset_activation_limit();
			$limit       = $license->activation_limit;
			$is_lifetime = $new_download->is_lifetime();

		}

		$license->activation_limit = $limit;

		$license_length = edd_software_licensing()->get_license_length( $license_id, $payment_id, $download_id );

		if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {
			// Set license expiration date
			delete_post_meta( $license_id, '_edd_sl_is_lifetime' );

			// Only change the dates if we're changing downloads, since that's only when license lengths will change.
			if ( ( $old_download_id !== $download_id && $expiration_change ) || empty( $license->expiration ) ) {
				$license_length = '+' . $new_download->get_expiration_length() . ' ' . $new_download->get_expiration_unit();
				$license->expiration = strtotime( $license_length, strtotime( $purchase_date ) );
			}
		} else {
			$license->is_lifetime = true;
		}

		$old_bundle_downloads = $old_download->get_bundled_downloads();
		$new_bundle_downloads = $new_download->get_bundled_downloads();

		// Before we start generating new keys, let's get existing overlap ones to change
		foreach ( $new_bundle_downloads as $new_d_id ) {
			if ( ! in_array( $new_d_id, $old_bundle_downloads ) ) {
				continue;
			}

			$overlap_license = edd_software_licensing()->get_license_by_purchase( $old_payment_id, $new_d_id, $old_cart_index, true );
			if ( $overlap_license ) {
				add_post_meta( $overlap_license->ID, '_edd_sl_payment_id', $payment_id );
				$overlap_license->update_meta( '_edd_sl_cart_index', $cart_index );
				$overlap_license->is_lifetime = $is_lifetime;
				// Only change the dates if we're changing downloads, since that's only when license lengths will change.
				if ( ( $old_download_id !== $download_id && $expiration_change ) || empty( $overlap_license->expiration ) ) {
					$license_length = '+' . $new_download->get_expiration_length() . ' ' . $new_download->get_expiration_unit();
					$overlap_license->expiration = strtotime( $license_length, strtotime( $purchase_date ) );
				}
			}
		}

		$options = array(
			'expiration_date' => $license->expiration,
		);

		$license->create( $download_id, $payment_id, $price_id, $cart_index, $options );

		if ( $new_download->licensing_enabled() ) {
			// Now that we've created all necessary new child licenses, remove any keys that don't belong in this
			$child_licenses = $license->get_child_licenses();
			foreach ( $child_licenses as $child_license ) {
				if ( ! in_array( $child_license->download_id, $new_bundle_downloads ) ) {
					wp_delete_post( $child_license->ID );
				}
			}
		}

	} else {

		// Standard license upgrade

		$new_title = $new_download->get_name();

		if( $new_download->has_variable_prices() ) {
			$new_title .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
		}

		$new_title .= ' - ' . $new_payment->email;
		$license->name = $new_title;

		$license->update_meta( '_edd_sl_cart_index', $cart_index );
		add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );
		$license->download_id = $download_id;

		if( $new_download->has_variable_prices() ) {

			$limit = $new_download->get_price_activation_limit( $upgrade['price_id'] );
			$license->activation_limit = $limit;

			$is_lifetime = $new_download->is_price_lifetime( $upgrade['price_id'] );
			$license->price_id = $upgrade['price_id'];

		} else {

			$license->reset_activation_limit();
			$is_lifetime = $new_download->is_lifetime();

		}

		if ( ! $is_lifetime ) {

			// Set license expiration date
			delete_post_meta( $license_id, '_edd_sl_is_lifetime' );

			// Only change the dates if we're changing downloads, since that's only when license lengths will change.
			if ( ( $old_download_id !== $download_id && $expiration_change ) || empty( $license->expiration ) ) {
				$license_length = '+' . $new_download->get_expiration_length() . ' ' . $new_download->get_expiration_unit();
				$license->expiration = strtotime( $license_length, strtotime( $purchase_date ) );
			} else if ( $lengths['old'] == 'lifetime' && $lengths['new'] !== 'lifetime' ) {
				$license_length = '+' . $new_download->get_expiration_length() . ' ' . $new_download->get_expiration_unit();
				$license->expiration = strtotime( $license_length );
			}

		} else {
			$license->is_lifetime = true;
		}

	}

	// Now store upgrade details / notes on payments

	$old_product = $old_download->get_name();
	if( $old_download->has_variable_prices() && false !== $old_price_id ) {
		$old_product .= ' - ' . edd_get_price_option_name( $old_download_id, $old_price_id );
	}

	$new_product = $new_download->get_name();
	if( $new_download->has_variable_prices() ) {
		$new_product .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
	}

	$note = sprintf( __( 'License upgraded from %s to %s', 'edd_sl' ), $old_product, $new_product );

	$new_payment->add_note( $note );

	$new_payment->update_meta( '_edd_sl_upgraded_payment_id', $old_payment_id );
	$old_payment->update_meta( '_edd_sl_upgraded_to_payment_id', $payment_id );

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

/**
 * Add upgrade links to product lists
 *
 * @since 3.5
 * @param  int   $download_id The ID of a given download
 * @param  array $args Arguements passed by the download
 * @return array
 */
function edd_sl_add_upgrade_link( $download_id = 0, $args ) {
	// Bail if user isn't logged in
	if( ! is_user_logged_in() ) {
		return;
	}

	// Bail if option isn't set
	if( ! edd_get_option( 'edd_sl_inline_upgrade_links', false ) ) {
		return;
	}

	$licenses = edd_software_licensing()->get_license_keys_of_user( get_current_user_id(), $download_id );

	// Bail if user isn't licensed for this product
	if( ! $licenses ) {
		return;
	}

	foreach( $licenses as $index => $license ) {
		if( ! edd_sl_license_has_upgrades( $license->ID ) ) {
			unset( $licenses[ $index ] );
		}
	}

	// Reset the array keys to 0 based after using unset on licenses without upgrades.
	$licenses = array_values( $licenses );

	if( count( $licenses ) == 1 ) {
		echo '<span class="edd-sl-upgrade-link"><a href="' . esc_url( edd_sl_get_license_upgrade_list_url( $licenses[0]->ID ) ) . '">' . __( 'Upgrade your existing license', 'edd_sl' ) . '</a></span>';
	} else {
		foreach( $licenses as $license ) {
			$license_key = apply_filters( 'edd_sl_add_upgrade_link_license_key', substr( edd_software_licensing()->get_license_key( $license->ID ), -4 ), edd_software_licensing()->get_license_key( $license->ID ), $license->ID );
			echo '<span class="edd-sl-upgrade-link"><a href="' . esc_url( edd_sl_get_license_upgrade_list_url( $license->ID )  ) . '">' . sprintf( __( 'Upgrade license ending in %s', 'edd_sl' ), $license_key ) . '</a></span>';
		}
	}
}
add_action( 'edd_purchase_link_end', 'edd_sl_add_upgrade_link', 10, 2 );

/**
 * Given a license ID, determine the URL to get to a license upgrade list
 *
 * @since 3.5
 * @param int $license_id The license ID to get an upgrade list url for
 * @return string         A fully qualified URL to the page where a user can upgrade their license, or empty if failure
 */
function edd_sl_get_license_upgrade_list_url( $license_id = 0 ) {

	if ( empty( $license_id ) || ! is_numeric( $license_id ) ) {
		return '';
	}

	$purchase_history_page = edd_get_option( 'purchase_history_page', false );

	if ( empty( $purchase_history_page ) ) {

		global $wpdb;
		$sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = "page" AND post_status="publish" AND post_content LIKE "%edd\_license\_keys%"';
		if ( $id = $wpdb->get_var( $sql ) ) {
			$purchase_history_page = $id;
		}

	}

	$url = '';

	if ( ! empty( $purchase_history_page ) ) {
		$url = get_permalink( $purchase_history_page );
		$url = add_query_arg( array( 'action' => 'manage_licenses', 'payment_id' => edd_software_licensing()->get_payment_id( $license_id ), 'view' => 'upgrades', 'license_id' => $license_id ) , $url );
	}

	return apply_filters( 'edd_sl_get_license_upgrade_list_url', $url, $license_id, $purchase_history_page );

}
