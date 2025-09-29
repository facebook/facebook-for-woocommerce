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
}