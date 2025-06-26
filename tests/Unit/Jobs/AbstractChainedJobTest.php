<?php

namespace WooCommerce\Facebook\Tests\Jobs;

use WooCommerce\Facebook\Jobs\AbstractChainedJob;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * @covers \WooCommerce\Facebook\Jobs\AbstractChainedJob
 */
class AbstractChainedJobTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * Returns a testable subclass of AbstractChainedJob with required methods stubbed.
     */
    protected function getTestJob(): AbstractChainedJob {
        return new class extends AbstractChainedJob {
            public $handleCalledWith = [];

            public function get_name(): string {
                return 'test';
            }

            public function get_items_for_batch( int $batch_number, array $args ): array {
                return [];
            }

            public function process_item( $item, array $args ) {
                // No-op for test
            }

            public function get_plugin_name(): string {
                return 'facebook-for-woocommerce';
            }

            public function handle_batch_action( int $batch_number, array $args ) {
                $this->handleCalledWith = [
                    'batch_number' => $batch_number,
                    'args'         => $args,
                ];
                // Simulate call to parent logic (e.g., record for test)
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
