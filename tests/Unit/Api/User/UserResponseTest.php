<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\User;

use WooCommerce\Facebook\API\User\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for User Response class.
 *
 * @since 3.5.2
 */
class UserResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test instantiation with user data.
	 */
	public function test_instantiation_with_user_data() {
		$data = json_encode( [ 'id' => '123456789', 'name' => 'John Doe' ] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing id property.
	 */
	public function test_id_property_access() {
		$user_id = '987654321';
		$data = json_encode( [ 'id' => $user_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $user_id, $response->id );
	}

	/**
	 * Test accessing name property.
	 */
	public function test_name_property_access() {
		$user_name = 'Jane Smith';
		$data = json_encode( [ 'name' => $user_name ] );
		$response = new Response( $data );
		
		$this->assertEquals( $user_name, $response->name );
	}

	/**
	 * Test accessing both id and name properties.
	 */
	public function test_id_and_name_properties() {
		$user_id = '1234567890';
		$user_name = 'Test User';
		$data = json_encode( [ 'id' => $user_id, 'name' => $user_name ] );
		$response = new Response( $data );
		
		$this->assertEquals( $user_id, $response->id );
		$this->assertEquals( $user_name, $response->name );
	}

	/**
	 * Test with missing id property.
	 */
	public function test_missing_id_property() {
		$data = json_encode( [ 'name' => 'User Without ID' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->id );
		$this->assertEquals( 'User Without ID', $response->name );
	}

	/**
	 * Test with missing name property.
	 */
	public function test_missing_name_property() {
		$data = json_encode( [ 'id' => '999' ] );
		$response = new Response( $data );
		
		$this->assertEquals( '999', $response->id );
		$this->assertNull( $response->name );
	}

	/**
	 * Test with empty object.
	 */
	public function test_empty_object() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->id );
		$this->assertNull( $response->name );
	}

	/**
	 * Test with additional properties.
	 */
	public function test_additional_properties() {
		$data = json_encode( [
			'id' => '123',
			'name' => 'Test User',
			'email' => 'test@example.com',
			'picture' => 'https://example.com/pic.jpg'
		] );
		$response = new Response( $data );
		
		// Documented properties should work
		$this->assertEquals( '123', $response->id );
		$this->assertEquals( 'Test User', $response->name );
		
		// Additional properties should also be accessible via magic getter
		$this->assertEquals( 'test@example.com', $response->email );
		$this->assertEquals( 'https://example.com/pic.jpg', $response->picture );
	}

	/**
	 * Test with special characters in name.
	 */
	public function test_special_characters_in_name() {
		$special_name = "O'Brien & Co. <Test> \"Quotes\" 'Apostrophes'";
		$data = json_encode( [ 'id' => '456', 'name' => $special_name ] );
		$response = new Response( $data );
		
		$this->assertEquals( $special_name, $response->name );
	}

	/**
	 * Test with Unicode characters in name.
	 */
	public function test_unicode_characters_in_name() {
		$unicode_name = '李明 (Li Ming) 🌟 émojis';
		$data = json_encode( [ 'id' => '789', 'name' => $unicode_name ] );
		$response = new Response( $data );
		
		$this->assertEquals( $unicode_name, $response->name );
	}

	/**
	 * Test with numeric id as integer.
	 */
	public function test_numeric_id_as_integer() {
		$data = json_encode( [ 'id' => 12345, 'name' => 'Numeric ID User' ] );
		$response = new Response( $data );
		
		// Should be accessible even if stored as integer
		$this->assertEquals( 12345, $response->id );
	}

	/**
	 * Test with very long id.
	 */
	public function test_very_long_id() {
		$long_id = '1234567890123456789012345678901234567890';
		$data = json_encode( [ 'id' => $long_id, 'name' => 'Long ID User' ] );
		$response = new Response( $data );
		
		$this->assertEquals( $long_id, $response->id );
	}

	/**
	 * Test with null values.
	 */
	public function test_null_values() {
		$data = json_encode( [ 'id' => null, 'name' => null ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->id );
		$this->assertNull( $response->name );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [ 'id' => '111', 'name' => 'Array Access User' ] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertEquals( '111', $response['id'] );
		$this->assertEquals( 'Array Access User', $response['name'] );
		
		// Test isset
		$this->assertTrue( isset( $response['id'] ) );
		$this->assertTrue( isset( $response['name'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$user_id = '555666777';
		$data = json_encode( [ 'id' => $user_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $user_id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_method_missing_id() {
		$data = json_encode( [ 'name' => 'No ID User' ] );
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
	 * Test response with nested user data.
	 */
	public function test_nested_user_data() {
		$data = json_encode( [
			'id' => '999',
			'name' => 'Nested User',
			'location' => [
				'city' => 'San Francisco',
				'country' => 'USA'
			]
		] );
		$response = new Response( $data );
		
		$this->assertEquals( '999', $response->id );
		$this->assertEquals( 'Nested User', $response->name );
		$this->assertIsArray( $response->location );
		$this->assertEquals( 'San Francisco', $response->location['city'] );
	}
} 