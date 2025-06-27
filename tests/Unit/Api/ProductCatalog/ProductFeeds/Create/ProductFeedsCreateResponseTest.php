<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductFeeds\Create;

use WooCommerce\Facebook\API\ProductCatalog\ProductFeeds\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductFeeds Create Response class.
 *
 * @since 3.5.2
 */
class ProductFeedsCreateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test response with product feed creation data.
	 */
	public function test_response_with_feed_creation_data() {
		$data = [
			'id' => '123456789012345',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '123456789012345', $response->id );
		$this->assertEquals( '123456789012345', $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with additional feed fields.
	 */
	public function test_response_with_additional_fields() {
		$data = [
			'id' => '987654321098765',
			'name' => 'WooCommerce Product Feed',
			'created_time' => '2023-01-01T00:00:00+0000',
			'schedule' => 'DAILY',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '987654321098765', $response->id );
		$this->assertEquals( '987654321098765', $response->get_id() );
		$this->assertEquals( 'WooCommerce Product Feed', $response->name );
		$this->assertEquals( '2023-01-01T00:00:00+0000', $response->created_time );
		$this->assertEquals( 'DAILY', $response->schedule );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'id' => '111222333444555',
			'status' => 'active',
			'item_count' => 100,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertEquals( '111222333444555', $response['id'] );
		$this->assertEquals( 'active', $response['status'] );
		$this->assertEquals( 100, $response['item_count'] );
		
		// Test setting values
		$response['custom_field'] = 'custom_value';
		$this->assertEquals( 'custom_value', $response['custom_field'] );
		
		// Test unsetting values
		unset( $response['item_count'] );
		$this->assertFalse( isset( $response['item_count'] ) );
	}

	/**
	 * Test response with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
		$this->assertNull( $response->id );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'id' => '555666777888999' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
		$this->assertEquals( $json, $response->to_string_safe() );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"id": "123"}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->missing_field );
	}

	/**
	 * Test response with null values.
	 */
	public function test_response_with_null_values() {
		$data = [
			'id' => '999888777666555',
			'name' => null,
			'description' => null,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '999888777666555', $response->id );
		$this->assertNull( $response->name );
		$this->assertNull( $response->description );
	}

	/**
	 * Test response with numeric ID.
	 */
	public function test_response_with_numeric_id() {
		$data = [
			'id' => 123456789012345,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// JSON decode will keep it as numeric
		$this->assertEquals( 123456789012345, $response->id );
		$this->assertIsInt( $response->id );
	}

	/**
	 * Test response with feed configuration.
	 */
	public function test_response_with_feed_configuration() {
		$data = [
			'id' => '123123123123123',
			'encoding' => 'UTF-8',
			'delimiter' => ',',
			'quoted_fields_mode' => 'AUTODETECT',
			'update_schedule' => [
				'interval' => 'DAILY',
				'url' => 'https://example.com/feed.csv',
				'hour' => 3,
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '123123123123123', $response->id );
		$this->assertEquals( 'UTF-8', $response->encoding );
		$this->assertEquals( ',', $response->delimiter );
		$this->assertEquals( 'AUTODETECT', $response->quoted_fields_mode );
		$this->assertIsArray( $response->update_schedule );
		$this->assertEquals( 'DAILY', $response->update_schedule['interval'] );
		$this->assertEquals( 3, $response->update_schedule['hour'] );
	}

	/**
	 * Test response with validation error.
	 */
	public function test_response_with_validation_error() {
		$errorData = [
			'error' => [
				'message' => 'Invalid parameters',
				'type' => 'GraphInvalidParameterException',
				'code' => 100,
				'error_subcode' => 2108006,
				'error_user_msg' => 'The feed URL is not accessible.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid parameters', $response->get_api_error_message() );
		$this->assertEquals( 'GraphInvalidParameterException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 2108006, $response->error['error_subcode'] );
	}

	/**
	 * Test response with very long ID.
	 */
	public function test_response_with_very_long_id() {
		$longId = str_repeat( '1234567890', 10 );
		$data = [
			'id' => $longId,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $longId, $response->id );
		$this->assertEquals( 100, strlen( $response->id ) );
	}

	/**
	 * Test response with feed metadata.
	 */
	public function test_response_with_feed_metadata() {
		$data = [
			'id' => '456456456456456',
			'product_count' => 1500,
			'latest_upload' => [
				'id' => 'upload_123',
				'end_time' => '2023-12-01T10:00:00+0000',
			],
			'is_partner_upload_enabled' => true,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '456456456456456', $response->id );
		$this->assertEquals( 1500, $response->product_count );
		$this->assertIsArray( $response->latest_upload );
		$this->assertEquals( 'upload_123', $response->latest_upload['id'] );
		$this->assertTrue( $response->is_partner_upload_enabled );
	}
} 