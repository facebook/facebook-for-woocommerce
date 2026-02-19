<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\FBE\Installation;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE API request object.
 *
 * @since 2.0.0
 */
class Request extends API\Request {

	/**
	 * API request constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $path desired path
	 * @param string $method request method
	 */
	public function __construct( $path, $method ) {
		parent::__construct( "/fbe_business/{$path}", $method );
	}
}
