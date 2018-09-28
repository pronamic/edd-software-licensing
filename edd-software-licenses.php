<?php
/*
Plugin Name: Easy Digital Downloads - Software Licensing
Plugin URL: https://easydigitaldownloads.com/downloads/software-licensing/
Description: Adds a software licensing system to Easy Digital Downloads
Version: 3.6.5
Author: Easy Digital Downloads
Author URI: https://easydigitaldownloads.com
Contributors: easydigitaldownloads, mordauk, cklosows
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
	define( 'EDD_SL_VERSION', '3.6.5' );
}

class EDD_Software_Licensing {

	/**
	 * @var EDD_Software_Licensing The one true EDD_Software_Licensing
	 * @since 1.5
	 */
	private static $instance;

	/**
	 * @var EDD_SL_License_DB
	 * @since 3.6
	 */
	public $licenses_db;

	/**
	 * @var EDD_SL_License_Meta_DB
	 * @since 3.6
	 */
	public $license_meta_db;

	/**
	 * @var EDD_SL_Activations_DB
	 * @since 3.6
	 */
	public $activations_db;

	/**
	 * @var EDD_SL_Roles
	 * @since 3.6
	 */
	public $roles;

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
		if ( class_exists( 'Easy_Digital_Downloads' ) ) {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Software_Licensing ) ) {
				self::$instance = new EDD_Software_Licensing;

				self::$instance->includes();
				self::$instance->actions();

				self::$instance->licenses_db     = new EDD_SL_License_DB();
				self::$instance->license_meta_db = new EDD_SL_License_Meta_DB();
				self::$instance->activations_db  = new EDD_SL_Activations_DB();
				self::$instance->roles           = new EDD_SL_Roles();
			}
			return self::$instance;
		}
	}

	/**
	 * Load the includes for EDD SL
	 *
	 * @since  3.2.4
	 * @return void
	 */
	private function includes() {

		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-db.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-license-db.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-license-meta-db.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-activations-db.php' );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EDD_SL_PLUGIN_DIR . '/includes/integrations/wp-cli.php';
		}

		if( is_admin() ) {

			if( class_exists( 'EDD_License' ) ) {
				$edd_sl_license = new EDD_License( __FILE__, 'Software Licensing', EDD_SL_VERSION, 'Easy Digital Downloads', 'edd_sl_license_key' );
			}

			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/customers.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/metabox.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/settings.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/export.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/reports.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/upgrades.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/licenses.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/license-actions.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/license-functions.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/payment-filters.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-retroactive-licensing.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-list-table.php' );
			include_once( EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-admin-notices.php' );
			$EDD_SL_Retroactive_Licensing = new EDD_SL_Retroactive_Licensing();
		}

		include_once( EDD_SL_PLUGIN_DIR . 'includes/scripts.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/errors.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/post-types.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/widgets.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/templates.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/license-upgrades.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/license-actions.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/license-renewals.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/readme.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/shortcodes.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/rest-api.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/filters.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/misc-functions.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-emails.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-changelog-widget.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-package-download.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-license.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-download.php' );
		include_once( EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-roles.php' );

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
		add_action( 'edd_process_verified_download', array( $this, 'prevent_expired_downloads' ), 10, 4 );

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
	 * Load API endpoint.
	 *
	 * @return void
	 */
	public function load_api_endpoint() {
		// If this is an API Request, load the endpoint
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

				/**
				 * Allow further processing of requests.
				 *
				 * @since 3.6
				 *
				 * @param string $request_type  Type of API request.
				 * @param string $request_class Class that will handle the API request.
				 */
				do_action( 'edd_sl_load_api_endpoint', $request_type, $request_class );
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

		/**
		 * Filter whether or not the endpoint is active.
		 *
		 * @since 3.6
		 *
		 * @param bool   $is_active Is the endpoint active?
		 * @param string $endpoint  Endpoint to check.
		 */
		$is_active = apply_filters( 'edd_sl_is_endpoint_active', $is_active, $endpoint );

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

		/**
		 * Allow the API endpoint to be filtered.
		 *
		 * @since 3.6
		 *
		 * @param string $endpoint API endpoint.
		 */
		$endpoint = apply_filters( 'edd_sl_get_api_endpoint', $endpoint );

		return $endpoint;
	}

	/**
	 * Retrieve a EDD_SL_License object by ID or key
	 *
	 * @since  3.5
	 * @param  $id_or_key string|int License key or license ID
	 * @param  $by_key    bool       True if retrieving with a key instead of ID
	 * @return EDD_SL_License|bool  License object if found. False if not found.
	 */
	public function get_license( $id_or_key, $by_key = false ) {
		if ( $by_key || ! is_numeric( $id_or_key ) ) {
			$result    = edd_software_licensing()->licenses_db->get_column_by( 'id', 'license_key', sanitize_text_field( $id_or_key ) );
			$id_or_key = is_numeric( $result ) ? (int) $result : false;
		}

		if ( empty( $id_or_key ) ) {
			return false;
		}

		$id      = $id_or_key;
		$license = new EDD_SL_License( $id );

		return $license;
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

		$purchased_download = new EDD_SL_Download( $download_id );
		if ( ! $purchased_download->is_bundled_download() && ! $purchased_download->licensing_enabled() ) {
			return $keys;
		}

		$license  = new EDD_SL_License();
		$price_id = isset( $cart_item['item_number']['options']['price_id'] ) ? $cart_item['item_number']['options']['price_id'] : false;
		$license->create( $download_id, $payment_id, $price_id, $cart_index, array() );

		if ( ! empty( $license->ID ) ) {
			$keys[] = $license->ID;

			$child_licenses = $license->get_child_licenses();
			if ( ! empty( $child_licenses ) ) {
				$child_ids = wp_list_pluck( $child_licenses, 'ID' );
				$keys = array_merge( $keys, $child_ids );
			}
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
			'expiration' => current_time( 'timestamp' ), // right now
			'url'        => ''
		);

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'edd_sl_pre_activate_license_args', $args );

		extract( $args, EXTR_SKIP );

		$license   = $this->get_license( $key, true );
		$item_name = html_entity_decode( $item_name );

		if( empty( $url ) ) {

			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );

		}

		$bypass_local = isset( $edd_options['edd_sl_bypass_local_hosts'] );
		$is_local_url = empty( $bypass_local ) ? false : $this->is_local_url( $url );

		$license_id  = false !== $license ? $license->ID : 0;
		$download_id = false !== $license ? $license->download_id : 0;
		do_action( 'edd_sl_pre_activate_license', $license_id, $download_id );

		$result = array();
		$result['success'] = true;

		// this license does not even exist
		if ( empty( $license ) ) {

			$result['success'] = false;
			$result['error']   = 'missing';

		} else {

			$allow_bundle_activation = apply_filters( 'edd_sl_allow_bundle_activation', false, $license );

			// Trying to activate bundle license
			if ( $license->get_download()->is_bundled_download() && ! $allow_bundle_activation ) {

				$result['success'] = false;
				$result['error'] = 'license_not_activable';

			}

			// License key revoked
			if ( $result['success'] && 'disabled' === $license->status ) {

				$result['success'] = false;
				$result['error']   = 'disabled';

			}

			// no activations left
			if( $result['success'] && ( $license->is_at_limit() && ! $is_local_url ) && ( $this->force_increase() || ! $license->is_site_active( $url ) ) ) {

				$result['success']   = false;
				$result['error']     = 'no_activations_left';
				$result['max_sites'] = $license->activation_count;

			}

			// this license has expired'
			if ( $result['success'] && ( ! $license->is_lifetime &&  $license->expiration < $expiration ) ) {

				$result['success'] = false;
				$result['error']   = 'expired';
				$result['expires'] = $license->expiration;

			}

			// keys don't match
			if ( $result['success'] && $key != $license->key ) {

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
				if( $result['success'] && ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ! $this->check_item_name( $license->download_id, $item_name, $license ) ) {
					$result['success']   = false;
					$result['error']     = 'item_name_mismatch';
				}

			}

		}

		$result = apply_filters( 'edd_sl_activate_license_response', $result, $license );

		if( $result['success'] ) {

			// activate the site for the license
			$license->add_site( $url );

			// activate the license
			$license->status = 'active';

			if ( $is_local_url ) {
				$result['is_local'] = true;
			}

			// enter this activation in the log
			$this->log_license_activation( $license->ID, $_SERVER );

			do_action( 'edd_sl_activate_license', $license->ID, $license->download_id );


		}

		if ( false !== $license ) {
			// All good, give some additional info about the activation
			$result['license_limit'] = $license->activation_limit;
			$result['site_count']    = $license->activation_count;
			$result['expires']       = $license->expiration;

			// just leaving this in here in case others are using it
			if( $license->activation_limit > 0 ) {
				$result['activations_left'] = $license->activation_limit - $license->activation_count;
			} else {
				$result['activations_left'] = 'unlimited';
			}
		}

		$result = apply_filters( 'edd_sl_post_activate_license_result', $result, $args );

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

		$args = array(
			'item_name' => $item_name,
			'key'       => $license,
			'url'       => $url,
			'item_id'   => $item_id
		);

		$result   = $this->activate_license( $args );
		$checksum = $this->get_request_checksum( $args );
		$result['checksum'] = $checksum;

		if ( $result['success'] ) {
			$license_check = 'valid';
		} else {
			$license_check = 'invalid';
		}

		$license  = $this->get_license( $license, true );
		if ( false !== $license ) {
			$result['expires']        = false === $license->is_lifetime ? date( 'Y-m-d H:i:s', $license->expiration ) : 'lifetime';
			$result['payment_id']     = $license->payment_id;
			$result['customer_name']  = $license->customer->name;
			$result['customer_email'] = $license->customer->email;
			$result['price_id']       = $license->price_id;
		}

		if( empty( $item_name ) ) {
			$item_name = get_the_title( $item_id );
		}

		$result = array_merge( array(
			'success'   => (bool) $result['success'],
			'license'   => $license_check,
			'item_id'   => $item_id,
			'item_name' => $item_name,
		), $result );

		$license_id = false !== $license ? $license->ID : 0;
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
			'expiration' => current_time( 'timestamp' ), // right now
			'url'        => ''
		);

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'edd_sl_pre_deactivate_license_args', $args );

		extract( $args, EXTR_SKIP );

		$license = $this->get_license( $key, true );

		if ( false === $license ) {
			return false;
		}

		$item_name = html_entity_decode( $item_name );

		if( empty( $url ) ) {

			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );

		}

		$bypass_local = isset( $edd_options['edd_sl_bypass_local_hosts'] );
		$is_local_url = empty( $bypass_local ) ? false : $this->is_local_url( $url );

		do_action( 'edd_sl_pre_deactivate_license', $license->ID, $license->download_id );

		// make sure license is active
		if( $license->status != 'active' && ! $bypass_local ) {
			return false;
		}

		$allow_bundle_activation = apply_filters( 'edd_sl_allow_bundle_activation', false, $license );

		// Trying to deactivate bundle license
		if ( $license->get_download()->is_bundled_download() && ! $allow_bundle_activation ) {
			return false;
		}

		// don't deactivate if expired
		if ( ! $license->is_lifetime && $license->expiration < $expiration ) {
			return false; // this license has expired
		}

		if ( $key != $license->key ) {
			return false; // keys don't match
		}

		if( ! empty( $args['item_id'] ) ) {

			if( ! $this->is_download_id_valid_for_license( $license->download_id, $args['key'] ) ) {

				return false;
			}

		} else {

			// Item names don't match
			if( ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ! $this->check_item_name( $license->download_id, $item_name, $license ) ) {
				return false; // Item names don't match
			}

		}

		// deactivate the site for the license
		$license->remove_site( $url );

		if ( ! $is_local_url ) {

			// enter this deactivation in the log
			$this->log_license_deactivation( $license->ID, $_SERVER );

			do_action( 'edd_sl_deactivate_license', $license->ID, $license->download_id );
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

		$args = array(
			'item_id'   => $item_id,
			'item_name' => $item_name,
			'key'       => $license,
			'url'       => $url,
		);

		$result   = $this->deactivate_license( $args );
		$checksum = $this->get_request_checksum( $args );

		if ( $result ) {
			$status = 'deactivated';
		} else {
			$status = 'failed';
		}

		$license  = $this->get_license( $license, true );
		$response = array();
		if ( false !== $license ) {
			$response['expires']        = $license->expiration;
			$response['payment_id']     = $license->payment_id;
			$response['customer_name']  = $license->customer->name;
			$response['customer_email'] = $license->customer->email;
			$response['price_id']       = $license->price_id;
		}

		if( empty( $item_name ) ) {
			$item_name = get_the_title( $item_id );
		}

		header( 'Content-Type: application/json' );

		$response = array_merge( array(
			'success'   => (bool) $result,
			'license'   => $status,
			'item_id'   => $item_id,
			'item_name' => $item_name,
			'checksum'  => $checksum,
		), $response );

		echo json_encode( apply_filters( 'edd_remote_license_deactivation_response', $response, $args, $license->ID ) );

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
			'expiration' => current_time( 'timestamp' ), // right now
			'url'        => ''
		);

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'edd_sl_pre_check_license_args', $args );

		$license = $this->get_license( $args['key'], true );

		if ( false === $license ) {
			return 'invalid';
		}

		$item_name          = html_entity_decode( $args['item_name'] );
		$url                = ! empty( $args['url'] ) ? $args['url'] : '';

		if( empty( $url ) ) {

			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );

		}

		if ( $args['key'] != $license->key ) {
			return 'invalid'; // keys don't match
		}

		if( ! empty( $args['item_id'] ) ) {

			if( ! $this->is_download_id_valid_for_license( $args['item_id'], $args['key'] ) ) {
				return 'invalid_item_id';
			}

		} else {

			if( ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ! $this->check_item_name( $license->download_id, $item_name, $license ) ) {
				return 'item_name_mismatch'; // Item names don't match
			}

		}

		if ( ! $license->is_lifetime && $args['expiration'] > $license->expiration ) {
			$status = 'expired'; // this license has expired
		} elseif ( 'disabled' === $license->status ) {
			$status = 'disabled'; // License key disabled
		} elseif ( 'active' != $license->status ) {
			$status = 'inactive'; // this license is not active.
		} elseif( ! $is_local_url && ! $license->is_site_active( $url ) ) {
			$status = 'site_inactive';
		} else {
			do_action( 'edd_sl_check_license', $license->ID, $license->download_id );
			$status = 'valid'; // license still active
		}

		return apply_filters( 'edd_sl_check_license_status', $status, $license );
	}

	/**
	 * @param array $data
	 * @return void
	 */
	function remote_license_check( $data ) {

		$item_id     = ! empty( $data['item_id'] )   ? absint( $data['item_id'] )         : false;
		$item_name   = ! empty( $data['item_name'] ) ? rawurldecode( $data['item_name'] ) : false;
		$license     = isset( $data['license'] )     ? urldecode( $data['license'] )      : false;
		$url         = isset( $data['url'] )         ? urldecode( $data['url'] )          : '';

		$args = array(
			'item_id'   => $item_id,
			'item_name' => $item_name,
			'key'       => $license,
			'url'       => $url,
		);

		$result   =  $message = $this->check_license( $args );
		$checksum = $this->get_request_checksum( $args );

		if ( 'invalid' === $result ) {
			$result = false;
		}

		$response = array();
		if ( false !== $result ) {
			$license  = $this->get_license( $license, true );

			$response['expires']          = is_numeric( $license->expiration ) ? date( 'Y-m-d H:i:s', $license->expiration ) : $license->expiration;
			$response['payment_id']       = $license->payment_id;
			$response['customer_name']    = $license->customer->name;
			$response['customer_email']   = $license->customer->email;
			$response['license_limit']    = $license->activation_limit;
			$response['site_count']       = $license->activation_count;
			$response['activations_left'] = $license->activation_limit > 0 ? $license->activation_limit - $license->activation_count : 'unlimited';
			$response['price_id']         = $license->price_id;
		}

		if( empty( $item_name ) ) {
			$item_name = get_the_title( $item_id );
		}

		$response = array_merge( array(
			'success'   => (bool) $result,
			'license'   => $message,
			'item_id'   => $item_id,
			'item_name' => $item_name,
			'checksum'  => $checksum,
		), $response );

		$license_id = ! empty( $license->ID ) ? $license->ID : false;
		header( 'Content-Type: application/json' );
		echo json_encode( apply_filters( 'edd_remote_license_check_response', $response, $args, $license_id ) );

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

		$license_id = ! empty( $cart_item['item_number']['options']['license_id'] ) ? absint( $cart_item['item_number']['options']['license_id'] ) : false;

		if( $license_id ) {

			$license = $this->get_license( $license_id );

			if( empty( $license->ID ) ) {
				return;
			}

			$license->renew( $payment_id );

		}
	}

	/**
	 * @param int $license_id
	 * @param int $payment_id
	 * @param int $download_id
	 * @return void
	 */
	function renew_license( $license_id = 0, $payment_id = 0, $download_id = 0 ) {
		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return false;
		}

		return $license->renew( $payment_id );
	}

	/**
	 * Retrieve the renewal URL for a license key
	 *
	 * @since  3.4
	 * @param int $license_id
	 * @return string The renewal URL
	 */
	function get_renewal_url( $license_id = 0 ) {
		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return '';
		}

		return $license->get_renewal_url();
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

	/**
	 * Determine if a license is allowed to be renewed after it's expiration
	 *
	 * @since  3.5.4
	 * @param int $license_id
	 * @return bool
	 */
	function can_renew( $license_id = 0 ) {
		$ret = edd_sl_renewals_allowed() && 'expired' === $this->get_license_status( $license_id );

		if( $this->is_lifetime_license( $license_id ) ) {
			$ret = false;
		}

		// Verify the initial payment is at least completed
		$payment_id = $this->get_payment_id( $license_id );
		$payment    = new EDD_Payment( $payment_id );
		if ( 'publish' !== $payment->status ) {
			$ret = false;
		}
		return apply_filters( 'edd_sl_can_renew_license', $ret, $license_id );
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

		$payment = new EDD_Payment( $payment_id );

		// Revoke license keys when the payment is refunded or revoked
		if ( ! in_array( $new_status, apply_filters( 'edd_sl_revoke_license_statuses', array( 'revoked', 'refunded' ) ) ) ) {
			return;
		}

		$licenses = $this->get_licenses_of_purchase( $payment->ID );

		if( ! $licenses ) {
			return;
		}

		foreach( $licenses as $license ) {

			$cart_item = $payment->cart_details[ $license->cart_index ];
			$upgrade   = ! empty( $cart_item['item_number']['options']['is_upgrade'] ) ? true : false;
			$renewal   = ! empty( $cart_item['item_number']['options']['is_renewal'] ) ? true : false;

			if ( $upgrade ) {
				$payment_index    = array_search( $payment_id, $license->payment_ids );
				$previous_payment = false;

				// Work our way backwards through the payment IDs until we find the first completed payment, ignoring others
				$key = $payment_index - 1;
				while ( $key >= 0 ) {
					$previous_payment = new EDD_Payment( $license->payment_ids[ $key ] );

					if ( 'publish' === $previous_payment->status ) {
						break;
					}

					$key--;

				}

				if ( false === $previous_payment ) {
					continue;
				}

				// Set the download ID to the initial download ID (since it could change).
				$license->download_id = $previous_payment->cart_details[ $license->cart_index ]['id'];

				// Reset the price ID.
				if ( isset( $previous_payment->cart_details[ $license->cart_index ]['item_number']['options']['price_id'] ) ) {
					$license->price_id = $previous_payment->cart_details[ $license->cart_index ]['item_number']['options']['price_id'];
				} else {
					$license->price_id = null;
				}

				// Reset the activation limits.
				$license->reset_activation_limit();
			} else {

				// Don't revoke license keys when a renewal is refunded if the license is not expired
				if( 'expired' !== $license->status && $renewal ) {
					continue;
				}

				do_action( 'edd_sl_pre_revoke_license', $license->ID, $payment_id );
				$license->disable();
				do_action( 'edd_sl_post_revoke_license', $license->ID, $payment_id );
			}

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

		$payment = new EDD_Payment( $payment_id );

		if( 'publish' !== $payment->status && 'revoked' !== $payment->status ) {
			return;
		}

		$licenses = $this->get_licenses_of_purchase( $payment->ID );

		if( ! $licenses ) {
			return;
		}

		foreach( $licenses as $license ) {

			if ( ! empty( $download_id ) && ! $this->is_download_id_valid_for_license( $download_id, $license->key, true ) ) {
				continue;
			}

			/**
			 * If this is not the initial payment for the license, don't delete it, just roll back the expiration and remove
			 * the payment ID from the license payment IDs
			 */
			if ( (int) $payment_id !== (int) $license->payment_id ) {

				// Roll back the expiration date on the license.
				$license->expiration = strtotime( '-' . $license->license_length(), $license->expiration );

				// Delete this payment ID from the license meta.
				edd_software_licensing()->license_meta_db->delete_meta( $license->ID, '_edd_sl_payment_id', $payment_id );

				// Delete the payment date from any meta (for upgrades and renewals)
				foreach ( $payment->downloads as $item ) {
					if ( empty( $item['options']['license_id'] ) ||  (int) $item['options']['license_id'] !== $license->ID ) {
						continue;
					}

					$action = false;
					if ( ! empty( $item['options']['is_upgrade'] ) ) {
						$action = 'upgrade';
					} elseif ( ! empty( $item['options']['is_renewal'] ) ) {
						$action = 'renewal';
					}

					if ( ! empty( $action ) && ! empty( $payment->completed_date ) ) {
						$meta_key = '_edd_sl_' . $action . '_date';
						edd_software_licensing()->license_meta_db->delete_meta( $license->ID, $meta_key, $payment->completed_date );

						break; // We don't need to iterate on the items anymore.
					}
				}

				continue;

			}

			do_action( 'edd_sl_pre_delete_license', $license->ID, $payment->ID );
			$license->delete();
			do_action( 'edd_sl_post_delete_license', $license->ID, $payment->ID );

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
		$beta      = isset( $data['beta'] )      ? (bool) $data['beta']                                      : false;
		if( empty( $item_name ) && empty( $item_id ) ) {
			$item_name = isset( $data['name'] )  ? sanitize_text_field( rawurldecode( $data['name'] ) )      : false;
		}


		$response  = array(
			'new_version'    => '',
			'stable_version' => '',
			'sections'       => '',
			'license_check'  => '',
			'msg'            => '',
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

			$check_by_name_first = apply_filters( 'edd_sl_force_check_by_name', false );

			if( empty( $license ) || $check_by_name_first ) {

				$item_id = $this->get_download_id_by_name( $item_name );

			} else {

				$item_id = $this->get_download_id_by_license( $license );

			}

		}

		$download = new EDD_SL_Download( $item_id );

		if( ! $download ) {

			if( empty( $license ) || $check_by_name_first ) {
				$response['msg'] = sprintf( __( 'Item name provided does not match a valid %s', 'edd_sl' ), edd_get_label_singular() );
			} else {
				$response['msg'] = sprintf( __( 'License key provided does not match a valid %s', 'edd_sl' ), edd_get_label_singular() );
			}

			echo json_encode( $response ); exit;

		}

		$is_valid_for_download = $this->is_download_id_valid_for_license( $download->ID, $license );
		if ( ! empty( $license ) && ( ! defined( 'EDD_BYPASS_NAME_CHECK' ) || ! EDD_BYPASS_NAME_CHECK ) && ( ! $is_valid_for_download || ( ! empty( $item_name ) && ! $this->check_item_name( $download->ID, $item_name, $license ) ) ) ) {

			$download_name   = ! empty( $item_name ) ? $item_name : $download->get_name();
			$response['msg'] = sprintf( __( 'License key is not valid for %s', 'edd_sl' ), $download_name );

			echo json_encode( $response ); exit;

		}

		$stable_version = $version = $this->get_latest_version( $item_id );
		$slug           = ! empty( $slug ) ? $slug : $download->post_name;
		$description    = ! empty( $download->post_excerpt ) ? $download->post_excerpt : $download->post_content;
		$changelog      = $download->get_changelog();

		$download_beta = false;
		if ( $beta && $download->has_beta()  ) {
			$version_beta = $this->get_beta_download_version( $item_id );
			if ( version_compare( $version_beta, $stable_version, '>') ) {
				$changelog     = $download->get_beta_changelog();
				$version       = $version_beta;
				$download_beta = true;
			}
		}

		$response = array(
			'new_version'    => $version,
			'stable_version' => $stable_version,
			'name'           => $download->post_title,
			'slug'           => $slug,
			'url'            => esc_url( add_query_arg( 'changelog', '1', get_permalink( $item_id ) ) ),
			'last_updated'   => $download->post_modified,
			'homepage'       => get_permalink( $item_id ),
			'package'        => $this->get_encoded_download_package_url( $item_id, $license, $url, $download_beta ),
			'download_link'  => $this->get_encoded_download_package_url( $item_id, $license, $url, $download_beta ),
			'sections'       => serialize(
				array(
					'description' => wpautop( strip_tags( $description, '<p><li><ul><ol><strong><a><em><span><br>' ) ),
					'changelog'   => wpautop( strip_tags( stripslashes( $changelog ), '<p><li><ul><ol><strong><a><em><span><br>' ) ),
				)
			),
			'banners' => serialize(
				array(
					'high' => get_post_meta( $item_id, '_edd_readme_plugin_banner_high', true ),
					'low'  => get_post_meta( $item_id, '_edd_readme_plugin_banner_low', true )
				)
			),
			'icons' => array()
		);

		if ( has_post_thumbnail( $download->ID ) ) {
			$thumb_id  = get_post_thumbnail_id( $download->ID );
			$thumb_128 = get_the_post_thumbnail_url( $download->ID, 'sl-small' );
			if ( ! empty( $thumb_128 ) ) {
				$response['icons'][ '1x' ] = $thumb_128;
			}

			$thumb_256 = get_the_post_thumbnail_url( $download->ID, 'sl-large' );
			if ( ! empty( $thumb_256 ) ) {
				$response['icons'][ '2x' ] = $thumb_256;
			}
		}

		$response['icons'] = serialize( $response['icons'] );

		$response = apply_filters( 'edd_sl_license_response', $response, $download, $download_beta );

		/**
		 * Encode any emoji in the name and sections.
		 *
		 * @since 3.6.5
		 * @see https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1313
		 */
		if ( function_exists( 'wp_encode_emoji' ) ) {
			$response['name']     = wp_encode_emoji( $response['name'] );

			$sections             = maybe_unserialize( $response['sections'] );
			$response['sections'] = serialize( array_map('wp_encode_emoji', $sections ) );
		}

		echo json_encode( $response );
		exit;
	}

	/**
	 * Given an array of arguments, sort them by length, and then md5 them to generate a checksum.
	 *
	 * @since 3.5
	 * @param array $args
	 *
	 * @return string
	 */
	private function get_request_checksum( $args = array() ) {
		usort( $args, array( $this, 'sort_args_by_length' ) );
		$string_args = json_encode( $args );

		return md5( $string_args );
	}

	/**
	 * Used by get_request_checksum to sort the array by size.
	 *
	 * @since 3.5
	 * @param $a The first item to compare for length.
	 * @param $b The second item to compare for length.
	 *
	 * @return int The difference in length.
	 */
	private function sort_args_by_length( $a,$b ) {
		return strlen( $b ) - strlen( $a );
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
		if ( $license = $this->get_license( $license_id ) ) {
			return $license->get_logs();
		}

		return false;
	}

	/**
	 * @param int $license_id
	 * @param array $server_data
	 */
	function log_license_activation( $license_id, $server_data ) {
		if ( $license = $this->get_license( $license_id ) ) {
			$license->add_log( __( 'LOG - License Activated: ', 'edd_sl' ) . $license_id, $server_data );
		}
	}

	/**
	 * @param int $license_id
	 * @param array $server_data
	 */
	function log_license_deactivation( $license_id, $server_data ) {
		if ( $license = $this->get_license( $license_id ) ) {
			$license->add_log( __( 'LOG - License Deactivated: ', 'edd_sl' ) . $license_id, $server_data );
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
		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return false;
		}

		return $license->sites;
	}

	/**
	 * @param int $license_id
	 *
	 * @return mixed|void
	 */
	public function get_site_count( $license_id = 0 ) {
		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return false;
		}
		return $license->activation_count;
	}

	/**
	 * @param int    $license_id
	 * @param string $site_url
	 *
	 * @return bool|mixed|void
	 */
	function is_site_active( $license_id = 0, $site_url = '' ) {
		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return false;
		}

		return $license->is_site_active( $site_url );
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

		$license = $this->get_license( $license_id );
		if ( false === $license ) {
			return false;
		}

		return (bool) $license->add_site( $site_url );

	}

	/**
	 * @param int    $license_id
	 * @param string $site_url
	 *
	 * @return bool|int
	 */
	function delete_site( $license_id = 0, $site_url = '' ) {
		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return false;
		}

		return $license->remove_site( $site_url );
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
		$download = new EDD_SL_Download( $download_id );
		return $download->get_new_license_key();
	}

	/**
	 * Generate a license key.
	 *
	 * @param int $license_id
	 * @param int $download_id
	 * @param int $payment_id
	 * @param mixed $cart_index
	 * @param int   $timestamp  A numeric timestamp in order to attempt to 'salt' keys so they can be regenerated.
	 *
	 * @return string
	 */
	function generate_license_key( $license_id = 0, $download_id = 0, $payment_id = 0, $cart_index = 0, $timestamp = 0 ) {
		$timestamp = is_numeric( $timestamp ) && ! empty( $timestamp ) ? absint( $timestamp ) : time();
		$key       = md5( $license_id . $download_id . $payment_id . $cart_index . $timestamp );

		return apply_filters( 'edd_sl_generate_license_key', $key, $license_id, $download_id, $payment_id, $cart_index );
	}

	/**
	 * @param string $license_key
	 *
	 * @return bool|null|string
	 */
	function get_license_by_key( $license_key ) {
		$license = $this->get_license( $license_key, true );

		if ( false === $license ) {
			return false;
		}

		return $license->ID;
	}

	/**
	 * @param int $license_id
	 *
	 * @return bool|mixed
	 */
	function get_license_key( $license_id ) {
		$license = $this->get_license( $license_id );

		if ( ! $license ) {
			return false;
		}

		return $license->key;
	}

	/**
	 * @param string $license_key
	 *
	 * @return mixed|void
	 */
	function get_download_id_by_license( $license_key ) {
		$license     = $this->get_license( $license_key, true );

		if ( ! $license ) {
			return false;
		}

		$download_id = $license->download_id;

		return apply_filters( 'edd_sl_get_download_id_by_license', $download_id, $license_key, $license->ID );
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
		$download    = get_page_by_title( urldecode( $name ), OBJECT, 'download' );

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
		$license = $this->get_license( $license_id );


		if ( ! $license ) {
			return false;
		}

		return $license->get_download()->get_name();
	}

	/**
	 * Returns the download ID of a license key
	 * @since 2.7
	 * @param int $license_id
	 * @return int
	 */
	function get_download_id( $license_id = 0 ) {
		$license = $this->get_license( $license_id );

		if ( ! $license ) {
			return false;
		}

		return $license->download_id;
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
		$license = $this->get_license( $license_id );

		if ( ! $license ) {
			return false;
		}

		return $license->user_id;
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
		$license  = $this->get_license( $license_id );
		return $license->price_id;
	}

	/**
	 * Returns the payment ID of a license key
	 *
	 * @since 3.4
	 * @param int $license_id
	 * @return int
	 */
	function get_payment_id( $license_id = 0 ) {
		$license = $this->get_license( $license_id );

		if ( ! $license ) {
			return false;
		}

		return $license->payment_id;
	}

	/**
	 * @param int $payment_id
	 *
	 * @return array|bool
	 */
	function get_licenses_of_purchase( $payment_id ) {
		global $wpdb;

		// Get licenses where this payment ID was the initial purchase.
		$licenses = self::$instance->licenses_db->get_licenses( array(
			'number'     => - 1,
			'payment_id' => $payment_id,
		) );

		if ( ! empty( $licenses ) ) {
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

		$args = array(
			'number' => 1,
		);

		if ( ! empty( $purchase_id ) ) {
			$args['payment_id'] = $purchase_id;
		}

		if ( ! empty( $download_id ) ) {
			$args['download_id'] = $download_id;
		}

		if( false !== $cart_index ) {
			$args['cart_index'] = $cart_index;
		}

		if ( false === $allow_children ) {
			$args['parent'] = 0;
		}

		$licenses = self::$instance->licenses_db->get_licenses( $args );

		if ( ! empty( $licenses ) ) {
			$license = $licenses[0];
			return apply_filters( 'edd_sl_licenses_by_purchase', $license, $purchase_id, $download_id, $cart_index );
		}

		return false;

	}

	/**
	 * Retrieve all license keys for a user
	 *
	 * @param int  $user_id The user ID to get licenses for
	 * @param bool $include_child_licenses If true (default) we will get all licenses including children of a bundle
	 *                                     when false, the method will only return licenses without a post_parent
	 *
	 * @since 3.4
	 * @param  $user_id     int The ID of the user to filter by
	 * @param  $download_id int The ID of a download to filter by
	 * @param  $status      string The license status to filter by, or all
	 * @return array
	 */
	function get_license_keys_of_user( $user_id = 0, $download_id = 0, $status = 'any', $include_child_licenses = true ) {

		if( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if( empty( $user_id ) ) {
			return array();
		}

		$args = array(
			'number'  => 50,
			'user_id' => $user_id,
			'orderby' => 'date_created',
			'order'   => 'DESC',
		);

		if( ! empty( $download_id ) ) {
			$args['download_id'] = $download_id;
		}

		$status = strtolower( $status );
		if( $status !== 'all' && $status !== 'any' ) {
			$args['status'] = $status;
		}

		if ( false === $include_child_licenses ) {
			$args['parent'] = 0;
		}

		/**
		 * Filters the arguments for the query to get the license keys of a user.
		 *
		 * @param array $args The arguments for get_posts
		 * @param int   $user_id The user this query is for.
		 */
		$args = apply_filters( 'edd_sl_get_license_keys_of_user_args', $args, $user_id );

		$license_keys = edd_software_licensing()->licenses_db->get_licenses( $args );

		// "License" was improperly capitalized. Filter corrected but typo maintained for backwards compatibility
		$license_keys = apply_filters( 'edd_sl_get_License_keys_of_user', $license_keys, $user_id );
		return apply_filters( 'edd_sl_get_license_keys_of_user', $license_keys, $user_id );

	}

	/**
	 * Given a license ID, return any child licenses it may have
	 *
	 * @since 3.4.8
	 * @param int $parent_license_id The parent license ID to look up
	 *
	 * @return array Array of child license objects.
	 */
	function get_child_licenses( $parent_license_id = 0 ) {
		$license = $this->get_license( $parent_license_id );
		return $license->get_child_licenses();
	}

	/**
	 * @param int $license_id
	 *
	 * @return string
	 */
	function get_license_status( $license_id ) {
		$license = $this->get_license( $license_id );
		return $license->status;
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
		$license = $this->get_license( $license_id );
		return $license->get_display_status();
	}

	/**
	 * @param int $license_id
	 * @param string $status
	 */
	function set_license_status( $license_id, $status = 'active' ) {

		if( empty( $license_id ) ) {
			return;
		}

		$license = $this->get_license( $license_id );
		$updated = $license->status = $status;

		return $updated;
	}

	/**
	 * @param int $license_id
	 * @param int $payment_id
	 * @param int $download_id
	 *
	 * @return string
	 */
	function get_license_length( $license_id = 0, $payment_id = 0, $download_id = 0 ) {
		$license = $this->get_license( $license_id );
		return $license->license_length();
	}

	/**
	 * @param int $license_id
	 *
	 * @return bool
	 */
	function is_lifetime_license( $license_id ) {
		$license = $this->get_license( $license_id );
		return $license->is_lifetime;
	}

	/**
	 * @param int $license_id
	 *
	 * @return bool|mixed|string
	 */
	function get_license_expiration( $license_id ) {
		$license = $this->get_license( $license_id );
		return $license->expiration;
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

		$license = $this->get_license( $license_id );

		if ( false == $license ) {
			return false;
		}

		// $expiration should be a valid timestamp
		$license->expiration = $expiration;

	}

	/**
	 * @param int $license_id
	 * @return void
	 */
	function set_license_as_lifetime( $license_id ) {

		if( empty( $license_id ) ) {
			return;
		}

		$license = $this->get_license( $license_id );

		if ( false === $license ) {
			return false;
		}

		$license->is_lifetime = true;

	}

	/**
	 * @param int $download_id
	 * @param int $license_id
	 *
	 * @return mixed|void
	 */
	function get_license_limit( $download_id = 0, $license_id = 0 ) {
		// TODO: Set a deprecated notice when download_id isn't empty

		$license = $this->get_license( $license_id );
		return $license->activation_limit;
	}

	/**
	 * Returns the license activation limit in a readable format
	 *
	 * @param int $license_id
	 * @since 2.7
	 * @return string|int
	 */
	function license_limit( $license_id = 0 ) {
		$license = $this->get_license( $license_id );
		return $license->license_limit();
	}

	/**
	 * @param int  $download_id
	 * @param null $price_id
	 *
	 * @return bool|int
	 */
	function get_price_activation_limit( $download_id = 0, $price_id = null ) {
		$download = new EDD_SL_Download( $download_id );
		return $download->get_price_activation_limit( $price_id );
	}

	/**
	 * @param int  $download_id
	 * @param int $price_id
	 *
	 * @return bool
	 */
	function get_price_is_lifetime( $download_id = 0, $price_id = null ) {
		$download = new EDD_SL_Download( $download_id );
		return $download->is_price_lifetime( $price_id );
	}

	/**
	 * @param int $license_id
	 * @param int $download_id
	 *
	 * @return bool
	 */
	function is_at_limit( $license_id = 0, $download_id = 0 ) {
		$license = $this->get_license( $license_id );
		return $license->is_at_limit();
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
	function check_item_name( $download_id = 0, $item_name = 0, $license = null ) {
		$download  = new EDD_SL_Download( $download_id );

		$match = false;

		if ( $download->ID > 0 ) {
			$tmp_name  = sanitize_title( urldecode( $item_name ) );
			$tmp_title = sanitize_title( $download->get_name() );

			$match = $tmp_title == $tmp_name;
		}

		return apply_filters( 'edd_sl_check_item_name', $match, $download_id, $item_name, $license );
	}

	/**
	 * @param $download_id
	 *
	 * @return bool|mixed
	 */
	function get_download_version( $download_id ) {
		$download = new EDD_SL_Download( $download_id );

		if ( empty( $download->ID ) ) {
			return false;
		}

		return $download->get_version();
	}

	/**
	 * @param int $download_id Download (Post) ID
	 *
	 * @return bool|mixed
	 */
	function get_beta_download_version( $download_id ) {
		$download = new EDD_SL_Download( $download_id );

		if ( empty( $download->ID ) ) {
			return false;
		}

		return $download->get_beta_version();
	}

	/**
	 * @param int    $download_id
	 * @param string $license_key
	 * @param string $url
	 * @param bool   $download_beta
	 *
	 * @return mixed|void
	 */
	function get_encoded_download_package_url( $download_id = 0, $license_key = '', $url = '', $download_beta = false ) {

		$package_download = new EDD_SL_Package_Download;
		return $package_download->get_encoded_download_package_url( $download_id, $license_key, $url, $download_beta );
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

		$download = new EDD_SL_Download( $download->ID );

		$changelog = $download->get_changelog();

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
	public function prevent_expired_downloads( $download_id = 0, $email = '', $payment_id, $args ) {
		$can_download_response = $this->license_can_download( $download_id = 0, $email = '', $payment_id, $args );

		if ( false === $can_download_response['success']  ) {
			$defaults = array(
				'message'  => __( 'You do not have a valid license for this download.', 'edd_sl' ),
				'title'    => __( 'No Valid License', 'edd_sl' ),
				'response' => 403,
			);

			$can_download_response = wp_parse_args( $can_download_response, $defaults );
			wp_die( $can_download_response['message'], $can_download_response['title'], $can_download_response['response'] );
		}

	}

	/**
	 * Return an array of data for if a user has the ability to be delivered a file via a download link.
	 *
	 * Triggers on the edd_process_verified_download hook in EDD Core.
	 *
	 * @since 3.6
	 *
	 * @param int    $download_id
	 * @param string $email
	 * @param int    $payment_id
	 * @param array  $args
	 *
	 * @return array $args {
	 *     @type bool   $success If the download is available, true for yes, false for no.
	 *     @type string $message (Required for success => false) A message to display during wp_die
	 *     @type string $title (Required for success => false) A title to display in the browser <title> tag during wp_die
	 *     @type int    $response (Required for success => false) The HTTP response code to use for wp_die
	 * }
	 */
	public function license_can_download( $download_id = 0, $email = '', $payment_id, $args ) {
		$can_download = array( 'success' => true );

		$licenses = $this->licenses_db->get_licenses( array(
			'download_id' => $download_id,
			'payment_id'  => $payment_id,
		) );

		if ( count( $licenses ) === 1 ) {
			$license = $licenses[0];
			if( 'expired' == $license->status ) {
				$can_download = array(
					'success'  => false,
					'message'  => __( 'Your license key for this purchase is expired. Renew your license key and you will be allowed to download your files again.', 'edd_sl' ),
					'title'    => __( 'Expired License', 'edd_sl' ),
					'response' => 401,
				);
			} elseif( 'disabled' === $license->status ) {
				$can_download = array(
					'success'  => false,
					'message'  => __( 'Your license key for this purchase has been revoked.', 'edd_sl' ),
					'title'    => __( 'Revoked License', 'edd_sl' ),
					'response' => 401,
				);
			}

		} elseif ( count( $licenses ) > 1 ) {
			$has_access      = false;
			$invalid_statues = apply_filters( 'edd_sl_license_download_invalid_statuses', array( 'expired', 'disabled' ) );
			foreach ( $licenses as $license ) {
				if ( ! in_array( $license->status, $invalid_statues ) ) {
					$has_access = true;
					break;
				}
			}

			if ( false === $has_access ) {
				$can_download = array(
					'success'  => false,
					'message'  => __( 'You do not have a valid license for this download.', 'edd_sl' ),
					'title'    => __( 'No Valid License', 'edd_sl' ),
					'response' => 401,
				);
			}
		}

		return apply_filters( 'edd_sl_license_can_download', $can_download, $args );
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

		} else {

			$user_id   = intval( $_POST['edd-payment-user-id'] );

		}

		$licenses = $this->get_licenses_of_purchase( $payment_id );

		if( $licenses ) {

			foreach( $licenses as $license ) {

				$license->update( array(
					'customer_id' => isset( $customer_id ) ? $customer_id : 0,
					'user_id'     => $user_id
				) );

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

		if ( apply_filters( 'edd_sl_strip_www', true ) ) {

			// strip www subdomain
			$url = str_replace( array( '://www.', ':/www.' ), '://', $url );

		}

		if ( apply_filters( 'edd_sl_strip_protocol', apply_filters( 'edd_sl_strip_protocal', true ) ) ) {
			// strip protocol
			$url = str_replace( array( 'http://', 'https://', 'http:/', 'https:/' ), '', $url );

		}

		if ( apply_filters( 'edd_sl_strip_port_number', true ) ) {

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
	 *
	 * @param  string $url The URL Provided
	 *
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
					'.dev', '.local', '.test',
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
					'dev.', '*.staging.', '*.test.',
				) );

				foreach ( $subdomains_to_check as $subdomain ) {

					$subdomain = str_replace( '.', '(.)', $subdomain );
					$subdomain = str_replace( array( '*', '(.)' ), '(.*)', $subdomain );

					if ( preg_match( '/^(' . $subdomain . ')/', $host ) ) {
						$is_local_url = true;
						continue;
					}
				}
			}
		}

		return apply_filters( 'edd_sl_is_local_url', $is_local_url, $url );
	}

	/**
	 * Update customer email on profile update
	 *
	 * @since 3.5
	 * @deprecated No longer used in 3.6 since license titles are not a property of licenses.
	 * @param bool $updated whether or not the customer was updated
	 * @param int $customer_id The ID of the customer
	 * @param array $data The updated data for the customer
	 * @return void
	 */
	function update_license_email_on_customer_update( $updated = false, $customer_id = 0, $data = array() ) {
		_edd_deprecated_function( 'EDD_Software_Licensing::update_license_email_on_customer_update', '3.6', 'Email address are no longer stored on licenses directly' );
	}

	/**
	 * Get emails for a license
	 *
	 * This is currently only used for matching
	 * on renewals with the Enforced Matching setting enabled.
	 *
	 * @since 3.5
	 * @access public
	 * @param int $license_id The ID to get emails for
	 * @return array $emails The emails for this license
	 */
	function get_emails_for_license( $license_id = 0 ) {
		$payment_id  = $this->get_payment_id( $license_id );
		$payment     = new EDD_Payment( $payment_id );
		$customer_id = $payment->customer_id;
		$customer    = new EDD_Customer( $customer_id );

		$emails   = $customer->emails;
		$emails[] = $customer->email;
		$emails[] = $payment->email;
		$emails   = array_unique( $emails );

		return apply_filters( 'edd_sl_get_emails_for_license', $emails, $license_id );
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

		if( defined( 'EDD_PLUGIN_DIR' ) ) {

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

		require_once EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-roles.php';
		$roles = new EDD_SL_Roles();
		$roles->add_caps();

	}

	add_option( 'edd_sl_version', EDD_SL_VERSION, '', false );

}
register_activation_hook( __FILE__, 'edd_sl_install' );
