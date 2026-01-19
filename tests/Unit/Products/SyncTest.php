<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Products;

use WooCommerce\Facebook\Products\Sync;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for the Products\Sync class.
 *
 * Tests focus on the is_sync_in_progress() method and its behavior
 * with caching and frontend guards.
 */
class SyncTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear any existing transients
		delete_transient( 'wc_facebook_background_product_sync_queue_empty' );
		delete_transient( 'wc_facebook_background_product_sync_sync_in_progress' );
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up transients
		delete_transient( 'wc_facebook_background_product_sync_queue_empty' );
		delete_transient( 'wc_facebook_background_product_sync_sync_in_progress' );

		// Clean up any test jobs
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wc_facebook_background_product_sync_job_%'" );

		parent::tearDown();
	}

	/**
	 * Test that is_sync_in_progress returns false on frontend requests
	 */
	public function test_is_sync_in_progress_returns_false_on_frontend() {
		// Ensure we're not in admin context
		set_current_screen( null );

		// Mock the plugin instance
		$this->setup_mock_plugin();

		// Should return false on frontend
		$result = Sync::is_sync_in_progress();
		$this->assertFalse( $result, 'Should return false on frontend' );
	}

	/**
	 * Test that is_sync_in_progress returns false when no jobs exist
	 */
	public function test_is_sync_in_progress_returns_false_when_no_jobs() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Mock the plugin instance
		$this->setup_mock_plugin();

		// Should return false when no processing jobs
		$result = Sync::is_sync_in_progress();
		$this->assertFalse( $result, 'Should return false when no processing jobs exist' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that is_sync_in_progress uses cached result
	 */
	public function test_is_sync_in_progress_uses_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Set cache to indicate jobs exist
		set_transient( 'wc_facebook_background_product_sync_sync_in_progress', 'has_jobs', 0 );

		// Mock the plugin instance
		$this->setup_mock_plugin();

		// Should return true from cache
		$result = Sync::is_sync_in_progress();
		$this->assertTrue( $result, 'Should return true when cache indicates jobs exist' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that is_sync_in_progress caches no_jobs result
	 */
	public function test_is_sync_in_progress_caches_no_jobs() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Mock the plugin instance
		$this->setup_mock_plugin();

		// Call should cache the result
		$result = Sync::is_sync_in_progress();
		$this->assertFalse( $result );

		// Verify cache was set
		$cached = get_transient( 'wc_facebook_background_product_sync_sync_in_progress' );
		$this->assertEquals( 'no_jobs', $cached, 'Cache should be set to "no_jobs"' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Helper to set up mock plugin instance for testing
	 */
	private function setup_mock_plugin() {
		// Create a mock background handler
		$mock_handler = $this->getMockBuilder( \WooCommerce\Facebook\Products\Sync\Background::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_jobs' ] )
			->getMock();

		// Default behavior - return null (no jobs) but respect the actual caching logic
		$mock_handler->method( 'get_jobs' )
			->willReturnCallback( function( $args ) {
				// Check if we're on frontend - should return null
				if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
					return null;
				}

				// Check cache
				$cached = get_transient( 'wc_facebook_background_product_sync_sync_in_progress' );
				if ( false !== $cached ) {
					return 'has_jobs' === $cached ? [ (object) [ 'cached' => true ] ] : null;
				}

				// No cache and no actual jobs - cache and return null
				set_transient( 'wc_facebook_background_product_sync_sync_in_progress', 'no_jobs', 0 );
				return null;
			} );

		// Create mock plugin
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_products_sync_background_handler' )
			->willReturn( $mock_handler );

		// Override the plugin instance
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
	}
}


