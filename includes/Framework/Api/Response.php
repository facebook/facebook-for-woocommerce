<?php
/**
 * Meta for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework\Api;

defined( 'ABSPATH' ) || exit;

/**
 * API Response
 */
interface Response {


	/**
	 * Returns the string representation of this request
	 *
	 * @since 2.2.0
	 * @return string the request
	 */
	public function to_string();


	/**
	 * Returns the string representation of this request with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 2.2.0
	 * @return string the request, safe for logging/displaying
	 */
	public function to_string_safe();
}
