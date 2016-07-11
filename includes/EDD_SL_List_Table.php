<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EDD_SL_List_Table extends WP_List_Table {

	private $per_page;
	private $active_count;
	private $inactive_count;
	private $expired_count;
	private $disabled_count;

	function __construct() {

		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular' => 'license',
			'plural'   => 'licenses',
			'ajax'     => false
		) );

		$this->per_page = 30;
		$this->active_count   = $this->count_licenses( 'active' );
		$this->inactive_count = $this->count_licenses( 'inactive' );
		$this->expired_count  = $this->count_licenses( 'expired' );
		$this->disabled_count = $this->count_licenses( 'disabled' );
	}


	/**
	 * Output column data
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */


	function column_default( $item, $column_name ) {

		$status = edd_software_licensing()->get_license_status( $item['ID'] );

		switch( $column_name ) {

			case 'status':
				echo '<span class="edd-sl-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
				if ( get_post_status( $item['ID'] ) === 'draft' ) {
					echo ' <em>(' . __( 'disabled', 'edd_sl' ) . ')</em>';
				}
				break;
			case 'key':
				echo esc_html( get_post_meta( $item['ID'], '_edd_sl_key', true ) );
				break;
			case 'user':

				$payment_id  = edd_software_licensing()->get_payment_id( $item['ID'] );
				$customer_id = edd_get_payment_customer_id( $payment_id );
				$customer    = new EDD_Customer( $customer_id );
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' ) . $customer->id ) . '">' . $customer->name . '</a>';
				break;
			case 'count':
				echo esc_html( edd_software_licensing()->get_site_count( $item['ID'] ) );
				break;

			case 'limit':
					// echo esc_html( get_post_meta( $item['download_id'], '_edd_sl_limit', true ) );
					$limit = edd_software_licensing()->get_license_limit( $item['download_id'], $item['ID'] );
					$limit = $limit > 0 ? esc_html( $limit ) : __( 'Unlimited', 'edd_sl' );
					$data  = '';
					if ( $item['is_child_license'] ) {

						$parent_id = wp_get_post_parent_id( $item['ID'] );
						if ( ! empty( $parent_id ) ) {
							$data .= 'data-parent="' . $parent_id . '"';
						}

					}
					echo '<span id="edd-sl-' . $item['ID'] . '-limit" ' . $data . '>' . $limit . '</span>';;

					if ( $item['is_child_license'] ) {
						return;
					}
					echo '<p>';
						echo '<a href="#" class="edd-sl-adjust-limit button-secondary" data-action="increase" data-id="' . absint( $item['ID'] ) . '" data-download="' . absint( $item['download_id'] ) . '">+</a>';
						echo '&nbsp;<a href="#" class="edd-sl-adjust-limit button-secondary" data-action="decrease" data-id="' . absint( $item['ID'] ) . '" data-download="' . absint( $item['download_id'] ) . '">-</a>';
					echo '</p>';

				break;

			case 'expires':

				if ( $item['is_child_license'] ) {
					return;
				}

				if ( edd_software_licensing()->is_lifetime_license( $item['ID'] ) ) {
					_e( 'Lifetime', 'edd_sl' );
				} else {
					if( 'expired' == $status ) {
						echo '<span class="edd-sl-expired">';
					}

					$expiration = get_post_meta( $item['ID'], '_edd_sl_expiration', true );

					if( $expiration ) {
						echo esc_html( date_i18n( get_option( 'date_format' ), $expiration ) );
					}

					if( 'expired' == $status ) {
						echo '</span>';
					}

				}

				$edit_url = admin_url( 'edit.php?post_type=download&page=edd-licenses&action=edit_exp&license_id=' . $item['ID'] );
				echo '&nbsp;&ndash;&nbsp;<a href="' . esc_attr( $edit_url ) . '">' . __( 'Edit', 'edd_sl' ) . '</a>';

				break;

			case 'purchased':

				$payment_id = get_post_meta( $item['ID'], '_edd_sl_payment_id', true );
				echo esc_html( get_the_time( get_option( 'date_format' ), $payment_id ) );

				if ( $payment_id && ! $item['is_child_license'] ) {
					$payment_url = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $payment_id );
					echo '&nbsp;&ndash;&nbsp;<a href="' . esc_attr( $payment_url ) . '">' . __( 'View payment', 'edd_sl' ) . '</a>';
				}

				break;
		}

		do_action( 'edd_sl_column_' . $column_name, $item );

	}


	/**
	 * Output the title column
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */

	function column_title( $item ) {

		//Build row actions
		$actions = array();
		$base    = wp_nonce_url( admin_url( 'edit.php?post_type=download&page=edd-licenses' ), 'edd_sl_key_nonce' );
		$license = get_post( $item['ID'] );
		$status  = edd_software_licensing()->get_license_status( $item['ID'] );

		$title = get_the_title( $item['ID'] );

		if ( $item['is_child_license'] ) {
			// Don't show email after download name for child licenses.
			$download_name = trim( substr( $title, 0, strrpos( $title, ' &#8211; ' ) ) );
			$title         = '&#8212; ' . $download_name;
		}

		if ( ! $item['is_child_license'] ) {
			if ( $status === 'active' || ( edd_is_bundled_product( $item['ID'] ) && $status !== 'expired' ) ) {
				$actions['deactivate'] = sprintf(
					'<a href="%s&action=%s&license=%s">' . __( 'Deactivate', 'edd_sl' ) . '</a>',
					$base,
					'deactivate',
					$item['ID']
				 );
				$actions['renew'] = sprintf( '<a href="%s&action=%s&license=%s" title="' . __( 'Extend this license key\'s expiration date', 'edd_sl' ) . '">' . __( 'Extend', 'edd_sl' ) . '</a>', $base, 'renew', $item['ID'] );
			} elseif( $status == 'expired' ) {
				$actions['renew'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Renew', 'edd_sl' ) . '</a>', $base, 'renew', $item['ID'] );
			} else {
				$actions['activate'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Activate', 'edd_sl' ) . '</a>', $base, 'activate', $item['ID'] );
			}

			if( 'draft' == $license->post_status ) {
				$actions['enable'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Enable', 'edd_sl' ) . '</a>', $base, 'enable', $item['ID'] );
			} elseif( 'publish' == $license->post_status ) {
				$actions['disable'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Disable', 'edd_sl' ) . '</a>', $base, 'disable', $item['ID'] );
			}
		}

		$actions['view_log'] = sprintf( '<a href="#TB_inline?width=640&inlineId=license_log_%s" class="thickbox" data-license-id="'. esc_attr( $item['ID'] ) .'" title="' . __( 'License Log', 'edd_sl' ) . '">' . __( 'View Log', 'edd_sl' ) . '</a>', $item['ID'] );

		if( ! edd_software_licensing()->force_increase() ) {
			$actions['manage_sites'] = sprintf( '<a href="%s&action=%s&license_id=%s">' . __( 'Manage Sites', 'edd_sl' ) . '</a>', $base, 'manage_sites', $item['ID'] );
		}

		$actions['delete'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Delete', 'edd_sl' ) . '</a>', $base, 'delete',$item['ID'] );

		// Filter the existing actions and include the license object.
		$actions = apply_filters( 'edd_sl_row_actions', $actions, $license );

		$log_html = '<div id="license_log_'. esc_attr( $item['ID'] ) .'" style="display: none;"><p>' . __( 'Loading license log..', 'edd_sl' ) . '</p></div>';

		// Return the title contents
		return esc_html( $title ) . $this->row_actions( $actions ) . $log_html;
	}

	/**
	 * Output the checkbox column
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */

	function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item['ID'] )
		);

	}


	/**
	 * Setup columns
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_columns() {

		$columns = array(
			'cb'        => '<input type="checkbox"/>',
			'title'     => __( 'Name', 'edd_sl' ),
			'status'    => __( 'Status', 'edd_sl' ),
			'key'       => __( 'Key', 'edd_sl' ),
			'user'      => __( 'Customer', 'edd_sl' ),
			'count'     => edd_software_licensing()->force_increase() ? __( 'Activation Count', 'edd_sl' ) : __( 'Site Count', 'edd_sl' ),
			'limit'     => __( 'Activation Limit', 'edd_sl' ),
			'expires'   => __( 'Expires', 'edd_sl' ),
			'purchased' => __( 'Purchased', 'edd_sl' )
		);

		return $columns;
	}

	/**
	 * Retrieve the table's sortable columns
	 *
	 * @access public
	 * @since 2.1.2
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'expires'   => array( 'expires', false ),
			'purchased' => array( 'purchased', false )
		);
	}

	/**
	 * Setup available views
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_views() {

		$base = admin_url( 'edit.php?post_type=download&page=edd-licenses' );
		$current = isset( $_GET['view'] ) ? $_GET['view'] : '';

		$link_html = '<a href="%s"%s>%s</a>(%s)';

		$views = array(
			'all'      => sprintf( $link_html,
				esc_url( remove_query_arg( 'view', $base ) ),
				$current === 'all' || $current == '' ? ' class="current"' : '',
				esc_html__( 'All', 'edd_sl' ),
				$this->get_total_licenses()
			),
			'active'   => sprintf( $link_html,
				esc_url( add_query_arg( 'view', 'active', $base ) ),
				$current === 'active' ? ' class="current"' : '',
				esc_html__( 'Active', 'edd_sl' ),
				$this->active_count
			),
			'inactive' => sprintf( $link_html,
				esc_url( add_query_arg( 'view', 'inactive', $base ) ),
				$current === 'inactive' ? ' class="current"' : '',
				esc_html__( 'Inactive', 'edd_sl' ),
				$this->inactive_count
			),
			'expired'  => sprintf( $link_html,
				esc_url( add_query_arg( 'view', 'expired', $base ) ),
				$current === 'expired' ? ' class="current"' : '',
				esc_html__( 'Expired', 'edd_sl' ),
				$this->expired_count
			),
			'disabled'  => sprintf( $link_html,
				esc_url( add_query_arg( 'view', 'disabled', $base ) ),
				$current === 'disabled' ? ' class="current"' : '',
				esc_html__( 'Disabled', 'edd_sl' ),
				$this->disabled_count
			)
		);

		return $views;

	}


	/**
	 * Retrieve the current page number
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */

	function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}


	/**
	 * Retrieve the total number of licenses
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */

	function get_total_licenses() {
		$args = array(
			'post_type'   => 'edd_license',
			'fields'      => 'ids',
			'nopaging'    => true,
			'post_parent' => 0
		);

		$query = new WP_Query( $args );

		if( $query->have_posts() ) {
			return $query->post_count;
		}

		return 0;
	}


	/**
	 * Retrieve the count of licenses by status
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */

	function count_licenses( $status = 'active' ) {
		$args = array(
			'post_type'   => 'edd_license',
			'fields'      => 'ids',
			'nopaging'    => true,
			'post_parent' => 0
		);

		if( 'disabled' == $status ) {
			$args['post_status'] = 'draft';
		} else {
			$args['meta_key']  = '_edd_sl_status';
			$args['meta_value'] = $status;
		}

		$query = new WP_Query( $args );

		if( $query->have_posts() ) {
			return $query->post_count;
		}

		return 0;
	}


	/**
	 * Setup available bulk actions
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_bulk_actions() {

		$actions = array(
			'deactivate'     => __( 'Deactivate', 'edd_sl' ),
			'activate'       => __( 'Activate', 'edd_sl' ),
			'enable'         => __( 'Enable', 'edd_sl' ),
			'renewal_notice' => __( 'Send Renewal Notice', 'edd_sl' ),
			'renew'          => __( 'Renew', 'edd_sl' ),
			'delete'         => __( 'Delete', 'edd_sl' )
		);

		return $actions;

	}


	/**
	 * Process bulk actions
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */
	function process_bulk_action() {

		if( empty( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		if( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-licenses' ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'edd_sl_key_nonce' ) ) {
			return;
		}

		$ids = isset( $_GET['license'] ) ? $_GET['license'] : false;

		if( ! $ids ) {
			return;
		}

		if ( ! is_array( $ids ) ) {
			$ids = array( $ids );
		}

		foreach ( $ids as $id ) {
			// Detect when a bulk action is being triggered...

			if ( 'deactivate' === $this->current_action() ) {
				edd_software_licensing()->set_license_status( $id, 'inactive' );
			}

			if ( 'activate' === $this->current_action() ) {
				edd_software_licensing()->set_license_status( $id, 'active' );
			}

			if ( 'enable' === $this->current_action() ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			}

			if ( 'disable' === $this->current_action() ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			}

			if ( 'renew' === $this->current_action() ) {
				$license = get_post( $id );
				// Don't bulk renew child licenses
				if ( empty( $license->post_parent ) ) {
					edd_software_licensing()->renew_license( $id );
				}
			}

			if ( 'renewal_notice' === $this->current_action() ) {
				$license = get_post( $id );

				if ( empty( $license->post_parent ) ) {

					$emails = new EDD_SL_Emails;

					if( 'expired' == edd_software_licensing()->get_license_status( $id ) ) {

						$notices        = edd_sl_get_renewal_notices();
						$send_notice_id = 0;

						foreach( $notices as $notice_id => $notice ) {

							if( 'expired' === $notice['send_period'] ) {
								$send_notice_id = $notice_id;
								break;

							}

						}

						$emails->send_renewal_reminder( $id, $send_notice_id );

					} else {

						$emails->send_renewal_reminder( $id );

					}

				}

			}

			if ( 'delete' === $this->current_action() ) {
				wp_delete_post( $id );
			}
		}

		set_transient( '_edd_sl_bulk_actions_redirect', 1, 1000 );

	}


	/**
	 * Query database for license data and prepare it for the table
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */
	function licenses_data() {

		$licenses_data = array();

		$license_args = array(
			'post_type'      => 'edd_license',
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'posts_per_page' => $this->per_page,
			'paged'          => $this->get_paged(),
			'meta_query'     => array( 'relation' => 'AND' ),
			'post_parent'    => 0
		);

		$view = isset( $_GET['view'] ) ? $_GET['view'] : false;

		if ( $view && 'disabled' == $view ) {
			$license_args['post_status'] = 'draft';
		} elseif( $view ) {
			$license_args['meta_query'][] = array(
				'key'   => '_edd_sl_status',
				'value' => $view
			);
		}

		$key_search = false;

		// check to see if we are searching
		if( ! empty( $_GET['s'] ) ) {

			$search = trim( $_GET['s'] );

			if( ! is_email( $search ) ) {

				$has_period = strstr( $search, '.' );

				if( false === $has_period && ! preg_match( '/\s/', $search ) ) {
					// Search in the license key.
					$license_args['meta_query'][] = array(
						'key'   => '_edd_sl_key',
						'value' => $search
					);

					$key_search = true;
					unset( $license_args['post_parent'] );


				} elseif( $has_period ) {

					// Search in the sites that are registered.
					$license_args['meta_query'][] = array(
						'key'   => '_edd_sl_sites',
						'value' => edd_software_licensing()->clean_site_url( $search ),
						'compare' => 'LIKE'
					);

				} else {

					$license_args['s'] = $search;

				}

			} else {

				$license_args['s'] = $search;

			}

		}

		$orderby        = isset( $_GET['orderby'] )  ? $_GET['orderby'] : 'ID';
		$order          = isset( $_GET['order'] )    ? $_GET['order']   : 'DESC';
		$order_inverse  = $order == 'DESC'           ? 'ASC'            : 'DESC';

		$license_args['order'] = $order;

		switch( $orderby ) {

			case 'purchased' :
				$license_args['orderby'] = 'date';
				break;

			case 'expires' :
				$license_args['orderby']  = 'meta_value_num';
				$license_args['meta_key'] = '_edd_sl_expiration';
				break;
		}

		$licenses = get_posts( $license_args );

		// If searching by Key
		if ( $key_search ) {

			$found_license = $licenses[0];

			// And we found a child license
			if ( ! empty( $found_license->post_parent ) ) {

				// Swap out the meta query for the parent license to show the entire bundle
				$parent_license_key = get_post_meta( $found_license->post_parent, '_edd_sl_key', true );

				foreach ( $license_args['meta_query'] as $key => $args ) {

					if ( ! empty( $args['key'] ) && '_edd_sl_key' === $args['key'] ) {
						$license_args['meta_query'][$key]['value'] = $parent_license_key;
					}

				}

				$licenses = get_posts( $license_args );

			}

		}

		if ( $licenses ) {
			foreach ( $licenses as $license ) {

				$status      = get_post_meta( $license->ID, '_edd_sl_status', true );
				$key         = get_post_meta( $license->ID, '_edd_sl_key', true );
				$user        = get_post_meta( $license->ID, '_edd_sl_user_id', true );
				$expires     = date_i18n( get_option( 'date_format' ), (int) get_post_meta( $license->ID, '_edd_sl_expiration', true ) );
				$purchased   = get_the_time( get_option( 'date_format' ), $license->ID );
				$download_id = get_post_meta( $license->ID, '_edd_sl_download_id', true );

				$licenses_data[] = array(
					'ID'               => $license->ID,
					'title'            => get_the_title( $license->ID ),
					'status'           => $status,
					'key'              => $key,
					'user'             => $user,
					'expires'          => $expires,
					'purchased'        => $purchased,
					'download_id'      => $download_id,
					'is_child_license' => false
				);

				if ( edd_is_bundled_product( $download_id ) ) {
					$child_args = array(
						'post_type'      => 'edd_license',
						'post_status'    => array( 'publish', 'future' ),
						'posts_per_page' => -1,
						'post_parent'    => $license->ID
					);

					$child_licenses = get_children( $child_args );
				} else {
					$child_licenses = false;
				}

				if ( ! empty( $child_licenses ) ) {

					foreach ( $child_licenses as $child_license ) {

						$child_license_status = get_post_meta( $child_license->ID, '_edd_sl_status', true );
						if ( ! empty( $_GET['view'] ) && $child_license_status !== $_GET['view'] ) {
							continue;
						}

						$licenses_data[] = array(
							'ID'               => $child_license->ID,
							'title'            => get_the_title( $child_license->ID ),
							'status'           => $status,
							'key'              => $key,
							'user'             => $user,
							'expires'          => $expires,
							'purchased'        => $purchased,
							'download_id'      => get_post_meta( $child_license->ID, '_edd_sl_download_id', true ),
							'is_child_license' => true
						);

					}

				}
			}
		}

		return $licenses_data;

	}

	/** ************************************************************************
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = $this->per_page;

		add_thickbox();

		$columns = $this->get_columns();
		$hidden  = array(); // no hidden columns

		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable, 'title' );

		$this->process_bulk_action();

		$current_page = $this->get_pagenum();

		if( isset( $_GET['view'] ) ) {
			$total_items = $this->count_licenses( $_GET['view'] );
		} else {
			$total_items = $this->get_total_licenses();
		}

		$this->items = $this->licenses_data();

		$pagination_args = array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		);

		$this->set_pagination_args( $pagination_args );

	}

}
