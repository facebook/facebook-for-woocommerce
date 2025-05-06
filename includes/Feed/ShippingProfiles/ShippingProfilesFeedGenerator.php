<?php

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Class ShippingProfilesFeedGenerator
 *
 * This class generates the feed as a batch job.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ShippingProfilesFeedGenerator extends FeedGenerator {
	/**
	 * Retrieves items for a specific batch.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args Additional arguments.
	 *
	 * @return array The items for the batch.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	public function get_items_for_batch( int $batch_number, array $args ): array {
		// Internal shipping data query APIs don't provide batching, so we just return everything on the first batch
		if ( 1 === $batch_number ) {
			return ShippingProfilesFeed::get_shipping_profiles_data();
		}
		return [];
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 * @since 3.5.0
	 */
	protected function get_batch_size(): int {
		return 0;
	}
}
