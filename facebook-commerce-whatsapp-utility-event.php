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
		// Check WhatsApp Consent Checkbox is selected in shipping or billing
		$user_wa_consent             = $this->has_billing_or_shipping_number_whatsapp_consent( $order );
		$wa_billing_consent_enabled  = $user_wa_consent['has_user_consented_to_wa_billing_number_notif'];
		$wa_shipping_consent_enabled = $user_wa_consent['has_user_consented_to_wa_shipping_number_notif'];

		$has_whatsapp_consent    = $wa_billing_consent_enabled || $wa_shipping_consent_enabled;
		$should_use_billing_info = isset( $billing_phone_number ) && $wa_billing_consent_enabled;
		// Get WhatsApp Phone number from entered Billing and Shipping phone number
		$billing_phone_number  = $order->get_billing_phone();
		$shipping_phone_number = $order->get_shipping_phone();
		$phone_number          = $should_use_billing_info ? $billing_phone_number : $shipping_phone_number;
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
		WhatsAppUtilityConnection::post_whatsapp_utility_messages_events_call( $event, $event_config_id, $language_code, $wacs_id, $order_id, $phone_number, $first_name, $refund_amount, $currency, $bisu_token, $country_code );
	}

	/**
	 * Determines if WhatsApp Consent is Enabled for a user either for Billing or Shipping Phone Number for Blocks or Classic Flows
	 *
	 * @param \WC_Order $order Order object
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private function has_billing_or_shipping_number_whatsapp_consent( $order ) {
		$block_billing_consent_value    = $order->get_meta( '_wc_billing/wc_facebook/whatsapp_consent_checkbox' );
		$block_shipping_consent_value   = $order->get_meta( '_wc_shipping/wc_facebook/whatsapp_consent_checkbox' );
		$classic_billing_consent_value  = $order->get_meta( '_billing_whatsapp_consent' );
		$classic_shipping_consent_value = $order->get_meta( '_shipping_whatsapp_consent' );

		$has_user_consented_to_wa_billing_number_notif  = false;
		$has_user_consented_to_wa_shipping_number_notif = false;
		if ( $block_billing_consent_value || $classic_billing_consent_value ) {
			$has_user_consented_to_wa_billing_number_notif = true;
		}

		if ( $block_shipping_consent_value || $classic_shipping_consent_value ) {
			$has_user_consented_to_wa_shipping_number_notif = true;
		}

		wc_get_logger()->info(
			sprintf(
				/* translators: %s consent for billing and shipping */
				__( 'WhatsApp Consent info for user  $block_billing_consent_value: %1$s, $block_shipping_consent_value: %2$s, $classic_billing_consent_value: %3$s, $classic_shipping_consent_value: %4$s', 'facebook-for-woocommerce' ),
				$block_billing_consent_value,
				$block_shipping_consent_value,
				$classic_billing_consent_value,
				$classic_shipping_consent_value
			)
		);

		return array(
			'has_user_consented_to_wa_billing_number_notif' => $has_user_consented_to_wa_billing_number_notif,
			'has_user_consented_to_wa_shipping_number_notif' => $has_user_consented_to_wa_shipping_number_notif,
		);
	}
}
