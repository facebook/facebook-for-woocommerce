<?php

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
