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
		
		// Replace global function temporarily
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Should return unchanged tools array
		$this->assertEquals( $tools, $result );
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
		
		// Replace global function temporarily
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Should return unchanged tools array
		$this->assertEquals( $tools, $result );
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
		
		// Replace global function temporarily
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Should add debug tools
		$this->assertArrayHasKey( 'wc_facebook_settings_reset', $result );
		$this->assertArrayHasKey( 'wc_facebook_delete_background_jobs', $result );
		$this->assertArrayHasKey( 'reset_all_product_fb_settings', $result );
		$this->assertArrayHasKey( 'wc_facebook_delete_all_products', $result );
		
		// Check tool structure
		$this->assertArrayHasKey( 'name', $result['wc_facebook_settings_reset'] );
		$this->assertArrayHasKey( 'button', $result['wc_facebook_settings_reset'] );
		$this->assertArrayHasKey( 'desc', $result['wc_facebook_settings_reset'] );
		$this->assertArrayHasKey( 'callback', $result['wc_facebook_settings_reset'] );
		
		// Check callbacks are callable
		$this->assertIsCallable( $result['wc_facebook_settings_reset']['callback'] );
		$this->assertIsCallable( $result['wc_facebook_delete_background_jobs']['callback'] );
		$this->assertIsCallable( $result['reset_all_product_fb_settings']['callback'] );
		$this->assertIsCallable( $result['wc_facebook_delete_all_products']['callback'] );
	}

	/**
	 * Test clean_up_old_background_sync_options method.
	 */
	public function test_clean_up_old_background_sync_options() {
		global $wpdb;
		
		// Insert test options
		$test_options = [
			'wc_facebook_background_product_sync_1' => 'test_value_1',
			'wc_facebook_background_product_sync_2' => 'test_value_2',
			'other_option' => 'should_not_be_deleted'
		];
		
		foreach ( $test_options as $option => $value ) {
			update_option( $option, $value );
		}
		
		$debug_tools = new DebugTools();
		$result = $debug_tools->clean_up_old_background_sync_options();
		
		// Check result message
		$this->assertEquals( 'Background sync jobs have been deleted.', $result );
		
		// Verify background sync options were deleted
		$this->assertFalse( get_option( 'wc_facebook_background_product_sync_1' ) );
		$this->assertFalse( get_option( 'wc_facebook_background_product_sync_2' ) );
		
		// Verify other options were not deleted
		$this->assertEquals( 'should_not_be_deleted', get_option( 'other_option' ) );
		
		// Clean up
		delete_option( 'other_option' );
	}

	/**
	 * Test clear_facebook_settings method.
	 */
	public function test_clear_facebook_settings() {
		$debug_tools = new DebugTools();
		
		// Mock the connection handler
		$mock_connection_handler = $this->createMock( \WooCommerce\Facebook\Handlers\Connection::class );
		$mock_connection_handler->expects( $this->once() )->method( 'disconnect' );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_connection_handler' )->willReturn( $mock_connection_handler );
		
		// Replace global function temporarily
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
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
		
		// Mock the job
		$mock_job = $this->createMock( \stdClass::class );
		$mock_job->expects( $this->once() )->method( 'queue_start' );
		
		// Mock the job manager
		$mock_job_manager = $this->createMock( \stdClass::class );
		$mock_job_manager->reset_all_product_fb_settings = $mock_job;
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->job_manager = $mock_job_manager;
		
		// Replace global function temporarily
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->reset_all_product_fb_settings();
		
		// Check result message
		$this->assertEquals( 'Reset products Facebook settings job started!', $result );
	}

	/**
	 * Test delete_all_products method.
	 */
	public function test_delete_all_products() {
		$debug_tools = new DebugTools();
		
		// Mock the job
		$mock_job = $this->createMock( \stdClass::class );
		$mock_job->expects( $this->once() )->method( 'queue_start' );
		
		// Mock the job manager
		$mock_job_manager = $this->createMock( \stdClass::class );
		$mock_job_manager->delete_all_products = $mock_job;
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->job_manager = $mock_job_manager;
		
		// Replace global function temporarily
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->delete_all_products();
		
		// Check result message
		$this->assertEquals( 'Delete products from Facebook catalog job started!', $result );
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
		
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$result = $debug_tools->add_debug_tool( $tools );
		
		// Test reset settings tool
		$this->assertEquals( 'Facebook: Reset connection settings', $result['wc_facebook_settings_reset']['name'] );
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
		
		// Test delete all products tool
		$this->assertEquals( 'Facebook: Delete all products from your Facebook Catalog', $result['wc_facebook_delete_all_products']['name'] );
		$this->assertEquals( 'Delete all products', $result['wc_facebook_delete_all_products']['button'] );
		$this->assertStringContainsString( 'delete all products from', $result['wc_facebook_delete_all_products']['desc'] );
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
		
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce', function() use ( $mock_plugin ) {
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
		$this->assertArrayHasKey( 'wc_facebook_delete_all_products', $result );
	}
} 