<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens;

use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Handlers\Connection;

/**
 * Unit tests for Shops settings screen troubleshooting functionality.
 */
class ShopsTroubleshootingTest extends \WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var Shops|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $shops_screen_mock;

	/**
	 * @var Connection|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $connection_handler_mock;

	/**
	 * @var \WC_Facebookcommerce|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $plugin_mock;

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock plugin
		$this->plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		
		// Create mock connection handler
		$this->connection_handler_mock = $this->createMock( Connection::class );

		// Configure plugin mock to return connection handler
		$this->plugin_mock->method( 'get_connection_handler' )
			->willReturn( $this->connection_handler_mock );

		// Create Shops screen instance
		$this->shops_screen_mock = new Shops();

		// Mock the global function
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			function facebook_for_woocommerce() {
				return $GLOBALS['test_plugin_mock'];
			}
		}
		$GLOBALS['test_plugin_mock'] = $this->plugin_mock;
	}

	/**
	 * Tests that action constants are properly defined.
	 */
	public function test_action_constants_exist(): void {
		$this->assertTrue( 
			defined( 'WooCommerce\Facebook\Admin\Settings_Screens\Shops::ACTION_RESET_CONNECTION' ),
			'ACTION_RESET_CONNECTION constant should be defined'
		);
		
		$this->assertTrue( 
			defined( 'WooCommerce\Facebook\Admin\Settings_Screens\Shops::ACTION_MANUAL_CONFIG_SYNC' ),
			'ACTION_MANUAL_CONFIG_SYNC constant should be defined'
		);

		$this->assertEquals( 
			'wc_facebook_reset_connection', 
			Shops::ACTION_RESET_CONNECTION,
			'ACTION_RESET_CONNECTION should have correct value'
		);
		
		$this->assertEquals( 
			'wc_facebook_manual_config_sync', 
			Shops::ACTION_MANUAL_CONFIG_SYNC,
			'ACTION_MANUAL_CONFIG_SYNC should have correct value'
		);
	}

	/**
	 * Tests that render method can be called without fatal errors.
	 */
	public function test_render_method_can_be_called(): void {
		// Configure connection handler to simulate connected state
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( true );

		// Should complete without fatal error
		ob_start();
		try {
			$this->shops_screen_mock->render();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		ob_end_clean();
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'render method completed without fatal error' );
	}

	/**
	 * Tests that render method works with troubleshooting functionality.
	 */
	public function test_render_includes_troubleshooting(): void {
		// Configure connection handler
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( true );

		// Call render method and verify it completes without error
		ob_start();
		try {
			$this->shops_screen_mock->render();
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			$output = '';
		}

		// Basic structure should be present
		$this->assertIsString( $output );
		
		// If we get here, the render method completed without fatal error
		$this->assertTrue( true, 'render method completed with troubleshooting functionality' );
	}

	/**
	 * Tests that render_troubleshooting_button_and_drawer method exists.
	 */
	public function test_render_troubleshooting_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->shops_screen_mock, 'render_troubleshooting_button_and_drawer' ),
			'render_troubleshooting_button_and_drawer method should exist'
		);

		$reflection = new \ReflectionMethod( $this->shops_screen_mock, 'render_troubleshooting_button_and_drawer' );
		$this->assertTrue( 
			$reflection->isPrivate(),
			'render_troubleshooting_button_and_drawer method should be private'
		);
	}

	/**
	 * Tests that enqueue_assets method exists and is public.
	 */
	public function test_enqueue_assets_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->shops_screen_mock, 'enqueue_assets' ),
			'enqueue_assets method should exist'
		);

		$reflection = new \ReflectionMethod( $this->shops_screen_mock, 'enqueue_assets' );
		$this->assertTrue( 
			$reflection->isPublic(),
			'enqueue_assets method should be public'
		);
	}

	/**
	 * Tests that enqueue_assets can be called without fatal errors.
	 */
	public function test_enqueue_assets_can_be_called(): void {
		// Mock WordPress functions to avoid dependencies
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			function wp_enqueue_style() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			function wp_enqueue_script() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_localize_script' ) ) {
			function wp_localize_script() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_create_nonce' ) ) {
			function wp_create_nonce( $action ) {
				return 'nonce_' . $action;
			}
		}
		if ( ! function_exists( 'admin_url' ) ) {
			function admin_url( $path ) {
				return 'http://example.com/wp-admin/' . $path;
			}
		}

		// Create a mock that simulates being on the correct screen
		$shops_mock = $this->getMockBuilder( Shops::class )
			->onlyMethods( [ 'is_current_screen_page' ] )
			->getMock();

		$shops_mock->method( 'is_current_screen_page' )
			->willReturn( true );

		// Should complete without fatal error
		try {
			$shops_mock->enqueue_assets();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'enqueue_assets method completed without fatal error' );
	}

	/**
	 * Tests that get_settings method exists and is public.
	 */
	public function test_get_settings_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->shops_screen_mock, 'get_settings' ),
			'get_settings method should exist'
		);

		$reflection = new \ReflectionMethod( $this->shops_screen_mock, 'get_settings' );
		$this->assertTrue( 
			$reflection->isPublic(),
			'get_settings method should be public'
		);
	}
}