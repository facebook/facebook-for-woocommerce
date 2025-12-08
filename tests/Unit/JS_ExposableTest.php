<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for JS_Exposable trait.
 *
 * @since 3.5.0
 */
class JS_ExposableTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Test instance using the JS_Exposable trait.
	 *
	 * @var JS_Exposable_Test_Implementation
	 */
	private $instance;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = new JS_Exposable_Test_Implementation();
	}

	/**
	 * Test that is_js_exposable returns true.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::is_js_exposable
	 */
	public function test_is_js_exposable_returns_true() {
		$this->assertTrue( JS_Exposable_Test_Implementation::is_js_exposable() );
		$this->assertIsBool( JS_Exposable_Test_Implementation::is_js_exposable() );
	}

	/**
	 * Test the structure of get_js_api_definition return value.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_structure() {
		$definition = $this->instance->get_js_api_definition();

		$this->assertIsArray( $definition );
		$this->assertArrayHasKey( 'path', $definition );
		$this->assertArrayHasKey( 'method', $definition );
		$this->assertArrayHasKey( 'className', $definition );
		$this->assertArrayHasKey( 'params', $definition );
		$this->assertArrayHasKey( 'required', $definition );
	}

	/**
	 * Test get_js_api_definition with required parameters.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_required_params() {
		$this->instance->set_param_schema( [
			'access_token' => [
				'type'     => 'string',
				'required' => true,
			],
			'catalog_id'   => [
				'type'     => 'string',
				'required' => true,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertCount( 2, $definition['required'] );
		$this->assertContains( 'access_token', $definition['required'] );
		$this->assertContains( 'catalog_id', $definition['required'] );
		$this->assertEquals( 'string', $definition['params']['access_token'] );
		$this->assertEquals( 'string', $definition['params']['catalog_id'] );
	}

	/**
	 * Test get_js_api_definition with optional parameters.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_optional_params() {
		$this->instance->set_param_schema( [
			'pixel_id'             => [
				'type'     => 'string',
				'required' => false,
			],
			'external_business_id' => [
				'type'     => 'string',
				'required' => false,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEmpty( $definition['required'] );
		$this->assertCount( 2, $definition['params'] );
		$this->assertEquals( 'string', $definition['params']['pixel_id'] );
		$this->assertEquals( 'string', $definition['params']['external_business_id'] );
	}

	/**
	 * Test get_js_api_definition with mixed required and optional parameters.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_mixed_params() {
		$this->instance->set_param_schema( [
			'merchant_access_token' => [
				'type'     => 'string',
				'required' => true,
			],
			'access_token'          => [
				'type'     => 'string',
				'required' => true,
			],
			'external_business_id'  => [
				'type'     => 'string',
				'required' => false,
			],
			'catalog_id'            => [
				'type'     => 'string',
				'required' => false,
			],
			'pixel_id'              => [
				'type'     => 'string',
				'required' => false,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertCount( 2, $definition['required'] );
		$this->assertContains( 'merchant_access_token', $definition['required'] );
		$this->assertContains( 'access_token', $definition['required'] );
		$this->assertNotContains( 'external_business_id', $definition['required'] );
		$this->assertNotContains( 'catalog_id', $definition['required'] );
		$this->assertNotContains( 'pixel_id', $definition['required'] );
		$this->assertCount( 5, $definition['params'] );
	}

	/**
	 * Test get_js_api_definition with empty parameter schema.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_empty_schema() {
		$this->instance->set_param_schema( [] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEmpty( $definition['required'] );
		$this->assertEmpty( $definition['params'] );
		$this->assertIsArray( $definition['required'] );
		$this->assertIsArray( $definition['params'] );
	}

	/**
	 * Test that get_js_api_definition extracts parameter types correctly.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_extracts_param_types() {
		$this->instance->set_param_schema( [
			'string_param'  => [
				'type'     => 'string',
				'required' => true,
			],
			'integer_param' => [
				'type'     => 'integer',
				'required' => false,
			],
			'boolean_param' => [
				'type'     => 'boolean',
				'required' => false,
			],
			'array_param'   => [
				'type'     => 'array',
				'required' => true,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEquals( 'string', $definition['params']['string_param'] );
		$this->assertEquals( 'integer', $definition['params']['integer_param'] );
		$this->assertEquals( 'boolean', $definition['params']['boolean_param'] );
		$this->assertEquals( 'array', $definition['params']['array_param'] );
	}

	/**
	 * Test that get_js_api_definition includes all required fields.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_includes_all_fields() {
		$this->instance->set_endpoint( '/test/endpoint' );
		$this->instance->set_method( 'POST' );
		$this->instance->set_js_function_name( 'testFunction' );
		$this->instance->set_param_schema( [
			'test_param' => [
				'type'     => 'string',
				'required' => true,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEquals( '/test/endpoint', $definition['path'] );
		$this->assertEquals( 'POST', $definition['method'] );
		$this->assertEquals( 'testFunction', $definition['className'] );
		$this->assertIsArray( $definition['params'] );
		$this->assertIsArray( $definition['required'] );
	}

	/**
	 * Test that required array only includes parameters marked as required.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_required_array_only_includes_required() {
		$this->instance->set_param_schema( [
			'required_param1'   => [
				'type'     => 'string',
				'required' => true,
			],
			'optional_param1'   => [
				'type'     => 'string',
				'required' => false,
			],
			'required_param2'   => [
				'type'     => 'integer',
				'required' => true,
			],
			'optional_param2'   => [
				'type'     => 'boolean',
				'required' => false,
			],
			'param_no_required' => [
				'type' => 'string',
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertCount( 2, $definition['required'] );
		$this->assertContains( 'required_param1', $definition['required'] );
		$this->assertContains( 'required_param2', $definition['required'] );
		$this->assertNotContains( 'optional_param1', $definition['required'] );
		$this->assertNotContains( 'optional_param2', $definition['required'] );
		$this->assertNotContains( 'param_no_required', $definition['required'] );
	}

	/**
	 * Test get_js_api_definition with complex parameter schema.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_complex_schema() {
		$this->instance->set_endpoint( 'settings/update' );
		$this->instance->set_method( 'POST' );
		$this->instance->set_js_function_name( 'updateSettings' );
		$this->instance->set_param_schema( [
			'merchant_access_token' => [
				'type'        => 'string',
				'required'    => true,
				'description' => 'Merchant access token',
			],
			'access_token'          => [
				'type'        => 'string',
				'required'    => true,
				'description' => 'Access token',
			],
			'external_business_id'  => [
				'type'        => 'string',
				'required'    => false,
				'description' => 'External business ID',
			],
			'catalog_id'            => [
				'type'        => 'string',
				'required'    => false,
				'description' => 'Catalog ID',
			],
			'pixel_id'              => [
				'type'        => 'string',
				'required'    => false,
				'description' => 'Pixel ID',
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		// Verify structure
		$this->assertEquals( 'settings/update', $definition['path'] );
		$this->assertEquals( 'POST', $definition['method'] );
		$this->assertEquals( 'updateSettings', $definition['className'] );

		// Verify params
		$this->assertCount( 5, $definition['params'] );
		$this->assertEquals( 'string', $definition['params']['merchant_access_token'] );
		$this->assertEquals( 'string', $definition['params']['access_token'] );
		$this->assertEquals( 'string', $definition['params']['external_business_id'] );
		$this->assertEquals( 'string', $definition['params']['catalog_id'] );
		$this->assertEquals( 'string', $definition['params']['pixel_id'] );

		// Verify required
		$this->assertCount( 2, $definition['required'] );
		$this->assertContains( 'merchant_access_token', $definition['required'] );
		$this->assertContains( 'access_token', $definition['required'] );
	}

	/**
	 * Test get_js_api_definition with GET method.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_get_method() {
		$this->instance->set_endpoint( 'settings/read' );
		$this->instance->set_method( 'GET' );
		$this->instance->set_js_function_name( 'getSettings' );
		$this->instance->set_param_schema( [] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEquals( 'GET', $definition['method'] );
		$this->assertEquals( 'settings/read', $definition['path'] );
		$this->assertEquals( 'getSettings', $definition['className'] );
	}

	/**
	 * Test get_js_api_definition with DELETE method.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_delete_method() {
		$this->instance->set_endpoint( 'settings/uninstall' );
		$this->instance->set_method( 'DELETE' );
		$this->instance->set_js_function_name( 'uninstallSettings' );
		$this->instance->set_param_schema( [] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEquals( 'DELETE', $definition['method'] );
		$this->assertEquals( 'settings/uninstall', $definition['path'] );
		$this->assertEquals( 'uninstallSettings', $definition['className'] );
	}

	/**
	 * Test that params array preserves parameter order.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_preserves_param_order() {
		$this->instance->set_param_schema( [
			'first_param'  => [
				'type'     => 'string',
				'required' => true,
			],
			'second_param' => [
				'type'     => 'integer',
				'required' => false,
			],
			'third_param'  => [
				'type'     => 'boolean',
				'required' => true,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$param_keys = array_keys( $definition['params'] );
		$this->assertEquals( 'first_param', $param_keys[0] );
		$this->assertEquals( 'second_param', $param_keys[1] );
		$this->assertEquals( 'third_param', $param_keys[2] );
	}

	/**
	 * Test get_js_api_definition with parameter that has only type.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_param_without_required_flag() {
		$this->instance->set_param_schema( [
			'param_with_required'    => [
				'type'     => 'string',
				'required' => true,
			],
			'param_without_required' => [
				'type' => 'string',
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		// Only param_with_required should be in required array
		$this->assertCount( 1, $definition['required'] );
		$this->assertContains( 'param_with_required', $definition['required'] );
		$this->assertNotContains( 'param_without_required', $definition['required'] );

		// Both should be in params
		$this->assertCount( 2, $definition['params'] );
		$this->assertArrayHasKey( 'param_with_required', $definition['params'] );
		$this->assertArrayHasKey( 'param_without_required', $definition['params'] );
	}

	/**
	 * Test get_js_api_definition with numeric parameter types.
	 *
	 * @covers WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable::get_js_api_definition
	 */
	public function test_get_js_api_definition_with_numeric_types() {
		$this->instance->set_param_schema( [
			'int_param'    => [
				'type'     => 'integer',
				'required' => true,
			],
			'number_param' => [
				'type'     => 'number',
				'required' => false,
			],
			'float_param'  => [
				'type'     => 'float',
				'required' => false,
			],
		] );

		$definition = $this->instance->get_js_api_definition();

		$this->assertEquals( 'integer', $definition['params']['int_param'] );
		$this->assertEquals( 'number', $definition['params']['number_param'] );
		$this->assertEquals( 'float', $definition['params']['float_param'] );
	}
}

