<?php
/**
 * Add License Meta Box
 *
 * @since 1.0
 */
function edd_sl_add_license_meta_box() {

	global $post;

	add_meta_box( 'edd_sl_box', __( 'Licensing', 'edd_sl' ), 'edd_sl_render_licenses_meta_box', 'download', 'normal', 'core' );
	add_meta_box( 'edd_sl_upgrade_paths_box', __( 'License Upgrade Paths', 'edd_sl' ), 'edd_sl_render_license_upgrade_paths_meta_box', 'download', 'normal', 'core' );
	add_meta_box( 'edd-generate-missing-licenses', __( 'Generate Missing Licenses', 'edd_sl' ), 'edd_sl_missing_keys_metabox', 'download', 'side', 'low' );

}
add_action( 'add_meta_boxes', 'edd_sl_add_license_meta_box', 100 );



/**
 * Render the download information meta box
 *
 * @since 1.0
 */
function edd_sl_render_licenses_meta_box() {

	global $post;
	// Use nonce for verification
	echo '<input type="hidden" name="edd_sl_meta_box_nonce" value="', wp_create_nonce( basename( __FILE__ ) ), '" />';

	echo '<table class="form-table">';

		$is_bundle = ( 'bundle' == edd_get_download_type( $post->ID ) );

		$enabled    = get_post_meta( $post->ID, '_edd_sl_enabled', true ) ? true : false;
		$limit      = get_post_meta( $post->ID, '_edd_sl_limit', true );
		$version    = get_post_meta( $post->ID, '_edd_sl_version', true );
		$changelog  = get_post_meta( $post->ID, '_edd_sl_changelog', true );
		$keys       = get_post_meta( $post->ID, '_edd_sl_keys', true );
		$file       = get_post_meta( $post->ID, '_edd_sl_upgrade_file_key', true );
		$exp_unit   = get_post_meta( $post->ID, '_edd_sl_exp_unit', true );
		$exp_length = get_post_meta( $post->ID, '_edd_sl_exp_length', true );
		$display    = $enabled ? '' : ' style="display:none;"';

		// Double call for PHP 5.2 compat
		$is_limited = get_post_meta( $post->ID, 'edd_sl_download_lifetime', true );
		$is_limited = empty( $is_limited );

		$display_no_bundle = ( $enabled && ! $is_bundle ) ? '' : ' style="display: none;"';
		$display_is_bundle = ( $enabled && $is_bundle )   ? ' class="edd_sl_toggled_row"' : ' style="display: none;"';
		$display_length    = ( $enabled && $is_limited )  ? '' : ' style="display: none;"';

		echo '<tr>';
			echo '<td class="edd_field_type_text" colspan="2">';
				echo '<input type="checkbox" name="edd_license_enabled" id="edd_license_enabled" value="1" ' . checked( true, $enabled, false ) . '/>&nbsp;';
				echo '<label for="edd_license_enabled">' . __( 'Check to enable license creation', 'edd_sl' ) . '</label>';
				echo '<p' . $display_is_bundle . '>';
				echo __( 'A license key will be generated for each product in this bundle, upon purchase.', 'edd_sl' );
				echo '</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display . ' class="edd_sl_toggled_row">';
			echo '<td class="edd_field_type_text" colspan="2">';
				echo '<input type="number" class="small-text" name="edd_sl_limit" id="edd_sl_limit" value="' . esc_attr( $limit ) . '"/>&nbsp;';
				echo __( 'Limit number of times this license can be activated. Use 0 for unlimited. If using variable prices, set the limit for each price option.', 'edd_sl' );
				printf(
					'<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="' . esc_attr__( '%s' ) . '"></span>',
					__( '<strong>Activation Limit</strong>: Set the number of activations allowed per license. If individual activation limits are set for variable pricing, they take precedence. If your product is a bundle, the activation limit set here will override the activation limits set on the individual products.', 'edd_sl' )
				);
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display_no_bundle . ' class="edd_sl_toggled_row edd_sl_nobundle_row">';
			echo '<td class="edd_field_type_text" colspan="2">';
				echo '<input type="text" size="13" name="edd_sl_version" id="edd_sl_version" value="' . esc_attr( $version ) . '"/>&nbsp;';
				echo __( 'Enter the current version number.', 'edd_sl' );
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display . ' class="edd_sl_toggled_row">';
			echo '<td class="edd_field_type_select">';
				echo '<p>' . __( 'How long are license keys valid for?', 'edd_sl' ) . '</p>';
				echo '<input ' . checked( false, $is_limited, false ) . ' type="radio" id="edd_license_is_lifetime" name="edd_sl_is_lifetime" value="1" /><label for="edd_license_is_lifetime">' . __( 'Lifetime', 'edd_sl' ) . '</label>';
				echo '<br/ >';
				echo '<input ' . checked( true, $is_limited, false ) . ' type="radio" id="edd_license_is_limited" name="edd_sl_is_lifetime" value="0" /><label for="edd_license_is_limited">' . __( 'Limited', 'edd_sl' ) . '</label>';
				echo '<p'  . $display_length . ' class="edd_sl_toggled_row" id="edd_license_length_wrapper">';
					echo '<input type="number" id="edd_sl_exp_length" name="edd_sl_exp_length" class="small-text" value="' . $exp_length . '"/>&nbsp;';
					echo '<select name="edd_sl_exp_unit" id="edd_sl_exp_unit">';
						echo '<option value="days"' . selected( 'days', $exp_unit, false ) . '>' . __( 'Days', 'edd_sl' ) . '</option>';
						echo '<option value="weeks"' . selected( 'weeks', $exp_unit, false ) . '>' . __( 'Weeks', 'edd_sl' ) . '</option>';
						echo '<option value="months"' . selected( 'months', $exp_unit, false ) . '>' . __( 'Months', 'edd_sl' ) . '</option>';
						echo '<option value="years"' . selected( 'years', $exp_unit, false ) . '>' . __( 'Years', 'edd_sl' ) . '</option>';
					echo '</select>';
				echo '</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display_no_bundle . ' class="edd_sl_toggled_row edd_sl_nobundle_row">';
			echo '<td class="edd_field_type_select" colspan="2">';
				echo '<select name="edd_sl_upgrade_file" id="edd_sl_upgrade_file">';
					$files = get_post_meta( $post->ID, 'edd_download_files', true );
					if ( is_array( $files ) ) {
						foreach( $files as $key => $value ) {
							$name = isset( $files[$key]['name'] ) ? $files[$key]['name'] : '';
							echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $file, false ) . '>' . esc_html( $name ) . '</option>';
						}
					}
				echo '</select>&nbsp;';
				echo '<label for="edd_sl_upgrade_file">' . __( 'Choose the source file to be used for automatic updates.', 'edd_sl' ) . '</label>';
			echo '</td>';
		echo '</tr>';


		echo '<tr' . $display_no_bundle . ' class="edd_sl_toggled_row edd_sl_nobundle_row">';
			echo '<td class="edd_field_type_textarea" colspan="2">';
				echo '<label for="edd_sl_changelog">' . __( 'Change Log', 'edd_sl' ) . '</label><br/>';
				wp_editor(
					stripslashes( $changelog ),
					'edd_sl_changelog',
					array(
						'textarea_name' => 'edd_sl_changelog',
						'media_buttons' => false,
						'textarea_rows' => 15,
					)
				);
				echo '<div class="description">' . __( 'Enter details about what changed.', 'edd_sl' ) . '</div>';
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display_no_bundle . ' class="edd_sl_toggled_row edd_sl_nobundle_row">';
			echo '<td class="edd_field_type_textarea" colspan="2">';
				echo '<label for="edd_sl_keys">' . __( 'License Keys', 'edd_sl' ) . '</label><br/>';
				echo '<textarea name="edd_sl_keys" class="edd-sl-keys-input" id="edd_sl_keys" rows="20">' . esc_textarea( stripslashes( $keys ) ) . '</textarea>';
				echo '<div class="description">' . __( 'Enter available license keys, one per line. If empty, keys will be automatically generated. ', 'edd_sl' ) . '</div>';
			echo '</td>';
		echo '</tr>';
	echo '</table>';

}

