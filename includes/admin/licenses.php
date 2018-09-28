<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Renders the main Licenses admin page
 *
 * @since       1.0
 * @return      void
*/
function edd_sl_licenses_page() {
	$default_views  = edd_sl_license_views();
	$requested_view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'licenses';

	if ( array_key_exists( $requested_view, $default_views ) && is_callable( $default_views[ $requested_view ] ) ) {
		edd_sl_render_license_view( $requested_view, $default_views );
	} else {
		edd_sl_licenses_list();
	}
}


/**
 * Register the views for license management
 *
 * @since  3.5
 * @return array Array of views and their callbacks
 */
function edd_sl_license_views() {
	$views = array();
	return apply_filters( 'edd_sl_license_views', $views );
}


/**
 * Register the tabs for license management
 *
 * @since  3.5
 * @return array Array of tabs for the customer
 */
function edd_sl_license_tabs() {
	$tabs = array();
	return apply_filters( 'edd_sl_license_tabs', $tabs );
}


/**
 * List table of licenses
 *
 * @since  3.5
 * @return void
 */
function edd_sl_licenses_list() {
	?>
	<div class="wrap">

		<div id="icon-edit" class="icon32"><br/></div>
		<h2><?php _e( 'Easy Digital Download Licenses', 'edd_sl' ); ?></h2>
		<?php edd_sl_show_errors(); ?>

		<style>
			.column-status, .column-count { width: 100px; }
			.column-limit { width: 150px; }
		</style>
		<form id="licenses-filter" method="get">
			<input type="hidden" name="post_type" value="download" />
			<input type="hidden" name="page" value="edd-licenses" />
			<?php
			$licenses_table = new EDD_SL_List_Table();
			$licenses_table->prepare_items();
			$licenses_table->search_box( 'search', 'edd_sl_search' );
			$licenses_table->views();
			$licenses_table->display();
			?>
		</form>
	</div>
	<?php

	$redirect = get_transient( '_edd_sl_bulk_actions_redirect' );

	if( false !== $redirect ) : delete_transient( '_edd_sl_bulk_actions_redirect' );
	$redirect = admin_url( 'edit.php?post_type=download&page=edd-licenses' );

	if( ! empty( $_GET['s'] ) ) {
		$redirect = add_query_arg( 's', $_GET['s'], $redirect );
	}
	?>

	<script type="text/javascript">
	window.location = "<?php echo $redirect; ?>";
	</script>
	<?php endif;
}


/**
 * Renders the license view wrapper
 *
 * @since  3.5
 * @param  string $view      The View being requested
 * @param  array $callbacks  The Registered views and their callback functions
 * @return void
 */
