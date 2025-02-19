<?php

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Class ExampleFeedGenerator
 *
 * This class generates the feed as a batch job.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 2.5.0
 */
class ExampleFeedGenerator extends FeedGenerator {
	/**
	 * @inheritdoc
	 */
	protected function handle_start() {
	}

	/**
	 * @inheritdoc
	 */
	protected function handle_end() {
	}

	/**
	 * @inheritdoc
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		return array();
	}

	/**
	 * @inheritdoc
	 */
	protected function process_items( array $items, array $args ) {
	}

	/**
	 * @inheritdoc
	 */
	protected function process_item( $item, array $args ) {
	}

	/**
	 * @inheritdoc
	 */
	public function get_name(): string {
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function get_plugin_name(): string {
		return WC_Facebookcommerce::PLUGIN_ID;
	}

	/**
	 * @inheritdoc
	 */
	protected function get_batch_size(): int {
		return - 1;
	}
}
