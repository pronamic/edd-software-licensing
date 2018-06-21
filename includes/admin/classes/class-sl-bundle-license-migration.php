<?php
/**
 * Migrate Bundle License Data
 *
 * This re-connects child licenses with the new license IDs of their parent licenses.
 *
 * @subpackage  Admin/Classes/EDD_SL_Bundle_License_Migration
 * @copyright   Copyright (c) 2015, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_SL_Bundle_License_Migration Class
 *
 * @since 3.6
 */
class EDD_SL_Bundle_License_Migration extends EDD_Batch_Export {

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

		$items = $this->get_stored_data( 'edd_sl_child_license_ids' );

		if ( ! is_array( $items ) ) {
			return false;
		}

		$offset     = ( $this->step - 1 ) * $this->per_step;
		$step_items = array_slice( $items, $offset, $this->per_step );

		if ( $step_items ) {

			// Get the already generated list of legacy license IDs.
			$legacy_license_ids = $this->get_stored_data( 'edd_sl_legacy_ids' );
			$license_db_table   = edd_software_licensing()->licenses_db->table_name;

			// Force Debug Mode on so we can log failures.
			add_filter( 'edd_is_debug_mode', '__return_true' );

			// Make sure to turn off our caching layers.
			if ( ! defined( 'DOING_SL_MIGRATION' ) ) {
				define( 'DOING_SL_MIGRATION', true );
			}

			foreach ( $step_items as $item ) {

				$child_license = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$license_db_table} WHERE id = %d", $item ) );
				if ( ! empty( $child_license->parent ) ) {
					$new_parent_id = isset( $legacy_license_ids[ $child_license->parent ] ) ? $legacy_license_ids[ $child_license->parent ] : 0;
					if ( ! empty( $new_parent_id ) ) {
						$wpdb->update( $license_db_table, array( 'parent' => $new_parent_id ), array( 'id' => $child_license->id ), '%d', '%d' );
					}
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

		$items = $this->get_stored_data( 'edd_sl_child_license_ids', false );
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
			wp_die(
				__( 'You do not have permission to migrate licenses.', 'edd_sl' ),
				__( 'Error', 'edd_sl' ),
				array( 'response' => 403 ) );
		}

		$had_data = $this->get_data();

		if( $had_data ) {
			$this->done = false;
			return true;
		} else {
			$this->delete_data( 'edd_sl_child_license_ids' );
			$this->delete_data( 'edd_sl_legacy_ids' );

			$this->done    = true;
			$this->message = __( 'Bundled licenses successfully upgraded.', 'edd_sl' );
			edd_set_upgrade_complete( 'migrate_license_parent_child' );
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
		global $wpdb;

		// Create the tables if necessary
		$licenses_db = edd_software_licensing()->licenses_db;
		if ( ! $licenses_db->table_exists( $licenses_db->table_name ) ) {
			@$licenses_db->create_table();
		}

		$license_meta_db = edd_software_licensing()->license_meta_db;
		if ( ! $license_meta_db->table_exists( $license_meta_db->table_name ) ) {
			@$license_meta_db->create_table();
		}

		if ( $this->step == 1 ) {
			$this->delete_data( 'edd_sl_child_license_ids' );
			$this->delete_data( 'edd_sl_legacy_ids' );
		}

		// Find any licenses that have a parent license assigned.
		$child_licenses_ids = get_option( 'edd_sl_child_license_ids', false );
		if ( false === $child_licenses_ids ) {
			$child_licenses_ids = array();

			$license_id_query   = "SELECT id FROM {$licenses_db->table_name} WHERE parent != 0";
			$child_licenses = $wpdb->get_col( $license_id_query, 0 );

			if ( ! empty( $child_licenses ) ) {
				$child_licenses_ids = $child_licenses;
			}

			$this->store_data( 'edd_sl_child_license_ids', $child_licenses_ids );
		}

		// Get the legacy and new license ID relationships
		// Array in the form of [ legacy_license_id ] => new_license_id
		$legacy_license_ids = get_option( 'edd_sl_legacy_ids', false );
		if ( false === $legacy_license_ids ) {
			$legacy_license_ids = array();

			$license_id_query = "SELECT license_id, meta_value as legacy_license_id FROM {$license_meta_db->table_name} WHERE meta_key = '_edd_sl_legacy_id'";
			$legacy_licenses  = $wpdb->get_results( $license_id_query );

			if ( ! empty( $legacy_licenses ) ) {
				foreach ( $legacy_licenses as $legacy_license ) {
					$legacy_license_ids[ $legacy_license->legacy_license_id ] = $legacy_license->license_id;
				}
			}

			$this->store_data( 'edd_sl_legacy_ids', $legacy_license_ids );
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
		$value = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = '%s'", $key )
		);

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
