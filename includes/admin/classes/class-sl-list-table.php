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
		$license = edd_software_licensing()->get_license( $item['ID'] );

		switch( $column_name ) {

			case 'key':
				echo esc_html( get_post_meta( $item['ID'], '_edd_sl_key', true ) );
				echo '&nbsp;&ndash;&nbsp;<span class="edd-sl-' . esc_attr( $license->status ) . '">' . esc_html( $license->status ) . '</span>';
				if ( get_post_status( $item['ID'] ) === 'draft' ) {
					echo ' <em>(' . __( 'disabled', 'edd_sl' ) . ')</em>';
				}
				break;
			case 'user':

				$customer = new EDD_Customer( $license->customer_id );
				$name     = empty( $customer->name ) ? $customer->email : $customer->name;
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' ) . $customer->id ) . '">' . $name . '</a>';
				break;

			case 'limit':
					$limit = $license->activation_limit > 0 ? esc_html( $license->activation_limit ) : __( 'Unlimited', 'edd_sl' );
					$data  = '';

					if ( ! empty( $license->parent ) ) {
						$data .= 'data-parent="' . $license->parent . '"';
					}

					echo '<span id="edd-sl-' . $item['ID'] . '-active">' . esc_html( $license->activation_count ) . '</span> / ';
					echo '<span id="edd-sl-' . $item['ID'] . '-limit" ' . $data . '>' . $limit . '</span>';

					if ( ! empty( $license->parent ) ) {
						return;
					}

					echo '<p>';
						echo '<a href="#" class="edd-sl-adjust-limit button-secondary" data-action="increase" data-id="' . absint( $item['ID'] ) . '" data-download="' . absint( $license->download_id ) . '">+</a>';
						echo '&nbsp;<a href="#" class="edd-sl-adjust-limit button-secondary" data-action="decrease" data-id="' . absint( $item['ID'] ) . '" data-download="' . absint( $license->download_id ) . '">-</a>';
					echo '</p>';

				break;

			case 'expires':

				if ( $license->is_lifetime ) {
					_e( 'Lifetime', 'edd_sl' );
				} else {
					if( 'expired' == $license->status ) {
						echo '<span class="edd-sl-expired">';
					}

					if( $license->expiration ) {
						echo esc_html( date_i18n( get_option( 'date_format' ), $license->expiration, true ) );
					}

					if( 'expired' == $license->status ) {
						echo '</span>';
					}

				}

				break;

			case 'purchased':

				$purchased = esc_html( get_the_time( get_option( 'date_format' ), $license->payment_id ) );

				if ( $license->payment_id && empty( $license->parent ) ) {
					$payment_url = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $license->payment_id );
					$purchased = '<a href="' . esc_attr( $payment_url ) . '">' . $purchased . '</a>';
				}

				echo $purchased;
				break;

			case 'actions':
				$base_url = admin_url( 'edit.php?post_type=download&page=edd-licenses' );

				echo '<a href="' . add_query_arg( array( 'view' => 'overview', 'license' => $item['ID'] ), $base_url ) . '">' . __( 'View', 'edd_sl' ) . '</a>';
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
		$base    = admin_url( 'edit.php?post_type=download&page=edd-licenses' );

		if( ! empty( $_GET['s'] ) ) {
			$base = add_query_arg( 's', $_GET['s'], $base );
		}

		$base    = wp_nonce_url( $base, 'edd_sl_key_nonce' );
		$license = edd_software_licensing()->get_license( $item['ID'] );

		$title = $item['title'];

		if ( ! empty( $license->parent ) ) {
			// Indent child licenses
			$title = '&#8212; ' . $title;
		}

		if ( empty( $license->parent ) ) {
			if ( $license->status === 'active' || ( $license->download->is_bundled_download() && $license->status !== 'expired' ) ) {
				$actions['deactivate'] = sprintf(
					'<a href="%s&action=%s&license=%s">' . __( 'Deactivate', 'edd_sl' ) . '</a>',
					$base,
					'deactivate',
					$item['ID']
				 );
				$actions['renew'] = sprintf( '<a href="%s&action=%s&license=%s" title="' . __( 'Extend this license key\'s expiration date', 'edd_sl' ) . '">' . __( 'Extend', 'edd_sl' ) . '</a>', $base, 'renew', $item['ID'] );
			} elseif( $license->status == 'expired' ) {
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

		$actions['view_log'] = sprintf( '<a href="%s&view=%s&license=%s">' . __( 'View Log', 'edd_sl' ) . '</a>', $base, 'logs', $item['ID'] );

		if( ! edd_software_licensing()->force_increase() ) {
			$actions['manage_sites'] = sprintf( '<a href="%s&view=overview&license=%s#edd-item-tables-wrapper">' . __( 'Manage Sites', 'edd_sl' ) . '</a>', $base, $item['ID'] );
		}

		$actions['delete'] = sprintf( '<a href="%s&view=%s&license=%s">' . __( 'Delete', 'edd_sl' ) . '</a>', $base, 'delete', $item['ID'] );

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
			'title'     => __( 'Product', 'edd_sl' ),
			'key'       => __( 'Key', 'edd_sl' ),
			'user'      => __( 'Customer', 'edd_sl' ),
			'limit'     => __( 'Activation Limit', 'edd_sl' ),
			'expires'   => __( 'Expires', 'edd_sl' ),
			'purchased' => __( 'Purchased', 'edd_sl' ),
			'actions'   => __( 'Actions', 'edd_sl' )
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

		$args = $this->build_search_args( $args );

		$query = new WP_Query( $args['args'] );

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

		$args = $this->build_search_args( $args );

		$query = new WP_Query( $args['args'] );

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
			'disable'        => __( 'Disable', 'edd_sl' ),
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
			$license = edd_software_licensing()->get_license( $id );

			// No license found, move along.
			if ( false === $license ) {
				continue;
			}

			if ( 'deactivate' === $this->current_action() ) {
				$license->status = 'inactive';
			}

			if ( 'activate' === $this->current_action() ) {
				$license->status = 'active';
			}

			if ( 'enable' === $this->current_action() ) {
				$license->enable();
			}

			if ( 'disable' === $this->current_action() ) {
				$license->disable();
			}

			if ( 'renew' === $this->current_action() ) {
				if ( empty( $license->parent ) ) {
					$license->renew();
				}
			}

			if ( 'renewal_notice' === $this->current_action() ) {

				if ( empty( $license->parent ) ) {

					$emails = new EDD_SL_Emails;

					if( 'expired' == $license->status ) {

						$notices        = edd_sl_get_renewal_notices();
						$send_notice_id = 0;

						foreach( $notices as $notice_id => $notice ) {

							if( 'expired' === $notice['send_period'] ) {
								$send_notice_id = $notice_id;
								break;

							}

						}

						$emails->send_renewal_reminder( $license->ID, $send_notice_id );

					} else {

						$emails->send_renewal_reminder( $license->ID );

					}

				}

			}

			if ( 'delete' === $this->current_action() ) {
				wp_delete_post( $license->ID );
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

		$license_args = $this->build_search_args( $license_args );
		$license_args = $license_args['args'];
		$key_search   = isset( $license_args['key_search'] ) ? true : false;

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
		if ( $key_search && ! empty( $licenses ) ) {

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

				$license = edd_software_licensing()->get_license( $license->ID );

				$licenses_data[] = array(
					'ID'               => $license->ID,
					'title'            => $license->get_name( false ),
					'status'           => $license->status,
					'key'              => $license->key,
					'user'             => $license->user_id,
					'expires'          => $license->expiration,
					'purchased'        => get_the_time( get_option( 'date_format' ), $license->payment_id ),
					'download_id'      => $license->download_id,
					'is_child_license' => false
				);

				$child_licenses = $license->get_child_licenses();

				if ( ! empty( $child_licenses ) ) {

					foreach ( $child_licenses as $child_license ) {

						if ( ! empty( $_GET['view'] ) && $child_license->status !== $_GET['view'] ) {
							continue;
						}

						$licenses_data[] = array(
							'ID'               => $child_license->ID,
							'title'            => $child_license->get_name( false ),
							'status'           => $child_license->status,
							'key'              => $child_license->key,
							'user'             => $child_license->user_id,
							'expires'          => $child_license->expiration,
							'purchased'        => get_the_time( get_option( 'date_format' ), $license->payment_id ),
							'download_id'      => $child_license->download_id,
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


	/**
	 * Build the args array for search and count comment_form_default_fields*
	 *
	 * @since 3.5
	 * @param array $args The existing args
	 * @return array $args The updated args
	 */
	function build_search_args( $args ) {
		$key_search = false;

		// check to see if we are searching
		if( ! empty( $_GET['s'] ) ) {

			$search = trim( $_GET['s'] );

			if( ! is_email( $search ) ) {

				$has_period = strstr( $search, '.' );

				if( strpos( $search, 'download:' ) !== false ) {

					// Search in the download ID key
					$args['meta_query'][] = array(
						'key'   => '_edd_sl_download_id',
						'value' => trim( str_replace( 'download:', '', $search ) )
					);

				} elseif( false === $has_period && ! preg_match( '/\s/', $search ) ) {
					// Search in the license key.
					$args['meta_query'][] = array(
						'key'   => '_edd_sl_key',
						'value' => $search
					);

					$key_search = true;
					unset( $args['post_parent'] );

				} elseif( $has_period ) {

					// Search in the sites that are registered.
					$args['meta_query'][] = array(
						'key'   => '_edd_sl_sites',
						'value' => edd_software_licensing()->clean_site_url( $search ),
						'compare' => 'LIKE'
					);

				} else {

					$args['s'] = $search;

				}

			} else {

				$args['s'] = $search;

			}

		}

		if( ! empty( $args['meta_query'] ) ) {
			$args['meta_query']['relation'] = 'AND';
		}

		$args['args']       = $args;
		$args['key_search'] = $key_search;

		return $args;
	}

}
