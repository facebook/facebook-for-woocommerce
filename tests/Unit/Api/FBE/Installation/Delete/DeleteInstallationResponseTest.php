<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\FBE\Installation\Delete;

use WooCommerce\Facebook\API\FBE\Installation\Delete\Response;
use WooCommerce\Facebook\API\Response as ApiResponse;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for FBE\Installation\Delete\Response class.
 *
 * @since 3.5.2
 */
class DeleteInstallationResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test successful deletion response.
	 */
	public function test_successful_deletion_response() {
		$json_data = json_encode( array(
			'success' => true,
			'id' => 'installation_123',
		) );
		
		$response = new Response( $json_data );
		
		$this->assertTrue( $response->success );
		$this->assertEquals( 'installation_123', $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test deletion response with error.
	 */
	public function test_deletion_response_with_error() {
		$json_data = json_encode( array(
			'error' => array(
				'type' => 'GraphMethodException',
				'message' => 'Installation not found',
				'code' => 100,
			),
		) );
		
		$response = new Response( $json_data );
		
		$this->assertTrue( $response->has_api_error() );
		$this->assertEquals( 'GraphMethodException', $response->get_api_error_type() );
		$this->assertEquals( 'Installation not found', $response->get_api_error_message() );
		$this->assertEquals( 100, $response->get_api_error_code() );
	}

	/**
	 * Test deletion response with partial success.
	 */
	public function test_deletion_response_with_partial_data() {
		$json_data = json_encode( array(
			'success' => false,
			'message' => 'Deletion failed',
		) );
		
		$response = new Response( $json_data );
		
		$this->assertFalse( $response->success );
		$this->assertEquals( 'Deletion failed', $response->message );
		$this->assertFalse( $response->has_api_error() );
	}

	/**
	 * Test empty response handling.
	 */
	public function test_empty_response() {
		$response = new Response( '{}' );
		
		$this->assertNull( $response->get_id() );
		$this->assertFalse( $response->has_api_error() );
		$this->assertNull( $response->success );
	}



	/**
	 * Test that response class inherits all parent functionality.
	 */
	public function test_inherits_parent_functionality() {
		$json_data = json_encode( array(
			'id' => 'test_id',
			'custom_field' => 'custom_value',
		) );
		
		$response = new Response( $json_data );
		
		// Test inherited methods work correctly
		$this->assertEquals( 'test_id', $response->get_id() );
		$this->assertEquals( 'custom_value', $response->custom_field );
		
		// Test magic getter works
		$this->assertEquals( 'custom_value', $response->custom_field );
	}
} 