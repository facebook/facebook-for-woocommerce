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
 * Class RatingsAndReviewsFeedGenerator
 *
 * This class generates the feed as a batch job.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class RatingsAndReviewsFeedGenerator extends FeedGenerator {
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
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		$batch_number = max( 1, $batch_number );
		$batch_size   = $this->get_batch_size();
		$offset       = ( $batch_number - 1 ) * $batch_size;

		$query_args = array(
			'number' => $batch_size,
			'offset' => $offset,
			'status' => 'approve',
		);

		return FeedUploadUtils::get_ratings_and_reviews_data( $query_args );
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 * @since 3.5.0
	 */
	protected function get_batch_size(): int {
		return 100;
	}
}
