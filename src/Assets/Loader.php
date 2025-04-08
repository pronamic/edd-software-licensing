<?php
/**
 * Software Licensing Assets Loader
 *
 * @package EDD
 * @subpackage SoftwareLicensing
 * @since 3.8.12
 */

namespace EDD\SoftwareLicensing\Assets;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Loader
 *
 * @since 3.8.12
 */
class Loader {

	/**
	 * Load the Software Licensing styles.
	 *
	 * @since 3.8.12
	 */
	public static function style() {
		global $post;

		if ( ! is_object( $post ) ) {
			return;
		}

		$load_scripts_manually = apply_filters( 'edd_sl_load_styles', false );

		wp_register_style( 'edd-sl-styles', plugins_url( '/assets/css/edd-sl.css', EDD_SL_PLUGIN_FILE ), false, EDD_SL_VERSION );

		if ( self::should_load_styles() || true === $load_scripts_manually ) {
			wp_enqueue_style( 'edd-sl-styles' );
		}
	}

	/**
	 * Determine if we should load the styles.
	 *
	 * @since 3.8.12
	 * @return bool
	 */
	private static function should_load_styles() {
		if ( is_admin() || edd_is_checkout() || edd_is_success_page() ) {
			return true;
		}

		if ( is_page( edd_get_option( 'success_page', false ) ) ) {
			return true;
		}

		global $post;
		if ( has_shortcode( $post->post_content, 'purchase_history' ) || has_shortcode( $post->post_content, 'edd_license_keys' ) ) {
			return true;
		}

		$inline_upgrade_links_enabled = edd_get_option( 'edd_sl_inline_upgrade_links', false );
		if ( $inline_upgrade_links_enabled && ( has_shortcode( $post->post_content, 'purchase_link' ) || has_shortcode( $post->post_content, 'downloads' ) ) ) {
			return true;
		}

		if ( $inline_upgrade_links_enabled && 'download' === get_post_type() ) {
			return true;
		}

		return false;
	}
}
