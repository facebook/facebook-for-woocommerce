<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\User\Permissions\Delete;

use WooCommerce\Facebook\API\User\Permissions\Delete\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for User Permissions Delete Response class.
 *
 * @since x.x.x
 */
class PermissionsDeleteResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Response class exists and can be instantiated.
	 */
	public function test_response_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that Response extends the API Response class.
	 */
	public function test_response_extends_api_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test that Response extends JSONResponse through inheritance.
	 */
	public function test_response_extends_json_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( JSONResponse::class, $response );
	}

	/**
	 * Test response instantiation with success response.
	 */
	public function test_response_with_success() {
		$json = json_encode( [ 'success' => true ] );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with user permission deletion data.
	 */
	public function test_response_with_permission_deletion_data() {
		$data = [
			'success' => true,
			'id' => 'user_123',
			'permissions_removed' => [ 'manage_pages', 'ads_management' ],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'user_123', $response->id );
		$this->assertEquals( 'user_123', $response->get_id() );
		$this->assertIsArray( $response->permissions_removed );
		$this->assertCount( 2, $response->permissions_removed );
		$this->assertContains( 'manage_pages', $response->permissions_removed );
		$this->assertContains( 'ads_management', $response->permissions_removed );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Invalid user ID',
				'type' => 'OAuthException',
				'code' => 190,
				'error_user_msg' => 'The user permissions could not be deleted.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid user ID', $response->get_api_error_message() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 190, $response->get_api_error_code() );
		$this->assertEquals( 'The user permissions could not be deleted.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'success' => true,
			'id' => 'user_456',
			'timestamp' => '2023-01-01T00:00:00Z',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['success'] ) );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'user_456', $response['id'] );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response['timestamp'] );
		
		// Test setting values
		$response['new_field'] = 'new_value';
		$this->assertEquals( 'new_value', $response['new_field'] );
		
		// Test unsetting values
		unset( $response['timestamp'] );
		$this->assertFalse( isset( $response['timestamp'] ) );
	}

	/**
	 * Test response with partial permission removal.
	 */
	public function test_response_with_partial_permission_removal() {
		$data = [
			'success' => true,
			'partial_success' => true,
			'permissions_removed' => [ 'manage_pages' ],
			'permissions_failed' => [ 'ads_management' ],
			'warnings' => [
				'Some permissions could not be removed due to dependencies',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertTrue( $response->partial_success );
		$this->assertIsArray( $response->permissions_removed );
		$this->assertContains( 'manage_pages', $response->permissions_removed );
		$this->assertIsArray( $response->permissions_failed );
		$this->assertContains( 'ads_management', $response->permissions_failed );
		$this->assertIsArray( $response->warnings );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'success' => true, 'id' => 'user_789' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
		$this->assertEquals( $json, $response->to_string_safe() );
	}

	/**
	 * Test response with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
		$this->assertNull( $response->success );
		$this->assertNull( $response->id );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with user details.
	 */
	public function test_response_with_user_details() {
		$data = [
			'success' => true,
			'id' => 'user_999',
			'user' => [
				'name' => 'John Doe',
				'email' => 'john@example.com',
			],
			'permissions_before' => [ 'manage_pages', 'ads_management', 'business_management' ],
			'permissions_after' => [ 'business_management' ],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'user_999', $response->id );
		$this->assertIsArray( $response->user );
		$this->assertEquals( 'John Doe', $response->user['name'] );
		$this->assertEquals( 'john@example.com', $response->user['email'] );
		$this->assertCount( 3, $response->permissions_before );
		$this->assertCount( 1, $response->permissions_after );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"success": true}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->another_missing_property );
	}

	/**
	 * Test response with null values.
	 */
	public function test_response_with_null_values() {
		$data = [
			'success' => true,
			'id' => 'user_123',
			'permissions_removed' => null,
			'message' => null,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'user_123', $response->id );
		$this->assertNull( $response->permissions_removed );
		$this->assertNull( $response->message );
	}
} 