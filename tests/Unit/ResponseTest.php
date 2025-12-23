<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\API\FBE\Configuration\Read\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for FBE Configuration Read Response class.
 *
 * @since 3.5.2
 */
class ResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Response::class ) );
	}

	/**
	 * Test that the class extends ApiResponse.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response
	 */
	public function test_class_extends_api_response() {
		$response = new Response( '{}' );
		$this->assertInstanceOf( ApiResponse::class, $response );
	}

	/**
	 * Test is_ig_shopping_enabled with enabled true.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_true() {
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'enabled' => true
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertTrue( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_shopping_enabled with enabled false.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_false() {
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'enabled' => false
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_shopping_enabled with missing enabled field.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_missing_enabled() {
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'other_field' => 'value'
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_shopping_enabled with empty ig_shopping.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_empty_ig_shopping() {
		$response_data = json_encode( array(
			'ig_shopping' => array()
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_shopping_enabled with missing ig_shopping.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_missing_ig_shopping() {
		$response_data = json_encode( array(
			'other_field' => 'value'
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_shopping_enabled with null ig_shopping.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_null_ig_shopping() {
		$response_data = json_encode( array(
			'ig_shopping' => null
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with enabled true.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_true() {
		$response_data = json_encode( array(
			'ig_cta' => array(
				'enabled' => true
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertTrue( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with enabled false.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_false() {
		$response_data = json_encode( array(
			'ig_cta' => array(
				'enabled' => false
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with missing ig_cta.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_missing_ig_cta() {
		$response_data = json_encode( array(
			'other_field' => 'value'
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with null ig_cta.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_null_ig_cta() {
		$response_data = json_encode( array(
			'ig_cta' => null
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with empty ig_cta array.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_empty_ig_cta() {
		$response_data = json_encode( array(
			'ig_cta' => array()
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with missing enabled field.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_missing_enabled() {
		$response_data = json_encode( array(
			'ig_cta' => array(
				'other_field' => 'value'
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test get_commerce_extension_uri with valid URI.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_get_commerce_extension_uri_valid() {
		$test_uri = 'https://example.com/commerce/extension';
		$response_data = json_encode( array(
			'commerce_extension' => array(
				'uri' => $test_uri
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( $test_uri, $response->get_commerce_extension_uri() );
	}

	/**
	 * Test get_commerce_extension_uri with missing uri field.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_get_commerce_extension_uri_missing_uri() {
		$response_data = json_encode( array(
			'commerce_extension' => array(
				'other_field' => 'value'
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( '', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test get_commerce_extension_uri with empty commerce_extension.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_get_commerce_extension_uri_empty_commerce_extension() {
		$response_data = json_encode( array(
			'commerce_extension' => array()
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( '', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test get_commerce_extension_uri with missing commerce_extension.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_get_commerce_extension_uri_missing_commerce_extension() {
		$response_data = json_encode( array(
			'other_field' => 'value'
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( '', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test get_commerce_extension_uri with null commerce_extension.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_get_commerce_extension_uri_null_commerce_extension() {
		$response_data = json_encode( array(
			'commerce_extension' => null
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( '', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test with complete configuration data.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_complete_configuration() {
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'enabled' => true,
				'created_at' => '2023-01-01',
				'updated_at' => '2023-06-01'
			),
			'ig_cta' => array(
				'enabled' => false,
				'reason' => 'Not configured'
			),
			'commerce_extension' => array(
				'uri' => 'https://example.com/commerce',
				'version' => '1.2.3'
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertTrue( $response->is_ig_shopping_enabled() );
		$this->assertFalse( $response->is_ig_cta_enabled() );
		$this->assertEquals( 'https://example.com/commerce', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test with empty response.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_empty_response() {
		$response = new Response( '{}' );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
		$this->assertFalse( $response->is_ig_cta_enabled() );
		$this->assertEquals( '', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test with various boolean values for enabled fields.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_various_boolean_values() {
		// Test with string "true"
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'enabled' => 'true'
			),
			'ig_cta' => array(
				'enabled' => '1'
			)
		) );
		
		$response = new Response( $response_data );
		
		// PHP will cast non-empty strings to true
		$this->assertTrue( $response->is_ig_shopping_enabled() );
		$this->assertTrue( $response->is_ig_cta_enabled() );
		
		// Test with numeric 0
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'enabled' => 0
			),
			'ig_cta' => array(
				'enabled' => ''
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_shopping_enabled() );
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}

	/**
	 * Test get_commerce_extension_uri with empty string URI.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::get_commerce_extension_uri
	 */
	public function test_get_commerce_extension_uri_empty_string() {
		$response_data = json_encode( array(
			'commerce_extension' => array(
				'uri' => ''
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertEquals( '', $response->get_commerce_extension_uri() );
	}

	/**
	 * Test is_ig_shopping_enabled with additional fields that should not affect result.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_shopping_enabled
	 */
	public function test_is_ig_shopping_enabled_with_additional_fields() {
		$response_data = json_encode( array(
			'ig_shopping' => array(
				'enabled' => true,
				'shop_id' => '12345',
				'catalog_id' => '67890',
				'created_at' => '2023-01-01T00:00:00Z',
				'updated_at' => '2023-12-01T00:00:00Z',
				'extra_data' => array(
					'nested' => 'value'
				)
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertTrue( $response->is_ig_shopping_enabled() );
	}

	/**
	 * Test is_ig_cta_enabled with additional fields that should not affect result.
	 *
	 * @covers \WooCommerce\Facebook\API\FBE\Configuration\Read\Response::is_ig_cta_enabled
	 */
	public function test_is_ig_cta_enabled_with_additional_fields() {
		$response_data = json_encode( array(
			'ig_cta' => array(
				'enabled' => false,
				'cta_type' => 'shop_now',
				'button_text' => 'Shop Now',
				'configuration' => array(
					'color' => 'blue',
					'size' => 'large'
				),
				'metadata' => array(
					'version' => '2.0'
				)
			)
		) );
		
		$response = new Response( $response_data );
		
		$this->assertFalse( $response->is_ig_cta_enabled() );
	}
}
