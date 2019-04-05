<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The License DB Class
 *
 * @since  3.6
 */

class EDD_SL_License_DB extends EDD_SL_DB {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'edd_licenses';
		$this->primary_key = 'id';
		$this->version     = '1.0.1';

		$db_version = get_option( $this->table_name . '_db_version' );

		if ( ! $this->table_exists( $this->table_name ) || version_compare( $db_version, $this->version, '<' ) ) {
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
			'id'           => '%d',
			'license_key'  => '%s',
			'status'       => '%s',
			'download_id'  => '%d',
			'price_id'     => '%d',
			'payment_id'   => '%d',
			'cart_index'   => '%d',
			'date_created' => '%s',
			'expiration'   => '%s',
			'parent'       => '%d',
			'customer_id'  => '%d',
			'user_id'      => '%d',
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
			'license_key'  => '',
			'status'       => 'inactive',
			'download_id'  => 0,
			'price_id'     => NULL,
			'payment_id'   => 0,
			'cart_index'   => 0,
			'date_created' => current_time( 'mysql' ),
			'expiration'   => NULL,
			'parent'       => 0,
			'customer_id'  => 0,
			'user_id'      => 0,
		);
	}

	/**
	 * Retrieve a row by the primary key
	 *
	 * @access  public
	 * @since   2.1
	 * @return  object
	 */
	public function get( $row_id ) {
		$license = $this->get_cache( $row_id, 'edd_license_objects' );

		if ( false === $license ) {
			$license = parent::get( $row_id );
			$this->set_cache( $row_id, $license, 'edd_license_objects' );
		}

		return $license;
	}

	/**
	 * Retrieve licenses for the requested arguments.
	 *
	 * @access  public
	 * @since   3.6
	 *
	 * @return
	 */
	public function get_licenses( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'number'  => 20,
			'offset'  => 0,
			'search'  => '',
			'orderby' => 'id',
			'order'   => 'ASC',
		);

		// Account for 'paged' in legacy $args
		if ( isset( $args['paged'] ) && $args['paged'] > 1 ) {
			$number         = isset( $args['number'] ) ? $args['number'] : $defaults['number'];
			$args['offset'] = ( ( $args['paged'] - 1 ) * $number );
			unset( $args['paged'] );
		}

		$args = wp_parse_args( $args, $defaults );

		// If there is a 'customer_id' present in the args, and it's set to 0, return no results.
		if ( isset( $args['customer_id'] ) && ( 0 == $args['customer_id'] || '0' == $args['customer_id'] ) ) {
			return array();
		}

		// If there is a 'user_id' present in the args, and it's set to 0, return no results.
		if ( isset( $args['user_id'] ) && ( 0 == $args['user_id'] || '0' == $args['user_id'] ) ) {
			return array();
		}

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = $this->parse_where( $args );

		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? 'id' : $args['orderby'];
		$args['orderby'] = esc_sql( $args['orderby'] );
		$args['order']   = esc_sql( $args['order'] );

		$query = $wpdb->prepare(
			"SELECT DISTINCT( l1.id ) FROM  {$this->table_name} l1 {$where} ORDER BY l1.{$args['orderby']} {$args['order']} LIMIT %d,%d;",
			absint( $args['offset'] ),
			absint( $args['number'] )
		);

		$license_ids = $wpdb->get_col( $query, 0 );
		$licenses    = array();

		if( ! empty( $license_ids ) ) {

			foreach( $license_ids as $license_id ) {
				if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
					$licenses[] = (int) $license_id;
				} else {
					$licenses[] = edd_software_licensing()->get_license( (int) $license_id );
				}
			}

		}

		return $licenses;
	}

	/**
	 * Count the total number of licenses in the database
	 *
	 * @access  public
	 * @since   3.6
	 */
	public function count( $args = array() ) {
		global $wpdb;

		$where     = $this->parse_where( $args );

		$sql   = "SELECT COUNT($this->primary_key) FROM " . $this->table_name . " as l1 {$where};";
		$count = $wpdb->get_var( $sql );

		return absint( $count );

	}

	public function delete( $license_id = 0 ) {
		global $wpdb;

		// Row ID must be positive integer
		$license_id = absint( $license_id );

		if( empty( $license_id ) ) {
			return false;
		}

		$delete_query = $wpdb->prepare( "DELETE FROM $this->table_name WHERE $this->primary_key = %d", $license_id );

		if ( false === $wpdb->query( $delete_query ) ) {
			return false;
		} else {
			$this->delete_cache( $license_id, 'edd_license_objects'  );

			edd_software_licensing()->license_meta_db->delete_all_meta( $license_id );
			edd_software_licensing()->activations_db->delete_all_activations( $license_id );

			// Before we unassociate the child licenses, get the IDs of them.
			$child_license_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $this->primary_key FROM $this->table_name WHERE parent = %d", $license_id ), 0 );

			// Remove any child license associations with this license.
			$wpdb->update(
				$this->table_name,
				array( 'parent' => 0 ),
				array( 'parent' => $license_id ),
				array( '%d' ),
				array( '%d' )
			);

			// Delete the cache of the child license keys.
			$this->delete_cache_multi( $child_license_ids, 'edd_license_objects' );
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
		$where_prefix = ' WHERE 1=1';
		$where        = '';

		// Handle swapping 'post_parent' for 'parent'.
		if ( isset( $args['post_parent'] ) ) {
			if ( ! isset( $args['parent'] ) ) {
				$args['parent'] = $args['post_parent'];
			}

			unset( $args['post_parent'] );
		}

		// Handle swapping lifetime for expiration
		if ( isset( $args['lifetime'] ) ) {
			if ( ! empty( $args['lifetime'] ) ) {
				$args['expiration'] = 0;
			} else {
				$args['expiration'] = 'any';
			}

			unset( $args['lifetime'] );

		}

		// Specific ID(s)
		if ( ! empty( $args['id'] ) ) {

			if( is_array( $args['id'] ) ) {
				$license_ids = $this->prepare_in_values( $args['id'], 'absint' );
			} else {
				$license_ids = absint( $args['id'] );
			}

			$where .= " AND l1.id IN( {$license_ids} ) ";

		}

		if ( ! empty( $args['key'] ) ) {
			$args['license_key'] = $args['key'];
			unset( $args['key'] );
		}

		// Specific Key(s)
		if ( ! empty( $args['license_key'] ) ) {

			if( is_array( $args['license_key'] ) ) {
				$keys = $this->prepare_in_values( $args['license_key'] );
			} else {
				$keys = "'" . sanitize_text_field( $args['license_key'] ) . "'";
			}

			$where .= " AND l1.license_key IN( {$keys} ) ";

		}

		// Specific parent license
		if ( isset( $args['parent'] ) ) {

			if ( is_array( $args['parent'] ) ) {
				$parents = $this->prepare_in_values( $args['parent'], 'absint' );
			} else {
				$parents = absint( $args['parent'] );
			}

			$where .= " AND l1.parent IN( {$parents} ) ";

		}

		// Specific users
		if( ! empty( $args['user_id'] ) ) {

			if( is_array( $args['user_id'] ) ) {
				$user_ids = $this->prepare_in_values( $args['user_id'], 'intval' );
			} else {
				$user_ids = intval( $args['user_id'] );
			}

			$where .= " AND l1.user_id IN( {$user_ids} ) ";

		}

		// Specific customers
		if( ! empty( $args['customer_id'] ) ) {

			if( is_array( $args['customer_id'] ) ) {
				$customer_ids = $this->prepare_in_values( $args['customer_id'], 'intval' );
			} else {
				$customer_ids = intval( $args['customer_id'] );
			}

			$where .= " AND l1.customer_id IN( {$customer_ids} ) ";

		}

		// Specific payment IDs
		if ( ! empty( $args['payment_id'] ) ) {

			if( is_array( $args['payment_id'] ) ) {
				$payment_ids = $this->prepare_in_values( $args['payment_id'], 'intval' );
			} else {
				$payment_ids = intval( $args['payment_id'] );
			}

			$meta_table    = edd_software_licensing()->license_meta_db->table_name;
			$where_prefix  = " LEFT JOIN {$meta_table} lm ON l1.id = lm.license_id WHERE 1=1 ";
			$where        .= " AND ( l1.payment_id IN( {$payment_ids} ) OR ( lm.meta_key = '_edd_sl_payment_id' AND lm.meta_value IN ( {$payment_ids} ) )  ) ";

		}

		// Get licenses activated on a specific site.
		if ( ! empty( $args['site'] ) ) {
			$activation_table = edd_software_licensing()->activations_db->table_name;
			$where_prefix     = " LEFT JOIN {$activation_table} la ON l1.id = la.license_id WHERE 1=1 ";

			$site   = edd_software_licensing()->clean_site_url( $args['site'] );
			$where .= " AND la.site_name LIKE '%{$site}%' ";
		}

		// Specific cart_index
		if ( ! empty( $args['cart_index'] ) ) {

			if( is_array( $args['cart_index'] ) ) {
				$cart_indexes = $this->prepare_in_values( $args['cart_index'], 'intval' );
			} else {
				$cart_indexes = intval( $args['cart_index'] );
			}

			$where .= " AND l1.cart_index IN( {$cart_indexes} ) ";

		}

		// Specific Downloads
		if ( ! empty( $args['download_id'] ) ) {
			if ( is_array( $args['download_id'] ) ) {
				$download_ids = $this->prepare_in_values( $args['download_id'], 'absint' );
			} else {
				$download_ids = absint( $args['download_id'] );
			}

			$where .= " AND l1.download_id IN( {$download_ids} ) ";

		}

		// Specific price IDs
		if ( isset( $args['price_id'] ) ) {

			if ( is_array( $args['price_id'] ) ) {
				$price_ids = $this->prepare_in_values( $args['price_id'], 'absint' );
			} else {
				$price_ids = absint( $args['price_id'] );
			}

			$where .= " AND l1.price_id IN( {$price_ids} ) ";

		}

		// Specific statuses
		if( ! empty( $args['status'] ) ) {

			if( is_array( $args['status'] ) ) {
				$statuses = $this->prepare_in_values( $args['status'] );
			} else {
				$statuses = "'" . sanitize_text_field( $args['status'] ) . "'";
			}

			$where .= " AND l1.status IN( {$statuses} ) ";

		} else {
			$where .= " AND l1.status != 'private' ";
		}

		// Created for a specific date or in a date range
		if( ! empty( $args['date'] ) ) {

			if( is_array( $args['date'] ) ) {

				if( ! empty( $args['date']['start'] ) ) {

					$start = date( 'Y-m-d H:i:s', strtotime( $args['date']['start'] ) );

					$where .= " AND l1.date_created >= '{$start}'";

				}

				if( ! empty( $args['date']['end'] ) ) {

					$end = date( 'Y-m-d H:i:s', strtotime( $args['date']['end'] ) );

					$where .= " AND l1.date_created <= '{$end}'";

				}

			} else {

				$year  = date( 'Y', strtotime( $args['date'] ) );
				$month = date( 'm', strtotime( $args['date'] ) );
				$day   = date( 'd', strtotime( $args['date'] ) );

				$where .= " AND $year = YEAR ( l1.date_created ) AND $month = MONTH ( l1.date_created ) AND $day = DAY ( l1.date_created )";
			}

		}

		// Specific expiration date or in a expiration date range
		if( isset ( $args['expiration'] ) ) {

			if( is_array( $args['expiration'] ) ) {

				if( ! empty( $args['expiration']['start'] ) ) {

					if ( is_numeric( $args['expiration']['start'] ) ) {
						$start = $args['expiration']['start'];
					} else {
						$start = strtotime( $args['expiration']['start'] );
					}

				}

				if( ! empty( $args['expiration']['end'] ) ) {

					if ( is_numeric( $args['expiration']['end'] ) ) {
						$end = $args['expiration']['end'];
					} else {
						$end = strtotime( $args['expiration']['end'] );
					}

				}

				if ( isset( $start ) && isset( $end ) ) {
					if ( $start > $end ) {
						$where .= " AND l1.expiration BETWEEN {$end} AND {$start}";
					} else {
						$where .= " AND l1.expiration BETWEEN {$start} AND {$end}";
					}
				} else if ( isset( $start ) && ! isset( $end ) ) {
					$where .= " AND l1.expiration >= {$start}";
				} else if ( isset( $end ) && ! isset( $start ) ) {
					$where .= " AND l1.expiration <= {$start}";
				}

			} else if ( ! empty( $args['expiration'] ) ) {

				if ( 'any' === $args['expiration'] ) {

					// Accept any non-lifetime license expiration.
					$where .= " AND l1.expiration > 0";

				} else {

					// Accept a specific date.
					if ( is_numeric( $args['expiration'] ) ) {
						$year  = date( 'Y', $args['expiration'] );
						$month = date( 'm', $args['expiration'] );
						$day   = date( 'd', $args['expiration'] );
					} else {
						$year  = date( 'Y', strtotime( $args['expiration'] ) );
						$month = date( 'm', strtotime( $args['expiration'] ) );
						$day   = date( 'd', strtotime( $args['expiration'] ) );
					}

					$start = strtotime( $month . '-' . $day . '-' . $year . ' 00:00:00' );
					$end   = strtotime( $month . '-' . $day . '-' . $year . ' 23:59:59' );

					$where .= " AND l1.expiration BETWEEN {$start} AND {$end}";

				}

			} else {

				// Accept only lifetime licenses.
				$where .= " AND l1.expiration = 0";

			}

		}

		if ( ! empty( $args['search'] ) ) {

			$search = sanitize_text_field( $args['search'] );
			$where .= " AND l1.license_key LIKE '%{$search}%'";

		}

		return $where_prefix . $where;
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
		id bigint(20) NOT NULL AUTO_INCREMENT,
		license_key varchar(64) NOT NULL,
		status varchar(20) NOT NULL,
		download_id bigint(20) NOT NULL,
		price_id varchar(20),
		payment_id bigint(20) NOT NULL,
		cart_index bigint(20) NOT NULL,
		date_created DATETIME NOT NULL,
		expiration bigint(32),
		parent varchar(20) NOT NULL,
		customer_id varchar(20) NOT NULL,
		user_id varchar(20) NOT NULL,
		PRIMARY KEY  (id),
		KEY download_id_and_price_id (download_id, price_id)
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

		return "'" . implode( "','", array_map( $sanitize_callback, $array ) ) . "'";
	}

}
