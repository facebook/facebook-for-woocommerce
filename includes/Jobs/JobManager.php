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
	 * @var CleanupSkyvergeFrameworkJobOptions
	 */
	public $cleanup_skyverge_job_options;

	/**
	 * @var ResetAllProductsFBSettings
	 */
	public $reset_all_product_fb_settings;

	/**
	 * @var DeleteProductsFromFBCatalog
	 */
	public $delete_all_products;

	/**
	 * Instantiate and init all jobs for the plugin.
	 */
	public function init() {
		$action_scheduler_proxy = new ActionScheduler();

		$this->generate_product_feed_job = new GenerateProductFeed( $action_scheduler_proxy );
		$this->generate_product_feed_job->init();

		$this->cleanup_skyverge_job_options = new CleanupSkyvergeFrameworkJobOptions();
		$this->cleanup_skyverge_job_options->init();

		$this->reset_all_product_fb_settings = new ResetAllProductsFBSettings( $action_scheduler_proxy );
		$this->reset_all_product_fb_settings->init();

		$this->delete_all_products = new DeleteProductsFromFBCatalog( $action_scheduler_proxy );
		$this->delete_all_products->init();
	}
}
