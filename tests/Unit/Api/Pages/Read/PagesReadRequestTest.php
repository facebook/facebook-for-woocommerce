<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Pages\Read;

use WooCommerce\Facebook\API\Pages\Read\Request;
use WooCommerce\Facebook\API\Request as ApiRequest;
use WooCommerce\Facebook\Framework\Api\JSONRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Pages Read Request class.
 *
 * @since 3.5.2
 */
class PagesReadRequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Request class exists and can be instantiated.
	 */
	public function test_request_class_exists() {
		$this->assertTrue( class_exists( Request::class ) );
	}

	/**
	 * Test that Request extends the API Request class.
	 */
	public function test_request_extends_api_request() {
		$request = new Request( '123456789' );
		$this->assertInstanceOf( ApiRequest::class, $request );
	}

	/**
	 * Test that Request extends JSONRequest through inheritance.
	 */
	public function test_request_extends_json_request() {
		$request = new Request( '123456789' );
		$this->assertInstanceOf( JSONRequest::class, $request );
	}

	/**
	 * Test request constructor with page ID.
	 */
	public function test_request_constructor_with_page_id() {
		$pageId = '123456789';
		$request = new Request( $pageId );
		
		$this->assertEquals( "/{$pageId}/?fields=name,link", $request->get_path() );
		$this->assertEquals( 'GET', $request->get_method() );
	}

	/**
	 * Test request with different page ID formats.
	 */
	public function test_request_with_different_page_id_formats() {
		// Numeric string ID
		$request1 = new Request( '987654321' );
		$this->assertEquals( '/987654321/?fields=name,link', $request1->get_path() );
		
		// Very long ID
		$longId = '123456789012345678901234567890';
		$request2 = new Request( $longId );
		$this->assertEquals( "/{$longId}/?fields=name,link", $request2->get_path() );
		
		// ID with leading zeros
		$request3 = new Request( '000123456789' );
		$this->assertEquals( '/000123456789/?fields=name,link', $request3->get_path() );
	}

	/**
	 * Test request path construction.
	 */
	public function test_request_path_construction() {
		$pageId = 'test_page_123';
		$request = new Request( $pageId );
		
		$expectedPath = "/{$pageId}/?fields=name,link";
		$this->assertEquals( $expectedPath, $request->get_path() );
		
		// Verify the fields parameter is included
		$this->assertStringContainsString( 'fields=name,link', $request->get_path() );
	}

	/**
	 * Test request method is GET.
	 */
	public function test_request_method_is_get() {
		$request = new Request( '123' );
		$this->assertEquals( 'GET', $request->get_method() );
		$this->assertNotEquals( 'POST', $request->get_method() );
	}

	/**
	 * Test request with special characters in page ID.
	 */
	public function test_request_with_special_characters_in_page_id() {
		// Note: In practice, Facebook page IDs are numeric, but testing edge cases
		$pageId = 'page-123_test';
		$request = new Request( $pageId );
		
		$this->assertEquals( "/{$pageId}/?fields=name,link", $request->get_path() );
	}

	/**
	 * Test request parameters can be set.
	 */
	public function test_request_set_params() {
		$request = new Request( '123456789' );
		
		$params = [
			'access_token' => 'test_token',
			'limit' => 10,
		];
		
		$request->set_params( $params );
		$this->assertEquals( $params, $request->get_params() );
	}

	/**
	 * Test request data can be set.
	 */
	public function test_request_set_data() {
		$request = new Request( '123456789' );
		
		$data = [
			'test_field' => 'test_value',
		];
		
		$request->set_data( $data );
		$this->assertEquals( $data, $request->get_data() );
	}

	/**
	 * Test request retry functionality.
	 */
	public function test_request_retry_functionality() {
		$request = new Request( '123456789' );
		
		// Test initial retry count
		$this->assertEquals( 0, $request->get_retry_count() );
		
		// Mark as retried
		$request->mark_retry();
		$this->assertEquals( 1, $request->get_retry_count() );
		
		// Mark as retried again
		$request->mark_retry();
		$this->assertEquals( 2, $request->get_retry_count() );
	}

	/**
	 * Test request retry limit.
	 */
	public function test_request_retry_limit() {
		$request = new Request( '123456789' );
		
		// Default retry limit should be 5
		$this->assertEquals( 5, $request->get_retry_limit() );
	}

	/**
	 * Test request retry codes.
	 */
	public function test_request_retry_codes() {
		$request = new Request( '123456789' );
		
		// Default retry codes should be empty array
		$this->assertIsArray( $request->get_retry_codes() );
		$this->assertEmpty( $request->get_retry_codes() );
	}

	/**
	 * Test request base path override.
	 */
	public function test_request_base_path_override() {
		$request = new Request( '123456789' );
		
		// Should return null by default
		$this->assertNull( $request->get_base_path_override() );
	}

	/**
	 * Test request specific headers.
	 */
	public function test_request_specific_headers() {
		$request = new Request( '123456789' );
		
		// Should return empty array by default
		$this->assertIsArray( $request->get_request_specific_headers() );
		$this->assertEmpty( $request->get_request_specific_headers() );
	}

	/**
	 * Test request with empty page ID.
	 */
	public function test_request_with_empty_page_id() {
		$request = new Request( '' );
		
		$this->assertEquals( '//?fields=name,link', $request->get_path() );
		$this->assertEquals( 'GET', $request->get_method() );
	}

	/**
	 * Test request with numeric page ID.
	 */
	public function test_request_with_numeric_page_id() {
		// Testing with actual numeric value (not string)
		$request = new Request( '123456789' );
		
		$this->assertEquals( '/123456789/?fields=name,link', $request->get_path() );
	}

	/**
	 * Test request path includes required fields.
	 */
	public function test_request_path_includes_required_fields() {
		$request = new Request( '123456789' );
		$path = $request->get_path();
		
		// Verify both name and link fields are requested
		$this->assertStringContainsString( 'name', $path );
		$this->assertStringContainsString( 'link', $path );
		$this->assertStringContainsString( 'fields=name,link', $path );
	}

	/**
	 * Test request inherits rate limiting trait.
	 */
	public function test_request_has_rate_limiting_trait() {
		$request = new Request( '123456789' );
		
		// Check if the trait methods are available
		$this->assertTrue( method_exists( $request, 'get_rate_limit_id' ) );
		$this->assertEquals( 'graph_api_request', Request::get_rate_limit_id() );
	}
} 