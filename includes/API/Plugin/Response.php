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
 * Base REST API Response class.
 *
 * Provides common functionality for handling REST API responses.
 *
 * @since 3.5.0
 */
class Response {

	/** @var bool Whether the request was successful */
	protected $success = true;

	/** @var string Error message if request failed */
	protected $message = '';

	/** @var mixed Response data */
	protected $data;

	/** @var int HTTP status code */
	protected $status_code = 200;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 *
	 * @param mixed $data Response data.
	 */
	public function __construct( $data = null ) {
		$this->data = $data;
	}

	/**
	 * Set the response as an error.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Error message.
	 * @param int    $status_code HTTP status code.
	 * @return self
	 */
	public function set_error( $message, $status_code = 400 ) {
		$this->success     = false;
		$this->message     = $message;
		$this->status_code = $status_code;

		return $this;
	}

	/**
	 * Set the response data.
	 *
	 * @since 3.5.0
	 *
	 * @param mixed $data Response data.
	 * @return self
	 */
	public function set_data( $data ) {
		$this->data = $data;

		return $this;
	}

	/**
	 * Set the HTTP status code.
	 *
	 * @since 3.5.0
	 *
	 * @param int $status_code HTTP status code.
	 * @return self
	 */
	public function set_status_code( $status_code ) {
		$this->status_code = $status_code;

		return $this;
	}

	/**
	 * Get the response as a WP_REST_Response object.
	 *
	 * @since 3.5.0
	 *
	 * @return \WP_REST_Response
	 */
	public function to_wp_rest_response() {
		$response_data = [
			'success' => $this->success,
		];

		if ( ! $this->success ) {
			$response_data['message'] = $this->message;
		}

		if ( null !== $this->data ) {
			$response_data['data'] = $this->data;
		}

		return new \WP_REST_Response( $response_data, $this->status_code );
	}

	/**
	 * Create a success response.
	 *
	 * @since 3.5.0
	 *
	 * @param mixed $data Response data.
	 * @param int   $status_code HTTP status code.
	 * @return self
	 */
	public static function success( $data = null, $status_code = 200 ) {
		$response = new self( $data );
		$response->set_status_code( $status_code );

		return $response;
	}

	/**
	 * Create an error response.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Error message.
	 * @param int    $status_code HTTP status code.
	 * @param mixed  $data Additional error data.
	 * @return self
	 */
	public static function error( $message, $status_code = 400, $data = null ) {
		$response = new self( $data );
		$response->set_error( $message, $status_code );

		return $response;
	}
}
