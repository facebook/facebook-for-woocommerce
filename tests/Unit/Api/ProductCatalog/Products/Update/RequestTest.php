<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace Unit\Api\ProductCatalog\Products\Update;

use WC_Facebook_Product;
use WC_Helper_Product;
use WP_UnitTestCase;
use WooCommerce;

/**
 * Test cases for product update API request
 */
class RequestTest extends WP_UnitTestCase {
	/**
	 * @return void
	 */
	public function test_request() {
		$product          = WC_Helper_Product::create_simple_product();
		$facebook_product = new WC_Facebook_Product( $product );
		$product_id       = 'facebook-product-id';
		$data             = $facebook_product->prepare_product();
		$request          = new WooCommerce\Facebook\API\ProductCatalog\Products\Update\Request( $product_id, $data );

		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( '/facebook-product-id', $request->get_path() );
		$this->assertEquals( $data, $request->get_data() );
	}
}
