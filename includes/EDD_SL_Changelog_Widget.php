<?php
/**
 * EDD Software Licensing Changelog widget
 *
 * Designed to be used on a download page. Shows the changelog.
 *
 * @package EDD_Software_Licensing
 * @subpackage Widgets
 * @copyright Copyright (c) 2014, Lee Willis
 * @since 2.5.3
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_SL_Changelog_Widget' ) ) :
/**
 * EDD Software Licensing Changelog Widget Class
 *
 * @package EDD_Software_Licensing
 * @since 2.5.3
 * @version 2.5.3
 * @author Lee Willis
 * @see WP_Widget
 */
final class EDD_SL_Changelog_Widget extends WP_Widget {

	/**
	 * Constructor Function
	 *
	 * @since 2.5.3
	 * @access public
	 * @see WP_Widget::__construct()
	 */
	public function __construct() {
		parent::__construct(
			false,
			__( 'EDD Software Licensing Changelog', 'edd_sl' ),
			apply_filters(
				'edd_sl_changelog_widget_options',
				array(
					'classname'   => 'widget_sl_changelog',
					'description' => __( 'Display the changelog for a specific download.', 'edd_sl' )
				)
			)
		);
		$this->alt_option_name = 'widget_sl_changelog';
		$this->defaults = array(
			'title' => __( 'Changelog', 'edd_sl' ),
			'download_id' => 'current',
		);
		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * Flush widget cache.
	 *
	 * @since 2.5.3
	 * @access public
	 * @uses wp_cache_delete()
	 * @return void
	 */
	public function flush_widget_cache( $post_id = null ) {
		wp_cache_delete( 'widget_sl_changelog', 'widget' );
	}

	/**
	 * Render the widget output.
	 *
	 * @since 2.5.3
	 * @access public
	 * @return void
	 */
	public function widget( $args, $instance ) {

		if ( $instance['download_id'] == 'current' ) {
			$post = get_queried_object();
			$post_id = isset( $post->ID ) ? $post->ID : null;
		} else {
			$post_id = $instance['download_id'];
		}
		$post_id = apply_filters( 'edd_sl_changeloge_widget_post_id', $post_id, $args, $instance );
		if ( !$post_id ) {
			return;
		}

		extract( $args, EXTR_SKIP );

		// Get cached items if they exist
		$cache = wp_cache_get( 'widget_sl_changelog', 'widget' );
		$cache_arr_key = $args['widget_id'] . '_' . $post_id;

		// Use cached information if it exists
		if ( $cache !== false ) {
			if ( !empty( $cache[$cache_arr_key] ) ) {
				echo $cache[$cache_arr_key];
				return;
			}
		} else {
			$cache = array();
		}

		// Begin output
		$output = '';

		// Otherwise generate the information
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$changelog 	= get_post_meta( $post_id, '_edd_sl_changelog', true );

		if ( ! empty( $changelog ) ) {
			$output .= $before_widget;

			if ( ! empty( $title ) )
				$output .= $before_title . $title . $after_title;

			$output .= '<div class="edd_sl_changelog_widget" id="' . esc_attr( $args['widget_id'] ) . '">';
			$output .= wpautop( stripslashes( $changelog ) );
			$output .= '</div>';
		}

		$output .= $after_widget;

		echo $output;
		$cache[$cache_arr_key] = $output;

		// Puts the reviews data in the cache for performance enhancements
		wp_cache_set( 'widget_sl_changelog', $cache, 'widget' );
	}

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @since 2.5.3
	 * @access public
	 * @uses EDD_Reviews_Per_Product_Reviews_Widget::flush_widget_cache()
	 * @return void
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title']       = strip_tags( $new_instance['title'] );
		$instance['download_id'] = $new_instance['download_id'];
		$this->flush_widget_cache();
		return $instance;
	}

	/**
	 * Generates the administration form for the widget.
	 *
	 * @since 2.5.3
	 * @access public
	 * @param array $instance The array of keys and values for the widget.
	 * @return void
	 */
	public function form( $instance ) {
		$config = array_merge( $this->defaults, $instance );
		extract( $config );
		$query_args = array(
			'post_type'      => 'download',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'asc',
		);
		$downloads = get_posts( $query_args );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'edd_sl' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'download_id' ); ?>"><?php _e( 'Show changelog for:', 'edd_sl' ); ?></label>
			<select class="widefat" name="<?php echo $this->get_field_name( 'download_id' ); ?>" id="<?php echo $this->get_field_id( 'download_id' ); ?>">
				<option value="current"><?php echo esc_html( sprintf( __( 'Current %s', 'edd_sl' ), edd_get_label_singular() ) ); ?></option>
				<?php foreach ( $downloads as $download ) { ?>
					<option <?php selected( $download_id, $download->ID ); ?> value="<?php echo $download->ID; ?>"><?php echo esc_html( $download->post_title ); ?></option>
				<?php } ?>
			</select>
		</p>
		<?php
	}
}

endif;
