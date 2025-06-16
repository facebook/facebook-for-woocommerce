<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductSets\Create;

use WooCommerce\Facebook\API\ProductCatalog\ProductSets\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductSets Create Response class.
 *
 * @since 3.5.2
 */
class ProductSetsCreateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test response with product set creation data.
	 */
	public function test_response_with_product_set_creation_data() {
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
	 * Test response with additional fields.
	 */
	public function test_response_with_additional_fields() {
		$data = [
			'id' => '987654321098765',
			'name' => 'Summer Collection 2023',
			'filter' => '{condition: {i_contains: summer}}',
			'product_catalog_id' => '111222333444555',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '987654321098765', $response->id );
		$this->assertEquals( '987654321098765', $response->get_id() );
		$this->assertEquals( 'Summer Collection 2023', $response->name );
		$this->assertEquals( '{condition: {i_contains: summer}}', $response->filter );
		$this->assertEquals( '111222333444555', $response->product_catalog_id );
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
				'error_user_msg' => 'The product catalog does not exist or you do not have permission to create product sets.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid product catalog ID', $response->get_api_error_message() );
		$this->assertEquals( 'OAuthException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The product catalog does not exist or you do not have permission to create product sets.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'id' => '111222333444555',
			'name' => 'Test Product Set',
			'is_dynamic' => true,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertEquals( '111222333444555', $response['id'] );
		$this->assertEquals( 'Test Product Set', $response['name'] );
		$this->assertTrue( $response['is_dynamic'] );
		
		// Test setting values
		$response['custom_field'] = 'custom_value';
		$this->assertEquals( 'custom_value', $response['custom_field'] );
		
		// Test unsetting values
		unset( $response['is_dynamic'] );
		$this->assertFalse( isset( $response['is_dynamic'] ) );
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
	 * Test response with product set configuration.
	 */
	public function test_response_with_product_set_configuration() {
		$data = [
			'id' => '123123123123123',
			'name' => 'Sale Items',
			'filter' => [
				'condition' => 'AND',
				'filters' => [
					[
						'field' => 'price',
						'operator' => 'LESS_THAN',
						'value' => 50,
					],
				],
			],
			'is_dynamic' => true,
			'product_count' => 150,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '123123123123123', $response->id );
		$this->assertEquals( 'Sale Items', $response->name );
		$this->assertIsArray( $response->filter );
		$this->assertEquals( 'AND', $response->filter['condition'] );
		$this->assertTrue( $response->is_dynamic );
		$this->assertEquals( 150, $response->product_count );
	}

	/**
	 * Test response with validation error.
	 */
	public function test_response_with_validation_error() {
		$errorData = [
			'error' => [
				'message' => 'Invalid filter syntax',
				'type' => 'GraphInvalidParameterException',
				'code' => 100,
				'error_subcode' => 2108006,
				'error_user_msg' => 'The filter syntax is invalid. Please check the documentation.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Invalid filter syntax', $response->get_api_error_message() );
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
	 * Test response with special characters in name.
	 */
	public function test_response_with_special_characters_in_name() {
		$data = [
			'id' => '456456456456456',
			'name' => 'Special Set: "Summer Sale" & More <2023>',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '456456456456456', $response->id );
		$this->assertEquals( 'Special Set: "Summer Sale" & More <2023>', $response->name );
	}

	/**
	 * Test response with Unicode characters.
	 */
	public function test_response_with_unicode_characters() {
		$data = [
			'id' => '789789789789789',
			'name' => '🎉 Unicode Set 测试 коллекция',
			'description' => 'Special characters: café, naïve, résumé',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '789789789789789', $response->id );
		$this->assertEquals( '🎉 Unicode Set 测试 коллекция', $response->name );
		$this->assertEquals( 'Special characters: café, naïve, résumé', $response->description );
	}
} 