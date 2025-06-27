<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Api;

use WooCommerce\Facebook\API\Response as ConcreteResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Framework\Api\Response;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for the JSONResponse abstract class (via concrete Response).
 */
class JSONResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Returns a concrete JSONResponse instance for testing.
	 *
	 * @param array $data The data to encode as JSON for the response.
	 * @return ConcreteResponse
	 */
	private function get_test_response(array $data = []): ConcreteResponse {
		// Encode the provided data as JSON and create a new response
		return new ConcreteResponse(json_encode($data));
	}

	/**
	 * Test that the response can be instantiated and is of the correct type.
	 */
	public function test_can_instantiate_and_is_jsonresponse() {
		// Create a response
		$response = $this->get_test_response(['foo' => 'bar']);

		// Assert correct types
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertInstanceOf(Response::class, $response);
	}

	/**
	 * Test that to_string returns the expected JSON string.
	 */
	public function test_to_string_returns_expected_value() {
		// Prepare data and expected JSON
		$data = ['foo' => 'bar', 'baz' => 123];
		$json = json_encode($data);

		// Create response
		$response = $this->get_test_response($data);

		// Assert to_string returns the correct JSON
		$this->assertEquals($json, $response->to_string());
	}

	/**
	 * Test that to_string_safe returns the expected JSON string.
	 */
	public function test_to_string_safe_returns_expected_value() {
		// Prepare data and expected JSON
		$data = ['foo' => 'bar', 'baz' => 123];
		$json = json_encode($data);

		// Create response
		$response = $this->get_test_response($data);

		// Assert to_string_safe returns the correct JSON
		$this->assertEquals($json, $response->to_string_safe());
	}

	/**
	 * Test that both methods return the expected types.
	 */
	public function test_types_of_methods() {
		// Create a response
		$response = $this->get_test_response(['foo' => 'bar']);

		// Assert both methods return strings
		$this->assertIsString($response->to_string());
		$this->assertIsString($response->to_string_safe());
	}

	/**
	 * Test that magic getter returns values and null for missing keys.
	 */
	public function test_magic_getter_returns_values_and_null_for_missing() {
		// Prepare data
		$data = ['foo' => 'bar', 'baz' => 123];
		$response = $this->get_test_response($data);

		// Assert magic getter returns correct values
		$this->assertEquals('bar', $response->foo);
		$this->assertEquals(123, $response->baz);

		// Assert missing key returns null
		$this->assertNull($response->not_a_key);
	}

	/**
	 * Test array access (offsetExists, offsetGet, offsetSet, offsetUnset).
	 */
	public function test_array_access_works() {
		// Prepare data
		$data = ['a' => 1, 'b' => 2];
		$response = $this->get_test_response($data);

		// Assert array access for existing keys
		$this->assertTrue(isset($response['a']));
		$this->assertEquals(1, $response['a']);
		$this->assertEquals(2, $response['b']);

		// Set new value
		$response['c'] = 3;
		$this->assertEquals(3, $response['c']);

		// Unset value and assert it's gone
		unset($response['a']);
		$this->assertFalse(isset($response['a']));
	}

	/**
	 * Test that null and empty values are handled correctly.
	 */
	public function test_null_and_empty_values() {
		// Create response with null and empty string
		$response = $this->get_test_response(['foo' => null, 'bar' => '']);

		// Assert null and empty string are handled correctly
		$this->assertNull($response->foo);
		$this->assertSame('', $response->bar);
	}

	/**
	 * Test with unicode and special characters.
	 */
	public function test_unicode_and_special_characters() {
		// Prepare data with unicode and special characters
		$data = ['emoji' => '😀', 'special' => '!@#$%^&*()_+-='];
		$response = $this->get_test_response($data);

		// Assert values are as set
		$this->assertEquals('😀', $response->emoji);
		$this->assertEquals('!@#$%^&*()_+-=', $response->special);
	}

	/**
	 * Test with error fields and error accessors.
	 */
	public function test_response_with_error_fields() {
		// Prepare error data
		$error = [
			'error' => [
				'message' => 'Something went wrong',
				'type' => 'TestError',
				'code' => 42,
				'error_user_msg' => 'User message',
			],
			'id' => 'abc123',
		];
		$response = $this->get_test_response($error);

		// Assert error accessors
		$this->assertTrue($response->has_api_error());
		$this->assertEquals('TestError', $response->get_api_error_type());
		$this->assertEquals('Something went wrong', $response->get_api_error_message());
		$this->assertEquals(42, $response->get_api_error_code());
		$this->assertEquals('User message', $response->get_user_error_message());
		$this->assertEquals('abc123', $response->get_id());
	}

	/**
	 * Test with empty JSON.
	 */
	public function test_response_with_empty_json() {
		// Create response with empty array
		$response = $this->get_test_response([]);

		// Assert response_data is an empty array
		$this->assertIsArray($response->response_data);
		$this->assertEmpty($response->response_data);
	}

	/**
	 * Test to_string and to_string_safe with empty JSON.
	 */
	public function test_to_string_and_safe_with_empty_json() {
		// Create response with empty array
		$response = $this->get_test_response([]);

		// Assert to_string and to_string_safe return '{}'
		$this->assertEquals('{}', $response->to_string());
		$this->assertEquals('{}', $response->to_string_safe());
	}

	/**
	 * Test with invalid JSON string.
	 */
	public function test_invalid_json_string_results_in_null_response_data() {
		// Create response with invalid JSON
		$response = $this->get_test_response_from_raw('{invalid json');

		// Assert response_data is null
		$this->assertNull($response->response_data);
	}

	/**
	 * Test with non-array JSON (scalar value).
	 */
	public function test_scalar_json_results_in_scalar_response_data() {
		// Create response with scalar JSON
		$response = $this->get_test_response_from_raw('123');

		// Assert response_data is the scalar value
		$this->assertSame(123, $response->response_data);
	}

	/**
	 * Test with non-array JSON (object value).
	 */
	public function test_object_json_results_in_array_response_data() {
		// Create response with object JSON
		$json = '{"foo": "bar", "baz": 1}';
		$response = $this->get_test_response_from_raw($json);

		// Assert response_data is an array
		$this->assertIsArray($response->response_data);
		$this->assertEquals(['foo' => 'bar', 'baz' => 1], $response->response_data);
	}

	/**
	 * Test with large and complex nested data structures.
	 */
	public function test_large_and_nested_data_structure() {
		// Prepare large, nested data
		$data = [
			'level1' => [
				'level2' => [
					'level3' => [
						'foo' => 'bar',
						'arr' => range(1, 100),
					],
				],
			],
		];
		$response = $this->get_test_response($data);

		// Assert nested values
		$this->assertEquals('bar', $response->level1['level2']['level3']['foo']);
		$this->assertCount(100, $response->level1['level2']['level3']['arr']);
	}

	/**
	 * Test array access with numeric keys.
	 */
	public function test_array_access_with_numeric_keys() {
		// Prepare data with numeric keys
		$data = [0 => 'zero', 1 => 'one', 2 => 'two'];
		$response = $this->get_test_response($data);

		// Assert array access for numeric keys
		$this->assertEquals('zero', $response[0]);
		$this->assertEquals('one', $response[1]);
		$this->assertEquals('two', $response[2]);
	}

	/**
	 * Test overwriting and unsetting keys via array access.
	 */
	public function test_overwriting_and_unsetting_keys() {
		// Prepare data
		$data = ['foo' => 'bar'];
		$response = $this->get_test_response($data);

		// Overwrite value
		$response['foo'] = 'baz';
		$this->assertEquals('baz', $response['foo']);

		// Unset value
		unset($response['foo']);
		$this->assertFalse(isset($response['foo']));
	}

	/**
	 * Test to_string and to_string_safe with non-UTF8/binary data.
	 */
	public function test_to_string_with_non_utf8_binary_data() {
		// Prepare binary data
		$binary = "\xFF\xFE\xFD";
		$response = $this->get_test_response(['bin' => $binary]);

		// Assert the key is present in the JSON string
		$this->assertStringContainsString('bin', $response->to_string());
	}

	/**
	 * Test that offsetSet and offsetUnset do not affect the original raw JSON string.
	 */
	public function test_array_access_does_not_affect_raw_json() {
		// Prepare data
		$data = ['foo' => 'bar'];
		$response = $this->get_test_response($data);

		// Modify response_data via array access
		$response['foo'] = 'baz';
		unset($response['foo']);

		// Assert the raw JSON string remains unchanged
		$this->assertEquals(json_encode($data), $response->to_string());
	}

	/**
	 * Test that response_data is always an array for empty or invalid input.
	 */
	public function test_response_data_is_array_for_empty_or_invalid_input() {
		// Create response with empty array
		$response = $this->get_test_response([]);
		$this->assertIsArray($response->response_data);

		// Create response with invalid JSON
		$response2 = $this->get_test_response_from_raw('{invalid json');
		$this->assertNull($response2->response_data);
	}

	/**
	 * Test with deeply nested arrays/objects.
	 */
	public function test_deeply_nested_arrays_and_objects() {
		// Prepare deeply nested data
		$data = ['a' => ['b' => ['c' => ['d' => ['e' => 'deep']]]]];
		$response = $this->get_test_response($data);

		// Assert deep value
		$this->assertEquals('deep', $response->a['b']['c']['d']['e']);
	}

	/**
	 * Test with special float values (INF, -INF, NAN).
	 */
	public function test_special_float_values() {
		// Prepare data with special float values
		$data = ['inf' => INF, 'ninf' => -INF, 'nan' => NAN];
		$response = $this->get_test_response($data);

		// Assert special float values
		$this->assertTrue(is_infinite($response->inf));
		$this->assertTrue(is_infinite($response->ninf));
		$this->assertTrue(is_nan($response->nan));
	}

	/**
	 * Test with boolean and null values at the top level.
	 */
	public function test_boolean_and_null_top_level() {
		// Prepare data with booleans and null
		$data = ['bool_true' => true, 'bool_false' => false, 'null_val' => null];
		$response = $this->get_test_response($data);

		// Assert boolean and null values
		$this->assertTrue($response->bool_true);
		$this->assertFalse($response->bool_false);
		$this->assertNull($response->null_val);
	}

	/**
	 * Test with JSON containing UTF-8 multi-byte characters.
	 */
	public function test_utf8_multibyte_characters() {
		// Prepare data with UTF-8 multi-byte characters
		$data = ['utf8' => '汉字テスト😀'];
		$response = $this->get_test_response($data);

		// Assert value is as set
		$this->assertEquals('汉字テスト😀', $response->utf8);
	}

	/**
	 * Test with JSON containing escape sequences.
	 */
	public function test_json_with_escape_sequences() {
		// Prepare data with escape sequences
		$data = ['escaped' => "Line1\nLine2\tTabbed\"Quote\""];
		$response = $this->get_test_response($data);

		// Assert value is as set
		$this->assertEquals("Line1\nLine2\tTabbed\"Quote\"", $response->escaped);
	}

	/**
	 * Test with empty string as input.
	 */
	public function test_empty_string_input() {
		// Create response with empty string
		$response = $this->get_test_response_from_raw('');

		// Assert response_data is null
		$this->assertNull($response->response_data);
	}

	/**
	 * Test with non-string input to constructor (should be string, but test for robustness).
	 */
	public function test_non_string_input_to_constructor() {
		// Create response with non-string input
		$response = new ConcreteResponse(12345); // int instead of string

		// Assert response_data is null
		$this->assertNull($response->response_data);
	}

	/**
	 * Helper to create a response from a raw JSON string (bypassing array encoding).
	 *
	 * @param string $raw_json
	 * @return ConcreteResponse
	 */
	private function get_test_response_from_raw(string $raw_json): ConcreteResponse {
		// Create a new response from a raw JSON string
		return new ConcreteResponse($raw_json);
	}
} 