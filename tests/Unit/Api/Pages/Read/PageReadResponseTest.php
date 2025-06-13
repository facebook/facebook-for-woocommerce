<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Pages\Read;

use WooCommerce\Facebook\API\Pages\Read\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Pages Read Response class.
 *
 * @since 3.5.2
 */
class PageReadResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test instantiation with page data.
	 */
	public function test_instantiation_with_page_data() {
		$data = json_encode( [ 
			'name' => 'Test Page', 
			'link' => 'https://www.facebook.com/testpage' 
		] );
		$response = new Response( $data );
		
		$this->assertInstanceOf( Response::class, $response );
		$this->assertEquals( $data, $response->to_string() );
	}

	/**
	 * Test accessing name property.
	 */
	public function test_name_property_access() {
		$page_name = 'My Business Page';
		$data = json_encode( [ 'name' => $page_name ] );
		$response = new Response( $data );
		
		$this->assertEquals( $page_name, $response->name );
	}

	/**
	 * Test accessing link property.
	 */
	public function test_link_property_access() {
		$page_link = 'https://www.facebook.com/mybusinesspage';
		$data = json_encode( [ 'link' => $page_link ] );
		$response = new Response( $data );
		
		$this->assertEquals( $page_link, $response->link );
	}

	/**
	 * Test accessing both name and link properties.
	 */
	public function test_name_and_link_properties() {
		$page_name = 'Official Business Page';
		$page_link = 'https://www.facebook.com/officialbusiness';
		$data = json_encode( [ 'name' => $page_name, 'link' => $page_link ] );
		$response = new Response( $data );
		
		$this->assertEquals( $page_name, $response->name );
		$this->assertEquals( $page_link, $response->link );
	}

	/**
	 * Test with missing name property.
	 */
	public function test_missing_name_property() {
		$data = json_encode( [ 'link' => 'https://www.facebook.com/page' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->name );
		$this->assertEquals( 'https://www.facebook.com/page', $response->link );
	}

	/**
	 * Test with missing link property.
	 */
	public function test_missing_link_property() {
		$data = json_encode( [ 'name' => 'Page Without Link' ] );
		$response = new Response( $data );
		
		$this->assertEquals( 'Page Without Link', $response->name );
		$this->assertNull( $response->link );
	}

	/**
	 * Test with empty object.
	 */
	public function test_empty_object() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->name );
		$this->assertNull( $response->link );
	}

	/**
	 * Test with additional properties.
	 */
	public function test_additional_properties() {
		$data = json_encode( [
			'name' => 'Test Page',
			'link' => 'https://www.facebook.com/testpage',
			'id' => '123456789',
			'category' => 'Business',
			'fan_count' => 1000
		] );
		$response = new Response( $data );
		
		// Documented properties
		$this->assertEquals( 'Test Page', $response->name );
		$this->assertEquals( 'https://www.facebook.com/testpage', $response->link );
		
		// Additional properties should also be accessible
		$this->assertEquals( '123456789', $response->id );
		$this->assertEquals( 'Business', $response->category );
		$this->assertEquals( 1000, $response->fan_count );
	}

	/**
	 * Test with special characters in name.
	 */
	public function test_special_characters_in_name() {
		$special_name = "Page & Co. <Official> \"Quotes\" 'Apostrophes'";
		$data = json_encode( [ 
			'name' => $special_name,
			'link' => 'https://www.facebook.com/page'
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $special_name, $response->name );
	}

	/**
	 * Test with Unicode characters in name.
	 */
	public function test_unicode_characters_in_name() {
		$unicode_name = '商店页面 🏪 Boutique émojis';
		$data = json_encode( [ 
			'name' => $unicode_name,
			'link' => 'https://www.facebook.com/boutique'
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $unicode_name, $response->name );
	}

	/**
	 * Test with various URL formats.
	 */
	public function test_various_url_formats() {
		// Standard Facebook page URL
		$data1 = json_encode( [ 'link' => 'https://www.facebook.com/mypage' ] );
		$response1 = new Response( $data1 );
		$this->assertEquals( 'https://www.facebook.com/mypage', $response1->link );
		
		// Mobile Facebook URL
		$data2 = json_encode( [ 'link' => 'https://m.facebook.com/mypage' ] );
		$response2 = new Response( $data2 );
		$this->assertEquals( 'https://m.facebook.com/mypage', $response2->link );
		
		// Facebook page with ID
		$data3 = json_encode( [ 'link' => 'https://www.facebook.com/pages/MyPage/123456789' ] );
		$response3 = new Response( $data3 );
		$this->assertEquals( 'https://www.facebook.com/pages/MyPage/123456789', $response3->link );
	}

	/**
	 * Test with null values.
	 */
	public function test_null_values() {
		$data = json_encode( [ 'name' => null, 'link' => null ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->name );
		$this->assertNull( $response->link );
	}

	/**
	 * Test ArrayAccess interface.
	 */
	public function test_array_access_interface() {
		$data = json_encode( [ 
			'name' => 'Array Access Page',
			'link' => 'https://www.facebook.com/arraypage'
		] );
		$response = new Response( $data );
		
		// Test array access
		$this->assertEquals( 'Array Access Page', $response['name'] );
		$this->assertEquals( 'https://www.facebook.com/arraypage', $response['link'] );
		
		// Test isset
		$this->assertTrue( isset( $response['name'] ) );
		$this->assertTrue( isset( $response['link'] ) );
		$this->assertFalse( isset( $response['nonexistent'] ) );
	}

	/**
	 * Test inherited get_id method.
	 */
	public function test_get_id_method() {
		$page_id = '987654321';
		$data = json_encode( [ 'id' => $page_id ] );
		$response = new Response( $data );
		
		$this->assertEquals( $page_id, $response->get_id() );
	}

	/**
	 * Test get_id method with missing id.
	 */
	public function test_get_id_method_missing_id() {
		$data = json_encode( [ 'name' => 'No ID Page', 'link' => 'https://fb.com/page' ] );
		$response = new Response( $data );
		
		$this->assertNull( $response->get_id() );
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
		
		// Should have no methods of its own
		$this->assertCount( 0, $own_methods );
	}

	/**
	 * Test with very long page name.
	 */
	public function test_very_long_page_name() {
		$long_name = str_repeat( 'Very Long Page Name ', 50 );
		$data = json_encode( [ 
			'name' => $long_name,
			'link' => 'https://www.facebook.com/verylongpage'
		] );
		$response = new Response( $data );
		
		$this->assertEquals( $long_name, $response->name );
	}

	/**
	 * Test with empty strings.
	 */
	public function test_empty_strings() {
		$data = json_encode( [ 'name' => '', 'link' => '' ] );
		$response = new Response( $data );
		
		$this->assertEquals( '', $response->name );
		$this->assertEquals( '', $response->link );
	}
} 