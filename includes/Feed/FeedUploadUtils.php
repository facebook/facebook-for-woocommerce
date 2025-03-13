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
 * Class containing util functions related to various feed uploads.
 *
 * @since 3.5.0
 */
class FeedUploadUtils {
	public static function get_ratings_and_reviews_data( array $query_args ): array {
		$comments     = get_comments( $query_args );
		$reviews_data = array();

		$store_name = get_bloginfo( 'name' );
		$store_id   = facebook_for_woocommerce()->get_connection_handler()->get_commerce_partner_integration_id();
		$store_urls = [ wc_get_page_permalink( 'shop' ) ];

		foreach ( $comments as $comment ) {
			try {
				$post_type = get_post_type( $comment->comment_post_ID );
				if ( 'product' !== $post_type ) {
					continue;
				}

				$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
				if ( ! is_numeric( $rating ) ) {
					continue;
				}

				$reviewer_id = $comment->user_id;
				// If reviewer_id is 0 then the reviewer is a logged-out user
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
			} catch ( \Exception $e ) {
				continue;
			}
		}

		return $reviews_data;
	}
}
