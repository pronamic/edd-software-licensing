<?php
/**
 * Remove Legacy Licenses
 *
 * This deletes all legacy licenses and their meta entries in the WordPress post and postmeta tables.
 *
 * @subpackage  Admin/Classes/EDD_SL_Remove_Legacy_Licenses
 * @copyright   Copyright (c) 2015, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_SL_Remove_Legacy_Licenses Class
 *
 * @since 3.6
 */
class EDD_SL_Remove_Legacy_Licenses extends EDD_Batch_Export {

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

			// Make sure to turn off our caching layers.
			if ( ! defined( 'DOING_SL_MIGRATION' ) ) {
				define( 'DOING_SL_MIGRATION', true );
			}

			$step_ids = "'" . implode( "','", array_map( 'intval', $step_items ) ) . "'";

			// Delete the posts from the database.
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$step_ids})" );

			// Delete any associated meta from the database.
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$step_ids})" );

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
			$this->delete_data( 'edd_sl_legacy_license_ids' );

			$this->done    = true;
			$this->message = __( 'Legacy licenses successfully removed. You can now navigate from this page.', 'edd_sl' );
			edd_set_upgrade_complete( 'remove_legacy_licenses' );
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

		if ( $this->step == 1 ) {
			$this->delete_data( 'edd_sl_legacy_license_ids' );
		}

		$items = get_option( 'edd_sl_legacy_license_ids', false );

		if ( false === $items ) {
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
