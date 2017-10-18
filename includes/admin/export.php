<?php

/**
 * Renders the license key export box
 *
 * @access      public
 * @since       3.0
 */
function edd_sl_license_export_box() {
?>
	<div class="postbox">
		<h3><span><?php _e( 'Export License Keys', 'edd_sl' ); ?></span></h3>
		<div class="inside">
			<p><?php _e( 'Use this tool to export license keys to a CSV file.', 'edd_sl' ); ?></p>
			<form method="post" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-tools&tab=import_export' ); ?>">
				<p>
					<?php echo EDD()->html->product_dropdown( array( 'chosen' => true, 'name' => 'edd_sl_download_id' ) ); ?>
				</p>
				<p>
					<select name="edd_sl_status">
						<option value="all"><?php _e( 'All License Keys', 'edd_sl' ); ?></option>
						<option value="active"><?php _e( 'Active License Keys', 'edd_sl' ); ?></option>
						<option value="inactive"><?php _e( 'Inactive License Keys', 'edd_sl' ); ?></option>
						<option value="expired"><?php _e( 'Expired License Keys', 'edd_sl' ); ?></option>
					</select>
					<input type="hidden" name="edd_action" value="sl_export_license_keys" />
				</p>
				<p>
					<?php wp_nonce_field( 'edd_sl_export_nonce', 'edd_sl_export_nonce' ); ?>
					<?php submit_button( __( 'Export', 'edd_el' ), 'secondary', 'submit', false ); ?>
				</p>
			</form>
		</div><!-- .inside -->
	</div><!-- .postbox -->
<?php
}
add_action( 'edd_tools_tab_import_export', 'edd_sl_license_export_box' );

/**
 * Processes the license key export
 *
 * @access      public
 * @since       3.0
 */
function edd_sl_process_license_export( $data ) {

	if( empty( $data['edd_sl_export_nonce'] ) ) {
		return;
	}

	check_admin_referer( 'edd_sl_export_nonce', 'edd_sl_export_nonce' );

	require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-export-licenses.php';

	$export = new EDD_SL_License_Export();

	$export->status      = sanitize_text_field( $data['edd_sl_status'] );
	$export->download_id = absint( $data['edd_sl_download_id'] );

	$export->export();

}
add_action( 'edd_sl_export_license_keys', 'edd_sl_process_license_export' );