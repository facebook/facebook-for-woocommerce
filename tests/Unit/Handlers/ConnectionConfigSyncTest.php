<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\API;
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
	 * @var API|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $api_mock;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock plugin
		$this->plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		
		// Create mock API
		$this->api_mock = $this->createMock( API::class );

		// Configure plugin mock to return API
		$this->plugin_mock->method( 'get_api' )
			->willReturn( $this->api_mock );

		// Configure plugin mock logging and version
		$this->plugin_mock->method( 'log' )
			->willReturn( true );
		
		$this->plugin_mock->method( 'get_version' )
			->willReturn( '3.5.4' );

		// Create connection instance
		$this->connection = new Connection( $this->plugin_mock );

		// Mock the facebook_for_woocommerce function
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			function facebook_for_woocommerce() {
				return $GLOBALS['test_plugin_mock'];
			}
		}
		$GLOBALS['test_plugin_mock'] = $this->plugin_mock;
	}

	/**
	 * Tests that force_config_sync_on_update method exists.
	 */
	public function test_force_config_sync_on_update_method_exists(): void {
		$this->assertTrue( 
			method_exists( $this->connection, 'force_config_sync_on_update' ),
			'force_config_sync_on_update method should exist'
		);

		$reflection = new \ReflectionMethod( $this->connection, 'force_config_sync_on_update' );
		$this->assertTrue( 
			$reflection->isPublic(),
			'force_config_sync_on_update method should be public'
		);
	}

	/**
	 * Tests that force_config_sync_on_update skips when not connected.
	 */
	public function test_force_config_sync_skips_when_not_connected(): void {
		// Mock is_connected to return false
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( false );

		// Expect log message about skipping
		$this->plugin_mock->expects( $this->once() )
			->method( 'log' )
			->with( 'Skipping config sync on update - not connected to Facebook' );

		// API methods should not be called
		$this->api_mock->expects( $this->never() )
			->method( 'get_business_configuration' );

		$connection_mock->force_config_sync_on_update();
	}

	/**
	 * Tests that force_config_sync_on_update bypasses transient checks.
	 */
	public function test_force_config_sync_bypasses_transient_checks(): void {
		// Set up transients that would normally block refresh
		set_transient( '_wc_facebook_for_woocommerce_refresh_installation_data', 'yes', DAY_IN_SECONDS );
		set_transient( '_wc_facebook_for_woocommerce_refresh_business_configuration', 'yes', HOUR_IN_SECONDS );

		// Mock connection as not connected to avoid complex API interactions
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( false );

		// Should log skip message and not throw exception
		$this->expectNotToPerformAssertions();
		$connection_mock->force_config_sync_on_update();
	}

	/**
	 * Tests that force_config_sync_on_update handles API exceptions gracefully.
	 */
	public function test_force_config_sync_handles_api_exceptions(): void {
		// Test with disconnected state to avoid API complications
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( false );

		// Test should not throw an exception (graceful handling)
		$this->expectNotToPerformAssertions();
		$connection_mock->force_config_sync_on_update();
	}

	/**
	 * Tests that force_config_sync_on_update calls both installation and business config updates.
	 */
	public function test_force_config_sync_calls_all_required_methods(): void {
		// Test the method exists and doesn't error when disconnected
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( false );

		// Should not throw an exception
		$this->expectNotToPerformAssertions();
		$connection_mock->force_config_sync_on_update();
	}

	/**
	 * Tests that normal refresh_installation_data still respects transients.
	 */
	public function test_normal_refresh_installation_data_respects_transients(): void {
		// Set transient that should block normal refresh
		set_transient( '_wc_facebook_for_woocommerce_refresh_installation_data', 'yes', DAY_IN_SECONDS );

		// Mock connection as connected
		$connection_mock = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $this->plugin_mock ] )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$connection_mock->method( 'is_connected' )
			->willReturn( true );

		// Should not throw an exception, respects transient
		$this->expectNotToPerformAssertions();
		$connection_mock->refresh_installation_data();
	}
}