<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

declare( strict_types=1 );

namespace Unit\Api\Catalog\Product_Group\Products\Read;

use WooCommerce\Facebook\API\Catalog\Product_Group\Products\Read\Request;
use WP_UnitTestCase;

class RequestTest extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function test_request() {
		$product_group_id = 'test_product_group_id';
		$limit = 1;

		$request = new Request($product_group_id, $limit);

		$expected_path = "/$product_group_id/products";
		$expected_params = array('fields' => 'id,retailer_id', 'limit'=> $limit);

		$this->assertEquals('GET', $request->get_method());
		$this->assertEquals($expected_path, $request->get_path());
		$this->assertEquals($expected_params, $request->get_params());
	}

	/**
	 * @return void
	 */
	public function test_get_rate_limit_id() {
		$this->assertEquals( 'ads_management', Request::get_rate_limit_id());
	}
}


