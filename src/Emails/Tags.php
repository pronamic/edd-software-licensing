<?php

namespace EDD\SoftwareLicensing\Emails;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Tags {

	/**
	 * Tags constructor.
	 */
	public function __construct() {
		add_action( 'edd_add_email_tags', array( $this, 'register' ), 100 );
	}

	/**
	 * Register the email tags.
	 *
	 * @since 3.8.12
	 */
	public function register() {
		foreach ( $this->get() as $tag ) {
			if ( ! isset( $tag['contexts'] ) ) {
				$tag['contexts'] = array( 'license' );
			}
			edd_add_email_tag( $tag['tag'], $tag['description'], $tag['callback'], $tag['label'], $tag['contexts'] );
		}
	}

	/**
	 * Get the email tags.
	 *
	 * @since 3.8.12
	 * @return array
	 */
	private function get() {
		$tags = array(
			array(
				'tag'         => 'license_keys',
				'description' => __( 'Show all purchased licenses.', 'edd_sl' ),
				'callback'    => array( $this, 'licenses_tag' ),
				'label'       => __( 'License Keys', 'edd_sl' ),
				'contexts'    => array( 'order' ),
			),
		);

		if ( ! edd_sl_are_email_templates_registered() ) {
			return $tags;
		}

		$license_tags = array(
			array(
				'tag'         => 'name',
				'description' => __( 'The first name of the customer.', 'edd_sl' ),
				'callback'    => array( $this, 'name_tag' ),
				'label'       => __( 'Customer First Name', 'edd_sl' ),
			),
			array(
				'tag'         => 'fullname',
				'description' => __( 'The full name of the customer.', 'edd_sl' ),
				'callback'    => array( $this, 'full_name_tag' ),
				'label'       => __( 'Customer Name', 'edd_sl' ),
			),
			array(
				'tag'         => 'license_key',
				'description' => __( 'Show the license key for a specific download.', 'edd_sl' ),
				'callback'    => function ( $license_id, $license = null ) {
					if ( ! $license ) {
						$license = edd_software_licensing()->get_license( $license_id );
					}

					return $license ? $license->key : '';
				},
				'label'       => __( 'License Key', 'edd_sl' ),
			),
			array(
				'tag'         => 'product_name',
				'description' => __( 'The name of the product the license key belongs to.', 'edd_sl' ),
				'callback'    => array( $this, 'product_name_tag' ),
				'label'       => __( 'Licensed Product Name', 'edd_sl' ),
			),
			array(
				'tag'         => 'expiration',
				'description' => __( 'The expiration date for the license key.', 'edd_sl' ),
				'callback'    => array( $this, 'expiration_tag' ),
				'label'       => __( 'License Expiration', 'edd_sl' ),
			),
			array(
				'tag'         => 'expiration_time',
				'description' => __( 'The expiration for the license key, displayed as a time difference.', 'edd_sl' ),
				'callback'    => array( $this, 'expiration_time_tag' ),
				'label'       => __( 'License Expiration (Relative)', 'edd_sl' ),
			),
			array(
				'tag'         => 'renewal_link',
				'description' => __( 'The link to add this licensed product to the cart (HTML).', 'edd_sl' ),
				'callback'    => array( $this, 'renewal_link_tag' ),
				'label'       => __( 'Renewal Link', 'edd_sl' ),
			),
			array(
				'tag'         => 'renewal_url',
				'description' => __( 'The URL to add this licensed product to the cart.', 'edd_sl' ),
				'callback'    => array( $this, 'renewal_url_tag' ),
				'label'       => __( 'Renewal URL', 'edd_sl' ),
			),
			array(
				'tag'         => 'unsubscribe_url',
				'description' => __( 'Raw URL to unsubscribe from email notifications for the license.', 'edd_sl' ),
				'callback'    => array( $this, 'unsubscribe_url_tag' ),
				'label'       => __( 'Unsubscribe URL', 'edd_sl' ),
			),
		);

		if ( edd_get_option( 'edd_sl_renewal_discount', false ) ) {
			$license_tags[] = array(
				'tag'         => 'renewal_discount',
				'description' => __( 'The renewal discount, including the `%` symbol.', 'edd_sl' ),
				'callback'    => array( $this, 'discount_tag' ),
				'label'       => __( 'Renewal Discount', 'edd_sl' ),
			);
		}

		return array_merge( $tags, $license_tags );
	}

	/**
	 * Get the license keys for a purchase.
	 *
	 * @since unknown
	 * @param int $order_id The order ID.
	 * @return string
	 */
	public function licenses_tag( $order_id = 0 ) {

		$keys_output  = '';
		$license_keys = edd_software_licensing()->get_licenses_of_purchase( $order_id );

		if ( ! $license_keys ) {
			return $keys_output;
		}
		foreach ( $license_keys as $license ) {
			$price_name = '';
			if ( function_exists( 'edd_get_download_name' ) ) {
				$price_name = edd_get_download_name( $license->download_id, $license->price_id );
			} else {
				$price_name = $license->get_download()->get_name();
				if ( $license->price_id ) {
					$price_name .= ' - ' . edd_get_price_option_name( $license->download_id, $license->price_id );
				}
			}
			$keys_output .= $price_name . ': ' . $license->key . "\n\r";
		}

		return $keys_output;
	}

