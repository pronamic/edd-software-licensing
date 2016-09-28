<?php
/*
Plugin Name: Easy Digital Downloads - Software Licenses
Plugin URL: http://easydigitaldownloads.com/extension/software-licenses
Description: Adds a software licensing system to Easy Digital Downloads
Version: 3.4.10
Author: Pippin Williamson and Chris Klosowski
Author URI: http://pippinsplugins.com
Contributors: mordauk
Text Domain: edd_sl
Domain Path: languages
*/

if ( ! defined( 'EDD_SL_PLUGIN_DIR' ) ) {
	define( 'EDD_SL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EDD_SL_PLUGIN_URL' ) ) {
	define( 'EDD_SL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'EDD_SL_PLUGIN_FILE' ) ) {
	define( 'EDD_SL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'EDD_SL_VERSION' ) ) {
	define( 'EDD_SL_VERSION', '3.4.10' );
}

class EDD_Software_Licensing {

	/**
	 * @var EDD_Software_Licensing The one true EDD_Software_Licensing
	 * @since 1.5
	 */
	private static $instance;

	/**
	 * @const FILE
	 */
	const FILE = __FILE__;


	/**
	 * Initialise the rest of the plugin
	 */
	private function __construct() {

		// do nothing if EDD is not activated
		if( ! class_exists( 'Easy_Digital_Downloads', false ) ) {
			return;
		}

		$this->includes();
		$this->actions();

	}

	/**
	 * Main EDD_Software_Licensing Instance
	 *
	 * Insures that only one instance of EDD_Software_Licensing exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.4
	 * @static
	 * @staticvar array $instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Software_Licensing ) ) {
			self::$instance = new EDD_Software_Licensing;
		}
		return self::$instance;
	}

	/**
	 * Load the includes for EDD SL
	 *
	 * @since  3.2.4
	 * @return void
	 */
	private function includes() {

		if( is_admin() ) {

			if( class_exists( 'EDD_License' ) ) {
				$edd_sl_license = new EDD_License( __FILE__, 'Software Licensing', EDD_SL_VERSION, 'Pippin Williamson', 'edd_sl_license_key' );
			}

			include_once( EDD_SL_PLUGIN_DIR . 'includes/customers.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/metabox.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/settings.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/export.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/reports.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/upgrades.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/EDD_SL_Retroactive_Licensing.php' );
			$EDD_SL_Retroactive_Licensing = new EDD_SL_Retroactive_Licensing();
		}

		include_once( EDD_SL_PLUGIN_DIR . 'includes/scripts.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/admin.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/errors.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/filters.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/post-types.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/widgets.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/history.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/license-upgrades.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/receipt.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/renewals.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/readme.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/EDD_SL_Emails.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/EDD_SL_List_Table.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/EDD_SL_Changelog_Widget.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-package-download.php' );

	}

	public function actions() {

		add_action( 'init', array( $this, 'localization' ), -1 );

		add_action( 'init', array( $this, 'load_api_endpoint' ) );

		// creates / stores a license during purchase for EDD 1.6+
		add_action( 'edd_complete_download_purchase', array( $this, 'generate_license' ), 0, 5 );

		// Revokes license keys on payment status change (if needed)
		add_action( 'edd_update_payment_status', array( $this, 'revoke_license' ), 0, 3 );

		// Delete license keys on payment deletion
		add_action( 'edd_payment_delete', array( $this, 'delete_license' ), 10, 1 );

		// Delete a license when an item is removed from a payment
		add_action( 'edd_remove_download_from_payment', array( $this, 'delete_license' ), 10, 2 );

		// Renews a license on purchase
		add_action( 'edd_complete_download_purchase', array( $this, 'process_renewal' ), 0, 4 );

		// activates a license
		add_action( 'edd_activate_license', array( $this, 'remote_license_activation' ) );

		// deactivates a license
		add_action( 'edd_deactivate_license', array( $this, 'remote_license_deactivation' ) );

		// checks a license
		add_action( 'edd_check_license', array( $this, 'remote_license_check' ) );

		// gets latest version
		add_action( 'edd_get_version', array( $this, 'get_latest_version_remote' ) );

		// Add /changelog enpoint
		add_action( 'init', array( $this, 'changelog_endpoint' ) );

		// Display a plain-text changelog
		add_action( 'template_redirect', array( $this, 'show_changelog' ), -999 );

		// Prevent downloads on purchases with expired keys
		add_action( 'edd_process_verified_download', array( $this, 'prevent_expired_downloads' ), 10, 2 );

		// Reduce query load for EDD API calls
		add_action( 'after_setup_theme', array( $this, 'reduce_query_load' ) );

		add_action( 'edd_updated_edited_purchase', array( $this, 'update_licenses_on_payment_update' ) );

		add_action( 'user_register', array( $this, 'add_past_license_keys_to_new_user' ) );

	}

	/**
	 * Load the localization files
	 *
	 * @since  3.2.4
	 * @return void
	 */
	public function localization() {
		load_plugin_textdomain( 'edd_sl', false, dirname( plugin_basename( EDD_SL_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * @return void
	 */
	public function load_api_endpoint() {

		// if this is an API Request, load the Endpoint
		if ( ! is_admin() && $this->is_api_request() !== false && ! defined( 'EDD_SL_DOING_API_REQUEST' ) ) {

			$request_type  = $this->get_api_endpoint();
			if ( ! empty( $request_type ) ) {
				$request_class = str_replace( '_', ' ', $request_type );
				$request_class = 'EDD_SL_' . ucwords( $request_class );
				$request_class = str_replace( ' ', '_', $request_class );

				if ( class_exists( $request_class ) ) {
					define( 'EDD_SL_DOING_API_REQUEST', true );
					$api_request = new $request_class;
					$api_request->process_request();
				}
			}

		}

	}

	/**
	 * The whitelisted endpoints for the Software Licensing
	 *
	 * @since  3.2.4
	 * @return array Array of endpoints whitelisted for EDD SL
	 */
	private function allowed_api_endpoints() {
		$default_endpoints = array(
			'package_download',
		);

		return apply_filters( 'edd_sl_allowed_api_endpoints', $default_endpoints );
	}

	/**
	 * Verify an endpoint is the one being requested
	 *
	 * @since  3.2.4
	 * @param  string  $endpoint The endpoint to check
	 * @return boolean           If the endpoint provided is the one currently being requested
	 */
	private function is_endpoint_active( $endpoint = '' ) {
		$is_active = stristr( $_SERVER['REQUEST_URI'], 'edd-sl/' . $endpoint ) !== false;

		if ( $is_active ) {
			$is_active = true;
		}

		return (bool) $is_active;
	}

	/**
	 * Is this a request we should respond to?
	 *
	 * @since  3.2.4
	 * @return bool
	 */
	private function is_api_request() {
		$trigger = false;

		$allowed_endpoints = $this->allowed_api_endpoints();

		foreach ( $allowed_endpoints as $endpoint ) {

			$trigger = $this->is_endpoint_active( $endpoint );

			if ( $trigger ) {
				$trigger = true;
				break;
			}

		}

		return (bool) apply_filters( 'edd_sl_is_api_request', $trigger );
	}

	/**
	 * Parse the API endpoint being requested
	 *
	 * @since  3.2.4
	 * @return string The endpoint being requested
	 */
	private function get_api_endpoint() {
		$url_parts = parse_url( $_SERVER['REQUEST_URI'] );
		$paths     = explode( '/', $url_parts['path'] );
		$endpoint  = '';
		foreach ( $paths as $index => $path ) {
			if ( 'edd-sl' === $path ) {
				$endpoint = $paths[ $index + 1 ];
				break;
			}
		}

		return $endpoint;
	}


	/*
	|--------------------------------------------------------------------------
	| License Creation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Generate license keys for a purchase
	 *
	 * Generates ( if needed ) a license key for the buyer at time of purchase
	 * This key will be used to activate all products for this purchase
	 *
	 * @access      private
	 * @since       1.5
	 *
	 * @param int $download_id
	 * @param int $payment_id
	 * @param string $type
	 * @param array $cart_item
	 * @param mixed $cart_index
	 *
	 * @return      mixed
	*/

	function generate_license( $download_id = 0, $payment_id = 0, $type = 'default', $cart_item = array(), $cart_index = 0 ) {

		$keys = array();

		// Bail if this cart item is for a renewal
		if( ! empty( $cart_item['item_number']['options']['is_renewal'] ) ) {
			return $keys;
		}

		// Bail if this cart item is for an upgrade
		if( ! empty( $cart_item['item_number']['options']['is_upgrade'] ) ) {
			return $keys;
		}

		$payment           = new EDD_Payment( $payment_id );
		$bundle_licensing  = ( 'bundle' == $type && (bool) get_post_meta( $download_id, '_edd_sl_enabled', true ) );
		$parent_license_id = 0;
		$activation_limit  = false;

		if( $type == 'bundle' ) {

			$downloads = array();

			if ( $bundle_licensing ) {
				$downloads[] = $download_id;
			}

			$downloads = array_merge( $downloads, edd_get_bundled_products( $download_id ) );

			if ( edd_has_variable_prices( $download_id ) ) {
				$activation_limit = $this->get_price_activation_limit( $download_id, $cart_item['item_number']['options']['price_id'] );
				$is_lifetime      = $this->get_price_is_lifetime( $download_id, $cart_item['item_number']['options']['price_id'] );
			}


		} else {

			if ( edd_has_variable_prices( $download_id ) ) {
				$is_lifetime = $this->get_price_is_lifetime( $download_id, $cart_item['item_number']['options']['price_id'] );
			}

			$downloads = array();
			$downloads[] = $download_id;

		}

		if( ! is_array( $downloads ) ) {
			return $keys;
		}

		foreach ( $downloads as $d_id ) {

			if ( ! get_post_meta( $d_id, '_edd_sl_enabled', true ) )
				continue;

			$license_title = get_the_title( $d_id ) . ' - ' . $payment->email;

			$license_args = array(
				'post_type'   => 'edd_license',
				'post_title'  => $license_title,
				'post_status' => 'publish',
				'post_date'   => get_post_field( 'post_date', $payment_id, 'raw' )
			);

			if ( $parent_license_id ) {
				$license_args['post_parent'] = $parent_license_id;
			}

			$license_id  = wp_insert_post( apply_filters( 'edd_sl_insert_license_args', $license_args ) );

			if ( $bundle_licensing && $download_id == $d_id && ! $parent_license_id ) {
				$parent_license_id = $license_id;
			}

			$keys[] = $license_id;

			$license_key = $this->get_new_download_license_key( $d_id );

			if( ! $license_key ) {
				// No predefined license key available, generate a random one
				$license_key = $this->generate_license_key( $license_id, $d_id, $payment_id, $cart_index );
			}

			$price_id = isset( $cart_item['item_number']['options']['price_id'] ) ? (int) $cart_item['item_number']['options']['price_id'] : false;

			add_post_meta( $license_id, '_edd_sl_download_id', $d_id );

			if( false !== $price_id ) {
				add_post_meta( $license_id, '_edd_sl_download_price_id', $price_id );
			}

			add_post_meta( $license_id, '_edd_sl_cart_index', $cart_index );
			add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );
			add_post_meta( $license_id, '_edd_sl_key', $license_key );
			add_post_meta( $license_id, '_edd_sl_user_id', $payment->user_id );
			add_post_meta( $license_id, '_edd_sl_status', 'inactive' );

			if ( $parent_license_id && ! empty( $activation_limit ) ) {
				add_post_meta( $license_id, '_edd_sl_limit', $activation_limit );
			}

			// Get the purchase date so we can set the correct license expiration date
			$payment_meta = edd_get_payment_meta( $payment_id );
			$purchase_date = null;
			if ( ! empty( $payment_meta['date'] ) ) {
				$purchase_date = strtotime( $payment_meta['date'] );
			}

			// Get license length
			$license_length = $this->get_license_length( $license_id, $payment_id, $d_id );

			if ( empty( $is_lifetime ) && 'lifetime' !== $license_length ) {

				// Set license expiration date
				$expiration = strtotime( $license_length, $purchase_date );

				if( $expiration > strtotime( '+24 hours' ) ) {

					// Force it to end of day if expiration is more than 24 hours in the future
					$expiration = date( 'Y-n-d 23:59:59', $expiration );

					// Convert back into timestamp
					$expiration = strtotime( $expiration );

				}

				$this->set_license_expiration( $license_id, $expiration );
			} else {
				$this->set_license_as_lifetime( $license_id );
			}

			do_action( 'edd_sl_store_license', $license_id, $d_id, $payment_id, $type );
		}

		return $keys;
	}


	/*
	|--------------------------------------------------------------------------
	| License Activation
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param array $args
	 *
	 * @return array
	 */
	function activate_license( $args ) {

		global $edd_options;

		$defaults = array(
			'key'        => '',
			'item_name'  => '',
			'item_id'    => 0,
			'expiration' => time(), // right now
			'url'        => ''
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args, EXTR_SKIP );

		$license_id  = $this->get_license_by_key( $key );
		$download_id = $this->get_download_id_by_license( $key );

		$license         = get_post( $license_id );
		$item_name       = html_entity_decode( $item_name );

		if( empty( $url ) ) {

			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );

		}

		$bypass_local = isset( $edd_options['edd_sl_bypass_local_hosts'] );
		$is_local_url = empty( $bypass_local ) ? false : $this->is_local_url( $url );

		do_action( 'edd_sl_pre_activate_license', $license_id, $download_id );

		// grab info about the license
		$license_expires = $this->get_license_expiration( $license_id );
		$license_key     = $this->get_license_key( $license_id );

		$result = array();
		$result['success'] = true;

		// this license does not even exist
		if ( false === $license_id || null == $license ) {

			$result['success'] = false;
			$result['error']   = 'missing';

		}

		// Trying to activate bundle license
		if ( edd_is_bundled_product( $download_id  ) ) {

			$result['success'] = false;
			$result['error'] = 'license_not_activable';

		}

		// License key revoked
		if ( $result['success'] && 'publish' != $license->post_status ) {

			$result['success'] = false;
			$result['error']   = 'revoked';

		}

		// no activations left
		if( $result['success'] && ( $this->is_at_limit( $license_id, $download_id ) && ! $is_local_url ) && ( $this->force_increase() || ! $this->is_site_active( $license_id, $url ) ) ) {

			$result['success']   = false;
			$result['error']     = 'no_activations_left';
			$result['max_sites'] = $this->get_site_count( $license_id );

		}

		// this license has expired'
		$is_lifetime_license = $this->is_lifetime_license( $license_id );
		if ( $result['success'] && ( ! $is_lifetime_license &&  $license_expires < $expiration ) ) {

			$result['success'] = false;
			$result['error']   = 'expired';
			$result['expires'] = $license_expires;

		}

		// keys don't match
		if ( $result['success'] && $key != $license_key ) {

			$result['success'] = false;
			$result['error']   = 'key_mismatch';

		}

		if( ! empty( $args['item_id'] ) && $result['success'] ) {

			if( ! $this->is_download_id_valid_for_license( $args['item_id'], $key ) ) {
				$result['success']   = false;
				$result['error']     = 'invalid_item_id';
			}

		} else {

			// Item names don't match
			if( $result['success'] && ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ! $this->check_item_name( $download_id, $item_name ) ) {
				$result['success']   = false;
				$result['error']     = 'item_name_mismatch';
			}

		}

		if( $result['success'] ) {

			// activate the site for the license
			$this->insert_site( $license_id, $url );

			// activate the license
			$this->set_license_status( $license_id, 'active' );

			if ( $is_local_url ) {
				$result['is_local'] = true;
			}

			// enter this activation in the log
			$this->log_license_activation( $license_id, $_SERVER );

			do_action( 'edd_sl_activate_license', $license_id, $download_id );


		}

		// All good, give some additional info about the activation
		$license_limit = $this->get_license_limit( $download_id, $license_id );
		$site_count    = $this->get_site_count( $license_id );

		$result['license_limit'] = $license_limit;
		$result['site_count']    = $site_count;
		$result['expires']       = $this->get_license_expiration( $license_id );

		// just leaving this in here in case others are using it
		if( $license_limit > 0 ) {
			$result['activations_left'] = $license_limit - $site_count;
		} else {
			$result['activations_left'] = 'unlimited';
		}

		return $result; // license is valid and activated
	}

	/**
	 * @param array $data
	 * @return void
	 */
	function remote_license_activation( $data ) {

		$item_id     = ! empty( $data['item_id'] ) ? absint( $data['item_id'] ) : false;
		$item_name   = ! empty( $data['item_name'] ) ? rawurldecode( $data['item_name'] ) : false;
		$license     = ! empty( $data['license'] ) ? urldecode( $data['license'] ) : false;
		$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';
		$license_id  = $this->get_license_by_key( $license );
		$expires     = $this->get_license_expiration( $license_id );
		$payment_id  = get_post_meta( $license_id, '_edd_sl_payment_id', true );
		$customer_id = edd_get_payment_customer_id( $payment_id );

		$customer = new EDD_Customer( $customer_id );

		$args = array(
			'item_name' => $item_name,
			'key'       => $license,
			'url'       => $url,
			'item_id'   => $item_id
		);

		$result = $this->activate_license( $args );

		if ( $result['success'] ) {
			$license_check = 'valid';
		} else {
			$license_check = 'invalid';
		}

		if( empty( $item_name ) ) {
			$item_name = get_the_title( $item_id );
		}

		$result = array_merge( $result, array(
				'success'        => (bool) $result['success'],
				'license'        => $license_check,
				'item_name'      => $item_name,
				'expires'        => is_numeric( $expires ) ? date( 'Y-m-d H:i:s', $expires ) : $expires,
				'payment_id'     => $payment_id,
				'customer_name'  => $customer->name,
				'customer_email' => $customer->email,
			)
		);


		header( 'Content-Type: application/json' );
		echo json_encode( apply_filters( 'edd_remote_license_activation_response', $result, $args, $license_id ) );
		exit;
	}


	/*
	|--------------------------------------------------------------------------
	| License Deactivation
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param array $args
	 *
	 * @return bool
	 */
	function deactivate_license( $args ) {
		global $edd_options;

		$defaults = array(
			'key'        => '',
			'item_name'  => '',
			'item_id'    => 0,
			'expiration' => time(), // right now
			'url'        => ''
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args, EXTR_SKIP );

		$license_id      = $this->get_license_by_key( $key );
		$download_id     = $this->get_download_id_by_license( $key );
		$item_name       = html_entity_decode( $item_name );

		if( empty( $url ) ) {

			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );

		}

		$bypass_local = isset( $edd_options['edd_sl_bypass_local_hosts'] );
		$is_local_url = empty( $bypass_local ) ? false : $this->is_local_url( $url );

		do_action( 'edd_sl_pre_deactivate_license', $license_id, $download_id );

		// grab info about the license
		$license_expires = $this->get_license_expiration( $license_id );
		$license_key     = $this->get_license_key( $license_id );
		$license_status  = $this->get_license_status( $license_id );

		// make sure license is active
		if( $license_status != 'active' && ! $bypass_local ) {
			return false;
		}

		// Trying to activate bundle license
		if ( edd_is_bundled_product( $download_id  ) ) {
			return false;
		}

		// don't deactivate if expired
		$is_lifetime_license = $this->is_lifetime_license( $license_id );
		if ( ! $is_lifetime_license && $license_expires < $expiration ) {
			return false; // this license has expired
		}

		if ( $key != $license_key ) {
			return false; // keys don't match
		}

		if( ! empty( $args['item_id'] ) ) {

			if( ! $this->is_download_id_valid_for_license( $download_id, $args['key'] ) ) {

				return false;
			}

		} else {

			// Item names don't match
			if( ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ! $this->check_item_name( $download_id, $item_name ) ) {
				return false; // Item names don't match
			}

		}

		// deactivate the site for the license
		$this->delete_site( $license_id, $url );

		if ( ! $is_local_url ) {

			// enter this deactivation in the log
			$this->log_license_deactivation( $license_id, $_SERVER );

			do_action( 'edd_sl_deactivate_license', $license_id, $download_id );
		}
		return true; // license has been deactivated

	}

	/**
	 * @param array $data
	 * @return void
	 */
	function remote_license_deactivation( $data ) {


		$item_id     = ! empty( $data['item_id'] ) ? absint( $data['item_id'] ) : false;
		$item_name   = ! empty( $data['item_name'] ) ? rawurldecode( $data['item_name'] ) : false;
		$license     = urldecode( $data['license'] );
		$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';
		$license_id  = $this->get_license_by_key( $license );
		$expires     = $this->get_license_expiration( $license_id );
		$payment_id  = get_post_meta( $license_id, '_edd_sl_payment_id', true );
		$customer_id = edd_get_payment_customer_id( $payment_id );

		$customer = new EDD_Customer( $customer_id );

		$args = array(
			'item_id'   => $item_id,
			'item_name' => $item_name,
			'key'       => $license,
			'url'       => $url,
		);

		$result = $this->deactivate_license( $args );

		if ( $result ) {
			$status = 'deactivated';
		} else {
			$status = 'failed';
		}

		if( empty( $item_name ) ) {
			$item_name = get_the_title( $item_id );
		}

		header( 'Content-Type: application/json' );

		echo json_encode( apply_filters( 'edd_remote_license_deactivation_response', array(
				'success'        => (bool) $result,
				'license'        => $status,
				'item_name'      => $item_name,
				'expires'        => is_numeric( $expires ) ? date( 'Y-m-d H:i:s', $expires ) : $expires,
				'payment_id'     => $payment_id,
				'customer_name'  => $customer->name,
				'customer_email' => $customer->email,
			), $args, $license_id )
		);

		exit;

	}


	/*
	|--------------------------------------------------------------------------
	| License Checking
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	function check_license( $args ) {

		$defaults = array(
			'key'        => '',
			'item_name'  => '',
			'item_id'    => 0,
			'expiration' => time(), // right now
			'url'        => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$license_id = $this->get_license_by_key( $args['key'] );

		if ( ! $license_id ) {
			return 'invalid';
		}

		// grab info about the license
		$license            = get_post( $license_id );
		$license_expires    = $this->get_license_expiration( $license_id );
		$license_key        = get_post_meta( $license_id, '_edd_sl_key', true );
		$license_status     = get_post_meta( $license_id, '_edd_sl_status', true );
		$download_id        = get_post_meta( $license_id, '_edd_sl_download_id', true );
		$item_name          = html_entity_decode( $args['item_name'] );
		$url                = ! empty( $args['url'] ) ? $args['url'] : '';

		if( empty( $url ) ) {

			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );

		}

		$is_local_url = $this->is_local_url( $url );

		if ( $args['key'] != $license_key ) {
			return 'invalid'; // keys don't match
		}

		if( ! empty( $args['item_id'] ) ) {

			if( ! $this->is_download_id_valid_for_license( $args['item_id'], $args['key'] ) ) {
				return 'invalid_item_id';
			}

		} else {

			if( ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ! $this->check_item_name( $download_id, $item_name ) ) {
				return 'item_name_mismatch'; // Item names don't match
			}

		}

		$is_lifetime_license = $this->is_lifetime_license( $license_id );
		if ( ! $is_lifetime_license && $args['expiration'] > $license_expires ) {
			return 'expired'; // this license has expired
		}

		if ( 'active' != $license_status ) {
			return 'inactive'; // this license is not active.
		}

		if ( 'publish' != $license->post_status ) {
			return 'disabled'; // License key disabled
		}

		if( ! $is_local_url && ! $this->is_site_active( $license_id, $url ) ) {
			return 'site_inactive';
		}


		do_action( 'edd_sl_check_license', $license_id, $download_id );

		return 'valid'; // license still active

	}

	/**
	 * @param array $data
	 * @return void
	 */
	function remote_license_check( $data ) {

		$item_id     = ! empty( $data['item_id'] )   ? absint( $data['item_id'] ) : false;
		$item_name   = ! empty( $data['item_name'] ) ? rawurldecode( $data['item_name'] ) : false;
		$license     = urldecode( $data['license'] );
		$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';
		$license_id  = $this->get_license_by_key( $license );
		$expires     = $this->get_license_expiration( $license_id );
		$payment_id  = get_post_meta( $license_id, '_edd_sl_payment_id', true );
		$download_id = get_post_meta( $license_id, '_edd_sl_download_id', true );
		$customer_id = edd_get_payment_customer_id( $payment_id );

		$customer = new EDD_Customer( $customer_id );

		$args = array(
			'item_id'   => $item_id,
			'item_name' => $item_name,
			'key'       => $license,
			'url'       => $url,
		);

		$result = $this->check_license( $args );

		$license_limit = $this->get_license_limit( $download_id, $license_id );
		$site_count    = $this->get_site_count( $license_id );

		if( empty( $item_name ) ) {
			$item_name = get_the_title( $item_id );
		}

		header( 'Content-Type: application/json' );
		echo json_encode( apply_filters( 'edd_remote_license_check_response', array(
				'success'          => (bool) $result,
				'license'          => $result,
				'item_name'        => $item_name,
				'expires'          => is_numeric( $expires ) ? date( 'Y-m-d H:i:s', $expires ) : $expires,
				'payment_id'       => $payment_id,
				'customer_name'    => ! empty( $customer->name ) ? $customer->name : '',
				'customer_email'   => ! empty( $customer->email ) ? $customer->email : '',
				'license_limit'    => $license_limit,
				'site_count'       => $site_count,
				'activations_left' => $license_limit > 0 ? $license_limit - $site_count : 'unlimited'
			), $args, $license_id )
		);

		exit;

	}


	/*
	|--------------------------------------------------------------------------
	| License Renewal
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int    $download_id
	 * @param int    $payment_id
	 * @param string $type (unused)
	 * @param array  $cart_item
	 * @return void
	 */
	function process_renewal( $download_id = 0, $payment_id = 0, $type = 'default', $cart_item = array() ) {

		// Bail if this is not a renewal item
		if( empty( $cart_item['item_number']['options']['is_renewal'] ) ) {
			return;
		}

		$license_keys = edd_get_payment_meta( $payment_id, '_edd_sl_renewal_key', false );

		if( is_array( $license_keys ) ) {

			foreach( $license_keys as $key ) {

				$license_id  = $this->get_license_by_key( $key );
				$item_id     = $this->get_download_id( $license_id );

				if( (int) $download_id !== (int) $item_id ) {
					continue;
				}

				if( empty( $license_id ) ) {
					continue;
				}

				$this->renew_license( $license_id, $payment_id, $download_id );

			}

		}
	}

	/**
	 * @param int $license_id
	 * @param int $payment_id
	 * @param int $download_id
	 * @return void
	 */
	function renew_license( $license_id = 0, $payment_id = 0, $download_id = 0 ) {

		$is_lifetime_license = $this->is_lifetime_license( $license_id );
		if ( $is_lifetime_license ) {
			return;
		}

		// License renewal length. Default is 1 year
		$length = $this->get_license_length( $license_id, $payment_id, $download_id );

		$expiration = $this->get_license_expiration( $license_id );

		// If expiration is less than today's time() then we need to renew it from time() now
		// that way renewing won't just renew them and expire immediately.
		// i.g. if they renew a license in 2011, it should be active now, not renew until 2012
		if ( $expiration < time() ) {
			$expiration = time();
		}

		// Set license expiration date
		$new_expiration = strtotime( $length, $expiration );

		if( $new_expiration > strtotime( '+24 hours' ) ) {

			// Force it to end of day if expiration is more than 24 hours in the future
			$new_expiration = date( 'Y-n-d 23:59:59', $new_expiration );

			// Convert back into timestamp
			$new_expiration = strtotime( $new_expiration );

		}

		do_action( 'edd_sl_pre_license_renewal', $license_id );

		$this->set_license_expiration( $license_id, $new_expiration );
		$this->set_license_status( $license_id, 'active' );

		// Store payment ID for license
		add_post_meta( $license_id, '_edd_sl_payment_id', $payment_id );

		// Allow the renewal reminder notice to be sent again
		delete_post_meta( $license_id, '_edd_sl_renewal_sent' );

		// Allow other plugins to hook into the renewal process (perhaps renewal emails)
		do_action( 'edd_sl_post_license_renewal', $license_id, $new_expiration );

	}

	/**
	 * Retrieve the renewal URL for a license key
	 *
	 * @since  3.4
	 * @param int $license_id
	 * @return string The renewal URL
	 */
	function get_renewal_url( $license_id = 0 ) {

		$args = array(
			'edd_license_key' => $this->get_license_key( $license_id ),
			'download_id'     => $this->get_download_id( $license_id )
		);

		$url = add_query_arg( $args, edd_get_checkout_uri() );
		return apply_filters( 'edd_sl_get_renewal_url', $url, $license_id );

	}

	/**
	 * Determine if a license is allowed to be extended
	 *
	 * @since  3.4.7
	 * @param int $license_id
	 * @return bool
	 */
	function can_extend( $license_id = 0 ) {
		$ret = edd_sl_renewals_allowed() && 'expired' !== $this->get_license_status( $license_id );

		if( $this->is_lifetime_license( $license_id ) ) {
			$ret = false;
		}

		// Verify the initial payment is at least completed
		$payment_id = $this->get_payment_id( $license_id );
		$payment    = new EDD_Payment( $payment_id );
		if ( 'publish' !== $payment->status ) {
			$ret = false;
		}
		return apply_filters( 'edd_sl_can_extend_license', $ret, $license_id );
	}


	/*
	|--------------------------------------------------------------------------
	| Revoke License
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int $payment_id
	 * @param string $new_status
	 * @param string $old_status
	 */
	function revoke_license( $payment_id, $new_status, $old_status ) {

		if ( $old_status != 'publish' && $old_status != 'complete' )
			return; // Make sure that licenses are only generated once

		// Revoke license keys when the payment is refunded or revoked
		if ( ! in_array( $new_status, apply_filters( 'edd_sl_revoke_license_statuses', array( 'revoked', 'refunded' ) ) ) ) {
			return;
		}

		$licenses = $this->get_licenses_of_purchase( $payment_id );

		if( ! $licenses ) {
			return;
		}

		$renewal = $this->is_renewal( $payment_id );

		foreach( $licenses as $license ) {

			// Don't revoke license keys when a renewal is refunded if the license is not expired
			if( 'expired' !== $this->get_license_status( $license->ID ) && $renewal ) {
				continue;
			}

			do_action( 'edd_sl_pre_revoke_license', $license->ID, $payment_id );
			wp_update_post( array( 'ID' => $license->ID, 'post_status' => 'draft' ) );
			$this->set_license_status( $license->ID, 'inactive' );
			do_action( 'edd_sl_post_revoke_license', $license->ID, $payment_id );
		}

	}

	/*
	|--------------------------------------------------------------------------
	| Delete License
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int $payment_id
	 * @param int $download_id
	 * @return void
	 */
	function delete_license( $payment_id, $download_id = 0 ) {

		$status   = get_post_field( 'post_status', $payment_id );
		$licenses = $this->get_licenses_of_purchase( $payment_id );

		if( ! $licenses ) {
			return;
		}

		if( $status !== 'publish' && 'revoked' !== $status ) {
			return;
		}

		foreach( $licenses as $license ) {

			$license_key = $this->get_license_key( $license->ID );

			if ( ! empty( $download_id ) && ! $this->is_download_id_valid_for_license( $download_id, $license_key, true ) ) {
				continue;
			}

			do_action( 'edd_sl_pre_delete_license', $license->ID, $payment_id );
			wp_delete_post( $license->ID, true );
			do_action( 'edd_sl_post_delete_license', $license->ID, $payment_id );

			if ( ! empty( $download_id ) ) {
				break;
			}

		}


	}

	/*
	|--------------------------------------------------------------------------
	| Version Checking
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int $item_id
	 *
	 * @return bool|mixed
	 */
	function get_latest_version( $item_id ) {

		return $this->get_download_version( $item_id );

	}

	/**
	 * @param array $data
	 * @return void
	 */
	function get_latest_version_remote( $data ) {

		$url       = isset( $data['url'] )       ? sanitize_text_field( urldecode( $data['url'] ) )          : false;
		$license   = isset( $data['license'] )   ? sanitize_text_field( urldecode( $data['license'] ) )      : false;
		$slug      = isset( $data['slug'] )      ? sanitize_text_field( urldecode( $data['slug'] ) )         : false;
		$item_id   = isset( $data['item_id'] )   ? absint( $data['item_id'] )                                : false;
		$item_name = isset( $data['item_name'] ) ? sanitize_text_field( rawurldecode( $data['item_name'] ) ) : false;
		if( empty( $item_name ) && empty( $item_id ) ) {
			$item_name = isset( $data['name'] )  ? sanitize_text_field( rawurldecode( $data['name'] ) )      : false;
		}

		$response  = array(
			'new_version'   => '',
			'sections'      => '',
			'license_check' => '',
			'msg'           => ''
		);

		// set content type of response
		header( 'Content-Type: application/json' );

		if( empty( $item_id ) && empty( $item_name ) && ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) ) {
			$response['msg'] = __( 'No item provided', 'edd_sl' );
			echo json_encode( $response ); exit;
		}

		if( empty( $item_id ) ) {

			if( empty( $license ) && empty( $item_name ) ) {
				$response['msg'] = __( 'No item provided', 'edd_sl' );
				echo json_encode( $response ); exit;
			}

			if( empty( $license ) ) {

				$item_id = $this->get_download_id_by_name( $item_name );

			} else {

				$item_id = $this->get_download_id_by_license( $license );

			}

		}

		$download = get_post( $item_id );

		if( ! $download ) {
			$response['msg'] = sprintf( __( 'License key provided does not match a valid %s', 'edd_sl' ), edd_get_label_singular() );
			echo json_encode( $response ); exit;
		}

		$version     = $this->get_latest_version( $item_id );
		$slug        = ! empty( $slug ) ? $slug : $download->post_name;
		$description = ! empty( $download->post_excerpt ) ? $download->post_excerpt : $download->post_content;
		$changelog   = get_post_meta( $item_id, '_edd_sl_changelog', true );

		$response = array(
			'new_version'   => $version,
			'name'          => $download->post_title,
			'slug'          => $slug,
			'url'           => esc_url( add_query_arg( 'changelog', '1', get_permalink( $item_id ) ) ),
			'last_updated'  => $download->post_modified,
			'homepage'      => get_permalink( $item_id ),
			'package'       => $this->get_encoded_download_package_url( $item_id, $license, $url ),
			'download_link' => $this->get_encoded_download_package_url( $item_id, $license, $url ),
			'sections'      => serialize(
				array(
					'description' => wpautop( strip_tags( $description, '<p><li><ul><ol><strong><a><em><span><br>' ) ),
					'changelog'   => wpautop( strip_tags( stripslashes( $changelog ), '<p><li><ul><ol><strong><a><em><span><br>' ) ),
				)
			),
		);

		$response = apply_filters( 'edd_sl_license_response', $response, $download );

		echo json_encode( $response );
		exit;
	}


	/*
	|--------------------------------------------------------------------------
	| Logging Functions
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param string $license_id
	 *
	 * @return array|bool
	 */
	function get_license_logs( $license_id = '' ) {

		$query_args = apply_filters(
			'edd_sl_license_logs_query_args',
			array(
				'post_type'      => 'edd_license_log',
				'meta_key'       => '_edd_sl_log_license_id',
				'meta_value'     => $license_id,
				'posts_per_page' => 1000
			)
		);

		$logs = get_posts( apply_filters( 'edd_sl_get_license_logs', $query_args ) );

		if ( $logs ) {
			return $logs;
		}

		return false; // no logs found

	}

	/**
	 * @param int $license_id
	 * @param array $server_data
	 */
	function log_license_activation( $license_id, $server_data ) {

		$log_id = wp_insert_post(
			array(
				'post_title'   => __( 'LOG - License Activated: ', 'edd_sl' ) . $license_id,
				'post_name'    => 'log-license-activated-' . $license_id . '-' . md5( time() ),
				'post_type'    => 'edd_license_log',
				'post_content' => json_encode( $server_data ),
				'post_status'  => 'publish'
			 )
		);

		add_post_meta( $log_id, '_edd_sl_log_license_id', $license_id );

		if( $this->force_increase() ) {

			$activation_count = $this->get_site_count( $license_id );
			$activation_count++;
			update_post_meta( $license_id, '_edd_sl_activation_count', $activation_count );

		}

	}

	/**
	 * @param int $license_id
	 * @param array $server_data
	 */
	function log_license_deactivation( $license_id, $server_data ) {

		$log_id = wp_insert_post(
			array(
				'post_title'   => __( 'LOG - License Deactivated: ', 'edd_sl' ) . $license_id,
				'post_name'    => 'log-license-deactivated-' . $license_id . '-' . md5( time() ),
				'post_type'    => 'edd_license_log',
				'post_content' => json_encode( $server_data ),
				'post_status'  => 'publish'
			 )
		);

		add_post_meta( $log_id, '_edd_sl_log_license_id', $license_id );

		if( $this->force_increase() ) {

			$activation_count = $this->get_site_count( $license_id );
			if( $activation_count >= 1 ) {
				$activation_count--;
				update_post_meta( $license_id, '_edd_sl_activation_count', $activation_count );
			}

		}

	}


	/*
	|--------------------------------------------------------------------------
	| Site tracking
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int $license_id
	 *
	 * @return array
	 */
	function get_sites( $license_id = 0 ) {
		$sites = get_post_meta( $license_id, '_edd_sl_sites', true );
		if( empty( $sites ) ) {
			$sites = array();
		}

		$sites = array_map( array( self::$instance, 'clean_site_url' ), $sites );

		return array_unique( apply_filters( 'edd_sl_get_sites', $sites, $license_id ) );

	}

	/**
	 * @param int $license_id
	 *
	 * @return mixed|void
	 */
	public function get_site_count( $license_id = 0 ) {
		$count = 0;

		if( $this->force_increase() ) {
			$count = absint( get_post_meta( $license_id, '_edd_sl_activation_count', true ) );
		} else {
			$sites = $this->get_sites( $license_id );
			$bypass_local = edd_get_option( 'edd_sl_bypass_local_hosts', false );

			if ( $bypass_local ) {
				foreach ( $sites as $site ) {
					if ( ! $this->is_local_url( $site ) ) {
						$count++;
					}
				}
			} else {
				$count = count( $sites );
			}

		}
		return apply_filters( 'edd_sl_get_site_count', $count, $license_id );
	}

	/**
	 * @param int    $license_id
	 * @param string $site_url
	 *
	 * @return bool|mixed|void
	 */
	function is_site_active( $license_id = 0, $site_url = '' ) {

		if( $this->force_increase() ) {
			return true; // Licenses are not tied to URLs
		}

		$site_url = trailingslashit( $this->clean_site_url( $site_url ) );

		$sites = array_map( array( self::$instance, 'clean_site_url' ), $this->get_sites( $license_id ) );

		$ret   = in_array( $site_url, $sites );
		return apply_filters( 'edd_sl_is_site_active', $ret, $license_id, $site_url );
	}

	/**
	 * @param int    $license_id
	 * @param string $site_url
	 *
	 * @return bool|int
	 */
	function insert_site( $license_id = 0, $site_url = '' ) {

		if( empty( $license_id ) ) {
			return false;
		}

		if( empty( $site_url ) ) {
			return false;
		}

		if( $this->force_increase() ) {
			return false; // Licenses are not tied to URLs
		}

		$sites = $this->get_sites( $license_id );

		$site_url = trailingslashit( $this->clean_site_url( $site_url ) );

		if( in_array( $site_url, $sites ) ) {
			return false; // Site already tracked
		}

		$sites[] = $site_url;

		return update_post_meta( $license_id, '_edd_sl_sites', $sites );

	}

	/**
	 * @param int    $license_id
	 * @param string $site_url
	 *
	 * @return bool|int
	 */
	function delete_site( $license_id = 0, $site_url = '' ) {

		if( empty( $license_id ) ) {
			return false;
		}

		if( empty( $site_url ) ) {
			return false;
		}

		if( $this->force_increase() ) {
			return false; // Licenses are not tied to URLs
		}

		$sites = $this->get_sites( $license_id );

		$site_url = trailingslashit( $this->clean_site_url( $site_url ) );

		if( ! in_array( $site_url, $sites ) ) {
			return false; // Site doesn't exist
		}

		$key = array_search( $site_url, $sites );

		unset( $sites[ $key ] );

		return update_post_meta( $license_id, '_edd_sl_sites', $sites );

	}


	/*
	|--------------------------------------------------------------------------
	| Misc Functions
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int $download_id
	 *
	 * @return mixed
	 */
	function get_new_download_license_key( $download_id = 0 ) {
		$keys = get_post_meta( $download_id, '_edd_sl_keys', true );

		if( ! $keys ) {
			return false; // no available keys
		}

		$keys = array_map( 'trim', explode( "\n", $keys ) );
		$key  = $keys[0];
		unset( $keys[0] );
		update_post_meta( $download_id, '_edd_sl_keys', implode( "\n", $keys ) );
		return $key;

	}

	/**
	 * @param int $license_id
	 * @param int $download_id
	 * @param int $payment_id
	 * @param mixed $cart_index
	 *
	 * @return mixed
	 */
	function generate_license_key( $license_id = 0, $download_id = 0, $payment_id = 0, $cart_index = 0 ) {
		$key = md5( $license_id . $download_id . $payment_id . $cart_index );
		return apply_filters( 'edd_sl_generate_license_key', $key, $license_id, $download_id, $payment_id, $cart_index );
	}

	/**
	 * @param string $license_key
	 *
	 * @return bool|null|string
	 */
	function get_license_by_key( $license_key ) {

		global $wpdb;

		static $cache = array();
		if( isset( $cache[ $license_key ] ) ) {
			return $cache[ $license_key ];
		}

		$license_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id, SLEEP(RAND()*0.20) AS junk FROM $wpdb->postmeta WHERE meta_key = '_edd_sl_key' AND meta_value = '%s' LIMIT 1", $license_key ) );

		if ( $license_id != NULL ) {
			$cache[ $license_key ] = $license_id;
			return $license_id;
		}

		return false;

	}

	/**
	 * @param int $license_id
	 *
	 * @return bool|mixed
	 */
	function get_license_key( $license_id ) {
		return get_post_meta( $license_id, '_edd_sl_key', true );
	}

	/**
	 * @param string $license_key
	 *
	 * @return mixed|void
	 */
	function get_download_id_by_license( $license_key ) {

		$download_id = false;
		$license_id  = $this->get_license_by_key( $license_key );

		if ( $license_id !== false ) {
			$download_id = $this->get_download_id( $license_id );
		}

		return apply_filters( 'edd_sl_get_download_id_by_license', $download_id, $license_key, $license_id );
	}
	/**
	 * @param string $license_key
	 * @deprecated 3.4.7
	 *
	 * @return mixed|void
	 */
	function get_download_by_license( $license_key ) {

		/*
		 * Deprecated in favor of get_download_id_by_license()
		 * See https://github.com/easydigitaldownloads/EDD-Software-Licensing/pull/479
		 */

		return $this->get_download_id_by_license( $license_key );
	}

	/**
	 * Retrieves the download ID by the name
	 *
	 * @param  string  $name Download name
	 * @since  3.4.4
	 * @return int     Download ID
	 */
	function get_download_id_by_name( $name = '' ) {

		$download_id = false;
		$download    = get_page_by_title( $name, OBJECT, 'download' );

		if( $download ) {
			$download_id = $download->ID;
		}

		return apply_filters( 'edd_sl_get_download_id_by_name', $download_id, $name );
	}

	/**
	 * Check if the license key is attributed to the download id given.
	 * Constant EDD_BYPASS_ITEM_ID_CHECK can bypass this check if true.
	 *
	 * @param  integer $download_id Download/Item ID (post_id)
	 * @param  string  $license_key License key
	 * @param  bool    $bypass_constant Allows a way to bypass the constant for cases outside of the download process
	 * @return bool               true/false
	 */
	function is_download_id_valid_for_license( $download_id = 0, $license_key = '', $bypass_constant = false ) {

		$license_download = (int) $this->get_download_id_by_license( $license_key );

		if ( defined( 'EDD_BYPASS_ITEM_ID_CHECK' ) && EDD_BYPASS_ITEM_ID_CHECK && true !== $bypass_constant ) {
			$license_match = true;
		} else {
			$license_match = (bool) ( $license_download === (int) $download_id );
		}

		return apply_filters( 'edd_sl_id_license_match', $license_match, $download_id, $license_download, $license_key );

	}

	/**
	 * Returns the name of the download ID
	 *
	 * @param int $license_id
	 * @since 3.4
	 * @return int
	 */
	function get_download_name( $license_id = 0 ) {
		return get_the_title( $this->get_download_id( $license_id ) );
	}

	/**
	 * Returns the download ID of a license key
	 * @since 2.7
	 * @param int $license_id
	 * @return int
	 */
	function get_download_id( $license_id = 0 ) {

		return get_post_meta( $license_id, '_edd_sl_download_id', true );

	}

	/**
	 * Returns the user ID (if any) the license belongs to, if none is found in post meta
	 * it retrieves it from the payment and populates the post meta
	 *
	 * @access public
	 * @since  3.4.8
	 * @param  int $license_id
	 * @return int
	 */
	public function get_user_id( $license_id = 0 ) {

		$user_id = get_post_meta( $license_id, '_edd_sl_user_id', true );

		if( empty( $user_id ) ) {

			$payment_id = $this->get_payment_id( $license_id );
			$payment    = new EDD_Payment( $payment_id );
			$user_id    = $payment->user_id;

			if( ! empty( $user_id ) ) {
				update_post_meta( $license_id, '_edd_sl_user_id', $user_id );
			}

		}

		return $user_id;

	}

	/**
	 * Returns the price ID for a license key
	 *
	 * @since 3.3.
	 * @param int $license_id
	 *
	 * @return int
	 */
	function get_price_id( $license_id = 0 ) {

		$price_id    = get_post_meta( $license_id, '_edd_sl_download_price_id', true );
		$download_id = edd_software_licensing()->get_download_id( $license_id );
		$prices      = edd_get_variable_prices( $download_id );

		if( '' === $price_id ) {

			$payment_id  = get_post_meta( $license_id, '_edd_sl_payment_id', true );
			$payment     = new EDD_Payment( $payment_id );

			foreach( $payment->downloads as $payment_item ) {

				if( (int) $payment_item['id'] !== (int) $download_id ) {
					continue;
				}

				if( isset( $payment_item['options']['price_id'] ) ) {

					$price_id = $payment_item['options']['price_id'];

					update_post_meta( $license_id, '_edd_sl_download_price_id', $price_id );
					break;

				}

			}

		}

		if ( ! isset( $prices[ $price_id ] ) ) {

			// Price ID no longer exists, fallback to default
			$price_id = edd_get_default_variable_price( $download_id );
			update_post_meta( $license_id, '_edd_sl_download_price_id', $price_id );

		}

		return $price_id;

	}

	/**
	 * Returns the payment ID of a license key
	 *
	 * @since 3.4
	 * @param int $license_id
	 * @return int
	 */
	function get_payment_id( $license_id = 0 ) {
		return get_post_meta( $license_id, '_edd_sl_payment_id', true );
	}

	/**
	 * @param int $payment_id
	 *
	 * @return array|bool
	 */
	function get_licenses_of_purchase( $payment_id ) {

		$args = array(
			'posts_per_page' => 1000,
			'meta_key'       => '_edd_sl_payment_id',
			'meta_value'     => $payment_id,
			'post_type'      => 'edd_license',
			'post_status'    => 'any',
			'post_parent'    => 0
		);

		$licenses = get_posts( $args );

		if ( $licenses ) {

			// Now see if there are child keys
			$args = array(
				'posts_per_page'  => 1000,
				'post_parent__in' => wp_list_pluck( $licenses, 'ID' ),
				'post_type'       => 'edd_license',
				'post_status'     => 'any'
			);

			$child_keys = get_posts( $args );

			if( $child_keys ) {

				$licenses = array_merge( $licenses, $child_keys );

			}

			return $licenses;
		}

		return false;

	}

	/**
	 * @param int  $purchase_id
	 * @param int  $download_id
	 * @param mixed $cart_index
	 * @param bool $allow_children If we should return child licenses if found on the payment containing a bundle
	 *
	 * @return WP_Post|bool Returns license, if found. If not, returns false
	 */
	function get_license_by_purchase( $purchase_id = 0, $download_id = 0, $cart_index = false, $allow_children = true ) {

		$meta_query = array(
			array(
				'key'   => '_edd_sl_payment_id',
				'value' => $purchase_id
			),
			array(
				'key'   => '_edd_sl_download_id',
				'value' => $download_id
			)
		);

		if( false !== $cart_index ) {
			$meta_query[] = array(
				'key'   => '_edd_sl_cart_index',
				'value' => $cart_index
			);
		}

		$args = array(
			'posts_per_page' => 1,
			'meta_query'     => $meta_query,
			'post_type'      => 'edd_license',
			'post_status'    => 'any',
		);

		if ( false === $allow_children ) {
			$args['post_parent'] = 0;
		}

		$licenses = get_posts( $args );

		if ( $licenses ) {
			return $licenses[0];
		}

		return false;

	}

	/**
	 * Retrieve all license keys for a user
	 *
	 * @param int $user_id
	 *
	 * @since 3.4
	 *
	 * @return array
	 */
	function get_license_keys_of_user( $user_id = 0 ) {

		if( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if( empty( $user_id ) ) {
			return array();
		}

		$meta_query = array(
			array(
				'key'   => '_edd_sl_user_id',
				'value' => $user_id
			)
		);

		$args = array(
			'posts_per_page' => 100,
			'meta_query'     => $meta_query,
			'post_type'      => 'edd_license',
			'post_status'    => 'any'
		);

		$license_keys = get_posts( $args );

		// "License" was improperly capitalized. Filter corrected but typo maintained for backwards compatibility
		$license_keys = apply_filters( 'edd_sl_get_License_keys_of_user', $license_keys, $user_id );
		return apply_filters( 'edd_sl_get_license_keys_of_user', $license_keys, $user_id );

	}

	/**
	 * @param int $license_id
	 *
	 * @return string
	 */
	function get_license_status( $license_id ) {

		$status          = strtolower( get_post_meta( $license_id, '_edd_sl_status', true ) );
		$license_expires = get_post_meta( $license_id, '_edd_sl_expiration', true );

		if ( $license_expires && $license_expires < time() && 'expired' !== $status ) {
			$status = 'expired';
			$this->set_license_status( $license_id, $status );
		} elseif( 'expired' === $status && $license_expires > time() ) {
			$status = $this->get_site_count( $license_id ) >= 1 ? 'active' : 'inactive';
			$this->set_license_status( $license_id, $status );
		}

		return $status;

	}

	/**
	 * Returns the status label
	 *
	 * @param int $license_id
	 *
	 * @since 2.7
	 * @return string
	 */
	function license_status( $license_id ) {

		$status = $this->get_license_status( $license_id );

		switch( $status ) {

			case 'active' :
				$status = __( 'Active', 'edd_sl' );
				break;

			case 'inactive' :
				$status = __( 'Inactive', 'edd_sl' );
				break;

			case 'expired' :
				$status = __( 'Expired', 'edd_sl' );

				if( edd_sl_renewals_allowed() ) {
					$renewal_link = edd_get_checkout_uri( array(
						'edd_license_key' => $this->get_license_key( $license_id ),
						'download_id'     => $this->get_download_id( $license_id )
					) );
					$status .= ', <a href="' . esc_url( $renewal_link ) . '" title="' . __( 'Renew this license', 'edd_sl' ) . '">' . __( 'renew now', 'edd_sl' ) . '</a>';
				}
				break;

		}

		return $status;

	}

	/**
	 * @param int $license_id
	 * @param string $status
	 */
	function set_license_status( $license_id, $status = 'active' ) {

		if( empty( $license_id ) ) {
			return;
		}

		$current_status = get_post_meta( $license_id, '_edd_sl_status', true );

		if( strtolower( $current_status ) === strtolower( $status ) ) {
			return; // Statuses are the same
		}

		do_action( 'edd_sl_pre_set_status', $license_id, $status );

		update_post_meta( $license_id, '_edd_sl_status', $status );

		if( 'expired' == $status ) {

			// Determine if we should send an email when a license key is marked as expired
			$notice_on_expired = false;
			$notices = edd_sl_get_renewal_notices();

			foreach( $notices as $key => $notice ) {

				if( 'expired' == $notice['send_period'] ) {
					$edd_sl_emails = new EDD_SL_Emails;
					$edd_sl_emails->send_renewal_reminder( $license_id, $key );
				}

			}

		}

		// Update status for child licenses.
		$args = array(
			'post_type'      => 'edd_license',
			'post_parent'    => $license_id,
			'posts_per_page' => 1000,
			'post_status'    => 'any',
			'fields'         => 'ids'
		);

		$child_licenses = get_posts( $args );

		foreach ( $child_licenses as $child_id ) {
			$this->set_license_status( $child_id, $status );
		}

		do_action( 'edd_sl_post_set_status', $license_id, $status );
	}

	/**
	 * @param int $license_id
	 * @param int $payment_id
	 * @param int $download_id
	 *
	 * @return string
	 */
	function get_license_length( $license_id = 0, $payment_id = 0, $download_id = 0 ) {

		if( empty( $download_id ) ) {
			$download_id = $this->get_download_id( $license_id );
		}

		$license_post = get_post( $license_id );
		if ( $license_post && $license_post->post_parent && get_post_status( $license_post->post_parent ) ) {
			$download_id = get_post_meta( $license_post->post_parent, '_edd_sl_download_id', true );
		}

		$download_is_lifeitme = get_post_meta( $download_id, 'edd_sl_download_lifetime', true );

		if ( ! empty( $download_is_lifeitme ) ) {
			$expiration = 'lifetime';
		} else {
			$exp_unit   = get_post_meta( $download_id, '_edd_sl_exp_unit', true );
			$exp_length = get_post_meta( $download_id, '_edd_sl_exp_length', true );

			if( empty( $exp_unit ) ) {
				$exp_unit = 'years';
			}

			if( empty( $exp_length ) ) {
				$exp_length = '1';
			}

			$expiration = '+' . $exp_length . ' ' . $exp_unit;
		}

		$expiration = apply_filters( 'edd_sl_license_exp_lengh', $expiration, $payment_id, $download_id, $license_id ); // for backward compatibility
		$expiration = apply_filters( 'edd_sl_license_exp_length', $expiration, $payment_id, $download_id, $license_id );

		return $expiration;
	}

	/**
	 * @param int $license_id
	 *
	 * @return bool
	 */
	function is_lifetime_license( $license_id ) {
		$is_lifetime = get_post_meta( $license_id, '_edd_sl_is_lifetime', true );

		return empty( $is_lifetime ) ? false : true;
	}

	/**
	 * @param int $license_id
	 *
	 * @return bool|mixed|string
	 */
	function get_license_expiration( $license_id ) {

		$is_lifetime_license = $this->is_lifetime_license( $license_id );
		if ( $is_lifetime_license ) {
			$expiration = 'lifetime';
		} else {
			$expiration = get_post_meta( $license_id, '_edd_sl_expiration', true );
		}

		return $expiration;
	}

	/**
	 * @param int $license_id
	 * @param int $expiration
	 *
	 * @return void
	 */
	function set_license_expiration( $license_id, $expiration ) {

		if( empty( $license_id ) ) {
			return;
		}

		// $expiration should be a valid timestamp
		do_action( 'edd_sl_pre_set_expiration', $license_id, $expiration );
		update_post_meta( $license_id, '_edd_sl_expiration', $expiration );
		do_action( 'edd_sl_post_set_expiration', $license_id, $expiration );

		// Update expiration date for child licenses.
		$args = array(
			'post_type'      => 'edd_license',
			'post_parent'    => $license_id,
			'posts_per_page' => 1000,
			'post_status'    => 'any',
			'fields'         => 'ids'
		);

		$child_licenses = get_posts( $args );

		foreach ( $child_licenses as $child_id ) {
			$this->set_license_expiration( $child_id, $expiration );
		}
	}

	/**
	 * @param int $license_id
	 * @return void
	 */
	function set_license_as_lifetime( $license_id ) {

		if( empty( $license_id ) ) {
			return;
		}

		do_action( 'edd_sl_pre_set_lifetime', $license_id );
		update_post_meta( $license_id, '_edd_sl_is_lifetime', 1 );
		do_action( 'edd_sl_post_set_lifetime', $license_id );

		// Update lifetime status for child licenses.
		$args = array(
			'post_type'      => 'edd_license',
			'post_parent'    => $license_id,
			'posts_per_page' => 1000,
			'post_status'    => 'any',
			'fields'         => 'ids'
		);

		$child_licenses = get_posts( $args );

		foreach ( $child_licenses as $child_id ) {
			$this->set_license_as_lifetime( $child_id );
		}

	}

	/**
	 * @param int $download_id
	 * @param int $license_id
	 *
	 * @return mixed|void
	 */
	function get_license_limit( $download_id = 0, $license_id = 0 ) {
		$license = get_post( $license_id );

		if ( $license && $license->post_parent && get_post_status( $license->post_parent ) ) {
			$license_id  = $license->post_parent;
			$download_id = get_post_meta( $license->post_parent, '_edd_sl_download_id', true );

			return $this->get_license_limit( $download_id, $license_id );
		}

		$limit    = get_post_meta( $download_id, '_edd_sl_limit', true );
		$price_id = false;

		// Check to see if this license key has a manual override
		$l_limit  = get_post_meta( $license_id, '_edd_sl_limit', true );

		if( '' != $l_limit ) {

			$limit = $l_limit;

		} else {

			if( edd_has_variable_prices( $download_id ) ) {

				$price_id = (int) $this->get_price_id( $license_id );
				if( false !== $price_id ) {
					$price_limit = $this->get_price_activation_limit( $download_id, $price_id );

					if( false !== $price_limit ) {
						$limit = $price_limit;
					}
				}
			}
		}

		return apply_filters( 'edd_get_license_limit', $limit, $download_id, $license_id, $price_id );

	}

	/**
	 * Returns the license activation limit in a readable format
	 *
	 * @param int $license_id
	 * @since 2.7
	 * @return string|int
	 */
	function license_limit( $license_id = 0 ) {

		$download_id = $this->get_download_id( $license_id );
		$limit       = $this->get_license_limit( $download_id, $license_id );

		if( $limit <= 0 ) {
			$limit = __( 'Unlimited', 'edd_sl' );
		}

		return $limit;

	}

	/**
	 * @param int  $download_id
	 * @param null $price_id
	 *
	 * @return bool|int
	 */
	function get_price_activation_limit( $download_id = 0, $price_id = null ) {

		$prices = edd_get_variable_prices( $download_id );

		if ( isset( $prices[ $price_id ][ 'license_limit' ] ) ) {
			return absint( $prices[ $price_id ][ 'license_limit' ] );
		}

		return false;
	}

	/**
	 * @param int  $download_id
	 * @param int $price_id
	 *
	 * @return bool
	 */
	function get_price_is_lifetime( $download_id = 0, $price_id = null ) {

		$prices = edd_get_variable_prices( $download_id );

		if ( ! empty( $prices[ $price_id ][ 'is_lifetime' ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param int $license_id
	 * @param int $download_id
	 *
	 * @return bool
	 */
	function is_at_limit( $license_id = 0, $download_id = 0 ) {

		$ret = false;
		$site_count = $this->get_site_count( $license_id );
		$limit      = $this->get_license_limit( $download_id, $license_id );

		if( $limit > 0 ) {

			if( $site_count >= absint( $limit ) ) {
				$ret = true; // license is at limit
			}
		}

		return (bool) apply_filters( 'edd_sl_license_at_limit', $ret, $license_id, $limit, $download_id );

	}

	/**
	 * @param int $payment_id
	 *
	 * @return bool
	 */
	function is_renewal( $payment_id = 0 ) {
		$renewal = edd_get_payment_meta( $payment_id, '_edd_sl_is_renewal', true );
		$ret     = false;

		if( ! empty( $renewal ) ) {
			$ret = true;
		}

		return $ret;
	}

	/**
	 * Sanitize the item names to be able to compare them properly (else we get problems with HTML special characters created
	 * by WordPress like hyphens replaced by long dashes
	 *
	 * @param int $download_id
	 * @param string $item_name
	 * @return boolean
	 * @since 2.5
	 */
	function check_item_name( $download_id = 0, $item_name = 0 ) {
		$post      = get_post( $download_id );
		$tmp_name  = sanitize_title( urldecode( $item_name ) );
		$tmp_title = sanitize_title( $post->post_title );

		return apply_filters( 'edd_sl_check_item_name', $tmp_title == $tmp_name, $download_id, $item_name );
	}

	/**
	 * @param $download_id
	 *
	 * @return bool|mixed
	 */
	function get_download_version( $download_id ) {

		return get_post_meta( $download_id, '_edd_sl_version', true );

	}

	/**
	 * @param int    $download_id
	 * @param string $license_key
	 * @param string $url
	 *
	 * @return mixed|void
	 */
	function get_encoded_download_package_url( $download_id = 0, $license_key = '', $url = '' ) {

		$package_download = new EDD_SL_Package_Download;
		return $package_download->get_encoded_download_package_url( $download_id, $license_key, $url );
	}

	/**
	 * @param int    $download_id
	 * @param string $license_key
	 * @param string $hash
	 * @param int    $expires
	 */
	function get_download_package( $download_id = 0, $license_key = '', $hash, $expires = 0 ) {
		EDD_SL_Package_Download::get_download_package( $download_id, $license_key, $hash, $expires );
	}

	/**
	 * Force activation count increase
	 *
	 * This checks whether we should always count activations
	 *
	 * By default activations are tied to URLs so that a single URL is not counted as two separate activations.
	 * Desktop software, for example, is not tied to a URL so it can't be counted in the same way.
	 *
	 * @param int $license_id
	 * @access      private
	 * @since       1.3.9
	 * @return      bool
	*/

	public function force_increase( $license_id = 0 ) {

		global $edd_options;

		$ret = isset( $edd_options['edd_sl_force_increase'] );

		return (bool) apply_filters( 'edd_sl_force_activation_increase', $ret, $license_id );
	}


	/**
	 * Add the /changelog enpoint
	 *
	 * Allows for the product changelog to be shown as plain text
	 *
	 * @access      public
	 * @since       1.7
	*/

	public function changelog_endpoint() {
		add_rewrite_endpoint( 'changelog', EP_PERMALINK );
	}


	/**
	 * Displays a changelog
	 *
	 * @access      public
	 * @since       1.7
	*/

	public function show_changelog() {

		global $wp_query;

		if ( ! isset( $wp_query->query_vars['changelog'] ) || ! isset( $wp_query->query_vars['download'] ) ) {
			return;
		}

		$download = get_page_by_path( $wp_query->query_vars['download'], OBJECT, 'download' );

		if( ! is_object( $download ) || 'download' != $download->post_type ) {
			return;
		}

		$changelog = get_post_meta( $download->ID, '_edd_sl_changelog', true );

		if( $changelog ) {
			echo $changelog;
		} else {
			_e( 'No changelog found', 'edd_sl' );
		}

		exit;
	}

	/**
	 * Prevent file downloads on expired license keys
	 *
	 * @access      public
	 * @since       2.3
	 *
	 * @param int $download_id
	 * @param string $email
	*/

	public function prevent_expired_downloads( $download_id = 0, $email = '' ) {
		$payment_id = edd_get_purchase_id_by_key( $_GET['download_key'] );
		$license    = $this->get_license_by_purchase( $payment_id, $download_id );

		if( ! $license ) {
			return;
		}

		if( 'expired' == $this->get_license_status( $license->ID ) ) {
			wp_die( __( 'Your license key for this purchase is expired. Renew your license key and you will be allowed to download your files again.', 'edd_sl' ), __( 'Expired License', 'edd_sl' ), array( 'response' => 401 ) );
		} elseif( 'publish' != $license->post_status ) {
			wp_die( __( 'Your license key for this purchase has been revoked.', 'edd_sl' ), __( 'Expired License', 'edd_sl' ), array( 'response' => 401 ) );
		}

	}

	/**
	 * Removes the queries caused by `widgets_init` for remote API calls (and for generating the download)
	 *
	 * @return void
	 */
	public function reduce_query_load() {

		if( ! isset( $_REQUEST['edd_action'] ) ) {
			return;
		}

		$actions = array(
			'activate_license',
			'deactivate_license',
			'get_version',
			'package_download',
			'check_license'
		);

		if( in_array( $_REQUEST['edd_action'], $actions ) ) {
			remove_all_actions( 'widgets_init' );
		}
	}

	/**
	 * Updates license details when a payment is updated
	 *
	 * @param int $payment_id
	 *
	 * @return void
	 */
	public function update_licenses_on_payment_update( $payment_id ) {


		if( version_compare( EDD_VERSION, '2.3', '>=' ) ) {

			$customer_id = edd_get_payment_customer_id( $payment_id );
			$customer    = new EDD_Customer( $customer_id );
			$user_id     = $customer->user_id;
			$new_email   = $customer->email;

		} else {

			$user_id   = intval( $_POST['edd-payment-user-id'] );
			$new_email = sanitize_text_field( $_POST['edd-payment-user-email'] );

		}

		$licenses = $this->get_licenses_of_purchase( $payment_id );

		if( $licenses ) {

			foreach( $licenses as $license ) {

				// Update our user IDs
				update_post_meta( $license->ID, '_edd_sl_user_id', $user_id );

				// Update the email address
				$title     = $license->post_title;
				$args      = array_map( 'trim', explode( '-', $title ) );
				$title_pos = strpos( $title, '-' ) + 1;
				$length    = strlen( $title );
				$email     = substr( $title, $title_pos, $length );

				if( ! $email || $email != $new_email ) {

					$new_title = $args[0] . ' - ' . $new_email;
					$post_args = array(
						'ID'         => $license->ID,
						'post_title' => $new_title
					);

					wp_update_post( $post_args );

				}

			}

		}

	}

	/**
	* Lowercases site URL's, strips HTTP protocols and strips www subdomains.
	*
	* @param string $url
	 *
	* @return string
	*/
	public function clean_site_url( $url ) {

		$url = strtolower( $url );

		if( apply_filters( 'edd_sl_strip_www', true ) ) {

			// strip www subdomain
			$url = str_replace( '://www.', '://', $url );

		}

		if( apply_filters( 'edd_sl_strip_protocal', true ) ) {
			// strip protocol
			$url = str_replace( array( 'http://', 'https://' ), '', $url );

		}

		if( apply_filters( 'edd_sl_strip_port_number', true ) ) {

			$port = parse_url( $url, PHP_URL_PORT );

			if( $port ) {

				// strip port number
				$url = str_replace( ':' . $port, '', $url );
			}

		}

		return sanitize_text_field( $url );
	}

	/**
	 * Looks up license keys by email that match the registering user
	 *
	 * This is for users that purchased as a guest and then came
	 * back and created an account.
	 *
	 * @access      public
	 * @since       3.1
	 * @param      int $user_id the new user's ID
	 * @return      void
	 */
	function add_past_license_keys_to_new_user( $user_id ) {

		$email    = get_the_author_meta( 'user_email', $user_id );
		$licenses = get_posts( array( 's' => $email, 'post_type' => 'edd_license', 'fields' => 'ids' ) );

		if( $licenses ) {

			foreach( $licenses as $license_id ) {

				if( intval( get_post_meta( $license_id, '_edd_sl_user_id', true ) ) > 0 ) {
					continue; // This license already associated with an account
				}

				// Store the updated user ID in the license meta
				update_post_meta( $license_id, '_edd_sl_user_id', $user_id );

			}

		}

	}

	/**
	 * Check if a URL is considered a local one
	 *
	 * @since  3.2.7
	 * @param  string  $url The URL Provided
	 * @return boolean      If we're considering the URL local or not
	 */
	function is_local_url( $url = '' ) {
		$is_local_url = false;

		// Trim it up
		$url = strtolower( trim( $url ) );

		// Need to get the host...so let's add the scheme so we can use parse_url
		if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
			$url = 'http://' . $url;
		}

		$url_parts = parse_url( $url );
		$host      = ! empty( $url_parts['host'] ) ? $url_parts['host'] : false;

		if ( ! empty( $url ) && ! empty( $host ) ) {

			if ( false !== ip2long( $host ) ) {
				if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$is_local_url = true;
				}
			} else if ( 'localhost' === $host ) {
				$is_local_url = true;
			}

			$check_tlds = apply_filters( 'edd_sl_validate_tlds', true );
			if ( $check_tlds ) {
				$tlds_to_check = apply_filters( 'edd_sl_url_tlds', array(
					'.dev', '.local',
				) );

				foreach ( $tlds_to_check as $tld ) {
					if ( false !== strpos( $host, $tld ) ) {
						$is_local_url = true;
						continue;
					}
				}
			}

			if ( substr_count( $host, '.' ) > 1 ) {
				$subdomains_to_check = apply_filters( 'edd_sl_url_subdomains', array(
					'dev.', 'staging.',
				) );

				foreach ( $subdomains_to_check as $subdomain ) {
					if ( 0 === strpos( $host, $subdomain ) ) {
						$is_local_url = true;
						continue;
					}
				}
			}
		}

		return apply_filters( 'edd_sl_is_local_url', $is_local_url, $url );
	}

}

/**
 * The main function responsible for returning the one true EDD_Software_Licensing
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd_sl = edd_software_licensing(); ?>
 *
 * @since 1.4
 * @return EDD_Software_Licensing The one true Easy_Digital_Downloads Instance
 */
function edd_software_licensing() {
	return EDD_Software_Licensing::instance();
}
// Get EDD Software Licensing Running
add_action( 'plugins_loaded', 'edd_software_licensing' );


function edd_sl_install() {

	$current_version = get_option( 'edd_sl_version' );

	if ( ! $current_version ) {
		require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrade-functions.php';

		// When new upgrade routines are added, mark them as complete on fresh install
		$upgrade_routines = array(
			'sl_add_bundle_licenses',
			'sl_deprecate_site_count_meta',
		);

		foreach ( $upgrade_routines as $upgrade ) {
			edd_set_upgrade_complete( $upgrade );
		}
	}

	add_option( 'edd_sl_version', EDD_SL_VERSION, '', false );

}
register_activation_hook( __FILE__, 'edd_sl_install' );
