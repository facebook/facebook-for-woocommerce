<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

defined( 'ABSPATH' ) || exit;

/**
 * The checkout permalink.
 *
 * @since 3.3.0
 */
class Checkout {

	/**
	 * Checkout constructor.
	 *
	 * @since 3.3.0
	 */
	public function __construct() {
		$this->add_hooks();
	}

	/**
	 * Adds the necessary action and filter hooks.
	 *
	 * @since 3.3.0
	 */
	public function add_hooks() {
		add_action( 'init', array( $this, 'add_checkout_permalink_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_checkout_permalink_query_var' ) );
		add_filter( 'template_include', array( $this, 'load_checkout_permalink_template' ) );

		register_activation_hook( __FILE__, array( $this, 'flush_rewrite_rules_on_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'flush_rewrite_rules_on_deactivation' ) );
	}

	/**
	 * Adds a rewrite rule for the checkout permalink.
	 *
	 * @since 3.3.0
	 */
	public function add_checkout_permalink_rewrite_rule() {
		add_rewrite_rule( '^fb-checkout/?$', 'index.php?fb_checkout=1', 'top' );
	}

	/**
	 * Adds query vars for the checkout permalink.
	 *
	 * @since 3.3.0
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_checkout_permalink_query_var( $vars ) {
		$vars[] = 'fb_checkout';
		$vars[] = 'products';
		$vars[] = 'coupon';

		return $vars;
	}

	/**
	 * Loads the checkout permalink template.
	 *
	 * @since 3.3.0
	 */
	public function load_checkout_permalink_template() {
		if ( get_query_var( 'fb_checkout' ) ) {
			WC()->cart->empty_cart();

			$products_param = get_query_var( 'products' );
			if ( $products_param ) {
				$products = explode( ',', $products_param );

				foreach ( $products as $product ) {
					list($product_id, $quantity) = explode( ':', $product );

					if ( is_numeric( $product_id ) && is_numeric( $quantity ) && $quantity > 0 ) {
						WC()->cart->add_to_cart( $product_id, $quantity );
					}
				}
			}

			$coupon_code = get_query_var( 'coupon' );
			if ( $coupon_code ) {
				WC()->cart->apply_coupon( sanitize_text_field( $coupon_code ) );
			}

			$checkout_page_id = wc_get_page_id( 'checkout' );
			if ( $checkout_page_id > 0 ) {
				$checkout_page = get_post( $checkout_page_id );
				if ( $checkout_page ) {
					setup_postdata( $checkout_page );
					get_header();
					echo wp_kses_post( apply_filters( 'the_content', $checkout_page->post_content ) );
					get_footer();
					wp_reset_postdata();

					exit;
				}
			}
		}
	}

	/**
	 * Flushes rewrite rules when the plugin is activated.
	 *
	 * @since 3.3.0
	 */
	public function flush_rewrite_rules_on_activation() {
		$this->add_checkout_permalink_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * Flushes rewrite rules when the plugin is deactivated.
	 *
	 * @since 3.3.0
	 */
	public function flush_rewrite_rules_on_deactivation() {
		flush_rewrite_rules();
	}
}
