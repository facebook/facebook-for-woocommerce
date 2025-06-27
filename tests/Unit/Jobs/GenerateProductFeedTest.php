<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\GenerateProductFeed;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Facebookcommerce;
use Exception;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

if (!function_exists('facebook_for_woocommerce')) {
	function facebook_for_woocommerce() {
		return $GLOBALS['mock_plugin'];
	}
}

// Proxy class to expose protected methods for testing
class GenerateProductFeedTestProxy extends GenerateProductFeed {
	public function public_get_items_for_batch(int $batch_number, array $args): array {
		return $this->get_items_for_batch($batch_number, $args);
	}
	public function public_get_batch_size(): int {
		return $this->get_batch_size();
	}
	public function public_handle_start() {
		return $this->handle_start();
	}
	public function public_handle_end() {
		return $this->handle_end();
	}
	public function public_process_items(array $items, array $args) {
		return $this->process_items($items, $args);
	}
}

/**
 * @covers \WooCommerce\Facebook\Jobs\GenerateProductFeed
 */
class GenerateProductFeedTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var GenerateProductFeedTestProxy */
	private $job;

	/** @var MockObject|ActionSchedulerInterface */
	private $mock_scheduler;

	/** @var mixed */
	private $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_scheduler = $this->createMock(ActionSchedulerInterface::class);
		$this->job = $this->getMockBuilder(GenerateProductFeedTestProxy::class)
			->setConstructorArgs([$this->mock_scheduler])
			->onlyMethods(['get_batch_size', 'get_query_offset'])
			->getMock();
		$this->job->method('get_batch_size')->willReturn(2);
		$this->job->method('get_query_offset')->willReturn(0);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		parent::tearDown();
	}

	private function mock_facebook_for_woocommerce_tracker() {
		$tracker = $this->getMockBuilder('stdClass')
			->addMethods(['reset_batch_generation_time', 'save_batch_generation_time', 'increment_batch_generation_time'])
			->getMock();
		$tracker->expects($this->any())->method('reset_batch_generation_time');
		$tracker->expects($this->any())->method('save_batch_generation_time');
		$tracker->expects($this->any())->method('increment_batch_generation_time');
		return $tracker;
	}

	private function mock_facebook_for_woocommerce() {
		$tracker = $this->mock_facebook_for_woocommerce_tracker();
		$mock = $this->getMockBuilder('stdClass')
			->addMethods(['get_tracker', 'log'])
			->getMock();
		$mock->method('get_tracker')->willReturn($tracker);
		$mock->method('log');
		return $mock;
	}

	private function mock_feed_handler() {
		$mock = $this->getMockBuilder('WC_Facebook_Product_Feed')
			->disableOriginalConstructor()
			->onlyMethods([
				'create_files_to_protect_product_feed_directory',
				'prepare_temporary_feed_file',
				'rename_temporary_feed_file_to_final_feed_file',
				'get_temp_file_path',
				'write_products_feed_to_temp_file',
			])
			->getMock();
		$mock->method('get_temp_file_path')->willReturn('php://memory');
		return $mock;
	}

	public function test_handle_start_calls_feed_handler_and_tracker() {
		$feed_handler = $this->mock_feed_handler();
		$feed_handler->expects($this->once())->method('create_files_to_protect_product_feed_directory');
		$feed_handler->expects($this->once())->method('prepare_temporary_feed_file');

		$tracker = $this->mock_facebook_for_woocommerce_tracker();
		$tracker->expects($this->once())->method('reset_batch_generation_time');

		$plugin = $this->getMockBuilder('stdClass')
			->addMethods(['get_tracker'])
			->getMock();
		$plugin->method('get_tracker')->willReturn($tracker);

		// Patch global function and class
		$GLOBALS['mock_feed_handler'] = $feed_handler;
		$GLOBALS['mock_plugin'] = $plugin;

		$this->job->public_handle_start();
	}

	public function test_handle_end_calls_feed_handler_and_tracker_and_triggers_action() {
		$feed_handler = $this->mock_feed_handler();
		$feed_handler->expects($this->once())->method('rename_temporary_feed_file_to_final_feed_file');

		$tracker = $this->mock_facebook_for_woocommerce_tracker();
		$tracker->expects($this->once())->method('save_batch_generation_time');

		$plugin = $this->getMockBuilder('stdClass')
			->addMethods(['get_tracker'])
			->getMock();
		$plugin->method('get_tracker')->willReturn($tracker);

		// Patch global function and class
		$GLOBALS['mock_feed_handler'] = $feed_handler;
		$GLOBALS['mock_plugin'] = $plugin;

		$called = false;
		add_action('wc_facebook_feed_generation_completed', function() use (&$called) { $called = true; });
		$this->job->public_handle_end();
		$this->assertTrue($called, 'Action wc_facebook_feed_generation_completed should be triggered.');
	}

	public function test_get_items_for_batch_returns_ids() {
		global $wpdb;
		$wpdb = $this->getMockBuilder('stdClass')
			->addMethods(['get_col', 'prepare'])
			->getMock();
		$wpdb->posts = 'wp_posts';
		$wpdb->expects($this->once())->method('prepare')->willReturn('SQL');
		$wpdb->expects($this->once())->method('get_col')->with('SQL')->willReturn(['1', '2']);

		// Patch global function and class
		$GLOBALS['mock_plugin'] = $this->mock_facebook_for_woocommerce();

		$result = $this->job->public_get_items_for_batch(1, []);
		$this->assertEquals([1, 2], $result);
	}

	public function test_process_items_writes_products_feed_and_increments_time() {
		$feed_handler = $this->mock_feed_handler();
		$feed_handler->expects($this->once())->method('write_products_feed_to_temp_file');

		$plugin = $this->mock_facebook_for_woocommerce();
		$plugin->get_tracker()->expects($this->once())->method('increment_batch_generation_time');

		// Patch global function and class
		$GLOBALS['mock_feed_handler'] = $feed_handler;
		$GLOBALS['mock_plugin'] = $plugin;

		if (!function_exists('wc_get_products')) {
			function wc_get_products($args) { return ['product1', 'product2']; }
		}

		$this->job->public_process_items([1, 2], []);
	}

	public function test_get_name_and_plugin_name_and_batch_size() {
		$this->assertEquals('generate_feed', $this->job->get_name());
		$this->assertEquals(WC_Facebookcommerce::PLUGIN_ID, $this->job->get_plugin_name());
		$this->assertEquals(2, $this->job->public_get_batch_size());
	}
}
