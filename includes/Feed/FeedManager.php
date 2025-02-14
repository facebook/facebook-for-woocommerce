<?php

namespace WooCommerce\Facebook\Feed;

/**
 * Responsible for creating and managing feeds.
 * Global manipulations of the feed such as updating feed and upload ID to be made through this class.
 */
class FeedManager {
	/**
	 * Used to create the feed instances
	 *
	 * @var FeedFactory
	 */
	private FeedFactory $feed_factory;

	/**
	 * The list of feed types as named strings.
	 *
	 * @var array<string> The list of feed types as named strings.
	 */
	private array $feed_types;

	/**
	 * The map of feed types to their instances.
	 *
	 * @var array<string, AbstractFeed> The map of feed types to their instances.
	 */
	private array $feed_instances = array();

	/**
	 * FeedManager constructor.
	 * Instantiates all the registered feed types and keeps in map.
	 */
	public function __construct() {
		$this->feed_factory = new FeedFactory();
		$this->feed_types   = FeedType::get_feed_types();
		foreach ( $this->feed_types as $feed_type ) {
			$this->feed_instances[ $feed_type ] = $this->feed_factory->create_feed( $feed_type );
		}
	}
}
