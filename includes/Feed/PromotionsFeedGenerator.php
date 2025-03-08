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
 * Promotions Feed Generator Class
 *
 * * Promotions Feed Generator Class. This file is responsible for the new-style feed generation for promotions
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class PromotionsFeedGenerator extends FeedGenerator {
	/**
	 * Retrieves items for a specific batch.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args Additional arguments.
	 * @return array The items for the batch. Format matches headers defined in PromotionsFeed::PROMOTIONS_FEED_HEADER
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		// Complete implementation would do a query based on $batch_number and get_batch_size().
		// Example below.
		/**
		 * $product_ids = $wpdb->get_col(
		$wpdb->prepare(
		"SELECT post.ID
		FROM {$wpdb->posts} as post
		LEFT JOIN {$wpdb->posts} as parent ON post.post_parent = parent.ID
		WHERE
		( post.post_type = 'product_variation' AND parent.post_status = 'publish' )
		OR
		( post.post_type = 'product' AND post.post_status = 'publish' )
		ORDER BY post.ID ASC
		LIMIT %d OFFSET %d",
		$this->get_batch_size(),
		$this->get_query_offset( $batch_number )
		)
		);
		 */

		// For proof of concept, we will just return the review id for batch 1
		// In parent classes, batch number starts with 1.
		if ( 1 === $batch_number ) {
			$obj_1 = [
				'retailer_id' => '99',
				'title'       => '10% Off',
			];

			return array( $obj_1 );
		} else {
			return array();
		}
	}
}
