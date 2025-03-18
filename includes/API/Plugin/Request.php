<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Base REST API Request class.
 *
 * Provides common functionality for handling and validating REST API requests.
 *
 * @since 3.5.0
 */
abstract class Request {

	/** @var \WP_REST_Request The WordPress REST request object */
	protected $request;

	/** @var array Sanitized request data */
	protected $data = [];

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 *
	 * @param \WP_REST_Request $request The WordPress REST request object.
	 */
	public function __construct( \WP_REST_Request $request ) {
		$this->request = $request;
		$this->parse_request();
	}

	/**
	 * Parse and validate the request data.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	protected function parse_request() {
		// Get JSON data from request body
		$params = $this->request->get_json_params();

		// If no JSON data, try to get from POST
		if ( empty( $params ) ) {
			$params = $this->request->get_params();
		}

		$this->data = $this->sanitize_data( $params );
	}

	/**
	 * Sanitize request data.
	 *
	 * @since 3.5.0
	 *
	 * @param array $data Raw request data.
	 * @return array Sanitized data.
	 */
	protected function sanitize_data( $data ) {
		if ( ! is_array( $data ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $data as $key => $value ) {
			$sanitized[ $key ] = $this->sanitize_value( $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single value.
	 *
	 * @since 3.5.0
	 *
	 * @param mixed $value Value to sanitize.
	 * @return mixed Sanitized value.
	 */
	protected function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			return $this->sanitize_data( $value );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return $value;
	}

	/**
	 * Get a value from the request data.
	 *
	 * @since 3.5.0
	 *
	 * @param string $key     The key to retrieve.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed The value or default if not set.
	 */
	public function get_param( $key, $default = null ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : $default;
	}

	/**
	 * Get all request data.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Validate the request.
	 *
	 * @since 3.5.0
	 *
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	abstract public function validate();
}
