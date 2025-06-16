<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\ProductCatalog\ItemsBatch\Create;

use WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog ItemsBatch Create Response class.
 *
 * @since 3.5.2
 */
class ItemsBatchCreateResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test instantiation with handles data.
	 */
	public function test_instantiation_with_handles_data() {
		$data = json_encode( [ 
			'handles' => [ 'handle1', 'handle2', 'handle3' ] 
		] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing handles property.
	 */
	public function test_handles_property_access() {
		$handles = [ 'batch_123', 'batch_456', 'batch_789' ];
		$data = json_encode( [ 'handles' => $handles ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->handles );
		$this->assertEquals( $handles, $response->handles );
		$this->assertCount( 3, $response->handles );
		
		// Test with missing handles property
		$data_without_handles = json_encode( [ 'validation_status' => [ 'errors' => 0 ] ] );
		$response_without_handles = new Response( $data_without_handles );
		$this->assertNull( $response_without_handles->handles );
	}

	/**
	 * Test accessing validation_status property.
	 */
	public function test_validation_status_property_access() {
		$validation_status = [
			'errors' => 0,
			'warnings' => 2
		];
		$data = json_encode( [ 'validation_status' => $validation_status ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->validation_status );
		$this->assertEquals( $validation_status, $response->validation_status );
		$this->assertEquals( 0, $response->validation_status['errors'] );
		$this->assertEquals( 2, $response->validation_status['warnings'] );
		
		// Test with missing validation_status property
		$data_without_validation = json_encode( [ 'handles' => [ 'handle1' ] ] );
		$response_without_validation = new Response( $data_without_validation );
		$this->assertNull( $response_without_validation->validation_status );
	}

	/**
	 * Test with both handles and validation_status.
	 */
	public function test_handles_and_validation_status() {
		$handles = [ 'handle_a', 'handle_b' ];
		$validation_status = [
			'errors' => 1,
			'warnings' => 3,
			'total' => 100
		];
		$data = json_encode( [ 
			'handles' => $handles,
			'validation_status' => $validation_status
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $handles, $response->handles );
		$this->assertEquals( $validation_status, $response->validation_status );
	}

	/**
	 * Test with empty handles array.
	 */
	public function test_empty_handles_array() {
		$data = json_encode( [ 'handles' => [] ] );
		$response = new Response( $data );
		
		$this->assertIsArray( $response->handles );
		$this->assertEmpty( $response->handles );
	}

	/**
	 * Test with empty object.
	 */
	public function test_empty_object() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->handles );
		$this->assertNull( $response->validation_status );
	}

	/**
	 * Test with complex validation_status structure.
	 */
	public function test_complex_validation_status() {
		$validation_status = [
			'errors' => 5,
			'warnings' => 10,
			'total_processed' => 100,
			'total_failed' => 5,
			'details' => [
				'invalid_price' => 3,
				'missing_image' => 2
			]
		];
		$data = json_encode( [ 'validation_status' => $validation_status ] );
		$response = new Response( $data );
		
		$this->assertEquals( 5, $response->validation_status['errors'] );
		$this->assertEquals( 10, $response->validation_status['warnings'] );
		$this->assertEquals( 100, $response->validation_status['total_processed'] );
		$this->assertIsArray( $response->validation_status['details'] );
		$this->assertEquals( 3, $response->validation_status['details']['invalid_price'] );
	}

	/**
	 * Test with large number of handles.
	 */
	public function test_large_number_of_handles() {
		$handles = array_map( function( $i ) {
			return "handle_$i";
		}, range( 1, 1000 ) );
		
		$data = json_encode( [ 'handles' => $handles ] );
		$response = new Response( $data );
		
		$this->assertCount( 1000, $response->handles );
		$this->assertEquals( 'handle_1', $response->handles[0] );
		$this->assertEquals( 'handle_1000', $response->handles[999] );
	}

	/**
	 * Test with additional undocumented properties.
	 */
	public function test_additional_properties() {
		$data = json_encode( [
			'handles' => [ 'h1', 'h2' ],
			'validation_status' => [ 'errors' => 0 ],
			'success' => true,
			'batch_id' => 'batch_12345',
			'timestamp' => '2023-01-01T00:00:00Z'
		] );
		$response = new Response( $data );
		
		// Documented properties
		$this->assertEquals( [ 'h1', 'h2' ], $response->handles );
		$this->assertEquals( [ 'errors' => 0 ], $response->validation_status );
		
		// Additional properties should also be accessible
		$this->assertTrue( $response->success );
		$this->assertEquals( 'batch_12345', $response->batch_id );
		$this->assertEquals( '2023-01-01T00:00:00Z', $response->timestamp );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [ 
			'handles' => [ 'handle1', 'handle2' ],
			'validation_status' => [ 'errors' => 1 ]
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertEquals( [ 'handle1', 'handle2' ], $response['handles'] );
		$this->assertEquals( [ 'errors' => 1 ], $response['validation_status'] );
		
		// Test isset
		$this->assertTrue( isset( $response['handles'] ) );
		$this->assertTrue( isset( $response['validation_status'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test with null values.
	 */
	public function test_null_values() {
		$data = json_encode( [ 
			'handles' => null,
			'validation_status' => null
		] );
		$response = new Response( $data );
		
		$this->assertNull( $response->handles );
		$this->assertNull( $response->validation_status );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$batch_id = 'batch_999';
		$data = json_encode( [ 'id' => $batch_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $batch_id, $response->get_id() );
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
		
		// Should have no methods of its own
		$this->assertCount( 0, $own_methods );
	}

	/**
	 * Test with special characters in handles.
	 */
	public function test_special_characters_in_handles() {
		$handles = [
			"handle_with_'quotes'",
			'handle_with_"double"',
			'handle_with_<brackets>',
			'handle_with_émojis_🎉'
		];
		$data = json_encode( [ 'handles' => $handles ] );
		$response = new Response( $data );
		
		$this->assertEquals( $handles, $response->handles );
		$this->assertCount( 4, $response->handles );
	}

	/**
	 * Test validation_status with string values.
	 */
	public function test_validation_status_with_string_values() {
		$validation_status = [
			'errors' => '5',
			'warnings' => '10',
			'status' => 'completed'
		];
		$data = json_encode( [ 'validation_status' => $validation_status ] );
		$response = new Response( $data );
		
		$this->assertEquals( '5', $response->validation_status['errors'] );
		$this->assertEquals( '10', $response->validation_status['warnings'] );
		$this->assertEquals( 'completed', $response->validation_status['status'] );
	}
} 