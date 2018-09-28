<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup License Key Post Type
 *
 * Setup the License Log Post Type
 *
 * Registers the License Key CPT.
 *
 * @access      private
 * @since       1.0
 * @return      void
*/

function edd_sl_setup_post_type() {

	register_post_type( 'edd_license', array(
		'labels'             => apply_filters( 'edd_license_labels', array(
			'name'               => _x( 'Licenses', 'post type general name', 'edd_sl' ),
			'singular_name'      => _x( 'License', 'post type singular name', 'edd_sl' ),
			'add_new'            => __( 'Add New', 'edd_sl' ),
			'add_new_item'       => __( 'Add New License', 'edd_sl' ),
			'edit_item'          => __( 'Edit License', 'edd_sl' ),
			'new_item'           => __( 'New License', 'edd_sl' ),
			'all_items'          => __( 'Licenses', 'edd_sl' ),
			'view_item'          => __( 'View License', 'edd_sl' ),
			'search_items'       => __( 'Search Licenses', 'edd_sl' ),
			'not_found'          => __( 'No Licenses found', 'edd_sl' ),
			'not_found_in_trash' => __( 'No Licenses found in Trash', 'edd_sl' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Licenses', 'edd_sl' )
	 	) ),
		'public'             => false,
		'rewrite'            => false,
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => true,
		'supports'           => array( 'title' )
	) );

	register_post_type( 'edd_license_log', array(
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => false,
		'query_var'          => false,
		'rewrite'            => false,
		'capability_type'    => 'page',
		'has_archive'        => false,
		'hierarchical'       => false,
		'supports'           => array( 'title', 'editor' ),
		'taxonomies'         => array( 'edd_log_type' )
	) );

}
add_action( 'init', 'edd_sl_setup_post_type', 2 );

/**
 * Registers the thumbnail sizes for the plugins, so they can be used in the updater icons array.
 * 
 * @since 3.6.5
 *
 * @return void
 */
function edd_sl_register_thumbnail_sizes() {
	add_image_size( 'sl-small', 128, 128, true );
	add_image_size( 'sl-large', 256, 256, true );
}
add_action( 'init', 'edd_sl_register_thumbnail_sizes' );

/**
 * Download Columns
 *
 * Defines the custom columns and their order
 *
 * @since 1.6
 * @param array $download_columns Array of download columns
 * @return array $download_columns Updated array of download columns for Downloads
 *  Post Type List Table
 */
function edd_sl_download_columns( $download_columns ) {
	unset( $download_columns['date'] );
	$download_columns['version'] = __( 'Version', 'edd_sl' );
	$download_columns['date'] = __( 'Date', 'edd_sl' );
	return apply_filters( 'edd_sl_download_columns', $download_columns );
}
add_filter( 'manage_edit-download_columns', 'edd_sl_download_columns' );

/**
 * Render Download Columns
 *
 * @since 1.6
 * @param string $column_name Column name
 * @param int $post_id Download (Post) ID
 * @return void
 */
function edd_sl_render_download_columns( $column_name, $post_id ) {
	if ( get_post_type( $post_id ) == 'download' ) {
		$download = new EDD_SL_Download( $post_id );
		switch ( $column_name ) {
			case 'version':
				echo esc_html( $download->get_version() );
				break;
		}
	}
}
add_action( 'manage_posts_custom_column', 'edd_sl_render_download_columns', 10, 2 );
