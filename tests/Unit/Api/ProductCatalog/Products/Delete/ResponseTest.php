<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */


declare( strict_types=1 );

namespace Api\ProductCatalog\Products\Delete;

use WooCommerce;
use WP_UnitTestCase;

/**
 * Test cases for product delete API response
 */
class ResponseTest extends WP_UnitTestCase {
	/**
	 * Tests response value
	 *
	 * @return void
	 */
	public function test_response() {
		$json     = '{"success":true}';
		$response = new WooCommerce\Facebook\API\ProductCatalog\Products\Delete\Response( $json );

		$this->assertTrue( $response->success );
	}
}
