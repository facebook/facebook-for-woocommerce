<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\FBE\Business\Read;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Response object for the Business Read API request.
 *
 * @since 2.6.0
 */
class Response extends API\Response {

	/**
	 * Gets the commerce extension URI.
	 *
	 * @since 2.6.0
	 *
	 * @return string
	 */
	public function get_commerce_extension_uri() {
		return isset( $this->response_data['commerce_extension']['uri'] ) ? $this->response_data['commerce_extension']['uri'] : '';
	}

	/**
	 * Determines whether the response contains a commerce extension URI.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function has_commerce_extension_uri() {
		return ! empty( $this->get_commerce_extension_uri() );
	}
}
