<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API;

use WooCommerce\Facebook\API\Request;
use WooCommerce\Facebook\Framework\Api\JSONRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for base API Request class.
 *
 * @since 2.0.0
 */
class RequestTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Test that the Request class exists and extends proper parent classes.
	 *
	 * @covers \WooCommerce\Facebook\API\Request
	 */
	public function test_request_class_inheritance() {
		$this->assertTrue( class_exists( Request::class ) );
		
		$request = new Request( '/test/path', 'GET' );
		$this->assertInstanceOf( JSONRequest::class, $request );
	}

	/**
	 * Test constructor with standard path and method.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::__construct
	 */
	public function test_constructor_with_standard_parameters() {
		$request = new Request( '/catalog/products', 'GET' );
		
		$this->assertEquals( '/catalog/products', $request->get_path() );
		$this->assertEquals( 'GET', $request->get_method() );
	}

	/**
	 * Test constructor with different HTTP methods.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::__construct
	 */
	public function test_constructor_with_different_methods() {
		$methods = [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ];
		
		foreach ( $methods as $method ) {
			$request = new Request( '/test/path', $method );
			$this->assertEquals( $method, $request->get_method() );
		}
	}

	/**
	 * Test constructor with different path formats.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::__construct
	 */
	public function test_constructor_with_different_paths() {
		// Empty path
		$request1 = new Request( '', 'GET' );
		$this->assertEquals( '', $request1->get_path() );
		
		// Path with query parameters
		$request2 = new Request( '/products?limit=10', 'GET' );
		$this->assertEquals( '/products?limit=10', $request2->get_path() );
		
		// Path with special characters
		$request3 = new Request( '/products/test-product_123', 'GET' );
		$this->assertEquals( '/products/test-product_123', $request3->get_path() );
		
		// Very long path
		$longPath = '/' . str_repeat( 'a', 200 );
		$request4 = new Request( $longPath, 'GET' );
		$this->assertEquals( $longPath, $request4->get_path() );
	}

	/**
	 * Test set_params method with various data types.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_params
	 */
	public function test_set_params_with_various_data() {
		$request = new Request( '/test', 'GET' );
		
		// Test with standard params
		$params = [
			'access_token' => 'test_token_123',
			'limit' => 50,
			'fields' => 'id,name,description',
		];
		
		$request->set_params( $params );
		$this->assertEquals( $params, $request->get_params() );
		
		// Test with empty params
		$request->set_params( [] );
		$this->assertEquals( [], $request->get_params() );
		
		// Test with nested arrays
		$nested_params = [
			'filter' => [
				'status' => 'active',
				'type' => 'product',
			],
			'sort' => [ 'created_at' => 'desc' ],
		];
		
		$request->set_params( $nested_params );
		$this->assertEquals( $nested_params, $request->get_params() );
	}

	/**
	 * Test set_params overwrites previous params.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_params
	 */
	public function test_set_params_overwrites_previous_params() {
		$request = new Request( '/test', 'GET' );
		
		$params1 = [ 'key1' => 'value1' ];
		$params2 = [ 'key2' => 'value2' ];
		
		$request->set_params( $params1 );
		$this->assertEquals( $params1, $request->get_params() );
		
		$request->set_params( $params2 );
		$this->assertEquals( $params2, $request->get_params() );
		$this->assertArrayNotHasKey( 'key1', $request->get_params() );
	}

	/**
	 * Test set_data method with various data types.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_set_data_with_various_data() {
		$request = new Request( '/test', 'POST' );
		
		// Test with standard data
		$data = [
			'name' => 'Test Product',
			'price' => 99.99,
			'in_stock' => true,
		];
		
		$request->set_data( $data );
		$this->assertEquals( $data, $request->get_data() );
		
		// Test with empty data
		$request->set_data( [] );
		$this->assertEquals( [], $request->get_data() );
		
		// Test with nested data
		$nested_data = [
			'product' => [
				'name' => 'Test',
				'variants' => [
					[ 'size' => 'S', 'color' => 'red' ],
					[ 'size' => 'M', 'color' => 'blue' ],
				],
			],
		];
		
		$request->set_data( $nested_data );
		$this->assertEquals( $nested_data, $request->get_data() );
	}

	/**
	 * Test set_data overwrites previous data.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_set_data_overwrites_previous_data() {
		$request = new Request( '/test', 'POST' );
		
		$data1 = [ 'field1' => 'value1' ];
		$data2 = [ 'field2' => 'value2' ];
		
		$request->set_data( $data1 );
		$this->assertEquals( $data1, $request->get_data() );
		
		$request->set_data( $data2 );
		$this->assertEquals( $data2, $request->get_data() );
		$this->assertArrayNotHasKey( 'field1', $request->get_data() );
	}

	/**
	 * Test get_retry_count returns initial value.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_count
	 */
	public function test_get_retry_count_initial_value() {
		$request = new Request( '/test', 'GET' );
		
		$this->assertEquals( 0, $request->get_retry_count() );
		$this->assertIsInt( $request->get_retry_count() );
	}

	/**
	 * Test mark_retry increments retry count.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::mark_retry
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_count
	 */
	public function test_mark_retry_increments_count() {
		$request = new Request( '/test', 'GET' );
		
		$this->assertEquals( 0, $request->get_retry_count() );
		
		$request->mark_retry();
		$this->assertEquals( 1, $request->get_retry_count() );
		
		$request->mark_retry();
		$this->assertEquals( 2, $request->get_retry_count() );
		
		$request->mark_retry();
		$this->assertEquals( 3, $request->get_retry_count() );
	}

	/**
	 * Test mark_retry can be called multiple times.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::mark_retry
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_count
	 */
	public function test_mark_retry_multiple_times() {
		$request = new Request( '/test', 'GET' );
		
		for ( $i = 1; $i <= 10; $i++ ) {
			$request->mark_retry();
			$this->assertEquals( $i, $request->get_retry_count() );
		}
	}

	/**
	 * Test get_retry_limit returns default value.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_limit
	 */
	public function test_get_retry_limit_default_value() {
		$request = new Request( '/test', 'GET' );
		
		$this->assertEquals( 5, $request->get_retry_limit() );
		$this->assertIsInt( $request->get_retry_limit() );
	}

	/**
	 * Test get_retry_limit applies filter.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_limit
	 */
	public function test_get_retry_limit_applies_filter() {
		$request = new Request( '/test', 'GET' );
		
		// Test with filter that increases limit
		$filter = $this->add_filter_with_safe_teardown(
			'wc_facebook_api_request_retry_limit',
			function( $limit, $req ) use ( $request ) {
				$this->assertEquals( 5, $limit );
				$this->assertSame( $request, $req );
				return 10;
			}
		);
		
		$this->assertEquals( 10, $request->get_retry_limit() );
		$filter->teardown_safely_immediately();
		
		// Test with filter that decreases limit
		$filter2 = $this->add_filter_with_safe_teardown(
			'wc_facebook_api_request_retry_limit',
			function( $limit ) {
				return 3;
			}
		);
		
		$this->assertEquals( 3, $request->get_retry_limit() );
		$filter2->teardown_safely_immediately();
	}

	/**
	 * Test get_retry_limit filter receives correct parameters.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_limit
	 */
	public function test_get_retry_limit_filter_parameters() {
		$request = new Request( '/catalog/products', 'POST' );
		
		$filter_called = false;
		$filter = $this->add_filter_with_safe_teardown(
			'wc_facebook_api_request_retry_limit',
			function( $limit, $req ) use ( $request, &$filter_called ) {
				$filter_called = true;
				$this->assertEquals( 5, $limit );
				$this->assertInstanceOf( Request::class, $req );
				$this->assertSame( $request, $req );
				return $limit;
			}
		);
		
		$request->get_retry_limit();
		$this->assertTrue( $filter_called );
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test get_retry_limit casts return value to int.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_limit
	 */
	public function test_get_retry_limit_casts_to_int() {
		$request = new Request( '/test', 'GET' );
		
		// Test with string value
		$filter = $this->add_filter_with_safe_teardown(
			'wc_facebook_api_request_retry_limit',
			function() {
				return '7';
			}
		);
		
		$result = $request->get_retry_limit();
		$this->assertIsInt( $result );
		$this->assertEquals( 7, $result );
		$filter->teardown_safely_immediately();
		
		// Test with float value
		$filter2 = $this->add_filter_with_safe_teardown(
			'wc_facebook_api_request_retry_limit',
			function() {
				return 8.9;
			}
		);
		
		$result2 = $request->get_retry_limit();
		$this->assertIsInt( $result2 );
		$this->assertEquals( 8, $result2 );
		$filter2->teardown_safely_immediately();
	}

	/**
	 * Test get_retry_codes returns default empty array.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_codes
	 */
	public function test_get_retry_codes_default_value() {
		$request = new Request( '/test', 'GET' );
		
		$retry_codes = $request->get_retry_codes();
		
		$this->assertIsArray( $retry_codes );
		$this->assertEmpty( $retry_codes );
		$this->assertEquals( [], $retry_codes );
	}

	/**
	 * Test get_base_path_override returns null.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_base_path_override
	 */
	public function test_get_base_path_override_returns_null() {
		$request = new Request( '/test', 'GET' );
		
		$this->assertNull( $request->get_base_path_override() );
	}

	/**
	 * Test get_request_specific_headers returns empty array.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_request_specific_headers
	 */
	public function test_get_request_specific_headers_returns_empty_array() {
		$request = new Request( '/test', 'GET' );
		
		$headers = $request->get_request_specific_headers();
		
		$this->assertIsArray( $headers );
		$this->assertEmpty( $headers );
		$this->assertEquals( [], $headers );
	}

	/**
	 * Test inherited get_method from JSONRequest.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::__construct
	 */
	public function test_get_method_inherited() {
		$request = new Request( '/test', 'POST' );
		
		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertIsString( $request->get_method() );
	}

	/**
	 * Test inherited get_path from JSONRequest.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::__construct
	 */
	public function test_get_path_inherited() {
		$request = new Request( '/catalog/items', 'GET' );
		
		$this->assertEquals( '/catalog/items', $request->get_path() );
		$this->assertIsString( $request->get_path() );
	}

	/**
	 * Test inherited get_params from JSONRequest.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_params
	 */
	public function test_get_params_inherited() {
		$request = new Request( '/test', 'GET' );
		
		// Default should be empty array
		$this->assertEquals( [], $request->get_params() );
		
		$params = [ 'key' => 'value' ];
		$request->set_params( $params );
		$this->assertEquals( $params, $request->get_params() );
	}

	/**
	 * Test inherited get_data from JSONRequest.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_get_data_inherited() {
		$request = new Request( '/test', 'POST' );
		
		// Default should be empty array
		$this->assertEquals( [], $request->get_data() );
		
		$data = [ 'field' => 'value' ];
		$request->set_data( $data );
		$this->assertEquals( $data, $request->get_data() );
	}

	/**
	 * Test to_string method with empty data.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_to_string_with_empty_data() {
		$request = new Request( '/test', 'POST' );
		
		$this->assertEquals( '', $request->to_string() );
	}

	/**
	 * Test to_string method with populated data.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_to_string_with_populated_data() {
		$request = new Request( '/test', 'POST' );
		
		$data = [
			'name' => 'Test Product',
			'price' => 99.99,
			'active' => true,
		];
		
		$request->set_data( $data );
		
		$result = $request->to_string();
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		
		// Verify it's valid JSON
		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( $data, $decoded );
	}

	/**
	 * Test to_string_safe method with empty data.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_to_string_safe_with_empty_data() {
		$request = new Request( '/test', 'POST' );
		
		$this->assertEquals( '', $request->to_string_safe() );
	}

	/**
	 * Test to_string_safe method with populated data.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_to_string_safe_with_populated_data() {
		$request = new Request( '/test', 'POST' );
		
		$data = [
			'key' => 'value',
			'number' => 123,
		];
		
		$request->set_data( $data );
		
		$result = $request->to_string_safe();
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		
		// Verify it's valid JSON
		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( $data, $decoded );
	}

	/**
	 * Test rate limit trait method get_rate_limit_id.
	 *
	 * @covers \WooCommerce\Facebook\API\Request
	 */
	public function test_get_rate_limit_id_from_trait() {
		$request = new Request( '/test', 'GET' );
		
		$this->assertTrue( method_exists( $request, 'get_rate_limit_id' ) );
		$this->assertEquals( 'graph_api_request', Request::get_rate_limit_id() );
		$this->assertIsString( Request::get_rate_limit_id() );
	}

	/**
	 * Test multiple instances are isolated from each other.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::__construct
	 * @covers \WooCommerce\Facebook\API\Request::set_params
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_multiple_instances_are_isolated() {
		$request1 = new Request( '/path1', 'GET' );
		$request2 = new Request( '/path2', 'POST' );
		
		// Verify paths are different
		$this->assertEquals( '/path1', $request1->get_path() );
		$this->assertEquals( '/path2', $request2->get_path() );
		
		// Verify methods are different
		$this->assertEquals( 'GET', $request1->get_method() );
		$this->assertEquals( 'POST', $request2->get_method() );
		
		// Set different params
		$request1->set_params( [ 'param1' => 'value1' ] );
		$request2->set_params( [ 'param2' => 'value2' ] );
		
		// Verify params are isolated
		$this->assertEquals( [ 'param1' => 'value1' ], $request1->get_params() );
		$this->assertEquals( [ 'param2' => 'value2' ], $request2->get_params() );
		
		// Set different data
		$request1->set_data( [ 'data1' => 'value1' ] );
		$request2->set_data( [ 'data2' => 'value2' ] );
		
		// Verify data is isolated
		$this->assertEquals( [ 'data1' => 'value1' ], $request1->get_data() );
		$this->assertEquals( [ 'data2' => 'value2' ], $request2->get_data() );
	}

	/**
	 * Test retry count is isolated between instances.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::mark_retry
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_count
	 */
	public function test_retry_count_isolated_between_instances() {
		$request1 = new Request( '/test1', 'GET' );
		$request2 = new Request( '/test2', 'GET' );
		
		// Mark retries on first request
		$request1->mark_retry();
		$request1->mark_retry();
		
		// Verify counts are isolated
		$this->assertEquals( 2, $request1->get_retry_count() );
		$this->assertEquals( 0, $request2->get_retry_count() );
		
		// Mark retry on second request
		$request2->mark_retry();
		
		// Verify counts remain isolated
		$this->assertEquals( 2, $request1->get_retry_count() );
		$this->assertEquals( 1, $request2->get_retry_count() );
	}

	/**
	 * Test params with special characters and types.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_params
	 */
	public function test_set_params_with_special_characters_and_types() {
		$request = new Request( '/test', 'GET' );
		
		$params = [
			'string' => 'test value',
			'int' => 123,
			'float' => 45.67,
			'bool_true' => true,
			'bool_false' => false,
			'null' => null,
			'special_chars' => 'test@example.com & value=123',
			'unicode' => 'テスト',
		];
		
		$request->set_params( $params );
		$this->assertEquals( $params, $request->get_params() );
	}

	/**
	 * Test data with special characters and types.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::set_data
	 */
	public function test_set_data_with_special_characters_and_types() {
		$request = new Request( '/test', 'POST' );
		
		$data = [
			'string' => 'test value',
			'int' => 456,
			'float' => 78.90,
			'bool_true' => true,
			'bool_false' => false,
			'null' => null,
			'special_chars' => 'test@example.com & value=456',
			'unicode' => 'テスト',
		];
		
		$request->set_data( $data );
		$this->assertEquals( $data, $request->get_data() );
	}

	/**
	 * Test that retry limit filter is called each time get_retry_limit is called.
	 *
	 * @covers \WooCommerce\Facebook\API\Request::get_retry_limit
	 */
	public function test_get_retry_limit_filter_called_each_time() {
		$request = new Request( '/test', 'GET' );
		
		$call_count = 0;
		$filter = $this->add_filter_with_safe_teardown(
			'wc_facebook_api_request_retry_limit',
			function( $limit ) use ( &$call_count ) {
				$call_count++;
				return $limit;
			}
		);
		
		$request->get_retry_limit();
		$this->assertEquals( 1, $call_count );
		
		$request->get_retry_limit();
		$this->assertEquals( 2, $call_count );
		
		$request->get_retry_limit();
		$this->assertEquals( 3, $call_count );
		
		$filter->teardown_safely_immediately();
	}
}
