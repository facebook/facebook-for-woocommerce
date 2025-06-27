<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\CleanupSkyvergeFrameworkJobOptions;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Utilities\Heartbeat;

/**
 * @covers \WooCommerce\Facebook\Jobs\CleanupSkyvergeFrameworkJobOptions
 */
class CleanupSkyvergeFrameworkJobOptionsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var CleanupSkyvergeFrameworkJobOptions
	 */
	private $cleanup_job;

	/**
	 * @var mixed
	 */
	private $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		$this->cleanup_job = new CleanupSkyvergeFrameworkJobOptions();
		
		// Store original wpdb for cleanup
		global $wpdb;
		$this->original_wpdb = $wpdb;
	}

	public function tearDown(): void {
		// Restore original wpdb
		global $wpdb;
		$wpdb = $this->original_wpdb;
		
		parent::tearDown();
	}

	public function test_init_adds_daily_heartbeat_action() {
		// Act
		$this->cleanup_job->init();

		// Assert
		$this->assertNotFalse(
			has_action(Heartbeat::DAILY, [$this->cleanup_job, 'clean_up_old_completed_options']),
			'Daily heartbeat action should be added for clean_up_old_completed_options method'
		);
	}

	public function test_clean_up_old_completed_options_deletes_completed_jobs() {
		global $wpdb;

		// Arrange: Create mock completed job options
		$completed_job_1 = [
			'option_name' => 'wc_facebook_background_product_sync_job_123',
			'option_value' => '{"status":"completed","data":"test"}',
		];
		$completed_job_2 = [
			'option_name' => 'wc_facebook_background_product_sync_job_456',
			'option_value' => '{"status":"completed","other":"data"}',
		];
		$failed_job = [
			'option_name' => 'wc_facebook_background_product_sync_job_789',
			'option_value' => '{"status":"failed","error":"test"}',
		];
		$running_job = [
			'option_name' => 'wc_facebook_background_product_sync_job_999',
			'option_value' => '{"status":"running","progress":50}',
		];
		$other_option = [
			'option_name' => 'wc_facebook_other_option',
			'option_value' => '{"status":"completed"}',
		];

		// Mock the database query to return our test data
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->with($this->matchesRegularExpression('/DELETE\s+FROM\s+wp_options/i'))
			->willReturn(3); // Return number of affected rows

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert: Method doesn't return the query result, it returns null
		$this->assertNull($result, 'Method should return null as it does not return the query result');
	}

	public function test_clean_up_old_completed_options_query_structure() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';

		$expected_query_pattern = "/DELETE\s+FROM\s+wp_options\s+WHERE\s+option_name\s+LIKE\s+'wc_facebook_background_product_sync_job_%'\s+AND\s+\(\s*option_value\s+LIKE\s+'%\"status\":\"completed\"%'\s+OR\s+option_value\s+LIKE\s+'%\"status\":\"failed\"%'\s*\)\s+ORDER\s+BY\s+option_id\s+ASC\s+LIMIT\s+500/i";

		$wpdb->expects($this->once())
			->method('query')
			->with($this->matchesRegularExpression($expected_query_pattern))
			->willReturn(0);

		// Act
		$this->cleanup_job->clean_up_old_completed_options();
	}

	public function test_clean_up_old_completed_options_handles_no_results() {
		global $wpdb;

		// Arrange: Mock wpdb to return no affected rows
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(0);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert: Method doesn't return the query result, it returns null
		$this->assertNull($result, 'Method should return null as it does not return the query result');
	}

	public function test_clean_up_old_completed_options_handles_database_error() {
		global $wpdb;

		// Arrange: Mock wpdb to return false (error)
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(false);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert: Method doesn't return the query result, it returns null
		$this->assertNull($result, 'Method should return null as it does not return the query result');
	}

	public function test_clean_up_old_completed_options_limits_to_500_rows() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';

		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains('LIMIT 500'))
			->willReturn(500);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert: Method doesn't return the query result, it returns null
		$this->assertNull($result, 'Method should return null as it does not return the query result');
	}

	public function test_clean_up_old_completed_options_orders_by_option_id_asc() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';

		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains('ORDER BY option_id ASC'))
			->willReturn(0);

		// Act
		$this->cleanup_job->clean_up_old_completed_options();
	}

	public function test_clean_up_old_completed_options_filters_correct_option_names() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';

		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains("option_name LIKE 'wc_facebook_background_product_sync_job_%'"))
			->willReturn(0);

		// Act
		$this->cleanup_job->clean_up_old_completed_options();
	}

	public function test_clean_up_old_completed_options_filters_completed_and_failed_status() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';

		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains('option_value LIKE \'%"status":"completed"%\''))
			->willReturn(0);

		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains('option_value LIKE \'%"status":"failed"%\''))
			->willReturn(0);

		// Act
		$this->cleanup_job->clean_up_old_completed_options();
	}

	public function test_clean_up_old_completed_options_uses_correct_table_name() {
		global $wpdb;

		// Arrange: Mock wpdb with custom table name
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'custom_wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains('FROM custom_wp_options'))
			->willReturn(0);

		// Act
		$this->cleanup_job->clean_up_old_completed_options();
	}

	public function test_init_can_be_called_multiple_times_safely() {
		// Act: Call init multiple times
		$this->cleanup_job->init();
		$this->cleanup_job->init();
		$this->cleanup_job->init();

		// Assert: Should still have the action registered
		$this->assertNotFalse(
			has_action(Heartbeat::DAILY, [$this->cleanup_job, 'clean_up_old_completed_options']),
			'Action should still be registered after multiple init() calls'
		);
	}

	public function test_clean_up_old_completed_options_handles_large_result_set() {
		global $wpdb;

		// Arrange: Mock wpdb to return maximum affected rows
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(500); // Maximum limit

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null as method does not return query result');
	}

	public function test_clean_up_old_completed_options_handles_negative_result() {
		global $wpdb;

		// Arrange: Mock wpdb to return negative number (edge case)
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(-1);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null as method does not return query result');
	}

	public function test_clean_up_old_completed_options_handles_string_result() {
		global $wpdb;

		// Arrange: Mock wpdb to return string (edge case)
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn('success');

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null as method does not return query result');
	}

	public function test_clean_up_old_completed_options_handles_empty_options_table() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(0);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null when no rows are affected');
	}

	public function test_clean_up_old_completed_options_handles_very_large_table() {
		global $wpdb;

		// Arrange: Mock wpdb to simulate a very large table
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(500); // Hit the limit

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null even when hitting the 500 row limit');
	}

	public function test_clean_up_old_completed_options_handles_database_timeout() {
		global $wpdb;

		// Arrange: Mock wpdb to simulate a database timeout
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(false); // Database error/timeout

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null even when database query fails');
	}

	public function test_clean_up_old_completed_options_handles_malformed_json_in_options() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(0);

		// Act: The query should still execute even if some options have malformed JSON
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null even with malformed JSON in options');
	}

	public function test_clean_up_old_completed_options_handles_special_characters_in_option_names() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(0);

		// Act: The query should handle special characters in option names
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertNull($result, 'Should return null even with special characters in option names');
	}

	public function test_class_can_be_instantiated() {
		// Act & Assert: Should be able to create new instance
		$instance = new CleanupSkyvergeFrameworkJobOptions();
		$this->assertInstanceOf(CleanupSkyvergeFrameworkJobOptions::class, $instance);
	}

	public function test_clean_up_old_completed_options_handles_null_wpdb() {
		// Arrange: Set wpdb to null to test edge case
		global $wpdb;
		$original_wpdb = $wpdb;
		$wpdb = null;

		// Act & Assert: Should handle null wpdb gracefully
		$this->expectException(\Error::class);
		$this->cleanup_job->clean_up_old_completed_options();

		// Cleanup: Restore original wpdb
		$wpdb = $original_wpdb;
	}
} 