<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\GenerateProductFeed;
use PHPUnit\Framework\MockObject\MockObject;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use WC_Facebook_Product_Feed;
use Exception;

/**
 * @covers \WooCommerce\Facebook\Jobs\GenerateProductFeed
 */
class GenerateProductFeedTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Mocked ActionSchedulerInterface.
	 *
	 * @var MockObject|ActionSchedulerInterface
	 */
	private $mock_scheduler;

	/**
	 * Original wpdb global for restoration.
	 *
	 * @var mixed
	 */
	private $original_wpdb;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->mock_scheduler = $this->createMock(ActionSchedulerInterface::class);
		global $wpdb;
		$this->original_wpdb = $wpdb;

		// Always define the facebook_for_woocommerce mock in the correct namespace for all tests
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\facebook_for_woocommerce')) {
			eval('namespace WooCommerce\\Facebook\\Jobs; function facebook_for_woocommerce() { global $mock_ffw; return $mock_ffw; }');
		}
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		parent::tearDown();
	}

	public function test_can_be_instantiated() {
		// Act
		$job = new GenerateProductFeed($this->mock_scheduler);

		// Assert
		$this->assertInstanceOf(GenerateProductFeed::class, $job);
	}

	public function test_get_name_returns_generate_feed() {
		// Act
		$job = new GenerateProductFeed($this->mock_scheduler);

		// Assert
		$this->assertEquals('generate_feed', $job->get_name());
	}

	public function test_get_plugin_name_returns_plugin_id() {
		// Act
		$job = new GenerateProductFeed($this->mock_scheduler);

		// Assert
		$this->assertNotEmpty($job->get_plugin_name());
	}

	public function test_get_batch_size_returns_15() {
		// Arrange
		$job = new GenerateProductFeed($this->mock_scheduler);
		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('get_batch_size');
		$method->setAccessible(true);

		// Act
		$result = $method->invoke($job);

		// Assert
		$this->assertEquals(15, $result);
	}

	public function test_get_items_for_batch_queries_wpdb_and_returns_int_array() {
		// Arrange
		global $wpdb;
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_col', 'prepare'])
			->getMock();
		$wpdb->posts = 'wp_posts'; // Needed for the SQL
		$wpdb->expects($this->once())->method('prepare')->willReturn('SQL');
		$wpdb->expects($this->once())->method('get_col')->willReturn(['1', '2', '3']);

		$job = $this->getMockBuilder(GenerateProductFeed::class)
			->setConstructorArgs([$this->mock_scheduler])
			->onlyMethods(['get_batch_size', 'get_query_offset'])
			->getMock();
		$job->method('get_batch_size')->willReturn(3);
		$job->method('get_query_offset')->willReturn(0);

		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('get_items_for_batch');
		$method->setAccessible(true);

		// Act
		$result = $method->invokeArgs($job, [1, []]);

		// Assert
		$this->assertEquals([1, 2, 3], $result);
	}

	public function test_process_item_is_noop() {
		// Arrange
		$job = new GenerateProductFeed($this->mock_scheduler);
		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('process_item');
		$method->setAccessible(true);

		// Act
		$result = $method->invokeArgs($job, [1, []]);

		// Assert
		$this->assertNull($result);
	}

	public function test_handle_start_calls_feed_handler_methods_and_tracker() {
		// Arrange: mock feed handler and tracker
		$feed_handler = $this->createMock(WC_Facebook_Product_Feed::class);
		$feed_handler->expects($this->once())->method('create_files_to_protect_product_feed_directory');
		$feed_handler->expects($this->once())->method('prepare_temporary_feed_file');

		$tracker = $this->getMockBuilder(\stdClass::class)
			->addMethods(['reset_batch_generation_time'])
			->getMock();
		$tracker->expects($this->once())->method('reset_batch_generation_time');

		global $mock_ffw;
		$mock_ffw = new class($tracker) {
			private $tracker;
			public function __construct($tracker) { $this->tracker = $tracker; }
			public function get_tracker() { return $this->tracker; }
			public function log($message, $log_id = null, $level = null) { /* no-op for this test */ }
		};

		// Act
		$job = new GenerateProductFeed($this->mock_scheduler);
		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('handle_start');
		$method->setAccessible(true);
		$method->invoke($job);
	}

	public function test_handle_end_calls_feed_handler_and_tracker_and_triggers_action() {
		// Arrange: mock feed handler and tracker
		$feed_handler = $this->createMock(WC_Facebook_Product_Feed::class);
		$feed_handler->expects($this->once())->method('rename_temporary_feed_file_to_final_feed_file');

		$tracker = $this->getMockBuilder(\stdClass::class)
			->addMethods(['save_batch_generation_time'])
			->getMock();
		$tracker->expects($this->once())->method('save_batch_generation_time');

		global $mock_ffw;
		$mock_ffw = new class($tracker) {
			private $tracker;
			public function __construct($tracker) { $this->tracker = $tracker; }
			public function get_tracker() { return $this->tracker; }
			public function log($message, $log_id = null, $level = null) { /* no-op for this test */ }
		};

		$called = false;
		add_action('wc_facebook_feed_generation_completed', function() use (&$called) { $called = true; });

		// Act
		$job = new GenerateProductFeed($this->mock_scheduler);
		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('handle_end');
		$method->setAccessible(true);
		$method->invoke($job);

		// Assert
		$this->assertTrue($called, 'Action wc_facebook_feed_generation_completed should be triggered');
	}

	public function test_process_items_writes_products_to_temp_file_and_tracks_time() {
		// Arrange: mock feed handler, tracker, and wc_get_products
		$products = [ (object)['id' => 1], (object)['id' => 2] ];
		$feed_handler = $this->createMock(WC_Facebook_Product_Feed::class);
		$feed_handler->expects($this->once())->method('get_temp_file_path')->willReturn('php://memory');
		$feed_handler->expects($this->once())->method('write_products_feed_to_temp_file')->with($products, $this->anything());

		$tracker = $this->getMockBuilder(\stdClass::class)
			->addMethods(['increment_batch_generation_time'])
			->getMock();
		$tracker->expects($this->once())->method('increment_batch_generation_time');

		global $mock_ffw;
		$mock_ffw = new class($tracker) {
			private $tracker;
			public function __construct($tracker) { $this->tracker = $tracker; }
			public function get_tracker() { return $this->tracker; }
			public function log($message, $log_id = null, $level = null) { /* no-op for this test */ }
		};
		global $mock_wc_get_products;
		$mock_wc_get_products = function($args) use ($products) { return $products; };
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\wc_get_products')) {
			eval('namespace WooCommerce\\Facebook\\Jobs; function wc_get_products($args) { global $mock_wc_get_products; return $mock_wc_get_products ? $mock_wc_get_products($args) : []; }');
		}

		// Act
		$job = new GenerateProductFeed($this->mock_scheduler);
		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('process_items');
		$method->setAccessible(true);
		$method->invokeArgs($job, [[1,2], []]);
	}

	public function test_log_writes_to_plugin_logger() {
		// Arrange: mock logger
		$job = $this->getMockBuilder(GenerateProductFeed::class)
			->setConstructorArgs([$this->mock_scheduler])
			->onlyMethods(['get_name'])
			->getMock();
		$job->method('get_name')->willReturn('generate_feed');

		global $mock_ffw;
		$test = $this;
		$mock_ffw = new class($test) {
			private $test;
			public function __construct($test) { $this->test = $test; }
			public function get_tracker() { return null; }
			public function log($message, $log_id = null, $level = null) {
				// Assert inside the logger
				$this->test->assertStringContainsString('Test log message', $message);
				$this->test->assertStringContainsString('facebook-for-woocommerce_generate_feed', $log_id);
			}
		};

		// Act
		$reflection = new \ReflectionClass($job);
		$method = $reflection->getMethod('log');
		$method->setAccessible(true);
		$method->invokeArgs($job, ['Test log message', 'facebook-for-woocommerce_generate_feed']);
	}
} 