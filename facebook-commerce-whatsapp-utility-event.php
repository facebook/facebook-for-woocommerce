<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\Handlers\WhatsAppUtilityConnection;

/**
 * Event Processor for sending WhatsApp Utility Message when Order Management events are triggered
 */
class WC_Facebookcommerce_Whatsapp_Utility_Event {

	/** @var array Mapping of Order Status to Event name */
	const ORDER_STATUS_TO_EVENT_MAPPING = array(
		'processing' => 'ORDER_PLACED',
		'completed'  => 'ORDER_FULFILLED',
		'refunded'   => 'ORDER_REFUNDED',
	);

	public function __construct() {
		if ( ! $this->is_whatsapp_utility_enabled() ) {
			return;
		}
		add_action( 'woocommerce_order_status_changed', array( $this, 'process_wc_order_status_changed' ), 10, 3 );
	}

	/**
	 * Determines if WhatsApp Utility Messages are enabled
	 * TODO: Update this function to check for gating logic for Alpha businesses
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	private function is_whatsapp_utility_enabled() {
		return true;
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
		// WhatsApp Utility Messages are supported only for Processing status
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
		$event = self::ORDER_STATUS_TO_EVENT_MAPPING[ $new_status ];

		// Check WhatsApp Event Config is active
		$event_config_id_option_name       = implode( '_', array( WhatsAppUtilityConnection::WA_UTILITY_OPTION_PREFIX, strtolower( $event ), 'event_config_id' ) );
		$event_config_language_option_name = implode( '_', array( WhatsAppUtilityConnection::WA_UTILITY_OPTION_PREFIX, strtolower( $event ), 'language' ) );
		$event_config_id                   = get_option( $event_config_id_option_name, null );
		$language_code                     = get_option( $event_config_language_option_name, null );
		if ( empty( $event_config_id ) || empty( $language_code ) ) {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Messages Post API call for Order id %1$s skipped due to no active event config', 'facebook-for-woocommerce' ),
					$order_id,
				)
			);
			return;
		}

		$order = wc_get_order( $order_id );
		// Check WhatsApp Consent Checkbox is selected in shipping and billing
		$billing_consent_value  = $order->get_meta( '_wc_billing/wc_facebook/whatsapp_consent_checkbox' );
		$shipping_consent_value = $order->get_meta( '_wc_shipping/wc_facebook/whatsapp_consent_checkbox' );
		$has_whatsapp_consent   = $billing_consent_value && $shipping_consent_value;
		// Get WhatsApp Phone number from entered Billing and Shipping phone number
		$billing_phone_number  = $order->get_billing_phone();
		$shipping_phone_number = $order->get_shipping_phone();
		$phone_number          = ( isset( $billing_phone_number ) && $billing_consent_value ) ? $billing_phone_number : $shipping_phone_number;
		// Get Customer first name
		$first_name = $order->get_billing_first_name();
		// Get Total Refund Amount for Order Refunded event
		$total_refund = 0;
		foreach ( $order->get_refunds() as $refund ) {
			$total_refund += $refund->get_amount();
		}
		$currency      = $order->get_currency();
		$refund_amount = $total_refund * 1000;
		if ( empty( $phone_number ) || ! $has_whatsapp_consent || empty( $event ) || empty( $first_name ) ) {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Messages Post API call for Order id %1$s skipped due to missing whatsapp consent or Order info', 'facebook-for-woocommerce' ),
					$order_id,
				)
			);
			return;
		}

		// Check Access token and WACS is available
		$bisu_token = get_option( 'wc_facebook_wa_integration_bisu_access_token', null );
		$wacs_id    = get_option( 'wc_facebook_wa_integration_wacs_id', null );
		if ( empty( $bisu_token ) || empty( $wacs_id ) ) {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Messages Post API call for Order id %1$s Failed due to missing access token or wacs info', 'facebook-for-woocommerce' ),
					$order_id,
				)
			);
			return;
		}
		WhatsAppUtilityConnection::post_whatsapp_utility_messages_events_call( $event, $event_config_id, $language_code, $wacs_id, $order_id, $phone_number, $first_name, $refund_amount, $currency, $bisu_token );
	}
}
