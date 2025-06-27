<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\ProductCatalog\ProductFeeds\Read;

use WooCommerce\Facebook\API\ProductCatalog\ProductFeeds\Read\Request;
use WooCommerce\Facebook\API\Request as ApiRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ProductCatalog ProductFeeds Read Request class.
 *
 * @since 3.5.2
 */
class ProductFeedReadRequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		$request = new Request( 'test_feed_id' );
		$this->assertInstanceOf( ApiRequest::class, $request );
	}

	/**
	 * Test constructor with valid product feed ID.
	 */
	public function test_constructor_with_valid_feed_id() {
		$feed_id = '123456789';
		$request = new Request( $feed_id );

		// Test that the path is correctly constructed
		$expected_path = "/{$feed_id}/?fields=created_time,latest_upload,product_count,schedule,update_schedule,name";
		$this->assertEquals( $expected_path, $request->get_path() );
		
		// Test that the method is GET
		$this->assertEquals( 'GET', $request->get_method() );
	}

	/**
	 * Test constructor with different feed ID formats.
	 */
	public function test_constructor_with_various_feed_ids() {
		$test_cases = array(
			'numeric_id'        => '987654321',
			'alphanumeric_id'   => 'abc123def456',
			'with_underscores'  => 'feed_123_456',
			'with_dashes'       => 'feed-123-456',
			'long_id'           => '12345678901234567890',
			'short_id'          => '123',
		);

		foreach ( $test_cases as $description => $feed_id ) {
			$request = new Request( $feed_id );
			$expected_path = "/{$feed_id}/?fields=created_time,latest_upload,product_count,schedule,update_schedule,name";
			$this->assertEquals( $expected_path, $request->get_path(), "Failed for: {$description}" );
		}
	}

	/**
	 * Test constructor with empty feed ID.
	 */
	public function test_constructor_with_empty_feed_id() {
		$request = new Request( '' );
		
		$expected_path = "//?fields=created_time,latest_upload,product_count,schedule,update_schedule,name";
		$this->assertEquals( $expected_path, $request->get_path() );
	}

	/**
	 * Test constructor with special characters in feed ID.
	 */
	public function test_constructor_with_special_characters() {
		$special_ids = array(
			'with spaces'       => 'feed id with spaces',
			'with_symbols'      => 'feed@id#123',
			'with_unicode'      => 'feed_123_测试',
			'with_encoded'      => 'feed%20id%20123',
		);

		foreach ( $special_ids as $description => $feed_id ) {
			$request = new Request( $feed_id );
			$expected_path = "/{$feed_id}/?fields=created_time,latest_upload,product_count,schedule,update_schedule,name";
			$this->assertEquals( $expected_path, $request->get_path(), "Failed for: {$description}" );
		}
	}

	/**
	 * Test that request fields are correctly set.
	 */
	public function test_request_fields() {
		$request = new Request( 'test_feed' );
		$path = $request->get_path();
		
		// Verify all expected fields are present in the path
		$expected_fields = array(
			'created_time',
			'latest_upload',
			'product_count',
			'schedule',
			'update_schedule',
			'name',
		);
		
		foreach ( $expected_fields as $field ) {
			$this->assertStringContainsString( $field, $path, "Field '{$field}' not found in request path" );
		}
	}

	/**
	 * Test inherited methods from parent class.
	 */
	public function test_inherited_methods() {
		$request = new Request( 'test_feed_id' );
		
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
	 * Test URL structure consistency.
	 */
	public function test_url_structure() {
		$feed_id = 'test_feed_123';
		$request = new Request( $feed_id );
		$path = $request->get_path();
		
		// Check URL structure
		$this->assertStringStartsWith( '/', $path );
		$this->assertStringContainsString( $feed_id, $path );
		$this->assertStringContainsString( '/?fields=', $path );
		
		// Ensure no duplicate slashes
		$this->assertStringNotContainsString( '//', $path );
		
		// Ensure fields are comma-separated
		$this->assertMatchesRegularExpression( '/\?fields=[a-z_,]+$/', $path );
	}

	/**
	 * Test request with very long feed ID.
	 */
	public function test_very_long_feed_id() {
		$long_id = str_repeat( '1234567890', 20 ); // 200 characters
		$request = new Request( $long_id );
		
		$path = $request->get_path();
		$this->assertStringContainsString( $long_id, $path );
		$this->assertEquals( 'GET', $request->get_method() );
	}

	/**
	 * Test multiple instances don't interfere with each other.
	 */
	public function test_multiple_instances() {
		$request1 = new Request( 'feed_1' );
		$request2 = new Request( 'feed_2' );
		$request3 = new Request( 'feed_3' );
		
		// Ensure each has its own path
		$this->assertStringContainsString( 'feed_1', $request1->get_path() );
		$this->assertStringContainsString( 'feed_2', $request2->get_path() );
		$this->assertStringContainsString( 'feed_3', $request3->get_path() );
		
		// All should use GET method
		$this->assertEquals( 'GET', $request1->get_method() );
		$this->assertEquals( 'GET', $request2->get_method() );
		$this->assertEquals( 'GET', $request3->get_method() );
	}
} 