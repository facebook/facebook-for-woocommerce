<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Integration with WooCommerce Bookings.
 *
 * @since 2.0.0-dev.1
 */
class Bookings {


	/**
	 * Integration constructor.
	 *
	 * @since 2.0.0-dev.3
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_hooks' ) );
	}


	/**
	 * Adds integration hooks.
	 *
	 * @since 2.0.0-dev.3
	 */
	public function add_hooks() {

		if ( facebook_for_woocommerce()->is_plugin_active( 'woocommerce-bookings.php' ) ) {
			add_filter( 'wc_facebook_product_price', array( $this, 'get_product_price' ), 10, 3 );
		}
	}


	/**
	 * Filters the product price user for Facebook sync for Bookable products.
	 *
	 * @internal
	 *
	 * @since 2.0.0-dev.3
	 *
	 * @param int         $price product price in cents
	 * @param float       $facebook_price user defined facebook price
	 * @param \WC_Product $product product object
	 * @return int
	 */
	public function get_product_price( $price, $facebook_price, $product ) {

		if ( ! $facebook_price && $product instanceof \WC_Product && $this->is_bookable_product( $product ) ) {

			$product      = new \WC_Product_Booking( $product );
			$display_cost = is_callable( array( $product, 'get_display_cost' ) ) ? $product->get_display_cost() : 0;

			$price = (int) round( wc_get_price_to_display( $product, array( 'price' => $display_cost ) ) * 100 );
		}

		return $price;
	}


	/**
	 * Determines whether the current product is a WooCommerce Bookings product.
	 *
	 * @since 2.0.0-dev.3
	 *
	 * @param \WC_Product $product product object
	 * @return bool
	 */
	private function is_bookable_product( \WC_Product $product ) {

		return class_exists( 'WC_Product_Booking' ) && is_callable( 'is_wc_booking_product' ) && is_wc_booking_product( $product );
	}
}
