<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\AbstractChainedJob;
use PHPUnit\Framework\MockObject\MockObject;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

/**
 * @covers \WooCommerce\Facebook\Jobs\AbstractChainedJob
 */
class AbstractChainedJobTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var MockObject|ActionSchedulerInterface
	 */
	private $mock_scheduler;

	protected function setUp(): void {
		parent::setUp();
		$this->mock_scheduler = $this->createMock(ActionSchedulerInterface::class);
	}

	public function test_handle_batch_action_logs_and_calls_parent() {
		// Arrange: create a test double for AbstractChainedJob
		$logger = $this->createMock(\stdClass::class);
		$logger->expects($this->once())->method('start')->with('test_job');
		$logger->expects($this->once())->method('stop')->with('test_job');

		// Mock global function facebook_for_woocommerce()
		global $mock_logger;
		$mock_logger = $logger;
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\facebook_for_woocommerce')) {
			eval('namespace WooCommerce\\Facebook\\Jobs; function facebook_for_woocommerce() { global $mock_logger; return new class($mock_logger) { private $logger; public function __construct($logger) { $this->logger = $logger; } public function get_profiling_logger() { return $this->logger; } }; }');
		}

		$job = new class($this->mock_scheduler) extends AbstractChainedJob {
			protected function get_items_for_batch(int $batch_number, array $args): array { return []; }
			protected function process_item($item, array $args) {}
			public function get_name(): string { return 'test'; }
			public function get_plugin_name(): string { return 'test_plugin'; }
			protected function get_batch_size(): int { return 1; }
			public function handle_batch_action(int $batch_number, array $args) { parent::handle_batch_action($batch_number, $args); }
		};

		// Act & Assert: call handle_batch_action and expect logger start/stop
		$job->handle_batch_action(1, []);
	}
} 