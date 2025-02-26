<?php

namespace WooCommerce\Facebook\Feed;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

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
	 * @since 3.5.0
	 */
	protected function handle_end() {
		$this->feed_writer->promote_temp_file();

		/**
		 * Trigger upload from ExampleFeed instance
		 *
		 * @since 3.5.0
		 */
		do_action( ExampleFeed::modify_action_name( AbstractFeed::FEED_GEN_COMPLETE_ACTION ) );
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
		// Complete implementation would do a query based on $batch_number and get_batch_size().
		// Example below.
		/**
		 * $product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post.ID
				FROM {$wpdb->posts} as post
				LEFT JOIN {$wpdb->posts} as parent ON post.post_parent = parent.ID
				WHERE
					( post.post_type = 'product_variation' AND parent.post_status = 'publish' )
				OR
					( post.post_type = 'product' AND post.post_status = 'publish' )
				ORDER BY post.ID ASC
				LIMIT %d OFFSET %d",
				$this->get_batch_size(),
				$this->get_query_offset( $batch_number )
			)
		);
		*/

		// For proof of concept, we will just return the review id for batch 1
		// In parent classes, batch number starts with 1.
		if ( 1 === $batch_number ) {
			return array( 2, 3, 4 );
		} else {
			return array();
		}
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
		// phpcs:ignore -- Using fopen to match existing implementation.
		$temp_feed_file = fopen( $this->feed_writer->get_temp_file_path(), 'a' );
		// True override of write_feed_file would probably take an array of item ids or item objects
		// For poc, will just write to the temp feed file.
		$this->feed_writer->write_temp_feed_file();

		if ( is_resource( $temp_feed_file ) ) {
			//phpcs:ignore -- Using fclose to match existing implementation.
			fclose( $temp_feed_file );
		}
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
		// Needed to satisfy the class inheritance
		// Because of the i/o opening and closing original feed implementation foregoes this method.
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
		return 1;
	}
}
