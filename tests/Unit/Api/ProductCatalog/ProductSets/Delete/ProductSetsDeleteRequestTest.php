<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\ProductCatalog\ProductSets\Delete;

use WooCommerce\Facebook\API\ProductCatalog\ProductSets\Delete\Request;
use WooCommerce\Facebook\API\Request as ApiRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog ProductSets Delete Request class.
 *
 * @since 3.5.2
 */
class ProductSetsDeleteRequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		$request = new Request( 'test_product_set_id' );
		$this->assertInstanceOf( ApiRequest::class, $request );
	}

	/**
	 * Test constructor with valid product set ID and default deletion flag.
	 */
	public function test_constructor_with_default_deletion_flag() {
		$product_set_id = '123456789';
		$request = new Request( $product_set_id );

		// Test that the path is correctly constructed without the deletion parameter
		$expected_path = "/{$product_set_id}";
		$this->assertEquals( $expected_path, $request->get_path() );
		
		// Test that the method is DELETE
		$this->assertEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test constructor with live deletion enabled.
	 */
	public function test_constructor_with_live_deletion_enabled() {
		$product_set_id = '123456789';
		$request = new Request( $product_set_id, true );

		// Test that the path includes the deletion parameter
		$expected_path = "/{$product_set_id}?allow_live_product_set_deletion=true";
		$this->assertEquals( $expected_path, $request->get_path() );
		
		// Test that the method is DELETE
		$this->assertEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test constructor with live deletion explicitly disabled.
	 */
	public function test_constructor_with_live_deletion_disabled() {
		$product_set_id = '123456789';
		$request = new Request( $product_set_id, false );

		// Test that the path does not include the deletion parameter when false
		$expected_path = "/{$product_set_id}";
		$this->assertEquals( $expected_path, $request->get_path() );
		
		// Test that the method is DELETE
		$this->assertEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test constructor with different product set ID formats.
	 */
	public function test_constructor_with_various_product_set_ids() {
		$test_cases = array(
			'numeric_id'        => '987654321',
			'alphanumeric_id'   => 'abc123def456',
			'with_underscores'  => 'set_123_456',
			'with_dashes'       => 'set-123-456',
			'long_id'           => '12345678901234567890',
			'short_id'          => '123',
		);

		foreach ( $test_cases as $description => $product_set_id ) {
			// Test without live deletion
			$request = new Request( $product_set_id );
			$expected_path = "/{$product_set_id}";
			$this->assertEquals( $expected_path, $request->get_path(), "Failed for: {$description}" );
			
			// Test with live deletion
			$request_live = new Request( $product_set_id, true );
			$expected_path_live = "/{$product_set_id}?allow_live_product_set_deletion=true";
			$this->assertEquals( $expected_path_live, $request_live->get_path(), "Failed for live deletion: {$description}" );
		}
	}

	/**
	 * Test constructor with empty product set ID.
	 */
	public function test_constructor_with_empty_product_set_id() {
		$request = new Request( '' );
		
		$expected_path = "/";
		$this->assertEquals( $expected_path, $request->get_path() );
		$this->assertEquals( 'DELETE', $request->get_method() );
		
		// Test with live deletion enabled
		$request_live = new Request( '', true );
		$expected_path_live = "/?allow_live_product_set_deletion=true";
		$this->assertEquals( $expected_path_live, $request_live->get_path() );
	}

	/**
	 * Test constructor with special characters in product set ID.
	 */
	public function test_constructor_with_special_characters() {
		$special_ids = array(
			'with spaces'       => 'set id with spaces',
			'with_symbols'      => 'set@id#123',
			'with_unicode'      => 'set_123_测试',
			'with_encoded'      => 'set%20id%20123',
		);

		foreach ( $special_ids as $description => $product_set_id ) {
			$request = new Request( $product_set_id );
			$expected_path = "/{$product_set_id}";
			$this->assertEquals( $expected_path, $request->get_path(), "Failed for: {$description}" );
		}
	}

	/**
	 * Test inherited methods from parent class.
	 */
	public function test_inherited_methods() {
		$request = new Request( 'test_product_set_id' );
		
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
	 * Test multiple instances with different configurations.
	 */
	public function test_multiple_instances() {
		$request1 = new Request( 'set_1', false );
		$request2 = new Request( 'set_2', true );
		$request3 = new Request( 'set_3' );
		
		// Ensure each has its own path
		$this->assertEquals( '/set_1', $request1->get_path() );
		$this->assertEquals( '/set_2?allow_live_product_set_deletion=true', $request2->get_path() );
		$this->assertEquals( '/set_3', $request3->get_path() );
		
		// All should use DELETE method
		$this->assertEquals( 'DELETE', $request1->get_method() );
		$this->assertEquals( 'DELETE', $request2->get_method() );
		$this->assertEquals( 'DELETE', $request3->get_method() );
	}
} 