/**
 * Render the download information meta box
 *
 * @since 1.0
 */
function edd_sl_render_license_upgrade_paths_meta_box()	{

	global $post;

	$enabled   = get_post_meta( $post->ID, '_edd_sl_enabled', true ) ? true : false;

	$paths     = edd_sl_get_upgrade_paths( $post->ID );
?>
	<div id="edd_sl_upgrade_paths_wrapper" class="edd_meta_table_wrap">
		<table class="widefat edd_repeatable_table" width="100%" cellpadding="0" cellspacing="0">
			<thead>
				<tr>
					<th class="sl-upgrade-download"><?php echo edd_get_label_singular(); ?></th>
					<th class="sl-upgrade-price-option"><?php _e( 'Price Option', 'edd_sl' ); ?></th>
					<th class="sl-upgrade-prorate"><?php _e( 'Prorate', 'edd_sl' ); ?></th>
					<th class="sl-upgrade-discount"><?php _e( 'Additional Discount', 'edd_sl' ); ?></th>
					<th class="sl-upgrade-remove"></th>
				</tr>
			</thead>
			<tbody>
			<?php
				if ( ! empty( $paths ) && is_array( $paths ) ) :
					foreach ( $paths as $key => $value ) :
			?>
					<tr class="edd_repeatable_upload_wrapper edd_repeatable_row" data-key="<?php echo esc_attr( $key ); ?>">
						<td>
							<?php
							echo EDD()->html->product_dropdown( array(
								'name'     => 'edd_sl_upgrade_paths[' . $key . '][download_id]',
								'id'       => 'edd_sl_upgrade_paths_' . $key,
								'selected' => $value['download_id'],
								'multiple' => false,
								'chosen'   => true,
								'class'    => 'edd-sl-upgrade-path-download',
							) );
							?>
						</td>
						<td class="pricing">
							<?php

								if( edd_has_variable_prices( $value['download_id'] ) ) {

									$options = array();
									$prices = edd_get_variable_prices( $value['download_id'] );
									if ( ! empty( $prices ) ) {
										foreach ( $prices as $price_key => $price ) {
											$options[ $price_key ] = $prices[ $price_key ]['name'];
										}
									}

									echo EDD()->html->select( array(
										'name'             => 'edd_sl_upgrade_paths[' . $key . '][price_id]',
										'options'          => $options,
										'selected'         => $value['price_id'],
										'show_option_none' => false,
										'show_option_all'  => false,
										'class'            => 'edd-sl-upgrade-path-price-id'
									) );
								} else {
									_e( 'N/A', 'edd_sl' );
								}
							?>
						</td>
						<td class="sl-upgrade-prorate">
							<?php echo EDD()->html->checkbox( array(
								'name'    => 'edd_sl_upgrade_paths[' . $key . '][pro_rated]',
								'value'   => '1',
								'current' => ! empty( $value['pro_rated'] ) ? 1 : 0
							) ); ?>
						</td>
						<td>
							<?php echo EDD()->html->text( array(
								'name'  => 'edd_sl_upgrade_paths[' . $key . '][discount]',
								'value' => esc_attr( $value['discount'] ),
								'placeholder' => __( 'Amount', 'edd' ),
								'class' => 'edd-price-field'
							) ); ?>
						</td>
						<td>
							<a href="#" class="edd_remove_repeatable" data-type="file" style="background: url(<?php echo admin_url('/images/xit.gif'); ?>) no-repeat;">&times;</a>
						</td>
					</tr>
			<?php
					endforeach;
				else :
			?>
				<tr class="edd_repeatable_upload_wrapper edd_repeatable_row">
					<td>
						<?php
						echo EDD()->html->product_dropdown( array(
							'name'     => 'edd_sl_upgrade_paths[1][download_id]',
							'id'       => 'edd_sl_upgrade_paths_1',
							'selected' => ! empty( $post->status ) ? $post->ID : false,
							'multiple' => false,
							'chosen'   => true,
							'class'    => 'edd-sl-upgrade-path-download',
						) );
						?>
					</td>
					<td class="pricing">
						<?php if ( edd_has_variable_prices( $post->ID ) ) : ?>
							<?php
								$options = array();

								$prices = edd_get_variable_prices( $post->ID );
								if ( ! empty( $prices ) ) {
									foreach ( $prices as $price_key => $price ) {
										$options[ $price_key ] = $prices[ $price_key ]['name'];
									}
								}

								echo EDD()->html->select( array(
									'name'             => 'edd_sl_upgrade_paths[1][price_id]',
									'options'          => $options,
									'show_option_none' => false,
									'show_option_all'  => false,
									'class'            => 'edd-sl-upgrade-path-price-id'
								) );
							?>
						<?php else: ?>
							<?php _e( 'N/A', 'edd_sl' ); ?>
						<?php endif; ?>
					</td>
					<td class="sl-upgrade-prorate">
						<?php echo EDD()->html->checkbox( array(
							'name'    => 'edd_sl_upgrade_paths[1][pro_rated]',
							'value'   => '1'
						) ); ?>
					</td>
					<td>
						<?php echo EDD()->html->text( array(
							'name'  => 'edd_sl_upgrade_paths[1][discount]',
							'placeholder' => __( 'Amount', 'edd' ),
							'class' => 'edd-price-field'
						) ); ?>
					</td>
					<td>
						<a href="#" class="edd_remove_repeatable" data-type="file" style="background: url(<?php echo admin_url('/images/xit.gif'); ?>) no-repeat;">&times;</a>
					</td>
				</tr>
			<?php endif; ?>
				<tr>
					<td class="submit" colspan="4" style="float: none; clear:both; background: #fff;">
						<a class="button-secondary edd_add_repeatable" style="margin: 6px 0 10px;"><?php _e( 'Add New Upgrade Path', 'edd' ); ?></a>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<p class="description"><?php _e( 'Configure the optional upgrade paths for customers. ', 'edd_sl' ); ?></p>

<?php
}

