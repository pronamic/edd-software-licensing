<?php

$color = edd_get_option( 'checkout_color', 'gray' );
$color = ( $color == 'inherit' ) ? '' : $color;

if ( is_user_logged_in() ) :
	$license_keys = edd_software_licensing()->get_license_keys_of_user( get_current_user_id(), 0, 'any', false );
	?>
	<?php do_action( 'edd_sl_license_keys_before' ); ?>

	<table id="edd_sl_license_keys" class="edd_sl_table edd-table">
		<thead>
			<tr class="edd_sl_license_row">
				<?php do_action('edd_sl_license_keys_header_before'); ?>
				<th class="edd_sl_item"><?php esc_html_e( 'Item', 'edd_sl' ); ?></th>
				<th class="edd_sl_details"><?php esc_html_e( 'License Details', 'edd_sl' ); ?></th>
				<?php do_action('edd_sl_license_keys_header_after'); ?>
			</tr>
		</thead>
		<?php if ( $license_keys ) : ?>

			<?php foreach ( $license_keys as $license ) : ?>
				<?php
					$payment_id   = $license->payment_id;
					$renewal_link = $license->get_renewal_url();
				?>
				<tr class="edd_sl_license_row">

					<?php do_action( 'edd_sl_license_keys_row_start', $license->ID ); ?>
					<?php $child_licenses = $license->child_licenses; ?>

					<td>
						<div class="edd_sl_item_name">
							<?php
							echo edd_software_licensing()->get_license_download_display_name( $license );
							?>
						</div>
						<input type="text" readonly="readonly" class="edd_sl_license_key" value="<?php echo esc_attr( $license->license_key ); ?>" />
						<?php if ( ! empty( $child_licenses ) ) : ?>
							<strong><?php esc_html_e( 'Bundle Licenses', 'edd_sl' ); ?></strong>
							<ul class="edd-sl-child-licenses">

								<?php foreach ( $child_licenses as $child_license ) : ?>

									<li class="edd-sl-child">
										<span>
											<?php
											echo edd_software_licensing()->get_license_download_display_name( $child_license );
											if ( ! edd_software_licensing()->force_increase() ) {
												$url = esc_url( add_query_arg( array( 'license_id' => $child_license->ID, 'action' => 'manage_licenses', 'payment_id' => $payment_id ), get_permalink( edd_get_option( 'purchase_history_page' ) ) ) );
												?>
												&nbsp;&ndash;&nbsp;<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Manage Sites', 'edd_sl' ); ?></a>
												<?php
											}
											?>
										</span>
										<input type="text" readonly="readonly" class="edd_sl_license_key" value="<?php echo esc_attr( $child_license->license_key ); ?>" />
									</li>
								<?php endforeach; ?>

							</ul>

						<?php endif; ?>
					</td>

					<td>
						<span class="edd_sl_status_label"><?php esc_html_e( 'Status:', 'edd_sl' ); ?>&nbsp;</span>
						<span class="edd_sl_license_status edd-sl-<?php echo esc_attr( $license->status ); ?>">
							<?php echo wp_kses_post( $license->get_display_status() ); ?>
						</span>
						<div class="edd_sl_item_expiration">
							<span class="edd_sl_expires_label edd_sl_expiries_label"><?php 'expired' === $license->status ? esc_html_e( 'Expired:', 'edd_sl' ) : esc_html_e( 'Expires:', 'edd_sl' ); ?>&nbsp;</span>
							<?php if ( $license->is_lifetime ) : ?>
								<?php _e( 'Never', 'edd_sl' ); ?>
							<?php else: ?>
								<?php echo esc_html( date_i18n( 'F j, Y', $license->expiration ) ); ?>
							<?php endif; ?>
						</div>
						<span class="edd_sl_limit_label"><?php esc_html_e( 'Activations:', 'edd_sl' ); ?>&nbsp;</span>
						<span class="edd_sl_limit_used"><?php echo esc_html( $license->activation_count ); ?></span>
						<span class="edd_sl_limit_sep">&nbsp;/&nbsp;</span>
						<span class="edd_sl_limit_max"><?php echo esc_html( 0 !== $license->activation_limit ? $license->activation_limit : __( 'Unlimited', 'edd_sl' ) ); ?></span>
						<?php if ( ! edd_software_licensing()->force_increase() && ( ! in_array( $license->status, array( 'expired', 'disabled' ), true ) ) ) : ?>
							<br/><a href="<?php echo esc_url( add_query_arg( array( 'license_id' => $license->ID, 'action' => 'manage_licenses', 'payment_id' => $payment_id ), get_permalink( edd_get_option( 'purchase_history_page' ) ) ) ); ?>"><?php _e( 'Manage Sites', 'edd_sl' ); ?></a>
						<?php elseif ( 'expired' === $license->status && $renewal_link ) : ?>
							<br/><span class="edd_sl_no_management"><?php esc_html_e( 'Renew to manage sites', 'edd_sl' ); ?></span>
						<?php else : ?>
							<br/><span class="edd_sl_no_management"><?php esc_html_e( 'Unable to manage sites', 'edd_sl' ); ?></span>
						<?php endif; ?>
						<?php if ( edd_sl_license_has_upgrades( $license->ID ) && 'expired' !== $license->status ) : ?>
							<span class="edd_sl_limit_sep">&nbsp;&ndash;&nbsp;</span>
							<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'upgrades', 'license_id' => $license->ID, 'action' => 'manage_licenses', 'payment_id' => $payment_id ), get_permalink( edd_get_option( 'purchase_history_page' ) ) ) ); ?>"><?php _e( 'View Upgrades', 'edd_sl' ); ?></a>
						<?php elseif ( edd_sl_license_has_upgrades( $license->ID ) && 'expired' === $license->status ) : ?>
							<span class="edd_sl_limit_sep">&nbsp;&ndash;&nbsp;</span>
							<span class="edd_sl_no_upgrades"><?php esc_html_e( 'Renew to upgrade', 'edd_sl' ); ?></span>
						<?php endif; ?>
						<?php if ( $renewal_link ) : ?>
							<?php if ( 'expired' === $license->status && edd_software_licensing()->can_renew( $license->ID ) ) : ?>
								<span class="edd_sl_key_sep">&nbsp;&ndash;&nbsp;</span>
								<a href="<?php echo esc_url( $renewal_link ); ?>"><?php esc_html_e( 'Renew license', 'edd_sl' ); ?></a>
							<?php elseif ( ! $license->is_lifetime && edd_software_licensing()->can_extend( $license->ID ) ) : ?>
								<span class="edd_sl_key_sep">&nbsp;&ndash;&nbsp;</span>
								<a href="<?php echo esc_url( $renewal_link ); ?>"><?php esc_html_e( 'Extend license', 'edd_sl' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<br/>
						<a class="edd_sl_purchase_number" href="<?php echo esc_url( edd_get_success_page_uri( '?payment_key=' . edd_get_payment_key( $payment_id ) ) ); ?>" title="<?php esc_attr_e( 'View Purchase Record', 'edd_sl' ); ?>"><?php printf( __( 'Purchase #%s', 'edd_sl' ), edd_get_payment_number( $payment_id ) ); ?></a>
						<?php do_action( 'edd_sl_license_key_details', $license->ID ); ?>
					</td>

					<?php do_action( 'edd_sl_license_keys_row_end', $license->ID ); ?>

				</tr>

			<?php endforeach; ?>

		<?php else: ?>

			<tr class="edd_sl_license_row">
				<td colspan="2"><?php esc_html_e( 'You currently have no licenses', 'edd_sl' ); ?></td>
			</tr>
		<?php endif; ?>
	</table>
	<?php do_action( 'edd_sl_license_keys_after' ); ?>
<?php else : ?>
	<p class="edd-alert edd-alert-warn">
		<?php esc_html_e( 'You must be logged in to view license keys.', 'edd_sl' ); ?>
	</p>
	<?php echo edd_login_form(); ?>
<?php endif; ?>
