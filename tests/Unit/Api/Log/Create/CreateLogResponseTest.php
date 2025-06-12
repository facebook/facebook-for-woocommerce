<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Log\Create;

use WooCommerce\Facebook\API\Log\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Log\Create\Response class.
 *
 * @since x.x.x
 */
class CreateLogResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that Response class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that Response extends ApiResponse.
	 */
	public function test_extends_api_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
		$this->assertInstanceOf( Response::class, $response );
	}

	/**
	 * Test response with valid JSON data.
	 */
	public function test_response_with_valid_json() {
		$json_data = json_encode( array(
			'id' => '123456',
			'success' => true,
		) );
		
		$response = new Response( $json_data );
		
		// Test inherited get_id() method
		$this->assertEquals( '123456', $response->get_id() );
		
		// Test that response data is accessible
		$this->assertTrue( $response->success );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error() {
		$json_data = json_encode( array(
			'error' => array(
				'type' => 'OAuthException',
				'message' => 'Invalid access token',
				'code' => 190,
				'error_user_msg' => 'Please re-authenticate',
			),
		) );
		
		$response = new Response( $json_data );
		
		// Test inherited error methods
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 'Invalid access token', $response->get_api_error_message() );
		$this->assertEquals( 190, $response->get_api_error_code() );
		$this->assertEquals( 'Please re-authenticate', $response->get_user_error_message() );
	}

	/**
	 * Test response without error.
	 */
	public function test_response_without_error() {
		$json_data = json_encode( array(
			'id' => '789',
			'data' => 'test',
		) );
		
		$response = new Response( $json_data );
		
		$this->assertFalse( $response->has_api_error() );
		$this->assertNull( $response->get_api_error_type() );
		$this->assertNull( $response->get_api_error_message() );
		$this->assertNull( $response->get_api_error_code() );
		$this->assertNull( $response->get_user_error_message() );
	}

	/**
	 * Test response with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
	}



	/**
	 * Test response with malformed JSON.
	 */
	public function test_response_with_malformed_json() {
		$response = new Response( 'not valid json' );
		
		// The parent class should handle malformed JSON gracefully
		$this->assertNull( $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with nested data structure.
	 */
	public function test_response_with_nested_data() {
		$json_data = json_encode( array(
			'id' => '999',
			'data' => array(
				'nested' => array(
					'value' => 'test',
				),
			),
		) );
		
		$response = new Response( $json_data );
		
		$this->assertEquals( '999', $response->get_id() );
		$this->assertEquals( 'test', $response->data['nested']['value'] );
	}

	/**
	 * Test that response class has no additional methods beyond parent.
	 */
	public function test_class_has_no_additional_public_methods() {
		$response_reflection = new \ReflectionClass( Response::class );
		$parent_reflection = new \ReflectionClass( ApiResponse::class );
		
		$response_methods = $response_reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		$parent_methods = $parent_reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		
		// Get method names
		$response_method_names = array_map( function( $method ) {
			return $method->getName();
		}, $response_methods );
		
		$parent_method_names = array_map( function( $method ) {
			return $method->getName();
		}, $parent_methods );
		
		// Response should not add any new public methods
		$new_methods = array_diff( $response_method_names, $parent_method_names );
		$this->assertEmpty( $new_methods, 'Response class should not add new public methods' );
	}
} 