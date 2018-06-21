<?php

/**
 * Class EDD_SL_DB
 *
 * @since 3.6
 */
class EDD_SL_DB extends EDD_DB {

	public function update( $row_id, $data = array(), $where = '' ) {
		global $wpdb;

		$updated = parent::update( $row_id, $data, $where );

		if ( ! empty( $updated ) && $this->use_cache() ) {
			$this->delete_query_cache();

			switch( $this->table_name ) {

				case $wpdb->prefix . 'edd_licenses':
					$license_id = $row_id;
					break;

				case $wpdb->prefix . 'edd_licensemeta':
					$license_id = $this->get_column_by( 'license_id', 'meta_id', $row_id );
					break;

				case $wpdb->prefix . 'edd_license_activations':
					$license_id = $this->get_column_by( 'license_id', 'site_id', $row_id );
					break;

			}

			$this->delete_cache( $license_id, 'edd_license_objects' );
		}

		return $updated;
	}

	public function insert( $data, $type = '' ) {
		global $wpdb;

		$insert_id = parent::insert( $data, $type = '' );

		if ( ! empty( $insert_id ) && $this->use_cache() ) {
			$this->delete_query_cache();

			switch( $this->table_name ) {

				case $wpdb->prefix . 'edd_licensemeta':
					$license_id = $this->get_column_by( 'license_id', 'meta_id', $insert_id );
					break;

				case $wpdb->prefix . 'edd_license_activations':
					$license_id = $this->get_column_by( 'license_id', 'site_id', $insert_id );
					break;

			}

			if ( ! empty( $license_id ) ) {
				$this->delete_cache( 'edd_license_objects', $license_id );
			}
		}

		return $insert_id;
	}

	/**
	 * Get a value from wp_cache
	 *
	 * @since 3.6
	 *
	 * @param string $group
	 * @param string $key
	 *
	 * @return bool|mixed
	 */
	protected function get_cache( $key = '', $group = '' ) {
		$key = $this->sanitize_key( $key );

		return $this->use_cache() ? wp_cache_get( $key, $group ) : false;
	}

	/**
	 * Set a value in wp_cache
	 *
	 * @since 3.6
	 *
	 * @param string $group
	 * @param string $key
	 * @param string $data
	 * @param int    $expires
	 *
	 * @return bool
	 */
	protected function set_cache( $key = '', $data = '', $group = '', $expires = MINUTE_IN_SECONDS ) {
		$key = $this->sanitize_key( $key );
		return $this->use_cache() ? wp_cache_set( $key, $data, $group, $expires ) : false;
	}

	/**
	 * Delete a value from wp_cache
	 *
	 * @since 3.6
	 *
	 * @param string $group
	 * @param string $key
	 *
	 * @return bool
	 */
	protected function delete_cache( $key = '', $group = '' ) {
		$key = $this->sanitize_key( $key );

		return $this->use_cache() ? wp_cache_delete( $key, $group ) : false;
	}

	/**
	 * Sanitize the key for storing in cache. If an array is passed, it will json_encode and MD5 it
	 *
	 * @since 3.6
	 *
	 * @param $key
	 *
	 * @return string
	 */
	private function sanitize_key( $key ) {
		if ( is_array( $key ) ) {
			$key = md5( json_encode( $key ) );
		}

		return sanitize_key( $key );
	}

	/**
	 * Delete all caches related to licenses
	 */
	private function delete_query_cache() {
		global $wp_object_cache;

		// These are our cache groups, license_meta is a registered meta table, so WordPress core handles that invalidation.
		$cache_groups = apply_filters( 'edd_sl_cache_groups', array( 'edd_licenses', 'edd_license_activations' ) );
		foreach ( $cache_groups as $group ) {
			if ( ! empty( $wp_object_cache->cache[ $group ] ) ) {
				foreach ( $wp_object_cache->cache[ $group ] as $key => $cached_data ) {
					$this->delete_cache( $key, $group );
				}

			}
		}
	}

	private function use_cache() {
		return apply_filters( 'edd_sl_db_use_cache', true );
	}

}