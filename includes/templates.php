<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function edd_sl_add_key_column() {
	echo '<th class="edd_license_key">' . __( 'License Keys', 'edd_sl' ) . '</th>';
}
add_action( 'edd_purchase_history_header_after', 'edd_sl_add_key_column' );

/**
 * Displays a Manage Licenses link in purchase history
 *
 * @since 2.7
 * @param \EDD\Orders\Order|int   $order_or_order_id In EDD 3.0, this is the order object; in 2.x, it is the payment ID.
 * @param array                   $purchase_data     The array of purchase data (not used in EDD 3.0).
 */
function edd_sl_site_management_links( $order_or_order_id, $purchase_data = array() ) {

	$is_upgrade_page = isset( $_GET['view'] ) && $_GET['view'] == 'upgrades';
	$is_manage_page  = isset( $_GET['action'] ) && $_GET['action'] == 'manage_licenses';
	if ( $is_upgrade_page || $is_manage_page ) {
		return;
	}
	if ( $order_or_order_id instanceof \EDD\Orders\Order ) {
		$order_id  = $order_or_order_id->id;
		$downloads = edd_count_order_items(
			array(
				'order_id' => $order_id,
			)
		);
	} else {
		$order_id  = $order_or_order_id;
		$downloads = edd_get_payment_meta_downloads( $order_id );
	}

	if ( $downloads ) :
		$licensing           = edd_software_licensing();
		$manage_licenses_url = add_query_arg(
			array(
				'action'     => 'manage_licenses',
				'payment_id' => urlencode( $order_id ),
			)
		);
		echo '<td class="edd_license_key">';
		if ( edd_is_payment_complete( $order_id ) && $licensing->get_licenses_of_purchase( $order_id ) ) {
			echo '<a href="' . esc_url( $manage_licenses_url ) . '">' . esc_html__( 'View Licenses', 'edd_sl' ) . '</a>';
		} else {
			echo '&ndash;';
		}
		echo '</td>';
	else :
		echo '<td>&mdash;</td>';
	endif;
}
$hook = 'edd_purchase_history_row_end';
if ( function_exists( 'edd_get_orders' ) ) {
	$hook = 'edd_order_history_row_end';
}
add_action( $hook, 'edd_sl_site_management_links', 10, 2 );

add_action( 'edd_order_history_order_details', 'edd_sl_blocks_management_links' );
/**
 * Adds the license keys link to the order history block.
 *
 * @param \EDD\Orders\Order $order
 * @return void
 */
function edd_sl_blocks_management_links( $order ) {
	remove_action( 'edd_order_history_row_end', 'edd_sl_site_management_links' );
	remove_action( 'edd_purchase_history_row_end', 'edd_sl_site_management_links' );

	$is_upgrade_page = isset( $_GET['view'] ) && 'upgrades' === $_GET['view'];
	$is_manage_page  = isset( $_GET['action'] ) && 'manage_licenses' === $_GET['action'];
	if ( $is_upgrade_page || $is_manage_page ) {
		return;
	}
	$statuses  = function_exists( 'edd_get_deliverable_order_item_statuses' ) ? edd_get_deliverable_order_item_statuses() : array( 'complete', 'partially_refunded' );
	$downloads = edd_count_order_items(
		array(
			'order_id'   => $order->id,
			'status__in' => $statuses,
		)
	);
	if ( empty( $downloads ) ) {
		return;
	}
	$licensing = edd_software_licensing();
	if ( ! $licensing->get_licenses_of_purchase( $order->id ) ) {
		return;
	}
	$manage_licenses_url = add_query_arg(
		array(
			'action'     => 'manage_licenses',
			'payment_id' => urlencode( $order->id ),
		)
	);
	?>
	<div class="edd-software-licensing__order">
		<a href="<?php echo esc_url( $manage_licenses_url ); ?>"><?php esc_html_e( 'View Licenses', 'edd_sl' ); ?></a>
	</div>
	<?php
}

/**
 * Override the content of the purchase history page to show our license management UI
 *
 * @param string $content
 *
 * @since 2.7
 *
 * @return string
 */
