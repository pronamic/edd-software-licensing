<?php

/*
 * This class handles generating license keys for purchases made before Software Licensing was activated
 */
class EDD_SL_Retroactive_Licensing {

	/**
	 * Setup actions
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	*/
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'edd_tools_banned_emails_after', array( $this, 'tool_box' ) );
		add_action( 'wp_ajax_edd_sl_process_retroactive_post', array( $this, 'edd_sl_process_retroactive_post' ) );
	}

	/**
	 * Render the admin UI under Downloads > Tools
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	*/
	public function tool_box() {
		// Capability check
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( $this->post_id, esc_html__( 'Your user account doesn\'t have permission to access this.', 'edd_sl' ), array( 'response' => 401 ) );
		}
?>
		<div class="postbox edd-sl-retroactive-licensing">
			<h3><span><?php _e( 'Software Licensing - Retroactive Licensing Processor', 'edd_sl' ); ?></span></h3>
			<div class="inside">
<?php
				// If the button was clicked
				if ( ! empty( $_POST[ 'edd-retroactive-licensing' ] ) || ! empty( $_REQUEST['posts'] ) ) {
					if ( ! wp_verify_nonce( $_REQUEST['edd_sl_retroactive'], 'edd_sl_retroactive' ) ) {
						wp_die();
					}

					if ( ! empty( $_REQUEST['posts'] ) ) {
						$posts = array( intval( $_REQUEST['posts'] ) );
					} else {
						$posts = self::get_unlicensed_payments();
					}

					$count = count( $posts );
					if ( ! $count ) {
						?>
						<div class="postbox">
							<h3><span><?php _e( 'All Done', 'edd_sl' ); ?></span></h3>
							<div class="inside">
								<h4><?php _e( 'All Done', 'edd_sl' ); ?></h4>
								<p><?php _e( 'No purchases needing licenses found.', 'edd_sl' ); ?></p>
							</div><!-- .inside -->
						</div><!-- .postbox -->
						<?php
						return;
					}

					$posts       = "'" . implode( "','", $posts ) . "'";
					$download_id = ! empty( $_REQUEST['edd_sl_single_id'] ) ? absint( $_REQUEST['edd_sl_single_id'] ) : 0;
					$this->show_status( $count, $posts, $download_id );
				} else {
?>
					<p><?php _e( 'Use this tool to provision licenses for unlicensed Easy Digital Downloads products.', 'edd_sl' ); ?></p>
					<p><?php _e( 'This processing is not reversible. Backup your database beforehand or be prepared to revert each transformed post manually.', 'edd_sl' ); ?></p>
					<form method="post" action="">
						<?php wp_nonce_field( 'edd-retroactive-licensing' ); ?>
						<p>
							<span id="sl-retro-type-wrapper">
								<select name="sl_retro_type" id="sl-retro-type">
									<option value="all"><?php printf( __( 'All %s', 'edd_sl' ), edd_get_label_plural() ); ?></option>
									<option value="single"><?php printf( __( 'Single %s', 'edd_sl' ), edd_get_label_singular() ); ?></option>
								</select>
							<span>
							<span id="sl-retro-single-wrapper" style="display:none;">
								<?php echo EDD()->html->product_dropdown( array( 'chosen' => true, 'name' => 'edd_sl_single_id', 'id' => 'edd-sl-single-id' ) ); ?>
							</span>
							<input type="submit" class="button hide-if-no-js" name="edd-retroactive-licensing" id="edd-retroactive-licensing" value="<?php _e( 'Generate License Keys for Past Purchases', 'edd_sl' ) ?>" />
							<?php wp_nonce_field( 'edd_sl_retroactive', 'edd_sl_retroactive' ); ?>
						</p>
						<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'edd_sl' ) ?></em></p></noscript>
					</form>
<?php
				}
?>
			</div><!-- .inside -->
		</div><!-- .postbox -->
