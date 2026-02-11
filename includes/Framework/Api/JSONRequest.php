<?php
/**
 * Meta for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Base JSON API request class.
 */
abstract class JSONRequest implements Request {

	/** @var string The request method, one of HEAD, GET, PUT, PATCH, POST, DELETE */
	protected $method;

	/** @var string The request path */
	protected $path;

	/** @var array The request parameters, if any */
	protected $params = [];

	/** @var array the request data */
	protected $data = [];

	/**
	 * Get the request method.
	 *
	 * @since 4.3.0
	 * @see Request::get_method()
	 * @return string
	 */
	public function get_method() {
		return $this->method;
	}

	/**
	 * Get the request path.
	 *
	 * @since 4.3.0
	 * @see Request::get_path()
	 * @return string
	 */
	public function get_path() {
		return $this->path;
	}

	/**
	 * Get the request parameters.
	 *
	 * @since 4.3.0
	 * @see Request::get_params()
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Get the request data.
	 *
	 * @since 4.5.0
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Get the string representation of this request.
	 *
	 * @since 4.3.0
	 * @see Request::to_string()
	 * @return string
	 */
	public function to_string() {
		$data = $this->get_data();
		return ! empty( $data ) ? wp_json_encode( $data ) : '';
	}

	/**
	 * Get the string representation of this request with any and all sensitive elements masked
	 * or removed.
	 *
	 * @since 4.3.0
	 * @see Request::to_string_safe()
	 * @return string
	 */
	public function to_string_safe() {
		return $this->to_string();
	}
}
