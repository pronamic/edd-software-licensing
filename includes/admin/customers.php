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
	$payment_ids = explode( ',', $customer->payment_ids );
	$licenses    = get_posts( array(
		'post_type'      => 'edd_license',
		'posts_per_page' => 100,
		'post_status'    => 'any',
		'meta_query'     =>  array(
			array(
				'key'     => '_edd_sl_payment_id',
				'value'   => $payment_ids,
				'compare' => 'IN'
			)
		)
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
	$payment_ids = explode( ',', $customer->payment_ids );
	$licenses    = get_posts( array(
		'post_type'      => 'edd_license',
		'posts_per_page' => 100,
		'post_status'    => 'any',
		'meta_query'     =>  array(
			array(
				'key'     => '_edd_sl_payment_id',
				'value'   => $payment_ids,
				'compare' => 'IN'
			)
		)
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

	$payment_ids = explode( ',', $customer->payment_ids );
	$licenses    = get_posts( array(
		'post_type'      => 'edd_license',
		'posts_per_page' => 100,
		'post_status'    => 'any',
		'meta_query'     =>  array(
			array(
				'key'     => '_edd_sl_payment_id',
				'value'   => $payment_ids,
				'compare' => 'IN'
			)
		)
	) );

	?>
	<div class="customer-notes-header">
		<?php echo get_avatar( $customer->email, 30 ); ?> <span><?php echo $customer->name; ?></span>
	</div>

	<?php if ( $licenses ) : ?>
	<div id="customer-tables-wrapper" class="customer-section">
		<h3><?php _e( 'License Keys', 'edd_sl' ); ?></h3>

		<table class="wp-list-table widefat striped downloads">
			<thead>
				<tr>
					<th><?php echo edd_get_label_singular(); ?></th>
					<th><?php _e( 'License Key', 'edd_sl' ); ?></th>
					<th><?php _e( 'Status', 'edd_sl' ); ?></th>
					<th width="120px"><?php _e( 'Actions', 'edd_sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $licenses ) ) : ?>
					<?php foreach ( $licenses as $license ) : ?>
						<?php
						$license_key = get_post_meta( $license->ID, '_edd_sl_key', true );
						$download_id = get_post_meta( $license->ID, '_edd_sl_download_id', true );
						?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $download_id ) ); ?>"><?php echo get_the_title( $download_id ); ?></a></td>
							<td><?php echo $license_key; ?></td>
							<td><?php echo edd_software_licensing()->get_license_status( $license->ID ); ?></td>
							<td>
								<a title="<?php esc_attr_e( 'View', 'edd_sl' ); ?>" href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-licenses&view=overview&license=' . $license->ID ) ); ?>">
									<?php _e( 'View', 'edd_sl' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr><td colspan="2"><?php _e( 'No license keys found', 'edd_sl' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

	</div>
	<?php endif;
}
