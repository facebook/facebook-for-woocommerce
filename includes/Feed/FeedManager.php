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

/**
 * Responsible for creating and managing feeds.
 * Global manipulations of the feed such as updating feed and upload ID to be made through this class.
 * Add feed type names as constant strings here.
 *
 * @since 3.5.0
 */
class FeedManager {
	const RATINGS_AND_REVIEWS = 'ratings_and_reviews';
	/**
	 * The list of feed types as named strings.
	 *
	 * @var array<string> The list of feed types as named strings.
	 * @since 3.5.0
	 */
	private array $feed_types;

	/**
	 * The map of feed types to their instances.
	 *
	 * @var array<string, AbstractFeed> The map of feed types to their instances.
	 * @since 3.5.0
	 */
	private array $feed_instances = array();

	/**
	 * FeedManager constructor.
	 * Instantiates all the registered feed types and keeps in map.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$this->feed_types = $this->get_feed_types();
		foreach ( $this->feed_types as $feed_type ) {
			$this->feed_instances[ $feed_type ] = $this->create_feed( $feed_type );
		}
	}

	/**
	 * Create a feed based on the data stream name.
	 *
	 * @param string $data_stream_name The name of the data stream.
	 *
	 * phpcs:ignore -- Method to be implemented when new feed types are added.
	 *
	 * @return AbstractFeed The created feed instance derived from AbstractFeed.
	 * @throws \InvalidArgumentException If the data stream doesn't correspond to a FeedType.
	 * @since 3.5.0
	 */
	private function create_feed( string $data_stream_name ): AbstractFeed {
		switch ( $data_stream_name ) {
			case self::RATINGS_AND_REVIEWS:
				return new RatingsAndReviewsFeed();
			default:
				throw new \InvalidArgumentException( "Invalid feed type {$data_stream_name}" );
		}
	}

	/**
	 * Get the list of feed types.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public static function get_feed_types(): array {
		return array( self::RATINGS_AND_REVIEWS );
	}

	/**
	 * Get the feed instance for the given feed type.
	 *
	 * @param string $feed_type the specific feed in question.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_feed_secret( string $feed_type ): string {
		$instance = $this->feed_instances[ $feed_type ];

		return $instance->get_feed_secret();
	}
}
