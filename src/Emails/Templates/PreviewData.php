<?php

namespace EDD\SoftwareLicensing\Emails\Templates;

defined( 'ABSPATH' ) || exit;

class PreviewData {

	/**
	 * The notice data.
	 *
	 * @since 3.8.12
	 * @var array|bool
	 */
	private static $notice_data;

	/**
	 * Get the notice data.
	 *
	 * @since 3.8.12
	 * @return array|bool
	 */
	public static function get_notice_data() {
		if ( ! is_null( self::$notice_data ) ) {
			return self::$notice_data;
		}

		$licenses = edd_software_licensing()->licenses_db->get_licenses(
			array(
				'number' => 10,
				'status' => array( 'active', 'inactive' ),
			)
		);
		if ( empty( $licenses ) ) {
			self::$notice_data = false;
		} else {

			$key     = array_rand( $licenses );
			$license = $licenses[ $key ];

			self::$notice_data = array(
				$license->ID,
				$license,
			);
		}

		return self::$notice_data;
	}
}
