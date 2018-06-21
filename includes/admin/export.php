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
			<form id="edd-sl-export-license-keys" class="edd-export-form edd-import-export-form" method="post">
				<?php echo EDD()->html->product_dropdown( array( 'chosen' => true, 'name' => 'edd_sl_download_id' ) ); ?>
				<select name="edd_sl_status">
					<option value="all"><?php _e( 'All License Keys', 'edd_sl' ); ?></option>
					<option value="active"><?php _e( 'Active License Keys', 'edd_sl' ); ?></option>
					<option value="inactive"><?php _e( 'Inactive License Keys', 'edd_sl' ); ?></option>
					<option value="expired"><?php _e( 'Expired License Keys', 'edd_sl' ); ?></option>
				</select>
				<?php wp_nonce_field( 'edd_ajax_export', 'edd_ajax_export' ); ?>
				<input type="hidden" name="edd-export-class" value="EDD_SL_License_Export"/>
				<?php wp_nonce_field( 'edd_sl_export_nonce', 'edd_sl_export_nonce' ); ?>
				<?php submit_button( __( 'Export', 'edd_sl' ), 'secondary', 'submit', false ); ?>
			</form>
		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
}
add_action( 'edd_tools_tab_import_export', 'edd_sl_license_export_box' );

/**
 * Register the license keys batch exporter.
 *
 * @since 3.6
 */
function edd_sl_register_batch_export() {
	add_action( 'edd_batch_export_class_include', 'edd_sl_include_batch_processor', 10, 1 );
}
add_action( 'edd_register_batch_exporter', 'edd_sl_register_batch_export', 10 );

/**
 * Loads the API requests batch process if needed
 *
 * @since  2.7
 * @param  string $class The class being requested to run for the batch export
 * @return void
 */
function edd_sl_include_batch_processor( $class ) {
	if ( 'EDD_SL_License_Export' === $class ) {
		require_once EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-export-licenses.php';
	}
}