<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductSets\Delete;

use WooCommerce\Facebook\API\ProductCatalog\ProductSets\Delete\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductSets Delete Response class.
 *
 * @since 3.5.2
 */
class ProductSetsDeleteResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test response with successful deletion.
	 */
	public function test_response_with_successful_deletion() {
		$data = [
			'success' => true,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with failed deletion.
	 */
	public function test_response_with_failed_deletion() {
		$data = [
			'success' => false,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertFalse( $response->success );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with additional fields.
	 */
	public function test_response_with_additional_fields() {
		$data = [
			'success' => true,
			'id' => 'productset_123456',
			'deleted_at' => '2023-01-01T00:00:00Z',
			'message' => 'Product set deleted successfully',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'productset_123456', $response->id );
		$this->assertEquals( 'productset_123456', $response->get_id() );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->deleted_at );
		$this->assertEquals( 'Product set deleted successfully', $response->message );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Product set not found',
				'type' => 'GraphMethodException',
				'code' => 100,
				'error_user_msg' => 'The product set you are trying to delete does not exist.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Product set not found', $response->get_api_error_message() );
		$this->assertEquals( 'GraphMethodException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The product set you are trying to delete does not exist.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'success' => true,
			'id' => 'ps_789',
			'status' => 'deleted',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['success'] ) );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'ps_789', $response['id'] );
		$this->assertEquals( 'deleted', $response['status'] );
		
		// Test setting values
		$response['custom_field'] = 'custom_value';
		$this->assertEquals( 'custom_value', $response['custom_field'] );
		
		// Test unsetting values
		unset( $response['status'] );
		$this->assertFalse( isset( $response['status'] ) );
	}

	/**
	 * Test response with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
		$this->assertNull( $response->success );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'success' => true, 'id' => 'ps_test' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
		$this->assertEquals( $json, $response->to_string_safe() );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"success": true}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->missing_field );
	}

	/**
	 * Test response with null values.
	 */
	public function test_response_with_null_values() {
		$data = [
			'success' => true,
			'id' => null,
			'message' => null,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertNull( $response->id );
		$this->assertNull( $response->message );
	}

	/**
	 * Test response with permission error.
	 */
	public function test_response_with_permission_error() {
		$errorData = [
			'error' => [
				'message' => 'Permission denied',
				'type' => 'OAuthException',
				'code' => 200,
				'error_subcode' => 1234,
				'error_user_msg' => 'You do not have permission to delete this product set.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Permission denied', $response->get_api_error_message() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 200, $response->get_api_error_code() );
	}

	/**
	 * Test response with partial deletion data.
	 */
	public function test_response_with_partial_deletion() {
		$data = [
			'success' => false,
			'partial_success' => true,
			'deleted_items' => 5,
			'failed_items' => 2,
			'errors' => [
				'Some items could not be deleted due to dependencies',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertFalse( $response->success );
		$this->assertTrue( $response->partial_success );
		$this->assertEquals( 5, $response->deleted_items );
		$this->assertEquals( 2, $response->failed_items );
		$this->assertIsArray( $response->errors );
		$this->assertCount( 1, $response->errors );
	}

	/**
	 * Test response with boolean string values.
	 */
	public function test_response_with_boolean_string_values() {
		$data = [
			'success' => 'true',
			'id' => 'ps_bool_test',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Note: PHP's json_decode will keep "true" as a string, not convert to boolean
		$this->assertEquals( 'true', $response->success );
		$this->assertNotSame( true, $response->success );
	}

	/**
	 * Test response with numeric success value.
	 */
	public function test_response_with_numeric_success_value() {
		$data = [
			'success' => 1,
			'id' => 'ps_numeric',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 1, $response->success );
		$this->assertNotSame( true, $response->success );
	}
} 