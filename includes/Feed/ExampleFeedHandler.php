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

defined( 'ABSPATH' ) || exit;

/**
 * Example Feed Handler class
 *
 * Extends the FeedHandler interface to handle example feed file generation.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ExampleFeedHandler implements FeedHandler {
	/**
	 * The feed writer instance for the given feed.
	 *
	 * @var FeedFileWriter
	 * @since 3.5.0
	 */
	private FeedFileWriter $feed_writer;

	/**
	 * Constructor.
	 *
	 * @param FeedFileWriter $feed_writer An instance of csv feed writer.
	 * @since 3.5.0
	 */
	public function __construct( FeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
	}

	/**
	 * Generate the feed file.
	 *
	 * This method is responsible for generating a feed file.
	 *
	 * @since 3.5.0
	 */
	public function generate_feed_file(): void {
		$this->feed_writer->write_feed_file( $this->get_feed_data() );
		/**
		 * Trigger upload from ExampleFeed instance
		 *
		 * @since 3.5.0
		 */
		do_action( AbstractFeed::FEED_GEN_COMPLETE_ACTION .FeedManager::EXAMPLE );
	}

	/**
	 * Get the feed file writer instance.
	 *
	 * @return FeedFileWriter
	 * @since 3.5.0
	 */
	public function get_feed_writer(): FeedFileWriter {
		return $this->feed_writer;
	}

	/**
	 * Get the feed data and return as array of objects.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_feed_data(): array {
		$obj_1 = [
			'aggregator'                       => 'magento',
			'store.name'                       => 'Default Store View',
			'store.id'                         => '1413745412827209',
			'store.store_urls'                 => "['http://35.91.150.25/']",
			'review_id'                        => '2',
			'rating'                           => '5',
			'title'                            => 'Great product',
			'content'                          => 'Very happy with this purchase. Would buy again.',
			'created_at'                       => '2025-01-09 18:30:43',
			'reviewer.name'                    => 'John Doe',
			'reviewer.reviewerID'              => '1',
			'product.name'                     => 'Baseball',
			'product.url'                      => 'http://35.91.150.25/catalog/product/view/id/12/s/baseball/',
			'product.image_urls'               => "['/b/a/baseball.jpg']",
			'product.product_identifiers.skus' => "['Baseball']",
			'country'                          => 'US',
		];

		$obj_2 = [
			'aggregator'                       => 'magento',
			'store.name'                       => 'Default Store View',
			'store.id'                         => '1413745412827209',
			'store.store_urls'                 => "['http://35.91.150.25/']",
			'review_id'                        => '3',
			'rating'                           => '1',
			'title'                            => "Don't recommend",
			'content'                          => 'Unusable after just a couple games. Expected better. Would not recommend.',
			'created_at'                       => '2025-01-09 19:56:37',
			'reviewer.name'                    => 'Tim Cook',
			'reviewer.reviewerID'              => '2',
			'product.name'                     => 'Baseball',
			'product.url'                      => 'http://35.91.150.25/catalog/product/view/id/12/s/baseball/',
			'product.image_urls'               => "['/b/a/baseball.jpg']",
			'product.product_identifiers.skus' => "['Baseball']",
			'country'                          => 'US',
		];

		$obj_3 = [
			'aggregator'                       => 'magento',
			'store.name'                       => 'Default Store View',
			'store.id'                         => '1413745412827209',
			'store.store_urls'                 => "['http://35.91.150.25/']",
			'review_id'                        => '4',
			'rating'                           => '4',
			'title'                            => 'Overall satisfied',
			'content'                          => 'Could have been better but overall satisfied with my purchase.',
			'created_at'                       => '2025-01-15 23:04:29',
			'reviewer.name'                    => 'Tom Manning',
			'reviewer.reviewerID'              => '3',
			'product.name'                     => 'Baseball',
			'product.url'                      => 'http://35.91.150.25/catalog/product/view/id/12/s/baseball/',
			'product.image_urls'               => "['/b/a/baseball.jpg']",
			'product.product_identifiers.skus' => "['Baseball']",
			'country'                          => 'US',
		];

		return array( $obj_1, $obj_2, $obj_3 );
	}
}
