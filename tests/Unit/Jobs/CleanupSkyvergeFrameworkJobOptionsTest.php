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

	public function setUp(): void {
		parent::setUp();
		$this->cleanup_job = new CleanupSkyvergeFrameworkJobOptions();
	}

	public function test_init_adds_daily_heartbeat_action() {
		// Act
		$this->cleanup_job->init();

		// Assert
		$this->assertTrue(
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
		$wpdb = $this->createMock(\stdClass::class);
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains("DELETE FROM {$wpdb->options}"))
			->willReturn(3); // Return number of affected rows

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertEquals(3, $result, 'Should return number of deleted rows');
	}

	public function test_clean_up_old_completed_options_query_structure() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->createMock(\stdClass::class);
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
		$wpdb = $this->createMock(\stdClass::class);
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(0);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertEquals(0, $result, 'Should return 0 when no rows are deleted');
	}

	public function test_clean_up_old_completed_options_handles_database_error() {
		global $wpdb;

		// Arrange: Mock wpdb to return false (error)
		$wpdb = $this->createMock(\stdClass::class);
		$wpdb->options = 'wp_options';
		$wpdb->expects($this->once())
			->method('query')
			->willReturn(false);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertFalse($result, 'Should return false when database query fails');
	}

	public function test_clean_up_old_completed_options_limits_to_500_rows() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->createMock(\stdClass::class);
		$wpdb->options = 'wp_options';

		$wpdb->expects($this->once())
			->method('query')
			->with($this->stringContains('LIMIT 500'))
			->willReturn(500);

		// Act
		$result = $this->cleanup_job->clean_up_old_completed_options();

		// Assert
		$this->assertEquals(500, $result, 'Should limit deletion to 500 rows');
	}

	public function test_clean_up_old_completed_options_orders_by_option_id_asc() {
		global $wpdb;

		// Arrange: Mock wpdb
		$wpdb = $this->createMock(\stdClass::class);
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
		$wpdb = $this->createMock(\stdClass::class);
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
		$wpdb = $this->createMock(\stdClass::class);
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
} 