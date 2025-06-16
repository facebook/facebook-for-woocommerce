<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ProductFeedUploads\Read;

use WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Read\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductFeedUploads Read Response class.
 *
 * @since 3.5.2
 */
class ProductFeedUploadReadResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		$data = json_encode( [ 
			'id' => 'upload_123',
			'data' => [
				'error_count' => 0,
				'warning_count' => 2
			]
		] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing data property with complete feed upload structure.
	 */
	public function test_data_property_access_complete_structure() {
		$upload_data = [
			'error_count' => 3,
			'warning_count' => 7,
			'num_detected_items' => 500,
			'num_persisted_items' => 490,
			'url' => 'https://shop.example.com/products/feed.xml',
			'end_time' => '2023-12-25T15:30:45+0000',
			'start_time' => '2023-12-25T15:25:30+0000',
			'status' => 'completed'
		];
		$data = json_encode( [ 
			'id' => 'feed_upload_789',
			'data' => $upload_data 
		] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertEquals( $upload_data, $response->data );
		$this->assertEquals( 'feed_upload_789', $response->id );
		$this->assertEquals( 3, $response->data['error_count'] );
		$this->assertEquals( 7, $response->data['warning_count'] );
		$this->assertEquals( 500, $response->data['num_detected_items'] );
		$this->assertEquals( 490, $response->data['num_persisted_items'] );
		$this->assertEquals( 'https://shop.example.com/products/feed.xml', $response->data['url'] );
		$this->assertEquals( '2023-12-25T15:30:45+0000', $response->data['end_time'] );
		$this->assertEquals( '2023-12-25T15:25:30+0000', $response->data['start_time'] );
		$this->assertEquals( 'completed', $response->data['status'] );
	}

	/**
	 * Test with missing data property.
	 */
	public function test_missing_data_property() {
		$data = json_encode( [ 'id' => 'upload_456' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->data );
	}

	/**
	 * Test with empty data array.
	 */
	public function test_empty_data_array() {
		$data = json_encode( [ 'data' => [] ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->data );
		$this->assertEmpty( $response->data );
	}

	/**
	 * Test with empty object.
	 */
	public function test_empty_object() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->data );
	}

	/**
	 * Test with edge case numeric values (zero and large numbers).
	 */
	public function test_edge_case_numeric_values() {
		// Test zero values
		$data = json_encode( [
			'data' => [
				'error_count' => 0,
				'warning_count' => 0,
				'num_detected_items' => 0,
				'num_persisted_items' => 0
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 0, $response->data['error_count'] );
		$this->assertEquals( 0, $response->data['warning_count'] );
		$this->assertEquals( 0, $response->data['num_detected_items'] );
		$this->assertEquals( 0, $response->data['num_persisted_items'] );
		
		// Test large numbers
		$data = json_encode( [
			'data' => [
				'error_count' => 999999,
				'warning_count' => 1234567,
				'num_detected_items' => 10000000,
				'num_persisted_items' => 9999999
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 999999, $response->data['error_count'] );
		$this->assertEquals( 1234567, $response->data['warning_count'] );
		$this->assertEquals( 10000000, $response->data['num_detected_items'] );
		$this->assertEquals( 9999999, $response->data['num_persisted_items'] );
	}

	/**
	 * Test with special characters in URL.
	 */
	public function test_special_characters_in_url() {
		$special_url = 'https://example.com/feed.xml?param=value&special=<test>&encoded=%20space';
		$data = json_encode( [
			'data' => [
				'url' => $special_url
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $special_url, $response->data['url'] );
	}

	/**
	 * Test with Unicode in URL.
	 */
	public function test_unicode_in_url() {
		$unicode_url = 'https://example.com/商品/feed.xml';
		$data = json_encode( [
			'data' => [
				'url' => $unicode_url
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $unicode_url, $response->data['url'] );
	}

	/**
	 * Test with null values in data.
	 */
	public function test_null_values_in_data() {
		$data = json_encode( [
			'data' => [
				'error_count' => null,
				'warning_count' => null,
				'url' => null,
				'end_time' => null
			]
		] );
		$response = new Response( $data );
		
		$this->assertNull( $response->data['error_count'] );
		$this->assertNull( $response->data['warning_count'] );
		$this->assertNull( $response->data['url'] );
		$this->assertNull( $response->data['end_time'] );
	}

	/**
	 * Test with string numbers.
	 */
	public function test_string_numbers() {
		$data = json_encode( [
			'data' => [
				'error_count' => '15',
				'warning_count' => '25',
				'num_detected_items' => '1000',
				'num_persisted_items' => '975'
			]
		] );
		$response = new Response( $data );
		
		// Values should be preserved as strings
		$this->assertEquals( '15', $response->data['error_count'] );
		$this->assertEquals( '25', $response->data['warning_count'] );
		$this->assertEquals( '1000', $response->data['num_detected_items'] );
		$this->assertEquals( '975', $response->data['num_persisted_items'] );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [
			'id' => 'upload_array_test',
			'data' => [
				'error_count' => 2,
				'warning_count' => 4
			]
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertEquals( 'upload_array_test', $response['id'] );
		$this->assertEquals( [ 'error_count' => 2, 'warning_count' => 4 ], $response['data'] );
		
		// Test isset
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertTrue( isset( $response['data'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$upload_id = 'feed_upload_get_id_test';
		$data = json_encode( [ 'id' => $upload_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $upload_id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_method_missing_id() {
		$data = json_encode( [ 'data' => [ 'error_count' => 0 ] ] );
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
	 * Test with additional undocumented properties.
	 */
	public function test_additional_properties() {
		$data = json_encode( [
			'id' => 'upload_extra',
			'data' => [
				'error_count' => 1,
				'warning_count' => 3
			],
			'success' => true,
			'timestamp' => '2023-01-01T00:00:00Z',
			'feed_id' => 'feed_123'
		] );
		$response = new Response( $data );
		
		// Documented properties
		$this->assertEquals( 'upload_extra', $response->id );
		$this->assertEquals( [ 'error_count' => 1, 'warning_count' => 3 ], $response->data );
		
		// Additional properties should also be accessible
		$this->assertTrue( $response->success );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->timestamp );
		$this->assertEquals( 'feed_123', $response->feed_id );
	}

	/**
	 * Test with nested data structures.
	 */
	public function test_nested_data_structures() {
		$data = json_encode( [
			'data' => [
				'error_count' => 5,
				'errors' => [
					[
						'message' => 'Invalid price format',
						'severity' => 'error',
						'product_id' => '123'
					],
					[
						'message' => 'Missing image',
						'severity' => 'error',
						'product_id' => '456'
					]
				],
				'warnings' => [
					[
						'message' => 'Description too short',
						'severity' => 'warning',
						'product_id' => '789'
					]
				]
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 5, $response->data['error_count'] );
		$this->assertIsArray( $response->data['errors'] );
		$this->assertCount( 2, $response->data['errors'] );
		$this->assertEquals( 'Invalid price format', $response->data['errors'][0]['message'] );
		$this->assertIsArray( $response->data['warnings'] );
		$this->assertCount( 1, $response->data['warnings'] );
	}

	/**
	 * Test with various date formats.
	 */
	public function test_various_date_formats() {
		$data = json_encode( [
			'data' => [
				'end_time' => '2023-12-31T23:59:59+0000',
				'start_time' => '2023-12-31T23:00:00Z',
				'created_at' => '2023-12-31 23:00:00',
				'updated_at' => 1704067199 // Unix timestamp
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '2023-12-31T23:59:59+0000', $response->data['end_time'] );
		$this->assertEquals( '2023-12-31T23:00:00Z', $response->data['start_time'] );
		$this->assertEquals( '2023-12-31 23:00:00', $response->data['created_at'] );
		$this->assertEquals( 1704067199, $response->data['updated_at'] );
	}

	/**
	 * Test with empty string values.
	 */
	public function test_empty_string_values() {
		$data = json_encode( [
			'data' => [
				'url' => '',
				'end_time' => '',
				'status' => ''
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '', $response->data['url'] );
		$this->assertEquals( '', $response->data['end_time'] );
		$this->assertEquals( '', $response->data['status'] );
	}

	/**
	 * Test with boolean values in data.
	 */
	public function test_boolean_values_in_data() {
		$data = json_encode( [
			'data' => [
				'is_complete' => true,
				'has_errors' => false,
				'requires_review' => true
			]
		] );
		$response = new Response( $data );
		
		$this->assertTrue( $response->data['is_complete'] );
		$this->assertFalse( $response->data['has_errors'] );
		$this->assertTrue( $response->data['requires_review'] );
	}

	/**
	 * Test with float values.
	 */
	public function test_float_values() {
		$data = json_encode( [
			'data' => [
				'success_rate' => 98.5,
				'processing_time' => 123.456,
				'average_item_size' => 0.0025
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( 98.5, $response->data['success_rate'] );
		$this->assertEquals( 123.456, $response->data['processing_time'] );
		$this->assertEquals( 0.0025, $response->data['average_item_size'] );
	}
}
