<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductFeedUploads\Create;

use WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductFeedUploads Create Response class.
 *
 * @since 3.5.2
 */
class ProductFeedUploadsCreateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Response class exists and extends proper classes.
	 */
	public function test_response_class_hierarchy() {
		$this->assertTrue( class_exists( Response::class ) );
		
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
		$this->assertInstanceOf( JSONResponse::class, $response );
	}

	/**
	 * Test response with feed upload data array.
	 */
	public function test_response_with_feed_upload_data() {
		$data = [
			'data' => [
				'id' => '123456789012345',
				'status' => 'processing',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '123456789012345', $response->data['id'] );
		$this->assertEquals( 'processing', $response->data['status'] );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with detailed upload information.
	 */
	public function test_response_with_detailed_upload_info() {
		$data = [
			'data' => [
				'id' => '987654321098765',
				'status' => 'completed',
				'start_time' => '2023-01-01T00:00:00+0000',
				'end_time' => '2023-01-01T00:05:00+0000',
				'num_items_processed' => 1500,
				'num_items_failed' => 5,
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '987654321098765', $response->data['id'] );
		$this->assertEquals( 'completed', $response->data['status'] );
		$this->assertEquals( '2023-01-01T00:00:00+0000', $response->data['start_time'] );
		$this->assertEquals( '2023-01-01T00:05:00+0000', $response->data['end_time'] );
		$this->assertEquals( 1500, $response->data['num_items_processed'] );
		$this->assertEquals( 5, $response->data['num_items_failed'] );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Invalid feed ID',
				'type' => 'OAuthException',
				'code' => 100,
				'error_user_msg' => 'The product feed does not exist or you do not have permission to upload.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid feed ID', $response->get_api_error_message() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The product feed does not exist or you do not have permission to upload.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'data' => [
				'id' => '111222333444555',
				'status' => 'in_progress',
			],
			'success' => true,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['data'] ) );
		$this->assertIsArray( $response['data'] );
		$this->assertEquals( '111222333444555', $response['data']['id'] );
		$this->assertTrue( $response['success'] );
		
		// Test setting values
		$response['custom_field'] = 'custom_value';
		$this->assertEquals( 'custom_value', $response['custom_field'] );
		
		// Test unsetting values
		unset( $response['success'] );
		$this->assertFalse( isset( $response['success'] ) );
	}

	/**
	 * Test response with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
		$this->assertNull( $response->data );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'data' => [ 'id' => '555666777888999' ] ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
		$this->assertEquals( $json, $response->to_string_safe() );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"data": {"id": "123"}}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->missing_field );
	}

	/**
	 * Test response with null values.
	 */
	public function test_response_with_null_values() {
		$data = [
			'data' => [
				'id' => '999888777666555',
				'status' => null,
				'error_message' => null,
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '999888777666555', $response->data['id'] );
		$this->assertNull( $response->data['status'] );
		$this->assertNull( $response->data['error_message'] );
	}

	/**
	 * Test response with empty data array.
	 */
	public function test_response_with_empty_data_array() {
		$data = [
			'data' => [],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEmpty( $response->data );
	}

	/**
	 * Test response with upload errors.
	 */
	public function test_response_with_upload_errors() {
		$data = [
			'data' => [
				'id' => '123123123123123',
				'status' => 'failed',
				'errors' => [
					[
						'line' => 10,
						'message' => 'Invalid price format',
					],
					[
						'line' => 25,
						'message' => 'Missing required field: availability',
					],
				],
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '123123123123123', $response->data['id'] );
		$this->assertEquals( 'failed', $response->data['status'] );
		$this->assertIsArray( $response->data['errors'] );
		$this->assertCount( 2, $response->data['errors'] );
		$this->assertEquals( 10, $response->data['errors'][0]['line'] );
		$this->assertEquals( 'Invalid price format', $response->data['errors'][0]['message'] );
	}

	/**
	 * Test response with validation error.
	 */
	public function test_response_with_validation_error() {
		$errorData = [
			'error' => [
				'message' => 'Invalid upload format',
				'type' => 'GraphInvalidParameterException',
				'code' => 100,
				'error_subcode' => 2108006,
				'error_user_msg' => 'The upload file must be in CSV or TSV format.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid upload format', $response->get_api_error_message() );
		$this->assertEquals( 'GraphInvalidParameterException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 2108006, $response->error['error_subcode'] );
	}

	/**
	 * Test response with batch upload data.
	 */
	public function test_response_with_batch_upload_data() {
		$data = [
			'data' => [
				'id' => '456456456456456',
				'status' => 'processing',
				'batch_size' => 500,
				'batches_completed' => 3,
				'total_batches' => 10,
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '456456456456456', $response->data['id'] );
		$this->assertEquals( 'processing', $response->data['status'] );
		$this->assertEquals( 500, $response->data['batch_size'] );
		$this->assertEquals( 3, $response->data['batches_completed'] );
		$this->assertEquals( 10, $response->data['total_batches'] );
	}

	/**
	 * Test response with upload URL.
	 */
	public function test_response_with_upload_url() {
		$data = [
			'data' => [
				'id' => '789789789789789',
				'upload_url' => 'https://upload.facebook.com/feed/123456789',
				'expires_at' => '2023-01-01T01:00:00+0000',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '789789789789789', $response->data['id'] );
		$this->assertEquals( 'https://upload.facebook.com/feed/123456789', $response->data['upload_url'] );
		$this->assertEquals( '2023-01-01T01:00:00+0000', $response->data['expires_at'] );
	}

	/**
	 * Test response with nested data structure.
	 */
	public function test_response_with_nested_data() {
		$data = [
			'data' => [
				'id' => '321321321321321',
				'metadata' => [
					'feed_id' => 'feed_123',
					'catalog_id' => 'catalog_456',
					'upload_type' => 'scheduled',
				],
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( '321321321321321', $response->data['id'] );
		$this->assertIsArray( $response->data['metadata'] );
		$this->assertEquals( 'feed_123', $response->data['metadata']['feed_id'] );
		$this->assertEquals( 'catalog_456', $response->data['metadata']['catalog_id'] );
		$this->assertEquals( 'scheduled', $response->data['metadata']['upload_type'] );
	}
} 