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
