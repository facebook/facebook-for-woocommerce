<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use WooCommerce\Facebook\Jobs\AbstractChainedJob;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * @covers \WooCommerce\Facebook\Jobs\AbstractChainedJob
 */
class AbstractChainedJobTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /** @var MockObject */
    private $mockLogger;
    /** @var MockObject */
    private $mockPlugin;
    /** @var callable|null */
    private $originalFacebookForWooCommerce;

    public function setUp(): void {
        parent::setUp();

        // Mock the logger
        $this->mockLogger = $this->getMockBuilder('stdClass')
            ->addMethods(['start', 'stop'])
            ->getMock();

        // Mock the plugin singleton
        $this->mockPlugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_profiling_logger'])
            ->getMock();
        $this->mockPlugin->method('get_profiling_logger')->willReturn($this->mockLogger);

        // Save original global function if exists
        if (function_exists('facebook_for_woocommerce')) {
            $this->originalFacebookForWooCommerce = \Closure::fromCallable('facebook_for_woocommerce');
        }

        // Redefine the global function in the test namespace
        eval('namespace { function facebook_for_woocommerce() { return \WooCommerce\\Facebook\\Tests\\Unit\\Jobs\\AbstractChainedJobTest::getTestPluginInstance(); } }');
        self::$testPluginInstance = $this->mockPlugin;
    }

    public function tearDown(): void {
        // Restore the original global function if it existed
        if ($this->originalFacebookForWooCommerce) {
            eval('namespace { function facebook_for_woocommerce() { return (\WooCommerce\\Facebook\\Tests\\Unit\\Jobs\\AbstractChainedJobTest::getOriginalPluginInstance())(); } }');
        }
        parent::tearDown();
    }

    // Static property to hold the test plugin instance for the global function
    private static $testPluginInstance;
    private static $originalPluginInstance;
    public static function getTestPluginInstance() { return self::$testPluginInstance; }
    public static function getOriginalPluginInstance() { return self::$originalPluginInstance; }

    /**
     * Returns a testable subclass of AbstractChainedJob with required methods stubbed.
     */
    protected function getTestJob(): AbstractChainedJob {
        return new class extends AbstractChainedJob {
            public $parentCalled = false;
            public function get_name(): string { return 'test'; }
            public function get_items_for_batch(int $batch_number, array $args): array { return []; }
            public function process_item($item, array $args) {}
            public function get_plugin_name(): string { return 'facebook-for-woocommerce'; }
            public function handle_batch_action(int $batch_number, array $args) {
                $this->parentCalled = true;
                parent::handle_batch_action($batch_number, $args);
            }
        };
    }

    public function test_handle_batch_action_triggers_logger_and_parent_logic() {
        $job = $this->getTestJob();
        $args = ['foo' => 'bar'];
        $processName = 'test_job';

        // Expect logger start and stop to be called with the correct process name
        $this->mockLogger->expects($this->once())
            ->method('start')
            ->with($processName);
        $this->mockLogger->expects($this->once())
            ->method('stop')
            ->with($processName);

        // Call the method
        $job->handle_batch_action(3, $args);

        // Assert parent logic was called
        $this->assertTrue($job->parentCalled);
    }
}
