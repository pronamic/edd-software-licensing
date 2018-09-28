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
			'title'        => __( 'Changelog', 'edd_sl' ),
			'display_type' => 'current',
			'download_id'  => 0,
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
		$defaults = $this->defaults;
		$instance = wp_parse_args( $instance, $defaults );

		if ( ! empty( $instance['download_id'] ) ) {
			if ( 'current' === ( $instance['download_id'] ) ) {
				$instance['display_type'] = 'current';
				unset( $instance['download_id'] );
			} elseif ( is_numeric( $instance['download_id'] ) ) {
				$instance['display_type'] = 'specific';
			}
		}

		// set correct download ID.
		$download_id = 0;

		if ( 'current' == $instance['display_type'] && is_singular( 'download' ) ) {
			$download_id = get_the_ID();
		} else if ( ! empty( $instance['download_id'] ) ) {
			$download_id = absint( $instance['download_id'] );
		}

		$download_id = apply_filters( 'edd_sl_changeloge_widget_post_id', $download_id, $args, $instance );

		// Added in 3.6.5 - Fixing spelling error in `changeloge` from previous versions.
		$download_id = apply_filters( 'edd_sl_changelog_widget_post_id', $download_id, $args, $instance );

		if ( ! $download_id ) {
			return;
		}

		extract( $args, EXTR_SKIP );

		$download = new EDD_SL_Download( $download_id );

		// Get cached items if they exist
		$cache = wp_cache_get( 'widget_sl_changelog', 'widget' );
		$cache_arr_key = $args['widget_id'] . '_' . $download_id;

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

		$changelog 	= $download->get_changelog();

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

		$instance['title']        = strip_tags( $new_instance['title'] );
		$instance['download_id']  = strip_tags( $new_instance['download_id'] );
		$instance['display_type'] = isset( $new_instance['display_type'] ) ? strip_tags( $new_instance['display_type'] ) : '';

		do_action( 'edd_sl_changelog_widget_update', $instance );

		// If the new view is 'current download' then remove the specific download ID
		if ( 'current' === $instance['display_type'] ) {
			unset( $instance['download_id'] );
		}

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
		$instance = wp_parse_args( $instance, $this->defaults );

		if ( ! empty( $instance['download_id'] ) ) {
			if ( 'current' === ( $instance['download_id'] ) ) {
				$instance['display_type'] = 'current';
				unset( $instance['download_id'] );
			} elseif ( is_numeric( $instance['download_id'] ) ) {
				$instance['display_type'] = 'specific';
			}
		}

		// set correct download ID.
		$download_id = isset( $instance['download_id'] ) ? absint( $instance['download_id'] ) : 0;
		?>
		<script>
			$( document ).ready(function() {
				// When the document is loaded, be sure to just trigger the width on the download chosen field so it's ready
				// when the user asks to view it by expanding the widget form.
				$('#<?php echo $this->id_base . '_download_id_' . $this->number . '_chosen'; ?>' ).css('width', '100%' );

				// After you 'save' a widget, the input field loses the 'chosen' state, so we have to re-trigger it again.
				$(document).on( 'widget-updated', function( widget ) {
					var save_button = widget.currentTarget.activeElement.id;
					if ( save_button === 'widget-<?php echo $this->id_base; ?>-<?php echo $this->number; ?>-savewidget') {
						$('#<?php echo $this->id_base . '_download_id_' . $this->number ; ?>' ).chosen();
					}
				});
			});
		</script>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'edd_sl' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>

		<p>
			<?php _e( 'Show Changelog For:', 'edd_sl' ); ?><br />
			<input type="radio" onchange="jQuery(this).parent().next('.download-details-selector').hide();" <?php checked( 'current', $instance['display_type'], true ); ?> value="current" name="<?php echo esc_attr( $this->get_field_name( 'display_type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'display_type' ) ); ?>-current"><label for="<?php echo esc_attr( $this->get_field_id( 'display_type' ) ); ?>-current"><?php _e( 'Current', 'edd_sl' ); ?></label>
			<input type="radio" onchange="jQuery(this).parent().next('.download-details-selector').show().find('div.chosen-container').css('width', '100%');" <?php checked( 'specific', $instance['display_type'], true ); ?> value="specific" name="<?php echo esc_attr( $this->get_field_name( 'display_type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'display_type' ) ); ?>-specific"><label for="<?php echo esc_attr( $this->get_field_id( 'display_type' ) ); ?>-specific"><?php _e( 'Specific', 'edd_sl' ); ?></label>
		</p>

		<!-- Download -->
		<?php $display = 'current' === $instance['display_type'] ? ' style="display: none;"' : ''; ?>
		<p class="download-details-selector" <?php echo $display; ?>>
			<label for="<?php echo $this->id_base . '-download-id-' . $this->number; ?>"><?php printf( __( '%s:', 'edd_sl' ), edd_get_label_singular() ); ?></label><br />
			<?php
			echo EDD()->html->product_dropdown( array(
				'name'        => $this->get_field_name( 'download_id' ),
				'id'          => $this->id_base . '-download-id-' . $this->number,
				'class'       => 'download-details-selector',
				'selected'    => $download_id,
				'chosen'      => true,
				'number'      => 15,
				'bundles'     => false,
				'placeholder' => sprintf( __( 'Choose a %s', 'edd_sl' ), edd_get_label_singular() ),
				'data'        => array(
					'search-type'        => 'download',
					'search-placeholder' => sprintf( __( 'Type to search all %s', 'edd_sl' ), edd_get_label_plural() )
				),
			) );
			?>
		</p>
		<?php
	}
}

endif;
