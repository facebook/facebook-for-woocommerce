<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\FBE\Configuration\Update;

use WooCommerce\Facebook\API\FBE\Configuration\Update\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for FBE\Configuration\Update\Response class.
 *
 * @since x.x.x
 */
class UpdateConfigurationResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that Response class exists and can be instantiated.
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
		$this->assertInstanceOf( Response::class, $response );
	}

	/**
	 * Test successful configuration update response.
	 */
	public function test_successful_update_response() {
		$json_data = json_encode( array(
			'success' => true,
			'id' => 'config_update_123',
			'updated_fields' => array( 'pixel_id', 'catalog_id' ),
		) );
		
		$response = new Response( $json_data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'config_update_123', $response->get_id() );
		$this->assertIsArray( $response->updated_fields );
		$this->assertContains( 'pixel_id', $response->updated_fields );
		$this->assertContains( 'catalog_id', $response->updated_fields );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test configuration update response with error.
	 */
	public function test_update_response_with_error() {
		$json_data = json_encode( array(
			'error' => array(
				'type' => 'ValidationException',
				'message' => 'Invalid configuration parameters',
				'code' => 400,
				'error_user_msg' => 'Please check your configuration settings',
			),
		) );
		
		$response = new Response( $json_data );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'ValidationException', $response->get_api_error_type() );
		$this->assertEquals( 'Invalid configuration parameters', $response->get_api_error_message() );
		$this->assertEquals( 400, $response->get_api_error_code() );
		$this->assertEquals( 'Please check your configuration settings', $response->get_user_error_message() );
	}

	/**
	 * Test partial update response.
	 */
	public function test_partial_update_response() {
		$json_data = json_encode( array(
			'success' => true,
			'id' => 'partial_update_456',
			'updated_fields' => array( 'pixel_id' ),
			'failed_fields' => array(
				'catalog_id' => 'Invalid catalog ID',
			),
		) );
		
		$response = new Response( $json_data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'partial_update_456', $response->get_id() );
		$this->assertCount( 1, $response->updated_fields );
		$this->assertIsArray( $response->failed_fields );
		$this->assertArrayHasKey( 'catalog_id', $response->failed_fields );
	}

	/**
	 * Test empty configuration update response.
	 */
	public function test_empty_update_response() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->get_id() );
		$this->assertNull( $response->success );
		$this->assertNull( $response->updated_fields );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test response with complex configuration data.
	 */
	public function test_response_with_complex_configuration() {
		$json_data = json_encode( array(
			'success' => true,
			'id' => 'complex_config_789',
			'configuration' => array(
				'pixel' => array(
					'id' => '123456789',
					'enabled' => true,
				),
				'catalog' => array(
					'id' => '987654321',
					'name' => 'Test Catalog',
				),
				'settings' => array(
					'currency' => 'USD',
					'timezone' => 'America/New_York',
				),
			),
		) );
		
		$response = new Response( $json_data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'complex_config_789', $response->get_id() );
		$this->assertIsArray( $response->configuration );
		$this->assertEquals( '123456789', $response->configuration['pixel']['id'] );
		$this->assertEquals( 'Test Catalog', $response->configuration['catalog']['name'] );
		$this->assertEquals( 'USD', $response->configuration['settings']['currency'] );
	}



	/**
	 * Test malformed JSON response handling.
	 */
	public function test_malformed_json_response() {
		$response = new Response( 'invalid json {' );
		
		$this->assertNull( $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
		$this->assertNull( $response->success );
	}
} 