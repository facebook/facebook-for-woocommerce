<?php
declare( strict_types=1 );

namespace Api\ProductCatalog\ItemsBatch\Create;

use WooCommerce;
use WooCommerce\Facebook\API\Request as ApiRequest;
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

	/**
	 * Tests request with empty requests array
	 *
	 * @return void
	 */
	public function test_request_with_empty_requests_array(): void {
		$product_catalog_id = 'test-catalog-id';
		$requests           = [];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( '/' . $product_catalog_id . '/items_batch', $request->get_path() );
		$this->assertEquals( [], $request->get_data()['requests'] );
		$this->assertIsArray( $request->get_data()['requests'] );
	}

	/**
	 * Tests request with multiple batch requests
	 *
	 * @return void
	 */
	public function test_request_with_multiple_requests(): void {
		$product_catalog_id = 'catalog-123';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'product-1',
					'name'        => 'Product One',
					'price'       => '10.00 USD',
				],
			],
			[
				'method' => 'UPDATE',
				'data'   => [
					'retailer_id' => 'product-2',
					'name'        => 'Product Two',
					'price'       => '20.00 USD',
				],
			],
			[
				'method' => 'DELETE',
				'data'   => [
					'retailer_id' => 'product-3',
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$this->assertEquals( $requests, $request->get_data()['requests'] );
		$this->assertCount( 3, $request->get_data()['requests'] );
	}

	/**
	 * Tests that request data contains all required fields
	 *
	 * @return void
	 */
	public function test_request_data_structure(): void {
		$product_catalog_id = 'test-catalog';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'test-product',
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );
		$data    = $request->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'allow_upsert', $data );
		$this->assertArrayHasKey( 'requests', $data );
		$this->assertArrayHasKey( 'item_type', $data );
		$this->assertCount( 3, $data );
	}

	/**
	 * Tests that allow_upsert is always set to true
	 *
	 * @return void
	 */
	public function test_allow_upsert_is_true(): void {
		$product_catalog_id = 'catalog-id';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'product-id',
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$this->assertTrue( $request->get_data()['allow_upsert'] );
		$this->assertIsBool( $request->get_data()['allow_upsert'] );
	}

	/**
	 * Tests that item_type is always set to PRODUCT_ITEM
	 *
	 * @return void
	 */
	public function test_item_type_is_product_item(): void {
		$product_catalog_id = 'catalog-id';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'product-id',
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$this->assertEquals( 'PRODUCT_ITEM', $request->get_data()['item_type'] );
		$this->assertIsString( $request->get_data()['item_type'] );
	}

	/**
	 * Tests request with different catalog ID formats
	 *
	 * @return void
	 */
	public function test_request_with_different_catalog_ids(): void {
		$requests = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'test-product',
				],
			],
		];

		// Numeric catalog ID
		$request1 = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( '123456789', $requests );
		$this->assertEquals( '/123456789/items_batch', $request1->get_path() );

		// Alphanumeric catalog ID
		$request2 = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( 'catalog123abc', $requests );
		$this->assertEquals( '/catalog123abc/items_batch', $request2->get_path() );

		// Catalog ID with hyphens
		$request3 = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( 'catalog-123-abc', $requests );
		$this->assertEquals( '/catalog-123-abc/items_batch', $request3->get_path() );

		// Catalog ID with underscores
		$request4 = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( 'catalog_123_abc', $requests );
		$this->assertEquals( '/catalog_123_abc/items_batch', $request4->get_path() );
	}

	/**
	 * Tests that request path is correctly constructed with catalog ID
	 *
	 * @return void
	 */
	public function test_request_path_construction(): void {
		$product_catalog_id = 'my-catalog-id';
		$requests           = [];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$expected_path = '/' . $product_catalog_id . '/items_batch';
		$this->assertEquals( $expected_path, $request->get_path() );
		$this->assertStringStartsWith( '/', $request->get_path() );
		$this->assertStringEndsWith( '/items_batch', $request->get_path() );
		$this->assertStringContainsString( $product_catalog_id, $request->get_path() );
	}

	/**
	 * Tests that Request extends the proper parent class
	 *
	 * @return void
	 */
	public function test_request_inherits_from_api_request(): void {
		$product_catalog_id = 'test-catalog';
		$requests           = [];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$this->assertInstanceOf( ApiRequest::class, $request );
		$this->assertTrue( method_exists( $request, 'get_method' ) );
		$this->assertTrue( method_exists( $request, 'get_path' ) );
		$this->assertTrue( method_exists( $request, 'get_data' ) );
		$this->assertTrue( method_exists( $request, 'set_params' ) );
		$this->assertTrue( method_exists( $request, 'set_data' ) );
	}

	/**
	 * Tests that modifying the requests array after construction doesn't affect request data
	 *
	 * @return void
	 */
	public function test_request_data_immutability(): void {
		$product_catalog_id = 'catalog-id';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id' => 'product-1',
					'name'        => 'Original Product',
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		// Get initial data
		$initial_data = $request->get_data();

		// Modify the original requests array
		$requests[] = [
			'method' => 'CREATE',
			'data'   => [
				'retailer_id' => 'product-2',
				'name'        => 'New Product',
			],
		];

		// Get data again
		$final_data = $request->get_data();

		// Data should remain unchanged
		$this->assertEquals( $initial_data, $final_data );
		$this->assertCount( 1, $final_data['requests'] );
		$this->assertEquals( 'Original Product', $final_data['requests'][0]['data']['name'] );
	}

	/**
	 * Tests request with complex nested data structures in batch requests
	 *
	 * @return void
	 */
	public function test_request_with_complex_batch_data(): void {
		$product_catalog_id = 'catalog-complex';
		$requests           = [
			[
				'method' => 'CREATE',
				'data'   => [
					'retailer_id'          => 'complex-product-1',
					'name'                 => 'Complex Product',
					'description'          => 'A product with complex data',
					'price'                => '99.99 USD',
					'availability'         => 'in stock',
					'condition'            => 'new',
					'brand'                => 'Test Brand',
					'additional_image_url' => [
						'https://example.com/image1.jpg',
						'https://example.com/image2.jpg',
						'https://example.com/image3.jpg',
					],
					'custom_data'          => [
						'category' => 'Electronics',
						'tags'     => [ 'new', 'featured', 'sale' ],
						'metadata' => [
							'weight' => '1.5kg',
							'color'  => 'blue',
						],
					],
				],
			],
			[
				'method' => 'UPDATE',
				'data'   => [
					'retailer_id' => 'complex-product-2',
					'price'       => '149.99 USD',
					'inventory'   => 50,
					'variants'    => [
						[
							'size'  => 'small',
							'color' => 'red',
							'sku'   => 'SKU-001',
						],
						[
							'size'  => 'medium',
							'color' => 'blue',
							'sku'   => 'SKU-002',
						],
					],
				],
			],
		];

		$request = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Request( $product_catalog_id, $requests );

		$data = $request->get_data();

		$this->assertEquals( $requests, $data['requests'] );
		$this->assertCount( 2, $data['requests'] );

		// Verify first request complex data
		$this->assertArrayHasKey( 'additional_image_url', $data['requests'][0]['data'] );
		$this->assertIsArray( $data['requests'][0]['data']['additional_image_url'] );
		$this->assertCount( 3, $data['requests'][0]['data']['additional_image_url'] );

		$this->assertArrayHasKey( 'custom_data', $data['requests'][0]['data'] );
		$this->assertIsArray( $data['requests'][0]['data']['custom_data'] );
		$this->assertArrayHasKey( 'metadata', $data['requests'][0]['data']['custom_data'] );

		// Verify second request complex data
		$this->assertArrayHasKey( 'variants', $data['requests'][1]['data'] );
		$this->assertIsArray( $data['requests'][1]['data']['variants'] );
		$this->assertCount( 2, $data['requests'][1]['data']['variants'] );
	}
}
