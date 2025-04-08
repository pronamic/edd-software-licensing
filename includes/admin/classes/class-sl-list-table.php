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
	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {

			case 'key':
				$title = $item['key'];
				if ( current_user_can( 'manage_licenses' ) ) {
					$title = sprintf(
						'<a href="%s">%s</a>',
						add_query_arg(
							array(
								'view' => 'overview',
							),
							$this->get_license_row_base_url( $item['ID'] )
						),
						$title
					);
				}
				echo $title;
				echo '&nbsp;&ndash;&nbsp;<span class="edd-sl-' . esc_attr( $item['status'] ) . '">' . esc_html( $item['status'] ) . '</span>';
				if ( 'disabled' === $item['status'] ) {
					echo ' <em>(' . esc_html__( 'disabled', 'edd_sl' ) . ')</em>';
				}
				echo $this->row_actions( $this->get_license_row_actions( $item ) );
				break;

			case 'user':
				$customer      = new EDD_Customer( $item['customer_id'] );
				$name          = empty( $customer->name ) ? $customer->email : $customer->name;
				$query_args    = array(
					'post_type' => 'download',
					'page'      => 'edd-customers',
					'view'      => 'overview',
					'id'        => urlencode( $customer->id ),
				);
				$customer_link = add_query_arg(
					$query_args,
					admin_url( 'edit.php' )
				);
				echo '<a href="' . esc_url( $customer_link ) . '">' . esc_html( $name ) . '</a>';
				echo '<br />';
				$query_args['view'] = 'licenses';
				$licenses_link      = add_query_arg(
					$query_args,
					admin_url( 'edit.php' )
				);
				$licenses_link     .= '#edd_general_licenses';
				echo '<a href="' . esc_url( $licenses_link ) . '">' . esc_html__( 'View other licenses', 'edd_sl' ) . '</a>';
				break;

			case 'limit':
				$limit = $item['activation_limit'] > 0 ? intval( $item['activation_limit'] ) : __( 'Unlimited', 'edd_sl' );
				echo esc_html( $item['activation_count'] ) . ' / ' . esc_html( $limit );
				break;

			case 'expires':
				if ( $item['is_lifetime'] ) {
					esc_html_e( 'Lifetime', 'edd_sl' );
				} else {
					if ( 'expired' === $item['status'] ) {
						echo '<span class="edd-sl-expired">';
					}

					if ( $item['expires'] ) {
						echo esc_html( date_i18n( get_option( 'date_format' ), $item['expires'], true ) );
					}

					if ( 'expired' === $item['status'] ) {
						echo '</span>';
					}

				}

				break;

			case 'purchased':
				$purchased = __( 'Order', 'edd_sl' ) . ' ' . $item['order_number'] . '<br />' . $item['purchased'];

				if ( $item['payment_id'] && empty( $item['parent'] ) ) {
					$payment_url = add_query_arg(
						array(
							'post_type' => 'download',
							'page'      => 'edd-payment-history',
							'view'      => 'view-order-details',
							'id'        => (int) $item['payment_id'],
						),
						admin_url( 'edit.php' )
					);
					$purchased   = '<a href="' . esc_url( $payment_url ) . '">' . $purchased . '</a>';
				}

				echo $purchased;
				break;
		}

		/**
		 * Fires at the end of the cell output. The action name is dynamically
		 * generated with the name of the column. Example: `edd_sl_column_purchased`.
		 *
		 * @param array  $item        The array of license data
		 */
		do_action( 'edd_sl_column_' . $column_name, $item );

		if ( 'key' === $column_name && has_action( 'edd_sl_column_actions' ) ) {
			if ( function_exists( 'edd_do_action_deprecated' ) ) {
				edd_do_action_deprecated( 'edd_sl_column_actions', array( $item ), '3.7', 'edd_sl_column_key' );
			} else {
				do_action_deprecated( 'edd_sl_column_actions', array( $item ), '3.7', 'edd_sl_column_key' );
			}
		}

		if ( 'expires' === $column_name && has_action( 'edd_sl_column_limit' ) ) {
			if ( function_exists( 'edd_do_action_deprecated' ) ) {
				edd_do_action_deprecated( 'edd_sl_column_limit', array( $item ), '3.7', 'edd_sl_column_expires' );
			} else {
				do_action_deprecated( 'edd_sl_column_limit', array( $item ), '3.7', 'edd_sl_column_expires' );
			}
		}
	}


	/**
	 * Output the title column
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */
	public function column_title( $item ) {
		$has_children = $this->is_main_view() && $item['children'];
		$title        = $item['title'];

		if ( ! empty( $item['parent'] ) ) {
			// Indent child licenses
			$title = '&#8212; ' . $title;
		}

		$title = esc_html( $title );
		if ( $has_children ) {
			$link   = add_query_arg(
				array(
					'post_type' => 'download',
					'page'      => 'edd-licenses',
					's'         => $item['key'],
				),
				admin_url( 'edit.php' )
			);
			$title .= sprintf(
				'<br />&#8212; <a href="%s">%s</a>',
				esc_url( $link ),
				_n(
					'View child license',
					'View child licenses',
					count( $item['children'] ),
					'edd_sl'
				)
			);
		}

		// Return the title contents
		return $title;
	}

	/**
	 * Output the checkbox column
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input id="select-%2$s" type="checkbox" name="%1$s_id[]" value="%2$s" /><label for="select-%2$s" class="screen-reader-text">%3$s</label>',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item['ID'] ),
			/* translators: the license key */
			sprintf( __( 'Select License Key %s', 'edd_sl' ), $item['key'] )
		);
	}

	/**
	 * Setup columns
	 *
	 * @access      public
	 * @since       1.0
	 * @return      array
	 */
	public function get_columns() {
		return apply_filters(
			'eddsl_get_admin_columns',
			array(
				'cb'        => '<input type="checkbox"/>',
				'key'       => __( 'Key', 'edd_sl' ),
				'title'     => __( 'Product', 'edd_sl' ),
				'user'      => __( 'Customer', 'edd_sl' ),
				'limit'     => __( 'Activations', 'edd_sl' ),
				'expires'   => __( 'Expires', 'edd_sl' ),
				'purchased' => __( 'Purchased', 'edd_sl' ),
			)
		);
	}

	/**
	 * Retrieve the table's sortable columns
	 *
	 * @access public
	 * @since 2.1.2
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		return apply_filters(
			'eddsl_get_sortable_admin_columns',
			array(
				'expires'   => array( 'expires', false ),
				'purchased' => array( 'purchased', false ),
			)
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
		return edd_software_licensing()->licenses_db->count( array( 'parent' => 0 ) );
	}


	/**
	 * Retrieve the count of licenses by status
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */
	private function count_licenses( $status = 'active' ) {
		$defaults = array(
			'status' => $status,
		);

		$args = $this->build_search_args( $defaults );
		return edd_software_licensing()->licenses_db->count( $args );
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

		if ( empty( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-licenses' ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'edd_sl_key_nonce' ) ) {
			return;
		}

		$ids = isset( $_GET['license_id'] ) ? $_GET['license_id'] : false;
		if ( ! $ids ) {
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

			switch ( $this->current_action() ) {
				case 'enable':
					$license->enable();
					break;

				case 'disable':
					$license->disable();
					break;

				case 'renew':
					if ( empty( $license->parent ) ) {
						$license->renew();
					}
					break;

				case 'renewal_notice':
					if ( empty( $license->parent ) ) {
						$emails = new EDD_SL_Emails();

						if ( 'expired' === $license->status ) {
							$notice = edd_software_licensing()->notices->get_notice_for_period( 'expired' );
							if ( ! empty( $notice ) ) {
								$emails->send_renewal_notice(
									$this->ID,
									edd_software_licensing()->notices->get_notice_id( $notice )
								);
							}
						} else {
							$emails->send_renewal_reminder( $license->ID );
						}
					}
					break;

				case 'delete':
					$license->delete();
					break;

				default:
					break;
			}
		}

		set_transient( '_edd_sl_bulk_actions_redirect', 1, 1000 );
	}

	/**
	 * Get the actions for the license key.
	 *
	 * @param $license array
	 * @return array
	 */
	private function get_license_row_actions( $license ) {
		$actions = array();
		$base    = $this->get_license_row_base_url( $license['ID'] );
		if ( current_user_can( 'manage_licenses' ) ) {
			if ( empty( $license['parent'] ) ) {
				$actions['edit'] = array(
					'label' => __( 'Manage', 'edd_sl' ),
					'link'  => add_query_arg(
						array(
							'view' => 'overview',
						),
						$base
					),
				);
				if ( 'disabled' !== $license['status'] ) {
					if ( 'expired' !== $license['status'] ) {
						$actions['renew'] = array(
							'label' => __( 'Extend', 'edd_sl' ),
							'link'  => add_query_arg(
								array(
									'action' => 'renew',
								),
								$base
							),
						);
					} else {
						$actions['renew'] = array(
							'label' => __( 'Renew', 'edd_sl' ),
							'link'  => add_query_arg(
								array(
									'action' => 'renew',
								),
								$base
							),
						);
					}
				}

				if ( 'disabled' === $license['status'] ) {
					$actions['enable'] = array(
						'label' => __( 'Enable', 'edd_sl' ),
						'link'  => add_query_arg(
							array(
								'action' => 'enable',
							),
							$base
						),
					);
				} else {
					$actions['disable'] = array(
						'label' => __( 'Disable', 'edd_sl' ),
						'link'  => add_query_arg(
							array(
								'action' => 'disable',
							),
							$base
						),
					);
				}
			}
		}

		if ( current_user_can( 'delete_licenses' ) ) {
			$actions['delete'] = array(
				'label' => __( 'Delete', 'edd_sl' ),
				'link'  => add_query_arg(
					array(
						'view' => 'delete',
					),
					$base
				),
			);
		}

		foreach ( $actions as $action => $args ) {
			$actions[ $action ] = sprintf( '<a href="%s">%s</a>', esc_url( $args['link'] ), esc_html( $args['label'] ) );
		}

		// Filter the existing actions and include the license object.
		return apply_filters( 'edd_sl_row_actions', $actions, $license );
	}

	/**
	 * Gets the base URL for each license row action/link.
	 *
	 * @param int $license_id
	 * @return string
	 * @since 3.7
	 */
	private function get_license_row_base_url( $license_id ) {
		$base = add_query_arg(
			array(
				'post_type'  => 'download',
				'page'       => 'edd-licenses',
				'license_id' => urlencode( $license_id ),
			),
			admin_url( 'edit.php' )
		);
		if( ! empty( $_GET['s'] ) ) {
			$base = add_query_arg( 's', urlencode( $_GET['s'] ), $base );
		}

		return wp_nonce_url( $base, 'edd_sl_key_nonce' );
	}


	/**
	 * Query database for license data and prepare it for the table
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */
	private function licenses_data() {

		$licenses_data = array();

		$license_args = array(
			'number' => $this->per_page,
			'paged'  => $this->get_paged(),
			'parent' => 0,
		);

		$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : false;

		if ( $view ) {
			$license_args['status'] = $view;
			unset( $license_args['parent'] );
		}

		$license_args = $this->build_search_args( $license_args );

		$orderby        = isset( $_GET['orderby'] )  ? $_GET['orderby'] : 'date_created';
		$order          = isset( $_GET['order'] )    ? $_GET['order']   : 'DESC';

		$license_args['order'] = $order;

		switch( $orderby ) {

			case 'purchased' :
				$license_args['orderby'] = 'date_created';
				break;

			case 'expires' :
				$license_args['orderby']  = 'expiration';
				break;

			default:
				$license_args['orderby'] = $orderby;
				break;

		}

		$licenses = edd_software_licensing()->licenses_db->get_licenses( $license_args );

		if ( ! $licenses ) {
			return $licenses_data;
		}

		foreach ( $licenses as $index => $license ) {
			if ( empty( $license->parent ) ) {
				continue;
			}
			$parent_license = edd_software_licensing()->get_license( $license->parent );
			if ( false !== $parent_license ) {
				array_splice( $licenses, $index, 1, edd_software_licensing()->licenses_db->get_licenses( array( 'id' => $parent_license->ID ) ) );
			}
		}

		if ( ! $licenses ) {
			return $licenses_data;
		}

		$is_main_view = $this->is_main_view();
		foreach ( $licenses as $license ) {

			if ( ! empty( $licenses_data[ $license->ID ] ) ) {
				continue;
			}

			$licenses_data[ $license->ID ] = $this->get_licenses_data_args( $license );

			if ( $is_main_view ) {
				continue;
			}
			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {

				foreach ( $child_licenses as $child_license ) {
					if ( ! is_object( $child_license ) ) {
						continue;
					}

					if ( $view && $view !== $child_license->status ) {
						continue;
					}

					$licenses_data[ $child_license->ID ] = $this->get_licenses_data_args( $child_license, $license );
				}
			}
		}

		return $licenses_data;
	}

	/**
	 * Determine if we are in the main view (not a search or filter by status).
	 *
	 * @return boolean
	 */
	private function is_main_view() {
		$search = ! empty( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$view   = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : false;

		return ! $search && ! $view;
	}

	/**
	 * Get the array of data parameters to add to the $licenses_data array.
	 *
	 * @since 3.6.10
	 * @param object $license
	 * @param boolean|mixed $parent
	 * @return array
	 */
	private function get_licenses_data_args( $license, $parent = false ) {
		$payment_id   = $parent ? $parent->payment_id : $license->payment_id;
		$order_number = $license->payment_id;
		$date         = esc_html__( 'Unknown Date', 'edd_sl' );

		if ( function_exists( 'edd_date_i18n' ) && function_exists( 'edd_get_order' ) ) {
			$order = edd_get_order( $license->payment_id );
			if ( ! empty( $order ) ) {
				$date = array(
					esc_html( edd_date_i18n( $order->date_created ) ),
					esc_html( edd_date_i18n( $order->date_created, 'time' ) . ' ' . edd_get_timezone_abbr() ),
				);
				$date = implode( '<br />', $date );
				if ( ! empty( $order->order_number ) ) {
					$order_number = $order->order_number;
				}
			}
		} else {
			$date = esc_html( get_the_time( get_option( 'date_format' ), $license->payment_id ) );
		}

		return array(
			'ID'               => $license->ID,
			'title'            => $license->get_name( false ),
			'status'           => $license->status,
			'key'              => $license->key,
			'user'             => $license->user_id,
			'expires'          => $license->expiration,
			'purchased'        => $date,
			'payment_id'       => $payment_id,
			'download_id'      => $license->download_id,
			'parent'           => $license->parent,
			'order_number'     => $order_number,
			'is_lifetime'      => $license->is_lifetime,
			'children'         => $license->get_child_licenses(),
			'customer_id'      => $license->customer_id,
			'activation_limit' => $license->activation_limit,
			'activation_count' => $license->activation_count,
		);
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

		$this->_column_headers = array( $columns, $hidden, $sortable, $this->get_primary_column_name() );

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
	 * Updates the primary column to the license key.
	 *
	 * @return string
	 * @since 3.7
	 */
	public function get_primary_column_name() {
		return 'key';
	}


	/**
	 * Build the args array for search and count comment_form_default_fields*
	 *
	 * @since 3.5
	 * @param array $args The existing args
	 * @return array $args The updated args
	 */
	private function build_search_args( $args ) {
		$search = ! empty( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		// check to see if we are searching
		if ( ! $search ) {
			return $args;
		}
		unset( $args['parent'] );

		if ( is_email( $search ) ) {

			$customer = new EDD_Customer( $search );

			if ( $customer && $customer->id > 0 ) {

				$args['customer_id'] = $customer->id;

			} else {
				$args['number'] = 0;
			}
		} else {

			$has_period = strstr( $search, '.' );

			if ( strpos( $search, 'download:' ) !== false ) {

				// Search in the download ID key
				$args['download_id'] = trim( str_replace( 'download:', '', $search ) );

			} elseif ( strlen( $search ) > 6 && false === $has_period && ! preg_match( '/\s/', $search ) ) {

				$license = edd_software_licensing()->get_license( $search );
				if ( ! empty( $license->parent ) ) {
					$license = edd_software_licensing()->get_license( $license->parent );
					$search  = $license->key;
				}
				$args['license_key'] = $search;
				unset( $args['post_parent'] );

			} elseif ( $has_period ) {

				$args['site'] = edd_software_licensing()->clean_site_url( $search );

			} else {

				$args['search'] = $search;

			}
		}

		return $args;
	}
}
