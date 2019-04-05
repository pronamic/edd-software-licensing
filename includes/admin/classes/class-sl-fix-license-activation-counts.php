<?php
/**
 * Fix license activation counts that might have been removed due to a bug that occurred when Disable URL checking was enabled.
 *
 * This moves the licenses, their meta, and activated URLs to custom tables
 *
 * @subpackage  Admin/Classes/EDD_SL_License_Activation_Count_Fix
 * @copyright   Copyright (c) 2015, Chris Klosowski
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_SL_License_Migration Class
 *
 * @since 3.6
 */
class EDD_SL_License_Activation_Count_Fix extends EDD_Batch_Export {

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
	 * @return bool  If we had items to process
	 */
	public function get_data() {

		$items = $this->get_stored_data( 'edd_sl_license_ids_to_fix' );

		if ( ! is_array( $items ) ) {
			return false;
		}

		$offset     = ( $this->step - 1 ) * $this->per_step;
		$step_items = array_slice( $items, $offset, $this->per_step );

		if ( $step_items ) {

			// Force Debug Mode on so we can log failures.
			add_filter( 'edd_is_debug_mode', '__return_true' );

			// Don't throw deprecated notices while migrating.
			add_filter( 'eddsl_show_deprecated_notices', '__return_false' );

			foreach ( $step_items as $item ) {

				$license = edd_software_licensing()->get_license( $item );
				edd_debug_log( 'Checking License ' . $item . ' for activation counts', true );
				$activations   = 0;
				$deactivations = 0;
				foreach( $license->get_logs() as $log ) {
					if ( false !== strpos( $log->post_title, __( 'LOG - License Activated: ', 'edd_sl' ) . $item ) ) {
						$activations++;
					} else if ( false !== strpos( $log->post_title, __( 'LOG - License Deactivated: ', 'edd_sl' ) . $item ) ) {
						$deactivations++;
					}
				}

				edd_debug_log( 'Found ' . $activations . ' activations and ' . $deactivations . ' deactivations', true );

				$total_activations = $activations - $deactivations;
				if ( ! empty( $license->activation_limit ) && $license->activation_limit < $total_activations ) {
					$total_activations = $license->activation_limit;
				}

				if ( ! empty( $total_activations ) ) {
					edd_debug_log( 'Updating license ID ' . $item . ' to have ' . $total_activations . ' activations', true );
					$license->update_meta( '_edd_sl_activation_count', $total_activations );
					$license->add_log( __( 'License activation count updated via update routine.', 'edd_sl' ), __( 'Activation count updated based off activation/deactivation logs via update routine', 'edd_sl' ) );
				} else {
					edd_debug_log( 'License ID ' . $item . ' did not need to have the activation count updated.', true );
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

		$items = $this->get_stored_data( 'edd_sl_license_ids_to_fix', false );
		$total = is_countable( $items ) ? count( $items ) : 0;

		$percentage = 100;

		if ( $total > 0 ) {
			$percentage = ( ( $this->per_step * $this->step ) / $total ) * 100;
		}

		if ( $percentage > 100 ) {
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
			wp_die( __( 'You do not have permission to run this update.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 403 ) );
		}

		$had_data = $this->get_data();

		if ( $had_data ) {
			$this->done = false;
			return true;
		} else {
			$this->done    = true;

			$this->delete_data( 'edd_sl_license_ids_to_fix' );
			$this->message = __( 'License activations update complete.', 'edd_sl' );
			edd_set_upgrade_complete( 'fix_no_url_check_activation_counts' );

			// Don't throw deprecated notices while migrating.
			add_filter( 'eddsl_show_deprecated_notices', '__return_false' );

			delete_option( 'edd_sl_fixing_license_activation_counts' );
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

		$license_meta_db = edd_software_licensing()->license_meta_db;

		if ( $this->step == 1 ) {
			$this->delete_data( 'edd_sl_license_ids_to_fix' );
			update_option( 'edd_sl_fixing_license_activation_counts', 1 );
		}

		$items = get_option( 'edd_sl_license_ids_to_fix', false );

		if ( false === $items ) {
			$license_ids = array();

			$sql = "SELECT l.id
					  FROM {$licenses_db->table_name} l
					  LEFT JOIN {$license_meta_db->table_name} lm ON l.id = lm.license_id
					  WHERE l.parent = 0 AND NOT EXISTS ( SELECT lm.license_id FROM {$license_meta_db->table_name} WHERE lm.license_id = l.id AND meta_key = '_edd_sl_activation_count' )";

			$results     = $wpdb->get_col( $sql );
			$license_ids = $results;
			$this->store_data( 'edd_sl_license_ids_to_fix', $license_ids );
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
