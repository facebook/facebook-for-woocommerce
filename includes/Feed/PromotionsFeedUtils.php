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
 * Promotions Feed Handler Class. This file is responsible for the actual coupon query and map logic.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class PromotionsFeedUtils {

	public static function get_coupons_data( array $query_args ): array {
		// TODO surround with try/catch
		$coupon_posts = get_posts( $query_args );
		$coupons_data = array();

		// Loop through each coupon post and map the necessary fields.
		foreach ( $coupon_posts as $coupon_post ) {
			if ( ! self::is_valid_coupon( $coupon_post ) ) {
				continue;
			}

			// TODO surround with try/catch
			// Create a coupon object using the coupon code.
			$coupon = new WC_Coupon( $coupon_post->post_title );

			// Map discount type and amount
			$woo_discount_type = $coupon->get_discount_type();
			$percent_off       = '';
			$fixed_amount_off  = ''; // TODO append currency?

			if ( 'percent' === $woo_discount_type ) {
				$value_type  = 'PERCENTAGE';
				$percent_off = $coupon->get_amount();
			} elseif ( in_array( $woo_discount_type, array( 'fixed_cart', 'fixed_product' ), true ) ) {
				$value_type       = 'FIXED_AMOUNT';
				$fixed_amount_off = $coupon->get_amount();
			} else {
				// TODO log -- this is likely a plugin coupon
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
			if ( empty( $coupon->get_product_ids() ) && empty( $coupon->get_product_categories() ) ) {
				// Coupon applies to all products.
				$target_selection = 'ALL_CATALOG_PRODUCTS';
			} else {
				$target_selection = 'SPECIFIC_PRODUCTS';
			}

			// Get product IDs the coupon applies to.
			$target_product_retailer_ids = array();
			foreach ( $coupon->get_product_ids() as $product_id ) {
				$product                       = new \WC_Product( $product_id );
				$fb_retailer_id                = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product );
				$target_product_retailer_ids[] = $fb_retailer_id;
			}
			// TODO needed?
			// if ( 0 === count( $target_product_retailer_ids ) ) {
			// $target_product_retailer_ids = '';
			// }

			$target_product_set_retailer_ids = $coupon->get_product_categories();

			// TODO need to worry about Individual use only flag?
			// TODO allowed and excluded brands -- can it be supported by target filter and do we sync them?

			// Build the mapped coupon data array.
			$data = array(
				'offer_id'                                => $coupon->get_id(),
				'title'                                   => $coupon->get_code(),
				'value_type'                              => $value_type,
				'percent_off'                             => $percent_off,
				'fixed_amount_off'                        => $fixed_amount_off,
				'application_type'                        => 'BUYER_APPLIED', // Woo only supports buyer applied coupons
				'target_type'                             => $target_type,
				'target_shipping_option_types'            => '', // Not needed for offsite checkout
				'target_granularity'                      => $target_granularity,
				'target_selection'                        => $target_selection,
				'start_date_time'                         => $start_date_time,
				'end_date_time'                           => $end_date_time,
				'coupon_codes'                            => array( $coupon->get_code() ),
				'public_coupon_code'                      => '', // TODO allow configuration of public coupons
				'target_filter'                           => '', // TODO build target filter if there are excluded products
				'target_product_retailer_ids'             => $target_product_retailer_ids,
				'target_product_group_retailer_ids'       => '', // Concept does not exist in Woo
				'target_product_set_retailer_ids'         => $target_product_set_retailer_ids,
				'redeem_limit_per_user'                   => $coupon->get_usage_limit_per_user(),
				'min_subtotal'                            => $coupon->get_minimum_amount(), // TODO needs currency?
				'min_quantity'                            => '', // Concept does not exist in Woo
				'offer_terms'                             => '', // TODO link to T&C page?
				'redemption_limit_per_seller'             => $coupon->get_usage_limit(),
				'target_quantity'                         => '', // Concept does not exist in Woo
				'prerequisite_filter'                     => '', // Concept does not exist in Woo
				'prerequisite_product_retailer_ids'       => array(), // Concept does not exist in Woo
				'prerequisite_product_group_retailer_ids' => array(), // Concept does not exist in Woo
				'prerequisite_product_set_retailer_ids'   => array(), // Concept does not exist in Woo
				'exclude_sale_priced_products'            => $coupon->get_exclude_sale_items(),
			);

			$coupons_data[] = $data;
		}

		return $coupons_data;
	}

	private static function is_valid_coupon( $coupon_post ): bool {
		/**
		 * Need to return false when:
		 * - coupon gives both a discount and free shipping
		 * - Maximum Spend is set
		 * - Allowed Emails are set
		 * - limit_usage_to_x_items is set
		 * - product and category exclusions are set (unless we map them to target filter)
		 */
		return true;
	}
}
