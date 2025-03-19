<?php
declare( strict_types=1 );

namespace Api\ProductCatalog\ItemsBatch\Create;

use WooCommerce;
use WP_UnitTestCase;

/**
 * Test cases for Items Batch create API request
 */
class RequestTest extends WP_UnitTestCase {
	/**
	 * Tests request endpoint config
	 *
	 * @return void
	 */
	public function test_request(): void {
		$product_catalog_id = 'facebook-product-catalog-id';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'product-123',
					'name'        => 'Test Product',
					'price'       => '19.99 USD',
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( '/' . $product_catalog_id . '/items_batch', $request->get_path() );
		$this->assertEquals( $requests, $request->get_data()['requests'] );
	}
}
