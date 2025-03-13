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
 * Request object for the API endpoint that returns Facebook Business Extension data.
 *
 * @since 2.6.0
 */
class Request extends API\Request {

	/**
	 * Constructor for the Business Read request.
	 *
	 * @since 2.6.0
	 *
	 * @param string $access_token The access token.
	 * @param string $external_business_id The external business ID.
	 */
	public function __construct( $access_token, $external_business_id ) {
		parent::__construct( '/fbe_business', 'GET' );

		$this->set_params(
			array(
				'access_token'             => $access_token,
				'fields'                   => 'commerce_extension',
				'fbe_external_business_id' => $external_business_id,
			)
		);
	}

	/**
	 * Gets the rate limit ID.
	 *
	 * @since 2.6.0
	 *
	 * @return string
	 */
	public static function get_rate_limit_id() {
		return 'ads_management';
	}
}
