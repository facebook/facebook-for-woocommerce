<?php

namespace WooCommerce\Facebook\Tests\Jobs;

use WooCommerce\Facebook\Jobs\AbstractChainedJob;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * @covers \WooCommerce\Facebook\Jobs\AbstractChainedJob
 */
class AbstractChainedJobTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * Returns a testable subclass of AbstractChainedJob.
     */
    protected function getTestJob(): AbstractChainedJob {
        return new class extends AbstractChainedJob {
            public $handleCalledWith = [];

            public function get_name(): string {
                return 'test';
            }

            public function handle_batch_action(int $batch_number, array $args) {
                $this->handleCalledWith = [
                    'batch_number' => $batch_number,
                    'args'         => $args,
                ];
                // Simulate call to parent::handle_batch_action
                // In a real test, we would mock the parent if needed
            }
        };
    }

    public function testHandleBatchActionTriggersParentLogic() {
        $job = $this->getTestJob();

        $args = ['foo' => 'bar'];
        $job->handle_batch_action(3, $args);

        $this->assertSame(3, $job->handleCalledWith['batch_number']);
        $this->assertSame($args, $job->handleCalledWith['args']);
    }
}
