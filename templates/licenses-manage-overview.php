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
$keys = edd_software_licensing()->get_licenses_of_purchase( $payment_id );
$keys = apply_filters( 'edd_sl_manage_template_payment_licenses', $keys, $payment_id );
if ( $keys ) : ?>
	<table id="edd_sl_license_keys" class="edd_sl_table edd-table">
		<thead>
			<tr class="edd_sl_license_row">
				<?php do_action('edd_sl_license_header_before'); ?>
				<th class="edd_sl_item"><?php esc_html_e( 'Item', 'edd_sl' ); ?></th>
				<th class="edd_sl_key"><?php esc_html_e( 'Key', 'edd_sl' ); ?></th>
				<th class="edd_sl_status"><?php esc_html_e( 'Status', 'edd_sl' ); ?></th>
				<th class="edd_sl_limit"><?php esc_html_e( 'Activations', 'edd_sl' ); ?></th>
				<th class="edd_sl_expiration"><?php esc_html_e( 'Expiration', 'edd_sl' ); ?></th>
				<?php if ( ! edd_software_licensing()->force_increase() ) : ?>
				<th class="edd_sl_sites"><?php esc_html_e( 'Manage Sites', 'edd_sl' ); ?></th>
				<?php endif; ?>
				<th class="edd_sl_upgrades"><?php esc_html_e( 'Upgrades', 'edd_sl' ); ?></th>
				<?php do_action('edd_sl_license_header_after'); ?>
			</tr>
		</thead>
		<?php foreach ( $keys as $license ) : ?>
			<tr class="edd_sl_license_row">
				<?php do_action( 'edd_sl_license_row_start', $license->ID ); ?>
				<td>
					<?php
					echo edd_software_licensing()->get_license_download_display_name( $license );
					?>
				</td>
				<td>
					<span class="view-key-wrapper">
						<a href="#" class="edd_sl_show_key" title="<?php esc_html_e( 'Click to view license key', 'edd_sl' ); ?>"><img src="<?php echo esc_url( EDD_SL_PLUGIN_URL . '/assets/images/key.png' ); ?>"/></a>
						<input type="text" readonly="readonly" class="edd_sl_license_key" value="<?php echo esc_attr( $license->license_key ); ?>" style="display:none;"/>
					</span>
				</td>
				<td class="edd_sl_license_status edd-sl-<?php echo esc_attr( $license->status ); ?>"><?php echo wp_kses_post( $license->get_display_status() ); ?></td>
				<td><span class="edd_sl_limit_used"><?php echo esc_html( $license->activation_count ); ?></span><span class="edd_sl_limit_sep">&nbsp;/&nbsp;</span><span class="edd_sl_limit_max"><?php echo esc_html( 0 !== $license->activation_limit ? $license->activation_limit : __( 'Unlimited', 'edd_sl' ) ); ?></span></td>
				<td>
				<?php if ( $license->is_lifetime ) : ?>
					<?php esc_html_e( 'Lifetime', 'edd_sl' ); ?>
				<?php else: ?>
					<?php echo date_i18n( 'F j, Y', $license->expiration ); ?>
				<?php endif; ?>
				<?php if ( edd_sl_renewals_allowed() && empty( $license->parent ) ) :
					$renewal_link = $license->get_renewal_url();
					?>
					<?php if ( 'expired' === $license->status && $renewal_link ) : ?>
						<span class="edd_sl_key_sep">&nbsp;&ndash;&nbsp;</span>
						<a href="<?php echo esc_url( $renewal_link ); ?>"><?php esc_html_e( 'Renew license', 'edd_sl' ); ?></a>
					<?php elseif ( ! $license->is_lifetime && edd_software_licensing()->can_extend( $license->ID ) ) : ?>
						<span class="edd_sl_key_sep">&nbsp;&ndash;&nbsp;</span>
						<a href="<?php echo esc_url( $renewal_link ); ?>"><?php esc_html_e( 'Extend license', 'edd_sl' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				</td>
				<?php if ( ! edd_software_licensing()->force_increase() ) : ?>
				<td><a href="<?php echo esc_url( add_query_arg( 'license_id', $license->ID ) ); ?>"><?php esc_html_e( 'Manage Sites', 'edd_sl' ); ?></a></td>
				<?php endif; ?>
				<td>
				<?php if ( 'expired' !== $license->status && edd_sl_license_has_upgrades( $license->ID ) ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'upgrades', 'license_id' => $license->ID ) ) ); ?>"><?php esc_html_e( 'View Upgrades', 'edd_sl' ); ?></a>
				<?php elseif ( 'expired' === $license->status && edd_sl_license_has_upgrades( $license->ID ) ) : ?>
					<span class="edd_sl_no_upgrades"><?php esc_html_e( 'Renew to upgrade', 'edd_sl' ); ?></span>
				<?php else : ?>
					<span class="edd_sl_no_upgrades"><?php esc_html_e( 'No upgrades available', 'edd_sl' ); ?></span>
				<?php endif; ?>
				</td>
				<?php do_action( 'edd_sl_license_row_end', $license->ID ); ?>
			</tr>
		<?php endforeach; ?>
	</table>
<?php else : ?>
	<p class="edd_sl_no_keys"><?php esc_html_e( 'There are no license keys for this purchase.', 'edd_sl' ); ?></p>
<?php endif;?>
