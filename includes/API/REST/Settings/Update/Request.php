<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST\Settings\Update;

use WooCommerce\Facebook\API\REST\Request as RESTRequest;
use WooCommerce\Facebook\API\REST\Traits\JS_Exposable;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Update REST API Request.
 *
 * @since 2.3.5
 */
class Request extends RESTRequest {

	use JS_Exposable;

	/**
	 * Validate the request.
	 *
	 * @since 2.3.5
	 *
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate() {
		// Validate required tokens
		if ( empty( $this->get_param( 'merchant_access_token' ) ) ) {
			return new \WP_Error(
				'missing_merchant_token',
				__( 'Missing merchant access token', 'facebook-for-woocommerce' ),
				[ 'status' => 400 ]
			);
		}

		if ( empty( $this->get_param( 'access_token' ) ) ) {
			return new \WP_Error(
				'missing_access_token',
				__( 'Missing access token', 'facebook-for-woocommerce' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}
} 