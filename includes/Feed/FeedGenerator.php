<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

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
	 * The feed writer instance for the given feed.
	 *
	 * @var AbstractFeedFileWriter
	 * @since 3.5.0
	 */
	protected AbstractFeedFileWriter $feed_writer;

	/**
	 * The name of the data feed.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	protected string $feed_name;

	/**
	 * FeedGenerator constructor.
	 *
	 * @param ActionSchedulerInterface $action_scheduler The action scheduler instance.
	 * @param AbstractFeedFileWriter   $feed_writer The feed handler instance.
	 * @param string                   $feed_name The name of the feed.
	 * @since 3.5.0
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, AbstractFeedFileWriter $feed_writer, string $feed_name ) {
		parent::__construct( $action_scheduler );
		$this->feed_writer = $feed_writer;
		$this->feed_name   = $feed_name;
	}

	/**
	 * Handles the start of the feed generation process.
	 *
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function handle_start(): void {
		// Create directory if not available and then the files to protect the directory.
		$this->feed_writer->create_files_to_protect_feed_directory();
		$this->feed_writer->prepare_temporary_feed_file();
	}

	/**
	 * Handles the end of the feed generation process.
	 *
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function handle_end(): void {
		$this->feed_writer->promote_temp_file();

		/**
		 * Trigger upload from ExampleFeed instance
		 *
		 * @since 3.5.0
		 */
		do_action( AbstractFeed::FEED_GEN_COMPLETE_ACTION . $this->feed_name );
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
	 * @param array $items The items to process.
	 * @param array $args Additional arguments.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function process_items( array $items, array $args ): void {
		$this->feed_writer->write_temp_feed_file( $items );
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
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_name(): string {
		return $this->feed_name . '_feed_generator';
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
		return 1;
	}
}
