<?php

namespace WooCommerce\Facebook\Feed;

use WooCommerce\Facebook\Utilities\Heartbeat;

/**
 * Responsible for creating and managing feeds.
 * Global manipulations of the feed such as updating feed and upload ID to be made through this class.
 */
class FeedManager {
	const EXAMPLE = 'example';

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
	 * @return AbstractFeed The created feed instance derived from AbstractFeed.
	 * @throws \InvalidArgumentException If the data stream doesn't correspond to a FeedType.
	 */
	private function create_feed( string $data_stream_name ): AbstractFeed {
		switch ( $data_stream_name ) {
			case self::EXAMPLE:
				return new ExampleFeed( $data_stream_name, Heartbeat::HOURLY );
			default:
				throw new \InvalidArgumentException( 'Invalid data stream name' );
		}
	}

	/**
	 * Get the list of feed types.
	 *
	 * @return array
	 */
	public static function get_feed_types(): array {
		return array( self::EXAMPLE );
	}

	/**
	 * Get the feed file writer for the given data stream name.
	 *
	 * @param string $data_stream_name The name of the data stream.
	 *
	 * @return FeedFileWriter
	 * @throws \InvalidArgumentException If the data stream doesn't correspond to a FeedType.
	 */
	public static function get_feed_file_writer( string $data_stream_name ): FeedFileWriter {
		switch ( $data_stream_name ) {
			case self::EXAMPLE:
				return new CsvFeedFileWriter( $data_stream_name );
			default:
				throw new \InvalidArgumentException( 'Invalid data stream name' );
		}
	}

	/**
	 * Get the feed instance for the given feed type.
	 *
	 * @param string $feed_type the specific feed in question.
	 *
	 * @return AbstractFeed
	 */
	public function get_feed_instance( string $feed_type ): AbstractFeed {
		return $this->feed_instances[ $feed_type ];
	}
}
