<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Log\Create;

use WooCommerce\Facebook\API\Log\Create\Request;
use WooCommerce\Facebook\API\Request as ApiRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Log\Create\Request class.
 *
 * @since x.x.x
 */
class CreateLogRequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that Request class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Request::class ) );
	}

	/**
	 * Test that Request extends ApiRequest.
	 */
	public function test_extends_api_request() {
		$request = new Request( '123456', 'Test message', 'Test error' );
		$this->assertInstanceOf( ApiRequest::class, $request );
		$this->assertInstanceOf( Request::class, $request );
	}

	/**
	 * Test constructor with valid parameters.
	 */
	public function test_constructor_with_valid_parameters() {
		$merchant_id = '123456789';
		$message = 'Test log message';
		$error = 'Test error details';
		
		$request = new Request( $merchant_id, $message, $error );
		
		// Test that the path is set correctly
		$this->assertEquals( "/{$merchant_id}/log_events", $request->get_path() );
		
		// Test that the method is POST
		$this->assertEquals( 'POST', $request->get_method() );
		
		// Test that data is set correctly
		$data = $request->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( $message, $data['message'] );
		$this->assertEquals( $error, $data['error'] );
	}

	/**
	 * Test constructor with empty merchant ID.
	 */
	public function test_constructor_with_empty_merchant_id() {
		$request = new Request( '', 'Message', 'Error' );
		
		// Path should still be constructed, just with empty ID
		$this->assertEquals( '//log_events', $request->get_path() );
	}

	/**
	 * Test constructor with special characters in parameters.
	 */
	public function test_constructor_with_special_characters() {
		$merchant_id = 'merchant-123_456';
		$message = 'Message with "quotes" and \'apostrophes\'';
		$error = 'Error: Something went wrong! @#$%';
		
		$request = new Request( $merchant_id, $message, $error );
		
		$this->assertEquals( "/{$merchant_id}/log_events", $request->get_path() );
		
		$data = $request->get_data();
		$this->assertEquals( $message, $data['message'] );
		$this->assertEquals( $error, $data['error'] );
	}

	/**
	 * Test constructor with very long parameters.
	 */
	public function test_constructor_with_long_parameters() {
		$merchant_id = str_repeat( '1234567890', 10 ); // 100 characters
		$message = str_repeat( 'This is a very long message. ', 100 ); // ~3000 characters
		$error = str_repeat( 'Error details. ', 200 ); // ~3000 characters
		
		$request = new Request( $merchant_id, $message, $error );
		
		$data = $request->get_data();
		$this->assertEquals( $message, $data['message'] );
		$this->assertEquals( $error, $data['error'] );
		$this->assertEquals( "/{$merchant_id}/log_events", $request->get_path() );
	}

	/**
	 * Test constructor with empty message and error.
	 */
	public function test_constructor_with_empty_message_and_error() {
		$request = new Request( '123456', '', '' );
		
		$data = $request->get_data();
		$this->assertEquals( '', $data['message'] );
		$this->assertEquals( '', $data['error'] );
	}

	/**
	 * Test constructor with Unicode characters.
	 */
	public function test_constructor_with_unicode_characters() {
		$merchant_id = '123456';
		$message = 'Message with émojis 😀 and accénts';
		$error = 'Ошибка (Russian) 错误 (Chinese) エラー (Japanese)';
		
		$request = new Request( $merchant_id, $message, $error );
		
		$data = $request->get_data();
		$this->assertEquals( $message, $data['message'] );
		$this->assertEquals( $error, $data['error'] );
	}

	/**
	 * Test inherited retry functionality.
	 */
	public function test_retry_functionality() {
		$request = new Request( '123456', 'Message', 'Error' );
		
		// Test initial retry count
		$this->assertEquals( 0, $request->get_retry_count() );
		
		// Mark as retried
		$request->mark_retry();
		$this->assertEquals( 1, $request->get_retry_count() );
		
		// Mark as retried again
		$request->mark_retry();
		$this->assertEquals( 2, $request->get_retry_count() );
	}

	/**
	 * Test inherited retry limit.
	 */
	public function test_retry_limit() {
		$request = new Request( '123456', 'Message', 'Error' );
		
		// Default retry limit should be 5
		$this->assertEquals( 5, $request->get_retry_limit() );
	}

	/**
	 * Test retry limit filter.
	 */
	public function test_retry_limit_filter() {
		$request = new Request( '123456', 'Message', 'Error' );
		
		// Add filter to change retry limit
		add_filter( 'wc_facebook_api_request_retry_limit', function( $limit ) {
			return 10;
		} );
		
		$this->assertEquals( 10, $request->get_retry_limit() );
		
		// Clean up
		remove_all_filters( 'wc_facebook_api_request_retry_limit' );
	}

	/**
	 * Test inherited retry codes.
	 */
	public function test_retry_codes() {
		$request = new Request( '123456', 'Message', 'Error' );
		
		// Default should be empty array
		$this->assertIsArray( $request->get_retry_codes() );
		$this->assertEmpty( $request->get_retry_codes() );
	}

	/**
	 * Test base path override.
	 */
	public function test_base_path_override() {
		$request = new Request( '123456', 'Message', 'Error' );
		
		// Should return null by default
		$this->assertNull( $request->get_base_path_override() );
	}

	/**
	 * Test request specific headers.
	 */
	public function test_request_specific_headers() {
		$request = new Request( '123456', 'Message', 'Error' );
		
		// Should return empty array by default
		$headers = $request->get_request_specific_headers();
		$this->assertIsArray( $headers );
		$this->assertEmpty( $headers );
	}

	/**
	 * Test that data structure is correct.
	 */
	public function test_data_structure() {
		$request = new Request( '123456', 'Test message', 'Test error' );
		
		$data = $request->get_data();
		
		// Should only have message and error keys
		$this->assertCount( 2, $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayHasKey( 'error', $data );
		
		// Should not have any other keys
		$expected_keys = array( 'message', 'error' );
		$actual_keys = array_keys( $data );
		sort( $expected_keys );
		sort( $actual_keys );
		$this->assertEquals( $expected_keys, $actual_keys );
	}

	/**
	 * Test constructor parameter types.
	 */
	public function test_constructor_parameter_types() {
		// Test with numeric merchant ID
		$request = new Request( 123456, 'Message', 'Error' );
		$this->assertEquals( '/123456/log_events', $request->get_path() );
		
		// Test with numeric message and error (should be converted to string)
		$request2 = new Request( '123456', 123, 456 );
		$data = $request2->get_data();
		$this->assertSame( 123, $data['message'] );
		$this->assertSame( 456, $data['error'] );
	}

	/**
	 * Test multiple instances don't interfere with each other.
	 */
	public function test_multiple_instances() {
		$request1 = new Request( '111', 'Message 1', 'Error 1' );
		$request2 = new Request( '222', 'Message 2', 'Error 2' );
		
		// Verify they have different data
		$data1 = $request1->get_data();
		$data2 = $request2->get_data();
		
		$this->assertEquals( 'Message 1', $data1['message'] );
		$this->assertEquals( 'Message 2', $data2['message'] );
		$this->assertEquals( '/111/log_events', $request1->get_path() );
		$this->assertEquals( '/222/log_events', $request2->get_path() );
	}
} 