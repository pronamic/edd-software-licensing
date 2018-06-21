<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The License Meta DB Class
 *
 * @since  3.6
 */

class EDD_SL_License_Meta_DB extends EDD_SL_DB {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'edd_licensemeta';
		$this->primary_key = 'meta_id';
		$this->version     = '1.0';

		if ( ! $this->table_exists( $this->table_name ) ) {
			$this->create_table();
		}

		add_action( 'plugins_loaded', array( $this, 'register_table' ), 11 );

	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function get_columns() {
		return array(
			'meta_id'    => '%d',
			'license_id' => '%d',
			'meta_key'   => '%s',
			'meta_value' => '%s',
		);
	}

	/**
	 * Register the table with $wpdb so the metadata api can find it
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function register_table() {
		global $wpdb;
		$wpdb->licensemeta = $this->table_name;
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function get_column_defaults() {
		return array(
			'license_id' => 0,
			'meta_key'   => '',
			'meta_value' => 0,
		);
	}
	/**
	 * Retrieve license meta field for a license.
	 *
	 * For internal use only. Use EDD_SL_License->get_meta() for public usage.
	 *
	 * @param   int    $license_id      License ID.
	 * @param   string $meta_key        The meta key to retrieve.
	 * @param   bool   $single          Whether to return a single value.
	 * @return  mixed                   Will be an array if $single is false. Will be value of meta data field if $single is true.
	 *
	 * @access  private
	 * @since   3.6
	 */
	public function get_meta( $license = 0, $meta_key = '', $single = false ) {
		$license_id = $this->sanitize_license_id( $license );
		if ( false === $license_id ) {
			return false;
		}

		return get_metadata( 'license', $license_id, $meta_key, $single );
	}

	public function get_license_id( $meta_key = '', $meta_value = '', $single = false ) {
		global $wpdb;
		if ( empty( $meta_key ) ) {
			return false;
		}

		if ( $single ) {
			$license_id = $wpdb->get_var( $wpdb->prepare( "SELECT license_id FROM {$this->table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $meta_value ) );
		} else {
			$license_id = $wpdb->get_col( $wpdb->prepare( "SELECT license_id FROM {$this->table_name} WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ), 0 );
		}

		return (int) $license_id;
	}

	/**
	 * Add meta data field to a license record.
	 *
	 * For internal use only. Use EDD_SL_License->add_meta() for public usage.
	 *
	 * @param   int    $license_id    License ID.
	 * @param   string $meta_key      Metadata name.
	 * @param   mixed  $meta_value    Metadata value.
	 * @param   bool   $unique        Optional, default is false. Whether the same key should not be added.
	 * @return  bool                  False for failure. True for success.
	 *
	 * @access  private
	 * @since   3.6
	 */
	public function add_meta( $license_id = 0, $meta_key = '', $meta_value, $unique = false ) {
		$license_id = $this->sanitize_license_id( $license_id );
		if ( false === $license_id ) {
			return false;
		}

		if ( $unique ) {
			$license = edd_software_licensing()->get_license( $license_id );
			$existing_meta = $license->get_meta( $meta_key, true );
			if ( ! empty( $existing_meta ) ) {
				return false;
			}
		}

		return add_metadata( 'license', $license_id, $meta_key, $meta_value, $unique );
	}

	/**
	 * Update license meta field based on License ID.
	 *
	 * For internal use only. Use EDD_SL_License->update_meta() for public usage.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and License ID.
	 *
	 * If the meta field for the license does not exist, it will be added.
	 *
	 * @param   int    $license_id   License ID.
	 * @param   string $meta_key      Metadata key.
	 * @param   mixed  $meta_value    Metadata value.
	 * @param   mixed  $prev_value    Optional. Previous value to check before removing.
	 * @return  bool                  False on failure, true if success.
	 *
	 * @access  private
	 * @since   3.6
	 */
	public function update_meta( $license_id = 0, $meta_key = '', $meta_value, $prev_value = '' ) {
		$license_id = $this->sanitize_license_id( $license_id );
		if ( false === $license_id ) {
			return false;
		}

		return update_metadata( 'license', $license_id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Remove metadata matching criteria from a license record.
	 *
	 * For internal use only. Use EDD_SL_License->delete_meta() for public usage.
	 *
	 * You can match based on the key, or key and value. Removing based on key and
	 * value, will keep from removing duplicate metadata with the same key. It also
	 * allows removing all metadata matching key, if needed.
	 *
	 * @param   int    $license_id    License ID.
	 * @param   string $meta_key      Metadata name.
	 * @param   mixed  $meta_value    Optional. Metadata value.
	 * @param   bool   $delete_all    If all items should be deleted or just the first
	 * @return  bool                  False for failure. True for success.
	 *
	 * @access  private
	 * @since   3.6
	 */
	public function delete_meta( $license_id = 0, $meta_key = '', $meta_value = '', $delete_all = false ) {
		return delete_metadata( 'license', $license_id, $meta_key, $meta_value, $delete_all );
	}

	/**
	 * Delete meta for a license ID
	 *
	 * @since 3.6
	 * @param $license_id
	 */
	public function delete_all_meta( $license_id ) {
		global $wpdb;

		$table = $this->table_name;
		$sql   = 'SELECT meta_key, meta_value FROM ' . $table . ' WHERE license_id = %d';
		$meta  = $wpdb->get_results( $wpdb->prepare( $sql, $license_id ) );
		foreach ( $meta as $meta_row ) {
			$this->delete_meta( $license_id, $meta_row->meta_key, $meta_row->meta_value, true );
		}
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function create_table() {

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE {$this->table_name} (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			license_id bigint(20) NOT NULL,
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY license_id (license_id),
			KEY meta_key (meta_key),
			KEY license_id_and_meta_key (license_id, meta_key)
			) CHARACTER SET utf8 COLLATE utf8_general_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

	/**
	 * Given a license ID, make sure it's a positive number, greater than zero before inserting or adding.
	 *
	 * @since  3.6
	 * @param  int|stirng $license_id    A passed license ID.
	 * @return int|bool                  The normalized license ID or false if it's found to not be valid.
	 */
	private function sanitize_license_id( $license_id ) {
		if ( ! is_numeric( $license_id ) ) {
			return false;
		}

		$license_id = (int) $license_id;

		// We were given a non positive number
		if ( absint( $license_id ) !== $license_id ) {
			return false;
		}

		if ( empty( $license_id ) ) {
			return false;
		}

		return absint( $license_id );

	}

}
