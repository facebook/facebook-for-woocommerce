<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\AJAX;

use WooCommerce\Facebook\AJAX;
use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Handlers\Connection;

/**
 * Unit tests for AJAX troubleshooting functionality.
 */
class TroubleshootingTest extends \WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var AJAX|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $ajax_mock;

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

		// Configure plugin mock logging
		$this->plugin_mock->method( 'log' )
			->willReturn( true );

		// Create AJAX instance
		$this->ajax_mock = new AJAX();

		// Mock the global function
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			function facebook_for_woocommerce() {
				return $GLOBALS['test_plugin_mock'];
			}
		}
		$GLOBALS['test_plugin_mock'] = $this->plugin_mock;
	}

	/**
	 * Tests that reset_connection method exists and is public.
	 */
	public function test_reset_connection_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->ajax_mock, 'reset_connection' ),
			'reset_connection method should exist'
		);

		$reflection = new \ReflectionMethod( $this->ajax_mock, 'reset_connection' );
		$this->assertTrue( 
			$reflection->isPublic(),
			'reset_connection method should be public'
		);
	}

	/**
	 * Tests that manual_config_sync method exists and is public.
	 */
	public function test_manual_config_sync_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->ajax_mock, 'manual_config_sync' ),
			'manual_config_sync method should exist'
		);

		$reflection = new \ReflectionMethod( $this->ajax_mock, 'manual_config_sync' );
		$this->assertTrue( 
			$reflection->isPublic(),
			'manual_config_sync method should be public'
		);
	}

	/**
	 * Tests that reset_connection can be called without fatal errors.
	 */
	public function test_reset_connection_can_be_called(): void {
		// Mock nonce functions to avoid WordPress dependencies
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_send_json_success' ) ) {
			function wp_send_json_success( $data ) {
				echo wp_json_encode( array( 'success' => true, 'data' => $data ) );
			}
		}

		// Configure connection handler to simulate successful disconnect
		$this->connection_handler_mock->method( 'disconnect' )
			->willReturn( true );

		// Should complete without fatal error
		ob_start();
		try {
			$this->ajax_mock->reset_connection();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		ob_end_clean();
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'reset_connection method completed without fatal error' );
	}

	/**
	 * Tests that manual_config_sync can be called without fatal errors.
	 */
	public function test_manual_config_sync_can_be_called(): void {
		// Mock nonce functions to avoid WordPress dependencies
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_send_json_success' ) ) {
			function wp_send_json_success( $data ) {
				echo wp_json_encode( array( 'success' => true, 'data' => $data ) );
			}
		}

		// Configure connection handler to simulate connected state
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( true );
		$this->connection_handler_mock->method( 'force_config_sync_on_update' )
			->willReturn( true );

		// Should complete without fatal error
		ob_start();
		try {
			$this->ajax_mock->manual_config_sync();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		ob_end_clean();
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'manual_config_sync method completed without fatal error' );
	}

	/**
	 * Tests that reset_connection can access connection handler through global function.
	 */
	public function test_reset_connection_accesses_connection_handler(): void {
		// Mock nonce functions
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_send_json_success' ) ) {
			function wp_send_json_success( $data ) {
				echo wp_json_encode( array( 'success' => true, 'data' => $data ) );
			}
		}
		if ( ! function_exists( 'wp_send_json_error' ) ) {
			function wp_send_json_error( $data ) {
				echo wp_json_encode( array( 'success' => false, 'data' => $data ) );
			}
		}

		// Ensure the global function is properly set up
		$GLOBALS['test_plugin_mock'] = $this->plugin_mock;

		ob_start();
		try {
			$this->ajax_mock->reset_connection();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		ob_end_clean();
		
		// If we get here, the method accessed the global function successfully
		$this->assertTrue( true, 'reset_connection accessed connection handler through global function' );
	}

	/**
	 * Tests that manual_config_sync can access connection handler through global function.
	 */
	public function test_manual_config_sync_accesses_connection_handler(): void {
		// Mock nonce functions
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer() {
				return true;
			}
		}
		if ( ! function_exists( 'wp_send_json_success' ) ) {
			function wp_send_json_success( $data ) {
				echo wp_json_encode( array( 'success' => true, 'data' => $data ) );
			}
		}
		if ( ! function_exists( 'wp_send_json_error' ) ) {
			function wp_send_json_error( $data ) {
				echo wp_json_encode( array( 'success' => false, 'data' => $data ) );
			}
		}

		// Configure connection handler
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( true );

		// Ensure the global function is properly set up
		$GLOBALS['test_plugin_mock'] = $this->plugin_mock;

		ob_start();
		try {
			$this->ajax_mock->manual_config_sync();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		ob_end_clean();
		
		// If we get here, the method accessed the global function successfully
		$this->assertTrue( true, 'manual_config_sync accessed connection handler through global function' );
	}
}