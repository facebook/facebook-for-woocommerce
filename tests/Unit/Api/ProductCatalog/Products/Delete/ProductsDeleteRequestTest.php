<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\ProductCatalog\Products\Delete;

use WooCommerce\Facebook\API\ProductCatalog\Products\Delete\Request;
use WooCommerce\Facebook\API\Request as ApiRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog Products Delete Request class.
 *
 * @since 3.5.2
 */
class ProductsDeleteRequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Request::class ) );
	}

	/**
	 * Test that the class extends ApiRequest.
	 */
	public function test_class_extends_api_request() {
		$request = new Request( 'test_product_id' );
		$this->assertInstanceOf( ApiRequest::class, $request );
	}

	/**
	 * Test constructor with valid product ID.
	 */
	public function test_constructor_with_valid_product_id() {
		$product_id = '123456789';
		$request = new Request( $product_id );

		// Test that the path is correctly constructed
		$expected_path = "/{$product_id}";
		$this->assertEquals( $expected_path, $request->get_path() );
		
		// Test that the method is DELETE
		$this->assertEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test constructor with different product ID formats.
	 */
	public function test_constructor_with_various_product_ids() {
		$test_cases = array(
			'numeric_id'        => '987654321',
			'alphanumeric_id'   => 'abc123def456',
			'with_underscores'  => 'product_123_456',
			'with_dashes'       => 'product-123-456',
			'long_id'           => '12345678901234567890',
			'short_id'          => '123',
		);

		foreach ( $test_cases as $description => $product_id ) {
			$request = new Request( $product_id );
			$expected_path = "/{$product_id}";
			$this->assertEquals( $expected_path, $request->get_path(), "Failed for: {$description}" );
			$this->assertEquals( 'DELETE', $request->get_method(), "Method should be DELETE for: {$description}" );
		}
	}

	/**
	 * Test constructor with empty product ID.
	 */
	public function test_constructor_with_empty_product_id() {
		$request = new Request( '' );
		
		$expected_path = "/";
		$this->assertEquals( $expected_path, $request->get_path() );
		$this->assertEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test constructor with special characters in product ID.
	 */
	public function test_constructor_with_special_characters() {
		$special_ids = array(
			'with spaces'       => 'product id with spaces',
			'with_symbols'      => 'product@id#123',
			'with_unicode'      => 'product_123_测试',
			'with_encoded'      => 'product%20id%20123',
			'with_slashes'      => 'product/123/456',
		);

		foreach ( $special_ids as $description => $product_id ) {
			$request = new Request( $product_id );
			$expected_path = "/{$product_id}";
			$this->assertEquals( $expected_path, $request->get_path(), "Failed for: {$description}" );
		}
	}

	/**
	 * Test inherited methods from parent class.
	 */
	public function test_inherited_methods() {
		$request = new Request( 'test_product_id' );
		
		// Test get_params (should be empty by default)
		$this->assertIsArray( $request->get_params() );
		$this->assertEmpty( $request->get_params() );
		
		// Test get_data (should be empty by default)
		$this->assertIsArray( $request->get_data() );
		$this->assertEmpty( $request->get_data() );
		
		// Test retry methods
		$this->assertEquals( 0, $request->get_retry_count() );
		$this->assertIsInt( $request->get_retry_limit() );
		$this->assertIsArray( $request->get_retry_codes() );
	}

	/**
	 * Test request with very long product ID.
	 */
	public function test_very_long_product_id() {
		$long_id = str_repeat( '1234567890', 20 ); // 200 characters
		$request = new Request( $long_id );
		
		$path = $request->get_path();
		$this->assertStringContainsString( $long_id, $path );
		$this->assertEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test multiple instances don't interfere with each other.
	 */
	public function test_multiple_instances() {
		$request1 = new Request( 'product_1' );
		$request2 = new Request( 'product_2' );
		$request3 = new Request( 'product_3' );
		
		// Ensure each has its own path
		$this->assertEquals( '/product_1', $request1->get_path() );
		$this->assertEquals( '/product_2', $request2->get_path() );
		$this->assertEquals( '/product_3', $request3->get_path() );
		
		// All should use DELETE method
		$this->assertEquals( 'DELETE', $request1->get_method() );
		$this->assertEquals( 'DELETE', $request2->get_method() );
		$this->assertEquals( 'DELETE', $request3->get_method() );
	}
} 