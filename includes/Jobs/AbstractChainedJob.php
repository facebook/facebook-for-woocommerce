<?php

namespace WooCommerce\Facebook\Jobs;

use Automattic\WooCommerce\ActionSchedulerJobFramework\AbstractChainedJob as FrameworkAbstractChainedJob;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbstractChainedJob
 *
 * @since 2.5.0
 */
abstract class AbstractChainedJob extends FrameworkAbstractChainedJob {

	/**
	 * Handle processing a chain batch.
	 *
	 * @hooked {plugin_name}/jobs/{job_name}/chain_batch
	 *
	 * @param int   $batch_number The batch number for the new batch.
	 * @param array $args         The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	public function handle_batch_action( int $batch_number, array $args ) {
		// Use the profile logger to log the usage of each job batch
		$logger       = facebook_for_woocommerce()->get_profiling_logger();
		$process_name = $this->get_name() . '_job';

		$logger->start( $process_name );

		parent::handle_batch_action( $batch_number, $args );

		$logger->stop( $process_name );
	}
}
