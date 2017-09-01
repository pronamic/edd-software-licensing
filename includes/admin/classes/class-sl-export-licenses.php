<?php
/**
 * License key Export Class
 *
 * This class handles exporting license keys
 *
 * @package     Easy Digital Downloads - Software Licensing
 * @subpackage  Export Class
 * @copyright   Copyright (c) 2014, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_SL_License_Export extends EDD_Export {

	/**
	 * Our export type. Used for export-type specific filters / actions
	 *
	 * @access      public
	 * @var         string
	 * @since       3.0
	 */
	public $export_type = 'licenses';

	/**
	 * The status we are exporting
	 *
	 * @access      public
	 * @var         string
	 * @since       3.0
	 */
	public $status = 'active';

	/**
	 * The Download we are exporting license keys for
	 *
	 * @access      public
	 * @var         string
	 * @since       3.0
	 */
	public $download_id = 0;

	/**
	 * Can we export?
	 *
	 * @access public
	 * @since 3.0
	 * @return bool Whether we can export or not
	 */
	public function can_export() {
		return (bool) apply_filters( 'edd_license_export_capability', current_user_can( 'export_shop_reports' ) );
	}

	/**
	 * Set the export headers
	 *
	 * @access public
	 * @since 3.0
	 * @return void
	 */
	public function headers() {
		ignore_user_abort( true );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=edd-export-' . $this->export_type . '-' . date( 'm-d-Y' ) . '-' . sanitize_title( get_the_title( $this->download_id ) ) . '.csv' );
		header( "Expires: 0" );
	}

	/**
	 * Set the CSV columns
	 *
	 * @access      public
	 * @since       3.0
	 * @return      array
	 */
	public function csv_cols() {
		$cols = array(
			'email'      => __( 'Email', 'edd_sl' ),
			'license'    => __( 'License Key', 'edd_sl' ),
			'status'     => __( 'License Status', 'edd_sl' ),
			'product'    => __( 'Product Name', 'edd_sl' ),
			'date'       => __( 'Purchase Date', 'edd_sl' ),
			'expiration' => __( 'Expiration Date', 'edd_sl' ),
			'limit'      => __( 'Activation Limit', 'edd_sl' ),
			'count'      => __( 'Activation Count', 'edd_sl' ),
			'urls'       => __( 'Activated URLs', 'edd_sl' ),
		);
		return $cols;
	}

	/**
	 * Get the data being exported
	 *
	 * @access      public
	 * @since       3.0
	 * @return      array
	 */
	public function get_data() {
		global $edd_logs;

		$data = array();

		$args = array(
			'nopaging'   => true,
			'post_type'  => 'edd_license',
			'meta_query' => array()
		);

		if( 'all' != $this->status ) {
			$args['meta_query'][] = array(
				'key'   => '_edd_sl_status',
				'value' => $this->status
			);
		}

		if( ! empty( $this->download_id ) ) {
			$args['meta_query'][] = array(
				'key'   => '_edd_sl_download_id',
				'value' => $this->download_id
			);
		}

		$license_keys = get_posts( $args );

		if ( $license_keys ) {

			$edd_sl = edd_software_licensing();

			foreach ( $license_keys as $license ) {

				$title      = $license->post_title;
				$title_pos  = strpos( $title, '-' ) + 1;
				$length     = strlen( $title );
				$email      = substr( $title, $title_pos, $length );

				$data[]    = array(
					'email'      => $email,
					'license'    => $edd_sl->get_license_key( $license->ID ),
					'status'     => $edd_sl->get_license_status( $license->ID ),
					'product'    => get_post_field( 'post_title', $edd_sl->get_download_id( $license->ID ) ),
					'date'       => $license->post_date,
					'expiration' => date( 'Y-m-d H:i:s', $edd_sl->get_license_expiration( $license->ID ) ),
					'limit'      => $edd_sl->license_limit( $license->ID ),
					'count'      => $edd_sl->get_site_count( $license->ID ),
					'urls'       => implode( ' - ', $edd_sl->get_sites( $license->ID ) ),
				);
			}
		}

		$data = apply_filters( 'edd_export_get_data', $data );
		$data = apply_filters( 'edd_export_get_data_' . $this->export_type, $data );

		return $data;
	}
}