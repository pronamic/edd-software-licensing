<?php
/**
 * License Renewal Form
 *
 * Used with the [edd_renewal_form] shortcode and to display the license renewal form on the checkout page.
 *
 * @package     EDD-Software-Licensing
 * @subpackage  Templates
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7
 */
$is_checkout = function_exists( 'edd_is_checkout' ) ? edd_is_checkout() : false;
$is_renewal  = false;
if ( $is_checkout ) {
	$renewal    = EDD()->session->get( 'edd_is_renewal' );
	$keys       = edd_sl_get_renewal_keys();
	$is_renewal = ! empty( $renewal ) && ! empty( $keys );
}
$preset_key = ! empty( $_GET['key'] ) ? esc_html( urldecode( $_GET['key'] ) ) : '';
$color      = edd_get_option( 'checkout_color', 'blue' );
$color      = ( 'inherit' === $color ) ? '' : $color;

?>
<form method="post" id="edd_sl_renewal_form">
	<fieldset id="edd_sl_renewal_fields">
	<legend class="screen-reader-text"><?php esc_html_e( 'Renew a License Key', 'edd_sl' ); ?></legend>
	<?php
	$form_class = 'edd-sl-renewal-form-fields';
	if ( $is_checkout ) {
		$form_class .= ' edd-no-js';
		?>
		<button id="edd_sl_show_renewal_form" type="button" class="edd-submit button <?php echo esc_attr( $color ); ?>"><?php esc_html_e( 'Renew An Existing License', 'edd_sl' ); ?></button>
		<?php
	}
	?>
		<div class="<?php echo esc_attr( $form_class ); ?>">
			<div id="edd-license-key-container-wrap" class="edd-cart-adjustment edd-form-group">
			<?php
			$label = __( 'Enter the license key you wish to renew.', 'edd_sl' );
			if ( $is_checkout ) {
				$label .= ' ' . __( 'Leave blank to purchase a new one.', 'edd_sl' );
			}
			?>
				<label for="edd-license-key" class="edd-description edd-form-group__label"><?php echo esc_html( $label ); ?></label>
				<div class="edd-form-group__control">
					<input class="edd-input required edd-form-group__input" type="text" name="edd_license_key" autocomplete="off" placeholder="<?php esc_html_e( 'Enter your license key', 'edd_sl' ); ?>" id="edd-license-key" value="<?php echo $preset_key; ?>"/>
					<input type="hidden" name="edd_action" value="apply_license_renewal"/>
				</div>
			</div>
			<div class="edd-sl-renewal-actions">
				<input type="submit" id="edd-add-license-renewal" disabled class="edd-submit button <?php echo $color; ?>" value="<?php esc_html_e( 'Apply License Renewal', 'edd_sl' ); ?>"/>
				<?php
				if ( $is_checkout ) {
					echo '&nbsp;<button id="edd-cancel-license-renewal" type="button">' . esc_html__( 'Cancel', 'edd_sl' ) . '</button>';
				}
				?>
			</div>

			<?php if ( $is_renewal ) : ?>
				<p class="edd-sl-multiple-keys-notice">
					<span class="edd-description"><?php esc_html_e( 'You may renew multiple license keys at once.', 'edd_sl' ); ?></span>
				</p>
			<?php endif; ?>
		</div>
	</fieldset>
</form>

<?php
if ( $is_renewal ) : ?>
	<form method="post" id="edd_sl_cancel_renewal_form">
		<p>
			<input type="hidden" name="edd_action" value="cancel_license_renewal"/>
			<input type="submit" class="edd-submit button" value="<?php esc_html_e( 'Cancel License Renewal', 'edd_sl' ); ?>"/>
		</p>
	</form>
	<?php
endif;

edd_sl_cart_error_messages();