function edd_sl_override_history_content( $content ) {

	if ( empty( $_GET['action'] ) || 'manage_licenses' != $_GET['action'] ) {
		return $content;
	}

	if ( empty( $_GET['payment_id'] ) ) {
		return $content;
	}

	if ( ! in_the_loop() ) {
		return $content;
	}

	// We only need to run this code once per page.
	remove_filter( 'edd_get_template_part', 'edd_sl_override_template_part', 10 );

	if ( isset( $_GET['license_id'] ) && isset( $_GET['view'] ) && 'upgrades' == $_GET['view'] ) {

		ob_start();
		edd_get_template_part( 'licenses', 'upgrades' );
		$content = ob_get_clean();

	} else {

		$view = isset( $_GET['license_id'] ) ? 'single' : 'overview';

		ob_start();
		edd_get_template_part( 'licenses', 'manage-' . $view );
		$content = ob_get_clean();

	}

	return $content;
}
add_filter( 'the_content', 'edd_sl_override_history_content', 10, 3 );

/**
 * Override template parts to show our license management UI
 *
 * @link https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1517
 *
 * @param array       $templates Template stack.
 * @param string      $slug      Template slug.
 * @param string|null $name      Optional. Template name.
 *
 * @since 3.7.1
 * @return array
 */
function edd_sl_override_template_part( $templates, $slug, $name ) {

	if ( empty( $_GET['action'] ) || 'manage_licenses' != $_GET['action'] ) {
		return $templates;
	}

	if ( empty( $_GET['payment_id'] ) ) {
		return $templates;
	}

	// Bail if in The Loop. Then `edd_sl_override_history_content()` will run instead.
	if ( in_the_loop() ) {
		return $templates;
	}

	if (
		// [purchase_history] shortcode
		( 'history' !== $slug && 'purchases' !== $name ) &&
		// [edd_license_keys] shortcode
		( 'license' !== $slug && 'keys' !== $name )
	) {
		return $templates;
	}

	// We only need to run this code once per page.
	remove_filter( 'edd_get_template_part', 'edd_sl_override_history_content', 10 );

	if ( isset( $_GET['license_id'] ) && isset( $_GET['view'] ) && 'upgrades' == $_GET['view'] ) {

		$templates = array(
			'licenses-upgrades.php',
			'licenses.php',
		);

	} else {

		$view = isset( $_GET['license_id'] ) ? 'single' : 'overview';

		$templates = array(
			'licenses-manage-' . $view . '.php',
			'licenses.php',
		);

	}

	return $templates;
}
add_filter( 'edd_get_template_part', 'edd_sl_override_template_part', 10, 3 );

/**
 * Adds our templates dir to the EDD template stack
 *
 * @since 2.7
 */
function edd_sl_add_template_stack( $paths ) {

	$paths[50] = EDD_SL_PLUGIN_DIR . 'templates/';

	return $paths;
}
add_filter( 'edd_template_paths', 'edd_sl_add_template_stack' );


/**
 * Display license keys on the [edd_receipt] short code
 *
 * @access      private
 * @since       1.3.6
 * @param \EDD\Orders\Order|EDD_Payment $order_or_payment Order (EDD 3.0) or payment (2.x) object.
 * @param array                         $edd_receipt_args Receipt arguments.
 * @return      void
 */

