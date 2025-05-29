<?php
/**
 * Facebook for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework;

defined( 'ABSPATH' ) || exit;

/**
 * Facebook Helper Class
 * The purpose of this class is to centralize common utility functions.
 */
class Helper {

	/** Encoding used for mb_*() string functions */
	const MB_ENCODING = 'UTF-8';

	/** String manipulation functions (all multi-byte safe) ***************/

	/**
	 * Returns true if the haystack string starts with needle
	 *
	 * Note: case-sensitive
	 *
	 * @since 2.2.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_starts_with( $haystack, $needle ) {
		if ( self::multibyte_loaded() ) {
			if ( '' === $needle ) {
				return true;
			}
			return 0 === mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );
		} else {
			$needle = self::str_to_ascii( $needle );
			if ( '' === $needle ) {
				return true;
			}
			return 0 === strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
		}
	}


	/**
	 * Return true if the haystack string ends with needle
	 *
	 * Note: case-sensitive
	 *
	 * @since 2.2.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_ends_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}
		if ( self::multibyte_loaded() ) {
			return mb_substr( $haystack, -mb_strlen( $needle, self::MB_ENCODING ), null, self::MB_ENCODING ) === $needle;
		} else {
			$haystack = self::str_to_ascii( $haystack );
			$needle   = self::str_to_ascii( $needle );
			return substr( $haystack, -strlen( $needle ) ) === $needle;
		}
	}


	/**
	 * Returns true if the needle exists in haystack
	 *
	 * Note: case-sensitive
	 *
	 * @since 2.2.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_exists( $haystack, $needle ) {
		if ( self::multibyte_loaded() ) {
			if ( '' === $needle ) {
				return false;
			}
			return false !== mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );
		} else {
			$needle = self::str_to_ascii( $needle );
			if ( '' === $needle ) {
				return false;
			}
			return false !== strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
		}
	}


	/**
	 * Truncates a given $string after a given $length if string is longer than
	 * $length. The last characters will be replaced with the $omission string
	 * for a total length not exceeding $length
	 *
	 * @since 2.2.0
	 * @param string $input_string Text to truncate.
	 * @param int    $length       Total desired length of string, including omission.
	 * @param string $omission     Omission text, defaults to '...'.
	 * @return string
	 */
	public static function str_truncate( $input_string, $length, $omission = '...' ) {
		// Ensure input is a string
		if ( ! is_string( $input_string ) ) {
			if ( is_array( $input_string ) ) {
				$input_string = isset( $input_string[0] ) ? $input_string[0] : '';
			} else {
				$input_string = (string) $input_string;
			}
		}

		if ( self::multibyte_loaded() ) {
			// bail if string doesn't need to be truncated
			if ( mb_strlen( $input_string, self::MB_ENCODING ) <= $length ) {
				return $input_string;
			}
			$length -= mb_strlen( $omission, self::MB_ENCODING );
			return mb_substr( $input_string, 0, $length, self::MB_ENCODING ) . $omission;
		} else {
			$input_string = self::str_to_ascii( $input_string );
			// bail if string doesn't need to be truncated
			if ( strlen( $input_string ) <= $length ) {
				return $input_string;
			}
			$length -= strlen( $omission );
			return substr( $input_string, 0, $length ) . $omission;
		}
	}


	/**
	 * Returns a string with all non-ASCII characters removed. This is useful
	 * for any string functions that expect only ASCII chars and can't
	 * safely handle UTF-8. Note this only allows ASCII chars in the range
	 * 33-126 (newlines/carriage returns are stripped)
	 *
	 * @since 2.2.0
	 * @param string $target_string string to make ASCII
	 * @return string
	 */
	public static function str_to_ascii( $target_string ) {
		// Strip ASCII chars 32 and under
		$target_string = preg_replace( '/[\x00-\x1F]/', '', $target_string );
		// Strip ASCII chars 127 and higher
		return preg_replace( '/[\x7F-\xFF]/', '', $target_string );
	}


	/**
	 * Helper method to check if the multibyte extension is loaded, which
	 * indicates it's safe to use the mb_*() string methods
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	protected static function multibyte_loaded() {
		return extension_loaded( 'mbstring' );
	}


	/** Array functions ***************************************************/


	/**
	 * Insert the given element after the given key in the array
	 *
	 * Sample usage:
	 *
	 * given
	 *
	 * array( 'item_1' => 'foo', 'item_2' => 'bar' )
	 *
	 * array_insert_after( $array, 'item_1', array( 'item_1.5' => 'w00t' ) )
	 *
	 * becomes
	 *
	 * array( 'item_1' => 'foo', 'item_1.5' => 'w00t', 'item_2' => 'bar' )
	 *
	 * @since 2.2.0
	 * @param array  $input_array Array to insert the given element into.
	 * @param string $insert_key  Key to insert given element after.
	 * @param array  $element     Element to insert into array.
	 * @return array
	 */
	public static function array_insert_after( array $input_array, $insert_key, array $element ) {
		$new_array = [];
		foreach ( $input_array as $key => $value ) {
			$new_array[ $key ] = $value;
			if ( $insert_key === $key ) {
				foreach ( $element as $k => $v ) {
					$new_array[ $k ] = $v;
				}
			}
		}
		return $new_array;
	}


