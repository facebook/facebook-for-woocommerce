<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API;

use WooCommerce\Facebook\Framework\Api\JSONResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Base API response object
 *
 * @since 2.0.0
 */
class Response extends JSONResponse {

	use Traits\Rate_Limited_Response;

	/**
	 * Gets the response ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}


	/**
	 * Determines whether the response includes an API error.
	 *
	 * @link https://developers.facebook.com/docs/graph-api/using-graph-api/error-handling#handling-errors
	 *
	 * @since 2.0.0
	 *
	 * @return boolean
	 */
	public function has_api_error() {
		return (bool) $this->error;
	}


	/**
	 * Gets the API error type.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_api_error_type() {
		return $this->error['type'] ?? null;
	}


	/**
	 * Gets the API error message.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_api_error_message() {
		return $this->error['message'] ?? null;
	}


	/**
	 * Gets the API error code.
	 *
	 * @since 2.0.0
	 *
	 * @return int|null
	 */
	public function get_api_error_code() {
		return $this->error['code'] ?? null;
	}


	/**
	 * Gets the API error subcode.
	 *
	 * @link https://developers.facebook.com/docs/graph-api/using-graph-api/error-handling#subcode
	 *
	 * @since 3.5.17
	 *
	 * @return int|null
	 */
	public function get_api_error_subcode() {
		return $this->error['error_subcode'] ?? null;
	}


	/**
	 * Gets the user error message.
	 *
	 * @since 2.1.0
	 *
	 * @return string|null
	 */
	public function get_user_error_message() {
		return $this->error['error_user_msg'] ?? null;
	}
}
