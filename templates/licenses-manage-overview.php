<?php

if ( ! is_user_logged_in() ) {
	return;
}

$payment_id = absint( $_GET['payment_id' ] );
$user_id    = edd_get_payment_user_id( $payment_id );

if( ! current_user_can( 'manage_licenses' ) && $user_id != get_current_user_id() ) {
	return;
}

$color = edd_get_option( 'checkout_color', 'gray' );
$color = ( $color == 'inherit' ) ? '' : $color;

?>
<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		var showKeys = document.querySelectorAll('.edd_sl_show_key');
		if (showKeys) {
			for (var i = 0; i < showKeys.length; i++) {
				showKeys[i].addEventListener('click', function(e) {
					e.preventDefault();
					var key = this.parentNode.querySelector('.edd_sl_license_key');
					key.style.display = (key.style.display != 'block') ? 'block' : 'none';
				});
			}
		}
	});
</script>
<p><a href="<?php echo esc_url( remove_query_arg( array( 'action', 'payment_id', 'edd_sl_error' ) ) ); ?>" class="edd-manage-license-back edd-submit button <?php echo esc_attr( $color ); ?>"><?php _e( 'Go back', 'edd_sl' ); ?></a></p>
<?php
// Retrieve all license keys for the specified payment
$edd_sl = edd_software_licensing();
$keys   = $edd_sl->get_licenses_of_purchase( $payment_id );
$keys   = apply_filters( 'edd_sl_manage_template_payment_licenses', $keys, $payment_id );
if ( $keys ) : ?>
	<table id="edd_sl_license_keys" class="edd_sl_table edd-table">
		<thead>
			<tr class="edd_sl_license_row">
				<?php do_action('edd_sl_license_header_before'); ?>
				<th class="edd_sl_item"><?php _e( 'Item', 'edd_sl' ); ?></th>
				<th class="edd_sl_key"><?php _e( 'Key', 'edd_sl' ); ?></th>
				<th class="edd_sl_status"><?php _e( 'Status', 'edd_sl' ); ?></th>
				<th class="edd_sl_limit"><?php _e( 'Activations', 'edd_sl' ); ?></th>
				<th class="edd_sl_expiration"><?php _e( 'Expiration', 'edd_sl' ); ?></th>
				<?php if( ! $edd_sl->force_increase() ) : ?>
				<th class="edd_sl_sites"><?php _e( 'Manage Sites', 'edd_sl' ); ?></th>
				<?php endif; ?>
				<th class="edd_sl_upgrades"><?php _e( 'Upgrades', 'edd_sl' ); ?></th>
				<?php do_action('edd_sl_license_header_after'); ?>
			</tr>
		</thead>
		<?php foreach ( $keys as $license ) : ?>
			<tr class="edd_sl_license_row">
				<?php do_action( 'edd_sl_license_row_start', $license->ID ); ?>
				<td>
					<?php
					$download_id = $edd_sl->get_download_id( $license->ID );
					$price_id    = $edd_sl->get_price_id( $license->ID );

					echo get_the_title( $download_id ); ?>
					<?php if( '' !== $price_id ) : ?>
						<span class="edd_sl_license_price_option">&ndash;&nbsp;<?php echo edd_get_price_option_name( $download_id, $price_id ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<span class="view-key-wrapper">
						<a href="#" class="edd_sl_show_key" title="<?php _e( 'Click to view license key', 'edd_sl' ); ?>"><img src="<?php echo EDD_SL_PLUGIN_URL . '/images/key.png'; ?>"/></a>
						<input type="text" readonly="readonly" class="edd_sl_license_key" value="<?php echo esc_attr( $edd_sl->get_license_key( $license->ID ) ); ?>" style="display:none;"/>
					</span>
				</td>
				<td class="edd_sl_license_status edd-sl-<?php echo $edd_sl->get_license_status( $license->ID ); ?>"><?php echo $edd_sl->license_status( $license->ID ); ?></td>
				<td><span class="edd_sl_limit_used"><?php echo $edd_sl->get_site_count( $license->ID ); ?></span><span class="edd_sl_limit_sep">&nbsp;/&nbsp;</span><span class="edd_sl_limit_max"><?php echo $edd_sl->license_limit( $license->ID ); ?></span></td>
				<td>
				<?php if ( method_exists( $edd_sl, 'is_lifetime_license' ) && $edd_sl->is_lifetime_license( $license->ID ) ) : ?>
					<?php _e( 'Lifetime', 'edd_sl' ); ?>
				<?php else: ?>
					<?php echo date_i18n( 'F j, Y', $edd_sl->get_license_expiration( $license->ID ) ); ?>
				<?php endif; ?>
				<?php if( edd_sl_renewals_allowed() && $license->post_parent == 0 ) : ?>
					<?php if( 'expired' === edd_software_licensing()->get_license_status( $license->ID ) && edd_software_licensing()->can_renew( $license->ID ) ) : ?>
						<span class="edd_sl_key_sep">&nbsp;&ndash;&nbsp;</span>
						<a href="<?php echo edd_software_licensing()->get_renewal_url( $license->ID ); ?>" title="<?php esc_attr_e( 'Renew license', 'edd_sl' ); ?>"><?php _e( 'Renew license', 'edd_sl' ); ?></a>
					<?php elseif( ! edd_software_licensing()->is_lifetime_license( $license->ID ) && edd_software_licensing()->can_extend( $license->ID ) ) : ?>
						<span class="edd_sl_key_sep">&nbsp;&ndash;&nbsp;</span>
						<a href="<?php echo edd_software_licensing()->get_renewal_url( $license->ID ); ?>" title="<?php esc_attr_e( 'Extend license', 'edd_sl' ); ?>"><?php _e( 'Extend license', 'edd_sl' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				</td>
				<?php if( ! $edd_sl->force_increase() ) : ?>
				<td><a href="<?php echo esc_url( add_query_arg( 'license_id', $license->ID ) ); ?>"><?php _e( 'Manage Sites', 'edd_sl' ); ?></a></td>
				<?php endif; ?>
				<td>
				<?php if( edd_sl_license_has_upgrades( $license->ID ) && 'expired' !== $edd_sl->get_license_status( $license->ID ) ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'upgrades', 'license_id' => $license->ID ) ) ); ?>"><?php _e( 'View Upgrades', 'edd_sl' ); ?></a>
				<?php elseif ( edd_sl_license_has_upgrades( $license->ID ) && 'expired' == $edd_sl->get_license_status( $license->ID ) ) : ?>
					<span class="edd_sl_no_upgrades"><?php _e( 'Renew to upgrade', 'edd_sl' ); ?></span>
				<?php else : ?>
					<span class="edd_sl_no_upgrades"><?php _e( 'No upgrades available', 'edd_sl' ); ?></span>
				<?php endif; ?>
				</td>
				<?php do_action( 'edd_sl_license_row_end', $license->ID ); ?>
			</tr>
		<?php endforeach; ?>
	</table>
<?php else : ?>
	<p class="edd_sl_no_keys"><?php _e( 'There are no license keys for this purchase', 'edd_sl' ); ?></p>
<?php endif;?>
