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
 * Ratings and Reviews Feed Handler class
 *
 * Extends the FeedHandler interface to handle ratings and reviews feed file generation.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class RatingsAndReviewsFeedHandler implements FeedHandler {
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
	 *
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
		 * Trigger upload from RatingsAndReviewsFeed instance
		 *
		 * @since 3.5.0
		 */
		do_action( AbstractFeed::FEED_GEN_COMPLETE_ACTION . FeedManager::RATINGS_AND_REVIEWS );
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
		$args = array(
			'status' => 'approve',
		);

		$comments     = get_comments( $args );
		$reviews_data = array();

		$store_name = get_bloginfo( 'name' );
		$store_id   = get_option( 'wc_facebook_commerce_merchant_settings_id', '' );
		$store_urls = [ wc_get_page_permalink( 'shop' ) ];

		foreach ( $comments as $comment ) {
			$post_type = get_post_type( $comment->comment_post_ID );
			if ( 'product' !== $post_type ) {
				continue;
			}

			$rating = get_comment_meta( $comment->comment_ID, 'rating', true );

			$reviewer_id           = $comment->user_id;
			$reviewer_is_anonymous = '0' === $reviewer_id ? 'true' : 'false';

			$product = wc_get_product( $comment->comment_post_ID );
			if ( null === $product ) {
				continue;
			}
			$product_name = $product->get_name();
			$product_url  = $product->get_permalink();
			$product_skus = [ $product->get_sku() ];

			$reviews_data[] = array(
				'aggregator'                      => 'woocommerce',
				'store.name'                      => $store_name,
				'store.id'                        => $store_id,
				'store.storeUrls'                 => "['" . implode( "','", $store_urls ) . "']",
				'review_id'                       => $comment->comment_ID,
				'rating'                          => intval( $rating ),
				'title'                           => 'Review',
				'content'                         => $comment->comment_content,
				'created_at'                      => $comment->comment_date,
				'reviewer.name'                   => $comment->comment_author,
				'reviewer.reviewerID'             => $reviewer_id,
				'reviewer.isAnonymous'            => $reviewer_is_anonymous,
				'product.name'                    => $product_name,
				'product.url'                     => $product_url,
				'product.productIdentifiers.skus' => "['" . implode( "','", $product_skus ) . "']",
			);
		}

		return $reviews_data;
	}
}
