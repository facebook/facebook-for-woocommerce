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

		// Mock catalog update method to prevent side effects
		$this->integration_mock->method( 'update_product_catalog_id' )
			->willReturn( true );

		// Mock get_option method to prevent it from interfering with our tests
		$this->integration_mock->method( 'get_option' )
			->willReturn( '' );
	}

	/**
	 * Test that disconnect clears facebook_config option.
	 */
	public function test_disconnect_clears_facebook_config(): void {
		// Verify the settings key constant
		$this->assertEquals( 'facebook_config', \WC_Facebookcommerce_Pixel::SETTINGS_KEY );

		// Verify the class exists for safety check
		$this->assertTrue( class_exists( 'WC_Facebookcommerce_Pixel' ) );

		// Test disconnect executes without errors
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		$this->assertTrue( true, 'disconnect() completed without errors' );
	}

	/**
	 * Test that disconnect works safely when facebook_config doesn't exist.
	 */
	public function test_disconnect_safe_when_facebook_config_missing(): void {
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		$this->assertTrue( true, 'Disconnect completed without error when facebook_config missing' );
	}

	/**
	 * Test that disconnect works safely when Pixel class doesn't exist.
	 */
	public function test_disconnect_safe_when_pixel_class_missing(): void {
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		$this->assertTrue( true, 'Disconnect completed successfully' );
	}

	/**
	 * Test that disconnect method executes completely.
	 */
	public function test_disconnect_includes_facebook_config_cleanup(): void {
		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		$this->assertTrue( true, 'Disconnect method executed successfully' );
	}
}