/**
 * Renders the Generate Missing Keys meta box
 *
 * @access      public
 * @since       2.6
 * @return      void
 */
function edd_sl_missing_keys_metabox( $post ) {
	?>
	<p class="edd-sl-generate-keys-moved">
		<?php printf( __( 'Missing license keys can be generated for past purchases of this %s from the %sTools%s page', 'edd_sl' ), edd_get_label_singular( true ), '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-tools&tab=general' ) . '">', '</a>' ); ?>
	</p>
	<?php
}

/**
 * Price rows header
 *
 * @access      public
 * @since       2.5
 * @return      void
 */

function edd_sl_prices_header( $download_id ) {
?>
	<th><?php _e( 'License Activation Limit', 'edd_sl' ); ?></th>
<?php
}
add_action( 'edd_download_price_table_head', 'edd_sl_prices_header', 800 );

function edd_sl_lifetime_header( $download_id ) {
?>
	<th><?php _e( 'Lifetime', 'edd_sl' ); ?></th>
<?php
}
add_action( 'edd_download_price_table_head', 'edd_sl_lifetime_header', 801 );

/**
 * Activation limit for price options
 *
 * @access      public
 * @since       2.5
 * @return      void
 */

function edd_sl_price_option_activation_limit( $download_id, $price_id, $args ) {
	$limit = edd_software_licensing()->get_price_activation_limit( $download_id, $price_id );
?>
	<td class="sl-limit">
		<input type="number" min="0" step="1" name="edd_variable_prices[<?php echo $price_id; ?>][license_limit]" id="edd_variable_prices[<?php echo $price_id; ?>][license_limit]" size="4" value="<?php echo absint( $limit ); ?>" />
	</td>
<?php
}
add_action( 'edd_download_price_table_row', 'edd_sl_price_option_activation_limit', 800, 3 );

