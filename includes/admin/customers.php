<?php

/**
 * Add Licenses to the EDD Customer Interface
 * *
 * @since 3.3
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Add the licenses tab to the customer interface if the customer has licenses
 *
 * @since  3.3
 * @param  array $tabs The tabs currently added to the customer view
 * @return array       Updated tabs array
 */
function edd_sl_customer_tab( $tabs ) {

	$customer_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : false;
	$customer    = new EDD_Customer( $customer_id );

	$licenses = edd_software_licensing()->licenses_db->get_licenses( array(
		'number'      => 1,
		'customer_id' => $customer->id,
		'parent'      => 0,
	) );

	// If they have licenses show the tab.
	if ( $licenses ) {

		$tabs['licenses'] = array( 'dashicon' => 'dashicons-lock', 'title' => __( 'License Keys', 'edd_sl' ) );

	}


	return $tabs;
}
add_filter( 'edd_customer_tabs', 'edd_sl_customer_tab', 10, 1 );

/**
 * Register the licenses view for the customer interface
 *
 * @since  3.3
 * @param  array $tabs The tabs currently added to the customer views
 * @return array       Updated tabs array
 */
function edd_sl_customer_view( $views ) {

	$customer_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : false;
	$customer    = new EDD_Customer( $customer_id );

	$licenses = edd_software_licensing()->licenses_db->count( array(
		'number'      => 1,
		'customer_id' => $customer->id,
	) );

	if ( $licenses ) {

		$views['licenses'] = 'edd_sl_customer_licenses_view';

	}

	return $views;
}
add_filter( 'edd_customer_views', 'edd_sl_customer_view', 10, 1 );

/**
 * Display the licenses area for the customer view
 *
 * @since  3.3
 * @param  object $customer The Customer being displayed
 * @return void
 */
function edd_sl_customer_licenses_view( $customer ) {

	$licenses = edd_software_licensing()->licenses_db->get_licenses( array(
		'number'      => -1,
		'customer_id' => $customer->id,
		'parent'      => 0,
		'orderby'     => 'id',
		'order'       => 'ASC',
	) );

	?>
	<div class="customer-notes-header">
		<?php echo get_avatar( $customer->email, 30 ); ?> <span><?php echo $customer->name; ?></span>
	</div>

	<?php if ( $licenses ) :
		// Before we start looping, let's make sure we get any child licenses.
		$all_license_keys = array();
		foreach ( $licenses as $license ) {
			$all_license_keys[] = $license;

			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				foreach ( $child_licenses as $child_license ) {
					$all_license_keys[] = $child_license;
				}
			}
		}
		?>
		<div id="customer-tables-wrapper" class="customer-section">
			<h3><?php esc_html_e( 'License Keys', 'edd_sl' ); ?></h3>

			<table class="wp-list-table widefat striped downloads">
				<thead>
					<tr>
						<th><?php echo edd_get_label_singular(); ?></th>
						<th><?php esc_html_e( 'License Key', 'edd_sl' ); ?></th>
						<th><?php esc_html_e( 'Status', 'edd_sl' ); ?></th>
						<th width="120px"><?php esc_html_e( 'Actions', 'edd_sl' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $all_license_keys ) ) : ?>
						<?php foreach ( $all_license_keys as $license ) : ?>
							<tr>
								<td>
									<?php if ( ! empty( $license->parent ) ) { echo '&mdash;'; } ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $license->download_id ) ); ?>"><?php echo $license->get_download()->get_name(); ?></a>
								</td>
								<td><?php echo $license->key; ?></td>
								<td>
									<?php
									$status = $license->status;
									echo '<span class="edd-sl-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
									?>
								</td>
								<td>
									<a title="<?php esc_attr_e( 'View', 'edd_sl' ); ?>" href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license_id=' . $license->ID ) ); ?>">
										<?php esc_html_e( 'View', 'edd_sl' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr><td colspan="2"><?php esc_html_e( 'No license keys found', 'edd_sl' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

		</div>
	<?php endif;
}
