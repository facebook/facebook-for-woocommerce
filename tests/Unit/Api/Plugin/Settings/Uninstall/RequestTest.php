<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Plugin\Settings\Uninstall;

use WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request;
use WooCommerce\Facebook\API\Plugin\Request as PluginRequest;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Settings Uninstall Request class.
 *
 * @since 3.5.0
 */
class RequestTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var \WP_REST_Request
	 */
	private $wp_rest_request;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create a mock WP_REST_Request
		$this->wp_rest_request = $this->createMock( \WP_REST_Request::class );
		$this->wp_rest_request->method( 'get_json_params' )->willReturn( [] );
		$this->wp_rest_request->method( 'get_params' )->willReturn( [] );
		
		$this->request = new Request( $this->wp_rest_request );
	}

	/**
	 * Test that the Request class exists and extends proper parent class.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_request_class_inheritance() {
		$this->assertTrue( class_exists( Request::class ) );
		$this->assertInstanceOf( PluginRequest::class, $this->request );
	}

	/**
	 * Test that the Request class uses JS_Exposable trait.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_request_uses_js_exposable_trait() {
		$this->assertTrue( method_exists( $this->request, 'is_js_exposable' ) );
		$this->assertTrue( method_exists( $this->request, 'get_js_api_definition' ) );
		$this->assertTrue( Request::is_js_exposable() );
	}

	/**
	 * Test get_endpoint returns correct endpoint.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_endpoint
	 */
	public function test_get_endpoint() {
		$endpoint = $this->request->get_endpoint();
		
		$this->assertIsString( $endpoint );
		$this->assertEquals( 'settings/uninstall', $endpoint );
	}

	/**
	 * Test get_endpoint returns consistent value.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_endpoint
	 */
	public function test_get_endpoint_consistency() {
		$endpoint1 = $this->request->get_endpoint();
		$endpoint2 = $this->request->get_endpoint();
		
		$this->assertEquals( $endpoint1, $endpoint2 );
	}

	/**
	 * Test get_method returns POST.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_method
	 */
	public function test_get_method() {
		$method = $this->request->get_method();
		
		$this->assertIsString( $method );
		$this->assertEquals( 'POST', $method );
	}

	/**
	 * Test get_method returns POST not other HTTP methods.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_method
	 */
	public function test_get_method_is_post_not_others() {
		$method = $this->request->get_method();
		
		$this->assertEquals( 'POST', $method );
		$this->assertNotEquals( 'GET', $method );
		$this->assertNotEquals( 'PUT', $method );
		$this->assertNotEquals( 'DELETE', $method );
		$this->assertNotEquals( 'PATCH', $method );
	}

	/**
	 * Test get_param_schema returns empty array.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_param_schema
	 */
	public function test_get_param_schema() {
		$schema = $this->request->get_param_schema();
		
		$this->assertIsArray( $schema );
		$this->assertEmpty( $schema );
	}

	/**
	 * Test get_param_schema returns empty array consistently.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_param_schema
	 */
	public function test_get_param_schema_is_always_empty() {
		$schema1 = $this->request->get_param_schema();
		$schema2 = $this->request->get_param_schema();
		
		$this->assertEquals( [], $schema1 );
		$this->assertEquals( [], $schema2 );
		$this->assertEquals( $schema1, $schema2 );
	}

	/**
	 * Test get_js_function_name returns correct function name.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_function_name
	 */
	public function test_get_js_function_name() {
		$function_name = $this->request->get_js_function_name();
		
		$this->assertIsString( $function_name );
		$this->assertEquals( 'uninstallSettings', $function_name );
	}

	/**
	 * Test get_js_function_name returns consistent value.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_function_name
	 */
	public function test_get_js_function_name_consistency() {
		$name1 = $this->request->get_js_function_name();
		$name2 = $this->request->get_js_function_name();
		
		$this->assertEquals( $name1, $name2 );
	}

	/**
	 * Test validate returns true.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::validate
	 */
	public function test_validate() {
		$result = $this->request->validate();
		
		$this->assertTrue( $result );
		$this->assertNotInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test validate always returns true.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::validate
	 */
	public function test_validate_always_returns_true() {
		$result1 = $this->request->validate();
		$result2 = $this->request->validate();
		
		$this->assertTrue( $result1 );
		$this->assertTrue( $result2 );
	}

	/**
	 * Test get_js_api_definition returns correct structure.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_api_definition
	 */
	public function test_get_js_api_definition() {
		$definition = $this->request->get_js_api_definition();
		
		$this->assertIsArray( $definition );
		$this->assertArrayHasKey( 'path', $definition );
		$this->assertArrayHasKey( 'method', $definition );
		$this->assertArrayHasKey( 'className', $definition );
		$this->assertArrayHasKey( 'params', $definition );
		$this->assertArrayHasKey( 'required', $definition );
	}

	/**
	 * Test get_js_api_definition has correct values.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_api_definition
	 */
	public function test_get_js_api_definition_values() {
		$definition = $this->request->get_js_api_definition();
		
		$this->assertEquals( 'settings/uninstall', $definition['path'] );
		$this->assertEquals( 'POST', $definition['method'] );
		$this->assertEquals( 'uninstallSettings', $definition['className'] );
		$this->assertIsArray( $definition['params'] );
		$this->assertEmpty( $definition['params'] );
		$this->assertIsArray( $definition['required'] );
		$this->assertEmpty( $definition['required'] );
	}

	/**
	 * Test request with empty JSON params.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_request_with_empty_json_params() {
		$wp_request = $this->createMock( \WP_REST_Request::class );
		$wp_request->method( 'get_json_params' )->willReturn( [] );
		$wp_request->method( 'get_params' )->willReturn( [] );
		
		$request = new Request( $wp_request );
		
		$this->assertInstanceOf( Request::class, $request );
		$this->assertEquals( [], $request->get_data() );
	}

	/**
	 * Test request with null JSON params falls back to get_params.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_request_with_null_json_params_uses_get_params() {
		$wp_request = $this->createMock( \WP_REST_Request::class );
		$wp_request->method( 'get_json_params' )->willReturn( null );
		$wp_request->method( 'get_params' )->willReturn( [] );
		
		$request = new Request( $wp_request );
		
		$this->assertInstanceOf( Request::class, $request );
		$this->assertEquals( [], $request->get_data() );
	}

	/**
	 * Test request inherits parent methods.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_request_inherits_parent_methods() {
		$this->assertTrue( method_exists( $this->request, 'get_param' ) );
		$this->assertTrue( method_exists( $this->request, 'get_data' ) );
	}

	/**
	 * Test get_param returns default when key doesn't exist.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_param
	 */
	public function test_get_param_returns_default() {
		$result = $this->request->get_param( 'nonexistent_key', 'default_value' );
		
		$this->assertEquals( 'default_value', $result );
	}

	/**
	 * Test get_param returns null when key doesn't exist and no default.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_param
	 */
	public function test_get_param_returns_null_without_default() {
		$result = $this->request->get_param( 'nonexistent_key' );
		
		$this->assertNull( $result );
	}

	/**
	 * Test get_data returns empty array for uninstall request.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_data
	 */
	public function test_get_data_returns_empty_array() {
		$data = $this->request->get_data();
		
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test multiple instances are independent.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_multiple_instances_are_independent() {
		$wp_request1 = $this->createMock( \WP_REST_Request::class );
		$wp_request1->method( 'get_json_params' )->willReturn( [] );
		$wp_request1->method( 'get_params' )->willReturn( [] );
		
		$wp_request2 = $this->createMock( \WP_REST_Request::class );
		$wp_request2->method( 'get_json_params' )->willReturn( [] );
		$wp_request2->method( 'get_params' )->willReturn( [] );
		
		$request1 = new Request( $wp_request1 );
		$request2 = new Request( $wp_request2 );
		
		$this->assertEquals( $request1->get_endpoint(), $request2->get_endpoint() );
		$this->assertEquals( $request1->get_method(), $request2->get_method() );
		$this->assertEquals( $request1->get_js_function_name(), $request2->get_js_function_name() );
	}

	/**
	 * Test that endpoint path is properly formatted.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_endpoint
	 */
	public function test_endpoint_path_format() {
		$endpoint = $this->request->get_endpoint();
		
		$this->assertStringContainsString( 'settings', $endpoint );
		$this->assertStringContainsString( 'uninstall', $endpoint );
		$this->assertStringContainsString( '/', $endpoint );
		$this->assertStringNotContainsString( '//', $endpoint );
	}

	/**
	 * Test JS function name follows camelCase convention.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_function_name
	 */
	public function test_js_function_name_is_camel_case() {
		$function_name = $this->request->get_js_function_name();
		
		// Should start with lowercase
		$this->assertEquals( strtolower( $function_name[0] ), $function_name[0] );
		
		// Should contain uppercase letter (camelCase)
		$this->assertMatchesRegularExpression( '/[A-Z]/', $function_name );
		
		// Should not contain spaces or special characters
		$this->assertMatchesRegularExpression( '/^[a-zA-Z]+$/', $function_name );
	}

	/**
	 * Test that validate doesn't require any parameters.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::validate
	 */
	public function test_validate_requires_no_parameters() {
		// Validate should work without any setup
		$result = $this->request->validate();
		
		$this->assertTrue( $result );
	}

	/**
	 * Test is_js_exposable returns true.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::is_js_exposable
	 */
	public function test_is_js_exposable_returns_true() {
		$this->assertTrue( Request::is_js_exposable() );
	}

	/**
	 * Test is_js_exposable is static method.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::is_js_exposable
	 */
	public function test_is_js_exposable_is_static() {
		$reflection = new \ReflectionMethod( Request::class, 'is_js_exposable' );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test get_js_api_definition structure matches expected format.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_api_definition
	 */
	public function test_get_js_api_definition_structure() {
		$definition = $this->request->get_js_api_definition();
		
		// Should have exactly 5 keys
		$this->assertCount( 5, $definition );
		
		// All values should be of correct types
		$this->assertIsString( $definition['path'] );
		$this->assertIsString( $definition['method'] );
		$this->assertIsString( $definition['className'] );
		$this->assertIsArray( $definition['params'] );
		$this->assertIsArray( $definition['required'] );
	}

	/**
	 * Test that param schema being empty means no required params.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_param_schema
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request::get_js_api_definition
	 */
	public function test_empty_param_schema_means_no_required_params() {
		$schema = $this->request->get_param_schema();
		$definition = $this->request->get_js_api_definition();
		
		$this->assertEmpty( $schema );
		$this->assertEmpty( $definition['required'] );
		$this->assertEmpty( $definition['params'] );
	}

	/**
	 * Test request can be created with different WP_REST_Request instances.
	 *
	 * @covers \WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request
	 */
	public function test_request_with_different_wp_rest_request_instances() {
		$wp_request1 = $this->createMock( \WP_REST_Request::class );
		$wp_request1->method( 'get_json_params' )->willReturn( [] );
		$wp_request1->method( 'get_params' )->willReturn( [] );
		
		$wp_request2 = $this->createMock( \WP_REST_Request::class );
		$wp_request2->method( 'get_json_params' )->willReturn( null );
		$wp_request2->method( 'get_params' )->willReturn( [] );
		
		$request1 = new Request( $wp_request1 );
		$request2 = new Request( $wp_request2 );
		
		// Both should validate successfully
		$this->assertTrue( $request1->validate() );
		$this->assertTrue( $request2->validate() );
		
		// Both should have same endpoint and method
		$this->assertEquals( $request1->get_endpoint(), $request2->get_endpoint() );
		$this->assertEquals( $request1->get_method(), $request2->get_method() );
	}
}
