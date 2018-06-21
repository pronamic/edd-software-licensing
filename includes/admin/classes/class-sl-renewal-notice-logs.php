<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EDD_SL_Renewal_Notice_Logs extends WP_List_Table {

	private $per_page = 20;

	function __construct() {

		//Set parent defaults
		parent::__construct( array(
			'singular' => 'renewal_notice',  //singular name of the listed records
			'plural'   => 'renewal_notices', //plural name of the listed records
			'ajax'     => false              //does this table support ajax?
		) );
	}

	/**
	 * Setup columns
	 *
	 * @access      public
	 * @since       3.0
	 * @return      array
	 */
	function get_columns() {

		$columns = array(
			'cb'        => '<input type="checkbox"/>',
			'recipient' => __( 'Product - Recipient', 'edd_sl' ),
			'subject'   => __( 'Email Subject', 'edd_sl' ),
			'date'      => __( 'Date Sent', 'edd_sl' ),
		);

		return $columns;
	}

	/**
	 * Output the checkbox column
	 *
	 * @access      public
	 * @since       3.0
	 * @return      void
	 */
	function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item->ID )
		);

	}

	/**
	 * Output the recipient column
	 *
	 * @access      public
	 * @since       3.0
	 * @return      void
	 */
	function column_recipient( $item ) {
		$license_id = get_post_meta( $item->ID, '_edd_sl_log_license_id', true );
		$license    = edd_software_licensing()->get_license( $license_id );

		return $license->get_download()->get_name() . ' &mdash; ' . $license->customer->email;
	}

	/**
	 * Output the subject column
	 *
	 * @access      public
	 * @since       3.0
	 * @return      void
	 */
	function column_subject( $item ) {
		$notice_id = get_post_meta( $item->ID, '_edd_sl_renewal_notice_id', true );
		$notice    = edd_sl_get_renewal_notice( $notice_id );

		return $notice['subject'];
	}

	/**
	 * Output the date column
	 *
	 * @access      public
	 * @since       3.0
	 * @return      void
	 */
	function column_date( $item ) {
		return $item->post_date;
	}

	/**
	 * Retrieve the current page number
	 *
	 * @access      public
	 * @since       3.0
	 * @return      int
	 */
	function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	/**
	 * Outputs the log views
	 *
	 * @access public
	 * @since  3.0
	 * @return void
	 */
	function bulk_actions( $which = '' ) {
		// These aren't really bulk actions but this outputs the markup in the right place
		edd_log_views();
	}

	/**
	 * Retrieve the current page number
	 *
	 * @access      public
	 * @since       3.0
	 * @return      int
	 */
	function count_total_items() {

		$args = array(
			'post_type' => 'edd_license_log',
			'fields'    => 'ids',
			'nopaging'  => true,
			'tax_query' => array(
				array(
					'taxonomy' => 'edd_log_type',
					'terms'    => array( 'renewal_notice' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			return $query->post_count;
		}

		return 0;
	}

	/**
	 * Query database for license data and prepare it for the table
	 *
	 * @access      public
	 * @since       3.0
	 * @return      array
	 */
	function logs_data() {

		$license_args = array(
			'post_type'      => 'edd_license_log',
			'post_status'    => array( 'publish', 'future' ),
			'posts_per_page' => $this->per_page,
			'paged'          => $this->get_paged(),
			'tax_query'      => array(
				array(
					'taxonomy' => 'edd_log_type',
					'terms'    => array( 'renewal_notice' ),
					'field'    => 'slug',
				),
			),
		);

		return get_posts( $license_args );

	}

	/**
	 * Sets up the list table items
	 *
	 * @access      public
	 * @since       3.0
	 * @return      void
	 */
	function prepare_items() {

		/**
		 * First, lets decide how many records per page to show
		 */

		$columns = $this->get_columns();

		$this->_column_headers = array( $columns, array(), array() );

		$this->items = $this->logs_data();

		$total_items = $this->count_total_items();

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total_items / $this->per_page ),
		) );

	}

}