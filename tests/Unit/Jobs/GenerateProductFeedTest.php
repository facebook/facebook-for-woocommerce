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

	/** @var MockObject|ActionSchedulerInterface */
	private $mock_scheduler;

	private $original_wpdb;
	private $original_facebook_for_woocommerce;
	private $original_wc_get_products;
	private $original_fopen;
	private $original_fclose;

	public function setUp(): void {
		parent::setUp();
		$this->mock_scheduler = $this->createMock(ActionSchedulerInterface::class);

		// Backup global $wpdb
		global $wpdb;
		$this->original_wpdb = $wpdb;

		// Backup global functions
		if (function_exists('facebook_for_woocommerce')) {
			$this->original_facebook_for_woocommerce = \Closure::fromCallable('facebook_for_woocommerce');
		}
		if (function_exists('wc_get_products')) {
			$this->original_wc_get_products = \Closure::fromCallable('wc_get_products');
		}
		if (function_exists('fopen')) {
			$this->original_fopen = \Closure::fromCallable('fopen');
		}
		if (function_exists('fclose')) {
			$this->original_fclose = \Closure::fromCallable('fclose');
		}
	}

	public function tearDown(): void {
		// Restore global $wpdb
		global $wpdb;
		$wpdb = $this->original_wpdb;

		// Restore global functions
		if ($this->original_facebook_for_woocommerce) {
			\runkit_function_redefine('facebook_for_woocommerce', '', $this->original_facebook_for_woocommerce);
		}
		if ($this->original_wc_get_products) {
			\runkit_function_redefine('wc_get_products', '', $this->original_wc_get_products);
		}
		if ($this->original_fopen) {
			\runkit_function_redefine('fopen', '', $this->original_fopen);
		}
		if ($this->original_fclose) {
			\runkit_function_redefine('fclose', '', $this->original_fclose);
		}
		parent::tearDown();
	}

	public function test_can_be_instantiated() {
		$job = new GenerateProductFeed($this->mock_scheduler);
		$this->assertInstanceOf(GenerateProductFeed::class, $job);
	}

	public function test_get_name_returns_generate_feed() {
		$job = new GenerateProductFeed($this->mock_scheduler);
		$this->assertEquals('generate_feed', $job->get_name());
	}

	public function test_get_plugin_name_returns_plugin_id() {
		$job = new GenerateProductFeed($this->mock_scheduler);
		$this->assertNotEmpty($job->get_plugin_name());
	}

	public function test_get_batch_size_returns_15() {
		$job = new GenerateProductFeed($this->mock_scheduler);
		$this->assertEquals(15, $job->get_batch_size());
	}

	public function test_handle_start_calls_feed_handler_methods_and_tracker() {
		$feed_handler = $this->createMock(WC_Facebook_Product_Feed::class);
		$feed_handler->expects($this->once())->method('create_files_to_protect_product_feed_directory');
		$feed_handler->expects($this->once())->method('prepare_temporary_feed_file');

		$tracker = $this->getMockBuilder(\stdClass::class)
			->addMethods(['reset_batch_generation_time'])
			->getMock();
		$tracker->expects($this->once())->method('reset_batch_generation_time');

		$ffw = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_tracker'])
			->getMock();
		$ffw->expects($this->once())->method('get_tracker')->willReturn($tracker);

		// Patch global function and class
		\runkit_class_adopt('WC_Facebook_Product_Feed', get_class($feed_handler));
		\runkit_function_redefine('facebook_for_woocommerce', '', 'return $ffw;');

		$job = new GenerateProductFeed($this->mock_scheduler);
		$job->handle_start();
	}

	public function test_handle_end_calls_feed_handler_and_tracker_and_triggers_action() {
		$feed_handler = $this->createMock(WC_Facebook_Product_Feed::class);
		$feed_handler->expects($this->once())->method('rename_temporary_feed_file_to_final_feed_file');

		$tracker = $this->getMockBuilder(\stdClass::class)
			->addMethods(['save_batch_generation_time'])
			->getMock();
		$tracker->expects($this->once())->method('save_batch_generation_time');

		$ffw = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_tracker'])
			->getMock();
		$ffw->expects($this->once())->method('get_tracker')->willReturn($tracker);

		// Patch global function and class
		\runkit_class_adopt('WC_Facebook_Product_Feed', get_class($feed_handler));
		\runkit_function_redefine('facebook_for_woocommerce', '', 'return $ffw;');

		$called = false;
		add_action('wc_facebook_feed_generation_completed', function() use (&$called) { $called = true; });

		$job = new GenerateProductFeed($this->mock_scheduler);
		$job->handle_end();
		$this->assertTrue($called, 'Action wc_facebook_feed_generation_completed should be triggered');
	}

	public function test_get_items_for_batch_queries_wpdb_and_returns_int_array() {
		global $wpdb;
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_col', 'prepare'])
			->getMock();
		$wpdb->expects($this->once())->method('prepare')->willReturn('SQL');
		$wpdb->expects($this->once())->method('get_col')->willReturn(['1', '2', '3']);

		$job = $this->getMockBuilder(GenerateProductFeed::class)
			->setConstructorArgs([$this->mock_scheduler])
			->onlyMethods(['get_batch_size', 'get_query_offset'])
			->getMock();
		$job->method('get_batch_size')->willReturn(3);
		$job->method('get_query_offset')->willReturn(0);

		$reflection = new \ReflectionClass(get_class($job));
		$method = $reflection->getMethod('get_items_for_batch');
		$method->setAccessible(true);
		$result = $method->invokeArgs($job, [1, []]);
		$this->assertEquals([1, 2, 3], $result);
	}

	public function test_process_items_writes_products_to_temp_file_and_tracks_time() {
		$products = [ (object)['id' => 1], (object)['id' => 2] ];
		$feed_handler = $this->createMock(WC_Facebook_Product_Feed::class);
		$feed_handler->expects($this->once())->method('get_temp_file_path')->willReturn('/tmp/test.csv');
		$feed_handler->expects($this->once())->method('write_products_feed_to_temp_file')->with($products, $this->anything());

		$tracker = $this->getMockBuilder(\stdClass::class)
			->addMethods(['increment_batch_generation_time'])
			->getMock();
		$tracker->expects($this->once())->method('increment_batch_generation_time');

		$ffw = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_tracker'])
			->getMock();
		$ffw->expects($this->once())->method('get_tracker')->willReturn($tracker);

		// Patch global function and class
		\runkit_class_adopt('WC_Facebook_Product_Feed', get_class($feed_handler));
		\runkit_function_redefine('facebook_for_woocommerce', '', 'return $ffw;');
		\runkit_function_redefine('wc_get_products', '', 'return [ (object)["id" => 1], (object)["id" => 2] ];');
		\runkit_function_redefine('fopen', '', 'return fopen("php://memory", "a");');
		\runkit_function_redefine('fclose', '', 'return true;');

		$job = new GenerateProductFeed($this->mock_scheduler);

        $reflection = new \ReflectionClass(get_class($job));
		$method = $reflection->getMethod('process_items');
		$method->setAccessible(true);
		$method->invokeArgs($job, [[1,2], []]);
	}

	public function test_process_item_is_noop() {
		$job = new GenerateProductFeed($this->mock_scheduler);

		$reflection = new \ReflectionClass(get_class($job));
		$method = $reflection->getMethod('process_item');
		$method->setAccessible(true);
		$this->assertNull($method->invokeArgs($job, [1, []]));
	}

	public function test_log_writes_to_plugin_logger() {
		$job = $this->getMockBuilder(GenerateProductFeed::class)
			->setConstructorArgs([$this->mock_scheduler])
			->onlyMethods(['get_name'])
			->getMock();
		$job->method('get_name')->willReturn('generate_feed');

		$logger = $this->getMockBuilder(\stdClass::class)
			->addMethods(['log'])
			->getMock();
		$logger->expects($this->once())->method('log')->with(
			$this->stringContains('Test log message'),
			$this->stringContains('facebook-for-woocommerce_generate_feed')
		);

		\runkit_function_redefine('facebook_for_woocommerce', '', 'return $logger;');

		$reflection = new \ReflectionClass(get_class($job));
		$method = $reflection->getMethod('log');
		$method->setAccessible(true);
		$method->invokeArgs($job, ['Test log message']);
	}
} 