<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Enqueue admin scripts
 *
 * @since 2.6
 */
function edd_sl_admin_scripts() {
	$screen = get_current_screen();

	if ( ! is_object( $screen ) ) {
		return;
	}

	$allowed_screens = array(
		'download',
		'download_page_edd-licenses',
		'download_page_edd-license-renewal-notice',
		'download_page_edd-reports',
		'download_page_edd-settings',
		'download_page_edd-tools',
		'download_page_edd-payment-history',
		'download_page_edd-customers',
	);

	$allowed_screens = apply_filters( 'edd-sl-admin-script-screens', $allowed_screens );

	if ( ! in_array( $screen->id, $allowed_screens ) ) {
		return;
	}

	wp_enqueue_script( 'edd-sl-admin', plugins_url( '/assets/js/edd-sl-admin.js', EDD_SL_PLUGIN_FILE ), array( 'jquery' ), EDD_SL_VERSION, true );

	if ( $screen->id === 'download' ) {
		wp_localize_script(
			'edd-sl-admin',
			'edd_sl',
			array(
				'download'      => get_the_ID(),
				'no_prices'     => __( 'N/A', 'edd_sl' ),
				'add_banner'    => __( 'Add Banner', 'edd_sl' ),
				'use_this_file' => __( 'Use This Image', 'edd_sl' ),
				'new_media_ui'  => apply_filters( 'edd_use_35_media_ui', 1 ),
				'readme_nonce'  => wp_create_nonce( 'edd_sl_readme_cache_nonce' ),
			)
		);
	} else {
		wp_localize_script(
			'edd-sl-admin',
			'edd_sl',
			array(
				'ajaxurl'           => edd_get_ajax_url(),
				'delete_license'    => __( 'Are you sure you wish to delete this license?', 'edd_sl' ),
				'action_edit'       => __( 'Edit', 'edd_sl' ),
				'action_cancel'     => __( 'Cancel', 'edd_sl' ),
				'send_notice'       => __( 'Send Renewal Notice', 'edd_sl' ),
				'cancel_notice'     => __( 'Cancel Renewal Notice', 'edd_sl' ),
				'regenerate_notice' => __( 'Regenerating a license key is not reversible. Click "OK" to continue.', 'edd_sl' ),
			)
		);
	}

	wp_enqueue_style( 'edd-sl-admin-styles', plugins_url( '/assets/css/edd-sl-admin.css', EDD_SL_PLUGIN_FILE ), false, EDD_SL_VERSION );
	wp_enqueue_style( 'edd-sl-styles', plugins_url( '/assets/css/edd-sl.css', EDD_SL_PLUGIN_FILE ), false, EDD_SL_VERSION );
}
add_action( 'admin_enqueue_scripts', 'edd_sl_admin_scripts' );

/**
 * Enqueue frontend scripts.
 *
 * @since 3.2
 */
function edd_sl_scripts() {
	EDD\SoftwareLicensing\Assets\Loader::style();
}
add_action( 'wp_enqueue_scripts', 'edd_sl_scripts' );

/**
 * Output the SL JavaScript for the checkout page
 *
 * @param boolean $force Optional parameter to allow the script within the shortcode.
 * @since  3.2
 * @return void
 */
function edd_sl_checkout_js( $force = false ) {
	if ( ! edd_sl_renewals_allowed() ) {
		return;
	}

	if ( ! edd_is_checkout() && ! $force ) {
		return;
	}

	$is_checkout = edd_is_checkout() ? 'true' : 'false';
	$script      = "document.addEventListener('DOMContentLoaded', function() {
		var hide = {$is_checkout},
			input = document.querySelector('#edd-license-key');
		if (hide) {
			document.querySelectorAll('.edd-sl-renewal-form-fields').forEach(function(el) {
				el.style.display = 'none';
			});
			document.querySelectorAll('#edd_sl_show_renewal_form, #edd-cancel-license-renewal').forEach(function(el) {
				el.addEventListener('click', function(e) {
					e.preventDefault();
					document.querySelectorAll('.edd-sl-renewal-form-fields, #edd_sl_show_renewal_form').forEach(function(toggleEl) {
						toggleEl.classList.remove( 'edd-no-js' );
						toggleEl.style.display = (toggleEl.style.display === 'none' ? '' : 'none');
					});
					input.focus();
				});
			});
		}

		if (input) {
			input.addEventListener('keyup', function(e) {
				var disabled = !input.value;
				document.querySelector('#edd-add-license-renewal').disabled = disabled;
			});
		}
	});";
	wp_add_inline_script( 'edd-ajax', $script );
}
add_action( 'wp_enqueue_scripts', 'edd_sl_checkout_js' );

function edd_sl_load_edd_admin_scripts( $should_load, $hook ) {
	if ( 'widgets.php' === $hook ) {
		$should_load = true;
	}

	return $should_load;
}
add_filter( 'edd_load_admin_scripts', 'edd_sl_load_edd_admin_scripts', 10, 2 );
