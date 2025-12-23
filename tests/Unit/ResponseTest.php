<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API;

use WooCommerce\Facebook\API\Response;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for API Response class.
 *
 * @since 2.0.0
 */
class ResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that Response extends JSONResponse.
	 */
	public function test_extends_json_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( JSONResponse::class, $response );
	}

	/**
	 * Test instantiation with valid JSON.
	 */
	public function test_instantiation_with_valid_json() {
		$data = json_encode( [ 'id' => '123456789' ] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test instantiation with empty JSON.
	 */
	public function test_instantiation_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( '{}', $response->to_string() );
	}

	/**
	 * Test get_id method with valid id.
	 */
	public function test_get_id_with_valid_id() {
		$id = '987654321';
		$data = json_encode( [ 'id' => $id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_with_missing_id() {
		$data = json_encode( [ 'name' => 'Test' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_id() );
	}

	/**
	 * Test get_id method with null id.
	 */
	public function test_get_id_with_null_id() {
		$data = json_encode( [ 'id' => null ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_id() );
	}

	/**
	 * Test get_id method with numeric id.
	 */
	public function test_get_id_with_numeric_id() {
		$data = json_encode( [ 'id' => 12345 ] );
		$response = new Response( $data );
		
		$this->assertEquals( 12345, $response->get_id() );
	}

	/**
	 * Test get_id method with very long id.
	 */
	public function test_get_id_with_very_long_id() {
		$long_id = '1234567890123456789012345678901234567890';
		$data = json_encode( [ 'id' => $long_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $long_id, $response->get_id() );
	}

	/**
	 * Test has_api_error method with error present.
	 */
	public function test_has_api_error_with_error_present() {
		$data = json_encode( [
			'error' => [
				'message' => 'An error occurred',
				'type' => 'OAuthException',
				'code' => 190,
			],
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->has_api_error() );
	}

	/**
	 * Test has_api_error method with no error.
	 */
	public function test_has_api_error_with_no_error() {
		$data = json_encode( [ 'id' => '123' ] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test has_api_error method with empty error.
	 */
	public function test_has_api_error_with_empty_error() {
		$data = json_encode( [ 'error' => [] ] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test has_api_error method with null error.
	 */
	public function test_has_api_error_with_null_error() {
		$data = json_encode( [ 'error' => null ] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test has_api_error method with empty string error.
	 */
	public function test_has_api_error_with_empty_string_error() {
		$data = json_encode( [ 'error' => '' ] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test get_api_error_type method with valid type.
	 */
	public function test_get_api_error_type_with_valid_type() {
		$data = json_encode( [
			'error' => [
				'type' => 'OAuthException',
				'message' => 'Invalid OAuth access token',
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
	}

	/**
	 * Test get_api_error_type method with missing type.
	 */
	public function test_get_api_error_type_with_missing_type() {
		$data = json_encode( [
			'error' => [
				'message' => 'An error occurred',
				'code' => 100,
			],
		] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_type() );
	}

	/**
	 * Test get_api_error_type method with null error.
	 */
	public function test_get_api_error_type_with_null_error() {
		$data = json_encode( [ 'id' => '123' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_type() );
	}

	/**
	 * Test get_api_error_type method with empty error array.
	 */
	public function test_get_api_error_type_with_empty_error_array() {
		$data = json_encode( [ 'error' => [] ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_type() );
	}

	/**
	 * Test get_api_error_message method with valid message.
	 */
	public function test_get_api_error_message_with_valid_message() {
		$error_message = 'Invalid OAuth access token';
		$data = json_encode( [
			'error' => [
				'message' => $error_message,
				'type' => 'OAuthException',
				'code' => 190,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $error_message, $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_message method with missing message.
	 */
	public function test_get_api_error_message_with_missing_message() {
		$data = json_encode( [
			'error' => [
				'type' => 'OAuthException',
				'code' => 190,
			],
		] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_message method with null error.
	 */
	public function test_get_api_error_message_with_null_error() {
		$data = json_encode( [ 'id' => '123' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_message method with special characters.
	 */
	public function test_get_api_error_message_with_special_characters() {
		$special_message = "Error: O'Brien & Co. <Test> \"Quotes\" 'Apostrophes'";
		$data = json_encode( [
			'error' => [
				'message' => $special_message,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $special_message, $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_message method with Unicode characters.
	 */
	public function test_get_api_error_message_with_unicode_characters() {
		$unicode_message = '错误: 无效的访问令牌 🚫';
		$data = json_encode( [
			'error' => [
				'message' => $unicode_message,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $unicode_message, $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_code method with valid code.
	 */
	public function test_get_api_error_code_with_valid_code() {
		$data = json_encode( [
			'error' => [
				'message' => 'Invalid OAuth access token',
				'type' => 'OAuthException',
				'code' => 190,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 190, $response->get_api_error_code() );
	}

	/**
	 * Test get_api_error_code method with missing code.
	 */
	public function test_get_api_error_code_with_missing_code() {
		$data = json_encode( [
			'error' => [
				'message' => 'An error occurred',
				'type' => 'Exception',
			],
		] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_code() );
	}

	/**
	 * Test get_api_error_code method with null error.
	 */
	public function test_get_api_error_code_with_null_error() {
		$data = json_encode( [ 'id' => '123' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_api_error_code() );
	}

	/**
	 * Test get_api_error_code method with numeric code.
	 */
	public function test_get_api_error_code_with_numeric_code() {
		$data = json_encode( [
			'error' => [
				'code' => 100,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertIsInt( $response->get_api_error_code() );
	}

	/**
	 * Test get_api_error_code method with string code.
	 */
	public function test_get_api_error_code_with_string_code() {
		$data = json_encode( [
			'error' => [
				'code' => '190',
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '190', $response->get_api_error_code() );
	}

	/**
	 * Test get_user_error_message method with valid message.
	 */
	public function test_get_user_error_message_with_valid_message() {
		$user_message = 'Please log in again to continue';
		$data = json_encode( [
			'error' => [
				'message' => 'Invalid OAuth access token',
				'type' => 'OAuthException',
				'code' => 190,
				'error_user_msg' => $user_message,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $user_message, $response->get_user_error_message() );
	}

	/**
	 * Test get_user_error_message method with missing message.
	 */
	public function test_get_user_error_message_with_missing_message() {
		$data = json_encode( [
			'error' => [
				'message' => 'An error occurred',
				'type' => 'Exception',
				'code' => 100,
			],
		] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_user_error_message() );
	}

	/**
	 * Test get_user_error_message method with null error.
	 */
	public function test_get_user_error_message_with_null_error() {
		$data = json_encode( [ 'id' => '123' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_user_error_message() );
	}

	/**
	 * Test get_user_error_message method with empty string.
	 */
	public function test_get_user_error_message_with_empty_string() {
		$data = json_encode( [
			'error' => [
				'error_user_msg' => '',
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '', $response->get_user_error_message() );
	}

	/**
	 * Test complex error structure with all fields.
	 */
	public function test_complex_error_structure_with_all_fields() {
		$data = json_encode( [
			'error' => [
				'message' => 'Invalid OAuth access token',
				'type' => 'OAuthException',
				'code' => 190,
				'error_subcode' => 463,
				'error_user_title' => 'Session Expired',
				'error_user_msg' => 'Please log in again to continue',
				'fbtrace_id' => 'ABC123XYZ',
			],
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 'Invalid OAuth access token', $response->get_api_error_message() );
		$this->assertEquals( 190, $response->get_api_error_code() );
		$this->assertEquals( 'Please log in again to continue', $response->get_user_error_message() );
	}

	/**
	 * Test accessing error property via magic getter.
	 */
	public function test_error_property_via_magic_getter() {
		$error_data = [
			'message' => 'Test error',
			'type' => 'TestException',
			'code' => 999,
		];
		$data = json_encode( [ 'error' => $error_data ] );
		$response = new Response( $data );
		
		$this->assertEquals( $error_data, $response->error );
		$this->assertIsArray( $response->error );
	}

	/**
	 * Test ArrayAccess interface with error data.
	 */
	public function test_array_access_interface_with_error() {
		$error_data = [
			'message' => 'Test error',
			'type' => 'TestException',
			'code' => 999,
		];
		$data = json_encode( [ 'error' => $error_data ] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertEquals( $error_data, $response['error'] );
		
		// Test isset
		$this->assertTrue( isset( $response['error'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test ArrayAccess interface with id.
	 */
	public function test_array_access_interface_with_id() {
		$id = '123456789';
		$data = json_encode( [ 'id' => $id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $id, $response['id'] );
		$this->assertTrue( isset( $response['id'] ) );
	}

	/**
	 * Test response with both id and error.
	 */
	public function test_response_with_both_id_and_error() {
		$data = json_encode( [
			'id' => '123456789',
			'error' => [
				'message' => 'Partial error',
				'type' => 'PartialException',
				'code' => 500,
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '123456789', $response->get_id() );
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'PartialException', $response->get_api_error_type() );
	}

	/**
	 * Test response with nested error data.
	 */
	public function test_response_with_nested_error_data() {
		$data = json_encode( [
			'error' => [
				'message' => 'Validation error',
				'type' => 'ValidationException',
				'code' => 400,
				'error_data' => [
					'field' => 'email',
					'reason' => 'invalid_format',
				],
			],
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'ValidationException', $response->get_api_error_type() );
		$this->assertIsArray( $response->error );
		$this->assertArrayHasKey( 'error_data', $response->error );
	}

	/**
	 * Test response with multiple top-level properties.
	 */
	public function test_response_with_multiple_properties() {
		$data = json_encode( [
			'id' => '987654321',
			'name' => 'Test Response',
			'status' => 'success',
			'data' => [
				'key1' => 'value1',
				'key2' => 'value2',
			],
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '987654321', $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
		$this->assertEquals( 'Test Response', $response->name );
		$this->assertEquals( 'success', $response->status );
		$this->assertIsArray( $response->data );
	}

	/**
	 * Test to_string method.
	 */
	public function test_to_string_method() {
		$json = '{"id":"123","error":{"message":"Test"}}';
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
	}

	/**
	 * Test to_string_safe method.
	 */
	public function test_to_string_safe_method() {
		$json = '{"id":"123","error":{"message":"Test"}}';
		$response = new Response( $json );
		
		// to_string_safe should return the same as to_string for this class
		$this->assertEquals( $json, $response->to_string_safe() );
		$this->assertEquals( $response->to_string(), $response->to_string_safe() );
	}

	/**
	 * Test with malformed JSON (should still construct but have null data).
	 */
	public function test_with_malformed_json() {
		$response = new Response( '{invalid json}' );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertNull( $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test error with zero code.
	 */
	public function test_error_with_zero_code() {
		$data = json_encode( [
			'error' => [
				'message' => 'Error with zero code',
				'code' => 0,
			],
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 0, $response->get_api_error_code() );
	}

	/**
	 * Test error with negative code.
	 */
	public function test_error_with_negative_code() {
		$data = json_encode( [
			'error' => [
				'message' => 'Error with negative code',
				'code' => -1,
			],
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( -1, $response->get_api_error_code() );
	}

	/**
	 * Test that Rate_Limited_Response trait methods are available.
	 */
	public function test_rate_limited_response_trait_methods_exist() {
		$response = new Response( '{}' );
		
		$this->assertTrue( method_exists( $response, 'get_rate_limit_usage' ) );
		$this->assertTrue( method_exists( $response, 'get_rate_limit_total_time' ) );
		$this->assertTrue( method_exists( $response, 'get_rate_limit_total_cpu_time' ) );
		$this->assertTrue( method_exists( $response, 'get_rate_limit_estimated_time_to_regain_access' ) );
	}

	/**
	 * Test accessing non-existent property returns null.
	 */
	public function test_accessing_nonexistent_property_returns_null() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->nonexistent_property );
		$this->assertNull( $response->another_missing_field );
	}

	/**
	 * Test with empty error object evaluates to false.
	 */
	public function test_empty_error_object_evaluates_to_false() {
		$data = json_encode( [ 'error' => (object) [] ] );
		$response = new Response( $data );
		
		// Empty object should be truthy in PHP, but empty array is falsy
		// JSON decode with true parameter converts to array
		$this->assertFalse( $response->has_api_error() );
	}
}
