<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Api;

use WooCommerce\Facebook\Framework\Api\Request;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for the Request interface.
 */
class RequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Returns a concrete implementation of the Request interface for testing.
	 *
	 * @param array $args Optional overrides for method, path, params, data.
	 * @return Request
	 */
	private function get_test_request( array $args = [] ) : Request {
		$defaults = [
			'method' => 'POST',
			'path'   => '/test/path',
			'params' => [ 'foo' => 'bar' ],
			'data'   => [ 'baz' => 'qux' ],
			'string' => '{"baz":"qux"}',
			'string_safe' => '{"baz":"safe"}',
		];

		$args = array_merge( $defaults, $args );

		return new class( $args ) implements Request {
			private $method;
			private $path;
			private $params;
			private $data;
			private $string;
			private $string_safe;

			public function __construct( $args ) {
				$this->method = $args['method'];
				$this->path = $args['path'];
				$this->params = $args['params'];
				$this->data = $args['data'];
				$this->string = $args['string'];
				$this->string_safe = $args['string_safe'];
			}

			public function get_method() { return $this->method; }
			public function get_path() { return $this->path; }
			public function get_params() { return $this->params; }
			public function get_data() { return $this->data; }
			public function to_string() { return $this->string; }
			public function to_string_safe() { return $this->string_safe; }
		};
	}

	/**
	 * Test that get_method returns the expected value.
	 */
	public function test_get_method_returns_expected_value() {
		// Create a request with method 'GET'
		$request = $this->get_test_request([ 'method' => 'GET' ]);

		// Assert the method is 'GET'
		$this->assertEquals('GET', $request->get_method());
	}

	/**
	 * Test that get_path returns the expected value.
	 */
	public function test_get_path_returns_expected_value() {
		// Create a request with a custom path
		$request = $this->get_test_request([ 'path' => '/foo/bar' ]);

		// Assert the path is as set
		$this->assertEquals('/foo/bar', $request->get_path());
	}

	/**
	 * Test that get_params returns the expected array.
	 */
	public function test_get_params_returns_expected_array() {
		$params = [ 'a' => 1, 'b' => 2 ];

		// Create a request with custom params
		$request = $this->get_test_request([ 'params' => $params ]);

		// Assert params are an array and match
		$this->assertIsArray($request->get_params());
		$this->assertEquals($params, $request->get_params());
	}

	/**
	 * Test that get_data returns the expected array.
	 */
	public function test_get_data_returns_expected_array() {
		$data = [ 'x' => 10, 'y' => 20 ];

		// Create a request with custom data
		$request = $this->get_test_request([ 'data' => $data ]);

		// Assert data is an array and matches
		$this->assertIsArray($request->get_data());
		$this->assertEquals($data, $request->get_data());
	}

	/**
	 * Test that to_string returns the expected value.
	 */
	public function test_to_string_returns_expected_value() {
		// Create a request with a custom string
		$request = $this->get_test_request([ 'string' => 'foobar' ]);

		// Assert the string is as set
		$this->assertEquals('foobar', $request->to_string());
	}

	/**
	 * Test that to_string_safe returns the expected value.
	 */
	public function test_to_string_safe_returns_expected_value() {
		// Create a request with a custom safe string
		$request = $this->get_test_request([ 'string_safe' => 'safe-string' ]);

		// Assert the safe string is as set
		$this->assertEquals('safe-string', $request->to_string_safe());
	}

	/**
	 * Test that all methods return the expected types.
	 */
	public function test_types_of_all_methods() {
		// Create a default request
		$request = $this->get_test_request();

		// Assert types for all interface methods
		$this->assertIsString($request->get_method());
		$this->assertIsString($request->get_path());
		$this->assertIsArray($request->get_params());
		$this->assertIsArray($request->get_data());
		$this->assertIsString($request->to_string());
		$this->assertIsString($request->to_string_safe());
	}

	/**
	 * Test that empty params and data are handled correctly.
	 */
	public function test_empty_params_and_data() {
		// Create a request with empty params and data
		$request = $this->get_test_request([
			'params' => [],
			'data' => [],
			'string' => '',
			'string_safe' => '',
		]);

		// Assert params and data are empty
		$this->assertEmpty($request->get_params());
		$this->assertEmpty($request->get_data());

		// Assert string and safe string are empty
		$this->assertSame('', $request->to_string());
		$this->assertSame('', $request->to_string_safe());
	}

	/**
	 * Test that get_method returns null or empty string is handled.
	 */
	public function test_get_method_with_null_and_empty() {
		// Null method
		$request = $this->get_test_request([ 'method' => null ]);
		$this->assertNull($request->get_method());

		// Empty string method
		$request2 = $this->get_test_request([ 'method' => '' ]);
		$this->assertSame('', $request2->get_method());
	}

	/**
	 * Test that get_path returns null or empty string is handled.
	 */
	public function test_get_path_with_null_and_empty() {
		// Null path
		$request = $this->get_test_request([ 'path' => null ]);
		$this->assertNull($request->get_path());

		// Empty string path
		$request2 = $this->get_test_request([ 'path' => '' ]);
		$this->assertSame('', $request2->get_path());
	}

	/**
	 * Test params and data with non-string keys and values.
	 */
	public function test_params_and_data_with_non_string_keys_and_values() {
		$params = [ 1 => 2, true => false, 'str' => 123 ];
		$data = [ 0 => 'zero', 2 => 'int', 'arr' => [1,2,3] ];

		// Create a request with non-string keys/values
		$request = $this->get_test_request([
			'params' => $params,
			'data' => $data,
		]);

		// Assert params and data match
		$this->assertEquals($params, $request->get_params());
		$this->assertEquals($data, $request->get_data());
	}

	/**
	 * Test params and data with unicode and special characters.
	 */
	public function test_params_and_data_with_unicode_and_special_chars() {
		$params = [ 'üñîçødë' => '✓', 'spécial' => '!@#$%^&*()' ];
		$data = [ 'emoji' => '😀', 'newline' => "line1\nline2" ];

		// Create a request with unicode and special characters
		$request = $this->get_test_request([
			'params' => $params,
			'data' => $data,
		]);

		// Assert params and data match
		$this->assertEquals($params, $request->get_params());
		$this->assertEquals($data, $request->get_data());
	}

	/**
	 * Test large arrays for params and data.
	 */
	public function test_large_params_and_data_arrays() {
		$params = array_fill(0, 1000, 'param');
		$data = array_fill(0, 1000, 'data');

		// Create a request with large arrays
		$request = $this->get_test_request([
			'params' => $params,
			'data' => $data,
		]);

		// Assert the arrays are the correct size
		$this->assertCount(1000, $request->get_params());
		$this->assertCount(1000, $request->get_data());
	}

	/**
	 * Test to_string and to_string_safe returning null or non-string values.
	 */
	public function test_to_string_and_safe_with_null_and_non_string() {
		// to_string and to_string_safe return null
		$request = $this->get_test_request([
			'string' => null,
			'string_safe' => null,
		]);
		$this->assertNull($request->to_string());
		$this->assertNull($request->to_string_safe());

		// to_string returns int, to_string_safe returns array
		$request2 = $this->get_test_request([
			'string' => 12345,
			'string_safe' => [1,2,3],
		]);
		$this->assertSame(12345, $request2->to_string());
		$this->assertSame([1,2,3], $request2->to_string_safe());
	}

	/**
	 * Test overlapping values between params and data.
	 */
	public function test_overlapping_params_and_data() {
		$params = [ 'foo' => 'bar', 'shared' => 'p' ];
		$data = [ 'baz' => 'qux', 'shared' => 'd' ];

		// Create a request with overlapping keys
		$request = $this->get_test_request([
			'params' => $params,
			'data' => $data,
		]);

		// Assert each value is correct for its array
		$this->assertEquals('p', $request->get_params()['shared']);
		$this->assertEquals('d', $request->get_data()['shared']);
	}

	/**
	 * Test that the Request interface is not instantiable.
	 */
	public function test_request_interface_is_not_instantiable() {
		$reflection = new \ReflectionClass(\WooCommerce\Facebook\Framework\Api\Request::class);
        
		$this->assertTrue($reflection->isInterface());
		$this->assertFalse($reflection->isInstantiable());
	}
} 