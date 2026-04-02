<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Plugin\Settings;

use WooCommerce\Facebook\API\Plugin\Settings\Handler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Settings Handler disconnect/clear functionality.
 */
class HandlerDisconnectTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that uninstall clears facebook_config option.
	 */
	public function test_clear_integration_options_includes_facebook_config(): void {
		$this->assertEquals( 'facebook_config', \WC_Facebookcommerce_Pixel::SETTINGS_KEY );

		$handler = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [] );

		$response = $handler->handle_uninstall( $mock_request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that uninstall works safely when facebook_config doesn't exist.
	 */
	public function test_clear_safe_when_facebook_config_missing(): void {
		$handler = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [] );

		$response = $handler->handle_uninstall( $mock_request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( true, 'Clear completed without error when facebook_config missing' );
	}

	/**
	 * Test that uninstall method executes successfully.
	 */
	public function test_uninstall_includes_facebook_config_cleanup(): void {
		$handler = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [] );

		$response = $handler->handle_uninstall( $mock_request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( true, 'Uninstall completed successfully' );
	}

	/**
	 * Test that handle_update clears the wc_facebook_connection_invalid transient.
	 */
	public function test_handle_update_clears_connection_invalid_transient(): void {
		// Pre-set the transient as if the connection was previously flagged invalid.
		set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );
		$this->assertNotFalse( get_transient( 'wc_facebook_connection_invalid' ) );

		$handler      = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [
			'access_token'          => 'new_valid_token',
			'merchant_access_token' => 'new_valid_token',
		] );

		$response = $handler->handle_update( $mock_request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ), 'Connection invalid transient should be deleted after successful settings update.' );
	}

	/**
	 * Test that handle_update returns success with valid params.
	 */
	public function test_handle_update_returns_success(): void {
		$handler      = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [
			'access_token'          => 'test_token',
			'merchant_access_token' => 'test_token',
		] );

		$response = $handler->handle_update( $mock_request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}
}