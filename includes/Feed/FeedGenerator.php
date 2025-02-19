<?php

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Exception;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Jobs\AbstractChainedJob;

/**
 * Class FeedGenerator
 *
 * This class is meant to be inherited to generate feed files for any given feed.
 * It extends the AbstractChainedJob class to utilize the Action Scheduler framework for batch processing.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class FeedGenerator extends AbstractChainedJob {
	/**
	 * The feed handler instance for the given feed.
	 *
	 * @var FeedHandler
	 * @since 3.5.0
	 */
	protected FeedHandler $feed_handler;

	/**
	 * FeedGenerator constructor.
	 *
	 * @param ActionSchedulerInterface $action_scheduler The action scheduler instance.
	 * @param FeedHandler              $feed_handler The feed handler instance.
	 * @since 3.5.0
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, FeedHandler $feed_handler ) {
		parent::__construct( $action_scheduler );
		$this->feed_handler = $feed_handler;
	}

	/**
	 * Called before starting the job.
	 * Override for specific data stream.
	 *
	 * @since 3.5.0
	 */
	protected function handle_start() {
	}

	/**
	 * Called after the finishing the job.
	 * Override for specific data stream.
	 *
	 * @since 3.5.0
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
	 * @param int   $batch_number The batch number increments for each new batch in the job cycle.
	 * @param array $args The args for the job.
	 * @since 3.5.0
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		return array();
	}

	/**
	 * Processes a batch of items.
	 *
	 * @param array $items The items of the current batch, probably compiled as an object.
	 * @param array $args The args for the job.
	 * @since 3.5.0
	 */
	protected function process_items( array $items, array $args ) {
	}

	/**
	 * The single item processing logic. Might not need if only using the whole batch.
	 *
	 * @param object $item the singular item to process. This method might not be used but needed to extend parent.
	 * @param array  $args the args for the job.
	 *
	 * @since 3.5.0
	 */
	protected function process_item( $item, array $args ) {
	}

	/**
	 * Get the name/slug of the job.
	 * Ex. generate_product_feed
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_name(): string {
		return '';
	}

	/**
	 * Get the name/slug of the plugin that owns the job.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_plugin_name(): string {
		return WC_Facebookcommerce::PLUGIN_ID;
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 * @since 3.5.0
	 */
	protected function get_batch_size(): int {
		return - 1;
	}
}
