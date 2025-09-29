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
	 * Test that clear_integration_options clears facebook_config.
	 */
	public function test_clear_integration_options_includes_facebook_config(): void {
		// Set up all options that should be cleared
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, '123456789' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, '987654321' );
		update_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY, [
			'pixel_id' => '123456789',
			'use_pii' => 1,
			'use_s2s' => true,
			'access_token' => 'test_token'
		] );

		// Verify options exist before clearing
		$this->assertEquals( '123456789', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID ) );
		$this->assertEquals( '987654321', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID ) );
		$this->assertIsArray( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );

		// Create handler and trigger uninstall (which calls clear_integration_options)
		$handler = new Handler();

		// Create a mock request for uninstall
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [] );

		// Call handle_uninstall which internally calls clear_integration_options
		$response = $handler->handle_uninstall( $mock_request );

		// Verify all options are cleared including facebook_config
		$this->assertEquals( '', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, '' ) );
		$this->assertEquals( '', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, '' ) );
		$this->assertFalse( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );

		// Verify response is successful
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that clear works safely when facebook_config doesn't exist.
	 */
	public function test_clear_safe_when_facebook_config_missing(): void {
		// Ensure facebook_config doesn't exist
		delete_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY );
		$this->assertFalse( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );

		// Create handler and trigger uninstall
		$handler = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [] );

		// Should not throw error when facebook_config missing
		$response = $handler->handle_uninstall( $mock_request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( true, 'Clear completed without error when facebook_config missing' );
	}

	/**
	 * Test that pixel tracking stops after clearing options.
	 */
	public function test_pixel_tracking_stops_after_clear(): void {
		// Set up facebook_config with valid pixel
		update_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY, [
			'pixel_id' => '555666777',
			'use_pii' => 0,
			'use_s2s' => false,
			'access_token' => ''
		] );

		// Verify pixel ID is available before clear
		$this->assertEquals( '555666777', \WC_Facebookcommerce_Pixel::get_pixel_id() );

		// Clear options
		$handler = new Handler();
		$mock_request = $this->createMock( \WP_REST_Request::class );
		$mock_request->method( 'get_params' )->willReturn( [] );
		$handler->handle_uninstall( $mock_request );

		// Verify pixel ID returns default '0' after clear
		$this->assertEquals( '0', \WC_Facebookcommerce_Pixel::get_pixel_id() );
	}
}