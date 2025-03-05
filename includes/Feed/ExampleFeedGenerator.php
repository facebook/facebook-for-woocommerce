<?php

namespace WooCommerce\Facebook\Feed;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class ExampleFeedGenerator
 *
 * This class generates the feed as a batch job.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ExampleFeedGenerator extends FeedGenerator {
	/**
	 * Retrieves items for a specific batch.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args Additional arguments.
	 * @return array The items for the batch.
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
		} else {
			return array();
		}
	}
}
