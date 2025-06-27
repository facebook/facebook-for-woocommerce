<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\MetaLog;

use WooCommerce\Facebook\API\MetaLog\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for MetaLog Response class.
 *
 * @since 3.5.2
 */
class MetaLogResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		$response = new Response( '' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test instantiation with JSON response data.
	 */
	public function test_instantiation_with_json_data() {
		$data = json_encode( [ 'success' => true, 'id' => '12345' ] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test inherited to_string method.
	 */
	public function test_to_string_method() {
		$raw_data = '{"test":"data"}';
		$response = new Response( $raw_data );
		
		$this->assertEquals( $raw_data, $response->to_string() );
	}

	/**
	 * Test accessing response data via magic getter.
	 */
	public function test_magic_getter_access() {
		$data = [ 'key' => 'value', 'number' => 123 ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 'value', $response->key );
		$this->assertEquals( 123, $response->number );
	}

	/**
	 * Test with complex nested data.
	 */
	public function test_with_nested_data() {
		$data = [
			'success' => true,
			'data' => [
				'items' => [ 'item1', 'item2' ],
				'meta' => [
					'count' => 2,
					'page' => 1
				]
			]
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertTrue( $response->success );
		$this->assertIsArray( $response->data['items'] );
		$this->assertEquals( 2, $response->data['meta']['count'] );
	}

	/**
	 * Test with invalid JSON data.
	 */
	public function test_with_invalid_json() {
		$invalid_json = '{"invalid": json}';
		$response = new Response( $invalid_json );
		
		// Invalid JSON results in null response_data
		$this->assertNull( $response->response_data );
	}

	/**
	 * Test with empty string response.
	 */
	public function test_with_empty_string() {
		$response = new Response( '' );
		
		$this->assertEquals( '', $response->to_string() );
		$this->assertNull( $response->response_data );
	}

	/**
	 * Test with null-like response.
	 */
	public function test_with_null_string() {
		$response = new Response( 'null' );
		
		$this->assertNull( $response->response_data );
	}

	/**
	 * Test with boolean response.
	 */
	public function test_with_boolean_response() {
		$response_true = new Response( 'true' );
		$response_false = new Response( 'false' );
		
		$this->assertTrue( $response_true->response_data );
		$this->assertFalse( $response_false->response_data );
	}

	/**
	 * Test with numeric response.
	 */
	public function test_with_numeric_response() {
		$response = new Response( '42' );
		
		$this->assertEquals( 42, $response->response_data );
	}

	/**
	 * Test with array response.
	 */
	public function test_with_array_response() {
		$response = new Response( '["item1", "item2", "item3"]' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertCount( 3, $response->response_data );
		$this->assertEquals( 'item1', $response->response_data[0] );
	}

	/**
	 * Test with Unicode characters.
	 */
	public function test_with_unicode_characters() {
		$data = [ 'message' => '你好世界 🌍 émojis' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '你好世界 🌍 émojis', $response->message );
	}

	/**
	 * Test with special characters.
	 */
	public function test_with_special_characters() {
		$data = [ 'text' => "Line1\nLine2\tTabbed\r\nWindows" ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( "Line1\nLine2\tTabbed\r\nWindows", $response->text );
	}

	/**
	 * Test response with large data set.
	 */
	public function test_with_large_dataset() {
		$large_array = array_fill( 0, 1000, 'test_value' );
		$data = [ 'items' => $large_array ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertCount( 1000, $response->items );
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
} 