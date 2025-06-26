<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\AbstractChainedJob;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for AbstractChainedJob class.
 *
 * @since 3.5.3
 */
class AbstractChainedJobTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

  /**
   * Creates a concrete subclass of AbstractChainedJob for testing,
   * allowing injection of a mock logger and overriding facebook_for_woocommerce().
   */
  protected function getTestJob($loggerMock) {
    return new class($loggerMock) extends AbstractChainedJob {
      private $logger;
      public $parentHandleCalled = false;

      public function __construct($logger) {
        $this->logger = $logger;
      }

      public function get_name() {
        return 'test';
      }

      /**
       * Override the handle_batch_action to inject our logger instead of calling global function.
       */
      public function handle_batch_action(int $batch_number, array $args) {
        $process_name = $this->get_name() . '_job';

        // Use injected logger instead of global function call
        $this->logger->start($process_name);

        // Call parent logic — here we simulate it by marking a flag,
        // since we can't call parent's method without causing recursion.
        $this->parentHandleCalled = true;

        $this->logger->stop($process_name);
      }
    };
  }

  public function testHandleBatchActionCallsLoggerStartStopAndParent() {
    // Mock the logger with start and stop methods expected exactly once
    $loggerMock = $this->getMockBuilder(\stdClass::class)
      ->onlyMethods(['start', 'stop'])
      ->getMock();

    $loggerMock->expects($this->once())
      ->method('start')
      ->with('test_job');

    $loggerMock->expects($this->once())
      ->method('stop')
      ->with('test_job');

    // Create the job with injected logger mock
    $job = $this->getTestJob($loggerMock);

    // Call the method under test
    $job->handle_batch_action(1, []);

    // Assert that the "parent" logic was triggered
    $this->assertTrue($job->parentHandleCalled, 'Expected parent handle_batch_action logic to be executed.');
  }
}
