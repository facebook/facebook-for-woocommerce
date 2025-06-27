<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductGroups\Update;

use WooCommerce\Facebook\API\ProductCatalog\ProductGroups\Update\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductGroups Update Response class.
 *
 * @since 3.5.2
 */
class ProductGroupsUpdateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		$data = json_encode( [ 'id' => 'group_123' ] );
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
			'id' => 'product_group_123',
			'updated_fields' => [ 'name', 'description' ],
			'timestamp' => '2023-01-01T00:00:00Z'
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'product_group_123', $response->id );
		$this->assertIsArray( $response->updated_fields );
		$this->assertCount( 2, $response->updated_fields );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->timestamp );
	}

	/**
	 * Test with error response structure.
	 */
	public function test_error_response_structure() {
		$data = json_encode( [
			'success' => false,
			'error' => [
				'message' => 'Invalid product group ID',
				'type' => 'OAuthException',
				'code' => 100,
				'error_subcode' => 33,
				'fbtrace_id' => 'AbCdEfGhIjKlMnOpQrStUvWxYz'
			]
		] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->success );
		$this->assertIsArray( $response->error );
		$this->assertEquals( 'Invalid product group ID', $response->error['message'] );
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
			'id' => 'group_array_test'
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'group_array_test', $response['id'] );
		
		// Test isset
		$this->assertTrue( isset( $response['success'] ) );
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$group_id = 'product_group_get_id_test';
		$data = json_encode( [ 
			'id' => $group_id,
			'success' => true
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $group_id, $response->get_id() );
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
	 * Test with detailed update response.
	 */
	public function test_detailed_update_response() {
		$data = json_encode( [
			'success' => true,
			'id' => 'group_detailed',
			'updated_at' => '2023-12-01T10:00:00Z',
			'fields_updated' => [
				'variants' => [
					'added' => 5,
					'removed' => 2,
					'updated' => 10
				],
				'retailer_id' => 'new_retailer_id'
			],
			'warnings' => [
				'Some variants were skipped due to invalid data'
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'group_detailed', $response->id );
		$this->assertEquals( '2023-12-01T10:00:00Z', $response->updated_at );
		$this->assertIsArray( $response->fields_updated );
		$this->assertEquals( 5, $response->fields_updated['variants']['added'] );
		$this->assertEquals( 2, $response->fields_updated['variants']['removed'] );
		$this->assertEquals( 10, $response->fields_updated['variants']['updated'] );
		$this->assertIsArray( $response->warnings );
		$this->assertCount( 1, $response->warnings );
	}

	/**
	 * Test with partial success response.
	 */
	public function test_partial_success_response() {
		$data = json_encode( [
			'success' => true,
			'partial_success' => true,
			'total_items' => 100,
			'successful_items' => 95,
			'failed_items' => 5,
			'failures' => [
				[
					'item_id' => 'variant_123',
					'error' => 'Invalid price format'
				],
				[
					'item_id' => 'variant_456',
					'error' => 'Missing required field: color'
				]
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertTrue( $response->partial_success );
		$this->assertEquals( 100, $response->total_items );
		$this->assertEquals( 95, $response->successful_items );
		$this->assertEquals( 5, $response->failed_items );
		$this->assertIsArray( $response->failures );
		$this->assertCount( 2, $response->failures );
		$this->assertEquals( 'variant_123', $response->failures[0]['item_id'] );
	}

	/**
	 * Test with rate limit information.
	 */
	public function test_with_rate_limit_info() {
		$data = json_encode( [
			'success' => true,
			'rate_limit' => [
				'remaining' => 950,
				'limit' => 1000,
				'reset_at' => '2023-12-01T11:00:00Z'
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertIsArray( $response->rate_limit );
		$this->assertEquals( 950, $response->rate_limit['remaining'] );
		$this->assertEquals( 1000, $response->rate_limit['limit'] );
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
				'message' => "Product group 'Test & Demo' <update> failed: \"Invalid\" characters & entities!",
				'details' => 'Field contains <script>alert("XSS")</script> which is not allowed'
			]
		] );
		$response = new Response( $data );
		
		$this->assertFalse( $response->success );
		$this->assertEquals( 
			"Product group 'Test & Demo' <update> failed: \"Invalid\" characters & entities!", 
			$response->error['message'] 
		);
		$this->assertEquals(
			'Field contains <script>alert("XSS")</script> which is not allowed',
			$response->error['details']
		);
	}

	/**
	 * Test with Unicode in response.
	 */
	public function test_unicode_in_response() {
		$data = json_encode( [
			'success' => true,
			'message' => 'Groupe de produits mis à jour 🎉',
			'updated_name' => '商品グループ テスト'
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'Groupe de produits mis à jour 🎉', $response->message );
		$this->assertEquals( '商品グループ テスト', $response->updated_name );
	}

	/**
	 * Test with very long ID.
	 */
	public function test_very_long_id() {
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
} 