function edd_sl_render_license_view( $view, $callbacks ) {
	$render = true;

	if( ! current_user_can( 'view_licenses' ) ) {
		edd_set_error( 'edd-no-access', __( 'You are not permitted to view this data.', 'edd_sl' ) );
		$render = false;
	}

	if ( isset( $_GET['license'] ) ) {
		if ( is_numeric( $_GET['license'] ) ) {
			$new_license_id = edd_software_licensing()->license_meta_db->get_license_id( '_edd_sl_legacy_id', absint( $_GET['license'] ), true );
		}

		if ( empty( $new_license_id ) ) {
			edd_set_error( 'edd-invalid-license', __( 'Invalid license ID provided.', 'edd_sl' ) );
			$render = false;
		}
	}

	if( ( ! isset( $_GET['license_id'] ) || ! is_numeric( $_GET['license_id'] ) ) && empty( $new_license_id ) ) {
		edd_set_error( 'edd-invalid-license', __( 'Invalid license ID provided.', 'edd_sl' ) );
		$render = false;
	}

	$license_id  = isset( $_GET['license_id'] ) ? absint( $_GET['license_id'] ) : $new_license_id;
	$license     = edd_software_licensing()->get_license( $license_id );

	if( empty( $license->key ) ) {
		edd_set_error( 'edd-invalid-license', __( 'Invalid license ID provided.', 'edd_sl' ) );
		$render = false;
	}

	$license_tabs = edd_sl_license_tabs();
	?>
	<div class="wrap">
		<h2><?php _e( 'Manage License Details', 'edd_sl' ); ?></h2>
		<?php if( edd_get_errors() ) : ?>
			<div class="error settings-error">
				<?php edd_print_errors(); ?>
			</div>
		<?php endif; ?>

		<?php if( $license->key && $render ) : ?>

			<div id="edd-item-wrapper" class="edd-item-has-tabs edd-clearfix">
				<div id="edd-item-tab-wrapper" class="license-tab-wrapper">
					<ul id="edd-item-tab-wrapper-list" class="license-tab-wrapper-list">
						<?php foreach ( $license_tabs as $key => $tab ) : ?>
							<?php $active = $key === $view ? true : false; ?>
							<?php $class  = $active ? 'active' : 'inactive'; ?>

							<li class="<?php echo sanitize_html_class( $class ); ?>">

								<?php
								// edd item tab full title
								$tab_title = sprintf( _x( 'License %s', 'License Details page tab title', 'edd_sl' ), esc_attr( $tab[ 'title' ] ) );

								// aria-label output
								$aria_label = ' aria-label="' . $tab_title . '"';
								?>

								<?php if ( ! $active ) : ?>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-licenses&view=' . $key . '&license_id=' . $license->ID ) ); ?>"<?php echo $aria_label; ?>>
								<?php endif; ?>

									<span class="edd-item-tab-label-wrap"<?php echo $active ? $aria_label : ''; ?>>
										<span class="dashicons <?php echo sanitize_html_class( $tab['dashicon'] ); ?>" aria-hidden="true"></span>
										<?php
										if ( version_compare( EDD_VERSION, 2.7, '>=' ) ) {
											echo '<span class="edd-item-tab-label">' . esc_attr( $tab['title'] ) . '</span>';
										}
										?>
									</span>

								<?php if ( ! $active ) : ?>
									</a>
								<?php endif; ?>

							</li>

						<?php endforeach; ?>
					</ul>
				</div>

				<div id="edd-item-card-wrapper" class="edd-sl-license-card" style="float: left">
					<?php if ( is_callable( $callbacks[ $view ] ) ) : ?>
						<?php $callbacks[ $view ]( $license ) ?>
					<?php endif; ?>
				</div>

			</div>
		<?php endif; ?>
	</div>
	<?php
}


/**
 * View a license
 *
 * @since  3.5
 * @param  $license The License object being displayed
 * @return void
 */
