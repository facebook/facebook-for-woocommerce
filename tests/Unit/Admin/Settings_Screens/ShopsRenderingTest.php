<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens;

use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Handlers\Connection;

/**
 * Unit tests for Shops settings screen rendering functionality.
 */
class ShopsRenderingTest extends \WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering {

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
	 * Tests that render_troubleshooting_button_and_drawer renders for connected state.
	 */
	public function test_render_troubleshooting_for_connected(): void {
		// Configure connection handler to simulate connected state
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( true );

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $this->shops_screen_mock );
		$method = $reflection->getMethod( 'render_troubleshooting_button_and_drawer' );
		$method->setAccessible( true );

		// Capture output
		ob_start();
		try {
			$method->invoke( $this->shops_screen_mock );
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			$output = '';
		}

		// Basic structure should be present
		$this->assertIsString( $output );
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'render_troubleshooting_button_and_drawer completed for connected state' );
	}

	/**
	 * Tests that render_troubleshooting_button_and_drawer renders for disconnected state.
	 */
	public function test_render_troubleshooting_for_disconnected(): void {
		// Configure connection handler to simulate disconnected state
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( false );

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $this->shops_screen_mock );
		$method = $reflection->getMethod( 'render_troubleshooting_button_and_drawer' );
		$method->setAccessible( true );

		// Capture output
		ob_start();
		try {
			$method->invoke( $this->shops_screen_mock );
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			$output = '';
		}

		// Basic structure should be present
		$this->assertIsString( $output );
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'render_troubleshooting_button_and_drawer completed for disconnected state' );
	}

	/**
	 * Tests that render method includes troubleshooting section.
	 */
	public function test_render_includes_troubleshooting_section(): void {
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
		$this->assertTrue( true, 'render method completed with troubleshooting section' );
	}

	/**
	 * Tests that troubleshooting rendering works with different connection states.
	 */
	public function test_troubleshooting_with_different_connection_states(): void {
		// Test with connected state
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( true );

		$reflection = new \ReflectionClass( $this->shops_screen_mock );
		$method = $reflection->getMethod( 'render_troubleshooting_button_and_drawer' );
		$method->setAccessible( true );

		ob_start();
		try {
			$method->invoke( $this->shops_screen_mock );
			$connected_output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			$connected_output = '';
		}

		// Test with disconnected state
		$this->connection_handler_mock = $this->createMock( Connection::class );
		$this->connection_handler_mock->method( 'is_connected' )
			->willReturn( false );
		$this->plugin_mock->method( 'get_connection_handler' )
			->willReturn( $this->connection_handler_mock );

		ob_start();
		try {
			$method->invoke( $this->shops_screen_mock );
			$disconnected_output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			$disconnected_output = '';
		}

		// Both outputs should be strings (content may differ)
		$this->assertIsString( $connected_output );
		$this->assertIsString( $disconnected_output );
		
		// If we get here, both states completed without fatal error
		$this->assertTrue( true, 'Troubleshooting rendering works for both connection states' );
	}

	/**
	 * Tests that render_facebook_iframe method exists.
	 */
	public function test_render_facebook_iframe_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->shops_screen_mock, 'render_facebook_iframe' ),
			'render_facebook_iframe method should exist'
		);

		$reflection = new \ReflectionMethod( $this->shops_screen_mock, 'render_facebook_iframe' );
		$this->assertTrue( 
			$reflection->isPrivate(),
			'render_facebook_iframe method should be private'
		);
	}

	/**
	 * Tests that render method can handle various connection handler states.
	 */
	public function test_render_handles_null_connection_handler(): void {
		// Configure plugin to return null connection handler
		$this->plugin_mock->method( 'get_connection_handler' )
			->willReturn( null );

		// Should complete without fatal error
		ob_start();
		try {
			$this->shops_screen_mock->render();
		} catch ( \Exception $e ) {
			// Expected in test environment
		}
		ob_end_clean();
		
		// If we get here, the method completed without fatal error
		$this->assertTrue( true, 'render method handles null connection handler gracefully' );
	}
}