<?php

	}

	/**
	 * Display the license generation status
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	*/
	public function show_status( $count, $posts, $download_id ) {
		echo '<p>' . esc_html__( 'Please be patient while this script runs. This can take a while, up to a minute per payment. Do not navigate away from this page until this script is done or the licensing will not be completed. You will be notified via this page when the licensing is completed.', 'edd_sl' ) . '</p>';

		$text_goback = ( ! empty( $_GET['goback'] ) ) ? sprintf( __( 'To go back to the previous page, <a href="%s">click here</a>.', 'edd_sl' ), 'javascript:history.go(-1)' ) : '';

		$text_failures = sprintf( __( 'All done! %1$s posts were successfully processed in %2$s seconds and there were %3$s failures. To try importing the failed posts again, <a href="%4$s">click here</a>. %5$s', 'edd_sl' ), "' + rt_successes + '", "' + rt_totaltime + '", "' + rt_errors + '", esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=download&?page=edd-retroactive-licensing&goback=1' ) ) . '&posts=' ) . "' + rt_failedlist + '", $text_goback );

		$text_nofailures = sprintf( esc_html__( 'All done! %1$s posts were successfully processed in %2$s seconds and there were no failures. %3$s', 'edd_sl' ), "' + rt_successes + '", "' + rt_totaltime + '", $text_goback );
?>

		<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'edd_sl' ) ?></em></p></noscript>

		<div id="wpsposts-bar">
			<div id="wpsposts-bar-percent"></div>
		</div>

		<p><input type="button" class="button hide-if-no-js" name="wpsposts-stop" id="wpsposts-stop" value="<?php _e( 'Abort Licensing Posts', 'edd_sl' ) ?>" /></p>

		<h3 class="title"><?php _e( 'Status', 'edd_sl' ) ?></h3>

		<p>
			<?php printf( esc_html__( 'Total Payments: %s', 'edd_sl' ), $count ); ?><br />
			<?php printf( esc_html__( 'Payments Processed: %s', 'edd_sl' ), '<span id="wpsposts-debug-successcount">0</span>' ); ?><br />
			<?php printf( esc_html__( 'License Failures: %s', 'edd_sl' ), '<span id="wpsposts-debug-failurecount">0</span>' ); ?>
		</p>

		<ol id="wpsposts-debuglist">
			<li style="display:none"></li>
		</ol>

		<script type="text/javascript">
		// <![CDATA[
			jQuery(document).ready(function($){
				var i;
				var rt_posts = [<?php echo $posts; ?>];
				var rt_total = rt_posts.length;
				var rt_single_id  = <?php echo $download_id; ?>;
				var rt_count = 1;
				var rt_percent = 0;
				var rt_successes = 0;
				var rt_errors = 0;
				var rt_failedlist = '';
				var rt_resulttext = '';
				var rt_timestart = new Date().getTime();
				var rt_timeend = 0;
				var rt_totaltime = 0;
				var rt_continue = true;

				// Create the progress bar
				$( "#wpsposts-bar" ).progressbar();
				$( "#wpsposts-bar-percent" ).html( "0%" );

				// Stop button
				$( "#wpsposts-stop" ).click(function() {
					rt_continue = false;
					$( '#wpsposts-stop' ).val( "<?php echo esc_html__( 'Stopping, please wait a moment.', 'edd_sl' ); ?>" );
				});

				// Clear out the empty list element that's there for HTML validation purposes
				$( "#wpsposts-debuglist li" ).remove();

				// Called after each import. Updates debug information and the progress bar.
				function WPSPostsUpdateStatus( id, success, response ) {
					$( "#wpsposts-bar" ).progressbar( "value", ( rt_count / rt_total ) * 100 );
					$( "#wpsposts-bar-percent" ).html( Math.round( ( rt_count / rt_total ) * 1000 ) / 10 + "%" );
					rt_count = rt_count + 1;

					if ( success ) {
						rt_successes = rt_successes + 1;
						$( "#wpsposts-debug-successcount" ).html(rt_successes);
						$( "#wpsposts-debuglist" ).append( "<li>" + response.success + "</li>" );
					}
					else {
						rt_errors = rt_errors + 1;
						rt_failedlist = rt_failedlist + ',' + id;
						$( "#wpsposts-debug-failurecount" ).html(rt_errors);
						$( "#wpsposts-debuglist" ).append( "<li>" + response.error + "</li>" );
					}
				}

				// Called when all posts have been processed. Shows the results and cleans up.
				function WPSPostsFinishUp() {
					rt_timeend = new Date().getTime();
					rt_totaltime = Math.round( ( rt_timeend - rt_timestart ) / 1000 );

					$( '#wpsposts-stop' ).hide();

					if ( rt_errors > 0 ) {
						rt_resulttext = '<?php echo $text_failures; ?>';
					} else {
						rt_resulttext = '<?php echo $text_nofailures; ?>';
					}

					$( "#message" ).html( "<p><strong>" + rt_resulttext + "</strong></p>" );
					$( "#message" ).show();
				}

				// Regenerate a specified image via AJAX
				function WPSPosts( id, dl ) {
					$.ajax({
						type: 'POST',
						url: ajaxurl,
						data: {
							action: "edd_sl_process_retroactive_post",
							id: id,
							dl: dl
						},
						success: function( response ) {
							if ( response.success ) {
								WPSPostsUpdateStatus( id, true, response );
							}
							else {
								WPSPostsUpdateStatus( id, false, response );
							}

							if ( rt_posts.length && rt_continue ) {
								WPSPosts( rt_posts.shift(), dl );
							}
							else {
								WPSPostsFinishUp();
							}
						},
						error: function( response ) {
							WPSPostsUpdateStatus( id, false, response );

							if ( rt_posts.length && rt_continue ) {
								WPSPosts( rt_posts.shift(), dl );
							}
							else {
								WPSPostsFinishUp();
							}
						}
					});
				}

				WPSPosts( rt_posts.shift(), rt_single_id );
			});
		// ]]>
		</script>
<?php
	}

	/**
	 * Retrieve all downloads that have licensing enabled
	 *
	 * @access      public
	 * @since       2.4
	 * @return      array
	*/
	public static function get_licensed_products() {
		$args = array(
			'post_type' => 'download',
			'nopaging'  => true,
			'fields'    => 'ids',
			'meta_key'  => '_edd_sl_enabled',
			'value'     => '1'
		);

		return get_posts( $args );
	}

	/**
	 * Get all payments that are missing license keys
	 *
	 * @access      public
	 * @since       2.4
	 * @return      array
	*/
	public static function get_unlicensed_payments() {
		global $wpdb;

		// Create the list of payment IDs
		if ( ! empty( $_REQUEST['edd_sl_single_id'] ) ) {
			$products = array( absint( $_REQUEST['edd_sl_single_id'] ) );
		} else {
			$products = self::get_licensed_products();
		}

		if ( empty( $products ) ) {
			return array();
		}

		// Gather all the payments, and individual download IDs so we can verify licenses exist
		$args = array(
			'download' => $products,
			'number'   => -1,
			'fields'   => 'ids',
		);
		$query    = new EDD_Payments_Query( $args );
		$payments = $query->get_payments();
		$payments = wp_list_pluck( $payments, 'ID' );

		return $payments;
	}



	/**
	 * Process an ajax post to generate keys for a license
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	*/
	public function edd_sl_process_retroactive_post() {

		error_reporting( 0 ); // Don't break the JSON result
		header( 'Content-type: application/json' );

		if ( ! current_user_can( 'edit_shop_payments' ) ) {
			die( json_encode( array( 'error' => __( 'Failed Licensing: You do not have permission to perform this action.', 'edd_sl' ) ) ) );
		}

		$payment_id = intval( $_REQUEST['id'] );
		$post       = get_post( $payment_id );
		$download   = intval( $_REQUEST['dl'] );

		if ( ! $post || $post->post_type != 'edd_payment' )
			die( json_encode( array( 'error' => sprintf( esc_html__( 'Failed Licensing: %s is incorrect post type.', 'edd_sl' ), esc_html( $payment_id ) ) ) ) );

		$number_generated = self::generate_license_keys( $payment_id, $download );

		if ( ! empty( $number_generated ) ) {
			$message = _n( '%1$s License key for Payment ID <a href="%2$s" target="_blank">%3$s</a> was successfully processed.', '%1$s License keys for Payment ID <a href="%2$s" target="_blank">%3$s</a> were successfully processed.', $number_generated, 'edd_sl' );
			die( json_encode( array( 'success' => sprintf( $message, $number_generated, self::get_order_url( $payment_id ), $payment_id ) ) ) );
		} else if ( 0 === $number_generated ) {
			die( json_encode( array( 'success' => sprintf( __( 'Payment ID <a href="%1$s" target="_blank">%2$s</a> processed. No licenses needed to be processed.', 'edd_sl' ), self::get_order_url( $payment_id ), $payment_id ) ) ) );
		} else {
			die( json_encode( array( 'error' => sprintf( __( 'Payment ID <a href="%1$s" target="_blank">%2$s</a> was NOT licensed because "%4$".', 'edd_sl' ), self::get_order_url( $payment_id ), $payment_id, $number_generated ) ) ) );
		}
	}

	/**
	 * Generate the license keys for a payment during an ajax post
	 *
	 * @access      public
	 * @since       2.4
	 * @return      mixed
	*/
	public static function generate_license_keys( $payment_id, $download_id ) {
		$payment_id = absint( $payment_id );
		if ( empty( $payment_id ) ) {
			return esc_html__( 'Empty `$payment_id`', 'edd_sl' );
		}

		$payment   = new EDD_Payment( $payment_id );
		$downloads = $payment->cart_details;

		if ( empty( $downloads ) ) {
			return esc_html__( 'No payment downloads found', 'edd_sl' );
		}

		$keys_generated = 0;

		foreach ( $downloads as $cart_key => $download ) {

			if ( ! empty( $download_id ) && (int) $download['id'] !== (int) $download_id ) {
				continue; // We've been told to only generate for a specific download, and this wasn't it
			}

			$item    = new EDD_Download( $download['id'] );
			$type    = $item->is_bundled_download() ? 'bundle' : 'default';
			$license = edd_software_licensing()->get_license_by_purchase( $payment_id, $download['id'], $cart_key, false );

			if( $license ) {

				if ( 'bundle' === $type ) {

					$price_id = isset( $download['item_number']['options']['price_id'] ) ? $download['item_number']['options']['price_id'] : false;
					$keys     = $license->create( $download['id'], $payment_id, $price_id , $cart_key );

				} else {
					continue; // This product already has keys
				}

			} else {

				$keys = edd_software_licensing()->generate_license( $download['id'], $payment_id, $type, $download, $cart_key );

			}

			$keys_generated = $keys_generated + count( $keys );

		}

		return $keys_generated;
	}

	/**
	 * Load necessary scripts
	 *
	 * @access      public
	 * @since       2.4
	 * @return      void
	*/
	public static function scripts( $hook ) {

		if ( 'download_page_edd-tools' == $hook ) {
			wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'js/jquery.ui.progressbar.js', EDD_SL_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget' ), '1.10.3' );
		}

	}

	/**
	 * Retrieve payment details screen URL
	 *
	 * @access      public
	 * @since       2.4
	 * @return      string
	*/
	public static function get_order_url( $payment_id ) {
		$link_base = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details' );
		$link      = esc_url( add_query_arg( 'id', $payment_id, $link_base ) );

		return $link;
	}


}
