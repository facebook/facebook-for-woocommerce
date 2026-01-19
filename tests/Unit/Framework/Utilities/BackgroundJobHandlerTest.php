<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Framework\Utilities;

use WooCommerce\Facebook\Framework\Utilities\BackgroundJobHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for the BackgroundJobHandler class.
 *
 * Tests focus on the caching mechanism and frontend guards
 * implemented to fix performance issues with expensive database queries.
 */
class BackgroundJobHandlerTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var TestableBackgroundJobHandler
	 */
	private $handler;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear any existing transients
		delete_transient( 'test_background_job_queue_empty' );
		delete_transient( 'test_background_job_sync_in_progress' );

		$this->handler = new TestableBackgroundJobHandler();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up transients
		delete_transient( 'test_background_job_queue_empty' );
		delete_transient( 'test_background_job_sync_in_progress' );

		// Clean up any test jobs
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'test_background_job_%'" );

		parent::tearDown();
	}

	/**
	 * Helper to invoke protected/private methods
	 */
	private function invokeMethod( $object, $methodName, array $parameters = [] ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $methodName );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	/**
	 * Helper to get protected/private property value
	 */
	private function get_protected_property( $object, $propertyName ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$property   = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );

		return $property->getValue( $object );
	}

	// ==========================================================================
	// Tests for is_queue_empty() caching
	// ==========================================================================

	/**
	 * Test that is_queue_empty() caches its result
	 */
	public function test_is_queue_empty_caches_result() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// First call should query the database and cache the result
		$result1 = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertTrue( $result1, 'Queue should be empty initially' );

		// Verify cache was set
		$cached = get_transient( 'test_background_job_queue_empty' );
		$this->assertEquals( 'empty', $cached, 'Cache should contain "empty"' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that is_queue_empty() returns cached result on subsequent calls
	 */
	public function test_is_queue_empty_uses_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Set cache manually
		set_transient( 'test_background_job_queue_empty', 'not_empty', 0 );

		// Call should return cached value without querying database
		$result = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertFalse( $result, 'Should return false from cached "not_empty" value' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that is_queue_empty() returns true when queue is empty
	 * 
	 * Note: Testing actual frontend context is difficult in PHPUnit as is_admin()
	 * often returns true. The frontend guard is tested via integration tests.
	 */
	public function test_is_queue_empty_returns_true_when_empty() {
		// Simulate admin context 
		set_current_screen( 'dashboard' );

		// Should return true when queue is empty
		$result = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertTrue( $result, 'Should return true when queue is empty' );

		// Cache should be set
		$cached = get_transient( 'test_background_job_queue_empty' );
		$this->assertNotFalse( $cached, 'Cache should be set after query' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that is_queue_empty() runs query in admin context
	 */
	public function test_is_queue_empty_runs_query_in_admin() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// First call should query and cache
		$result = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertTrue( $result );

		// Cache should be set
		$cached = get_transient( 'test_background_job_queue_empty' );
		$this->assertNotFalse( $cached, 'Cache should be set in admin context' );

		// Clean up
		set_current_screen( null );
	}

	// ==========================================================================
	// Tests for get_jobs() caching
	// ==========================================================================

	/**
	 * Test that get_jobs() returns null on frontend requests
	 */
	public function test_get_jobs_returns_null_on_frontend() {
		// Ensure we're not in admin context
		set_current_screen( null );

		$jobs = $this->handler->get_jobs( [ 'status' => 'processing' ] );
		$this->assertNull( $jobs, 'Should return null on frontend' );
	}

	/**
	 * Test that get_jobs() caches processing status queries
	 */
	public function test_get_jobs_caches_processing_status_query() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Call get_jobs with status=processing
		$jobs = $this->handler->get_jobs( [ 'status' => 'processing' ] );
		$this->assertNull( $jobs, 'Should return null when no processing jobs' );

		// Verify cache was set
		$cached = get_transient( 'test_background_job_sync_in_progress' );
		$this->assertEquals( 'no_jobs', $cached, 'Cache should contain "no_jobs"' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that get_jobs() uses cached result for processing status
	 */
	public function test_get_jobs_uses_cache_for_processing_status() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Set cache manually to indicate jobs exist
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Call should return cached indicator
		$jobs = $this->handler->get_jobs( [ 'status' => 'processing' ] );
		$this->assertNotNull( $jobs, 'Should return non-null from cache' );
		$this->assertIsArray( $jobs, 'Should return array' );
		$this->assertNotEmpty( $jobs, 'Should return non-empty array' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that get_jobs() can bypass cache when use_cache is false
	 */
	public function test_get_jobs_can_bypass_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Set cache manually
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Call with use_cache=false should query database
		$jobs = $this->handler->get_jobs( [
			'status'    => 'processing',
			'use_cache' => false,
		] );

		// Since there are no actual jobs, should return null despite cache
		$this->assertNull( $jobs, 'Should return null when bypassing cache with no actual jobs' );

		// Clean up
		set_current_screen( null );
	}

	// ==========================================================================
	// Tests for cache invalidation
	// ==========================================================================

	/**
	 * Test that creating a job invalidates the cache
	 */
	public function test_create_job_invalidates_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Set cache
		set_transient( 'test_background_job_queue_empty', 'empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'no_jobs', 0 );

		// Create a job
		$job = $this->handler->create_job( [ 'data' => [ 'test' ] ] );
		$this->assertNotNull( $job, 'Job should be created' );

		// Cache should be invalidated
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertFalse( $queue_cache, 'Queue cache should be invalidated after job creation' );
		$this->assertFalse( $sync_cache, 'Sync cache should be invalidated after job creation' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that completing a job invalidates the cache
	 */
	public function test_complete_job_invalidates_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Create a job first
		$job = $this->handler->create_job( [ 'data' => [ 'test' ] ] );

		// Set cache
		set_transient( 'test_background_job_queue_empty', 'not_empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Complete the job
		$completed_job = $this->handler->complete_job( $job );
		$this->assertNotFalse( $completed_job, 'Job should be completed' );
		$this->assertEquals( 'completed', $completed_job->status, 'Job status should be completed' );

		// Cache should be invalidated
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertFalse( $queue_cache, 'Queue cache should be invalidated after job completion' );
		$this->assertFalse( $sync_cache, 'Sync cache should be invalidated after job completion' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that failing a job invalidates the cache
	 */
	public function test_fail_job_invalidates_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Create a job first
		$job = $this->handler->create_job( [ 'data' => [ 'test' ] ] );

		// Set cache
		set_transient( 'test_background_job_queue_empty', 'not_empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Fail the job
		$failed_job = $this->handler->fail_job( $job, 'Test failure reason' );
		$this->assertNotFalse( $failed_job, 'Job should be failed' );
		$this->assertEquals( 'failed', $failed_job->status, 'Job status should be failed' );

		// Cache should be invalidated
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertFalse( $queue_cache, 'Queue cache should be invalidated after job failure' );
		$this->assertFalse( $sync_cache, 'Sync cache should be invalidated after job failure' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that deleting a job invalidates the cache
	 */
	public function test_delete_job_invalidates_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Create a job first
		$job = $this->handler->create_job( [ 'data' => [ 'test' ] ] );

		// Set cache
		set_transient( 'test_background_job_queue_empty', 'not_empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Delete the job
		$this->handler->delete_job( $job );

		// Cache should be invalidated
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertFalse( $queue_cache, 'Queue cache should be invalidated after job deletion' );
		$this->assertFalse( $sync_cache, 'Sync cache should be invalidated after job deletion' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that update_job with invalidate_cache=true invalidates the cache
	 */
	public function test_update_job_with_invalidate_flag_invalidates_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Create a job first
		$job = $this->handler->create_job( [ 'data' => [ 'test' ] ] );

		// Set cache
		set_transient( 'test_background_job_queue_empty', 'not_empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Update job with cache invalidation
		$job->status = 'processing';
		$updated_job = $this->handler->update_job( $job, true );
		$this->assertNotFalse( $updated_job, 'Job should be updated' );

		// Cache should be invalidated
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertFalse( $queue_cache, 'Queue cache should be invalidated when update_job called with true' );
		$this->assertFalse( $sync_cache, 'Sync cache should be invalidated when update_job called with true' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that update_job without invalidate_cache flag does NOT invalidate cache
	 */
	public function test_update_job_without_invalidate_flag_keeps_cache() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Create a job first
		$job = $this->handler->create_job( [ 'data' => [ 'test' ] ] );

		// Set cache after job creation
		set_transient( 'test_background_job_queue_empty', 'not_empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'has_jobs', 0 );

		// Update job WITHOUT cache invalidation (default behavior for progress updates)
		$job->progress = 50;
		$updated_job   = $this->handler->update_job( $job ); // Default is false
		$this->assertNotFalse( $updated_job, 'Job should be updated' );

		// Cache should NOT be invalidated
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertEquals( 'not_empty', $queue_cache, 'Queue cache should NOT be invalidated for progress updates' );
		$this->assertEquals( 'has_jobs', $sync_cache, 'Sync cache should NOT be invalidated for progress updates' );

		// Clean up
		set_current_screen( null );
	}

	// ==========================================================================
	// Tests for invalidate_queue_cache() method
	// ==========================================================================

	/**
	 * Test that invalidate_queue_cache() clears both transients
	 */
	public function test_invalidate_queue_cache_clears_both_transients() {
		// Set both caches
		set_transient( 'test_background_job_queue_empty', 'empty', 0 );
		set_transient( 'test_background_job_sync_in_progress', 'no_jobs', 0 );

		// Call invalidate
		$this->invokeMethod( $this->handler, 'invalidate_queue_cache' );

		// Both should be cleared
		$queue_cache = get_transient( 'test_background_job_queue_empty' );
		$sync_cache  = get_transient( 'test_background_job_sync_in_progress' );

		$this->assertFalse( $queue_cache, 'Queue cache should be cleared' );
		$this->assertFalse( $sync_cache, 'Sync cache should be cleared' );
	}

	// ==========================================================================
	// Tests for job lifecycle with actual database operations
	// ==========================================================================

	/**
	 * Test full job lifecycle with caching
	 */
	public function test_full_job_lifecycle_with_caching() {
		// Simulate admin context
		set_current_screen( 'dashboard' );

		// Initially queue should be empty
		$is_empty = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertTrue( $is_empty, 'Queue should be empty initially' );

		// Create a job
		$job = $this->handler->create_job( [ 'data' => [ 'item1', 'item2' ] ] );
		$this->assertNotNull( $job );
		$this->assertEquals( 'queued', $job->status );

		// Queue should no longer be empty (cache was invalidated, so this queries DB)
		$is_empty = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertFalse( $is_empty, 'Queue should not be empty after creating job' );

		// Get jobs should find the queued job
		$jobs = $this->handler->get_jobs( [ 'status' => 'queued' ] );
		$this->assertNotNull( $jobs );
		$this->assertCount( 1, $jobs );

		// Complete the job
		$completed = $this->handler->complete_job( $job );
		$this->assertEquals( 'completed', $completed->status );

		// Queue should be empty again (completed jobs don't count)
		$is_empty = $this->invokeMethod( $this->handler, 'is_queue_empty' );
		$this->assertTrue( $is_empty, 'Queue should be empty after job completion' );

		// Clean up
		set_current_screen( null );
	}

	/**
	 * Test that cache keys are unique per handler identifier
	 */
	public function test_cache_keys_use_handler_identifier() {
		$queue_key = $this->get_protected_property( $this->handler, 'queue_empty_cache_key' );
		$sync_key  = $this->get_protected_property( $this->handler, 'sync_in_progress_cache_key' );

		$this->assertEquals( 'test_background_job_queue_empty', $queue_key );
		$this->assertEquals( 'test_background_job_sync_in_progress', $sync_key );
	}
}

/**
 * Testable implementation of BackgroundJobHandler for unit testing
 */
class TestableBackgroundJobHandler extends BackgroundJobHandler {

	protected $prefix = 'test';
	protected $action = 'background_job';

	/**
	 * Constructor - skip parent hooks for testing
	 */
	public function __construct() {
		$this->identifier                 = $this->prefix . '_' . $this->action;
		$this->cron_hook_identifier       = $this->identifier . '_cron';
		$this->cron_interval_identifier   = $this->identifier . '_cron_interval';
		$this->queue_empty_cache_key      = $this->identifier . '_queue_empty';
		$this->sync_in_progress_cache_key = $this->identifier . '_sync_in_progress';
		// Don't call parent constructor to avoid adding hooks during tests
	}

	/**
	 * Required abstract method implementation
	 */
	protected function process_item( $item, $job ) {
		// No-op for testing
		return $item;
	}

	/**
	 * Override is_process_request to control in tests
	 */
	protected function is_process_request() {
		return false;
	}
}


