<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the widgets.
 */
function edd_sl_register_widgets() {
	register_widget( 'EDD_SL_Changelog_Widget' );
}
add_action( 'widgets_init', 'edd_sl_register_widgets' );