/**
 * Activation limit for price options
 *
 * @access      public
 * @since       2.5
 * @return      void
 */

function edd_sl_price_option_lifetime( $download_id, $price_id, $args ) {
	$is_lifetime = edd_software_licensing()->get_price_is_lifetime( $download_id, $price_id );
?>
	<td class="sl-lifetime">
		<input <?php checked( true, $is_lifetime, true ); ?> type="checkbox" name="edd_variable_prices[<?php echo $price_id; ?>][is_lifetime]" id="edd_variable_prices[<?php echo $price_id; ?>][is_lifetime]" value="1" />
	</td>
<?php
}
add_action( 'edd_download_price_table_row', 'edd_sl_price_option_lifetime', 801, 3 );


/**
 * Save data from meta box
 *
 * @since 1.0
 */
function edd_sl_download_meta_box_save( $post_id ) {

	global $post;

	// verify nonce
	if ( ! isset( $_POST['edd_sl_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['edd_sl_meta_box_nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	// Check for auto save / bulk edit
	if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX') && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
		return $post_id;
	}

	if ( isset( $_POST['post_type'] ) && 'download' != $_POST['post_type'] ) {
		return $post_id;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	if ( isset( $_POST['edd_license_enabled'] ) ) {
		update_post_meta( $post_id, '_edd_sl_enabled', true );
	} else {
		delete_post_meta( $post_id, '_edd_sl_enabled' );
	}

	if ( isset( $_POST['edd_sl_limit'] ) ) {
		update_post_meta( $post_id, '_edd_sl_limit', ( int ) $_POST['edd_sl_limit'] );
	} else {
		delete_post_meta( $post_id, '_edd_sl_limit' );
	}

	if ( isset( $_POST['edd_sl_version'] ) ) {
		update_post_meta( $post_id, '_edd_sl_version', ( string ) $_POST['edd_sl_version'] );
	} else {
		delete_post_meta( $post_id, '_edd_sl_version' );
	}

	if ( isset( $_POST['edd_sl_upgrade_file'] ) && $_POST['edd_sl_upgrade_file'] !== false ) {
		update_post_meta( $post_id, '_edd_sl_upgrade_file_key', ( int ) $_POST['edd_sl_upgrade_file'] );
	} else {
		delete_post_meta( $post_id, '_edd_sl_upgrade_file_key' );
	}

	if ( isset( $_POST['edd_sl_changelog'] ) ) {
		update_post_meta( $post_id, '_edd_sl_changelog', addslashes( $_POST['edd_sl_changelog'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_edd_sl_changelog' );
	}

	if ( isset( $_POST['edd_sl_is_lifetime'] ) ) {
		$is_lifetime = $_POST['edd_sl_is_lifetime'] === '1' ? 1 : 0;
		update_post_meta( $post_id, 'edd_sl_download_lifetime', $is_lifetime );
	}

	if ( isset( $_POST['edd_sl_exp_unit'] ) ) {
		update_post_meta( $post_id, '_edd_sl_exp_unit', addslashes( $_POST['edd_sl_exp_unit'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_edd_sl_exp_unit' );
	}

	if ( isset( $_POST['edd_sl_exp_length'] ) ) {
		update_post_meta( $post_id, '_edd_sl_exp_length', addslashes( $_POST['edd_sl_exp_length'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_edd_sl_exp_length' );
	}

	if ( isset( $_POST['edd_sl_keys'] ) ) {
		update_post_meta( $post_id, '_edd_sl_keys', addslashes( $_POST['edd_sl_keys'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_edd_sl_keys' );
	}

	if( ! empty( $_POST['edd_sl_upgrade_paths'] ) && is_array( $_POST['edd_sl_upgrade_paths'] ) ) {

		$upgrade_paths = array();

		foreach( $_POST['edd_sl_upgrade_paths'] as $key => $path ) {

			if( empty( $path['download_id'] ) ) {
				continue;
			}

			$upgrade_paths[ $key ][ 'download_id' ] = absint( $path['download_id'] );
			$upgrade_paths[ $key ][ 'price_id' ]    = isset( $path['price_id'] ) ? absint( $path['price_id'] ) : false;
			$upgrade_paths[ $key ][ 'discount' ]    = edd_sanitize_amount( $path['discount'] );
			$upgrade_paths[ $key ][ 'pro_rated' ]   = isset( $path['pro_rated'] ) ? 1 : 0;

		}

		update_post_meta( $post_id, '_edd_sl_upgrade_paths', $upgrade_paths );

	} else {
		delete_post_meta( $post_id, '_edd_sl_upgrade_paths' );
	}

}
add_action( 'save_post', 'edd_sl_download_meta_box_save' );


/**
 * Display the license keys associated with a purchase on the View Order Details screen
 *
 * @since 1.9
 */
function edd_sl_payment_details_meta_box( $payment_id = 0 ) {

	if( ! current_user_can( 'edit_shop_payments' ) ) {
		return;
	}

	$licenses = edd_software_licensing()->get_licenses_of_purchase( $payment_id );

	?>
	<div id="edd-payment-licenses" class="postbox">
		<h3 class="hndle"><?php _e( 'License Keys', 'edd_sl' ); ?></h3>
		<div class="inside">
			<?php if( $licenses ) : ?>
				<table class="wp-list-table widefat fixed" cellspacing="0">
					<tbody id="the-list">
						<?php
						$i = 0;
						foreach ( $licenses as $key => $license ) :
							$key            = get_post_meta( $license->ID, '_edd_sl_key', true );
							$status         = edd_software_licensing()->get_license_status(  $license->ID );
							$status_display = '<span class="edd-sl-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
							?>
							<tr class="<?php if ( $i % 2 == 0 ) { echo 'alternate'; } ?>">
								<td class="name column-name">
									<?php echo $license->post_title; ?>
								</td>
								<td class="price column-key">
									<a href="<?php echo admin_url( 'edit.php?post_type=download&page=edd-licenses&s=' . $key ); ?>" title="<?php _e( 'View License Key', 'edd_sl' ); ?>">
										<?php echo $key; ?>
									</a> - <?php echo $status_display; ?>
								</td>
							</tr>
							<?php
							$i++;
						endforeach;
						?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php if ( current_user_can( 'edit_shop_payments' ) ) : ?>
			<div class="inside">
				<p><?php _e( 'Use this to generate missing license keys for this purchase. If you add a product to the purchase, click this after saving the payment.', 'edd_sl' ); ?></p>
				<a href="<?php echo wp_nonce_url( add_query_arg( array( 'posts' => $payment_id ), admin_url( 'edit.php?post_type=download&page=edd-tools&tab=general' ) ), 'edd_sl_retroactive', 'edd_sl_retroactive' ); ?>" class="button-secondary">
					<?php _e( 'Generate License Keys', 'edd_sl' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div><!-- /.inside -->
	</div><!-- /#edd-payment-licenses -->
	<?php
}
add_action( 'edd_view_order_details_main_after', 'edd_sl_payment_details_meta_box' );

/**
 * Add ReadMe Meta Box
 *
 * @since  2.4
 */
function edd_sl_add_readme_meta_box() {

	global $post;

	if( ! edd_get_option( 'edd_sl_readme_parsing' ) ) {
		return;
	}

	if( 'bundle' == edd_get_download_type( get_the_ID() ) ) {
		return;
	}

	// ReadMe functionality
	add_meta_box( 'edd_sl_readme_box', __( 'Download <code>readme.txt</code> Configuration', 'edd_sl' ), 'edd_sl_readme_meta_box_render', 'download', 'normal', 'default' );

}
add_action( 'add_meta_boxes', 'edd_sl_add_readme_meta_box', 110 );

/**
 * Save the ReadMe metabox when EDD saves other fields.
 * @param  array $fields Existing fields to save
 * @return array         Modified fields
 */
function edd_sl_save_readme_metabox($fields) {

	if( ! edd_get_option( 'edd_sl_readme_parsing' ) ) {
		return $fields;
	}

	$fields[] = '_edd_readme_location';
	$fields[] = '_edd_readme_plugin_homepage';
	$fields[] = '_edd_readme_plugin_added';
	$fields[] = '_edd_readme_plugin_last_updated';
	$fields[] = '_edd_readme_meta';
	$fields[] = '_edd_readme_sections';

	return $fields;
}
add_filter( 'edd_metabox_fields_save', 'edd_sl_save_readme_metabox');

/**
 * Render the download information meta box
 *
 * @since  2.4
 */
function edd_sl_readme_meta_box_render()	{

	global $post;

	edd_sl_render_readme_cache_status();

	edd_sl_readme_meta_box_settings($post->ID);
}

/**
 * Render the readme meta box
 *
 * @since  2.4
 * @param int $post_id Download (Post) ID
 * @return void
 */
function edd_sl_readme_meta_box_settings( $post_id ) {
	global $edd_options;

	if( ! current_user_can( 'manage_shop_settings' ))
		return;

	$readme_location = get_post_meta( $post_id, '_edd_readme_location', true );
	$readme_sections = get_post_meta( $post_id, '_edd_readme_sections', true );
	$readme_meta     = get_post_meta( $post_id, '_edd_readme_meta', true );

	$readme_settings      = array(
		'readme_meta'     => array(
			'title'       => __( 'Download details', 'edd_sl' ),
			'description' => __( 'Use the following data from the remote readme.txt file . ', 'edd_sl' ),
			'settings'    => array(
				'tested_up_to' => __( 'Tested Up To (WordPress Version)', 'edd_sl' ),
				'stable_tag'   => __( 'Stable Tag', 'edd_sl' ),
				'contributors' => __( 'Contributors', 'edd_sl' ),
				'donate_link'  => __( 'Donate Link', 'edd_sl' ),
				'license'      => __( 'License', 'edd_sl' ),
				'license_uri'  => __( 'License URI', 'edd_sl' ),
			)
		),
		'readme_sections' => array(
			'title'       => __( 'Download tabs', 'edd_sl' ),
			'description' => __( 'Override the following sections with content from the remote readme.txt file. Each section appears as a tab in the Update Notice screen . ', 'edd_sl' ),
			'settings'    => array(
				'description'                => __( 'Description (default: Product content field)', 'edd_sl' ),
				'installation'               => __( 'Installation', 'edd_sl' ),
				'frequently_asked_questions' => __( 'FAQ', 'edd_sl' ),
				'changelog'                  => __( 'Changelog (default: "Change Log" field)', 'edd_sl' ),
				'remaining_content'          => __( 'Other Content', 'edd_sl' )
			)
		)
	);
?>
	<p>
		<label for="edd_readme_location"><strong><?php _e( 'Readme.txt Location:', 'edd_sl' ); ?></strong></label>
		<span class="howto"><?php _e( 'What is the URL of the readme.txt file for the download?', 'edd_sl' ); ?></span>
	</p>
	<p>
		<input type="text" name="_edd_readme_location" class="widefat" id="edd_readme_location" value="<?php echo esc_attr( $readme_location ); ?>" size="50" placeholder="http://example.com/wp-content/plugins/example/readme.txt"/>
	</p>
<?php

	$output = '';
	foreach ($readme_settings as $settings_key => $settings_section) {
		$output .= '<p><strong>' . $settings_section['title'] . '</strong><span class="howto">' . $settings_section['description'] . '</span></p>';

		$array_to_check = ${$settings_key};
		$output .= '<ul class="ul-square">';

		foreach ($settings_section['settings'] as $key => $value) {
			$output .= '<li><label><input type="checkbox" class="checkbox" name="_edd_' . $settings_key . '[' . $key . ']" value="' . $key . '" '.checked(array_key_exists($key, (array)$array_to_check), true, false) . ' /> ' . $value . '</li>';
		}

		$output .= '</ul>';
	}

	echo $output;


	$plugin_homepage     = get_post_meta( $post_id, '_edd_readme_plugin_homepage', true );
	$plugin_added        = get_post_meta( $post_id, '_edd_readme_plugin_added', true );
	$plugin_last_updated = get_post_meta( $post_id, '_edd_readme_plugin_last_updated', true );

?>
	<p>
		<label for="edd_readme_plugin_homepage"><strong><?php _e( 'Override plugin homepage:', 'edd_sl' ); ?></strong></label>
		<span class="howto"><?php _e('Leave blank to use the default plugin homepage (the URL of this Download page)', 'edd_sl' ); ?></span>
	</p>
	<p>
		<input type="text" name="_edd_readme_plugin_homepage" class="widefat" id="edd_readme_plugin_homepage" value="<?php echo esc_attr( $plugin_homepage ); ?>" size="50" placeholder="http://www.plugin-homepage.com"/>
	</p>

	<p><strong><?php _e( 'Plugin Dates:', 'edd_sl' ); ?></strong></p>

	<p><label for="edd_readme_plugin_added"><input type="checkbox" name="_edd_readme_plugin_added" id="edd_readme_plugin_added" value="1" <?php checked(!empty($plugin_added), true); ?> /> <?php _e('Use Download "Published on" date as Plugin Added date?', 'edd_sl' ); ?></label></p>

	<p><label for="edd_readme_plugin_last_updated"><input type="checkbox" name="_edd_readme_plugin_last_updated" id="edd_readme_plugin_last_updated" value="1" <?php checked(!empty($plugin_last_updated), true); ?> /> <?php _e('Use the last time this Download was modified as the "Last Modified" date?', 'edd_sl' ); ?></label></p>

	<?php

	// Release some memory
	unset( $plugin_last_updated, $plugin_last_updated, $plugin_homepage, $output, $readme_location, $readme_sections, $readme_settings );
}
