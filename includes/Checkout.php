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
		// add the necessary action and filter hooks
		$this->add_hooks();
	}

	/**
	 * Adds the necessary action and filter hooks.
	 *
	 * @since 3.3.0
	 */
	public function add_hooks() {
		// add the rewrite rule for the checkout permalink
		add_action( 'init', array( $this, 'add_checkout_permalink_rewrite_rule' ) );

		// add the query var for the checkout permalink
		add_filter( 'query_vars', array( $this, 'add_checkout_permalink_query_var' ) );

		// load the checkout permalink template
		add_filter( 'template_include', array( $this, 'load_checkout_permalink_template' ) );

		// flush rewrite rules when plugin is activated
		register_activation_hook( __FILE__, array( $this, 'flush_rewrite_rules_on_activation' ) );

		// flush rewrite rules when plugin is deactivated
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
		// Add 'fb_checkout' as a query var
		$vars[] = 'fb_checkout';

		// Add 'products' as a query var
		$vars[] = 'products';

		// Add 'coupon' as a query var
		$vars[] = 'coupon';

		return $vars;
	}

	/**
	 * Loads the checkout permalink template.
	 *
	 * @since 3.3.0
	 *
	 * @param string $template
	 * @return string
	 */
	public function load_checkout_permalink_template( $template ) {
		if ( get_query_var( 'fb_checkout' ) ) {
			// Clear the WooCommerce cart
			WC()->cart->empty_cart();
			$products_param = get_query_var( 'products' );

			if ( $products_param ) {
				// Split multiple products by comma
				$products = explode( ',', $products_param );

				foreach ( $products as $product ) {
					// Parse each product ID and quantity
					list($product_id, $quantity) = explode( ':', $product );

					// Parse the product ID. The input is sent in the Retailer ID format (see get_fb_retailer_id())
					// The Retailer ID format is: {product_sku}_{product_id}, so we need to extract the product_id
					if ( false !== strpos( $product_id, '_' ) ) {
						$parts      = explode( '_', $product_id );
						$product_id = end( $parts );
					}

					// Validate and add the product to the cart
					if ( is_numeric( $product_id ) && is_numeric( $quantity ) && $quantity > 0 ) {
						try {
							WC()->cart->add_to_cart( $product_id, $quantity );
						} catch ( \Exception $e ) {
							\WC_Facebookcommerce_Utils::logExceptionImmediatelyToMeta(
								$e,
								array(
									'flow_name'       => 'checkout',
									'incoming_params' => array(
										'products_param' => $products_param,
										'product_id'     => $product_id,
									),
								)
							);
						}
					} else {
						\WC_Facebookcommerce_Utils::logTelemetryToMeta(
							'Failed to add product to cart',
							array(
								'flow_name'       => 'checkout',
								'incoming_params' => array(
									'products_param' => $products_param,
									'product_id'     => $product_id,
								),
							)
						);
					}
				}
			}

			// Get the 'coupon' query parameter
			$coupon_code = get_query_var( 'coupon' );

			if ( $coupon_code ) {
				// Apply the coupon to the cart
				WC()->cart->apply_coupon( sanitize_text_field( $coupon_code ) );
			}

			// Use a custom template file
			include plugin_dir_path( __FILE__ ) . 'Templates/CheckoutTemplate.php';

			exit;
		}

		return $template;
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