	/** Number helper functions *******************************************/


	/**
	 * Format a number with 2 decimal points, using a period for the decimal
	 * separator and no thousands separator.
	 *
	 * Commonly used for payment gateways which require amounts in this format.
	 *
	 * @since 3.0.0
	 * @param float $number
	 * @return string
	 */
	public static function number_format( $number ) {
		return number_format( (float) $number, 2, '.', '' );
	}


	/** WooCommerce helper functions **************************************/


	/**
	 * Safely gets a value from $_POST.
	 *
	 * If the expected data is a string also trims it.
	 *
	 * @since 5.5.0
	 *
	 * @param string                           $key           Posted data key.
	 * @param int|float|array|bool|null|string $default_value Default data type to return (default empty string).
	 * @return int|float|array|bool|null|string Posted data value if key found, or default.
	 */
	public static function get_posted_value( $key, $default_value = '' ) {

		$value = $default_value;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sanitized_value = wc_clean( wp_unslash( $_POST[ $key ] ) );
			$value           = is_string( $sanitized_value ) ? trim( $sanitized_value ) : $sanitized_value;
		}

		return $value;
	}


	/**
	 * Safely gets a value from $_REQUEST.
	 *
	 * If the expected data is a string also trims it.
	 *
	 * @since 5.5.0
	 *
	 * @param string                           $key           Posted data key.
	 * @param int|float|array|bool|null|string $default_value Default data type to return (default empty string).
	 * @return int|float|array|bool|null|string Posted data value if key found, or default.
	 */
	public static function get_requested_value( $key, $default_value = '' ) {

		$value = $default_value;
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST[ $key ] ) ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sanitized_value = wc_clean( wp_unslash( $_REQUEST[ $key ] ) );
			$value           = is_string( $sanitized_value ) ? trim( $sanitized_value ) : $sanitized_value;
		}

		return $value;
	}


	/**
	 * Get the count of notices added, either for all notices (default) or for one
	 * particular notice type specified by $notice_type.
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 3.0.2
	 * @param string $notice_type The name of the notice type - either error, success or notice. [optional]
	 * @return int
	 */
	public static function wc_notice_count( $notice_type = '' ) {

		if ( function_exists( 'wc_notice_count' ) ) {
			return wc_notice_count( $notice_type );
		}

		return 0;
	}


	/**
	 * Add and store a notice.
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 3.0.2
	 * @param string $message The text to display in the notice.
	 * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
	 */
	public static function wc_add_notice( $message, $notice_type = 'success' ) {

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $notice_type );
		}
	}


	/**
	 * Print a single notice immediately
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 3.0.2
	 * @param string $message The text to display in the notice.
	 * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
	 */
	public static function wc_print_notice( $message, $notice_type = 'success' ) {

		if ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $message, $notice_type );
		}
	}


	/**
	 * Gets the current WordPress site name.
	 *
	 * This is helpful for retrieving the actual site name instead of the
	 * network name on multisite installations.
	 *
	 * @since 4.6.0
	 * @return string
	 */
	public static function get_site_name() {

		return ( is_multisite() ) ? get_blog_details()->blogname : get_bloginfo( 'name' );
	}


	/** Misc functions ****************************************************/


	/**
	 * Gets the WordPress current screen.
	 *
	 * @see get_current_screen() replacement which is always available, unlike the WordPress core function
	 *
	 * @since 5.4.2
	 *
	 * @return \WP_Screen|null
	 */
	public static function get_current_screen() {
		global $current_screen;

		return ! empty( $current_screen ) ? $current_screen : null;
	}


	/**
	 * Checks if the current screen matches a specified ID.
	 *
	 * This helps avoiding using the get_current_screen() function which is not always available,
	 * or setting the substitute global $current_screen every time a check needs to be performed.
	 *
	 * @since 5.4.2
	 *
	 * @param string $id id (or property) to compare
	 * @param string $prop optional property to compare, defaults to screen id
	 * @return bool
	 */
	public static function is_current_screen( $id, $prop = 'id' ) {
		global $current_screen;

		return isset( $current_screen->$prop ) && $id === $current_screen->$prop;
	}


	/**
	 * Determines if the current request is for a WC REST API endpoint.
	 *
	 * @see \WooCommerce::is_rest_api_request()
	 *
	 * @since 5.9.0
	 *
	 * @return bool
	 */
	public static function is_rest_api_request() {

		if ( is_callable( 'WC' ) && is_callable( [ WC(), 'is_rest_api_request' ] ) ) {
			return (bool) WC()->is_rest_api_request();
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) || ! function_exists( 'rest_get_url_prefix' ) ) {
			return false;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = false !== strpos( wc_clean( wp_unslash( $_SERVER['REQUEST_URI'] ) ), $rest_prefix );

		/** Applies WooCommerce core filter */
		return (bool) apply_filters( 'woocommerce_is_rest_api_request', $is_rest_api_request );
	}
}
