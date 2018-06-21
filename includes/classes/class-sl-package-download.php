<?php
/**
 * The class to process downloading a package URL from the tokenized URLs
 *
 * @since  3.2.4
 */
class EDD_SL_Package_Download {


	/**
	 * Initialize the request
	 */
	public function __construct() {
		add_action( 'edd_package_download', array( $this, 'process_package_download' ) );
	}

	/**
	 * Process the request for a package download
	 *
	 * @since  3.2.4
	 * @return  void
	 */
	public function process_request() {

		$data = $this->parse_url();

		if( ! empty( $data ) && is_array( $data ) ) {

			foreach ( $data as $key => $arg ) {
				$_GET[ $key ] = $arg;
			}

			do_action( 'edd_package_download' );

			// We're firing a download URL, just get out
			wp_die();

		}
	}

	/**
	 * Parse the URL for the package downloader
	 *
	 * @since  3.2.4
	 * @return array Array of parsed url information
	 */
	private function parse_url() {

		if( false === stristr( $_SERVER['REQUEST_URI'], 'edd-sl/package_download' ) ) {
			return false; // Not a package download request
		}

		$data      = array();
		$url_parts = parse_url( $_SERVER['REQUEST_URI'] );
		$paths     = array_values( explode( '/', $url_parts['path'] ) );

		$token  = end( $paths );
		$values = explode( ':', base64_decode( $token ) );

		if ( count( $values ) !== 6 ) {
			wp_die( __( 'Invalid token supplied', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
		}

		$expires        = $values[0];
		$license_key    = $values[1];
		$download_id    = (int) $values[2];
		$url            = str_replace( '@', ':', $values[4] );
		$download_beta  = (bool) $values[5];


		if (  ! edd_software_licensing()->is_download_id_valid_for_license( $download_id, $license_key ) ) {
			wp_die( __( 'Invalid license supplied', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
		}

		$license_check_args = array(
			'url'        => $url,
			'key'        => $license_key,
			'item_id'    => $download_id,
		);
		$license_status = edd_software_licensing()->check_license( $license_check_args );
		switch( $license_status ) {
			case 'valid':
				break;
			case 'expired':
				$renewal_link = add_query_arg( 'edd_license_key', $license_key, edd_get_checkout_uri() );
				wp_die( sprintf( __( 'Your license has expired, please <a href="%s" title="Renew your license">renew it</a> to install this update.', 'edd_sl' ), $renewal_link ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
				break;
			case 'inactive':
			case 'site_inactive':
				wp_die( __( 'Your license has not been activated for this domain, please activate it first.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
				break;
			case 'disabled':
				wp_die( __( 'Your license has been disabled.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
				break;
			default:
				wp_die( __( 'Your license could not be validated.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
				break;
		}

		$download = new EDD_SL_Download( $download_id );

		$download_name  = $download->get_name();
		if ( $download_beta ) {
			$file_key      = $download->get_beta_upgrade_file_key();
		} else {
			$file_key      = $download->get_upgrade_file_key();
		}

		$computed_hash = md5( $download_name . $file_key . $download_id . $license_key . (int) $expires );
		$computed_hash = apply_filters( 'edd_sl_package_download_computed_hash', $computed_hash, $download_name, $file_key, $download_id, $license_key, (int) $expires );
		if ( ! hash_equals( $computed_hash, $values[3] ) ) {
			wp_die( __( 'Provided hash does not validate.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
		}

		$data = array(
			'expires'       => $expires,
			'license'       => $license_key,
			'id'            => $download_id,
			'key'           => $computed_hash,
			'beta'          => $download_beta
		);

		return $data;

	}

	public function get_encoded_download_package_url( $download_id = 0, $license_key = '', $url = '', $download_beta = false ) {

		$package_url = '';

		if( ! empty( $license_key ) ) {

			$download = new EDD_SL_Download( $download_id );

			$download_name = $download->get_name();
			$hours         = '+' . absint( edd_get_option( 'download_link_expiration', 24 ) ) . ' hours';
			$expires       = strtotime( $hours, current_time( 'timestamp' ) );

			if ( $download_beta ) {
				$file_key      = $download->get_beta_upgrade_file_key();
			} else {
				$file_key      = $download->get_upgrade_file_key();
			}

			$hash          = md5( $download_name . $file_key . $download_id . $license_key . (int) $expires );
			$url           = str_replace( ':', '@', $url );

			$token = base64_encode( sprintf( '%s:%s:%d:%s:%s:%d', $expires, $license_key, $download_id, $hash, $url, $download_beta ) );

			$package_url = trailingslashit( home_url() ) . 'edd-sl/package_download/' . $token;

		}

		return apply_filters( 'edd_sl_encoded_package_url', $package_url );

	}

	/**
	 * Deliver the file download
	 *
	 * @since  3.2.4
	 * @return void
	 */
	public function process_package_download() {

		if ( isset( $_GET['key'] ) && isset( $_GET['id'] ) && isset( $_GET['license'] ) && isset( $_GET['expires'] ) ) {

			$id            = absint( urldecode( $_GET['id'] ) );
			$hash          = urldecode( $_GET['key'] );
			$license       = sanitize_text_field( urldecode( $_GET['license'] ) );
			$expires       = is_numeric( $_GET['expires'] ) ? $_GET['expires'] : urldecode( base64_decode( $_GET['expires'] ) );
			$download_beta = (bool) $_GET['beta'];

			do_action( 'edd_sl_before_package_download', $id, $hash, $license, $expires );

			if ( current_time( 'timestamp' ) > $expires ) {
				wp_die( __( 'Your download link has expired', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
			}

			if ( empty( $license ) ) {
				wp_die( __( 'No license key provided', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
			}

			if (  ! edd_software_licensing()->is_download_id_valid_for_license( $id, $license ) ) {
				wp_die( __( 'Invalid license supplied', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
			}

			$requested_file = $this->get_download_package( $id, $license, $hash, $expires, $download_beta );

			$file_extension = edd_get_file_extension( $requested_file );
			$ctype          = edd_get_file_ctype( $file_extension );

			if ( !edd_is_func_disabled( 'set_time_limit' ) ) {
				set_time_limit(0);
			}

			@session_write_close();
			if( function_exists( 'apache_setenv' ) ) @apache_setenv('no-gzip', 1);
			@ini_set( 'zlib.output_compression', 'Off' );

			nocache_headers();
			header("Robots: none");
			header("Content-Type: " . $ctype . "");
			header("Content-Description: File Transfer");
			header("Content-Disposition: attachment; filename=\"" . apply_filters( 'edd_requested_file_name', basename( $requested_file ) ) . "\";");
			header("Content-Transfer-Encoding: binary");

			$method = edd_get_file_download_method();
			if( 'x_sendfile' == $method && ( ! function_exists( 'apache_get_modules' ) || ! in_array( 'mod_xsendfile', apache_get_modules() ) ) ) {
				// If X-Sendfile is selected but is not supported, fallback to Direct
				$method = 'direct';
			}

			$file_details = parse_url( $requested_file );
			$schemes      = array( 'http', 'https' ); // Direct URL schemes

			if ( ( ! isset( $file_details['scheme'] ) || ! in_array( $file_details['scheme'], $schemes ) ) && isset( $file_details['path'] ) && file_exists( $requested_file ) ) {

				/**
				 * Download method is set to to Redirect in settings but an absolute path was provided
				 * We need to switch to a direct download in order for the file to download properly
				 */
				$method = 'direct';

			}

			switch( $method ) :

				case 'redirect' :

					// Redirect straight to the file
					header( "Location: " . $requested_file );
					break;

				case 'direct' :
				default:

					$direct    = false;
					$file_path = $requested_file;

					if ( ( ! isset( $file_details['scheme'] ) || ! in_array( $file_details['scheme'], $schemes ) ) && isset( $file_details['path'] ) && file_exists( $requested_file ) ) {

						/** This is an absolute path */
						$direct    = true;
						$file_path = $requested_file;

					} else if( defined( 'UPLOADS' ) && strpos( $requested_file, UPLOADS ) !== false ) {

						/**
						 * This is a local file given by URL so we need to figure out the path
						 * UPLOADS is always relative to ABSPATH
						 * site_url() is the URL to where WordPress is installed
						 */
						$file_path  = str_replace( site_url(), '', $requested_file );
						$file_path  = realpath( ABSPATH . $file_path );
						$direct     = true;

					} else if( strpos( $requested_file, content_url() ) !== false ) {

						/** This is a local file given by URL so we need to figure out the path */
						$file_path  = str_replace( content_url(), WP_CONTENT_DIR, $requested_file );
						$file_path  = realpath( $file_path );
						$direct     = true;

					} else if( strpos( $requested_file, set_url_scheme( content_url(), 'https' ) ) !== false ) {

						/** This is a local file given by an HTTPS URL so we need to figure out the path */
						$file_path  = str_replace( set_url_scheme( content_url(), 'https' ), WP_CONTENT_DIR, $requested_file );
						$file_path  = realpath( $file_path );
						$direct     = true;

					}

					// Set the file size header
					header( "Content-Length: " . @filesize( $file_path ) );

					// Now deliver the file based on the kind of software the server is running / has enabled
					if ( stristr( getenv( 'SERVER_SOFTWARE' ), 'lighttpd' ) ) {

						header( "X-LIGHTTPD-send-file: $file_path" );

					} elseif ( $direct && ( stristr( getenv( 'SERVER_SOFTWARE' ), 'nginx' ) || stristr( getenv( 'SERVER_SOFTWARE' ), 'cherokee' ) ) ) {

						$ignore_x_accel_redirect_header = apply_filters( 'edd_ignore_x_accel_redirect', false );

						if ( ! $ignore_x_accel_redirect_header ) {
							// We need a path relative to the domain
							$redirect_path = '/' . str_ireplace( realpath( $_SERVER['DOCUMENT_ROOT'] ), '', $file_path );
							$redirect_path = apply_filters( 'edd_sl_accel_redirect_path', $redirect_path, $file_path );
							header( "X-Accel-Redirect: $redirect_path" );
						}

					}

					if( $direct ) {

						edd_deliver_download( $file_path );

					} else {

						// The file supplied does not have a discoverable absolute path
						edd_deliver_download( $requested_file, true );

					}

					break;

			endswitch;

			edd_die();


		} else {
			wp_die( __( 'You do not have permission to download this file', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
		}

		exit;

	}

	/**
	 * Deliver the package download URL
	 *
	 * @since  3.2.4
	 * @param  int $download_id The Download ID to get the package for
	 * @param  string  $license_key The license key
	 * @param  string  $hash        The hash to verify access
	 * @param  int $expires         The TTL for this link
	 * @return string               The URL for the download package
	 */
	public function get_download_package( $download_id = 0, $license_key = '', $hash, $expires = 0, $download_beta = false ) {

		$download = new EDD_SL_Download( $download_id );

		if ( $download_beta ) {
			$file_key  = $download->get_beta_upgrade_file_key();
			$all_files = get_post_meta( $download_id, '_edd_sl_beta_files', true );
		} else {
			$file_key  = $download->get_upgrade_file_key();
			$all_files = get_post_meta( $download_id, 'edd_download_files', true );
		}

		if ( $all_files && is_array( $all_files ) ) {
			$file_url = $all_files[ $file_key ]['file'];
		} else {
			$file_url = '';
		}

		$download_name = $download->get_name();

		$computed_hash = md5( $download_name . $file_key . $download_id . $license_key . $expires );
		$computed_hash = apply_filters( 'edd_sl_package_download_computed_hash', $computed_hash, $download_name, $file_key, $download_id, $license_key, (int) $expires );

		if ( ! empty( $hash ) && ! hash_equals( $computed_hash, $hash ) ) {
			wp_die( __( 'You do not have permission to download this file. An invalid hash was provided.', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
		}

		// This filter ensures compatibility with the Amazon S3 extension
		$file_url = apply_filters( 'edd_requested_file', $file_url, $all_files, $file_key );

		return apply_filters( 'edd_sl_download_package_url', $file_url, $download_id, $license_key );

	}

}
