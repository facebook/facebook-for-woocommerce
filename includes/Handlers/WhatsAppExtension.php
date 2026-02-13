<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WooCommerce\Facebook\RolloutSwitches;

/**
 * Handles Meta WhatsApp Utility Extension functionality and configuration.
 *
 * @since 3.5.0
 */
class WhatsAppExtension {



	/** @var string Commerce Hub base URL */
	const COMMERCE_HUB_URL = 'https://www.commercepartnerhub.com/';
	/** @var string Client token */
	const CLIENT_TOKEN = '753591807210902|489b438e3f0d9ba44504eccd5ce8fe94';
	/** @var string Whatsapp Integration app ID */
	const APP_ID = '753591807210902';
	/** @var string Whatsapp Tech Provider Business ID */
	const TP_BUSINESS_ID = '1421860479064677';
	/** @var string base url for meta stefi endpoint */
	const BASE_STEFI_ENDPOINT_URL = 'https://api.facebook.com';
	/** @var string Default language for Library Template */
	const DEFAULT_LANGUAGE = 'en';


	// ==========================
	// = IFrame Management      =
	// ==========================

	/**
	 * Generates the Commerce Hub whatsapp iframe splash page URL.
	 *
	 * @param object $plugin The plugin instance.
	 * @param string $external_wa_id External business ID.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_wa_iframe_splash_url( $plugin, $external_wa_id ): string {
		$whatsapp_connection = $plugin->get_whatsapp_connection_handler();
		wc_get_logger()->info(
			sprintf(
				__( 'WhatsApp Utility Messages Iframe Splash Url Fetched.', 'facebook-for-woocommerce' ),
			)
		);

		return add_query_arg(
			array(
				'access_client_token'   => self::CLIENT_TOKEN,
				'app_id'                => self::APP_ID,
				'app_owner_business_id' => self::TP_BUSINESS_ID,
				'external_business_id'  => $external_wa_id,
				'locale'                => get_user_locale() ?? self::DEFAULT_LANGUAGE,
			),
			self::COMMERCE_HUB_URL . 'whatsapp_utility_integration/splash/'
		);
	}

	/**
	 * Generates the Commerce Hub whatsApp iframe management page URL.
	 *
	 * @param object $plugin The plugin instance.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_wa_iframe_management_url( $plugin ) {
		$whatsapp_connection = $plugin->get_whatsapp_connection_handler();
		$is_connected        = $whatsapp_connection->is_connected();
		if ( ! $is_connected ) {
			wc_get_logger()->info(
				sprintf(
					__( 'WhatsApp Utility Messages Iframe Management Url failed to fetch due to failed WhatsApp connection', 'facebook-for-woocommerce' ),
				)
			);
			return '';
		}

		$wa_installation_id = $whatsapp_connection->get_wa_installation_id();
		$base_url           = array( self::BASE_STEFI_ENDPOINT_URL, 'whatsapp/business', $wa_installation_id, 'utility_message_iframe_management_uri' );
		$base_url           = esc_url( implode( '/', $base_url ) );
		$params             = array(
			'locale' => get_user_locale() ?? self::DEFAULT_LANGUAGE,
		);
		$url                = add_query_arg( $params, $base_url );

		$bisu_token      = $whatsapp_connection->get_access_token();
		$options         = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bisu_token,
			),
			'body'    => array(),
			'timeout' => 3000, // 5 minutes
		);
		$response        = wp_remote_get( $url, $options );
		$status_code     = wp_remote_retrieve_response_code( $response );
		$data            = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_object = json_decode( $data[0] );
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			$error_message = $response_object->detail ?? $response_object->title ?? 'Something went wrong. Please try again later!';
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $wa_installation_id %s $error_message */
					__( 'Failed to fetch iframe Management URI. wa_installation_id: %1$s, error message: %2$s', 'facebook-for-woocommerce' ),
					$wa_installation_id,
					$error_message,
				)
			);
			return '';
		} else {
			wc_get_logger()->info(
				sprintf(
					__( 'WhatsApp Utility Messages Iframe Management Url successfully fetched', 'facebook-for-woocommerce' ),
				)
			);
		}
		return $response_object->iframe_management_uri;
	}

	/**
	 * Trigger WhatsApp Message Sends for Processed Order
	 *
	 * @param object $plugin The plugin instance.
	 * @param string $event Order Management event
	 * @param string $order_id Order id
	 * @param string $order_details_link Order Details Link
	 * @param string $phone_number Customer phone number
	 * @param string $first_name Customer first name
	 * @param int    $refund_value Amount refunded to the Customer
	 * @param string $currency Currency code
	 * @param string $country_code Customer country code
	 * @param array  $order_metadata Optional order metadata used to build rich order status
	 * @param bool   $is_rich_order_enabled Whether rich order rollout switch is enabled
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function process_whatsapp_utility_message_event(
		$plugin,
		$event,
		$order_id,
		$order_details_link,
		$phone_number,
		$first_name,
		$refund_value,
		$currency,
		$country_code,
		$order_metadata = array(),
		$is_rich_order_enabled = false
	) {
		$whatsapp_connection = $plugin->get_whatsapp_connection_handler();
		$is_connected        = $whatsapp_connection->is_connected();
		if ( ! $is_connected ) {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Customer Events Post API call for Order id %1$s Failed due to failed connection ', 'facebook-for-woocommerce' ),
					$order_id,
				)
			);
			return;
		}
		$wa_installation_id = $whatsapp_connection->get_wa_installation_id();
		$base_url           = array( self::BASE_STEFI_ENDPOINT_URL, 'whatsapp/business', $wa_installation_id, 'customer_events' );
		$base_url           = esc_url( implode( '/', $base_url ) );
		$bisu_token         = $whatsapp_connection->get_access_token();
		$event_lowercase    = strtolower( $event );
		$event_object       = self::get_object_for_event(
			$event,
			$order_details_link,
			$refund_value,
			$currency
		);
		$event_base_object  = array(
			'id'   => "#{$order_id}",
			'type' => $event,
		);
		if ( ! empty( $event_object ) ) {
			$event_base_object[ $event_lowercase ] = $event_object;
		}
		// Attach rich_order_status only when rollout switch enabled and order_metadata provided.
		if ( $is_rich_order_enabled && ! empty( $order_metadata ) ) {
			try {
				$rich_status = self::build_rich_order_status( $order_metadata );
				if ( ! empty( $rich_status ) ) {
					$event_base_object['rich_order_status'] = $rich_status;
				}
			} catch ( \Throwable $e ) {
				wc_get_logger()->info( 'Error building rich_order_status: ' . $e->getMessage() );
			}
		}
		$options = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bisu_token,
			),
			'body'    => array(
				'customer' => array(
					'id'           => $phone_number,
					'type'         => 'GUEST',
					'first_name'   => $first_name,
					'country_code' => $country_code,
					'language'     => get_user_locale(),
				),
				'event'    => $event_base_object,
			),
			'timeout' => 3000, // 5 minutes
		);

		$response        = wp_remote_post( $base_url, $options );
		$status_code     = wp_remote_retrieve_response_code( $response );
		$data            = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_object = json_decode( $data[0] );
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			$error_message = $response_object->detail ?? $response_object->title ?? 'Something went wrong. Please try again later!';
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id %s $error_message */
					__( 'Customer Events Post API call for Order id %1$s Failed %2$s ', 'facebook-for-woocommerce' ),
					$order_id,
					$error_message,
				)
			);
		} else {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Customer Events Post API call for Order id %1$s Succeeded.', 'facebook-for-woocommerce' ),
					$order_id
				)
			);
		}
		return;
	}

	/**
	 * Build the rich_order_status array from order metadata.
	 *
	 * @param array $order_metadata The order metadata containing order details.
	 * @return array An array with keys: order_url, order_date, currency, shipping_method, items (each item has: name, quantity, amount_1000, image_url)
	 */
	public static function build_rich_order_status( $order_metadata ) {
		$rich_status_object = array(
			'order_url'       => $order_metadata['order_url'] ?? '',
			'order_date'      => $order_metadata['order_date'] ?? '',
			'currency'        => $order_metadata['currency'] ?? '',
			'shipping_method' => $order_metadata['shipping_method'] ?? '',
			'items'           => array(),
		);

		if ( ! empty( $order_metadata['items'] ) && is_array( $order_metadata['items'] ) ) {
			foreach ( $order_metadata['items'] as $order_item ) {
				$item_arr = array();
				$item_arr['name']     = $order_item['name'] ?? ( $order_item['product_name'] ?? '' );
				$item_arr['quantity'] = isset( $order_item['quantity'] ) ? intval( $order_item['quantity'] ) : null;

				// Ensure `amount_1000` is present for the server consumer.
				// Derived from numeric `amount`.
				if ( isset( $order_item['amount'] ) ) {
					// Derive from numeric amount; scale to cents per current server expectation.
					$item_arr['amount_1000'] = (int) round( (float) $order_item['amount'] * 100 );
				}

				$image_url = '';
				if ( ! empty( $order_item['product_id'] ) ) {
					try {
						$product = wc_get_product( $order_item['product_id'] );
						if ( $product ) {
							$image_id = method_exists( $product, 'get_image_id' ) ? $product->get_image_id() : null;
							if ( $image_id ) {
								$img = wp_get_attachment_image_url( $image_id, 'full' );
								$image_url = $img ? $img : wp_get_attachment_url( $image_id );
								// Ensure HTTPS protocol for image URLs
								$image_url = preg_replace( '/^http:/', 'https:', $image_url );
							}
						}
					} catch ( \Throwable $e ) {
						wc_get_logger()->info( 'Error fetching product image: ' . $e->getMessage() );
					}
				}
				$item_arr['image_url'] = $image_url;
				$rich_status_object['items'][] = $item_arr;
			}
		}

		return $rich_status_object;
	}

	/**
	 * Gets event data tied to Order Management Event
	 *
	 * @param string $event Order Management event
	 * @param string $order_details_link Order details link
	 * @param string $refund_value Amount refunded to the Customer
	 * @param string $currency Currency code
	 */
	public static function get_object_for_event( $event, $order_details_link, $refund_value, $currency ) {
		switch ( $event ) {
			case 'ORDER_PLACED':
				return array(
					'order_details_url' => $order_details_link,
				);
			case 'ORDER_FULFILLED':
				return array(
					'tracking_url' => $order_details_link,
				);
			case 'ORDER_REFUNDED':
				return array(
					'amount_1000' => $refund_value,
					'currency'    => $currency,
				);
			default:
				return array();
		}
	}
}
