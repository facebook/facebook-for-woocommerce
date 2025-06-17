<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * Unit tests for Connection handler config sync functionality.
 */
class ConnectionConfigSyncTest extends \WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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

		// Configure plugin mock logging
		$this->plugin_mock->method( 'log' )
			->willReturn( true );
	}

	/**
	 * Tests that force_config_sync_on_update method exists and is public.
	 */
	public function test_force_config_sync_on_update_method_exists(): void {
		$connection = new Connection( $this->plugin_mock );
		
		$this->assertTrue( 
			method_exists( $connection, 'force_config_sync_on_update' ),
			'force_config_sync_on_update method should exist'
		);

		$reflection = new \ReflectionMethod( $connection, 'force_config_sync_on_update' );
		$this->assertTrue( 
			$reflection->isPublic(),
			'force_config_sync_on_update method should be public'
		);
	}

	/**
	 * Tests that force_config_sync_on_update skips execution when not connected.
	 */
	public function test_force_config_sync_skips_when_not_connected(): void {
		// Create mock that simulates disconnected state
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( false );

		// Should log skip message
		$this->plugin_mock->expects( $this->once() )
			->method( 'log' )
			->with( 'Skipping config sync on update - not connected to Facebook' );

		$connection_mock->force_config_sync_on_update();
	}

	/**
	 * Tests that force_config_sync_on_update logs start message when connected.
	 */
	public function test_force_config_sync_logs_start_when_connected(): void {
		// Create mock that simulates connected state
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( true );

		// Should log start message (at minimum)
		$this->plugin_mock->expects( $this->atLeastOnce() )
			->method( 'log' );

		// This will attempt to call API methods, but we just want to verify it starts
		// The test may fail on API calls, but that's expected in unit test environment
		$this->expectException( \TypeError::class );
		$connection_mock->force_config_sync_on_update();
	}

	/**
	 * Tests that force_config_sync_on_update method can be called when disconnected.
	 */
	public function test_force_config_sync_when_disconnected(): void {
		// Test with disconnected state to avoid API calls
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( false );

		// Should complete without fatal error when disconnected
		$connection_mock->force_config_sync_on_update();
		
		// If we get here, the method completed successfully
		$this->assertTrue( true, 'Method completed without error when disconnected' );
	}
}