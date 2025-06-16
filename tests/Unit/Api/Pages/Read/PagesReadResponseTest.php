<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Pages\Read;

use WooCommerce\Facebook\API\Pages\Read\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Framework\Api\JSONResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Pages Read Response class.
 *
 * @since 3.5.2
 */
class PagesReadResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Response class exists and can be instantiated.
	 */
	public function test_response_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that Response extends the API Response class.
	 */
	public function test_response_extends_api_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test that Response extends JSONResponse through inheritance.
	 */
	public function test_response_extends_json_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( JSONResponse::class, $response );
	}

	/**
	 * Test response with page data containing name and link.
	 */
	public function test_response_with_page_data() {
		$data = [
			'name' => 'Test Business Page',
			'link' => 'https://www.facebook.com/testbusiness',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 'Test Business Page', $response->name );
		$this->assertEquals( 'https://www.facebook.com/testbusiness', $response->link );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with additional page fields.
	 */
	public function test_response_with_additional_fields() {
		$data = [
			'id' => '123456789',
			'name' => 'My Store',
			'link' => 'https://www.facebook.com/mystore',
			'category' => 'Retail Company',
			'about' => 'We sell amazing products',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 'My Store', $response->name );
		$this->assertEquals( 'https://www.facebook.com/mystore', $response->link );
		$this->assertEquals( '123456789', $response->id );
		$this->assertEquals( '123456789', $response->get_id() );
		$this->assertEquals( 'Retail Company', $response->category );
		$this->assertEquals( 'We sell amazing products', $response->about );
	}

	/**
	 * Test response with error data.
	 */
	public function test_response_with_error_data() {
		$errorData = [
			'error' => [
				'message' => 'Page not found',
				'type' => 'GraphMethodException',
				'code' => 100,
				'error_user_msg' => 'The page you requested does not exist.',
			],
		];
		$json = json_encode( $errorData );
		$response = new Response( $json );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'Page not found', $response->get_api_error_message() );
		$this->assertEquals( 'GraphMethodException', $response->get_api_error_type() );
		$this->assertEquals( 100, $response->get_api_error_code() );
		$this->assertEquals( 'The page you requested does not exist.', $response->get_user_error_message() );
	}

	/**
	 * Test response array access interface.
	 */
	public function test_response_array_access() {
		$data = [
			'name' => 'Array Access Test',
			'link' => 'https://www.facebook.com/arraytest',
			'verified' => true,
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		// Test array access
		$this->assertTrue( isset( $response['name'] ) );
		$this->assertEquals( 'Array Access Test', $response['name'] );
		$this->assertEquals( 'https://www.facebook.com/arraytest', $response['link'] );
		$this->assertTrue( $response['verified'] );
		
		// Test setting values
		$response['custom_field'] = 'custom_value';
		$this->assertEquals( 'custom_value', $response['custom_field'] );
		
		// Test unsetting values
		unset( $response['verified'] );
		$this->assertFalse( isset( $response['verified'] ) );
	}

	/**
	 * Test response with empty JSON.
	 */
	public function test_response_with_empty_json() {
		$response = new Response( '{}' );
		
		$this->assertIsArray( $response->response_data );
		$this->assertEmpty( $response->response_data );
		$this->assertNull( $response->name );
		$this->assertNull( $response->link );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response string representation.
	 */
	public function test_response_string_representation() {
		$data = [ 'name' => 'String Test Page', 'link' => 'https://fb.com/test' ];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $json, $response->to_string() );
		$this->assertEquals( $json, $response->to_string_safe() );
	}

	/**
	 * Test accessing non-existent properties.
	 */
	public function test_response_non_existent_properties() {
		$response = new Response( '{"name": "Test", "link": "https://fb.com/test"}' );
		
		$this->assertNull( $response->non_existent_property );
		$this->assertNull( $response->missing_field );
	}

	/**
	 * Test response with null values.
	 */
	public function test_response_with_null_values() {
		$data = [
			'name' => null,
			'link' => null,
			'id' => '12345',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertNull( $response->name );
		$this->assertNull( $response->link );
		$this->assertEquals( '12345', $response->id );
	}

	/**
	 * Test response with special characters in page name.
	 */
	public function test_response_with_special_characters() {
		$data = [
			'name' => 'José\'s Café & Restaurant <Special>',
			'link' => 'https://www.facebook.com/josescafe',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 'José\'s Café & Restaurant <Special>', $response->name );
		$this->assertEquals( 'https://www.facebook.com/josescafe', $response->link );
	}

	/**
	 * Test response with Unicode characters in page name.
	 */
	public function test_response_with_unicode_characters() {
		$data = [
			'name' => '🎉 Unicode Store 测试 магазин',
			'link' => 'https://www.facebook.com/unicodestore',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( '🎉 Unicode Store 测试 магазин', $response->name );
		$this->assertEquals( 'https://www.facebook.com/unicodestore', $response->link );
	}

	/**
	 * Test response with very long page name and link.
	 */
	public function test_response_with_long_values() {
		$longName = str_repeat( 'Very Long Page Name ', 50 );
		$data = [
			'name' => $longName,
			'link' => 'https://www.facebook.com/' . str_repeat( 'verylong', 20 ),
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( $longName, $response->name );
		$this->assertStringStartsWith( 'https://www.facebook.com/', $response->link );
	}

	/**
	 * Test response with page metadata.
	 */
	public function test_response_with_page_metadata() {
		$data = [
			'name' => 'Meta Page',
			'link' => 'https://www.facebook.com/metapage',
			'fan_count' => 10000,
			'is_published' => true,
			'hours' => [
				'mon_1_open' => '09:00',
				'mon_1_close' => '17:00',
			],
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 'Meta Page', $response->name );
		$this->assertEquals( 'https://www.facebook.com/metapage', $response->link );
		$this->assertEquals( 10000, $response->fan_count );
		$this->assertTrue( $response->is_published );
		$this->assertIsArray( $response->hours );
		$this->assertEquals( '09:00', $response->hours['mon_1_open'] );
	}

	/**
	 * Test response with different URL formats.
	 */
	public function test_response_with_different_url_formats() {
		$data = [
			'name' => 'URL Test Page',
			'link' => 'https://m.facebook.com/pages/category/Store/URLTestPage-123456789/',
		];
		$json = json_encode( $data );
		$response = new Response( $json );
		
		$this->assertEquals( 'URL Test Page', $response->name );
		$this->assertStringContainsString( 'facebook.com', $response->link );
		$this->assertStringContainsString( 'URLTestPage', $response->link );
	}
} 