function edd_sl_show_keys_on_receipt( $order_or_payment, $edd_receipt_args ) {

	if ( empty( $order_or_payment ) ) {
		return;
	}
	if ( $order_or_payment instanceof \EDD\Orders\Order ) {
		$order_id = $order_or_payment->id;
		$payment  = edd_get_payment( $order_or_payment->id );
	} else {
		$order_id = $order_or_payment->ID;
		$payment  = $order_or_payment;
	}
	if ( empty( $order_id ) || empty( $payment ) ) {
		return;
	}

	$licensing = edd_software_licensing();
	$licenses  = apply_filters( 'edd_sl_licenses_of_purchase', $licensing->get_licenses_of_purchase( $order_id ), $payment, $edd_receipt_args );

	if ( ! empty( $licenses ) ) {
		echo '<tr class="edd_license_keys">';
			echo '<td colspan="2"><strong>' . __( 'License Keys:', 'edd_sl' ) . '</strong></td>';
		echo '</tr>';
		foreach ( $licenses as $license ) {
			echo '<tr class="edd_license_key">';
				echo '<td>';
					echo $licensing->get_license_download_display_name( $license );
			if ( 'expired' == $license->status ) {
				echo '<span class="edd_sl_license_key_expired">&nbsp;(' . __( 'expired', 'edd_sl' ) . ')</span>';
			} elseif ( 'disabled' === $license->status ) {
				echo '<span class="edd_sl_license_key_revoked">&nbsp;(' . __( 'disabled', 'edd_sl' ) . ')</span>';
			}
				echo '</td>';
			if ( $license ) {
				echo '<td>';
					echo '<span class="edd_sl_license_key">' . $license->key . '</span>';
				echo '</td>';
			} else {
				echo '<td><span class="edd_sl_license_key edd_sl_none">' . __( 'none', 'edd_sl' ) . '</span></td>';
			}
			echo '</tr>';
		}
	}
}
$hook = 'edd_payment_receipt_after';
if ( function_exists( 'edd_get_orders' ) ) {
	$hook = 'edd_order_receipt_after';
}
add_action( $hook, 'edd_sl_show_keys_on_receipt', 10, 2 );

add_action( 'edd_order_receipt_order_details', 'edd_sl_show_keys_on_block_receipt' );
/**
 * Adds license keys to the EDD Receipt block.
 *
 * @param \EDD\Orders\Order $order
 * @return void
 */
function edd_sl_show_keys_on_block_receipt( $order ) {
	remove_action( 'edd_order_receipt_after', 'edd_sl_show_keys_on_receipt', 10, 2 );
	$licenses = edd_software_licensing()->get_licenses_of_purchase( $order->id );
	if ( empty( $licenses ) ) {
		return;
	}
	?>
	<div class="edd-blocks__row edd-blocks-receipt__row-item">
		<div class="edd-blocks__row-label"><?php esc_html_e( 'License Keys:', 'edd_sl' ); ?></div>
	</div>
	<?php
	foreach ( $licenses as $license ) {
		?>
		<div class="edd-blocks__row edd-blocks-receipt__row-item edd-blocks-receipt__row-item--license-key">
			<div class="edd-blocks__row-sublabel">
				<?php
				echo wp_kses_post( edd_software_licensing()->get_license_download_display_name( $license ) );
				if ( 'expired' === $license->status ) {
					echo '<span class="edd_sl_license_key_expired">&nbsp;(' . esc_html__( 'expired', 'edd_sl' ) . ')</span>';
				} elseif ( 'disabled' === $license->status ) {
					echo '<span class="edd_sl_license_key_revoked">&nbsp;(' . esc_html__( 'disabled', 'edd_sl' ) . ')</span>';
				}
				?>
			</div>
			<div class="edd-blocks__row-value">
				<span class="edd_sl_license_key"><?php echo esc_html( $license->key ); ?></span>
			</div>
		</div>
		<?php
	}
}

/**
 * Hide download links for expired licenses on purchase receipt page
 *
 * @since       2.3
 * @since       3.6 - Updated to use EDD_Software_Licensing->license_can_download to support multiple licenses for same ID
 *
 * @param       bool $show If we should show or hide the links to download on the purchase receipt
 * @param       int  $item The Item ID that was purchased (download ID)
 * @param       array $receipt_args Array of arguments for the item, of which we use `id` for the Payment/Order ID
 *
 * @return      bool
 */
function edd_sl_hide_downloads_on_expired( $show, $item, $receipt_args ) {
	$can_download = edd_software_licensing()->license_can_download( $item, '', $receipt_args['id'], array() );
	if ( true === $can_download['success'] ) {
		$show = true;
	} elseif ( false === $can_download['success'] ) {
		$show = false;
	}
	return $show;
}
add_filter( 'edd_receipt_show_download_files', 'edd_sl_hide_downloads_on_expired', 10, 3 );