function edd_sl_licenses_view( $license ) {
	$base         = admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license_id=' . $license->ID );
	$base         = remove_query_arg( 'edd-message' );
	$base         = wp_nonce_url( $base, 'edd_sl_license_nonce' );
	$has_children = $license->get_child_licenses();
	$unsubscribed = $license->get_meta( 'edd_sl_unsubscribed', true );

	$initial_payment = edd_get_payment( $license->payment_id );

	do_action( 'edd_sl_license_card_top', $license->key );
	?>
	<div class="info-wrapper item-section">
		<form id="edit-item-info" method="post" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license_id=' . $license->ID ); ?>">
			<div class="item-info">
				<table class="widefat striped">
					<tbody>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'License Key:', 'edd_sl' ); ?></label>
							</td>
							<td style="word-wrap: break-word">
								<span id="license-key"><?php echo $license->key; ?></span>
								<?php if ( current_user_can( 'manage_licenses' ) ) : ?>
								<span>
									<a id="edd-sl-regenerate-key"
									   href="#"
									   title="Regenerate License Key"
									   data-nonce="<?php echo wp_create_nonce( 'edd-sl-regenerate-license' ); ?>"
									   data-license-id="<?php echo absint( $license->id ); ?>"
									>
										<span class="dashicons dashicons-update"></span>
									</a>
									<?php $tool_tip_title   = __( 'Regenerate License Key', 'edd_sl'); ?>
									<?php $tool_tip_message = __( 'In the event that a user needs to have their license key changed, using this button will assign a new key to this license', 'edd_sl' ); ?>
									<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<strong><?php echo $tool_tip_title ?></strong>:<br /><?php echo $tool_tip_message ?>">
								</span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Purchase Date:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php
								$payment_date = esc_html( date( get_option( 'date_format' ), strtotime( $initial_payment->completed_date ) ) );

								if( $license->payment_id && ! $license->post_parent ) {
									$payment_url = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $license->payment_id );
									echo '<a href="' . esc_attr( $payment_url ) . '">' . $payment_date . '</a>';
								} else {
									echo $payment_date;
								}
								?>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Expiration Date:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php
								$exp_date       = ucfirst( $license->expiration );
								$classes        = 'hidden edd-sl-license-exp-date edd_datepicker';
								$parent_license = $license->parent > 0 ? edd_software_licensing()->get_license( $license->parent ) : false;

								if ( ! $license->is_lifetime ) {
									if ( $license->parent == 0 ) {
										$exp_date = esc_html( date_i18n( get_option( 'date_format' ), $exp_date, 1 ) );
									} else {
										$exp_date       = esc_html( date_i18n( get_option( 'date_format' ), $parent_license->expiration, 1 ) );
									}
								}
								?>
								<span class="edd-sl-license-exp-date"><?php echo $exp_date; ?></span>
								<input type="text" name="exp_date" class="<?php echo $classes; ?>" value="<?php echo esc_attr( $exp_date ); ?>" />

								<?php if ( $license->parent == 0 ) : ?>
								<span>&nbsp;&ndash;&nbsp;</span>
								<a href="#" class="edd-sl-edit-license-exp-date"><?php _e( 'Edit', 'edd_sl' ); ?></a>
								<?php endif; ?>

								<?php
								if ( $license->parent > 0 ) {
									printf( __( '(Set by <a href="%s">parent license</a>)', 'edd_sl' ), add_query_arg( 'license_id', $parent_license->ID ) );
								}
								?>

								<?php
								if( empty( $license->parent ) && ! $license->is_lifetime ) {
									echo sprintf( 'or&nbsp;<a href="%s&action=%s">' . __( 'Mark as Lifetime', 'edd_sl' ) . '</a>', $base, 'set-lifetime' );
								}
								?>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'License Term', 'edd_sl' ); ?>:</label>
							</td>
							<td>
								<span class="license-term"><?php echo $license->license_term(); ?></span>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Product:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php
								$download_name = $license->get_download()->get_name();
								if ( strrpos( $download_name, ' &#8211; ' ) ) {
									$download_name = trim( substr( $download_name, 0, strrpos( $download_name, ' &#8211; ' ) ) );
								}
								$download_name = '<a href="' . admin_url( 'post.php?post=' . $license->download_id . '&action=edit' ) . '">' . $download_name . '</a>';
								$price_id      = 0;

								if( $license->get_download()->has_variable_prices() ) {
									$price_id = $license->price_id;
									$prices   = $license->get_download()->get_prices();

									$options = array();
									if ( empty( $license->parent ) ) {
										foreach ( $prices as $id => $price ) {
											$options[ $id ] = $price['name'];
										}
									} else {
										$child_price_id_label = ' (' . $prices[ $price_id ]['name'] . ')';
									}

								}

								echo $download_name;
								if ( ! empty( $options ) ) {
									echo ' - ';
									echo EDD()->html->select( array(
										'name'             => 'price_id',
										'id'               => 'license-price-id',
										'show_option_all'  => false,
										'show_option_none' => false,
										'options'          => $options,
										'selected'         => $price_id,
									) );
								} else if ( ! empty( $child_price_id_label ) ) {
									echo $child_price_id_label;
								}
								?>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Activation Limit:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php
								$limit = $license->license_limit();
								$data  = '';

								if( $license->post_parent ) {
									$data .= 'data-parent="' . $license->post_parent . '"';
								}
								$active_count = $license->activation_count;
								$limit_text   = '<span id="edd-sl-' . $license->ID . '-limit" ' . $data . '>' . $limit . '</span>';

								echo '<span class="edd-sl-limit-wrap">' . $active_count . ' / ' . $limit_text . '</span>';

								if( ! $license->post_parent ) {
									echo '<span style="margin-left: 15px">';
									echo '<a href="#" class="edd-sl-adjust-limit button-secondary" data-action="increase" data-id="' . absint( $license->ID ) . '" data-download="' . absint( $license->download_id ) . '">+</a>';
									echo '&nbsp;<a href="#" class="edd-sl-adjust-limit button-secondary" data-action="decrease" data-id="' . absint( $license->ID ) . '" data-download="' . absint( $license->download_id ) . '">-</a>';
									echo '</span>';

									$default_count = $license->get_default_activation_count();

									$message = sprintf(
										__( 'The default activation limit for this license is %s, which is controlled by the %s product.', 'edd_sl' ),
										! empty( $default_count ) ? $default_count : __( 'Unlimited', 'edd_sl' ),
										$license->get_download()->get_name()
									);
									$message .= '<br /><br />';
									$message .= __( 'To modify this license, use the +/- to increase or decrease the number of activations, respectively. To allow unlimited activations for this license, reduce the activation limit to 0.', 'edd_sl' );

									echo '&nbsp;<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<strong>'. __( 'Change License Limit', 'edd_sl' ) . '</strong>: ' . $message . '"></span>';
								}
								?>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Status:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php
								$status = $license->status;
								echo '<span class="edd-sl-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
								if ( 'disabled' === $license->status ) {
									echo ' <em>(' . __( 'disabled', 'edd_sl' ) . ')</em>';
								}
								?>
								<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<strong><?php _e( 'What does this mean', 'edd_sl'); ?></strong>:<br /><strong><?php _e( 'Active:', 'edd_sl' ); ?></strong> <?php _e( 'Indicates that a user has activated a site with this license.', 'edd_sl' ); ?><br /><strong> <?php _e( 'Inactive:', 'edd_sl' ); ?></strong> <?php _e( 'Indicates that this license is not currently activated on any sites.', 'edd_sl' ); ?></span><br /><strong><?php _e( 'Expired:', 'edd_sl' ); ?></strong> <?php _e( 'Indicates that this license has expired and can not be used unless renewed.', 'edd_sl' ); ?><br /><strong><?php _e( 'Disabled:', 'edd_sl' ); ?></strong> <?php _e( 'Indicates that this license has been administratively disabled.', 'edd_sl' ); ?>">
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Customer:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php
								$customer_id = $license->customer_id;
								$customer    = new EDD_Customer( $customer_id );
								$name        = empty( $customer->name ) ? $customer->email : $customer->name;
								echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' ) . $customer->id ) . '">' . $name . '</a>';
								?>
							</td>
						</tr>
						<?php if( ! $license->is_lifetime ) : ?>
							<tr>
								<td class="row-title">
									<label for="tablecell"><?php _e( 'Email Notifications:', 'edd_sl' ); ?></label>
								</td>
								<td>
									<?php
									if( $unsubscribed ) {
										printf( __( 'Unsubscribed on %s', 'edd_sl' ), date_i18n( 'Y-n-d H:i:s', $unsubscribed ) );
									} else {
										_e( 'Subscribed', 'edd_sl' );
									}
									?>
									<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<strong><?php _e( 'This indicates whether this customer will receive the license renewal email notifications configured in the Software Licensing settings tab.', 'edd_sl' ); ?>">
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Actions:', 'edd_sl' ); ?></label>
							</td>
							<td class="edd-sl-license-card-actions">
								<?php
								$actions = array();
								$status  = $license->status;

								if( ! $license->parent ) {
									if ( ! $license->is_lifetime ) {

										if( ! $unsubscribed ) {
											$actions[ 'renewal_notice' ] = '<a href="#" id="edd_sl_send_renewal_notice" title="' . __( 'Send a renewal notice for this license key', 'edd_sl' ) . '">' . __( 'Send Renewal Notice', 'edd_sl' ) . '</a>';
										}

										if ( 'disabled' !== $license->status ) {
											if( 'expired' !== $status ) {
												$actions['renew'] = sprintf( '<a href="%s&action=%s" title="' . __( 'Extend this license key\'s expiration date', 'edd_sl' ) . '">' . __( 'Extend', 'edd_sl' ) . '</a>', $base, 'renew', $license->ID );
											} else {
												$actions['renew'] = sprintf( '<a href="%s&action=%s">' . __( 'Renew', 'edd_sl' ) . '</a>', $base, 'renew' );
											}
										}

									}

									if( 'disabled' === $license->status ) {
										$actions['enable'] = sprintf( '<a href="%s&action=%s">' . __( 'Enable', 'edd_sl' ) . '</a>', $base, 'enable' );
									} else {
										$actions['disable'] = sprintf( '<a href="%s&action=%s">' . __( 'Disable', 'edd_sl' ) . '</a>', $base, 'disable' );
									}

								}

								$actions = apply_filters( 'edd_sl_license_details_actions', $actions, $license->ID );
								if ( ! empty( $actions ) ) {
									$count = count( $actions );
									$i     = 1;

									foreach( $actions as $action ) {
										echo $action;

										if( $i < $count ) {
											echo '&nbsp;|&nbsp;';
											$i++;
										}
									}
								} else {
									_e( 'No actions available for this license', 'edd_sl' );
								}
								?>
							</td>
						</tr>
						<tr class="edd-sl-license-card-notices">
							<td class="row-title">
								<label for="tablecell"><?php _e( 'Select Notice:', 'edd_sl' ); ?></label>
							</td>
							<td>
								<?php $notices = edd_sl_get_renewal_notices(); ?>
								<select name="edd_sl_renewal_notice" id="edd_sl_renewal_notice">
									<?php
									foreach( $notices as $notice_id => $notice_data ) {
										echo '<option value="' . esc_attr( $notice_id ) . '">' . esc_html( $notice_data['subject'] ) . '</option>';
									}
									?>
								</select>
								<input type="submit" class="button-secondary button" value="<?php _e( 'Send Notice', 'edd_sl' ); ?>" data-license-id="<?php echo esc_attr( $license->ID ); ?>" />
								<span class="spinner"></span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div id="item-edit-actions" class="edit-item" style="float: right; margin: 10px 0 0; display: block;">
				<input type="hidden" name="edd_action" value="update_license" />
				<?php wp_nonce_field( 'edd-sl-update-license', 'edd-sl-update-license-nonce' ); ?>
				<input type="submit" name="edd_sl_update_license" id="edd_sl_update_license" class="button button-primary" value="<?php _e( 'Update License', 'edd_sl' ); ?>" />
				<input type="hidden" name="license_id" value="<?php echo $license->ID; ?>" />
			</div>
		</form>
	</div>

	<?php do_action( 'edd_sl_license_before_tables_wrapper', $license->key ); ?>

	<div id="edd-item-tables-wrapper" class="item-section">
		<?php do_action( 'edd_sl_license_before_related_licenses', $license->key ); ?>

		<?php if( ! empty( $has_children ) || $parent_has_license = (bool) edd_software_licensing()->get_license_key( $license->parent ) ) : ?>
			<h3>
				<?php _e( 'Related Licenses:', 'edd_sl' ); ?>
				<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php _e( 'This table shows licenses related to this license, including relevant bundled licenses.', 'edd_sl' ); ?>"></span>
			</h3>
			<table class="wp-list-table widefat striped related-licenses">
				<thead>
					<tr>
						<th><?php _e( 'Product', 'edd_sl' ); ?></th>
						<th><?php _e( 'License Key', 'edd_sl' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if( isset( $parent_has_license ) ) : ?>
						<?php $parent_license = edd_software_licensing()->get_license( $license->parent ); ?>
						<tr>
							<td><?php echo $parent_license->get_name( false ); ?></td>
							<td><a href="<?php echo add_query_arg( 'license_id', $parent_license->ID ); ?>"><?php echo edd_software_licensing()->get_license_key( $parent_license->ID ); ?></a></td>
						</tr>
					<?php endif; ?>
					<?php if( $has_children ) : ?>
						<?php foreach( $has_children as $child_license ) : ?>
							<tr>
								<td><?php echo $child_license->get_name( false ); ?></td>
								<td><a href="<?php echo add_query_arg( 'license_id', $child_license->ID ); ?>"><?php echo edd_software_licensing()->get_license_key( $child_license->ID ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php do_action( 'edd_sl_license_after_related_licenses_table', $license->key ); ?>

		<?php endif; ?>

		<?php do_action( 'edd_sl_license_before_licensed_urls', $license->key ); ?>

		<!-- Only show licensed URLs on non-bundled products -->
		<?php if( ! edd_is_bundled_product( $license->download_id ) ) : ?>

		<h3>
			<?php _e( 'Licensed URLs:', 'edd_sl' ); ?>
			<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php _e( 'Use this form to add a new site URL for this license. Once added, the site will be considered active and will be included in the total site count.', 'edd_sl' ); ?>"></span>
		</h3>
		<table class="wp-list-table widefat striped licensed-urls">
			<thead>
				<tr>
					<th><?php _e( 'Site URL', 'edd_sl' ); ?></th>
					<th><?php _e( 'Actions', 'edd_sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$sites = $license->sites;
				if( ! empty( $sites ) ) :
					$i = 0;
					foreach( $sites as $site ) : ?>
						<?php $site_url = strpos( $site, 'http' ) !== false ? $site : 'http://' . $site; ?>
						<tr class="row<?php if( $i % 2 == 0 ) { echo ' alternate'; } ?>">
							<td><a href="<?php echo $site_url; ?>" target="_blank"><?php echo $site; ?></a></td>
							<td><a href="<?php echo wp_nonce_url( add_query_arg( array( 'edd_action' => 'deactivate_site', 'site_url' => $site, 'license' => $license->ID ) ), 'edd_deactivate_site_nonce' ); ?>"><?php _e( 'Deactivate Site', 'edd_sl' ); ?></a></td>
						</tr>
						<?php
						$i++;
					endforeach;
				else : ?>
					<tr class="row"><td colspan="2"><?php _e( 'This license has not been activated on any sites', 'edd_sl' ); ?></td></tr>
				<?php endif; ?>
				<tr class="edd-sl-add-licensed-url-row">
					<td colspan="2" class="edd-sl-add-licensed-url-td">
						<form method="post">
							<input type="text" name="site_url" placeholder="<?php _e( 'New site URL (including http://)', 'edd_sl' ); ?>"/>
							<?php wp_nonce_field( 'edd_add_site_nonce', 'edd_add_site_nonce' ); ?>
							<input type="hidden" name="edd_action" value="insert_site"/>
							<input type="hidden" name="license" value="<?php echo esc_attr( $license->ID ); ?>"/>
							<input type="submit" class="button-secondary button" value="<?php _e( 'Add Site', 'edd_sl' ); ?>"/>
						</form>
					</td>
				</tr>
			</tbody>
		</table>

		<?php endif; ?>
		<!-- End bundled license check -->

		<?php do_action( 'edd_sl_license_before_related_payments', $license->key ); ?>

		<h3>
			<?php _e( 'Related Payments:', 'edd_sl' ); ?>
			<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php _e( 'This table shows the most recent payments relevant to this license, including upgrades and renewals.', 'edd_sl' ); ?>"></span>
		</h3>
		<table class="wp-list-table widefat striped related-payments">
			<?php
			$payments = edd_get_payments( array( 'post__in' => $license->payment_ids ) );
			$payments = array_slice( $payments, 0, 10 );
			?>
			<thead>
				<tr>
					<th><?php _e( 'ID', 'edd_sl' ); ?></th>
					<th><?php _e( 'Amount', 'edd_sl' ); ?></th>
					<th><?php _e( 'Date', 'edd_sl' ); ?></th>
					<th><?php _e( 'Status', 'edd_sl' ); ?></th>
					<th><?php _e( 'Actions', 'edd_sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $payments ) ) : ?>
					<?php foreach ( $payments as $payment ) : ?>
						<tr>
							<td><?php echo $payment->ID; ?></td>
							<td><?php echo edd_payment_amount( $payment->ID ); ?></td>
							<td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $payment->post_date ) ); ?></td>
							<td><?php echo edd_get_payment_status( $payment, true ); ?></td>
							<td>
								<a href="<?php echo admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $payment->ID ); ?>">
									<?php _e( 'View Details', 'edd_sl' ); ?>
								</a>
								<?php do_action( 'edd_sl_license_details_relate_payment_actions', $customer, $payment ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr><td colspan="5"><?php _e( 'No Payments Found', 'edd_sl' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php do_action( 'edd_sl_license_before_upgrade_paths', $license->key ); ?>

		<h3>
			<?php _e( 'Upgrade Paths:', 'edd_sl' ); ?>
			<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php printf( __( 'This table shows the available upgrade paths for this license, along with a link to each specific upgrade. Upgrade paths can be added through the %s edit screen.', 'edd_sl' ), strtolower( edd_get_label_singular() ) ); ?>"></span>
		</h3>
		<table class="wp-list-table widefat striped upgrades">
			<thead>
				<tr>
					<th><?php _e( 'Product', 'edd_sl' ); ?></th>
					<th><?php _e( 'Amount', 'edd_sl' ); ?></th>
					<th><?php _e( 'Upgrade Link', 'edd_sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( $status == 'expired' ) {
					echo '<tr>';
					echo '<td colspan="3">' . __( 'This license must be renewed before it can be upgraded', 'edd_sl' ) . '</td>';
					echo '</tr>';
				} else {
					$upgrades = edd_sl_get_license_upgrades( $license->ID );

					if( $upgrades && ! $license->post_parent ) {
						foreach( $upgrades as $upgrade_id => $upgrade ) {
							echo '<tr>';
							echo '<td>';
							echo get_the_title( $upgrade['download_id'] );
							if( isset( $upgrade['price_id'] ) && edd_has_variable_prices( $upgrade['download_id'] ) ) {
								echo ' - ' . edd_get_price_option_name( $upgrade['download_id'], $upgrade['price_id'] );
							}
							echo '</td>';
							echo '<td>' . edd_currency_filter( edd_sanitize_amount( edd_sl_get_license_upgrade_cost( $license->ID, $upgrade_id ) ) ) . '</td>';
							echo '<td>' . '<input type="text" readonly="readonly" class="edd_sl_upgrade_link" value="' . esc_url( edd_sl_get_license_upgrade_url( $license->ID, $upgrade_id ) ) . '"/></td>';
							echo '</tr>';
						}
					} elseif( $license->post_parent ) {
						echo '<tr>';
						echo '<td colspan="3">&nbsp;&mdash;&nbsp;' . __( 'Bundled licenses can not be upgraded individually', 'edd_sl' ) . '</td>';
						echo '</tr>';
					} else {
						echo '<tr>';
						echo '<td colspan="3">' . __( 'No upgrade path available', 'edd_sl' ) . '</td>';
						echo '</tr>';
					}
				}
				?>
			</tbody>
		</table>
		<?php if( edd_sl_renewals_allowed() && ! $license->is_lifetime ) : ?>
			<h3>
				<?php _e( 'Renewal URL:', 'edd_sl' ); ?>
				<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php printf( __( 'This URL can be provided to a customer for a direct link to your checkout screen with the license renewal pre-populated.', 'edd_sl' ), strtolower( edd_get_label_singular() ) ); ?>"></span>
			</h3>
			<table class="wp-list-table widefat striped" id="edd-sl-renewal-url">
				<tbody>
					<tr>
						<td><?php echo $license->get_renewal_url(); ?></td>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if( ! $unsubscribed && ! $license->is_lifetime ) : ?>
			<h3>
				<?php _e( 'Unsubscribe URL:', 'edd_sl' ); ?>
				<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php _e( 'This URL can be provided to a customer in order to unsubscribe from license renewal notification emails.', 'edd_sl' ); ?>"></span>
			</h3>
			<table class="wp-list-table widefat striped" id="edd-sl-renewal-url">
				<tbody>
					<tr>
						<td><?php echo $license->get_unsubscribe_url(); ?></td>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php
	do_action( 'edd_sl_license_card_bottom', $license->key );
}


/**
 * View logs for a license
 *
 * @since  3.5
 * @param  EDD_SL_License $license The License object being displayed
 * @return void
 */
function edd_sl_licenses_logs_view( $license ) {
	$license_id  = $license->ID;
	?>

	<div class="license-logs-header">
		<span><?php printf( __( 'License Key: %s', 'edd_sl' ), $license->key ); ?></span>
	</div>

	<?php do_action( 'edd_sl_license_before_license_logs', $license->key ); ?>

	<div class="edd-item-info license-logs">
		<h3>
			<?php _e( 'Logged Entries:', 'edd_sl' ); ?>
			<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php _e( 'This table shows log entries relevant to this license.', 'edd_sl' ); ?>"></span>
		</h3>
		<table class="wp-list-table widefat striped license-logs">
			<thead>
				<tr>
					<th><?php _e( 'ID', 'edd_sl' ); ?></th>
					<th><?php _e( 'Date/Time', 'edd_sl' ); ?></th>
					<th><?php _e( 'Entry', 'edd_sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$logs = $license->get_logs();

				if( ! empty( $logs ) ) {
					foreach ( $logs as $log ) {
						echo '<tr>';
						echo '<td>#' . esc_html( $log->ID ) . '</td>';
						echo '<td>' . date_i18n( 'Y-m-d h:m:s', strtotime( $log->post_date ) ) . '</td>';
						echo '<td>';
						if( has_term( 'renewal_notice', 'edd_log_type', $log->ID ) ) {
							echo esc_html( $log->post_title );
						} else {
							$data = json_decode( $log->post_content );
							echo esc_html( $log->post_title ) . '<br />';

							if( isset( $data->HTTP_USER_AGENT ) ) {
								echo esc_html( $data->HTTP_USER_AGENT ) . ' - ';
							}

							if( isset( $data->HTTP_USER_AGENT ) ) {
								echo 'IP: ' . esc_html( $data->REMOTE_ADDR ) . ' - ';
							}

							if( isset( $data->HTTP_USER_AGENT ) ) {
								echo esc_html( date_i18n( get_option( 'date_format' ), $data->REQUEST_TIME ) . ' ' . date_i18n( get_option( 'time_format' ), $data->REQUEST_TIME ) );
							}

							if ( isset( $data->license_key ) ) {
								echo esc_html( $data->license_key ) . ' - ';
							}

							if ( isset( $data->user_id ) ) {
								$login = '';
								if ( ! empty( $data->user_id ) ) {
									$user  = new WP_User( $data->user_id );
									$login = $user->user_login;
								}

								if ( ! empty( $login ) ) {
									echo esc_html( $login );
								}

							}

						}
						echo '</td>';
						echo '</tr>';
					}
				} else {
					echo '<tr><td colspan="3">' . __( 'This license has no log entries', 'edd_sl' ) . '</td></tr>';
				}
				?>
			</tbody>
		</table>

		<?php do_action( 'edd_sl_license_after_license_logs', $license->key ); ?>

	</div>
	<?php
}


/**
 * Delete a license
 *
 * @since  3.5
 * @param  $license The License object being displayed
 * @return void
 */
function edd_sl_licenses_delete_view( $license ) {
	$license_id  = $license->ID;
	?>

	<div class="license-logs-header">
		<span><?php printf( __( 'License Key: %s', 'edd_sl' ), $license->key ); ?></span>
	</div>

	<?php do_action( 'edd_sl_license_before_license_delete', $license->key ); ?>

	<form id="delete-license" method="post" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-licenses&view=delete&license_id=' . $license->ID ); ?>">
		<div class="edd-item-info delete-license">
			<span class="delete-license-options">
				<p>
					<?php echo EDD()->html->checkbox( array( 'name' => 'edd-sl-license-delete-confirm' ) ); ?>
					<label for="edd-sl-license-delete-confirm"><?php _e( 'Are you sure you want to delete this license?', 'edd_sl' ); ?></label>
				</p>

				<?php do_action( 'edd_sl_license_delete_inputs', $license ); ?>
			</span>

			<span id="license-edit-actions">
				<input type="hidden" name="license_id" value="<?php echo $license->ID; ?>" />
				<?php wp_nonce_field( 'delete-license', '_wpnonce', false, true ); ?>
				<input type="hidden" name="edd_action" value="sl_delete_license" />
				<input type="submit" disabled="disabled" id="edd-sl-delete-license" class="button-primary" value="<?php _e( 'Delete License', 'edd_sl' ); ?>" />
				<a id="edd-sl-delete-license-cancel" href="<?php echo admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license_id=' . $license->ID ); ?>" class="delete"><?php _e( 'Cancel', 'edd_sl' ); ?></a>
			</span>
		</div>
	</form>

	<?php do_action( 'edd_sl_license_after_license_delete', $license->key );
}
