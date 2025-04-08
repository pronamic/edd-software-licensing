<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Retrieve upgrade paths for a Download
 *
 * @since 3.3
 * @return array
 */
function edd_sl_get_upgrade_paths( $download_id = 0 ) {

	if ( empty( $download_id ) ) {
		return array();
	}

	$paths = get_post_meta( $download_id, '_edd_sl_upgrade_paths', true );
	if ( empty( $paths ) && ! is_array( $paths ) ) {
		$paths = array();
	}

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

	if ( isset( $upgrades[ $upgrade_id ] ) ) {
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

	$upgrade_paths = array();
	$license       = edd_software_licensing()->get_license( $license_id );

	if ( false !== $license ) {

		$status_is_upgradable = false;

		// In EDD 3.0, we check for an order item which is deliverable.
		if ( function_exists( 'edd_count_order_items' ) ) {
			$status_is_upgradable = (bool) edd_count_order_items(
				array(
					'order_id__in' => $license->payment_ids,
					'status'       => edd_get_deliverable_order_item_statuses(),
					'product_id'   => $license->download_id,
				)
			);
		} else {
			// In EDD 2.x, we just need the payment status.
			$payment = edd_get_payment( $license->payment_id );
			if ( ! empty( $payment->status ) && in_array( $payment->status, array( 'complete', 'publish' ), true ) ) {
				$status_is_upgradable = true;
			}
		}
		if ( $status_is_upgradable ) {
			$upgrade_paths = edd_sl_get_upgrade_paths( $license->download_id );
		}

		if ( ! empty( $upgrade_paths ) && is_array( $upgrade_paths ) ) {

			foreach ( $upgrade_paths as $key => $path ) {

				if ( $license->get_download()->has_variable_prices() ) {

					// If there is a different product in the upgrade paths, upgrade is available
					if ( (int) $path['download_id'] === (int) $license->download_id ) {

						// If same download but with a more expensive price ID is in upgrade paths, upgrade is available
						if ( (int) $path['price_id'] !== (int) $license->price_id ) {

							if ( edd_get_price_option_amount( $path['download_id'], $path['price_id'] ) <= edd_get_price_option_amount( $license->download_id, $license->price_id ) ) {
								unset( $upgrade_paths[ $key ] );
							}
						} elseif ( edd_get_price_option_amount( $path['download_id'], $path['price_id'] ) <= edd_get_price_option_amount( $license->download_id, $license->price_id ) ) {

								unset( $upgrade_paths[ $key ] );
						}
					}
				} else {

					// If there is a different product in the upgrade paths, upgrade is available
					if ( (int) $path['download_id'] === (int) $license->download_id ) {
						unset( $upgrade_paths[ $key ] );
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

	if ( empty( $license_id ) ) {
		return false;
	}

	$license = edd_software_licensing()->get_license( $license_id );
	if ( false === $license || in_array( $license->status, array( 'disabled', 'revoked' ), true ) ) {
		return false;
	}

	$ret = false;

	if ( empty( $license->parent ) ) {
		$download_id = edd_software_licensing()->get_download_id( $license_id );
		$price_id    = edd_software_licensing()->get_price_id( $license_id );

		$upgrade_paths = edd_sl_get_upgrade_paths( $download_id );
		$payment_id    = edd_software_licensing()->get_payment_id( $license_id );
		$payment       = new EDD_Payment( $payment_id );

		if ( is_array( $upgrade_paths ) && ( 'publish' === $payment->status || 'complete' === $payment->status ) ) {

			foreach ( $upgrade_paths as $path ) {

				if ( edd_has_variable_prices( $download_id ) ) {

					// If there is a different product in the upgrade paths, upgrade is available.
					if ( (int) $path['download_id'] !== (int) $download_id ) {

						$ret = true;

					} else {

						// If same download but with a more expensive price ID is in upgrade paths, upgrade is available.
						if ( (int) $path['price_id'] !== (int) $price_id ) {

							if ( edd_get_price_option_amount( $path['download_id'], $path['price_id'] ) > edd_get_price_option_amount( $download_id, $price_id ) ) {

								$ret = true;

							}
						}
					}
				} else {

					// If there is a different product in the upgrade paths, upgrade is available
					if ( (int) $path['download_id'] !== (int) $download_id ) {
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

	if ( is_array( $upgrades ) && isset( $upgrades[ $upgrade_id ] ) ) {

		$url = add_query_arg(
			array(
				'edd_action' => 'sl_license_upgrade',
				'license_id' => $license_id,
				'upgrade_id' => $upgrade_id,
			),
			edd_get_checkout_uri()
		);

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

	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$download    = new EDD_SL_Download( $download_id );
	$upgrades    = edd_sl_get_upgrade_paths( $download_id );

	/**
	 * Allow using the previously paid amount as the $old_price
	 *
	 * Some store owners would prefer that the old price be based off what was previously paid, instead of what
	 * the current price ID value is. Returning false here, allows the $old_price to be based on the last amount paid
	 * instead of the current price of the Price ID, in the event it has been changed.
	 *
	 * @since 3.6.4
	 *
	 * @param bool             Should we use the current price of the Price ID for prorated estimates.
	 * @param int  $license_id The License ID requesting the prorated cost.
	 * @param int  $download_id The Download ID associated with the license.
	 */
	$use_current_price = apply_filters( 'edd_sl_use_current_price_proration', true, $license_id, $download_id );

	if ( $download->has_variable_prices() ) {

		$price_id = edd_software_licensing()->get_price_id( $license_id );

		if ( false !== $price_id && '' !== $price_id ) {

			$prices = $download->get_prices();

			if ( array_key_exists( $price_id, $prices ) && $use_current_price ) {

				// The old price ID still exists, use the current price of it as the old price.
				$old_price = edd_get_price_option_amount( $download_id, $price_id );

			} else {
				$old_price = 0.00;

				// The old price ID was removed, so just figure out what they paid last.
				$license = edd_software_licensing()->get_license( $license_id );
				if ( false !== $license ) {
					$last_payment_id = max( $license->payment_ids );
					$payment         = edd_get_payment( $last_payment_id );

					foreach ( $payment->cart_details as $item ) {
						if ( (int) $item['id'] !== $download->ID ) {
							continue;
						}

						$old_price = $item['item_price'];
						break;
					}
				}
			}
		} else {

			$old_price = edd_get_lowest_price_option( $download_id );

		}
	} else {

		$old_price = edd_get_download_price( $download_id );

		if ( ! $use_current_price ) {

			$license = edd_software_licensing()->get_license( $license_id );

			if ( false !== $license ) {
				$last_payment_id = max( $license->payment_ids );
				$payment         = edd_get_payment( $last_payment_id );

				foreach ( $payment->cart_details as $item ) {
					if ( (int) $item['id'] !== $download->ID ) {
						continue;
					}

					$old_price = $item['item_price'];
					break;
				}
			}
		}
	}

	$price_id = isset( $upgrades[ $upgrade_id ]['price_id'] ) && false !== $upgrades[ $upgrade_id ]['price_id'] ? $upgrades[ $upgrade_id ]['price_id'] : false;
	if ( is_numeric( $price_id ) ) {

		$new_price = edd_get_price_option_amount( $upgrades[ $upgrade_id ]['download_id'], $price_id );

	} else {

		$new_price = edd_get_download_price( $upgrades[ $upgrade_id ]['download_id'] );

	}

	$cost = $new_price;

	if ( ! empty( $upgrades[ $upgrade_id ]['pro_rated'] ) ) {

		$cost = edd_sl_get_pro_rated_upgrade_cost( $license_id, $old_price, $new_price, $upgrades[ $upgrade_id ]['download_id'], $price_id );

	}

	if ( isset( $upgrades[ $upgrade_id ]['discount'] ) ) {

		$cost -= $upgrades[ $upgrade_id ]['discount'];

	}

	if ( $cost < 0 ) {
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
 * @param int $upgrade_id ID of the new download
 * @param false|int       Price ID of the new download (for variable products)
 * @return float The prorated cost to upgrade the license
 */
function edd_sl_get_pro_rated_upgrade_cost( $license_id, $old_price, $new_price, $upgrade_id = 0, $price_id = false ) {
	$proration_method = edd_get_option( 'edd_sl_proration_method', 'cost-based' );
	$proration_method = apply_filters( 'edd_sl_proration_method', $proration_method, $license_id, $old_price, $new_price );

	// Check global setting and handle accordingly, if the filter is used
	// to fall back to simple pro-ration, return the simple new - old price
	if ( $proration_method == 'cost-based' || apply_filters( 'edd_sl_license_upgrade_pro_rate_simple', false ) ) {
		$prorated = edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	} else {
		$prorated = edd_sl_get_time_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price, $upgrade_id, $price_id );
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
function edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price ) {
	$prorated = $new_price - $old_price;

	return apply_filters( 'edd_sl_get_cost_based_pro_rated_upgrade_cost', $prorated, $license_id, $old_price, $new_price );
}

/**
 * Calculate the prorated cost based on cost
 *
 * @since 3.5
 * @param int       $license_id ID of license being upgraded
 * @param float|int $old_price  Price of the license being upgraded
 * @param float|int $new_price  Price of the new license level
 * @param int       $upgrade_id ID of the new download
 * @param false|int $price_id   Price ID of the new download (for variable products)
 *
 * @return float The prorated cost to upgrade the license
 */
function edd_sl_get_time_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price, $upgrade_id = 0, $price_id = false ) {

	$license = edd_software_licensing()->get_license( $license_id );

	// If the license is lifetime, we cannot use time based pro-ration, so fall back to cost based.
	if ( $license->is_lifetime ) {
		return edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	}

	$download_id = edd_software_licensing()->get_download_id( $license_id );
	$payment_id  = edd_software_licensing()->get_payment_id( $license_id );

	// Convert $license_length value from "+1 years" to # of seconds in that period of time, not a timestamp
	$current_time           = time();
	$license_length         = edd_software_licensing()->get_license_length( $license_id, $payment_id, $download_id );
	$midnight_today         = strtotime( 'today midnight' );
	$license_length_seconds = strtotime( $license_length, $midnight_today ) - $midnight_today;
	$seconds_until_expires  = absint( edd_software_licensing()->get_license_expiration( $license_id ) - $current_time + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	$seconds_used           = $license_length_seconds - $seconds_until_expires;
	$minimum_time           = apply_filters( 'edd_sl_get_time_based_pro_rated_minimum_time', DAY_IN_SECONDS );

	// If the license has been purchased within the minimum time fall back on cost-based
	if ( $minimum_time >= $seconds_used ) {
		return edd_sl_get_cost_based_pro_rated_upgrade_cost( $license_id, $old_price, $new_price );
	}

	// Percentage used of current license.
	$percent_used_decimal = $seconds_used / $license_length_seconds;

	// "Unused" price of current license.
	$credit = $old_price * abs( 1 - $percent_used_decimal );

	// Set the new license length to false before seeing if a new license length can be calculated.
	$new_length = false;

	// If the new download ID is provided, use that for calculating the prorated cost.
	if ( $upgrade_id ) {
		$new_length = edd_sl_get_product_license_length( $upgrade_id, $price_id );
		if ( $new_length && 'lifetime' !== $new_length ) {
			$new_license_length_seconds = strtotime( $new_length, $midnight_today ) - $midnight_today;

			// Recalculate the percentage used based on the new license length.
			$percent_used_decimal = $seconds_used / $new_license_length_seconds;
		}
	}

	// Lifetime upgrades are calculated differently because the amount of time left is unlimited.
	if ( 'lifetime' === $new_length ) {
		$prorated = $new_price - $credit;
	} else {
		// What percentage of the new license time is left (in decimal form).
		$percent_remaining_decimal = abs( 1 - $percent_used_decimal );
		$prorated                  = ( $new_price * $percent_remaining_decimal ) - $credit;
	}

	$prorated = round( $prorated, edd_currency_decimal_filter() );

	/**
	 * Filters the time based prorated upgrade cost.
	 *
	 * @param float     $prorated   The final cost of the upgrade (rounded).
	 * @param int       $license_id The license ID to be upgraded.
	 * @param float     $old_price  The original price paid for the license.
	 * @param float     $new_price  The price of the new license.
	 * @param false|int $upgrade_id The ID for the new download.
	 * @param false|int $price_id   The price ID for the new download.
	 */
	return apply_filters( 'edd_sl_get_time_based_pro_rated_upgrade_cost', $prorated, $license_id, $old_price, $new_price, $upgrade_id, $price_id );
}

/**
 * Add license upgrade to the cart
 *
 * @since 3.3
 * @return void
 */
function edd_sl_add_upgrade_to_cart( $data ) {

	$license_id = false;
	if ( ! empty( $data['license_id'] ) ) {
		$license_id = $data['license_id'];
	} elseif ( ! empty( $data['license_key'] ) ) {
		$license_id = edd_software_licensing()->get_license_by_key( $data['license_key'] );
	}

	if ( ! $license_id ) {
		return;
	}

	$license = edd_software_licensing()->get_license( $license_id );
	if ( in_array( $license->status, array( 'revoked', 'disabled' ), true ) ) {
		return;
	}

	// Only allow upgrading when the payment ID for the license is completed
	$payment_id     = edd_software_licensing()->get_payment_id( $license_id );
	$payment_status = edd_get_payment_status( $payment_id );
	if ( ! in_array( $payment_status, array( 'publish', 'complete', 'partially_refunded' ), true ) ) {
		return;
	}

	$allow_upgrade = true;
	if ( 'expired' === $license->status ) {
		$allow_upgrade = 'expired';
	}

	$upgrade = edd_sl_get_upgrade_path( $license->download_id, $data['upgrade_id'] );

	// Verify that this upgrade is not the same price ID as the current license.
	if ( (int) $upgrade['download_id'] === (int) $license->download_id && (int) $upgrade['price_id'] === (int) $license->price_id ) {
		$allow_upgrade = 'mismatch';
	}

	if ( true === $allow_upgrade ) {

		// If this license ID is already in the cart, remove it to add the new choice
		foreach ( edd_get_cart_contents() as $key => $item ) {

			if ( isset( $item['options']['license_id'] ) && $item['options']['license_id'] == $license_id ) {

				// Replace any existing upgrades and/or renewals for this license
				edd_remove_from_cart( $key );
				break;
			}

			if ( ! empty( $item['item_number']['options']['is_renewal'] ) ) {
				$allow_upgrade = 'renewal';
				break;
			}
		}

		$options = array(
			'price_id'   => $upgrade['price_id'],
			'is_upgrade' => true,
			'upgrade_id' => $data['upgrade_id'],
			'license_id' => $license_id,
			'cost'       => edd_sl_get_license_upgrade_cost( $license_id, $data['upgrade_id'] ),
		);

		edd_add_to_cart( $upgrade['download_id'], $options );

	} else {

		switch ( $allow_upgrade ) {
			case 'expired':
				edd_set_error( 'edd-sl-expired-license', __( 'Your license key is expired. It must be renewed before it can be upgraded.', 'edd_sl' ) );
				break;

			case 'invalid_price_id':
				edd_set_error( 'edd-sl-invalid-price-id', __( 'Invalid price ID specified for upgrade.', 'edd_sl' ) );
				break;

			case 'renewal':
				edd_set_error( 'edd-sl-unique-action', __( 'License renewals and upgrades must be purchased separately. Please complete your license renewal before upgrading it.', 'edd_sl' ) );
				break;

			default:
				edd_set_error( 'edd-sl-upgrade-error', __( 'Your upgrade could not be added to the cart.', 'edd_sl' ) );
				break;
		}
	}

	wp_safe_redirect( edd_get_checkout_uri() );
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
	if ( ! $cart_items ) {
		return;
	}

	foreach ( $cart_items as $item ) {

		if ( ! isset( $item['options']['is_upgrade'] ) ) {
			continue;
		}

		$license_id = absint( $item['options']['license_id'] );
		$license    = edd_software_licensing()->get_license( $license_id );

		if ( false === $license ) {
			continue;
		}

		if ( empty( $license->user_id ) ) {
			continue;
		}

		$user_id = get_current_user_id();

		if ( (int) $user_id !== (int) $license->user_id || ! is_user_logged_in() ) {

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

	if ( empty( $options['is_upgrade'] ) || ! isset( $options['upgrade_id'] ) ) {
		return $price;
	}

	return $options['cost'];
}
add_filter( 'edd_cart_item_price', 'edd_sl_license_upgrade_cart_item_price', 10, 3 );

/**
 * Adds the license key being upgraded to the cart item title.
 *
 * @param array $item
 * @since 3.7
 */
function edd_sl_upgrade_details_cart_item( $item ) {
	if ( empty( $item['options']['is_upgrade'] ) || empty( $item['options']['license_id'] ) ) {
		return;
	}

	$license_key = edd_software_licensing()->get_license( $item['options']['license_id'] );
	if ( ! $license_key ) {
		return;
	}

	// If the user is not logged in, or the license is not connected to the logged in user, mask the key.
	$license_key_output = ! is_user_logged_in() || (int) get_current_user_id() !== (int) $license_key->user_id ? edd_sl_mask_license_key( $license_key->key ) : $license_key->key;
	?>
		<div class="edd-sl-upgrade-details edd-sl-upgrade-details-cart">
				<span class="edd-sl-upgrade-label"><?php esc_html_e( 'Upgrading', 'edd_sl' ); ?>:</span>
				<span class="edd-sl-upgrade-key"><?php echo esc_html( $license_key_output ); ?></span>
		</div>
	<?php
}
add_action( 'edd_checkout_cart_item_title_after', 'edd_sl_upgrade_details_cart_item' );

/**
 * @since 3.3
 * @param $price float The current item price
 * @param $download_id int Download product ID
 * @param $options array the cart item options
 * @return float
 */
function edd_sl_license_upgrade_cart_item_price_label( $label, $download_id, $options ) {
	global $edd_sl_cart_item_quantity_removed;

	if ( empty( $options['is_upgrade'] ) || ! isset( $options['upgrade_id'] ) ) {
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
	if ( $cart_items ) {
		foreach ( $cart_items as $item ) {
			if ( isset( $item['options']['is_upgrade'] ) ) {
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
	if ( empty( $cart_item['item_number']['options']['is_upgrade'] ) ) {
		return;
	}

	$license_id     = $cart_item['item_number']['options']['license_id'];
	$license        = edd_software_licensing()->get_license( $license_id );
	$old_cart_index = $license->cart_index;

	$upgrade_id      = $cart_item['item_number']['options']['upgrade_id'];
	$old_payment_ids = $license->payment_ids;
	$old_payment_id  = end( $old_payment_ids ); // We only want the most recent one
	$old_download_id = $license->download_id;
	$old_price_id    = $license->price_id;

	edd_debug_log( sprintf( 'Upgrading license ID %d for payment ID %d', $license_id, $payment_id ) );

	edd_debug_log( sprintf( 'Old Payment ID: %s', $old_payment_id ) );
	edd_debug_log( sprintf( 'Old Download ID: %s', $old_download_id ) );
	edd_debug_log( sprintf( 'Old Price ID: %s', $old_price_id ) );

	$old_payment   = new EDD_Payment( $old_payment_id );
	$purchase_date = $old_payment->date;
	$upgrade       = edd_sl_get_upgrade_path( $old_download_id, $upgrade_id );

	edd_debug_log( sprintf( 'Upgrade path: %s', print_r( $upgrade, true ) ) );

	$price_id = isset( $upgrade['price_id'] ) ? $upgrade['price_id'] : false;

	// Set up downloads.
	$old_download = new EDD_SL_Download( $old_download_id );
	$new_download = new EDD_SL_Download( $download_id );

	// Set up payments.
	$old_payment = new EDD_Payment( $old_payment_id );
	$new_payment = new EDD_Payment( $payment_id );

	// Setup some checks if we need to modify the expiration date of the upgraded license.
	$expiration_change = false;
	$old_length        = $license->license_length();
	$new_length        = edd_sl_get_product_license_length( $download_id, $price_id );

	edd_debug_log( sprintf( 'Old Length: %s - New Length: %s', $old_length, $new_length ) );

	// Normalize to numerical differences for easier comparision.
	$lengths = array(
		'old' => 'lifetime' !== $old_length ? strtotime( $old_length ) : 'lifetime',
		'new' => 'lifetime' !== $new_length ? strtotime( $new_length ) : 'lifetime',
	);

	if ( $lengths['old'] !== $lengths['new'] ) {
		$expiration_change = true;
	}

	edd_debug_log( sprintf( 'Lengths Found: %s', print_r( $lengths, true ) ) );
	edd_debug_log( sprintf( 'Expiration Change: %s', print_r( $lengths, true ) ) );

	edd_debug_log( sprintf( 'Old Download is Bundle: %s', print_r( $old_download->is_bundled_download(), true ) ) );
	edd_debug_log( sprintf( 'New Download is Bundle: %s', print_r( $new_download->is_bundled_download(), true ) ) );

	if ( $new_download->is_bundled_download() && ! $old_download->is_bundled_download() ) {

		// Upgrade to a bundle from a standard license.
		edd_debug_log( 'Starting Standard to Bundle License Upgrade' );

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
				$license->download_id      = $new_download->ID;
				$license->price_id         = $price_id;
				$license->activation_limit = $options['activation_limit'];
				if ( $options['is_lifetime'] ) {
					$license->is_lifetime = $options['is_lifetime'];
				}

				$license->add_meta( '_edd_sl_payment_id', $payment_id );
			}

			$license->create( $new_download->ID, $payment_id, $price_id, $cart_index, $options );

			if ( $expiration_change ) {
				$license->expiration = strtotime( $new_length, strtotime( $purchase_date ) );
			}

			// Add the meta to all child licenses as well.
			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$child_license->add_meta( '_edd_sl_payment_id', $payment_id );
				}
			}
		} else {
			$downloads = $new_download->get_bundled_downloads();
			foreach ( $downloads as $d_id ) {

				if ( (int) $d_id === (int) $old_download_id ) {
					continue;
				}

				$new_license = new EDD_SL_License();
				$new_license->create( $d_id, $payment_id, $price_id, $cart_index, $options );
			}
		}
	} elseif ( $new_download->is_bundled_download() && $old_download->is_bundled_download() ) {

		// Bundle to Bundle upgrade
		edd_debug_log( 'Starting Bundle to Bundle License Upgrade' );

		// Change out the details on the bundle license
		$license->cart_index = $cart_index;
		edd_debug_log( sprintf( 'Set license cart index: %d', $cart_index ) );

		$license->download_id = $download_id;
		edd_debug_log( sprintf( 'Set license download id: %d', $download_id ) );

		$license->add_meta( '_edd_sl_payment_id', $payment_id );

		if ( $new_download->has_variable_prices() ) {

			edd_debug_log( 'New download has variable prices' );
			$limit       = $new_download->get_price_activation_limit( $upgrade['price_id'] );
			$is_lifetime = $new_download->is_price_lifetime( $upgrade['price_id'] );

			$license->price_id = $upgrade['price_id'];
			$license->reset_activation_limit();

		} else {

			edd_debug_log( 'New download is single price' );
			$license->reset_activation_limit();
			$limit       = $license->activation_limit;
			$is_lifetime = $new_download->is_lifetime();

		}

		$license->activation_limit = $limit;

		$license_length = edd_software_licensing()->get_license_length( $license_id, $payment_id, $download_id );

		if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {
			// Set license expiration date
			if ( $expiration_change ) {
				$license->expiration = strtotime( $new_length, strtotime( $purchase_date ) );
			}
		} else {
			$license->is_lifetime = true;
		}

		$old_bundle_downloads = $old_download->get_bundled_downloads();
		edd_debug_log( sprintf( 'Old downloads: %s', print_r( $old_bundle_downloads, true ) ) );

		$new_bundle_downloads = $new_download->get_bundled_downloads();
		edd_debug_log( sprintf( 'New downloads: %s', print_r( $new_bundle_downloads, true ) ) );

		// Before we start generating new keys, let's get existing overlap ones to change
		foreach ( $new_bundle_downloads as $new_d_id ) {

			edd_debug_log( 'Checking for licenses that overlap' );
			if ( ! in_array( $new_d_id, $old_bundle_downloads ) ) {

				continue;
			}

			$overlap_license = edd_software_licensing()->get_license_by_purchase( $old_payment_id, $new_d_id, $old_cart_index, true );
			if ( $overlap_license ) {

				$overlap_license->add_meta( '_edd_sl_payment_id', $payment_id );

				$overlap_license->update_meta( '_edd_sl_cart_index', $cart_index );
				$overlap_license->is_lifetime = $is_lifetime;

				if ( $expiration_change ) {
					$overlap_license->expiration = strtotime( $new_length, strtotime( $purchase_date ) );
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
					$child_license->delete();
				}
			}
		}

		// Add the meta to all child licenses as well.
		$child_licenses = $license->get_child_licenses();
		if ( ! empty( $child_licenses ) ) {
			foreach ( $child_licenses as $child_license ) {
				$child_license->add_meta( '_edd_sl_payment_id', $payment_id );
				$price_id_pos = strpos( $new_d_id, '_' );
				if ( false !== $price_id_pos ) {
					$child_license->price_id = substr( $new_d_id, $price_id_pos + 1, strlen( $new_d_id ) );
					$child_license->add_log(
						__( 'License Upgraded', 'edd_sl' ),
						/* translators: the new price ID; payment ID*/
						sprintf( __( 'Price ID updated to %1$d via Payment ID %2$s', 'edd_sl' ), $child_license->price_id, $payment_id )
					);
				}
			}
		}
	} else {

		// Standard license upgrade

		edd_debug_log( 'Starting Standard License Upgrade' );

		$license->cart_index = $cart_index;
		edd_debug_log( sprintf( 'Set license cart index: %d', $cart_index ) );

		$license->download_id = $download_id;
		edd_debug_log( sprintf( 'Set license download id: %d', $download_id ) );

		edd_debug_log( sprintf( 'Adding payment ID to license: %d', $payment_id ) );
		$license->add_meta( '_edd_sl_payment_id', $payment_id );

		if ( $new_download->has_variable_prices() ) {

			edd_debug_log( 'New download has variable prices' );
			$is_lifetime       = $new_download->is_price_lifetime( $upgrade['price_id'] );
			$license->price_id = $upgrade['price_id'];

			edd_debug_log( sprintf( 'Set new price ID: %d', $license->price_id ) );

			edd_debug_log( 'Reset license activation count' );
			$license->reset_activation_limit();

		} else {

			edd_debug_log( 'New download is single price' );
			edd_debug_log( 'Reset license activation count' );
			$license->reset_activation_limit();
			$is_lifetime = $new_download->is_lifetime();
		}

		edd_debug_log( sprintf( 'New license is lifetime: %s', print_r( $is_lifetime, true ) ) );

		if ( ! $is_lifetime ) {

			if ( $expiration_change ) {
				if ( 'lifetime' === $lengths['old'] || empty( $license->expiration ) ) {
					edd_debug_log( sprintf( 'Moving from lifetime, setting expiration to new length: %s', print_r( $new_length, true ) ) );
					$license->expiration = strtotime( $new_length, current_time( 'timestamp' ) );
				} else {
					edd_debug_log( sprintf( 'Expiration needs to change, setting expiration with new length: %s', print_r( $new_length, true ) ) );
					$license->expiration = strtotime( $new_length, strtotime( $purchase_date ) );
				}
			}
		} else {
			edd_debug_log( 'Marking new license as lifetime' );
			$license->is_lifetime = true;
		}
	}

	// Now store upgrade details / notes on payments

	$old_product = $old_download->get_name();
	if ( $old_download->has_variable_prices() && false !== $old_price_id ) {
		$old_product .= ' - ' . edd_get_price_option_name( $old_download_id, $old_price_id );
	}

	$new_product = $new_download->get_name();
	if ( $new_download->has_variable_prices() ) {
		$new_product .= ' - ' . edd_get_price_option_name( $download_id, $upgrade['price_id'] );
	}

	$note = sprintf( __( 'License upgraded from %1$s to %2$s', 'edd_sl' ), $old_product, $new_product );

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
		'upgrade_price_id' => false,
	);

	if ( isset( $upgrade['price_id'] ) ) {
		$args['upgrade_price_id'] = $upgrade['price_id'];
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

	if ( $upgraded_from ) :

		$view_url = esc_url( admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' ) . $upgraded_from );
		?>
	<div class="edd-admin-box-inside">
		<p>
			<?php printf( '<strong>%s:</strong> <a href="%s">#%s</a>', __( 'Upgraded from', 'edd_sl' ), $view_url, edd_get_payment_number( $upgraded_from ) ); ?>&nbsp;
		</p>
	</div>
		<?php
	endif;

	if ( $upgraded_to ) :

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
function edd_sl_get_upgrades_by_date( $day = null, $month = null, $year = null, $hour = null ) {

	$args = apply_filters(
		'edd_get_upgrades_by_date',
		array(
			'number'   => -1,
			'status'   => array( 'revoked', 'publish', 'complete' ),
			'meta_key' => '_edd_sl_upgraded_payment_id',
			'year'     => $year,
			'month'    => $month,
			'fields'   => 'ids',
		),
		$day,
		$month,
		$year
	);

	if ( ! empty( $day ) ) {
		$args['day'] = $day;
	}

	if ( ! empty( $hour ) ) {
		$args['hour'] = $hour;
	}

	$query    = new EDD_Payments_Query( $args );
	$upgrades = $query->get_payments();

	$return             = array();
	$return['earnings'] = 0;
	$return['count']    = count( $upgrades );
	if ( $upgrades ) {
		foreach ( $upgrades as $upgrade ) {
			$return['earnings'] += edd_get_payment_amount( $upgrade->ID );
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
function edd_sl_add_upgrade_link( $download_id, $args ) {
	// Bail if user isn't logged in
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Bail if option isn't set
	if ( ! edd_get_option( 'edd_sl_inline_upgrade_links', false ) ) {
		return;
	}

	$licenses = edd_software_licensing()->get_license_keys_of_user( get_current_user_id(), $download_id );

	// Bail if user isn't licensed for this product
	if ( ! $licenses ) {
		return;
	}

	foreach ( $licenses as $index => $license ) {
		if ( ! edd_sl_license_has_upgrades( $license->ID ) ) {
			unset( $licenses[ $index ] );
		}
	}

	// Reset the array keys to 0 based after using unset on licenses without upgrades.
	$licenses = array_values( $licenses );

	if ( count( $licenses ) == 1 ) {
		echo '<span class="edd-sl-upgrade-link"><a href="' . esc_url( edd_sl_get_license_upgrade_list_url( $licenses[0]->ID ) ) . '">' . __( 'Upgrade your existing license', 'edd_sl' ) . '</a></span>';
	} else {
		foreach ( $licenses as $license ) {
			$license_key = apply_filters( 'edd_sl_add_upgrade_link_license_key', substr( edd_software_licensing()->get_license_key( $license->ID ), -4 ), edd_software_licensing()->get_license_key( $license->ID ), $license->ID );
			echo '<span class="edd-sl-upgrade-link"><a href="' . esc_url( edd_sl_get_license_upgrade_list_url( $license->ID ) ) . '">' . sprintf( __( 'Upgrade license ending in %s', 'edd_sl' ), $license_key ) . '</a></span>';
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
		$url = add_query_arg(
			array(
				'action'     => 'manage_licenses',
				'payment_id' => edd_software_licensing()->get_payment_id( $license_id ),
				'view'       => 'upgrades',
				'license_id' => $license_id,
			),
			$url
		);
	}

	return apply_filters( 'edd_sl_get_license_upgrade_list_url', $url, $license_id, $purchase_history_page );
}
