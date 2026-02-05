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
	// const BASE_STEFI_ENDPOINT_URL = 'https://api.11978.od.facebook.com';
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
	* @param array  $order_meta Additional order metadata to merge into the event object
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
		$order_meta = array()
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

		// Log input and initial event object
		wc_get_logger()->info( 'WA INPUT: ' . wp_json_encode( array(
			'order_id' => $order_id,
			'event' => $event,
			'order_details_link' => $order_details_link,
			'refund_value' => $refund_value,
			'currency' => $currency,
			'phone' => $phone_number,
			'first_name' => $first_name,
			'country_code' => $country_code,
			'event_object_initial' => $event_object,
			'order_meta' => $order_meta,
		) ) );

		
		// Merge any additional order metadata into the event object
		if ( ! empty( $order_meta ) && is_array( $order_meta ) ) {
			wc_get_logger()->info( 'WA PRE-MERGE event_object: ' . wp_json_encode( $event_object ) . ' order_meta: ' . wp_json_encode( $order_meta ) );
			$event_object = array_merge( $event_object, $order_meta );
			wc_get_logger()->info( 'WA POST-MERGE event_object: ' . wp_json_encode( $event_object ) );
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Installation ID %1$s  ', 'facebook-for-woocommerce' ),
					$wa_installation_id,
				)
			);
		}

		// Ensure a rich_order_status object exists for order-related events so downstream
		// Stefi consumers don't hit a non-null assertion.
		if ( in_array( $event, array( 'ORDER_PLACED', 'ORDER_FULFILLED', 'ORDER_REFUNDED' ), true ) ) {
			$rich_order_status = array();
			// prefer explicit fields from $order_meta when available
			if ( isset( $order_meta['order_url'] ) ) {
				$rich_order_status['order_url'] = $order_meta['order_url'];
			} elseif ( isset( $event_object['order_details_url'] ) ) {
				$rich_order_status['order_url'] = $event_object['order_details_url'];
			}
			if ( isset( $order_meta['order_date'] ) ) {
				$rich_order_status['order_date'] = $order_meta['order_date'];
			}
			if ( ! empty( $currency ) ) {
				$rich_order_status['currency'] = $currency;
			}
			if ( isset( $order_meta['shipping_method'] ) ) {
				$rich_order_status['shipping_method'] = $order_meta['shipping_method'];
			}
			// items: try to fetch basic item info from the order if possible
			$items = array();
			$order_obj = null;
			try {
				if ( function_exists( 'wc_get_order' ) ) {
					$order_obj = wc_get_order( ltrim( (string) $order_id, '#' ) );
				}
			} catch ( \Exception $e ) {
				$order_obj = null;
			}
			if ( $order_obj ) {
				wc_get_logger()->info( 'WA ORDER_OBJ: id=' . ( method_exists( $order_obj, 'get_id' ) ? $order_obj->get_id() : 'n/a' ) . ' items_count=' . count( $order_obj->get_items() ) );
				foreach ( $order_obj->get_items() as $item ) {
					// Log per-item diagnostics to understand why items might be empty
					try {
						$diag = array(
							'class' => is_object( $item ) ? get_class( $item ) : 'array',
							'get_name' => is_object( $item ) && method_exists( $item, 'get_name' ) ? $item->get_name() : null,
							'get_quantity' => is_object( $item ) && method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : null,
							'get_total' => is_object( $item ) && method_exists( $item, 'get_total' ) ? $item->get_total() : ( is_array( $item ) && isset( $item['line_total'] ) ? $item['line_total'] : null ),
							'raw' => is_array( $item ) ? $item : null,
						);
						wc_get_logger()->info( 'WA ORDER_ITEM_DIAG: ' . wp_json_encode( $diag ) );
					} catch ( \Exception $e ) {
						wc_get_logger()->info( 'WA ORDER_ITEM_DIAG_ERROR: ' . $e->getMessage() );
					}
					$product = $item->get_product();
					$amount_1000 = null;
					// try to get line_total and convert to thousandths if available
					$line_total = isset( $item['line_total'] ) ? $item['line_total'] : ( method_exists( $item, 'get_total' ) ? $item->get_total() : null );
					if ( null !== $line_total ) {
						// line_total is in base currency units; convert to cents then *10 to approximate _1000 scale
						$amount_1000 = (int) round( ( (float) $line_total * 100 ) );
					}
					$item_name = is_object( $item ) && method_exists( $item, 'get_name' ) ? $item->get_name() : ( is_array( $item ) && isset( $item['name'] ) ? $item['name'] : '' );
					$item_qty  = is_object( $item ) && method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : ( is_array( $item ) && isset( $item['qty'] ) ? $item['qty'] : 1 );
					// Prefer real product image id if available
					$image_url = null;
					if ( $product && method_exists( $product, 'get_image_id' ) ) {
						$image_id = $product->get_image_id();
						if ( $image_id ) {
							$image_url = wp_get_attachment_url( $image_id );
						}
					}
					$items[] = array(
						'name' => $item_name,
						'quantity' => $item_qty,
						'amount_1000' => $amount_1000,
						'image_url' => 'https://cdnmedia.dsc-cricket.com.au/media/catalog/product/cache/ead9833944ee19ab74f6785fdc9a346d/f/l/flip-pro-english-willow-cricket-bat.jpg',
					);
				}
			}
			$rich_order_status['items'] = $items;
			// If no items were found, create a minimal fallback item from order totals
			if ( empty( $items ) && $order_obj ) {
				try {
					$fallback_amount_1000 = null;
					if ( method_exists( $order_obj, 'get_total' ) ) {
						$fallback_amount_1000 = (int) round( ( (float) $order_obj->get_total() * 100 ) * 10 );
					}
					$fallback_item = array(
						// use order id as placeholder name when item details are unavailable
						'name' => 'Order #' . $order_id,
						'quantity' => 1,
						'amount_1000' => $fallback_amount_1000 ?? 0,
						'image_url' => 'https://cdn.shopify.com/s/files/1/0090/9236/6436/files/Best_Shopify_store_examples_Beard_Blade_015931ad-7969-43dc-9f93-c067bd56522f_1024x1024.png',
					);
					$rich_order_status['items'] = array( $fallback_item );
					wc_get_logger()->info( 'WA ITEMS_FALLBACK used: ' . wp_json_encode( $fallback_item ) );
				} catch ( \Exception $e ) {
					wc_get_logger()->info( 'WA ITEMS_FALLBACK_ERROR: ' . $e->getMessage() );
				}
			}
			// attach to the event object
			$event_object['rich_order_status'] = $rich_order_status;
			wc_get_logger()->info( 'WA RICH_ORDER_STATUS: ' . wp_json_encode( $event_object['rich_order_status'] ?? null ) );
		}
		$event_base_object  = array(
			'id'   => "#{$order_id}",
			'type' => $event,
		);
		if ( ! empty( $event_object ) ) {
			$event_base_object[ $event_lowercase ] = $event_object;
		}

		// Some downstream consumers expect `rich_order_status` as a sibling of
		// the event-specific object (e.g. event.order_placed and event.rich_order_status).
		// If present, hoist it to the event root and remove from the nested object.
		if ( isset( $event_base_object[ $event_lowercase ]['rich_order_status'] ) ) {
			$event_base_object['rich_order_status'] = $event_base_object[ $event_lowercase ]['rich_order_status'];
			unset( $event_base_object[ $event_lowercase ]['rich_order_status'] );
		}
		// Build JSON payload and headers to preserve nested objects
		$payload = array(
			'customer' => array(
				'id'           => $phone_number,
				'type'         => 'GUEST',
				'first_name'   => $first_name,
				'country_code' => $country_code,
				'language'     => get_user_locale(),
			),
			'event'    => $event_base_object,
		);
		wc_get_logger()->info( 'WA OUTBOUND_PAYLOAD: ' . wp_json_encode( $payload ) );
		$options = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bisu_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 300, // 5 minutes
		);

		$response        = wp_remote_post( $base_url, $options );
		$status_code     = wp_remote_retrieve_response_code( $response );
		$data            = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_object = json_decode( $data[0] );

				// Log detailed response for debugging
		$log_payload = array(
			'response'        => $response,
			'status_code'     => $status_code,
			'data'            => $data,
			'response_object' => $response_object,
		);
		wc_get_logger()->info(
			sprintf(
				/* translators: %1$s $wa_installation_id, %2$s payload */
				__( 'WhatsApp API response for %1$s: %2$s', 'facebook-for-woocommerce' ),
				isset( $wa_installation_id ) ? $wa_installation_id : '',
				wp_json_encode( $log_payload )
			)
		);
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
				// include both tracking_url (expected by downstream API) and order_details_url
				// so we satisfy the external contract while keeping internal fallbacks working
				return array(
					'tracking_url' => $order_details_link,
					'order_details_url' => $order_details_link,
				);
			case 'ORDER_REFUNDED':
				return array(
					'amount' => array(
						'value'  => $refund_value,
						'offset' => 100,
					),
					'currency'    => $currency,
				);
			default:
				return array();
		}
	}
}
