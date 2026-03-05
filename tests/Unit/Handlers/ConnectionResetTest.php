<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\Handlers\MetaExtension;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for Connection reset functionality.
 *
 * Tests the reset_connection() and reset_connection_only() methods to ensure
 * they properly delete all expected Facebook options from the database.
 *
 * Note: This test uses actual database operations (not option isolation)
 * because we need to test that delete_option() actually removes options.
 *
 * @since 3.0.0
 */
class ConnectionResetTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var \WC_Facebookcommerce|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $plugin_mock;

	/**
	 * @var \WC_Facebookcommerce_Integration|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $integration_mock;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * List of all connection-related options that should be deleted.
	 *
	 * Uses constants from MetaExtension and Integration classes to ensure
	 * the test remains in sync with the actual implementation.
	 *
	 * @var array
	 */
	private $connection_options = [
		MetaExtension::OPTION_ACCESS_TOKEN,
		MetaExtension::OPTION_MERCHANT_ACCESS_TOKEN,
		MetaExtension::OPTION_PAGE_ACCESS_TOKEN,
		MetaExtension::OPTION_EXTERNAL_BUSINESS_ID,
		MetaExtension::OPTION_BUSINESS_MANAGER_ID,
		MetaExtension::OPTION_AD_ACCOUNT_ID,
		MetaExtension::OPTION_SYSTEM_USER_ID,
		Connection::OPTION_COMMERCE_MANAGER_ID,
		MetaExtension::OPTION_INSTAGRAM_BUSINESS_ID,
		MetaExtension::OPTION_COMMERCE_MERCHANT_SETTINGS_ID,
		MetaExtension::OPTION_COMMERCE_PARTNER_INTEGRATION_ID,
		\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID,
		MetaExtension::OPTION_PIXEL_ID,
		MetaExtension::OPTION_PRODUCT_CATALOG_ID,
		\WC_Facebookcommerce_Integration::OPTION_EXTERNAL_MERCHANT_SETTINGS_ID,
		MetaExtension::OPTION_HAS_CONNECTED_FBE_2,
		MetaExtension::OPTION_HAS_AUTHORIZED_PAGES,
		MetaExtension::OPTION_PROFILES,
		MetaExtension::OPTION_INSTALLED_FEATURES,
		'facebook_config', // WC_Facebookcommerce_Pixel::SETTINGS_KEY (dynamically referenced in code)
	];

	/**
	 * List of all settings options that should be preserved by reset_connection_only().
	 *
	 * Uses constants from Integration class to ensure the test remains in sync
	 * with the actual implementation.
	 *
	 * @var array
	 */
	private $settings_options = [
		\WC_Facebookcommerce_Integration::OPTION_FEED_ID,
		\WC_Facebookcommerce_Integration::OPTION_UPLOAD_ID,
		\WC_Facebookcommerce_Integration::OPTION_JS_SDK_VERSION,
		\WC_Facebookcommerce_Integration::OPTION_PIXEL_INSTALL_TIME,
		\WC_Facebookcommerce_Integration::SETTING_ENABLE_PRODUCT_SYNC,
		\WC_Facebookcommerce_Integration::SETTING_EXCLUDED_PRODUCT_CATEGORY_IDS,
		\WC_Facebookcommerce_Integration::SETTING_EXCLUDED_PRODUCT_TAG_IDS,
		\WC_Facebookcommerce_Integration::SETTING_PRODUCT_DESCRIPTION_MODE,
		\WC_Facebookcommerce_Integration::SETTING_SCHEDULED_RESYNC_OFFSET,
		\WC_Facebookcommerce_Integration::SETTING_ENABLE_META_DIAGNOSIS,
		\WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE,
		\WC_Facebookcommerce_Integration::SETTING_ENABLE_NEW_STYLE_FEED_GENERATOR,
		\WC_Facebookcommerce_Integration::SETTING_ENABLE_FACEBOOK_MANAGED_COUPONS,
		\WC_Facebookcommerce_Integration::SETTING_REQUEST_HEADERS_IN_DEBUG_MODE,
		\WC_Facebookcommerce_Integration::SETTING_ENABLE_ADVANCED_MATCHING,
		\WC_Facebookcommerce_Integration::SETTING_USE_S2S,
		\WC_Facebookcommerce_Integration::SETTING_ACCESS_TOKEN,
		\WC_Facebookcommerce_Integration::OPTION_ENABLE_MESSENGER,
		\WC_Facebookcommerce_Integration::OPTION_LEGACY_FEED_FILE_GENERATION_ENABLED,
		\WC_Facebookcommerce_Integration::OPTION_LANGUAGE_OVERRIDE_FEED_GENERATION_ENABLED,
		'woocommerce_facebookcommerce_settings', // Legacy settings array (no constant defined)
	];

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

		// Create connection instance
		$this->connection = new Connection( $this->plugin_mock );

		// Set all options to test values before each test
		$this->set_all_facebook_options();
	}

	/**
	 * Sets all Facebook options to test values.
	 */
	private function set_all_facebook_options(): void {
		// Set connection options
		foreach ( $this->connection_options as $option ) {
			update_option( $option, 'test_value_' . $option );
		}

		// Set settings options
		foreach ( $this->settings_options as $option ) {
			update_option( $option, 'test_value_' . $option );
		}
	}

	/**
	 * Asserts that an option has been cleared (either deleted or set to empty string).
	 *
	 * @param string $option The option name to check.
	 * @param string $message Optional assertion message.
	 */
	private function assertOptionCleared( string $option, string $message = '' ): void {
		$value = get_option( $option, false );
		$this->assertTrue(
			$value === false || $value === '',
			$message ?: "Option '{$option}' should be cleared (either deleted or empty string)"
		);
	}

	/**
	 * Test that reset_connection_only() deletes all connection options.
	 */
	public function test_reset_connection_only_deletes_all_connection_options(): void {
		$this->connection->reset_connection_only();

		// Verify all connection options are deleted
		foreach ( $this->connection_options as $option ) {
			$value = get_option( $option, 'default_not_found' );
			$this->assertEquals(
				'default_not_found',
				$value,
				"Connection option '{$option}' should be deleted by reset_connection_only()"
			);
		}
	}

	/**
	 * Test that reset_connection_only() preserves settings options.
	 */
	public function test_reset_connection_only_preserves_settings_options(): void {
		$this->connection->reset_connection_only();

		// Verify settings options are preserved
		foreach ( $this->settings_options as $option ) {
			$value = get_option( $option, 'default_not_found' );
			$this->assertEquals(
				'test_value_' . $option,
				$value,
				"Settings option '{$option}' should be preserved by reset_connection_only()"
			);
		}
	}

	/**
	 * Test that reset_connection() deletes all connection options.
	 */
	public function test_reset_connection_deletes_all_connection_options(): void {
		$this->connection->reset_connection();

		// Verify all connection options are deleted
		foreach ( $this->connection_options as $option ) {
			$value = get_option( $option, 'default_not_found' );
			$this->assertEquals(
				'default_not_found',
				$value,
				"Connection option '{$option}' should be deleted by reset_connection()"
			);
		}
	}

	/**
	 * Test that reset_connection() deletes all settings options.
	 */
	public function test_reset_connection_deletes_all_settings_options(): void {
		$this->connection->reset_connection();

		// Verify all settings options are deleted
		foreach ( $this->settings_options as $option ) {
			$value = get_option( $option, 'default_not_found' );
			$this->assertEquals(
				'default_not_found',
				$value,
				"Settings option '{$option}' should be deleted by reset_connection()"
			);
		}
	}

	/**
	 * Test that reset_connection_only() fires the correct action hook.
	 */
	public function test_reset_connection_only_fires_action_hook(): void {
		$action_fired = false;
		$this->add_filter_with_safe_teardown(
			'wc_facebook_connection_only_reset',
			function() use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$this->connection->reset_connection_only();

		$this->assertTrue( $action_fired, 'Action hook wc_facebook_connection_only_reset should be fired' );
	}

	/**
	 * Test that reset_connection() fires the correct action hook.
	 */
	public function test_reset_connection_fires_action_hook(): void {
		$action_fired = false;
		$this->add_filter_with_safe_teardown(
			'wc_facebook_connection_reset',
			function() use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$this->connection->reset_connection();

		$this->assertTrue( $action_fired, 'Action hook wc_facebook_connection_reset should be fired' );
	}

	/**
	 * Test that reset_connection_only() works safely when options don't exist.
	 */
	public function test_reset_connection_only_safe_when_options_missing(): void {
		// Delete all options first
		foreach ( array_merge( $this->connection_options, $this->settings_options ) as $option ) {
			delete_option( $option );
		}

		// Should not throw any errors
		$this->connection->reset_connection_only();

		$this->assertTrue( true, 'reset_connection_only() completed without errors when options missing' );
	}

	/**
	 * Test that reset_connection() works safely when options don't exist.
	 */
	public function test_reset_connection_safe_when_options_missing(): void {
		// Delete all options first
		foreach ( array_merge( $this->connection_options, $this->settings_options ) as $option ) {
			delete_option( $option );
		}

		// Should not throw any errors
		$this->connection->reset_connection();

		$this->assertTrue( true, 'reset_connection() completed without errors when options missing' );
	}

	/**
	 * Test that reset_connection_only() clears pixel configuration.
	 */
	public function test_reset_connection_only_clears_pixel_config(): void {
		// Set pixel config
		update_option( 'facebook_config', [ 'pixel_id' => '123456' ] );

		$this->connection->reset_connection_only();

		// Verify pixel config is cleared
		$value = get_option( 'facebook_config', 'default_not_found' );
		$this->assertEquals(
			'default_not_found',
			$value,
			'Pixel configuration should be cleared by reset_connection_only()'
		);
	}

	/**
	 * Test that reset_connection() clears pixel configuration.
	 */
	public function test_reset_connection_clears_pixel_config(): void {
		// Set pixel config
		update_option( 'facebook_config', [ 'pixel_id' => '123456' ] );

		$this->connection->reset_connection();

		// Verify pixel config is cleared
		$value = get_option( 'facebook_config', 'default_not_found' );
		$this->assertEquals(
			'default_not_found',
			$value,
			'Pixel configuration should be cleared by reset_connection()'
		);
	}

	/**
	 * Test that both methods work when Pixel class doesn't exist.
	 */
	public function test_reset_methods_safe_when_pixel_class_missing(): void {
		// These should complete without errors even if Pixel class is missing
		$this->connection->reset_connection_only();
		$this->connection->reset_connection();

		$this->assertTrue( true, 'Reset methods completed successfully' );
	}

	/**
	 * Test that reset_connection_only() preserves specific critical settings.
	 */
	public function test_reset_connection_only_preserves_critical_settings(): void {
		// Set some critical settings
		update_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_PRODUCT_SYNC, 'yes' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_EXCLUDED_PRODUCT_CATEGORY_IDS, [ 1, 2, 3 ] );
		update_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE, 'yes' );

		$this->connection->reset_connection_only();

		// Verify critical settings are preserved
		$this->assertEquals( 'yes', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_PRODUCT_SYNC ) );
		$this->assertEquals( [ 1, 2, 3 ], get_option( \WC_Facebookcommerce_Integration::SETTING_EXCLUDED_PRODUCT_CATEGORY_IDS ) );
		$this->assertEquals( 'yes', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE ) );
	}

	/**
	 * Test that reset_connection() deletes the same critical settings.
	 */
	public function test_reset_connection_deletes_critical_settings(): void {
		// Set some critical settings
		update_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_PRODUCT_SYNC, 'yes' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_EXCLUDED_PRODUCT_CATEGORY_IDS, [ 1, 2, 3 ] );
		update_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE, 'yes' );

		$this->connection->reset_connection();

		// Verify critical settings are deleted
		$this->assertEquals( 'default_not_found', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_PRODUCT_SYNC, 'default_not_found' ) );
		$this->assertEquals( 'default_not_found', get_option( \WC_Facebookcommerce_Integration::SETTING_EXCLUDED_PRODUCT_CATEGORY_IDS, 'default_not_found' ) );
		$this->assertEquals( 'default_not_found', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE, 'default_not_found' ) );
	}

	/**
	 * Test that reset_connection_only() clears connection status flags.
	 */
	public function test_reset_connection_only_clears_connection_status_flags(): void {
		// Set connection status flags
		update_option( MetaExtension::OPTION_HAS_CONNECTED_FBE_2, 'yes' );
		update_option( MetaExtension::OPTION_HAS_AUTHORIZED_PAGES, 'yes' );

		$this->connection->reset_connection_only();

		// Verify status flags are cleared
		$this->assertEquals( 'default_not_found', get_option( MetaExtension::OPTION_HAS_CONNECTED_FBE_2, 'default_not_found' ) );
		$this->assertEquals( 'default_not_found', get_option( MetaExtension::OPTION_HAS_AUTHORIZED_PAGES, 'default_not_found' ) );
	}

	/**
	 * Test the difference between reset_connection() and reset_connection_only().
	 */
	public function test_difference_between_reset_methods(): void {
		// Set all options
		$this->set_all_facebook_options();

		// Test reset_connection_only first
		$connection1 = new Connection( $this->plugin_mock );
		$connection1->reset_connection_only();

		// Count preserved options
		$preserved_count = 0;
		foreach ( $this->settings_options as $option ) {
			if ( get_option( $option, 'default_not_found' ) !== 'default_not_found' ) {
				$preserved_count++;
			}
		}

		$this->assertGreaterThan(
			0,
			$preserved_count,
			'reset_connection_only() should preserve some settings options'
		);

		// Reset and test reset_connection
		$this->set_all_facebook_options();
		$connection2 = new Connection( $this->plugin_mock );
		$connection2->reset_connection();

		// Count preserved options
		$preserved_count_after_full_reset = 0;
		foreach ( $this->settings_options as $option ) {
			if ( get_option( $option, 'default_not_found' ) !== 'default_not_found' ) {
				$preserved_count_after_full_reset++;
			}
		}

		$this->assertEquals(
			0,
			$preserved_count_after_full_reset,
			'reset_connection() should delete all settings options'
		);
	}
}
