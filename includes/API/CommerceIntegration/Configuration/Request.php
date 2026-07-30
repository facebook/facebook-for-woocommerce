<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Configuration;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Commerce Integration Configuration API request object.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $commerce_integration_id commerce integration ID
	 * @param string $method request method
	 */
	public function __construct( $commerce_integration_id, $method ) {
		parent::__construct( '/' . $commerce_integration_id, $method );
	}
}