	/**
	 * Get the first name of the customer who purchased the license.
	 *
	 * @since  3.8.12
	 *
	 * @param  int $license_id License ID
	 * @param  EDD_SL_License $license EDD_SL_License object
	 * @param  string $context The context of the email
	 *
	 * @return string
	 */
	public function name_tag( $license_id, $license = null, $context = 'license' ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}

		return $license ? edd_email_tag_first_name( $license->payment_id ) : '';
	}

	/**
	 * Get the full name of the customer who purchased the license.
	 *
	 * @since  3.8.12
	 *
	 * @param  int $license_id License ID
	 * @param  EDD_SL_License $license EDD_SL_License object
	 * @param  string $context The context of the email
	 *
	 * @return string
	 */
	public function full_name_tag( $license_id, $license = null, $context = 'license' ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}

		return $license ? edd_email_tag_fullname( $license->payment_id ) : '';
	}

	/**
	 * Get the product name of the license.
	 *
	 * @since  3.8.12
	 *
	 * @param  int $license_id License ID
	 * @param  EDD_SL_License $license EDD_SL_License object
	 * @param  string $context The context of the email
	 *
	 * @return string
	 */
	public function product_name_tag( $license_id, $license = null, $context = 'order' ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}

		return $license ? $license->get_download()->get_name() : '';
	}

	/**
	 * Get the expiration date of the license.
	 *
	 * @since  3.8.12
	 * @param  int            $license_id License ID.
	 * @param  EDD_SL_License $license    EDD_SL_License object.
	 *
	 * @return string
	 */
	public function expiration_tag( $license_id, $license = null ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}

		return $license ? date_i18n( get_option( 'date_format' ), $license->expiration ) : '';
	}

	/**
	 * Get the expiration date of the license as a human time difference string
	 *
	 * @since  3.8.12
	 * @param  int            $license_id License ID.
	 * @param  EDD_SL_License $license    EDD_SL_License object.
	 *
	 * @return string
	 */
	public function expiration_time_tag( $license_id, $license = null ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}

		if ( ! $license ) {
			return '';
		}

		$current_time = current_time( 'timestamp' );
		$time_diff    = human_time_diff( $license->expiration, $current_time );

		if ( $license->expiration < $current_time ) {
			/* translators: how long ago the license expired. */
			return sprintf( __( 'expired %s ago', 'edd_sl' ), $time_diff );
		}

		/* translators: how long until the license expires. */
		return sprintf( __( 'expires in %s', 'edd_sl' ), $time_diff );
	}

	/**
	 * Get the HTML renewal link for the license.
	 *
	 * @since  3.8.12
	 * @param  int            $license_id License ID.
	 * @param  EDD_SL_License $license    EDD_SL_License object.
	 *
	 * @return string
	 */
	public function renewal_link_tag( $license_id, $license = null ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}
		if ( ! $license ) {
			return '';
		}

		$renewal_link = apply_filters( 'edd_sl_renewal_link', $license->get_renewal_url() );

		return sprintf( '<a href="%s">%s</a>', $renewal_link, $renewal_link );
	}

	/**
	 * Get the HTML renewal link for the license.
	 *
	 * @since  3.8.12
	 * @param  int            $license_id License ID.
	 * @param  EDD_SL_License $license    EDD_SL_License object.
	 *
	 * @return string
	 */
	public function renewal_url_tag( $license_id, $license = null ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}
		if ( ! $license ) {
			return '';
		}

		return apply_filters( 'edd_sl_renewal_link', $license->get_renewal_url() );
	}

	/**
	 * Get the unsubscribe link for the license.
	 *
	 * @since  3.8.12
	 * @param  int            $license_id License ID.
	 * @param  EDD_SL_License $license    EDD_SL_License object.
	 *
	 * @return string
	 */
	public function unsubscribe_url_tag( $license_id, $license = null ) {
		if ( ! $license ) {
			$license = edd_software_licensing()->get_license( $license_id );
		}
		if ( ! $license ) {
			return '';
		}

		return $license->get_unsubscribe_url();
	}

	/**
	 * Get the renewal discount for the license.
	 *
	 * @since  3.8.12
	 * @param  int            $license_id License ID.
	 *
	 * @return string
	 */
	public function discount_tag( $license_id ) {
		$discount = edd_sl_get_renewal_discount_percentage( $license_id );

		return $discount ? $discount . '%' : '';
	}
}
