<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\CommerceIntegration\Repair;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Commerce Integration Repair API request object.
 *
 * @since 3.4.8
 */
class Request extends API\Request {

	/**
	 * API request constructor.
	 *
	 * @since 3.4.8
	 */
	public function __construct() {
		parent::__construct( '/commerce_integration_repair', 'POST' );
	}
}
