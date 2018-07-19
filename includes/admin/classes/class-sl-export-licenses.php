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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EDD_SL_License_Export Class.
 *
 * @since 3.6 - Updated to use EDD_Batch_Export
 */
class EDD_SL_License_Export extends EDD_Batch_Export {

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

		$download = new EDD_Download( $this->download_id );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=edd-export-' . $this->export_type . '-' . date( 'm-d-Y' ) .
		        '-' . sanitize_title( $download->get_name() ) . '.csv' );

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
			'id'          => __( 'License ID', 'edd_sl' ),
			'license'     => __( 'License Key', 'edd_sl' ),
			'status'      => __( 'License Status', 'edd_sl' ),
			'customer_id' => __( 'Customer ID', 'edd_sl' ),
			'email'       => __( 'Customer Email', 'edd_sl' ),
			'name'        => __( 'Customer Name', 'edd_sl' ),
			'user_id'     => __( 'User ID', 'edd_sl' ),
			'product'     => __( 'Product Name', 'edd_sl' ),
			'price_id'    => __( 'Price ID', 'edd_sl' ),
			'date'        => __( 'Purchase Date', 'edd_sl' ),
			'expiration'  => __( 'Expiration Date', 'edd_sl' ),
			'limit'       => __( 'Activation Limit', 'edd_sl' ),
			'count'       => __( 'Activation Count', 'edd_sl' ),
			'urls'        => __( 'Activated URLs', 'edd_sl' ),
		);

		return $cols;
	}

	/**
	 * Get the data being exported
	 *
	 * @access public
	 * @since 3.0
	 * @since 3.6 - Updated to use EDD_Batch_Export
	 *
	 * @return mixed array|bool Logs if they exist, false otherwise.
	 */
	public function get_data() {
		global $edd_logs;

		$data = array();

		$args = array(
			'number' => 30,
			'paged'  => $this->step,
		);

		if ( 'all' !== $this->status ) {
			$args['status'] = $this->status;
		}

		if ( ! empty( $this->download_id ) ) {
			$args['download_id'] = $this->download_id;
		}

		$licenses = edd_software_licensing()->licenses_db->get_licenses( $args );

		if ( ! empty( $licenses ) ) {
			foreach ( $licenses as $license ) {

				$data[] = array(
					'id'          => $license->ID,
					'license'     => $license->key,
					'status'      => $license->status,
					'customer_id' => $license->customer_id,
					'email'       => $license->customer->email,
					'name'        => $license->customer->name,
					'user_id'     => $license->user_id,
					'product'     => $license->download->get_name(),
					'price_id'    => $license->price_id,
					'date'        => $license->date_created,
					'expiration'  => $license->expiration,
					'limit'       => $license->activation_limit,
					'count'       => $license->activation_count,
					'urls'        => implode( ', ', $license->sites ),
				);

			}

			$data = apply_filters( 'edd_export_get_data', $data );
			$data = apply_filters( 'edd_export_get_data_' . $this->export_type, $data );

			return $data;
		}

		return false;
	}

	/**
	 * Return the calculated completion percentage.
	 *
	 * @access public
	 * @since 3.6
	 *
	 * @return int Percentage complete based on current step.
	 */
	public function get_percentage_complete() {
		global $edd_logs;

		$args = array(
			'number' => -1,
		);

		if ( 'all' !== $this->status ) {
			$args['status'] = $this->status;
		}

		if ( ! empty( $this->download_id ) ) {
			$args['download_id'] = $this->download_id;
		}

		$total      = edd_software_licensing()->licenses_db->count( $args );
		$percentage = 100;

		if ( $total > 0 ) {
			$percentage = ( ( 30 * $this->step ) / $total ) * 100;
		}

		if ( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Define properties for the batch exporter.
	 *
	 * @access public
	 * @since 3.6
	 */
	public function set_properties( $request ) {
		$this->status      = sanitize_text_field( $request['edd_sl_status'] );
		$this->download_id = absint( $request['edd_sl_download_id'] );
	}
}