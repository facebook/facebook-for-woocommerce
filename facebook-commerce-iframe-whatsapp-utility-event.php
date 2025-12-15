<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\Handlers\WhatsAppExtension;
use WooCommerce\Facebook\RolloutSwitches;

/**
 * Event Processor for sending WhatsApp Utility Message when Order Management events are triggered
 */
class WC_Facebookcommerce_Iframe_Whatsapp_Utility_Event {

	/** @var array Mapping of Order Status to Event name */
	const ORDER_STATUS_TO_EVENT_MAPPING = array(
		'processing' => 'ORDER_PLACED',
		'completed'  => 'ORDER_FULFILLED',
		'refunded'   => 'ORDER_REFUNDED',
		'pending'    => 'ORDER_PENDING_PAYMENT',
		'on-hold'    => 'ORDER_ON_HOLD',
		'cancelled'  => 'ORDER_CANCELLED',
		'failed'     => 'ORDER_PAYMENT_FAILED',
	);

	/** @var \WC_Facebookcommerce */
	private $plugin;


	public function __construct( WC_Facebookcommerce $plugin ) {
		$rollout_switches = $plugin->get_rollout_switches();
		$this->plugin     = $plugin;
		if ( ! $this->is_whatsapp_utility_enabled() ) {
			return;
		}
		add_action( 'woocommerce_order_status_changed', array( $this, 'process_wc_order_status_changed' ), 10, 3 );
	}


	/**
	 * Determines if WhatsApp Utility Messages are enabled
	 *
	 * @return bool
	 */
	private function is_whatsapp_utility_enabled() {
		$is_enabled       = false;
		$rollout_switches = $this->plugin->get_rollout_switches();
		if ( isset( $rollout_switches ) ) {
			$is_enabled = $rollout_switches->is_switch_enabled(
				RolloutSwitches::WHATSAPP_UTILITY_MESSAGING_BETA_EXPERIENCE
			) ?? false;
		}
		return $is_enabled;
	}

	/**
	 * Hook to process Order Processing, Order Completed and Order Refunded events for WhatsApp Utility Messages
	 *
	 * @param string $order_id Order id
	 * @param string $old_status Old Order Status
	 * @param string $new_status New Order Status
	 *
	 * @return void
	 * @since 2.3.0
	 */
	public function process_wc_order_status_changed( $order_id, $old_status, $new_status ) {
		$supported_statuses = array_keys( self::ORDER_STATUS_TO_EVENT_MAPPING );
		if ( ! in_array( $new_status, $supported_statuses, true ) ) {
			return;
		}

		wc_get_logger()->info(
			sprintf(
			/* translators: %s $order_id */
				__( 'Processing Order id %1$s to send Whatsapp Utility messages', 'facebook-for-woocommerce' ),
				$order_id,
			)
		);
		$event              = self::ORDER_STATUS_TO_EVENT_MAPPING[ $new_status ];
		$order              = wc_get_order( $order_id );
		$order_details_link = $order->get_checkout_order_received_url();
		// Get WhatsApp Phone number from entered Billing and Shipping phone number
		$billing_phone_number  = $order->get_billing_phone();
		$shipping_phone_number = $order->get_shipping_phone();
		$phone_number          = $billing_phone_number ?? $shipping_phone_number;
		// Get Country Code from Billing and Shipping Country to override Country Calling Code
		$country_code = $should_use_billing_info ? $order->get_billing_country() : $order->get_shipping_country();
		// Get Customer first name
		$first_name = $order->get_billing_first_name();
		// Get Total Refund Amount for Order Refunded event
		$total_refund = 0;
		foreach ( $order->get_refunds() as $refund ) {
			$total_refund += $refund->get_amount();
		}
		$currency      = $order->get_currency();
		$refund_amount = $total_refund * 1000;
<<<<<<< HEAD
		// Build order payload with additional fields requested by WA API
		$order_payload = array();
		// Order status
		if ( method_exists( $order, 'get_status' ) ) {
			$order_payload['status'] = $order->get_status();
		}
		// Order URL (view from My Account)
		if ( method_exists( $order, 'get_view_order_url' ) ) {
			$order_payload['order_url'] = $order->get_view_order_url();
		}
		// Shipping method
		if ( method_exists( $order, 'get_shipping_method' ) ) {
			$order_payload['shipping_method'] = $order->get_shipping_method();
		}
		// Currency
		$order_payload['currency'] = $currency;
		// Order date (ISO 8601)
		$order_date = $order->get_date_created();
		if ( $order_date && method_exists( $order_date, 'format' ) ) {
			$order_payload['order_date'] = $order_date->format( DATE_ATOM );
		} elseif ( $order_date ) {
			$order_payload['order_date'] = (string) $order_date;
		}
		// Items
		$order_payload['items'] = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$image   = '';
			if ( is_object( $item ) && method_exists( $item, 'get_image' ) ) {
				$image = $item->get_image();
			} elseif ( $product && method_exists( $product, 'get_image_id' ) ) {
				$image_id = $product->get_image_id();
				if ( $image_id ) {
					$image = wp_get_attachment_url( $image_id );
				}
			}
			$name     = is_object( $item ) && method_exists( $item, 'get_name' ) ? $item->get_name() : ( $product && method_exists( $product, 'get_name' ) ? $product->get_name() : '' );
			$quantity = is_object( $item ) && method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 0;
			$total    = is_object( $item ) && method_exists( $item, 'get_total' ) ? $item->get_total() : '';
			$order_payload['items'][] = array(
				'image'    => $image,
				'name'     => $name,
				'quantity' => $quantity,
				'amount'   => array(
					'value'  => (string) $total,
					'offset' => 100,
				),
			);
		}
=======
>>>>>>> parent of f4305d67 (adding more order data)
		if ( empty( $phone_number ) || empty( $event ) || empty( $first_name ) ) {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Customer Events Post API call for Order id %1$s skipped due to missing Order info', 'facebook-for-woocommerce' ),
					$order_id,
				)
			);
			return;
		}
		WhatsAppExtension::process_whatsapp_utility_message_event( $this->plugin, $event, $order_id, $order_details_link, $phone_number, $first_name, $refund_amount, $currency, $country_code );
	}
}