/**
 * Concrete test implementation class that uses the JS_Exposable trait.
 *
 * This class implements the abstract methods required by the trait for testing purposes.
 */
class JS_Exposable_Test_Implementation {
	use JS_Exposable;

	/**
	 * Endpoint for testing.
	 *
	 * @var string
	 */
	private $endpoint = '/test/endpoint';

	/**
	 * HTTP method for testing.
	 *
	 * @var string
	 */
	private $method = 'POST';

	/**
	 * Parameter schema for testing.
	 *
	 * @var array
	 */
	private $param_schema = [];

	/**
	 * JavaScript function name for testing.
	 *
	 * @var string
	 */
	private $js_function_name = 'testFunction';

	/**
	 * Gets the API endpoint for this request.
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return $this->endpoint;
	}

	/**
	 * Gets the HTTP method for this request.
	 *
	 * @return string
	 */
	public function get_method() {
		return $this->method;
	}

	/**
	 * Gets the parameter schema for this request.
	 *
	 * @return array
	 */
	public function get_param_schema() {
		return $this->param_schema;
	}

	/**
	 * Gets the JavaScript function name for this request.
	 *
	 * @return string
	 */
	public function get_js_function_name() {
		return $this->js_function_name;
	}

	/**
	 * Sets the endpoint for testing.
	 *
	 * @param string $endpoint The endpoint.
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;
	}

	/**
	 * Sets the method for testing.
	 *
	 * @param string $method The HTTP method.
	 */
	public function set_method( $method ) {
		$this->method = $method;
	}

	/**
	 * Sets the parameter schema for testing.
	 *
	 * @param array $schema The parameter schema.
	 */
	public function set_param_schema( $schema ) {
		$this->param_schema = $schema;
	}

	/**
	 * Sets the JavaScript function name for testing.
	 *
	 * @param string $name The function name.
	 */
	public function set_js_function_name( $name ) {
		$this->js_function_name = $name;
	}
}
