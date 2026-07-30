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
 * Test cases for product delete API request
 */
class RequestTest extends WP_UnitTestCase {
	/**
	 * Tests request endpoint config
	 *
	 * @return void
	 */
	public function test_request() {
		$product_group_id = 'facebook-product-group-id';
		$request          = new WooCommerce\Facebook\API\ProductCatalog\Products\Delete\Request( $product_group_id );

		$this->assertEquals( 'DELETE', $request->get_method() );
		$this->assertEquals( '/facebook-product-group-id', $request->get_path() );
	}
}
