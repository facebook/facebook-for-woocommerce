<?php

namespace WooCommerce\Facebook\Feed;

use WC_Facebookcommerce;

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
	 * Handles the start of the feed generation process.
	 *
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function handle_start() {
	}

	/**
	 * Handles the end of the feed generation process.
	 *
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function handle_end() {
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
		return '';
	}

	/**
	 * Gets the plugin name associated with the feed generator.
	 *
	 * @return string The plugin name.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	public function get_plugin_name(): string {
		return WC_Facebookcommerce::PLUGIN_ID;
	}

	/**
	 * Gets the batch size for the feed generation process.
	 *
	 * @return int The batch size.
	 * @inheritdoc
	 */
	protected function get_batch_size(): int {
		return -1;
	}
}
