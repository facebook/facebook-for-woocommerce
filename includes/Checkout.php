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

use WooCommerce\Facebook\Framework\Logger;

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
	 *
	 * @param string $template
	 * @return string
	 */
	public function load_checkout_permalink_template( $template ) {
		if ( get_query_var( 'fb_checkout' ) ) {
			WC()->cart->empty_cart();

			$products_param = get_query_var( 'products' );
			if ( $products_param ) {
				$products = explode( ',', $products_param );

				foreach ( $products as $product ) {
					list( $product_id, $quantity ) = explode( ':', $product );

					// Parse the product ID. The input is sent in the Retailer ID format (see get_fb_retailer_id())
					// The Retailer ID format is: {product_sku}_{product_id}, so we need to extract the product_id
					if ( false !== strpos( $product_id, '_' ) ) {
						$parts      = explode( '_', $product_id );
						$product_id = end( $parts );
					}

					$product_obj = wc_get_product( $product_id );

					if (
					$product_obj &&
					$product_obj->is_purchasable() &&
					is_numeric( $quantity ) &&
					$quantity > 0
					) {
						$added = WC()->cart->add_to_cart( $product_id, $quantity );
						if ( ! $added ) {
							$error_message = sprintf(
								'WC add_to_cart() failed: product_id=%s, quantity=%s',
								$product_id,
								$quantity
							);

							Logger::log(
								$error_message,
								array(
									'flow_name'  => 'checkout',
									'flow_step'  => 'add_to_cart',
									'extra_data' => [
										'products_param' => $products_param,
										'product_id'     => $product_id,
										'quantity'       => $quantity,
									],
								),
								array(
									'should_send_log_to_meta'        => true,
									'should_save_log_in_woocommerce' => true,
									'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
								)
							);
						}
					} else {
						$error_message = sprintf(
							'Invalid product or quantity: product_id=%s, quantity=%s',
							$product_id,
							$quantity
						);

						Logger::log(
							$error_message,
							array(
								'flow_name'  => 'checkout',
								'flow_step'  => 'product_quantity_validation',
								'extra_data' => [
									'products_param' => $products_param,
									'product_id'     => $product_id,
									'quantity'       => $quantity,
								],
							),
							array(
								'should_send_log_to_meta' => true,
								'should_save_log_in_woocommerce' => true,
								'woocommerce_log_level'   => \WC_Log_Levels::ERROR,
							)
						);
					}
				}
			}

			$coupon_code = get_query_var( 'coupon' );
			if ( $coupon_code ) {
				$coupon_code_sanitized = sanitize_text_field( $coupon_code );
				WC()->cart->apply_coupon( $coupon_code_sanitized );

				if ( ! in_array( $coupon_code_sanitized, WC()->cart->get_applied_coupons(), true ) ) {
					$error_message = sprintf(
						'Failed to apply coupon: %s',
						$coupon_code_sanitized
					);

					Logger::log(
						$error_message,
						array(
							'flow_name'  => 'checkout',
							'flow_step'  => 'apply_coupon',
							'extra_data' => [
								'coupon_param' => $coupon_code,
								'coupon_code'  => $coupon_code_sanitized,
							],
						),
						array(
							'should_send_log_to_meta' => true,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'   => \WC_Log_Levels::ERROR,
						)
					);
				}
			}

			$checkout_url = wc_get_checkout_url();
			echo '<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<title>Checkout</title>
				<style>
					body, html {
						margin: 0;
						padding: 0;
						height: 100%;
						overflow: hidden;
					}
					iframe {
						width: 100%;
						height: 100vh;
						border: none;
						display: block;
						max-width: 100%;
						max-height: 100%;
						box-sizing: border-box;
					}
				</style>
			</head>
			<body>
				<iframe src="' . esc_url( $checkout_url ) . '"></iframe>
			</body>
			</html>';

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
