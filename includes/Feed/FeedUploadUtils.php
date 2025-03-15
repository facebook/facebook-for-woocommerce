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

use WC_Coupon;

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
		$store_id   = get_option( 'wc_facebook_commerce_merchant_settings_id', '' );
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

	public static function get_coupons_data( array $query_args ): array {
		try {
			$coupon_posts = get_posts( $query_args );
			$coupons_data = array();

			// Loop through each coupon post and map the necessary fields.
			foreach ( $coupon_posts as $coupon_post ) {
				if ( ! self::is_valid_coupon( $coupon_post ) ) {
					continue;
				}

				try {
					// Create a coupon object using the coupon code.
					$coupon = new WC_Coupon( $coupon_post->post_title );

					// Map discount type and amount
					$woo_discount_type = $coupon->get_discount_type();
					$percent_off       = '';
					$fixed_amount_off  = '';

					if ( 'percent' === $woo_discount_type ) {
						$value_type  = 'PERCENTAGE';
						$percent_off = $coupon->get_amount();
					} elseif ( in_array( $woo_discount_type, array( 'fixed_cart', 'fixed_product' ), true ) ) {
						$value_type       = 'FIXED_AMOUNT';
						$fixed_amount_off = $coupon->get_amount(); // TODO currency?
					} else {
						\WC_Facebookcommerce_Utils::logTelemetryToMeta(
							'Unknown discount type encountered during feed processing',
							array(
								'promotion_id' => $coupon_post->ID,
								'extra_data'   => array( 'discount_type' => $woo_discount_type ),
							)
						);
						continue;
					}

					// Map start and end dates (if available)
					$start_date_time = $coupon->get_date_created() ? (string) $coupon->get_date_created()->getTimestamp() : $coupon_post->post_date;
					$end_date_time   = $coupon->get_date_expires() ? (string) $coupon->get_date_expires()->getTimestamp() : '';

					// Map target type
					$is_free_shipping = $coupon->get_free_shipping();
					if ( $is_free_shipping ) {
						$target_type = 'SHIPPING';
					} else {
						$target_type = 'LINE_ITEM';
					}

					// Map target granularity
					if ( $is_free_shipping || 'fixed_cart' === $woo_discount_type ) {
						$target_granularity = 'ORDER_LEVEL';
					} else {
						$target_granularity = 'ITEM_LEVEL';
					}

					// Map target selection
					if ( empty( $coupon->get_product_ids() )
						&& empty( $coupon->get_product_categories() )
						&& empty( $coupon->get_excluded_product_ids() )
						&& empty( $coupon->get_excluded_product_categories() )
					) {
						// Coupon applies to all products.
						$target_selection = 'ALL_CATALOG_PRODUCTS';
					} else {
						$target_selection = 'SPECIFIC_PRODUCTS';
					}

					// Determine target product mapping
					$target_product_set_retailer_ids = '';
					$target_product_retailer_ids     = '';
					$target_filter                   = '';

					if ( 'SPECIFIC_PRODUCTS' === $target_selection ) {
						$target_filter = self::get_target_filter(
							$coupon->get_product_ids(),
							$coupon->get_excluded_product_ids(),
							$coupon->get_product_categories(),
							$coupon->get_excluded_product_categories()
						);
					}

					// Build the mapped coupon data array.
					$data = array(
						'offer_id'                        => $coupon->get_id(),
						'title'                           => $coupon->get_code(),
						'value_type'                      => $value_type,
						'percent_off'                     => $percent_off,
						'fixed_amount_off'                => $fixed_amount_off,
						'application_type'                => 'BUYER_APPLIED', // coupon code ==> buyer_applied
						'target_type'                     => $target_type,
						'target_shipping_option_types'    => '', // Not needed for offsite checkout
						'target_granularity'              => $target_granularity,
						'target_selection'                => $target_selection,
						'start_date_time'                 => $start_date_time,
						'end_date_time'                   => $end_date_time,
						'coupon_codes'                    => array( $coupon->get_code() ),
						'public_coupon_code'              => '', // TODO allow public coupons
						'target_filter'                   => $target_filter,
						'target_product_retailer_ids'     => $target_product_retailer_ids,
						'target_product_group_retailer_ids' => '', // Concept does not exist in Woo
						'target_product_set_retailer_ids' => $target_product_set_retailer_ids,
						'redeem_limit_per_user'           => $coupon->get_usage_limit_per_user(),
						'min_subtotal'                    => $coupon->get_minimum_amount(), // TODO currency?
						'min_quantity'                    => '', // Concept does not exist in Woo
						'offer_terms'                     => '', // TODO link to T&C page?
						'redemption_limit_per_seller'     => $coupon->get_usage_limit(),
						'target_quantity'                 => '', // Concept does not exist in Woo
						'prerequisite_filter'             => '', // Concept does not exist in Woo
						'prerequisite_product_retailer_ids' => '', // Concept does not exist in Woo
						'prerequisite_product_group_retailer_ids' => '', // Concept does not exist in Woo
						'prerequisite_product_set_retailer_ids' => '', // Concept does not exist in Woo
						'exclude_sale_priced_products'    => $coupon->get_exclude_sale_items(),
					);

					$coupons_data[] = $data;
				} catch ( \Exception $e ) {
					\WC_Facebookcommerce_Utils::logTelemetryToMeta(
						'Exception while trying to get coupon data for feed',
						array(
							'promotion_id'      => $coupon_post->ID,
							'exception_message' => $e->getMessage(),
						)
					);
					continue;
				}
			}

			return $coupons_data;
		} catch ( \Exception $e ) {
			\WC_Facebookcommerce_Utils::logTelemetryToMeta(
				'Exception while trying to process promotion feed',
				array(
					'exception_message' => $e->getMessage(),
				)
			);
			return array();
		}
	}

	private static function is_valid_coupon( $coupon_post ): bool {
		/**
		 * Need to return false when:
		 * - coupon gives both a discount and free shipping
		 * - Maximum Spend is set
		 * - Allowed Emails are set
		 * - limit_usage_to_x_items is set
		 * - allowed and excluded brands are set?
		 */
		return true;
	}

	private static function get_target_filter(
		array $included_product_ids,
		array $excluded_product_ids,
		array $included_product_category_ids,
		array $excluded_product_category_ids
	): string {
		$filter_parts = [];

		// Build an "or" clause for included product IDs, if provided.
		if ( ! empty( $included_product_category_ids ) ) {
			$included_product_ids_from_category = self::get_product_ids_from_categories( $included_product_category_ids );
			$included_product_ids               = array_unique( array_merge( $included_product_ids, $included_product_ids_from_category ) );
		}

		if ( ! empty( $included_product_ids ) ) {
			// "is product x or is product y"
			$included       = self::build_product_id_filter( $included_product_ids, 'eq' );
			$filter_parts[] = [ 'or' => $included ];
		}

		// Build an "or" clause for excluded product IDs, if provided.
		if ( ! empty( $excluded_product_category_ids ) ) {
			$excluded_product_ids_from_category = self::get_product_ids_from_categories( $excluded_product_category_ids );
			$excluded_product_ids               = array_unique( array_merge( $excluded_product_ids, $excluded_product_ids_from_category ) );
		}

		if ( ! empty( $excluded_product_ids ) ) {
			// "not product x and not product y"
			$excluded       = self::build_product_id_filter( $excluded_product_ids, 'neq' );
			$filter_parts[] = [ 'and' => $excluded ];
		}

		// Combine the filter parts:
		// - If both parts are present, wrap them in an "and" clause.
		// - If only one part exists, use it directly.
		if ( count( $filter_parts ) > 1 ) {
			$final_filter = [ 'and' => $filter_parts ];
		} elseif ( count( $filter_parts ) === 1 ) {
			$final_filter = $filter_parts[0];
		} else {
			return '';
		}

		// Return the JSON representation. It should look something like:
		// {"and":[
		// {"or":[{"retailer_id":{"eq":"retailer_id_1"}},{"retailer_id":{"eq":"retailer_id_2"}}]},
		// {"and":[{"retailer_id":{"neq":"retailer_id_3"}},{"retailer_id":{"neq":"retailer_id_4"}}]}
		// ]}
		return wp_json_encode( $final_filter );
	}

	private static function build_product_id_filter( array $product_ids, string $operator ): array {
		return array_map(
			function ( $product_id ) use ( $operator ) {
				$product        = new \WC_Product( $product_id );
				$fb_retailer_id = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product );
				return [ 'retailer_id' => [ $operator => $fb_retailer_id ] ];
			},
			$product_ids
		);
	}

	private static function get_product_ids_from_categories( array $included_category_ids ): array {
		$all_product_ids = [];

		// Load products for each category.
		foreach ( $included_category_ids as $category_id ) {
			$args     = [
				'status'    => 'publish',
				'limit'     => -1,
				'tax_query' => [ // TODO slow
					[
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $category_id,
					],
				],
			];
			$products = wc_get_products( $args );
			foreach ( $products as $product ) {
				$all_product_ids[] = $product->get_id();
			}
		}
		// Remove duplicate IDs.
		return array_unique( $all_product_ids );
	}
}
