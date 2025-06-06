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
 * Handles WhatsApp Utility GET and POST APIs Graph API requests.
 *
 * @since 2.3.0
 */
class WhatsAppUtilityConnection {

	/** @var string API version */
	const API_VERSION = 'v22.0';

	/** @var string Graph API base URL */
	const GRAPH_API_BASE_URL = 'https://graph.facebook.com';

	/** @var string Prefix for Whatsapp Utility Option Names */
	const WA_UTILITY_OPTION_PREFIX = 'wc_facebook_wa';

	/** @var array Mapping of Events to Template Library name */
	const EVENT_TO_LIBRARY_TEMPLATE_MAPPING = array(
		'ORDER_PLACED'    => 'order_management_4',
		'ORDER_FULFILLED' => 'shipment_confirmation_4',
		'ORDER_REFUNDED'  => 'refund_confirmation_1',
	);

	/** @var string Default language for Library Template */
	const DEFAULT_LANGUAGE = 'en';

	/** @var array List of all WA settings stored in wp_options */
	const WA_SETTINGS = array(
		'wc_facebook_wa_integration_waba_id',
		'wc_facebook_wa_integration_bisu_access_token',
		'wc_facebook_wa_integration_business_id',
		'wc_facebook_wa_integration_wacs_phone_number',
		'wc_facebook_wa_integration_is_payment_setup',
		'wc_facebook_wa_integration_wacs_id',
		'wc_facebook_wa_integration_waba_profile_picture_url',
		'wc_facebook_wa_integration_waba_display_name',
		'wc_facebook_whatsapp_consent_collection_setting_status',
		'wc_facebook_wa_integration_config_id',
		'wc_facebook_wa_order_placed_event_config_id',
		'wc_facebook_wa_order_placed_language',
		'wc_facebook_wa_order_fulfilled_event_config_id',
		'wc_facebook_wa_order_fulfilled_language',
		'wc_facebook_wa_order_refunded_event_config_id',
		'wc_facebook_wa_order_refunded_language',
	);

	const WA_INVALID_TOKEN_ERROR_CODE = 190;

	/**
	 * Makes an API call to Template Library API
	 *
	 * @param string $event Order Management Event
	 * @param string $bisu_token the BISU token received in the webhook
	 */
	public static function get_template_library_content( $event, $bisu_token ) {
		wc_get_logger()->info(
			sprintf(
				__( 'In Template Library Get API call ', 'facebook-for-woocommerce' ),
			)
		);
		$base_url     = array( self::GRAPH_API_BASE_URL, self::API_VERSION, 'message_template_library' );
		$base_url     = esc_url( implode( '/', $base_url ) );
		$library_name = self::EVENT_TO_LIBRARY_TEMPLATE_MAPPING[ $event ];

		$params  = array(
			'name'         => $library_name,
			'language'     => self::DEFAULT_LANGUAGE,
			'access_token' => $bisu_token,
		);
		$url     = add_query_arg( $params, $base_url );
		$options = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
			'timeout' => 300, // 5 minutes
		);

