<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Traits;

use WooCommerce\Facebook\API\Traits\Idempotent_Request;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Test class that uses the Idempotent_Request trait.
 */
class TestIdempotentRequestClass {
	use Idempotent_Request;
}

/**
 * Unit tests for Idempotent_Request trait.
 *
 * @since 3.5.2
 */
class IdempotentRequestTraitTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the trait can be used in a class.
	 */
	public function test_trait_can_be_used() {
		$test_object = new TestIdempotentRequestClass();
		
		$this->assertInstanceOf( TestIdempotentRequestClass::class, $test_object );
		$this->assertTrue( method_exists( $test_object, 'get_idempotency_key' ) );
	}

	/**
	 * Test get_idempotency_key generates a UUID.
	 */
	public function test_get_idempotency_key_generates_uuid() {
		$test_object = new TestIdempotentRequestClass();
		$key = $test_object->get_idempotency_key();
		
		$this->assertIsString( $key );
		$this->assertNotEmpty( $key );
		
		// Validate UUID v4 format
		$uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		$this->assertMatchesRegularExpression( $uuid_pattern, $key );
	}

	/**
	 * Test idempotency key persistence.
	 */
	public function test_idempotency_key_persistence() {
		$test_object = new TestIdempotentRequestClass();
		
		$key1 = $test_object->get_idempotency_key();
		$key2 = $test_object->get_idempotency_key();
		$key3 = $test_object->get_idempotency_key();
		
		// Same key should be returned on multiple calls
		$this->assertEquals( $key1, $key2 );
		$this->assertEquals( $key2, $key3 );
	}

	/**
	 * Test different instances generate different keys.
	 */
	public function test_different_instances_generate_different_keys() {
		$object1 = new TestIdempotentRequestClass();
		$object2 = new TestIdempotentRequestClass();
		$object3 = new TestIdempotentRequestClass();
		
		$key1 = $object1->get_idempotency_key();
		$key2 = $object2->get_idempotency_key();
		$key3 = $object3->get_idempotency_key();
		
		// Each instance should have a unique key
		$this->assertNotEquals( $key1, $key2 );
		$this->assertNotEquals( $key2, $key3 );
		$this->assertNotEquals( $key1, $key3 );
	}

	/**
	 * Test UUID format compliance.
	 */
	public function test_uuid_format_compliance() {
		$test_object = new TestIdempotentRequestClass();
		$key = $test_object->get_idempotency_key();
		
		// Split UUID into parts
		$parts = explode( '-', $key );
		
		$this->assertCount( 5, $parts );
		$this->assertEquals( 8, strlen( $parts[0] ) );
		$this->assertEquals( 4, strlen( $parts[1] ) );
		$this->assertEquals( 4, strlen( $parts[2] ) );
		$this->assertEquals( 4, strlen( $parts[3] ) );
		$this->assertEquals( 12, strlen( $parts[4] ) );
		
		// Version 4 UUID should have '4' as the first character of the third group
		$this->assertEquals( '4', substr( $parts[2], 0, 1 ) );
		
		// Variant bits should be correct (8, 9, a, or b)
		$variant_char = strtolower( substr( $parts[3], 0, 1 ) );
		$this->assertContains( $variant_char, [ '8', '9', 'a', 'b' ] );
	}

	/**
	 * Test property visibility.
	 */
	public function test_property_visibility() {
		$reflection = new \ReflectionClass( TestIdempotentRequestClass::class );
		
		// Check that idempotency_key property exists and is protected
		$this->assertTrue( $reflection->hasProperty( 'idempotency_key' ) );
		
		$property = $reflection->getProperty( 'idempotency_key' );
		$this->assertTrue( $property->isProtected() );
	}

	/**
	 * Test method visibility.
	 */
	public function test_method_visibility() {
		$reflection = new \ReflectionClass( TestIdempotentRequestClass::class );
		
		// Check that get_idempotency_key method exists and is public
		$this->assertTrue( $reflection->hasMethod( 'get_idempotency_key' ) );
		
		$method = $reflection->getMethod( 'get_idempotency_key' );
		$this->assertTrue( $method->isPublic() );
	}

	/**
	 * Test multiple trait usage in same class.
	 */
	public function test_trait_reusability() {
		// Create multiple test classes using the trait
		$objects = [];
		$keys = [];
		
		for ( $i = 0; $i < 10; $i++ ) {
			$objects[] = new TestIdempotentRequestClass();
			$keys[] = $objects[ $i ]->get_idempotency_key();
		}
		
		// All keys should be unique
		$unique_keys = array_unique( $keys );
		$this->assertCount( 10, $unique_keys );
	}

	/**
	 * Test that wp_generate_uuid4 is called.
	 */
	public function test_uses_wp_generate_uuid4() {
		// This test verifies that the trait uses WordPress's UUID generation
		$test_object = new TestIdempotentRequestClass();
		
		// Get the key
		$key = $test_object->get_idempotency_key();
		
		// Generate a WordPress UUID4 to compare format
		$wp_uuid = wp_generate_uuid4();
		
		// Both should have the same format
		$this->assertEquals( strlen( $wp_uuid ), strlen( $key ) );
		$this->assertEquals( 36, strlen( $key ) ); // UUID v4 is always 36 characters
	}

	/**
	 * Test lazy initialization of idempotency key.
	 */
	public function test_lazy_initialization() {
		$test_object = new TestIdempotentRequestClass();
		
		// Use reflection to check the property before calling the method
		$reflection = new \ReflectionClass( $test_object );
		$property = $reflection->getProperty( 'idempotency_key' );
		$property->setAccessible( true );
		
		// Property should be uninitialized/empty before first call
		$initial_value = $property->getValue( $test_object );
		$this->assertEmpty( $initial_value );
		
		// Call the method
		$key = $test_object->get_idempotency_key();
		
		// Property should now be set
		$stored_value = $property->getValue( $test_object );
		$this->assertEquals( $key, $stored_value );
	}

	/**
	 * Test manual property override.
	 */
	public function test_manual_property_override() {
		$test_object = new TestIdempotentRequestClass();
		
		// Use reflection to manually set the property
		$reflection = new \ReflectionClass( $test_object );
		$property = $reflection->getProperty( 'idempotency_key' );
		$property->setAccessible( true );
		
		$manual_key = 'manually-set-key-12345';
		$property->setValue( $test_object, $manual_key );
		
		// get_idempotency_key should return the manually set value
		$retrieved_key = $test_object->get_idempotency_key();
		$this->assertEquals( $manual_key, $retrieved_key );
	}

	/**
	 * Test empty key regeneration.
	 */
	public function test_empty_key_regeneration() {
		$test_object = new TestIdempotentRequestClass();
		
		// Get initial key
		$initial_key = $test_object->get_idempotency_key();
		
		// Use reflection to clear the property
		$reflection = new \ReflectionClass( $test_object );
		$property = $reflection->getProperty( 'idempotency_key' );
		$property->setAccessible( true );
		$property->setValue( $test_object, '' );
		
		// Getting the key again should generate a new one
		$new_key = $test_object->get_idempotency_key();
		
		$this->assertNotEmpty( $new_key );
		$this->assertNotEquals( $initial_key, $new_key );
		
		// Validate it's still a proper UUID
		$uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		$this->assertMatchesRegularExpression( $uuid_pattern, $new_key );
	}
} 