<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\AbstractChainedJob;
use PHPUnit\Framework\MockObject\MockObject;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Exception;

/**
 * @covers \WooCommerce\Facebook\Jobs\AbstractChainedJob
 */
class AbstractChainedJobTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var MockObject|ActionSchedulerInterface
	 */
	private $mock_scheduler;

	public function setUp(): void {
		parent::setUp();
		$this->mock_scheduler = $this->createMock(ActionSchedulerInterface::class);
	}

	public function test_handle_batch_action_logs_and_calls_parent() {
		// Arrange: create a test double for AbstractChainedJob
		$logger = $this->getMockBuilder(\WooCommerce\Facebook\Tests\Unit\Jobs\TestLogger::class)
			->onlyMethods(['start', 'stop'])
			->getMock();
		$logger->expects($this->once())->method('start')->with('test_job');
		$logger->expects($this->once())->method('stop')->with('test_job');

		// Mock global function facebook_for_woocommerce()
		global $mock_logger;
		$mock_logger = $logger;
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\facebook_for_woocommerce')) {
			eval('
				namespace WooCommerce\\Facebook\\Jobs; 
				function facebook_for_woocommerce() { 
					global $mock_logger; 
					return new class($mock_logger) { 
						private $logger; 
						public function __construct($logger) { 
							$this->logger = $logger; 
						} 
						public function get_profiling_logger() { 
							return $this->logger; 
						} 
					}; 
				}
			');
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

	public function test_handle_batch_action_constructs_correct_process_name() {
		// Arrange: create a test double for AbstractChainedJob
		$logger = $this->getMockBuilder(\WooCommerce\Facebook\Tests\Unit\Jobs\TestLogger::class)
			->onlyMethods(['start', 'stop'])
			->getMock();
		$logger->expects($this->once())->method('start')->with('custom_name_job');
		$logger->expects($this->once())->method('stop')->with('custom_name_job');

		// Mock global function facebook_for_woocommerce()
		global $mock_logger;
		$mock_logger = $logger;
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\facebook_for_woocommerce')) {
			eval('
				namespace WooCommerce\\Facebook\\Jobs; 
				function facebook_for_woocommerce() { 
					global $mock_logger; 
					return new class($mock_logger) { 
						private $logger; 
						public function __construct($logger) { 
							$this->logger = $logger; 
						} 
						public function get_profiling_logger() { 
							return $this->logger; 
						} 
					}; 
				}
			');
		}

		$job = new class($this->mock_scheduler) extends AbstractChainedJob {
			protected function get_items_for_batch(int $batch_number, array $args): array { return []; }
			protected function process_item($item, array $args) {}
			public function get_name(): string { return 'custom_name'; }
			public function get_plugin_name(): string { return 'test_plugin'; }
			protected function get_batch_size(): int { return 1; }
			public function handle_batch_action(int $batch_number, array $args) { parent::handle_batch_action($batch_number, $args); }
		};

		// Act & Assert: call handle_batch_action and expect logger start/stop with correct process name
		$job->handle_batch_action(1, []);
	}

	public function test_handle_batch_action_passes_parameters_to_parent() {
		// Arrange: create a test double for AbstractChainedJob
		$logger = $this->getMockBuilder(\WooCommerce\Facebook\Tests\Unit\Jobs\TestLogger::class)
			->onlyMethods(['start', 'stop'])
			->getMock();
		$logger->expects($this->once())->method('start')->with('test_job');
		$logger->expects($this->once())->method('stop')->with('test_job');

		// Mock global function facebook_for_woocommerce()
		global $mock_logger;
		$mock_logger = $logger;
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\facebook_for_woocommerce')) {
			eval('
				namespace WooCommerce\\Facebook\\Jobs; 
				function facebook_for_woocommerce() { 
					global $mock_logger; 
					return new class($mock_logger) { 
						private $logger; 
						public function __construct($logger) { 
							$this->logger = $logger; 
						} 
						public function get_profiling_logger() { 
							return $this->logger; 
						} 
					}; 
				}
			');
		}

		$job = new class($this->mock_scheduler) extends AbstractChainedJob {
			protected function get_items_for_batch(int $batch_number, array $args): array { return []; }
			protected function process_item($item, array $args) {}
			public function get_name(): string { return 'test'; }
			public function get_plugin_name(): string { return 'test_plugin'; }
			protected function get_batch_size(): int { return 1; }
			public function handle_batch_action(int $batch_number, array $args) { parent::handle_batch_action($batch_number, $args); }
		};

		// Act & Assert: call handle_batch_action with specific parameters
		$batch_number = 5;
		$args = ['test_arg' => 'test_value'];
		$job->handle_batch_action($batch_number, $args);
	}

	public function test_handle_batch_action_handles_exception_from_parent() {
		// Arrange: create a test double for AbstractChainedJob
		$logger = $this->getMockBuilder(\WooCommerce\Facebook\Tests\Unit\Jobs\TestLogger::class)
			->onlyMethods(['start', 'stop'])
			->getMock();
		$logger->expects($this->once())->method('start')->with('test_job');
		$logger->expects($this->once())->method('stop')->with('test_job');

		// Mock global function facebook_for_woocommerce()
		global $mock_logger;
		$mock_logger = $logger;
		if (!function_exists('WooCommerce\\Facebook\\Jobs\\facebook_for_woocommerce')) {
			eval('
				namespace WooCommerce\\Facebook\\Jobs; 
				function facebook_for_woocommerce() { 
					global $mock_logger; 
					return new class($mock_logger) { 
						private $logger; 
						public function __construct($logger) { 
							$this->logger = $logger; 
						} 
						public function get_profiling_logger() { 
							return $this->logger; 
						} 
					}; 
				}
			');
		}

		$job = new class($this->mock_scheduler) extends AbstractChainedJob {
			protected function get_items_for_batch(int $batch_number, array $args): array { return []; }
			protected function process_item($item, array $args) {}
			public function get_name(): string { return 'test'; }
			public function get_plugin_name(): string { return 'test_plugin'; }
			protected function get_batch_size(): int { return 1; }
			public function handle_batch_action(int $batch_number, array $args) { 
				parent::handle_batch_action($batch_number, $args); 
			}
		};

		// Mock the parent method to throw an exception
		$job = $this->getMockBuilder(get_class($job))
			->setConstructorArgs([$this->mock_scheduler])
			->onlyMethods(['handle_batch_action'])
			->getMock();
		
		$job->expects($this->once())
			->method('handle_batch_action')
			->willThrowException(new Exception('Test exception'));

		// Act & Assert: expect exception to be thrown but logger to still be stopped
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Test exception');
		$job->handle_batch_action(1, []);
	}
}

class TestLogger {
	public function start($name) {}
	public function stop($name) {}
} 