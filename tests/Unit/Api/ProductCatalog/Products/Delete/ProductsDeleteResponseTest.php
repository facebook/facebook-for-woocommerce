<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\Products\Delete;

use WooCommerce\Facebook\API\ProductCatalog\Products\Delete\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog Products Delete Response class.
 *
 * @since 3.5.2
 */
class ProductsDeleteResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test response instantiation with ID property.
	 */
	public function test_response_with_id_property() {
		$id = 'product_123';
		$json = json_encode( [ 'id' => $id, 'success' => true ] );
		$response = new Response( $json );
		
		$this->assertEquals( $id, $response->id );
		$this->assertEquals( $id, $response->get_id() );
		$this->assertTrue( $response->success );
	}

	/**
	 * Test response with delete confirmation data.
	 */
	public function test_response_with_delete_confirmation() {
		$data = [
			'success' => true,
			'deleted_count' => 1,
			'id' => 'product_456',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 1, $response->deleted_count );
		$this->assertEquals( 'product_456', $response->id );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Product not found',
				'type' => 'GraphMethodException',
				'code' => 100,
				'error_user_msg' => 'The product could not be deleted because it does not exist.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Product not found', $response->get_api_error_message() );
		$this->assertEquals( 'GraphMethodException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The product could not be deleted because it does not exist.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'success' => true,
			'id' => 'product_789',
			'deleted_at' => '2023-01-01T00:00:00Z',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['success'] ) );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'product_789', $response['id'] );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response['deleted_at'] );
	}

	/**
	 * Test response with partial success.
	 */
	public function test_response_with_partial_success() {
		$data = [
			'success' => false,
			'partial_success' => true,
			'errors' => [
				'Some products could not be deleted',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertFalse( $response->success );
		$this->assertTrue( $response->partial_success );
		$this->assertIsArray( $response->errors );
		$this->assertContains( 'Some products could not be deleted', $response->errors );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'success' => true, 'id' => 'product_123' ];
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
	}

	/**
	 * Test response with batch delete results.
	 */
	public function test_response_with_batch_delete() {
		$data = [
			'success' => true,
			'deleted_count' => 5,
			'failed_count' => 2,
			'deleted_ids' => [ 'prod_1', 'prod_2', 'prod_3', 'prod_4', 'prod_5' ],
			'failed_ids' => [ 'prod_6', 'prod_7' ],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 5, $response->deleted_count );
		$this->assertEquals( 2, $response->failed_count );
		$this->assertIsArray( $response->deleted_ids );
		$this->assertCount( 5, $response->deleted_ids );
		$this->assertIsArray( $response->failed_ids );
		$this->assertCount( 2, $response->failed_ids );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"success": true}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->another_missing_property );
	}
} 