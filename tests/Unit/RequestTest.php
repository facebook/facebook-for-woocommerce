<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\PublicKeyGet;

use WooCommerce\Facebook\API\PublicKeyGet\Request;
use WooCommerce\Facebook\API\Request as ApiRequest;
use WooCommerce\Facebook\Framework\Api\JSONRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for PublicKeyGet Request class.
 *
 * @since 2.0.0
 */
class RequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Request class exists and extends proper parent classes.
	 */
	public function test_request_class_inheritance() {
		$this->assertTrue( class_exists( Request::class ) );
		
		$request = new Request( 'test-project' );
		$this->assertInstanceOf( ApiRequest::class, $request );
		$this->assertInstanceOf( JSONRequest::class, $request );
	}

	/**
	 * Test constructor with a standard project name.
	 */
	public function test_constructor_with_standard_project_name() {
		$request = new Request( 'my-project' );
		
		$this->assertEquals( 'shops_public_key/my-project', $request->get_path() );
		$this->assertEquals( 'GET', $request->get_method() );
	}

	/**
	 * Test constructor with different project name formats.
	 */
	public function test_constructor_with_different_project_names() {
		// Empty string
		$request1 = new Request( '' );
		$this->assertEquals( 'shops_public_key/', $request1->get_path() );
		
		// Numeric string
		$request2 = new Request( '123456789' );
		$this->assertEquals( 'shops_public_key/123456789', $request2->get_path() );
		
		// Project with special characters
		$request3 = new Request( 'project-name_123' );
		$this->assertEquals( 'shops_public_key/project-name_123', $request3->get_path() );
		
		// Very long project name
		$longName = str_repeat( 'a', 200 );
		$request4 = new Request( $longName );
		$this->assertEquals( 'shops_public_key/' . $longName, $request4->get_path() );
		
		// Project with spaces
		$request5 = new Request( 'my project name' );
		$this->assertEquals( 'shops_public_key/my project name', $request5->get_path() );
		
		// Project with unicode characters
		$request6 = new Request( 'プロジェクト' );
		$this->assertEquals( 'shops_public_key/プロジェクト', $request6->get_path() );
	}

	/**
	 * Test request method is GET.
	 */
	public function test_request_method_is_get() {
		$request = new Request( 'test-project' );
		
		$this->assertEquals( 'GET', $request->get_method() );
		$this->assertNotEquals( 'POST', $request->get_method() );
		$this->assertNotEquals( 'PUT', $request->get_method() );
		$this->assertNotEquals( 'DELETE', $request->get_method() );
	}

	/**
	 * Test get_base_path_override returns correct URL.
	 */
	public function test_get_base_path_override() {
		$request = new Request( 'test-project' );
		
		$this->assertEquals( 'https://api.facebook.com/', $request->get_base_path_override() );
		$this->assertIsString( $request->get_base_path_override() );
		$this->assertStringStartsWith( 'https://', $request->get_base_path_override() );
		$this->assertStringEndsWith( '/', $request->get_base_path_override() );
	}

	/**
	 * Test get_request_specific_headers returns correct headers.
	 */
	public function test_get_request_specific_headers() {
		$request = new Request( 'test-project' );
		
		$headers = $request->get_request_specific_headers();
		
		$this->assertIsArray( $headers );
		$this->assertArrayHasKey( 'X-API-Version', $headers );
		$this->assertEquals( '1.0.0', $headers['X-API-Version'] );
	}

	/**
	 * Test request specific headers structure.
	 */
	public function test_get_request_specific_headers_structure() {
		$request = new Request( 'test-project' );
		
		$headers = $request->get_request_specific_headers();
		
		$this->assertIsArray( $headers );
		$this->assertCount( 1, $headers );
		$this->assertArrayHasKey( 'X-API-Version', $headers );
		$this->assertIsString( $headers['X-API-Version'] );
		$this->assertEquals( '1.0.0', $headers['X-API-Version'] );
	}

	/**
	 * Test request path construction.
	 */
	public function test_request_path_construction() {
		$projectName = 'my-test-project';
		$request = new Request( $projectName );
		
		$expectedPath = sprintf( 'shops_public_key/%s', $projectName );
		$this->assertEquals( $expectedPath, $request->get_path() );
		
		// Verify path contains both the base path and project name
		$this->assertStringContainsString( 'shops_public_key', $request->get_path() );
		$this->assertStringContainsString( $projectName, $request->get_path() );
	}

	/**
	 * Test request inherits parent methods.
	 */
	public function test_request_inherits_parent_methods() {
		$request = new Request( 'test-project' );
		
		// Test that parent methods exist
		$this->assertTrue( method_exists( $request, 'set_params' ) );
		$this->assertTrue( method_exists( $request, 'set_data' ) );
		$this->assertTrue( method_exists( $request, 'get_params' ) );
		$this->assertTrue( method_exists( $request, 'get_data' ) );
		$this->assertTrue( method_exists( $request, 'get_retry_count' ) );
		$this->assertTrue( method_exists( $request, 'get_retry_limit' ) );
		$this->assertTrue( method_exists( $request, 'mark_retry' ) );
	}

	/**
	 * Test request set_params functionality.
	 */
	public function test_request_set_params() {
		$request = new Request( 'test-project' );
		
		$params = [
			'access_token' => 'test_token_123',
			'limit' => 50,
			'fields' => 'id,name',
		];
		
		$request->set_params( $params );
		$this->assertEquals( $params, $request->get_params() );
		
		// Test with empty params
		$request->set_params( [] );
		$this->assertEquals( [], $request->get_params() );
	}

	/**
	 * Test request set_data functionality.
	 */
	public function test_request_set_data() {
		$request = new Request( 'test-project' );
		
		$data = [
			'public_key' => 'test_public_key',
			'project_id' => '12345',
		];
		
		$request->set_data( $data );
		$this->assertEquals( $data, $request->get_data() );
		
		// Test with empty data
		$request->set_data( [] );
		$this->assertEquals( [], $request->get_data() );
	}

	/**
	 * Test request retry functionality and limits.
	 */
	public function test_request_retry_functionality() {
		$request = new Request( 'test-project' );
		
		// Test initial retry count
		$this->assertEquals( 0, $request->get_retry_count() );
		
		// Default retry limit should be 5
		$this->assertEquals( 5, $request->get_retry_limit() );
		
		// Mark as retried
		$request->mark_retry();
		$this->assertEquals( 1, $request->get_retry_count() );
		
		// Mark as retried again
		$request->mark_retry();
		$this->assertEquals( 2, $request->get_retry_count() );
		
		// Test up to the limit
		for ( $i = 2; $i < 5; $i++ ) {
			$request->mark_retry();
		}
		$this->assertEquals( 5, $request->get_retry_count() );
	}

	/**
	 * Test request retry codes.
	 */
	public function test_request_retry_codes() {
		$request = new Request( 'test-project' );
		
		// Default retry codes should be empty array
		$this->assertIsArray( $request->get_retry_codes() );
		$this->assertEmpty( $request->get_retry_codes() );
	}

	/**
	 * Test request inherits rate limiting trait.
	 */
	public function test_request_has_rate_limiting_trait() {
		$request = new Request( 'test-project' );
		
		// Check if the trait methods are available
		$this->assertTrue( method_exists( $request, 'get_rate_limit_id' ) );
		$this->assertEquals( 'graph_api_request', Request::get_rate_limit_id() );
	}

	/**
	 * Test that class constants are defined with correct values.
	 */
	public function test_constants_are_defined() {
		$reflection = new \ReflectionClass( Request::class );
		
		// Test API_REQUEST_PATH constant
		$this->assertTrue( $reflection->hasConstant( 'API_REQUEST_PATH' ) );
		$this->assertEquals( 'shops_public_key', Request::API_REQUEST_PATH );
		
		// Test API_METHOD constant
		$this->assertTrue( $reflection->hasConstant( 'API_METHOD' ) );
		$this->assertEquals( 'GET', Request::API_METHOD );
		
		// Test API_VERSION constant
		$this->assertTrue( $reflection->hasConstant( 'API_VERSION' ) );
		$this->assertEquals( '1.0.0', Request::API_VERSION );
	}

	/**
	 * Test to_string and to_string_safe methods.
	 */
	public function test_to_string_methods() {
		$request = new Request( 'test-project' );
		
		// Test with no data
		$this->assertEquals( '', $request->to_string() );
		$this->assertEquals( '', $request->to_string_safe() );
		
		// Test with data
		$data = [
			'key' => 'value',
			'number' => 123,
		];
		$request->set_data( $data );
		
		$this->assertIsString( $request->to_string() );
		$this->assertIsString( $request->to_string_safe() );
		$this->assertNotEmpty( $request->to_string() );
		
		// Verify it's valid JSON
		$decoded = json_decode( $request->to_string(), true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( $data, $decoded );
	}

	/**
	 * Test creating multiple instances with different projects.
	 */
	public function test_multiple_instances_with_different_projects() {
		$request1 = new Request( 'project-one' );
		$request2 = new Request( 'project-two' );
		$request3 = new Request( 'project-three' );
		
		// Verify each has the correct path
		$this->assertEquals( 'shops_public_key/project-one', $request1->get_path() );
		$this->assertEquals( 'shops_public_key/project-two', $request2->get_path() );
		$this->assertEquals( 'shops_public_key/project-three', $request3->get_path() );
		
		// Verify they don't interfere with each other
		$this->assertNotEquals( $request1->get_path(), $request2->get_path() );
		$this->assertNotEquals( $request2->get_path(), $request3->get_path() );
		$this->assertNotEquals( $request1->get_path(), $request3->get_path() );
		
		// Set different params for each
		$request1->set_params( [ 'param1' => 'value1' ] );
		$request2->set_params( [ 'param2' => 'value2' ] );
		$request3->set_params( [ 'param3' => 'value3' ] );
		
		// Verify params are isolated
		$this->assertEquals( [ 'param1' => 'value1' ], $request1->get_params() );
		$this->assertEquals( [ 'param2' => 'value2' ], $request2->get_params() );
		$this->assertEquals( [ 'param3' => 'value3' ], $request3->get_params() );
	}

	/**
	 * Test that all instances share the same method and base path override.
	 */
	public function test_all_instances_share_same_method_and_base_path() {
		$request1 = new Request( 'project-alpha' );
		$request2 = new Request( 'project-beta' );
		
		// All should have GET method
		$this->assertEquals( 'GET', $request1->get_method() );
		$this->assertEquals( 'GET', $request2->get_method() );
		
		// All should have same base path override
		$this->assertEquals( 'https://api.facebook.com/', $request1->get_base_path_override() );
		$this->assertEquals( 'https://api.facebook.com/', $request2->get_base_path_override() );
		
		// All should have same API version header
		$this->assertEquals( $request1->get_request_specific_headers(), $request2->get_request_specific_headers() );
	}

	/**
	 * Test request with special project name characters.
	 */
	public function test_request_with_special_project_name_characters() {
		// Test with URL-encoded characters
		$request1 = new Request( 'project%20name' );
		$this->assertEquals( 'shops_public_key/project%20name', $request1->get_path() );
		
		// Test with slashes (edge case)
		$request2 = new Request( 'project/name' );
		$this->assertEquals( 'shops_public_key/project/name', $request2->get_path() );
		
		// Test with dots
		$request3 = new Request( 'project.name.test' );
		$this->assertEquals( 'shops_public_key/project.name.test', $request3->get_path() );
		
		// Test with underscores and hyphens
		$request4 = new Request( 'project_name-test' );
		$this->assertEquals( 'shops_public_key/project_name-test', $request4->get_path() );
	}

	/**
	 * Test that the request path format is consistent.
	 */
	public function test_request_path_format_consistency() {
		$projects = [ 'test1', 'test2', 'test3', 'test-project', 'my_project' ];
		
		foreach ( $projects as $project ) {
			$request = new Request( $project );
			$path = $request->get_path();
			
			// Path should always start with API_REQUEST_PATH
			$this->assertStringStartsWith( 'shops_public_key/', $path );
			
			// Path should end with the project name
			$this->assertStringEndsWith( $project, $path );
			
			// Path should be in format: API_REQUEST_PATH/project
			$this->assertEquals( sprintf( '%s/%s', Request::API_REQUEST_PATH, $project ), $path );
		}
	}

	/**
	 * Test that headers array is not modified after retrieval.
	 */
	public function test_headers_immutability() {
		$request = new Request( 'test-project' );
		
		$headers1 = $request->get_request_specific_headers();
		$headers2 = $request->get_request_specific_headers();
		
		// Both calls should return the same structure
		$this->assertEquals( $headers1, $headers2 );
		
		// Modifying the returned array should not affect subsequent calls
		$headers1['X-Custom-Header'] = 'custom-value';
		$headers3 = $request->get_request_specific_headers();
		
		$this->assertArrayNotHasKey( 'X-Custom-Header', $headers3 );
		$this->assertCount( 1, $headers3 );
	}
}
