<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The License Activations DB Class
 *
 * @since  3.6
 */

class EDD_SL_Activations_DB extends EDD_SL_DB {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'edd_license_activations';
		$this->primary_key = 'site_id';
		$this->version     = '1.0';

		if ( ! $this->table_exists( $this->table_name ) ) {
			$this->create_table();
		}

	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function get_columns() {
		return array(
			'site_id'        => '%d',
			'site_name'      => '%s',
			'license_id'     => '%d',
			'activated'      => '%d',
			'is_local'       => '%d',
		);
	}

	/**
	 * Returns the column labels only.
	 *
	 * @since 3.6
	 * @return array
	 */
	public function get_column_labels() {
		return array_keys( $this->get_columns() );
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function get_column_defaults() {
		return array(
			'site_name'  => null,
			'license_id' => null,
			'activated'  => 1,
			'is_local'   => 0,
		);
	}

	/**
	 * Retrieve all commissions for a customer
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function get_activations( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'number'    => 25,
			'offset'    => 0,
			'search'    => '',
			'orderby'   => 'license_id',
			'order'     => 'ASC',
			'activated' => 1,
		);

		// Account for 'paged' in legacy $args
		if ( isset( $args['paged'] ) && $args['paged'] > 1 ) {
			$number         = isset( $args['number'] ) ? $args['number'] : $defaults['number'];
			$args['offset'] = ( ( $args['paged'] - 1 ) * $number );
			unset( $args['paged'] );
		}

		// Alias 'Status' for easy of use
		if ( isset( $args['status'] ) ) {
			switch( $args['status'] ) {

				case 'activated':
				case 1:
					$args['activated'] = 1;
					break;

				case 'deactivated':
				case 0:
					$args['activated'] = 0;
					break;

				case 'all':
				default:
					$args['activated'] = array( 0, 1 );
					break;

			}

			unset( $args['status'] );
		}

		$args = wp_parse_args( $args, $defaults );

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = $this->parse_where( $args );

		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? 'id' : $args['orderby'];
		$args['orderby'] = esc_sql( $args['orderby'] );
		$args['order']   = esc_sql( $args['order'] );

		if ( isset( $args['fields'] ) && in_array( $args['fields'], $this->get_column_labels() ) ) {
			$activations = $wpdb->get_col( $wpdb->prepare( "SELECT {$args['fields']} FROM  $this->table_name $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d,%d;", absint( $args['offset'] ), absint( $args['number'] ) ), 0 );
		} else {
			$activations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM  $this->table_name $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d,%d;", absint( $args['offset'] ), absint( $args['number'] ) ) );
		}

		return $activations;
	}

	/**
	 * Delete any license activations
	 *
	 * @since 3.6
	 * @param $license_id
	 *
	 * @return false|int
	 */
	public function delete_all_activations( $license_id ) {
		global $wpdb;

		$sql = "DELETE FROM {$this->table_name} WHERE license_id = %d";
		return $wpdb->query( $wpdb->prepare( $sql, $license_id ) );
	}

	/**
	 * Count the total number of licenses in the database
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function count( $args = array() ) {
		global $wpdb;

		$where = $this->parse_where( $args );
		$sql   = "SELECT COUNT($this->primary_key) FROM " . $this->table_name . "{$where};";
		$count = $wpdb->get_var( $sql );

		return absint( $count );
	}

	public function delete( $site_id = '' ) {
		global $wpdb;

		if( empty( $site_id ) ) {
			return false;
		}

		$delete_query = $wpdb->prepare( "DELETE FROM $this->table_name WHERE $this->primary_key = %d", $site_id );

		if ( false === $wpdb->query( $delete_query ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Given the arguments, generate the 'where' clause for a MySQL query.
	 *
	 * @access private
	 * @since 3.6
	 *
	 * @param $args
	 *
	 * @return string
	 */
	private function parse_where( $args ) {
		$where = ' WHERE 1=1';

		// Specific Site ID(s)
		if ( ! empty( $args['site_id'] ) ) {

			if( is_array( $args['site_id'] ) ) {
				$site_ids = $this->prepare_in_values( $args['site_id'], 'absint' );
			} else {
				$site_ids = absint( $args['site_id'] );
			}

			$where .= " AND `site_id` IN( {$site_ids} ) ";

		}

		// Specific ID(s)
		if ( ! empty( $args['site_name'] ) ) {

			if( is_array( $args['site_name'] ) ) {
				$site_names = $this->prepare_in_values( $args['site_name'] );
			} else {
				$site_names = sanitize_text_field( $args['site_name'] );
			}

			$where .= " AND `site_name` IN( '{$site_names}' ) ";

		}

		// Specific Licenses(s)
		if ( isset( $args['license_id'] ) ) {

			$keys = false;

			if( is_array( $args['license_id'] ) ) {
				$keys = $this->prepare_in_values( $args['license_id'], 'absint' );
			} elseif ( is_numeric( $args['license_id'] ) ) {
				$keys = absint( $args['license_id'] );
			}

			if ( false !== $keys ) {
				$where .= " AND `license_id` IN( {$keys} ) ";
			}

		}

		// Specific activation status
		if ( isset( $args['activated'] ) ) {

			if ( is_array( $args['activated'] ) ) {
				$statuses = $this->prepare_in_values( $args['activated'], 'absint' );
			} else {
				$statuses = absint( $args['activated'] );
			}

			$where .= " AND `activated` IN( {$statuses} ) ";

		}

		// Specific is_local Checks
		if ( isset( $args['is_local'] ) ) {

			if ( is_array( $args['is_local'] ) ) {
				$is_local = $this->prepare_in_values( $args['is_local'], 'absint' );
			} else {
				$is_local = absint( $args['is_local'] );
			}

			$where .= " AND `is_local` IN( {$is_local} ) ";

		}

		if ( ! empty( $args['search'] ) ) {

			$search = sanitize_text_field( $args['search'] );
			$where .= " AND `id` LIKE '%{$search}%'";

		}

		return $where;
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function create_table() {

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE " . $this->table_name . " (
		site_id bigint(20) NOT NULL AUTO_INCREMENT,
		site_name varchar(128) NOT NULL,
		license_id bigint(20) NOT NULL,
		activated TINYINT(1) NOT NULL,
		is_local TINYINT(1) NOT NULL,
		PRIMARY KEY  (site_id),
		UNIQUE KEY (site_name,license_id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

		dbDelta( $sql );

		if ( $this->table_exists( $this->table_name ) ) {
			update_option( $this->table_name . '_db_version', $this->version );
		}
	}

	/**
	 * Given an array of values and a callback function, create a formatted set of values for a
	 * MySQL IN () clause.
	 *
	 * @since 3.6
	 *
	 * @param array  $array
	 * @param string $sanitize_callback
	 *
	 * @return string
	 */
	private function prepare_in_values( $array = array(), $sanitize_callback = 'sanitize_text_field' ) {
		if ( empty( $array ) || ! is_callable( $sanitize_callback ) ) {
			return '';
		}

		if ( in_array( $sanitize_callback, array( 'absint', 'intval', 'floatval' ) ) ) {
			$concat_with = ",";
		} else {
			$concat_with = "','";
		}

		return implode( $concat_with, array_map( $sanitize_callback, $array ) );
	}

}
