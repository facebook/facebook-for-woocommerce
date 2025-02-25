<?php

namespace WooCommerce\Facebook\Feed;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Utilities\BatchQueryOffset;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Jobs\LoggingTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Class ExampleFeedGenerator
 *
 * This class generates the feed as a batch job.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ExampleFeedGenerator extends FeedGenerator {
	/**
	 * Used to interact with the directory system.
	 *
	 * @var FeedFileWriter $feed_writer
	 */
	private FeedFileWriter $feed_writer;

	/**
	 * Constructor for this instance.
	 *
	 * @param ActionSchedulerInterface $action_scheduler Global scheduler.
	 * @param FeedHandler              $feed_handler The feed handler instance for this feed.
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, FeedHandler $feed_handler ) {
		parent::__construct( $action_scheduler, $feed_handler );
		$this->feed_writer = $feed_handler->get_feed_writer();
	}

	/**
	 * Handles the start of the feed generation process.
	 *
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function handle_start() {
		$this->feed_writer->create_files_to_protect_feed_directory();
		$this->feed_writer->prepare_temporary_feed_file();
	}

	/**
	 * Handles the end of the feed generation process.
	 *
	 * @inheritdoc
	 * @throw PluginException If the temporary file cannot be promoted.
	 * @since 3.5.0
	 */
	protected function handle_end() {
		$this->feed_writer->promote_temp_file();

		/**
		 * Trigger upload from ExampleFeed instance
		 *
		 * @since 3.5.0
		 */
		do_action( ExampleFeed::modify_action_name( ExampleFeed::FEED_GEN_COMPLETE_ACTION ) );
	}

	/**
	 * Retrieves items for a specific batch.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args Additional arguments.
	 * @return array The items for the batch.
	 * @inheritdoc
	 * @since 3.5.0
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
	protected function process_items( array $items, array $args ) {
	}

	/**
	 * Processes a single item.
	 *
	 * @param mixed $item The item to process.
	 * @param array $args Additional arguments.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function process_item( $item, array $args ) {
	}

	/**
	 * Gets the name of the feed generator.
	 *
	 * @return string The name of the feed generator.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	public function get_name(): string {
		return self::class;
	}

	/**
	 * Gets the batch size for the feed generation process.
	 *
	 * @return int The batch size.
	 * @inheritdoc
	 */
	protected function get_batch_size(): int {
		return 15;
	}
}
