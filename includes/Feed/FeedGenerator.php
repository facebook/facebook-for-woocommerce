<?php
// phpcs:ignoreFile

namespace WooCommerce\Facebook\Feed;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use WC_Facebook_Product_Feed;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Jobs\AbstractChainedJob;

class FeedGenerator extends AbstractChainedJob {
	//TODO: replace with generic feed_handler class
	protected WC_Facebook_Product_Feed $feed_handler;

	public function __construct( ActionSchedulerInterface $action_scheduler, WC_Facebook_Product_Feed $feed_handler ) {
		parent::__construct( $action_scheduler );
		$this->feed_handler = $feed_handler;
	}

	/**
	 * Called before starting the job.
	 * Override for specific data stream.
	 */
	protected function handle_start() {
	}

	/**
	 * Called after the finishing the job.
	 * Override for specific data stream.
	 */
	protected function handle_end() {
	}

	/**
	 * Get a set of items for the batch.
	 *
	 * NOTE: when using an OFFSET based query to retrieve items it's recommended to order by the item ID while
	 * ASCENDING. This is so that any newly added items will not disrupt the query offset.
	 * Override with your custom SQL logic.
	 *
	 * @param int $batch_number The batch number increments for each new batch in the job cycle.
	 * @param array $args The args for the job
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		return array();
	}

	/**
	 * Processes a batch of items.
	 *
	 * @param array $items The items of the current batch.
	 * @param array $args The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 * @since 1.1.0
	 *
	 */
	protected function process_items( array $items, array $args ) {
	}

	/**
	 * The single item processing logic. Might not need if only using the whole batch.
	 */
	protected function process_item( $item, array $args ) {
	}

	/**
	 * Get the name/slug of the job.
	 * Ex. generate_product_feed
	 *
	 * @return string
	 */
	public function get_name(): string {
		return '';
	}

	/**
	 * Get the name/slug of the plugin that owns the job.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return WC_Facebookcommerce::PLUGIN_ID;
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 */
	protected function get_batch_size(): int {
		return - 1;
	}
}
