<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\ProductCatalog\Products\Create;

use WC_Facebook_Product;
use WC_Helper_Product;
use WP_UnitTestCase;
use WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request;

/**
 * Test cases for Product Catalog Products Create API request.
 *
 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request
 */
class RequestTest extends WP_UnitTestCase {

	/**
	 * Tests basic request configuration with product data.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_request() {
		$product          = WC_Helper_Product::create_simple_product();
		$facebook_product = new WC_Facebook_Product( $product );
		$product_group_id = 'facebook-product-group-id';
		$data             = $facebook_product->prepare_product();
		$request          = new Request( $product_group_id, $data );

		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( '/facebook-product-group-id/products', $request->get_path() );
		$this->assertEquals( $data, $request->get_data() );
	}

	/**
	 * Tests constructor with numeric product catalog ID.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_constructor_with_numeric_catalog_id() {
		$product_catalog_id = '123456789';
		$data               = array( 'name' => 'Test Product' );
		$request            = new Request( $product_catalog_id, $data );

		$this->assertEquals( '/123456789/products', $request->get_path() );
		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( $data, $request->get_data() );
	}

	/**
	 * Tests constructor with product catalog ID containing special characters.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_constructor_with_special_characters_in_catalog_id() {
		$product_catalog_id = 'catalog-id_123';
		$data               = array( 'name' => 'Test Product' );
		$request            = new Request( $product_catalog_id, $data );

		$this->assertEquals( '/catalog-id_123/products', $request->get_path() );
		$this->assertEquals( $data, $request->get_data() );
	}

	/**
	 * Tests constructor with empty data array.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_constructor_with_empty_data() {
		$product_catalog_id = 'test-catalog';
		$data               = array();
		$request            = new Request( $product_catalog_id, $data );

		$this->assertEquals( '/test-catalog/products', $request->get_path() );
		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( array(), $request->get_data() );
	}

	/**
	 * Tests constructor with complex nested data.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_constructor_with_complex_nested_data() {
		$product_catalog_id = 'test-catalog';
		$data               = array(
			'name'        => 'Test Product',
			'description' => 'Test Description',
			'price'       => 1999,
			'currency'    => 'USD',
			'availability' => 'in stock',
			'custom_data' => array(
				'key1' => 'value1',
				'key2' => array(
					'nested_key' => 'nested_value',
				),
			),
		);
		$request            = new Request( $product_catalog_id, $data );

		$this->assertEquals( $data, $request->get_data() );
		$this->assertIsArray( $request->get_data() );
		$this->assertArrayHasKey( 'custom_data', $request->get_data() );
	}

	/**
	 * Tests that request method is always POST.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_method_returns_post() {
		$request = new Request( 'catalog-123', array( 'name' => 'Product' ) );

		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertIsString( $request->get_method() );
	}

	/**
	 * Tests that path is correctly formatted with product catalog ID.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_path_format() {
		$product_catalog_id = 'my-catalog-id';
		$request            = new Request( $product_catalog_id, array() );

		$this->assertEquals( '/my-catalog-id/products', $request->get_path() );
		$this->assertStringStartsWith( '/', $request->get_path() );
		$this->assertStringEndsWith( '/products', $request->get_path() );
	}

	/**
	 * Tests that data is properly set and retrievable.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_data_returns_set_data() {
		$data    = array(
			'name'  => 'Test Product',
			'price' => 2999,
		);
		$request = new Request( 'catalog-id', $data );

		$this->assertEquals( $data, $request->get_data() );
		$this->assertIsArray( $request->get_data() );
	}

	/**
	 * Tests that params are empty by default.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_params_returns_empty_array_by_default() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$this->assertEquals( array(), $request->get_params() );
		$this->assertIsArray( $request->get_params() );
		$this->assertEmpty( $request->get_params() );
	}

	/**
	 * Tests to_string method with data.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_to_string_with_data() {
		$data    = array(
			'name'  => 'Test Product',
			'price' => 1999,
		);
		$request = new Request( 'catalog-id', $data );

		$result = $request->to_string();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertEquals( wp_json_encode( $data ), $result );
	}

	/**
	 * Tests to_string method with empty data.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_to_string_with_empty_data() {
		$request = new Request( 'catalog-id', array() );

		$result = $request->to_string();

		$this->assertIsString( $result );
		$this->assertEquals( '', $result );
	}

	/**
	 * Tests get_retry_count returns default value.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_retry_count_default_value() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$this->assertEquals( 0, $request->get_retry_count() );
		$this->assertIsInt( $request->get_retry_count() );
	}

	/**
	 * Tests get_retry_limit returns default value.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_retry_limit_default_value() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$retry_limit = $request->get_retry_limit();

		$this->assertIsInt( $retry_limit );
		$this->assertGreaterThanOrEqual( 0, $retry_limit );
	}

	/**
	 * Tests get_retry_codes returns array.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_retry_codes_returns_array() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$retry_codes = $request->get_retry_codes();

		$this->assertIsArray( $retry_codes );
	}

	/**
	 * Tests get_base_path_override returns null.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_base_path_override_returns_null() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$this->assertNull( $request->get_base_path_override() );
	}

	/**
	 * Tests get_request_specific_headers returns empty array.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_get_request_specific_headers_returns_empty_array() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$headers = $request->get_request_specific_headers();

		$this->assertIsArray( $headers );
		$this->assertEmpty( $headers );
		$this->assertEquals( array(), $headers );
	}

	/**
	 * Tests to_string_safe method returns same as to_string.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_to_string_safe_returns_same_as_to_string() {
		$data    = array(
			'name'  => 'Test Product',
			'price' => 1999,
		);
		$request = new Request( 'catalog-id', $data );

		$this->assertEquals( $request->to_string(), $request->to_string_safe() );
	}

	/**
	 * Tests mark_retry increments retry count.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_mark_retry_increments_count() {
		$request = new Request( 'catalog-id', array( 'name' => 'Product' ) );

		$this->assertEquals( 0, $request->get_retry_count() );

		$request->mark_retry();
		$this->assertEquals( 1, $request->get_retry_count() );

		$request->mark_retry();
		$this->assertEquals( 2, $request->get_retry_count() );
	}

	/**
	 * Tests constructor with very long product catalog ID.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_constructor_with_long_catalog_id() {
		$product_catalog_id = str_repeat( 'a', 100 );
		$data               = array( 'name' => 'Product' );
		$request            = new Request( $product_catalog_id, $data );

		$expected_path = '/' . $product_catalog_id . '/products';
		$this->assertEquals( $expected_path, $request->get_path() );
	}

	/**
	 * Tests that data array preserves all keys and values.
	 *
	 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Request::__construct
	 * @return void
	 */
	public function test_data_preserves_all_keys_and_values() {
		$data = array(
			'name'         => 'Product Name',
			'description'  => 'Product Description',
			'price'        => 2999,
			'currency'     => 'USD',
			'availability' => 'in stock',
			'condition'    => 'new',
			'brand'        => 'Test Brand',
		);

		$request      = new Request( 'catalog-id', $data );
		$request_data = $request->get_data();

		foreach ( $data as $key => $value ) {
			$this->assertArrayHasKey( $key, $request_data );
			$this->assertEquals( $value, $request_data[ $key ] );
		}
	}
}
