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
	const VALUE_TYPE_PERCENTAGE                      = 'PERCENTAGE';
	const VALUE_TYPE_FIXED_AMOUNT                    = 'FIXED_AMOUNT';
	const TARGET_TYPE_SHIPPING                       = 'SHIPPING';
	const TARGET_TYPE_LINE_ITEM                      = 'LINE_ITEM';
	const TARGET_GRANULARITY_ORDER_LEVEL             = 'ORDER_LEVEL';
	const TARGET_GRANULARITY_ITEM_LEVEL              = 'ITEM_LEVEL';
	const TARGET_SELECTION_ENTIRE_CATALOG            = 'ALL_CATALOG_PRODUCTS';
	const TARGET_SELECTION_SPECIFIC_PRODUCTS         = 'SPECIFIC_PRODUCTS';
	const APPLICATION_TYPE_BUYER_APPLIED             = 'BUYER_APPLIED';
	const PROMO_SYNC_LOGGING_FLOW_NAME               = 'promotion_feed_sync';
	const RATINGS_AND_REVIEWS_SYNC_LOGGING_FLOW_NAME = 'ratings_and_reviews_feed_sync';
	const SHIPPING_PROFILES_SYNC_LOGGING_FLOW_NAME   = 'shipping_profiles_feed_sync';
	const NAVIGATION_MENU_SYNC_LOGGING_FLOW_NAME     = 'navigation_menu_feed_sync';


	public static function get_ratings_and_reviews_data( array $query_args ): array {
		try {
			$comments     = get_comments( $query_args );
			$reviews_data = array();

			$store_name = get_bloginfo( 'name' );
			$store_id   = facebook_for_woocommerce()->get_connection_handler()->get_commerce_merchant_settings_id();
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
						'updated_at'                      => null,
						'review_image_urls'               => null,
						'incentivized'                    => 'false',
						'has_verified_purchase'           => 'false',
						'reviewer.name'                   => $comment->comment_author,
						'reviewer.reviewerID'             => $reviewer_id,
						'reviewer.isAnonymous'            => $reviewer_is_anonymous,
						'product.name'                    => $product_name,
						'product.url'                     => $product_url,
						'product.productIdentifiers.skus' => "['" . implode( "','", $product_skus ) . "']",
					);
				} catch ( \Exception $e ) {
					\WC_Facebookcommerce_Utils::log_to_meta(
						'Exception while trying to map product review data for feed',
						array(
							'flow_name'  => self::RATINGS_AND_REVIEWS_SYNC_LOGGING_FLOW_NAME,
							'flow_step'  => 'map_ratings_and_reviews_data',
							'extra_data' => [
								'exception_message' => $e->getMessage(),
							],
						)
					);
					continue;
				}
			}

			return $reviews_data;
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$exception,
				[
					'event'      => self::RATINGS_AND_REVIEWS_SYNC_LOGGING_FLOW_NAME,
					'event_type' => 'get_ratings_and_reviews_data',
					'extra_data' => [
						'query_args' => wp_json_encode( $query_args ),
					],
				]
			);
			throw $exception;
		}
	}

	/**
	 * Query for coupons and map them to Meta format.
	 *
	 * @param array $query_args arguments for the get_posts() call
	 *
	 * @throws \Exception If an error occurs during fetching coupons.
	 */
	public static function get_coupons_data( array $query_args ): array {
		try {
			$coupon_posts = get_posts( $query_args );
			$coupons_data = array();

			// Loop through each coupon post and map the necessary fields.
			foreach ( $coupon_posts as $coupon_post ) {
				// Create a coupon object using the coupon code.
				$coupon = new WC_Coupon( $coupon_post->post_title );

				if ( ! self::is_valid_coupon( $coupon ) ) {
					continue;
				}

				try {
					// Map discount type and amount
					$woo_discount_type = $coupon->get_discount_type();
					$percent_off       = '';
					$fixed_amount_off  = '';

					if ( 'percent' === $woo_discount_type ) {
						$value_type  = self::VALUE_TYPE_PERCENTAGE;
						$percent_off = $coupon->get_amount();
					} elseif ( in_array( $woo_discount_type, array( 'fixed_cart', 'fixed_product' ), true ) ) {
						$value_type       = self::VALUE_TYPE_FIXED_AMOUNT;
						$fixed_amount_off = $coupon->get_amount(); // TODO we may want to pass in optional currency code for multinational support
					} else {
						\WC_Facebookcommerce_Utils::log_to_meta(
							'Unknown discount type encountered during feed processing',
							array(
								'promotion_id' => $coupon_post->ID,
								'extra_data'   => [ 'discount_type' => $woo_discount_type ],
								'flow_name'    => self::PROMO_SYNC_LOGGING_FLOW_NAME,
								'flow_step'    => 'map_discount_type',
							)
						);
						continue;
					}

					// Map start and end dates (if available)
					$start_date_time = $coupon->get_date_created() ? (string) $coupon->get_date_created()->getTimestamp() : $coupon_post->post_date;
					$end_date_time   = $coupon->get_date_expires() ? (string) $coupon->get_date_expires()->getTimestamp() : '';

					// Map target type. Coupons that apply both a discount and free shipping are already
					// filtered out in is_valid_coupon
					$is_free_shipping        = $coupon->get_free_shipping();
					$target_shipping_options = '';
					if ( $is_free_shipping ) {
						$target_type             = self::TARGET_TYPE_SHIPPING;
						$value_type              = self::VALUE_TYPE_PERCENTAGE;
						$percent_off             = '100'; // 100% off shipping
						$target_shipping_options = [ 'STANDARD' ]; // options are STANDARD, RUSH, EXPEDITED, TWO_DAY
					} else {
						$target_type = self::TARGET_TYPE_LINE_ITEM;
					}

					// Map target granularity
					if ( $is_free_shipping || 'fixed_cart' === $woo_discount_type ) {
						$target_granularity = self::TARGET_GRANULARITY_ORDER_LEVEL;
					} else {
						$target_granularity = self::TARGET_GRANULARITY_ITEM_LEVEL;
					}

					// Map target selection
					if ( empty( $coupon->get_product_ids() )
						&& empty( $coupon->get_product_categories() )
						&& empty( $coupon->get_excluded_product_ids() )
						&& empty( $coupon->get_excluded_product_categories() )
					) {
						// Coupon applies to all products.
						$target_selection = self::TARGET_SELECTION_ENTIRE_CATALOG;
					} else {
						$target_selection = self::TARGET_SELECTION_SPECIFIC_PRODUCTS;
					}

					// Determine target product mapping
					$target_product_set_retailer_ids = '';
					$target_product_retailer_ids     = '';
					$target_filter                   = '';

					if ( self::TARGET_SELECTION_SPECIFIC_PRODUCTS === $target_selection ) {
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
						'application_type'                => self::APPLICATION_TYPE_BUYER_APPLIED,
						'target_type'                     => $target_type,
						'target_shipping_option_types'    => $target_shipping_options,
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
						'min_subtotal'                    => $coupon->get_minimum_amount(), // TODO we may want to pass in optional currency code for multinational support
						'min_quantity'                    => '', // Concept does not exist in Woo
						'offer_terms'                     => '', // TODO link to T&C page?
						'redemption_limit_per_seller'     => $coupon->get_usage_limit(),
						'target_quantity'                 => '', // Concept does not exist in Woo
						'prerequisite_filter'             => '', // Concept does not exist in Woo
						'prerequisite_product_retailer_ids' => '', // Concept does not exist in Woo
						'prerequisite_product_group_retailer_ids' => '', // Concept does not exist in Woo
						'prerequisite_product_set_retailer_ids' => '', // Concept does not exist in Woo
						'exclude_sale_priced_products'    => $coupon->get_exclude_sale_items(),
						'usage_count'                     => $coupon->get_usage_count(),
					);

					$coupons_data[] = $data;
				} catch ( \Exception $e ) {
					\WC_Facebookcommerce_Utils::log_to_meta(
						'Exception while trying to get coupon data for feed',
						array(
							'promotion_id' => $coupon_post->ID,
							'extra_data'   => [
								'exception_message' => $e->getMessage(),
								'query_args'        => wp_json_encode( $query_args ),
							],
							'flow_name'    => self::PROMO_SYNC_LOGGING_FLOW_NAME,
							'flow_step'    => 'map_coupon_data',
						)
					);
					continue;
				}
			}

			return $coupons_data;
		} catch ( \Exception $e ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$e,
				array(
					'event'      => self::PROMO_SYNC_LOGGING_FLOW_NAME,
					'event_type' => 'get_coupon_data',
					'extra_data' => [ 'query_args' => wp_json_encode( $query_args ) ],
				)
			);
			throw $e;
		}
	}

	private static function is_valid_coupon( WC_Coupon $coupon ): bool {
		/**
		 * Fields not supported by Meta:
		 * - coupon gives both a discount and free shipping
		 * - Maximum Spend is set
		 * - Allowed Emails are set
		 * - limit_usage_to_x_items is set
		 * - missing coupon code
		 * - coupon uses brand targeting
		 */
		if ( empty( $coupon->get_code() ) ) {
			return false;
		}
		if ( $coupon->get_free_shipping() && $coupon->get_amount() > 0 ) {
			return false;
		}
		if ( $coupon->get_maximum_amount() > 0 ) {
			return false;
		}
		if ( count( $coupon->get_email_restrictions() ) > 0 ) {
			return false;
		}
		if ( ( $coupon->get_limit_usage_to_x_items() ?? 0 ) > 0 ) {
			return false;
		}

		$brands       = $coupon->get_meta( 'product_brands' );
		$brands_count = is_countable( $brands ) ? count( $brands ) : ( ! empty( $brands ) ? 1 : 0 );
		if ( $brands_count > 0 ) {
			return false;
		}

		$exclude_brands       = $coupon->get_meta( 'exclude_product_brands' );
		$exclude_brands_count = is_countable( $exclude_brands ) ? count( $exclude_brands ) : ( ! empty( $exclude_brands ) ? 1 : 0 );
		if ( $exclude_brands_count > 0 ) {
			return false;
		}

		return true;
	}

	private static function get_target_filter(
		array $included_product_ids,
		array $excluded_product_ids,
		array $included_product_category_ids,
		array $excluded_product_category_ids
	): string {
		$filter_parts = [];

		$included_products = self::get_products( $included_product_ids, $included_product_category_ids );
		$excluded_products = self::get_products( $excluded_product_ids, $excluded_product_category_ids );

		if ( ! empty( $included_products ) ) {
			// "is product x or is product y"
			$included       = self::build_retailer_id_filter( $included_products, 'eq' );
			$filter_parts[] = [ 'or' => $included ];
		}
		if ( ! empty( $excluded_products ) ) {
			// "is not product x and is not product y"
			$excluded       = self::build_retailer_id_filter( $excluded_products, 'neq' );
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

	private static function build_retailer_id_filter( array $products, string $operator ): array {
		return array_map(
			function ( $product ) use ( $operator ) {
				$fb_retailer_id = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product );
				return [ 'retailer_id' => [ $operator => $fb_retailer_id ] ];
			},
			$products
		);
	}

	private static function get_products( array $product_ids, array $product_category_ids ): array {
		$products = [];

		if ( ! empty( $product_ids ) ) {
			$products = wc_get_products(
				array(
					'include' => $product_ids,
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
		}

		// TODO when confident in category syncing, we can use target_product_set_retailer_ids instead of
		// extracting products from the categories to use in the target filter. This current logic
		// may result in the target filter field being too large for Meta to ingest.
		if ( ! empty( $product_category_ids ) ) {
			$products_from_categories = wc_get_products(
				array(
					'product_category_id' => $product_category_ids,
					'orderby'             => 'ID',
					'order'               => 'ASC',
				)
			);
			$products                 = array_unique( array_merge( $products, $products_from_categories ) );
		}

		return $products;
	}

	public static function get_navigation_menu_data(): array {
		try {
			// Fetch all product categories
			$args       = array(
				'taxonomy'   => 'product_cat',
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false, // Show all categories, even if they are empty
			);
			$categories = get_terms( $args );

			$category_tree = self::build_category_tree( $categories );
			return array(
				'navigation' => array(
					array(
						'items'               => $category_tree,
						'title'               => 'Product Categories',
						'partner_menu_handle' => 'product_categories_menu',
						'partner_menu_id'     => '1',
					),
				),
			);
		} catch ( \Exception $e ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$e,
				array(
					'event'      => self::NAVIGATION_MENU_SYNC_LOGGING_FLOW_NAME,
					'event_type' => 'get_navigation_menu_data',
				)
			);
			throw $e;
		}
	}

	private static function build_category_tree( array $categories, int $parent_id = 0, array &$memo = [] ): array {
		if ( isset( $memo[ $parent_id ] ) ) {
			return $memo[ $parent_id ];
		}

		$branch = [];

		foreach ( $categories as $category ) {
			if ( $category->parent === $parent_id ) {
				$children      = self::build_category_tree( $categories, $category->term_taxonomy_id, $memo );
				$category_data = array(
					'title'        => $category->name,
					'resourceType' => 'collection',
					'retailerID'   => $category->term_taxonomy_id,
				);
				if ( ! empty( $children ) ) {
					$category_data['items'] = $children;
				}
				$branch[] = $category_data;
			}
		}

		$memo[ $parent_id ] = $branch;
		return $branch;
	}
}
