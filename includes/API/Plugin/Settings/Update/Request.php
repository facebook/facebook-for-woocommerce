<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin\Settings\Update;

use WooCommerce\Facebook\API\Plugin\Request as RESTRequest;
use WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Update REST API Request.
 *
 * @since 3.5.0
 */
class Request extends RESTRequest {

	use JS_Exposable;

	/**
	 * Gets the API endpoint for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return 'settings/update';
	}

	/**
	 * Gets the HTTP method for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_method() {
		return 'POST';
	}

	/**
	 * Gets the parameter schema for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return array Array of parameters with their types and whether they're required
	 */
	public function get_param_schema() {
		return [
			'merchant_access_token' => [
				'type'     => 'string',
				'required' => true,
			],
			'access_token'          => [
				'type'     => 'string',
				'required' => true,
			],
			'external_business_id'  => [
				'type'     => 'string',
				'required' => false,
			],
			'catalog_id'            => [
				'type'     => 'string',
				'required' => false,
			],
			'pixel_id'              => [
				'type'     => 'string',
				'required' => false,
			],
		];
	}

	/**
	 * Gets the JavaScript function name for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_js_function_name() {
		return 'updateSettings';
	}

	/**
	 * Validate the request.
	 *
	 * @since 3.5.0
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
