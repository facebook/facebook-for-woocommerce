<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\Products\Update;

use WooCommerce\Facebook\API\ProductCatalog\Products\Update\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Products Update Response class.
 *
 * @since 3.5.2
 */
class ProductsUpdateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
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
	}

	/**
	 * Test instantiation and to_string method.
	 */
	public function test_instantiation_and_to_string() {
		$data = json_encode( [ 'success' => true ] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing success property with boolean values.
	 */
	public function test_success_property_boolean_values() {
		// Test true
		$data = json_encode( [ 'success' => true ] );
		$response = new Response( $data );
		$this->assertTrue( $response->success );
		
		// Test false
		$data = json_encode( [ 'success' => false ] );
		$response = new Response( $data );
		$this->assertFalse( $response->success );
	}

	/**
	 * Test with missing success property.
	 */
	public function test_missing_success_property() {
		$data = json_encode( [ 'id' => 'product_123' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->success );
	}

	/**
	 * Test with empty object.
	 */
	public function test_empty_object() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->success );
	}

	/**
	 * Test with additional properties in success response.
	 */
	public function test_success_with_additional_properties() {
		$data = json_encode( [
			'success' => true,
			'id' => 'product_item_123',
			'updated_fields' => [ 'price', 'availability', 'description' ],
			'timestamp' => '2023-01-01T00:00:00Z'
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'product_item_123', $response->id );
		$this->assertIsArray( $response->updated_fields );
		$this->assertCount( 3, $response->updated_fields );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->timestamp );
	}

	/**
	 * Test with error response structure.
	 */
	public function test_error_response_structure() {
		$data = json_encode( [
			'success' => false,
			'error' => [
				'message' => 'Invalid product ID',
				'type' => 'OAuthException',
				'code' => 100,
				'error_subcode' => 33,
				'fbtrace_id' => 'AbCdEfGhIjKlMnOpQrStUvWxYz'
			]
		] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->success );
		$this->assertIsArray( $response->error );
		$this->assertEquals( 'Invalid product ID', $response->error['message'] );
		$this->assertEquals( 'OAuthException', $response->error['type'] );
		$this->assertEquals( 100, $response->error['code'] );
		$this->assertEquals( 33, $response->error['error_subcode'] );
	}

	/**
	 * Test with various success value types.
	 */
	public function test_various_success_value_types() {
		// Test null
		$data = json_encode( [ 'success' => null ] );
		$response = new Response( $data );
		$this->assertNull( $response->success );
		
		// Test string
		$data = json_encode( [ 'success' => 'true' ] );
		$response = new Response( $data );
		$this->assertEquals( 'true', $response->success );
		
		// Test numeric 1
		$data = json_encode( [ 'success' => 1 ] );
		$response = new Response( $data );
		$this->assertEquals( 1, $response->success );
		
		// Test numeric 0
		$data = json_encode( [ 'success' => 0 ] );
		$response = new Response( $data );
		$this->assertEquals( 0, $response->success );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [
			'success' => true,
			'id' => 'product_array_test'
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'product_array_test', $response['id'] );
		
		// Test isset
		$this->assertTrue( isset( $response['success'] ) );
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$product_id = 'product_get_id_test';
		$data = json_encode( [ 
			'id' => $product_id,
			'success' => true
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $product_id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_method_missing_id() {
		$data = json_encode( [ 'success' => true ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_id() );
	}

	/**
	 * Test that the class has no additional public methods.
	 */
	public function test_no_additional_public_methods() {
		$reflection = new \ReflectionClass( Response::class );
		$public_methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		
		// Filter out inherited methods
		$own_methods = array_filter( $public_methods, function( $method ) {
			return $method->getDeclaringClass()->getName() === Response::class;
		} );
		
		// Should have no methods of its own (empty class extending ApiResponse)
		$this->assertCount( 0, $own_methods );
	}

	/**
	 * Test with detailed product update response.
	 */
	public function test_detailed_product_update_response() {
		$data = json_encode( [
			'success' => true,
			'id' => 'product_detailed',
			'retailer_id' => 'woo_product_123',
			'updated_at' => '2023-12-01T10:00:00Z',
			'fields_updated' => [
				'price' => '19.99 USD',
				'availability' => 'in stock',
				'condition' => 'new',
				'description' => 'Updated product description'
			],
			'warnings' => [
				'Image URL might be too large for optimal performance'
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'product_detailed', $response->id );
		$this->assertEquals( 'woo_product_123', $response->retailer_id );
		$this->assertEquals( '2023-12-01T10:00:00Z', $response->updated_at );
		$this->assertIsArray( $response->fields_updated );
		$this->assertEquals( '19.99 USD', $response->fields_updated['price'] );
		$this->assertEquals( 'in stock', $response->fields_updated['availability'] );
		$this->assertIsArray( $response->warnings );
		$this->assertCount( 1, $response->warnings );
	}

	/**
	 * Test with validation errors in response.
	 */
	public function test_validation_errors_response() {
		$data = json_encode( [
			'success' => false,
			'validation_errors' => [
				[
					'field' => 'price',
					'message' => 'Price must be greater than 0'
				],
				[
					'field' => 'image_url',
					'message' => 'Invalid image URL format'
				]
			],
			'error_count' => 2
		] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->success );
		$this->assertIsArray( $response->validation_errors );
		$this->assertCount( 2, $response->validation_errors );
		$this->assertEquals( 'price', $response->validation_errors[0]['field'] );
		$this->assertEquals( 'Price must be greater than 0', $response->validation_errors[0]['message'] );
		$this->assertEquals( 2, $response->error_count );
	}

	/**
	 * Test with rate limit information.
	 */
	public function test_with_rate_limit_info() {
		$data = json_encode( [
			'success' => true,
			'rate_limit' => [
				'remaining' => 4950,
				'limit' => 5000,
				'reset_at' => '2023-12-01T11:00:00Z'
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertIsArray( $response->rate_limit );
		$this->assertEquals( 4950, $response->rate_limit['remaining'] );
		$this->assertEquals( 5000, $response->rate_limit['limit'] );
		$this->assertEquals( '2023-12-01T11:00:00Z', $response->rate_limit['reset_at'] );
	}

	/**
	 * Test with empty arrays.
	 */
	public function test_empty_arrays() {
		$data = json_encode( [
			'success' => true,
			'updated_fields' => [],
			'warnings' => [],
			'errors' => []
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertIsArray( $response->updated_fields );
		$this->assertEmpty( $response->updated_fields );
		$this->assertIsArray( $response->warnings );
		$this->assertEmpty( $response->warnings );
		$this->assertIsArray( $response->errors );
		$this->assertEmpty( $response->errors );
	}

	/**
	 * Test with special characters in error messages.
	 */
	public function test_special_characters_in_error_messages() {
		$data = json_encode( [
			'success' => false,
			'error' => [
				'message' => "Product 'Test & Demo' <update> failed: \"Invalid\" characters & entities!",
				'details' => 'Description contains <script>alert("XSS")</script> which is not allowed'
			]
		] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->success );
		$this->assertEquals( 
			"Product 'Test & Demo' <update> failed: \"Invalid\" characters & entities!", 
			$response->error['message'] 
		);
		$this->assertEquals(
			'Description contains <script>alert("XSS")</script> which is not allowed',
			$response->error['details']
		);
	}

	/**
	 * Test with Unicode in response.
	 */
	public function test_unicode_in_response() {
		$data = json_encode( [
			'success' => true,
			'message' => 'Produit mis à jour avec succès 🎉',
			'updated_title' => '商品タイトル テスト'
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'Produit mis à jour avec succès 🎉', $response->message );
		$this->assertEquals( '商品タイトル テスト', $response->updated_title );
	}

	/**
	 * Test with very long product ID.
	 */
	public function test_very_long_product_id() {
		$long_id = '1234567890123456789012345678901234567890';
		$data = json_encode( [
			'success' => true,
			'id' => $long_id
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( $long_id, $response->id );
		$this->assertEquals( $long_id, $response->get_id() );
	}

	/**
	 * Test with batch update response.
	 */
	public function test_batch_update_response() {
		$data = json_encode( [
			'success' => true,
			'batch_id' => 'batch_123',
			'total_products' => 50,
			'successful_updates' => 48,
			'failed_updates' => 2,
			'processing_time_ms' => 1250,
			'failures' => [
				[
					'product_id' => 'prod_fail_1',
					'error' => 'Invalid price format'
				],
				[
					'product_id' => 'prod_fail_2',
					'error' => 'Missing required field: availability'
				]
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'batch_123', $response->batch_id );
		$this->assertEquals( 50, $response->total_products );
		$this->assertEquals( 48, $response->successful_updates );
		$this->assertEquals( 2, $response->failed_updates );
		$this->assertEquals( 1250, $response->processing_time_ms );
		$this->assertIsArray( $response->failures );
		$this->assertCount( 2, $response->failures );
	}
}
