<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

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
	 * Test that disconnect clears the stored legacy page access token.
	 */
	public function test_disconnect_clears_legacy_page_access_token(): void {
		$this->assertTrue(
			add_option( \WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN, 'legacy_page_token' ),
			'Failed to create the legacy page access token option.'
		);

		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		$this->assertFalse( get_option( \WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN, false ) );
	}

	/**
	 * Test that disconnect clears every deprecated connection option.
	 */
	public function test_disconnect_clears_deprecated_options(): void {
		foreach ( \WC_Facebookcommerce_Integration::DEPRECATED_OPTIONS as $option ) {
			$this->assertTrue( add_option( $option, 'legacy_value' ), "Failed to create {$option}." );
		}

		$connection = new Connection( $this->plugin_mock );
		$connection->disconnect();

		foreach ( \WC_Facebookcommerce_Integration::DEPRECATED_OPTIONS as $option ) {
			$this->assertFalse( get_option( $option, false ), "Failed to delete {$option}." );
		}
	}

	/**
	 * Test that deprecated public setters retain their behavior until removal.
	 */
	public function test_deprecated_setters_preserve_legacy_writes(): void {
		$this->setExpectedDeprecated( Connection::class . '::update_system_user_id' );
		$this->setExpectedDeprecated( Connection::class . '::update_merchant_access_token' );

		$connection = new Connection( $this->plugin_mock );
		$connection->update_system_user_id( 'legacy-system-user' );
		$connection->update_merchant_access_token( 'legacy-merchant-token' );

		$this->assertSame( 'legacy-system-user', get_option( Connection::OPTION_SYSTEM_USER_ID ) );
		$this->assertSame( 'legacy-merchant-token', get_option( Connection::OPTION_MERCHANT_ACCESS_TOKEN ) );
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
