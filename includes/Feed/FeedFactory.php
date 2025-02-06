<?php

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Promotions\PromotionsFeed;

/**
 * Factory class for creating feed instances.
 *
 * @package FacebookCommerce
 */
class FeedFactory {

	/**
	 * Create a feed based on the data stream name.
	 *
	 * @param string $data_stream_name The name of the data stream.
	 *
	 * @return AbstractFeed The created feed instance derived from AbstractFeed.
	 * @throws \InvalidArgumentException If the data stream doesn't correspond to a FeedType.
	 */
	public function create_feed( string $data_stream_name ): AbstractFeed {
		switch ( $data_stream_name ) {
			case FeedType::PROMOTIONS:
				return new PromotionsFeed();
			default:
				throw new \InvalidArgumentException( 'Invalid data stream name' );
		}
	}
}
