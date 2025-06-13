<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductGroups\Create;

use WooCommerce\Facebook\API\ProductCatalog\ProductGroups\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog ProductGroups Create Response class.
 *
 * @since 3.5.2
 */
class ProductGroupsCreateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test response instantiation with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
	}

	/**
	 * Test response instantiation with ID property.
	 */
	public function test_response_with_id_property() {
		$id = '1234567890';
		$json = json_encode( [ 'id' => $id ] );
		$response = new Response( $json );
		
		$this->assertEquals( $id, $response->id );
		$this->assertEquals( $id, $response->get_id() );
	}

	/**
	 * Test response with product group data.
	 */
	public function test_response_with_product_group_data() {
		$data = [
			'id' => '9876543210',
			'name' => 'Test Product Group',
			'retailer_id' => 'test_retailer_123',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $data['id'], $response->id );
		$this->assertEquals( $data['name'], $response->name );
		$this->assertEquals( $data['retailer_id'], $response->retailer_id );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'id' => '123',
			'status' => 'active',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertEquals( $data['id'], $response['id'] );
		$this->assertEquals( $data['status'], $response['status'] );
		
		// Test setting values
		$response['new_field'] = 'new_value';
		$this->assertEquals( 'new_value', $response['new_field'] );
		
		// Test unsetting values
		unset( $response['status'] );
		$this->assertFalse( isset( $response['status'] ) );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Invalid product group',
				'type' => 'OAuthException',
				'code' => 100,
				'error_user_msg' => 'The product group could not be created.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid product group', $response->get_api_error_message() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The product group could not be created.', $response->get_user_error_message() );
	}

	/**
	 * Test response without error.
	 */
	public function test_response_without_error() {
		$data = [ 'id' => '123' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertFalse( $response->has_api_error() );
		$this->assertNull( $response->get_api_error_message() );
		$this->assertNull( $response->get_api_error_type() );
		$this->assertNull( $response->get_api_error_code() );
		$this->assertNull( $response->get_user_error_message() );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'id' => '123', 'name' => 'Test' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
		$this->assertEquals( $json, $response->to_string_safe() );
	}

	/**
	 * Test response with null values.
	 */
	public function test_response_with_null_values() {
		$data = [
			'id' => '123',
			'name' => null,
			'description' => null,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '123', $response->id );
		$this->assertNull( $response->name );
		$this->assertNull( $response->description );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"id": "123"}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->another_missing_property );
	}

	/**
	 * Test response with complex nested data.
	 */
	public function test_response_with_nested_data() {
		$data = [
			'id' => '123',
			'metadata' => [
				'created_time' => '2023-01-01T00:00:00Z',
				'updated_time' => '2023-01-02T00:00:00Z',
			],
			'filters' => [
				'retailer_id' => [ 'is_any' => [ '1', '2', '3' ] ],
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '123', $response->id );
		$this->assertIsArray( $response->metadata );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->metadata['created_time'] );
		$this->assertIsArray( $response->filters );
	}

	/**
	 * Test response with Unicode characters.
	 */
	public function test_response_with_unicode_characters() {
		$data = [
			'id' => '123',
			'name' => 'Produit testé 测试产品 🎉',
			'description' => 'Description with émojis 😀 and special chars',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $data['name'], $response->name );
		$this->assertEquals( $data['description'], $response->description );
	}
} 