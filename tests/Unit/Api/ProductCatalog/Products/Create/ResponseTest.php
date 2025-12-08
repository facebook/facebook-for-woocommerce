<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\Products\Create;

use WooCommerce\Facebook\API\ProductCatalog\Products\Create\Response;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Product Catalog > Products > Create Response.
 *
 * @covers \WooCommerce\Facebook\API\ProductCatalog\Products\Create\Response
 */
class ResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that response can access id property via magic getter.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_can_access_id_property() {
		$json     = '{"id":"facebook-product-id"}';
		$response = new Response( $json );

		$this->assertEquals( 'facebook-product-id', $response->id );
	}

	/**
	 * Test that response can be instantiated with valid JSON.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 */
	public function test_response_can_be_instantiated_with_valid_json() {
		$json     = '{"id":"test-id","name":"Test Product"}';
		$response = new Response( $json );

		$this->assertInstanceOf( Response::class, $response );
		$this->assertIsArray( $response->response_data );
		$this->assertEquals( 'test-id', $response->id );
		$this->assertEquals( 'Test Product', $response->name );
	}

	/**
	 * Test that response handles empty JSON.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 */
	public function test_response_handles_empty_json() {
		$json     = '{}';
		$response = new Response( $json );

		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
		$this->assertNull( $response->id );
	}

	/**
	 * Test that response handles invalid JSON.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 */
	public function test_response_handles_invalid_json() {
		$json     = '{invalid json}';
		$response = new Response( $json );

		$this->assertNull( $response->response_data );
	}

	/**
	 * Test that magic getter returns null for missing properties.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_magic_getter_returns_null_for_missing_properties() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$this->assertNull( $response->nonexistent_property );
		$this->assertNull( $response->another_missing_field );
	}

	/**
	 * Test that magic getter handles nested data.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_magic_getter_handles_nested_data() {
		$json     = '{"id":"test-id","metadata":{"key":"value","count":42}}';
		$response = new Response( $json );

		$this->assertIsArray( $response->metadata );
		$this->assertEquals( 'value', $response->metadata['key'] );
		$this->assertEquals( 42, $response->metadata['count'] );
	}

	/**
	 * Test array access offsetExists method.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::offsetExists
	 */
	public function test_offset_exists() {
		$json     = '{"id":"test-id","name":"Test"}';
		$response = new Response( $json );

		$this->assertTrue( isset( $response['id'] ) );
		$this->assertTrue( isset( $response['name'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test array access offsetGet method.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::offsetGet
	 */
	public function test_offset_get() {
		$json     = '{"id":"test-id","price":99.99}';
		$response = new Response( $json );

		$this->assertEquals( 'test-id', $response['id'] );
		$this->assertEquals( 99.99, $response['price'] );
	}

	/**
	 * Test array access offsetSet method.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::offsetSet
	 */
	public function test_offset_set() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$response['new_field'] = 'new_value';
		$this->assertEquals( 'new_value', $response['new_field'] );

		$response['id'] = 'updated-id';
		$this->assertEquals( 'updated-id', $response['id'] );
	}

	/**
	 * Test array access offsetUnset method.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::offsetUnset
	 */
	public function test_offset_unset() {
		$json     = '{"id":"test-id","name":"Test"}';
		$response = new Response( $json );

		$this->assertTrue( isset( $response['name'] ) );
		unset( $response['name'] );
		$this->assertFalse( isset( $response['name'] ) );
	}

	/**
	 * Test to_string method returns raw JSON.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::to_string
	 */
	public function test_to_string_returns_raw_json() {
		$json     = '{"id":"test-id","name":"Test Product"}';
		$response = new Response( $json );

		$this->assertEquals( $json, $response->to_string() );
		$this->assertIsString( $response->to_string() );
	}

	/**
	 * Test to_string_safe method returns raw JSON.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::to_string_safe
	 */
	public function test_to_string_safe_returns_raw_json() {
		$json     = '{"id":"test-id","name":"Test Product"}';
		$response = new Response( $json );

		$this->assertEquals( $json, $response->to_string_safe() );
		$this->assertIsString( $response->to_string_safe() );
	}

	/**
	 * Test get_id method returns the id property.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_id
	 */
	public function test_get_id_returns_id_property() {
		$json     = '{"id":"facebook-product-123"}';
		$response = new Response( $json );

		$this->assertEquals( 'facebook-product-123', $response->get_id() );
	}

	/**
	 * Test get_id returns null when id is missing.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_id
	 */
	public function test_get_id_returns_null_when_missing() {
		$json     = '{"name":"Test Product"}';
		$response = new Response( $json );

		$this->assertNull( $response->get_id() );
	}

	/**
	 * Test has_api_error returns false when no error.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::has_api_error
	 */
	public function test_has_api_error_returns_false_when_no_error() {
		$json     = '{"id":"test-id","success":true}';
		$response = new Response( $json );

		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test has_api_error returns true when error exists.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::has_api_error
	 */
	public function test_has_api_error_returns_true_when_error_exists() {
		$json     = '{"error":{"message":"Something went wrong","type":"OAuthException","code":190}}';
		$response = new Response( $json );

		$this->assertTrue( $response->has_api_error() );
	}

	/**
	 * Test get_api_error_type returns error type.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_type
	 */
	public function test_get_api_error_type_returns_error_type() {
		$json     = '{"error":{"message":"Invalid token","type":"OAuthException","code":190}}';
		$response = new Response( $json );

		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
	}

	/**
	 * Test get_api_error_type returns null when no error.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_type
	 */
	public function test_get_api_error_type_returns_null_when_no_error() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$this->assertNull( $response->get_api_error_type() );
	}

	/**
	 * Test get_api_error_message returns error message.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_message
	 */
	public function test_get_api_error_message_returns_error_message() {
		$json     = '{"error":{"message":"Invalid OAuth access token","type":"OAuthException","code":190}}';
		$response = new Response( $json );

		$this->assertEquals( 'Invalid OAuth access token', $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_message returns null when no error.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_message
	 */
	public function test_get_api_error_message_returns_null_when_no_error() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$this->assertNull( $response->get_api_error_message() );
	}

	/**
	 * Test get_api_error_code returns error code.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_code
	 */
	public function test_get_api_error_code_returns_error_code() {
		$json     = '{"error":{"message":"Invalid token","type":"OAuthException","code":190}}';
		$response = new Response( $json );

		$this->assertEquals( 190, $response->get_api_error_code() );
	}

	/**
	 * Test get_api_error_code returns null when no error.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_code
	 */
	public function test_get_api_error_code_returns_null_when_no_error() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$this->assertNull( $response->get_api_error_code() );
	}

	/**
	 * Test get_user_error_message returns user error message.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_user_error_message
	 */
	public function test_get_user_error_message_returns_user_message() {
		$json     = '{"error":{"message":"Technical error","type":"OAuthException","code":190,"error_user_msg":"Please reconnect your account"}}';
		$response = new Response( $json );

		$this->assertEquals( 'Please reconnect your account', $response->get_user_error_message() );
	}

	/**
	 * Test get_user_error_message returns null when not present.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::get_user_error_message
	 */
	public function test_get_user_error_message_returns_null_when_not_present() {
		$json     = '{"error":{"message":"Technical error","type":"OAuthException","code":190}}';
		$response = new Response( $json );

		$this->assertNull( $response->get_user_error_message() );
	}

	/**
	 * Test get_rate_limit_usage with valid headers.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_usage
	 */
	public function test_get_rate_limit_usage_with_valid_headers() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-Business-Use-Case-Usage' => array(
				'call_count'   => 75,
				'total_time'   => 50,
				'total_cputime' => 40,
			),
		);

		$this->assertEquals( 75, $response->get_rate_limit_usage( $headers ) );
	}

	/**
	 * Test get_rate_limit_usage with missing call_count.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_usage
	 */
	public function test_get_rate_limit_usage_with_missing_call_count() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-Business-Use-Case-Usage' => array(
				'total_time' => 50,
			),
		);

		$this->assertEquals( 0, $response->get_rate_limit_usage( $headers ) );
	}

	/**
	 * Test get_rate_limit_usage with no headers.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_usage
	 */
	public function test_get_rate_limit_usage_with_no_headers() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$this->assertEquals( 0, $response->get_rate_limit_usage( array() ) );
	}

	/**
	 * Test get_rate_limit_total_time with valid headers.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_total_time
	 */
	public function test_get_rate_limit_total_time_with_valid_headers() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-App-Usage' => array(
				'call_count' => 50,
				'total_time' => 85,
			),
		);

		$this->assertEquals( 85, $response->get_rate_limit_total_time( $headers ) );
	}

	/**
	 * Test get_rate_limit_total_time with missing total_time.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_total_time
	 */
	public function test_get_rate_limit_total_time_with_missing_total_time() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-App-Usage' => array(
				'call_count' => 50,
			),
		);

		$this->assertEquals( 0, $response->get_rate_limit_total_time( $headers ) );
	}

	/**
	 * Test get_rate_limit_total_cpu_time with valid headers.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_total_cpu_time
	 */
	public function test_get_rate_limit_total_cpu_time_with_valid_headers() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'x-business-use-case-usage' => array(
				'call_count'     => 60,
				'total_cputime'  => 92,
			),
		);

		$this->assertEquals( 92, $response->get_rate_limit_total_cpu_time( $headers ) );
	}

	/**
	 * Test get_rate_limit_total_cpu_time with missing total_cputime.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_total_cpu_time
	 */
	public function test_get_rate_limit_total_cpu_time_with_missing_total_cputime() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'x-app-usage' => array(
				'call_count' => 60,
				'total_time' => 45,
			),
		);

		$this->assertEquals( 0, $response->get_rate_limit_total_cpu_time( $headers ) );
	}

	/**
	 * Test get_rate_limit_estimated_time_to_regain_access with valid data.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_estimated_time_to_regain_access
	 */
	public function test_get_rate_limit_estimated_time_to_regain_access_with_valid_data() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-Business-Use-Case-Usage' => array(
				'call_count'                        => 100,
				'estimated_time_to_regain_access'   => 600,
			),
		);

		$this->assertEquals( 600, $response->get_rate_limit_estimated_time_to_regain_access( $headers ) );
	}

	/**
	 * Test get_rate_limit_estimated_time_to_regain_access with missing field.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_estimated_time_to_regain_access
	 */
	public function test_get_rate_limit_estimated_time_to_regain_access_with_missing_field() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-Business-Use-Case-Usage' => array(
				'call_count' => 100,
			),
		);

		$this->assertNull( $response->get_rate_limit_estimated_time_to_regain_access( $headers ) );
	}

	/**
	 * Test get_rate_limit_estimated_time_to_regain_access with zero value.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_estimated_time_to_regain_access
	 */
	public function test_get_rate_limit_estimated_time_to_regain_access_with_zero_value() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-Business-Use-Case-Usage' => array(
				'estimated_time_to_regain_access' => 0,
			),
		);

		$this->assertNull( $response->get_rate_limit_estimated_time_to_regain_access( $headers ) );
	}

	/**
	 * Test response with complex nested data structure.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_with_complex_nested_data() {
		$json     = '{"id":"test-id","product":{"name":"Test Product","variants":[{"id":"v1","price":10.99},{"id":"v2","price":15.99}]}}';
		$response = new Response( $json );

		$this->assertEquals( 'test-id', $response->id );
		$this->assertIsArray( $response->product );
		$this->assertEquals( 'Test Product', $response->product['name'] );
		$this->assertIsArray( $response->product['variants'] );
		$this->assertCount( 2, $response->product['variants'] );
		$this->assertEquals( 'v1', $response->product['variants'][0]['id'] );
		$this->assertEquals( 10.99, $response->product['variants'][0]['price'] );
	}

	/**
	 * Test response with null values.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_with_null_values() {
		$json     = '{"id":"test-id","name":null,"description":null}';
		$response = new Response( $json );

		$this->assertEquals( 'test-id', $response->id );
		$this->assertNull( $response->name );
		$this->assertNull( $response->description );
	}

	/**
	 * Test response with boolean values.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_with_boolean_values() {
		$json     = '{"id":"test-id","active":true,"deleted":false}';
		$response = new Response( $json );

		$this->assertTrue( $response->active );
		$this->assertFalse( $response->deleted );
	}

	/**
	 * Test response with numeric values.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_with_numeric_values() {
		$json     = '{"id":"test-id","count":42,"price":99.99,"discount":0}';
		$response = new Response( $json );

		$this->assertEquals( 42, $response->count );
		$this->assertEquals( 99.99, $response->price );
		$this->assertEquals( 0, $response->discount );
	}

	/**
	 * Test response with empty string values.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_with_empty_string_values() {
		$json     = '{"id":"test-id","name":"","description":""}';
		$response = new Response( $json );

		$this->assertSame( '', $response->name );
		$this->assertSame( '', $response->description );
	}

	/**
	 * Test response with unicode characters.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__construct
	 * @covers \WooCommerce\Facebook\Framework\Api\JSONResponse::__get
	 */
	public function test_response_with_unicode_characters() {
		$json     = '{"id":"test-id","name":"Test 😀 Product","description":"汉字テスト"}';
		$response = new Response( $json );

		$this->assertEquals( 'Test 😀 Product', $response->name );
		$this->assertEquals( '汉字テスト', $response->description );
	}

	/**
	 * Test complete error response structure.
	 *
	 * @covers \WooCommerce\Facebook\API\Response::has_api_error
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_type
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_message
	 * @covers \WooCommerce\Facebook\API\Response::get_api_error_code
	 * @covers \WooCommerce\Facebook\API\Response::get_user_error_message
	 */
	public function test_complete_error_response_structure() {
		$json     = '{"error":{"message":"Invalid OAuth 2.0 Access Token","type":"OAuthException","code":190,"error_subcode":463,"error_user_title":"Session Expired","error_user_msg":"Please log in again","fbtrace_id":"ABC123"}}';
		$response = new Response( $json );

		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 'Invalid OAuth 2.0 Access Token', $response->get_api_error_message() );
		$this->assertEquals( 190, $response->get_api_error_code() );
		$this->assertEquals( 'Please log in again', $response->get_user_error_message() );
	}

	/**
	 * Test rate limit headers with lowercase keys.
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_usage
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_total_time
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_total_cpu_time
	 */
	public function test_rate_limit_headers_with_lowercase_keys() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'x-business-use-case-usage' => array(
				'call_count'    => 65,
				'total_time'    => 55,
				'total_cputime' => 45,
			),
		);

		$this->assertEquals( 65, $response->get_rate_limit_usage( $headers ) );
		$this->assertEquals( 55, $response->get_rate_limit_total_time( $headers ) );
		$this->assertEquals( 45, $response->get_rate_limit_total_cpu_time( $headers ) );
	}

	/**
	 * Test rate limit headers priority (Business Use Case over App Usage).
	 *
	 * @covers \WooCommerce\Facebook\API\Traits\Rate_Limited_Response::get_rate_limit_usage
	 */
	public function test_rate_limit_headers_priority() {
		$json     = '{"id":"test-id"}';
		$response = new Response( $json );

		$headers = array(
			'X-Business-Use-Case-Usage' => array(
				'call_count' => 100,
			),
			'X-App-Usage' => array(
				'call_count' => 50,
			),
		);

		// Business Use Case header should take precedence
		$this->assertEquals( 100, $response->get_rate_limit_usage( $headers ) );
	}
}