		$response    = wp_remote_request( $url, $options );
		$status_code = wp_remote_retrieve_response_code( $response );
		$data        = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			wc_get_logger()->info(
				sprintf(
					/* translators: %s $error_message */
					__( 'Template Library GET API call Failed %1$s ', 'facebook-for-woocommerce' ),
					$data,
				)
			);
			wp_send_json_error( $response, 'Template Library GET API call Failed' );
		} else {
			wc_get_logger()->info(
				sprintf(
					__( 'Template Library GET API call Succeeded', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_success( $data, 'Finish Template Library API Call' );
		}
	}

	/**
	 * Makes an API call to Whatsapp Utility Message Connect API
	 *
	 * @param string $waba_id WABA ID
	 * @param string $wacs_id WACS ID
	 * @param string $external_business_id external business ID
	 * @param string $bisu_token BISU token
	 */
	public static function wc_facebook_whatsapp_connect_utility_messages_call( $waba_id, $wacs_id, $external_business_id, $bisu_token ) {
		$base_url     = array( self::GRAPH_API_BASE_URL, self::API_VERSION, $waba_id, 'connect_utility_messages' );
		$base_url     = esc_url( implode( '/', $base_url ) );
		$query_params = array(
			'external_integration_id' => $external_business_id,
			'wacs_id'                 => $wacs_id,
			'access_token'            => $bisu_token,
		);
		$base_url     = add_query_arg( $query_params, $base_url );
		$options      = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
			'timeout' => 300, // 5 minutes
		);
		$response     = wp_remote_post( $base_url, $options );
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $response */
				__( 'Connect Whatsapp Utility Message API Response: %1$s ', 'facebook-for-woocommerce' ),
				wp_json_encode( $response ),
			)
		);
		$response_body = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_data = json_decode( $response_body[0] );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error_message = $response_data->error->error_user_title ?? $response_data->error->message ?? 'Something went wrong. Please try again later!';

			wc_get_logger()->info(
				sprintf(
					/* translators: %s $error_message */
					__( 'Connect Whatsapp Utility Message API Call Failure %1$s ', 'facebook-for-woocommerce' ),
					$error_message,
				)
			);
			wp_send_json_error( $error_message, 'Finish Onboarding Failure' );
		} else {
				$integration_config_id = $response_data->id;
				wc_get_logger()->info(
					sprintf(
						/* translators: %s $integration_config_id */
						__( 'Connect Whatsapp Utility Message API Call Success!!! Integration ID: %1$s!!!', 'facebook-for-woocommerce' ),
						$integration_config_id,
					)
				);
			update_option( 'wc_facebook_wa_integration_config_id', $integration_config_id );
			wp_send_json_success( $response, 'Finish Onboarding Success' );
		}
	}

	/**
	 * Makes an API call to Whatsapp Utility Message Disconnect API and delete the options in DB
	 *
	 * @param string $waba_id WABA ID
	 * @param string $integration_config_id whatsapp integration config ID
	 * @param string $bisu_token BISU token
	 */
	public static function wc_facebook_disconnect_whatsapp( $waba_id, $integration_config_id, $bisu_token ) {
		$base_url           = array( self::GRAPH_API_BASE_URL, self::API_VERSION, $waba_id, 'disconnect_utility_messages' );
		$base_url           = esc_url( implode( '/', $base_url ) );
		$query_params       = array(
			'integration_config_id' => $integration_config_id,
			'access_token'          => $bisu_token,
		);
		$base_url           = add_query_arg( $query_params, $base_url );
		$options            = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
			'timeout' => 300, // 5 minutes
		);
		$response           = wp_remote_post( $base_url, $options );
		$response_body      = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_body_json = json_decode( $response_body[0] );
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $error_message */
				__( 'Disconnect Whatsapp Utility Message API Call Response: %1$s ', 'facebook-for-woocommerce' ),
				wp_json_encode( $response ),
			)
		);
		// Error code 190 is for invalid token meaning the app was already uninstalled, in this case we can delete the options in DB
		if ( null !== $response_body_json && isset( $response_body_json->error ) && self::WA_INVALID_TOKEN_ERROR_CODE === $response_body_json->error->code ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Disconnecting Whatsapp Utility Message since Access token is invalid!!!', 'facebook-for-woocommerce' )
				)
			);

			// delete all the whatsapp setting options in DB
			self::wc_facebook_whatsapp_settings_delete( self::WA_SETTINGS );

			wc_get_logger()->info(
				sprintf(
					__( 'Disconnect Whatsapp Utility Message Invalid Access Token - Whatsapp Settings Deletion Success!!!', 'facebook-for-woocommerce' )
				)
			);

			wp_send_json_success( $response, 'Disconnect Whatsapp Success with Invalid Access Token' );

		} elseif ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error_object  = json_decode( $response_body[0] );
			$error_message = $error_object->error->error_user_title ?? $error_object->error->message ?? 'Something went wrong. Please try again later!';

			wc_get_logger()->info(
				sprintf(
					/* translators: %s $error_message */
					__( 'Disconnect Whatsapp Utility Message API Call Error: %1$s ', 'facebook-for-woocommerce' ),
					$error_message,
				)
			);
			wp_send_json_error( $error_message, 'Disconnect Whatsapp Failure' );
		} else {
			wc_get_logger()->info(
				sprintf(
					__( 'Disconnect Whatsapp Utility Message API Call Success!!!', 'facebook-for-woocommerce' )
				)
			);

			// delete all the whatsapp setting options in DB
			self::wc_facebook_whatsapp_settings_delete( self::WA_SETTINGS );

			wc_get_logger()->info(
				sprintf(
					__( 'Disconnect Whatsapp Utility Message - Whatsapp Settings Deletion Success!!!', 'facebook-for-woocommerce' )
				)
			);

			wp_send_json_success( $response, 'Disconnect Whatsapp Success' );
		}
	}

	public static function wc_facebook_whatsapp_settings_delete( $wa_settings ) {
		foreach ( $wa_settings as $setting ) {
			delete_option( $setting ); // this only deletes if option exists, no error on failure
		}
	}

	/**
	 * Makes an API call to Whatsapp Utility Event Configs Post API to create or update Event Configs
	 *
	 * @param string $event Order Management Event
	 * @param string $integration_config_id Integration Config Id
	 * @param string $language Language Code
	 * @param string $status ACTIVE or INACTIVE
	 * @param string $bisu_token the BISU token received in the webhook
	 */
	public static function post_whatsapp_utility_messages_event_configs_call( $event, $integration_config_id, $language, $status, $bisu_token ) {
		$base_url             = array( self::GRAPH_API_BASE_URL, self::API_VERSION, $integration_config_id, 'event_configs' );
		$base_url             = esc_url( implode( '/', $base_url ) );
		$account_url          = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
		$view_orders_endpoint = get_option( 'woocommerce_myaccount_view_order_endpoint' );
		$view_orders_base_url = esc_url( $account_url . $view_orders_endpoint );
		// Order Refunded template has no CTA
		$library_template_button_inputs = 'ORDER_REFUNDED' === $event ? array() : array(
			array(
				'type' => 'URL',
				'url'  => array(
					// View Url is dynamic and has order_id as suffix
					'base_url'           => "$view_orders_base_url/{{1}}",
					// Example view orders url with order id: 1234
					'url_suffix_example' => "$view_orders_base_url/1234",
				),
			),
		);
		$query_params                   = array(
			'event'                          => $event,
			'language'                       => $language,
			'status'                         => $status,
			'library_template_name'          => self::EVENT_TO_LIBRARY_TEMPLATE_MAPPING[ $event ],
			'library_template_button_inputs' => $library_template_button_inputs,
			'access_token'                   => $bisu_token,
		);
		$base_url                       = add_query_arg( $query_params, $base_url );
		$options                        = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
			'timeout' => 300, // 5 minutes
		);
		$response                       = wp_remote_post( $base_url, $options );
		$status_code                    = wp_remote_retrieve_response_code( $response );
		$data                           = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_object                = json_decode( $data[0] );
		$is_error                       = is_wp_error( $response );
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $error_message */
				__( 'Event Configs Post API call Response: %1$s ', 'facebook-for-woocommerce' ),
				wp_json_encode( $response ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			$error_message = $response_object->error->error_user_title ?? $response_object->error->message ?? 'Something went wrong. Please try again later!';
			wc_get_logger()->info(
				sprintf(
					/* translators: %s $error_message %s status code %s is_wp_error value*/
					__( 'Event Configs Post API call Failed with Error: %1$s, Status code: %2$d, Is Wp Error: %3$s', 'facebook-for-woocommerce' ),
					$error_message,
					$status_code,
					(string) $is_error,
				)
			);
			wp_send_json_error( $response, 'Event Configs Post API call Failed' );
		} else {
			$event_config_id_option_name       = implode( '_', array( self::WA_UTILITY_OPTION_PREFIX, strtolower( $event ), 'event_config_id' ) );
			$event_config_language_option_name = implode( '_', array( self::WA_UTILITY_OPTION_PREFIX, strtolower( $event ), 'language' ) );
			$event_config_id                   = $response_object->id;
			$event_status                      = $response_object->status;
			$language                          = $response_object->language;
			wc_get_logger()->info(
				sprintf(
					/* translators: %s $option_name  %s $event_config_id %s $event_status */
					__( 'Event Configs Post API call Succeeded. API Response Event Config id: %1$s, Event Status: %2$s, Language: %3$s', 'facebook-for-woocommerce' ),
					$event_config_id,
					$event_status,
					$language,
				)
			);
			if ( 'ACTIVE' === $event_status ) {
				update_option( $event_config_id_option_name, $event_config_id );
				update_option( $event_config_language_option_name, $language );
			} else {
				$settings = array(
					$event_config_id_option_name,
					$event_config_language_option_name,
				);
				self::wc_facebook_whatsapp_settings_delete(
					$settings
				);
			}
			wp_send_json_success( 'Event Configs Post API call Completed' );
		}
	}


	/**
	 * Makes an API call to Event Processor: Message Events Post API to send whatsapp utility messages
	 *
	 * @param string $event Order Managerment event
	 * @param string $event_config_id Event Config Id
	 * @param string $language_code Language code
	 * @param string $wacs_id Whatsapp Phone Number id
	 * @param string $order_id Order id
	 * @param string $phone_number Customer phone number
	 * @param string $first_name Customer first name
	 * @param int    $refund_value Amount refunded to the Customer
	 * @param string $currency Currency code
	 * @param string $bisu_token the BISU token received in the webhook
	 */
	public static function post_whatsapp_utility_messages_events_call( $event, $event_config_id, $language_code, $wacs_id, $order_id, $phone_number, $first_name, $refund_value, $currency, $bisu_token ) {
		$base_url        = array( self::GRAPH_API_BASE_URL, self::API_VERSION, $wacs_id, "message_events?access_token=$bisu_token" );
		$base_url        = esc_url( implode( '/', $base_url ) );
		$name            = self::EVENT_TO_LIBRARY_TEMPLATE_MAPPING[ $event ];
		$components      = self::get_components_for_event( $event, $order_id, $first_name, $refund_value, $currency );
		$options         = array(
			'body'    => array(
				'messaging_product' => 'whatsapp',
				'to'                => $phone_number,
				'event_config_id'   => $event_config_id,
				'external_event_id' => "${order_id}",
				'template'          => array(
					'language'   => array(
						'code' => $language_code,
					),
					'components' => $components,
				),
				'type'              => 'template',
			),
			'timeout' => 300, // 5 minutes
		);
		$response        = wp_remote_post( $base_url, $options );
		$status_code     = wp_remote_retrieve_response_code( $response );
		$data            = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_object = json_decode( $data[0] );
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $error_message */
				__( 'Message Events Post API call Response: %1$s ', 'facebook-for-woocommerce' ),
				wp_json_encode( $response ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			$error_message = $response_object->error->error_user_title ?? $response_object->error->message ?? 'Something went wrong. Please try again later!';
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id %s $error_message */
					__( 'Message Events Post API call for Order id %1$s Failed %2$s ', 'facebook-for-woocommerce' ),
					$order_id,
					$error_message,
				)
			);
		} else {
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $order_id */
					__( 'Message Events Post API call for Order id %1$s Succeeded.', 'facebook-for-woocommerce' ),
					$order_id
				)
			);
		}
	}

	/**
	 * Makes an API call to Integration Config Get API
	 *
	 * @param string $integration_config_id Integration Config id
	 * @param string $bisu_token the BISU token received in the webhook
	 */
	public static function get_supported_languages_for_templates( $integration_config_id, $bisu_token ) {
		$base_url = array( self::GRAPH_API_BASE_URL, self::API_VERSION, $integration_config_id );
		$base_url = esc_url( implode( '/', $base_url ) );
		$params   = array(
			'access_token' => $bisu_token,
		);
		$url      = add_query_arg( $params, $base_url );
		$options  = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
			'timeout' => 300, // 5 minutes
		);

		$response    = wp_remote_request( $url, $options );
		$status_code = wp_remote_retrieve_response_code( $response );
		$data        = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			wc_get_logger()->info(
				sprintf(
					/* translators: %s $error_message */
					__( 'Integration Config GET API call Failed %1$s ', 'facebook-for-woocommerce' ),
					$data,
				)
			);
			wp_send_json_error( $response, 'Integration Config GET API call Failed' );
		} else {
			wc_get_logger()->info(
				sprintf(
					__( 'Integration Config GET API call Succeeded', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_success( $data, 'Finish Integration Config API Call' );
		}
	}


	/**
	 * Gets Component Objects for Order Management Events
	 *
	 * @param string $event Order Management event
	 * @param string $order_id Order id
	 * @param string $first_name Customer first name
	 * @param string $refund_value Amount refunded to the Customer
	 * @param string $currency Currency code
	 */
	public static function get_components_for_event( $event, $order_id, $first_name, $refund_value, $currency ) {
		if ( 'ORDER_REFUNDED' === $event ) {
			return array(
				array(
					'type'       => 'HEADER',
					'parameters' => array(
						array(
							'type'     => 'currency',
							'currency' => array(
								'fallback_value' => 'VALUE',
								'code'           => $currency,
								'amount_1000'    => $refund_value,
							),
						),
					),
				),
				array(
					'type'       => 'BODY',
					'parameters' => array(
						array(
							'type' => 'text',
							'text' => $first_name,
						),
						array(
							'type'     => 'currency',
							'currency' => array(
								'fallback_value' => 'VALUE',
								'code'           => $currency,
								'amount_1000'    => $refund_value,
							),
						),
						array(
							'type' => 'text',
							'text' => "#$order_id",
						),
					),
				),
			);
		} else {
			return array(
				array(
					'type'       => 'BODY',
					'parameters' => array(
						array(
							'type' => 'text',
							'text' => $first_name,
						),
						array(
							'type' => 'text',
							'text' => "#$order_id",
						),
					),
				),
				array(
					'type'       => 'BUTTON',
					'sub_type'   => 'url',
					'index'      => 0,
					'parameters' => array(
						array(
							'type' => 'text',
							'text' => "$order_id",
						),
					),
				),
			);
		}
	}
}
