<?php

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;
use WooCommerce\Facebook\Promotions\PromotionsFeedGenerator;
use WooCommerce\Facebook\Promotions\PromotionsFeedHandler;

/**
 * Class FeedGeneratorFactory
 *
 * This class creates and inits all the FeedGeneratorClasses except Product for now
 *
 * @package WooCommerce\Facebook\Feed
 * @since 2.5.0
 */
class FeedGeneratorFactory {

	/**
	 * A map of feed generator class names to their instances.
	 *
	 * @var array $feed_generators
	 */
	private array $feed_generators = array();

	/**
	 * PromotionsFeedGeneratorFactory constructor.
	 *
	 * @param ActionScheduler $scheduler The action scheduler instance.
	 */
	public function __construct( ActionScheduler $scheduler ) {
		// Will refactor this as more feeds are added.
		$data_stream_name = FeedType::PROMOTIONS;
		$feed_writer      = new CsvFeedFileWriter( $data_stream_name );

		$feed_handler              = new PromotionsFeedHandler( $feed_writer );
		$promotions_feed_generator = new PromotionsFeedGenerator( $scheduler, $feed_handler );

		$promotions_feed_generator->init();
		$this->feed_generators[ $data_stream_name ] = $promotions_feed_generator;
	}

	/**
	 * Get the feed generator instance.
	 *
	 * @param string $feed_gen_class_name The class name of the feed generator.
	 *
	 * @return FeedGenerator
	 */
	public function get_feed_generator( string $feed_gen_class_name ): FeedGenerator {
		return $this->feed_generators[ $feed_gen_class_name ];
	}
}
