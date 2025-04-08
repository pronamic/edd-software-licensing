<?php
/**
 * Polyfills for PHP functions which may not be available in older versions.
 *
 * These are loaded via the composer.json file.
 *
 * @since 3.8.12
 *
 * @package     EDD\SoftwareLicensing\Globals\Polyfills
 */

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Check if substring is contained in string
	 *
	 * @since 3.8.12
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool
	 */
	function str_contains( string $haystack, string $needle ): bool {
		return ( strpos( $haystack, $needle ) !== false );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Check if string ends with a specific substring
	 *
	 * @since 3.8.12
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool
	 */
	function str_starts_with( string $haystack, string $needle ): bool {
		return strlen( $needle ) === 0 || strpos( $haystack, $needle ) === 0;
	}
}
