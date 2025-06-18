<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\Products\Create;

use WooCommerce\Facebook\API\ProductCatalog\Products\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Products Create Response class.
 *
 * @since 3.5.2
 */
class ProductsCreateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test response with product creation data.
	 */
	public function test_response_with_product_creation_data() {
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
	 * Test response with additional product fields.
	 */
	public function test_response_with_additional_fields() {
		$data = [
			'id' => '987654321098765',
			'retailer_id' => 'SKU-12345',
			'product_group_id' => '111222333444555',
			'created_time' => '2023-01-01T00:00:00+0000',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '987654321098765', $response->id );
		$this->assertEquals( '987654321098765', $response->get_id() );
		$this->assertEquals( 'SKU-12345', $response->retailer_id );
		$this->assertEquals( '111222333444555', $response->product_group_id );
		$this->assertEquals( '2023-01-01T00:00:00+0000', $response->created_time );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Invalid product catalog ID',
				'type' => 'OAuthException',
				'code' => 100,
				'error_user_msg' => 'The product catalog does not exist or you do not have permission to create products.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid product catalog ID', $response->get_api_error_message() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The product catalog does not exist or you do not have permission to create products.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'id' => '111222333444555',
			'status' => 'active',
			'availability' => 'in stock',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertEquals( '111222333444555', $response['id'] );
		$this->assertEquals( 'active', $response['status'] );
		$this->assertEquals( 'in stock', $response['availability'] );
		
		// Test setting values
		$response['custom_field'] = 'custom_value';
		$this->assertEquals( 'custom_value', $response['custom_field'] );
		
		// Test unsetting values
		unset( $response['availability'] );
		$this->assertFalse( isset( $response['availability'] ) );
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
	 * Test response with product details.
	 */
	public function test_response_with_product_details() {
		$data = [
			'id' => '123123123123123',
			'retailer_id' => 'WC-PRODUCT-456',
			'name' => 'Test Product',
			'description' => 'This is a test product description',
			'price' => '2999',
			'currency' => 'USD',
			'availability' => 'in stock',
			'condition' => 'new',
			'brand' => 'Test Brand',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '123123123123123', $response->id );
		$this->assertEquals( 'WC-PRODUCT-456', $response->retailer_id );
		$this->assertEquals( 'Test Product', $response->name );
		$this->assertEquals( 'This is a test product description', $response->description );
		$this->assertEquals( '2999', $response->price );
		$this->assertEquals( 'USD', $response->currency );
		$this->assertEquals( 'in stock', $response->availability );
		$this->assertEquals( 'new', $response->condition );
		$this->assertEquals( 'Test Brand', $response->brand );
	}

	/**
	 * Test response with validation error.
	 */
	public function test_response_with_validation_error() {
		$errorData = [
			'error' => [
				'message' => 'Invalid product data',
				'type' => 'GraphInvalidParameterException',
				'code' => 100,
				'error_subcode' => 2108006,
				'error_user_msg' => 'The product price must be a positive number.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid product data', $response->get_api_error_message() );
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
	 * Test response with special characters in retailer ID.
	 */
	public function test_response_with_special_characters_in_retailer_id() {
		$data = [
			'id' => '456456456456456',
			'retailer_id' => 'SKU-123_TEST/2023#SPECIAL',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '456456456456456', $response->id );
		$this->assertEquals( 'SKU-123_TEST/2023#SPECIAL', $response->retailer_id );
	}

	/**
	 * Test response with image URLs.
	 */
	public function test_response_with_image_urls() {
		$data = [
			'id' => '789789789789789',
			'image_url' => 'https://example.com/product-image.jpg',
			'additional_image_urls' => [
				'https://example.com/product-image-2.jpg',
				'https://example.com/product-image-3.jpg',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '789789789789789', $response->id );
		$this->assertEquals( 'https://example.com/product-image.jpg', $response->image_url );
		$this->assertIsArray( $response->additional_image_urls );
		$this->assertCount( 2, $response->additional_image_urls );
	}
} 