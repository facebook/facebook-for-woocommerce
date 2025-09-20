<?php

namespace WooCommerce\Facebook\Jobs;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Class JobManager
 *
 * @since 2.5.0
 */
class JobManager {

	/**
	 * @var GenerateProductFeed
	 */
	public $generate_product_feed_job;

	/**
	 * @var ResetAllProductsFBSettings
	 */
	public $reset_all_product_fb_settings;

	/**
	 * Instantiate and init all jobs for the plugin.
	 */
	public function init() {
		$action_scheduler_proxy = new ActionScheduler();

		$this->generate_product_feed_job = new GenerateProductFeed( $action_scheduler_proxy );
		$this->generate_product_feed_job->init();

		$this->reset_all_product_fb_settings = new ResetAllProductsFBSettings( $action_scheduler_proxy );
		$this->reset_all_product_fb_settings->init();
	}
}
