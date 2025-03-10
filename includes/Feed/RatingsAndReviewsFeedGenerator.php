<?php

namespace WooCommerce\Facebook\Feed;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

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
	 * @param int $batch_number The batch number.
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

		$comments     = get_comments( $query_args );
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
			if ( null === $rating ) {
				continue;
			}

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
				'title'                           => null,
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

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 * @since 3.5.0
	 */
	protected function get_batch_size(): int {
		return 1;
		// return 100;
	}
}
