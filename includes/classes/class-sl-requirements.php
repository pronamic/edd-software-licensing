<?php
/**
 * Requirements
 *
 * Used for checking if requirements are met.
 *
 * @package   edd-software-licensing
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.7.2
 */

class EDD_SL_Requirements {

	/**
	 * Requirements
	 *
	 * @var array
	 */
	private $requirements = array();

	/**
	 * EDD_SL_Requirements constructor.
	 *
	 * @param array $requirements
	 */
	public function __construct( $requirements = array() ) {
		foreach ( $requirements as $id => $args ) {
			$this->add_requirement( $id, $args );
		}
	}

	/**
	 * Adds a new requirement.
	 *
	 * @param string $id      Unique ID for the requirement.
	 * @param array  $args    {
	 *                        Array of arguments.
	 *
	 * @type string  $minimum Minimum version required.
	 * @type string  $name    Display name for the requirement.
	 *                     }
	 *
	 * @return void
	 */
	public function add_requirement( $id, $args ) {
		$args = wp_parse_args( $args, array(
			'minimum' => '1',   // Minimum version number
			'name'    => '',    // Display name
			'exists'  => false, // Whether or not this requirement exists.
			'current' => false, // The currently installed version number.
			'checked' => false, // Whether or not the requirement has been checked.
			'met'     => false, // Whether or not all requirements are met.
			'local'   => false, // Whether or not we're checking the local platform.
		) );

		// Auto fetch current version if this is a local (current WP install) requirement and a current version wasn't provided.
		if ( $args['local'] && false === $args['current'] ) {
			$args['current'] = $this->get_local_version( $id );
			$args['exists']  = false !== $args['current'];
		}

		$this->requirements[ sanitize_key( $id ) ] = $args;
	}

	/**
	 * Returns the local (current WordPress install) version of a given requirement.
	 *
	 * @since 3.8
	 *
	 * @param string $id Requirement ID.
	 *
	 * @return string|false Version number if it can be found, false if not.
	 */
	private function get_local_version( $id ) {
		switch ( $id ) {
			case 'php' :
				return phpversion();
			case 'wp' :
				return get_bloginfo( 'version' );
			case 'easy-digital-downloads' :
				return defined( 'EDD_VERSION' ) ? EDD_VERSION : false;
			default :
				return false;
		}
	}

	/**
	 * Whether or not all requirements have been met.
	 *
	 * @return bool
	 */
	public function met() {
		$this->check();

		$requirements_met = true;

		// If any one requirement is not met, we return false.
		foreach ( $this->requirements as $requirement ) {
			if ( empty( $requirement['met'] ) ) {
				$requirements_met = false;
				break;
			}
		}

		return $requirements_met;
	}

	/**
	 * Returns unmet requirements.
	 *
	 * @since 3.8
	 *
	 * @return array
	 */
	public function get_unmet() {
		return array_filter( $this->requirements, function ( $requirement ) {
			return empty( $requirement['met'] );
		} );
	}

	/**
	 * Checks the requirements.
	 *
	 * @return void
	 */
	private function check() {
		foreach ( $this->requirements as $requirement_id => $properties ) {
			if ( ! empty( $properties['current'] ) ) {
				$this->requirements[ $requirement_id ] = array_merge( $this->requirements[ $requirement_id ], array(
					'checked' => true,
					'met'     => version_compare( $properties['current'], $properties['minimum'], '>=' )
				) );
			}
		}
	}

	/**
	 * Returns requirements errors.
	 *
	 * @return WP_Error
	 */
	public function get_errors() {
		$error = new WP_Error();

		foreach ( $this->requirements as $requirement_id => $properties ) {
			if ( empty( $properties['met'] ) ) {
				$error->add( $requirement_id, $this->unmet_requirement_description( $properties ) );
			}
		}

		return $error;
	}

	/**
	 * Generates an HTML error description.
	 *
	 * @param array $requirement
	 *
	 * @return string
	 */
	private function unmet_requirement_description( $requirement ) {
		// Requirement exists, but is out of date.
		if ( ! empty( $requirement['exists'] ) ) {
			return sprintf(
				$this->unmet_requirements_description_text(),
				'<strong>' . esc_html( $requirement['name'] ) . '</strong>',
				'<strong>' . esc_html( $requirement['minimum'] ) . '</strong>',
				'<strong>' . esc_html( $requirement['current'] ) . '</strong>'
			);
		}

		// Requirement could not be found.
		return sprintf(
			$this->unmet_requirements_missing_text(),
			esc_html( $requirement['name'] ),
			'<strong>' . esc_html( $requirement['minimum'] ) . '</strong>'
		);
	}

	/**
	 * Plugin specific text to describe a single unmet requirement.
	 *
	 * @return string
	 */
	private function unmet_requirements_description_text() {
		/* Translators: %1$s name of the requirement; %2$s required version; %3$s current version */
		return esc_html__( '%1$s: minimum required %2$s (you have %3$s)', 'edd_sl' );
	}

	/**
	 * Plugin specific text to describe a single missing requirement.
	 *
	 * @return string
	 */
	private function unmet_requirements_missing_text() {
		/* Translators: %1$s name of the requirement; %2$s required version */
		return wp_kses( __( '<strong>Missing %1$s</strong>: minimum required %2$s', 'edd_sl' ), array( 'strong' => array() ) );
	}

}
