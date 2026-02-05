<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Utilities;

use WooCommerce\Facebook\Utilities\DebugTools;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for DebugTools class.
 *
 * @since 3.5.2
 */
class DebugToolsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class can be instantiated.
	 */
	public function test_class_exists_and_can_be_instantiated() {
		$this->assertTrue( class_exists( DebugTools::class ) );
		
		$debug_tools = new DebugTools();
		$this->assertInstanceOf( DebugTools::class, $debug_tools );
	}

	/**
	 * Test constructor adds filter when in admin and not doing ajax.
	 */
	public function test_constructor_adds_filter_in_admin() {
		// Set up admin context
		set_current_screen( 'dashboard' );
		$this->assertTrue( is_admin() );
		
		// Create instance
		$debug_tools = new DebugTools();
		
		// Check filter was added
		$this->assertNotFalse( has_filter( 'woocommerce_debug_tools', [ $debug_tools, 'add_debug_tool' ] ) );
		
		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test add_debug_tool when not connected.
	 */
	public function test_add_debug_tool_when_not_connected() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// Mock the connection handler to return false
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( false );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Should add the two always-visible reset tools
		$this->assertArrayHasKey( 'wc_facebook_reset_connection_only', $result );
		$this->assertArrayHasKey( 'wc_facebook_reset_all_settings', $result );
		
		// Should have exactly 2 tools (the always-visible ones)
		$this->assertCount( 2, $result );
	}

	/**
	 * Test add_debug_tool when connected but debug mode disabled.
	 */
	public function test_add_debug_tool_when_connected_but_debug_disabled() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// Mock the connection handler to return true
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( true );
		
		// Mock the integration to return false for debug mode
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( false );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Should add the two always-visible reset tools
		$this->assertArrayHasKey( 'wc_facebook_reset_connection_only', $result );
		$this->assertArrayHasKey( 'wc_facebook_reset_all_settings', $result );
		
		// Should have exactly 2 tools (the always-visible ones)
		$this->assertCount( 2, $result );
	}

	/**
	 * Test add_debug_tool when connected and debug mode enabled.
	 */
	public function test_add_debug_tool_when_connected_and_debug_enabled() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// Mock the connection handler to return true
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( true );
		
		// Mock the integration to return true for debug mode
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Should add debug tools
		$this->assertArrayHasKey( 'wc_facebook_settings_reset', $result );
		$this->assertArrayHasKey( 'wc_facebook_delete_background_jobs', $result );
		$this->assertArrayHasKey( 'reset_all_product_fb_settings', $result );
		
		// Check tool structure
		$this->assertArrayHasKey( 'name', $result['wc_facebook_settings_reset'] );
		$this->assertArrayHasKey( 'button', $result['wc_facebook_settings_reset'] );
		$this->assertArrayHasKey( 'desc', $result['wc_facebook_settings_reset'] );
		$this->assertArrayHasKey( 'callback', $result['wc_facebook_settings_reset'] );
		
		// Check callbacks are callable
		$this->assertIsCallable( $result['wc_facebook_settings_reset']['callback'] );
		$this->assertIsCallable( $result['wc_facebook_delete_background_jobs']['callback'] );
		$this->assertIsCallable( $result['reset_all_product_fb_settings']['callback'] );
	}

	/**
	 * Test clean_up_old_background_sync_options method.
	 */
	public function test_clean_up_old_background_sync_options() {
		global $wpdb;
		
		// Insert test options directly into database
		$wpdb->insert( 
			$wpdb->options, 
			[
				'option_name' => 'wc_facebook_background_product_sync_1',
				'option_value' => 'test_value_1',
				'autoload' => 'yes'
			]
		);
		$wpdb->insert( 
			$wpdb->options, 
			[
				'option_name' => 'wc_facebook_background_product_sync_2',
				'option_value' => 'test_value_2',
				'autoload' => 'yes'
			]
		);
		$wpdb->insert( 
			$wpdb->options, 
			[
				'option_name' => 'other_option',
				'option_value' => 'should_not_be_deleted',
				'autoload' => 'yes'
			]
		);
		
		$debug_tools = new DebugTools();
		$result = $debug_tools->clean_up_old_background_sync_options();
		
		// Check result message
		$this->assertEquals( 'Background sync jobs have been deleted.', $result );
		
		// Verify background sync options were deleted
		$sync_option_1 = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wc_facebook_background_product_sync_1'" );
		$sync_option_2 = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wc_facebook_background_product_sync_2'" );
		$this->assertNull( $sync_option_1 );
		$this->assertNull( $sync_option_2 );
		
		// Verify other options were not deleted
		$other_option = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'other_option'" );
		$this->assertEquals( 'should_not_be_deleted', $other_option );
		
		// Clean up
		$wpdb->delete( $wpdb->options, [ 'option_name' => 'other_option' ] );
	}

	/**
	 * Test clear_facebook_settings method.
	 */
	public function test_clear_facebook_settings() {
		$debug_tools = new DebugTools();
		
		// Mock the connection handler with disconnect method
		$mock_connection_handler = $this->getMockBuilder( \WooCommerce\Facebook\Handlers\Connection::class )
			->disableOriginalConstructor()
			->onlyMethods( ['disconnect'] )
			->getMock();
		$mock_connection_handler->expects( $this->once() )->method( 'disconnect' );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->clear_facebook_settings();
		
		// Check result message
		$this->assertEquals( 'Cleared all Facebook settings!', $result );
	}

	/**
	 * Test reset_all_product_fb_settings method.
	 */
	public function test_reset_all_product_fb_settings() {
		$debug_tools = new DebugTools();
		
		// Create a mock job with queue_start method
		$mock_job = $this->getMockBuilder( \stdClass::class )
			->addMethods( ['queue_start'] )
			->getMock();
		$mock_job->expects( $this->once() )->method( 'queue_start' );
		
		// Mock the job manager
		$mock_job_manager = new \stdClass();
		$mock_job_manager->reset_all_product_fb_settings = $mock_job;
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->job_manager = $mock_job_manager;
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->reset_all_product_fb_settings();
		
		// Check result message
		$this->assertEquals( 'Reset products Facebook settings job started!', $result );
	}

	/**
	 * Test tool descriptions and labels.
	 */
	public function test_tool_descriptions_and_labels() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// Set up mocks for connected and debug enabled state
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( true );
		
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Test reset settings tool (legacy)
		$this->assertEquals( 'Facebook: Reset connection settings (legacy)', $result['wc_facebook_settings_reset']['name'] );
		$this->assertEquals( 'Reset settings', $result['wc_facebook_settings_reset']['button'] );
		$this->assertStringContainsString( 'clear your Facebook settings', $result['wc_facebook_settings_reset']['desc'] );
		
		// Test delete background jobs tool
		$this->assertEquals( 'Facebook: Delete Background Sync Jobs', $result['wc_facebook_delete_background_jobs']['name'] );
		$this->assertEquals( 'Clear Background Sync Jobs', $result['wc_facebook_delete_background_jobs']['button'] );
		$this->assertStringContainsString( 'background sync jobs', $result['wc_facebook_delete_background_jobs']['desc'] );
		
		// Test reset products tool
		$this->assertEquals( 'Facebook: Reset all products', $result['reset_all_product_fb_settings']['name'] );
		$this->assertEquals( 'Reset products Facebook settings', $result['reset_all_product_fb_settings']['button'] );
		$this->assertStringContainsString( 'reset Facebook settings for all products', $result['reset_all_product_fb_settings']['desc'] );
	}

	/**
	 * Test add_debug_tool preserves existing tools.
	 */
	public function test_add_debug_tool_preserves_existing_tools() {
		$debug_tools = new DebugTools();
		$existing_tools = [
			'existing_tool' => [
				'name' => 'Existing Tool',
				'button' => 'Run',
				'desc' => 'An existing tool',
				'callback' => '__return_true'
			]
		];
		
		// Set up mocks for connected and debug enabled state
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( true );
		
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $existing_tools );
		
		// Should preserve existing tool
		$this->assertArrayHasKey( 'existing_tool', $result );
		$this->assertEquals( 'Existing Tool', $result['existing_tool']['name'] );
		
		// Should add new tools
		$this->assertArrayHasKey( 'wc_facebook_settings_reset', $result );
		$this->assertArrayHasKey( 'wc_facebook_delete_background_jobs', $result );
		$this->assertArrayHasKey( 'reset_all_product_fb_settings', $result );
		
	}

	/**
	 * Test that new reset tools are always added (not requiring debug mode).
	 */
	public function test_add_debug_tool_includes_new_reset_tools_without_debug_mode() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// Mock the connection handler to return false (not connected)
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( false );
		
		// Mock the integration to return false for debug mode
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( false );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// New reset tools should be added even without debug mode
		$this->assertArrayHasKey( 'wc_facebook_reset_connection_only', $result );
		$this->assertArrayHasKey( 'wc_facebook_reset_all_settings', $result );
		
		// Legacy tools should NOT be added without debug mode
		$this->assertArrayNotHasKey( 'wc_facebook_settings_reset', $result );
		$this->assertArrayNotHasKey( 'wc_facebook_delete_background_jobs', $result );
		$this->assertArrayNotHasKey( 'reset_all_product_fb_settings', $result );
	}

	/**
	 * Test reset_connection_data method.
	 */
	public function test_reset_connection_data() {
		$debug_tools = new DebugTools();
		
		// Mock the connection handler with reset_connection_only method
		$mock_connection_handler = $this->getMockBuilder( \WooCommerce\Facebook\Handlers\Connection::class )
			->disableOriginalConstructor()
			->onlyMethods( ['reset_connection_only'] )
			->getMock();
		$mock_connection_handler->expects( $this->once() )->method( 'reset_connection_only' );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->reset_connection_data();
		
		// Check result message
		$this->assertEquals( 'Facebook connection data has been reset. Your other settings have been preserved. You can now reconnect to Facebook.', $result );
	}

	/**
	 * Test reset_all_settings method.
	 */
	public function test_reset_all_settings() {
		$debug_tools = new DebugTools();
		
		// Mock the connection handler with reset_connection method
		$mock_connection_handler = $this->getMockBuilder( \WooCommerce\Facebook\Handlers\Connection::class )
			->disableOriginalConstructor()
			->onlyMethods( ['reset_connection'] )
			->getMock();
		$mock_connection_handler->expects( $this->once() )->method( 'reset_connection' );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->reset_all_settings();
		
		// Check result message
		$this->assertEquals( 'All Facebook settings have been completely reset. The plugin is now in a fresh installation state.', $result );
	}

	/**
	 * Test new reset tool descriptions and labels.
	 */
	public function test_new_reset_tool_descriptions() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// No need to mock connection/debug since new tools are always visible
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( false );
		
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( false );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Test reset connection data tool
		$this->assertEquals( 'Facebook: Reset connection data', $result['wc_facebook_reset_connection_only']['name'] );
		$this->assertEquals( 'Reset connection', $result['wc_facebook_reset_connection_only']['button'] );
		$this->assertStringContainsString( 'connection tokens and IDs', $result['wc_facebook_reset_connection_only']['desc'] );
		$this->assertStringContainsString( 'preserving your other settings', $result['wc_facebook_reset_connection_only']['desc'] );
		
		// Test reset all settings tool
		$this->assertEquals( 'Facebook: Reset all settings', $result['wc_facebook_reset_all_settings']['name'] );
		$this->assertEquals( 'Reset all settings', $result['wc_facebook_reset_all_settings']['button'] );
		$this->assertStringContainsString( 'completely reset ALL Facebook-related settings', $result['wc_facebook_reset_all_settings']['desc'] );
		$this->assertStringContainsString( 'fresh installation state', $result['wc_facebook_reset_all_settings']['desc'] );
		
		// Check callbacks are callable
		$this->assertIsCallable( $result['wc_facebook_reset_connection_only']['callback'] );
		$this->assertIsCallable( $result['wc_facebook_reset_all_settings']['callback'] );
	}

	/**
	 * Test that legacy tool is marked correctly.
	 */
	public function test_legacy_tool_marked_correctly() {
		$debug_tools = new DebugTools();
		$tools = [];
		
		// Set up mocks for connected and debug enabled state
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->method( 'is_connected' )->willReturn( true );
		
		$mock_integration = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$mock_integration->method( 'is_debug_mode_enabled' )->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		$mock_plugin->method( 'get_integration' )->willReturn( $mock_integration );
		
		// Use filter to override the instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Legacy tool should be marked as "(legacy)"
		$this->assertStringContainsString( '(legacy)', $result['wc_facebook_settings_reset']['name'] );
	}
} 