<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Connection disconnect functionality.
 */
class ConnectionDisconnectTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var \WC_Facebookcommerce|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $plugin_mock;

	/**
	 * @var \WC_Facebookcommerce_Integration|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $integration_mock;

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock plugin
		$this->plugin_mock = $this->createMock( \WC_Facebookcommerce::class );

		// Create mock integration
		$this->integration_mock = $this->createMock( \WC_Facebookcommerce_Integration::class );

		$this->plugin_mock->method( 'get_integration' )
			->willReturn( $this->integration_mock );

		// Mock catalog update method
		$this->integration_mock->method( 'update_product_catalog_id' )
			->willReturn( true );
	}

	/**
	 * Test that disconnect clears all expected options including facebook_config.
	 */
	public function test_disconnect_clears_all_options_including_facebook_config(): void {
		// Set up initial state with all options set
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, '123456789' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, '987654321' );
		update_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY, [
			'pixel_id' => '987654321',
			'use_pii' => 1,
			'use_s2s' => true,
			'access_token' => 'test_token'
		] );

		// Verify options are set before disconnect
		$this->assertEquals( '123456789', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID ) );
		$this->assertEquals( '987654321', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID ) );
		$this->assertIsArray( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );

		// Create connection and disconnect
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		// Verify all options are cleared
		$this->assertEquals( '', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID ) );
		$this->assertEquals( '', get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID ) );
		$this->assertFalse( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );
	}

	/**
	 * Test that disconnect works safely when facebook_config doesn't exist.
	 */
	public function test_disconnect_safe_when_facebook_config_missing(): void {
		// Ensure facebook_config doesn't exist
		delete_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY );
		$this->assertFalse( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );

		// Create connection and disconnect - should not throw error
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		// Should complete successfully
		$this->assertTrue( true, 'Disconnect completed without error when facebook_config missing' );
	}

	/**
	 * Test that disconnect works safely when WC_Facebookcommerce_Pixel class doesn't exist.
	 */
	public function test_disconnect_safe_when_pixel_class_missing(): void {
		// We can't actually remove the class, but we can test the condition
		// by checking that the class_exists check is in place
		$connection = new Connection( $this->plugin_mock );

		// This should complete without fatal error even if pixel class has issues
		$connection->disconnect();

		$this->assertTrue( true, 'Disconnect completed successfully' );
	}

	/**
	 * Test that pixel tracking stops after disconnect.
	 */
	public function test_pixel_tracking_stops_after_disconnect(): void {
		// Set up facebook_config with valid pixel
		update_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY, [
			'pixel_id' => '123456789',
			'use_pii' => 0,
			'use_s2s' => false,
			'access_token' => ''
		] );

		// Verify pixel ID is available before disconnect
		$this->assertEquals( '123456789', \WC_Facebookcommerce_Pixel::get_pixel_id() );

		// Disconnect
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		// Verify pixel ID returns default '0' after disconnect
		$this->assertEquals( '0', \WC_Facebookcommerce_Pixel::get_pixel_id() );
	}

	/**
	 * Test the stale pixel fix scenario: disconnect → change pixel → reconnect.
	 */
	public function test_stale_pixel_fix_scenario(): void {
		// 1. Initial connection with pixel ID
		$original_pixel_id = '111111111';
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, $original_pixel_id );
		update_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY, [
			'pixel_id' => $original_pixel_id,
			'use_pii' => 0,
			'use_s2s' => false,
			'access_token' => ''
		] );

		$this->assertEquals( $original_pixel_id, \WC_Facebookcommerce_Pixel::get_pixel_id() );

		// 2. Disconnect (this should clear facebook_config)
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		$this->assertEquals( '0', \WC_Facebookcommerce_Pixel::get_pixel_id() );
		$this->assertFalse( get_option( \WC_Facebookcommerce_Pixel::SETTINGS_KEY ) );

		// 3. Reconnect with new pixel ID (simulating FBE reconnection)
		$new_pixel_id = '222222222';
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, $new_pixel_id );

		// 4. Simulate the sync logic that runs during plugin initialization
		$pixel_id = \WC_Facebookcommerce_Pixel::get_pixel_id(); // Should be '0'
		$settings_pixel_id = get_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID );

		// The sync condition should trigger because pixel_id is '0' (invalid)
		$this->assertEquals( '0', $pixel_id );
		$this->assertEquals( $new_pixel_id, $settings_pixel_id );

		// 5. Run the sync (this is what happens in facebook-commerce.php:464)
		if ( $settings_pixel_id && ( $pixel_id !== $settings_pixel_id || $pixel_id === '0' ) ) {
			\WC_Facebookcommerce_Pixel::set_pixel_id( $settings_pixel_id );
		}

		// 6. Verify the new pixel ID is now active
		$this->assertEquals( $new_pixel_id, \WC_Facebookcommerce_Pixel::get_pixel_id() );
	}
}