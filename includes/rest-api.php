<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function edd_sl_products_product_api( $product ) {
	$download_id = $product['info']['id'];
	$download    = new EDD_Download( $download_id );

	$enabled    = get_post_meta( $download_id, '_edd_sl_enabled', true ) ? true : false;
	$version    = get_post_meta( $download_id, '_edd_sl_version', true );
	$exp_unit   = get_post_meta( $download_id, '_edd_sl_exp_unit', true );
	$exp_length = get_post_meta( $download_id, '_edd_sl_exp_length', true );

	$licensing_data = array(
		'enabled'    => $enabled,
		'version'    => $version,
		'exp_unit'   => $exp_unit,
		'exp_length' => $exp_length,
	);

	$licensing_data       = apply_filters( 'edd_sl_products_product_api', $licensing_data, $download_id );
	$product['licensing'] = $licensing_data;

	return $product;
}
add_filter( 'edd_api_products_product', 'edd_sl_products_product_api', 10, 1 );

/**
 * Add license data to EDD API sales endpoint
 *
 * @since  3.5
 * @param  array $sales   The current sales data
 * @return array $sales   The modified sales data
 */
function edd_sl_sales_api( $sales ) {
	foreach( $sales['sales'] as $id => $sale ) {
		$licenses = edd_software_licensing()->get_licenses_of_purchase( $sale['ID'] );

		if( ! empty( $licenses ) ) {
			$i = 0;

			foreach( $licenses as $license ) {
				$key      = edd_software_licensing()->get_license_key( $license->ID );
				$download = edd_software_licensing()->get_download_id( $license->ID );
				$price_id = edd_software_licensing()->get_price_id( $license->ID );
				$title    = get_the_title( $download );
				$status   = ( edd_software_licensing()->get_license_status( $license->ID ) == 'draft' ? 'revoked' : edd_software_licensing()->get_license_status( $license->ID ) );

				if( edd_has_variable_prices( $download ) ) {
					$title .= ' - ' . edd_get_price_option_name( $download, $price_id );
				}

				$sales['sales'][ $id ]['licenses'][ $i ]['id']     = $license->ID;
				$sales['sales'][ $id ]['licenses'][ $i ]['name']   = $title;
				$sales['sales'][ $id ]['licenses'][ $i ]['status'] = $status;
				$sales['sales'][ $id ]['licenses'][ $i ]['key']    = ( $license ? $key : 'none' );

				$i++;
			}

		}
	}

	return $sales;
}
add_filter( 'edd_api_sales', 'edd_sl_sales_api', 10, 1 );
