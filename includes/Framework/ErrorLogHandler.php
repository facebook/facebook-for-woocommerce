<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Framework;

use WC_Facebookcommerce_Utils;
use Throwable;

defined( 'ABSPATH' ) || exit;


/**
 * The ErrorLog handler.
 *
 * @since 3.5.0
 */
class ErrorLogHandler {

	/**
	 * Hook name for Meta Log API.
	 */
	const META_LOG_API = 'facebook_for_woocommerce_log_api';

	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = \WC_Facebookcommerce::VERSION;

	/**
	 * Constructs a new ErrorLog handler.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		add_action( self::META_LOG_API, array( $this, 'process_error_log' ), 10, 1 );
	}

	/**
	 * Function that calls log_to_meta api.
	 *
	 * @internal
	 *
	 * @param array $raw_context log context
	 * @since 3.5.0
	 */
	public function process_error_log( $raw_context ) {
		$context = self::prefill_log_context( $raw_context );
		facebook_for_woocommerce()->get_api()->log_to_meta( $context );
		WC_Facebookcommerce_Utils::logWithDebugModeEnabled( 'Request data: ' . wp_json_encode( $context ) );
	}

	/**
	 * Utility function for sending exception logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param Throwable $error error object
	 * @param array     $context context example: ['catalog_id' => '1234567890', 'order_id' => '1234567890',
	 *      'promotion_id' => '1234567890', 'flow_name' => 'checkout', 'flow_step' => 'verification',
	 *      'extra_data' => ['dictionary type' => 'any data that is not fall into our pre-defined format.']
	 */
	public static function log_expcetion_to_meta( Throwable $error, array $context = [] ) {
		$extra_data                = WC_Facebookcommerce_Utils::getContextData( $context, 'extra_data', [] );
		$extra_data['php_version'] = phpversion();

		$request_data = [
			'event'                           => 'error_log',
			'event_type'                      => WC_Facebookcommerce_Utils::getContextData( $context, 'event_type' ),
			'commerce_partner_integration_id' => WC_Facebookcommerce_Utils::getContextData( $context, 'commerce_partner_integration_id' ),
			'exception_message'               => $error->getMessage(),
			'exception_trace'                 => $error->getTraceAsString(),
			'exception_code'                  => $error->getCode(),
			'exception_class'                 => get_class( $error ),
			'order_id'                        => WC_Facebookcommerce_Utils::getContextData( $context, 'order_id' ),
			'promotion_id'                    => WC_Facebookcommerce_Utils::getContextData( $context, 'promotion_id' ),
			'flow_name'                       => WC_Facebookcommerce_Utils::getContextData( $context, 'flow_name' ),
			'flow_step'                       => WC_Facebookcommerce_Utils::getContextData( $context, 'flow_step' ),
			'incoming_params'                 => WC_Facebookcommerce_Utils::getContextData( $context, 'incoming_params' ),
			'seller_platform_app_version'     => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
			'extra_data'                      => $extra_data,
		];

		// Check if Action Scheduler is available
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'facebook_for_woocommerce_log_api', array( $request_data ) );
		} else {
			// Handle the absence of the Action Scheduler
			WC_Facebookcommerce_Utils::logWithDebugModeEnabled( 'Action Scheduler is not available.' );
		}
	}

	/**
	 * Prefill the log context with basic information.
	 *
	 * @since 3.5.0
	 *
	 * @param array $context log context
	 */
	public static function prefill_log_context( array $context ) {
		$request_data = [
			'commerce_merchant_settings_id' => facebook_for_woocommerce()->get_connection_handler()->get_commerce_merchant_settings_id(),
			'external_business_id'          => facebook_for_woocommerce()->get_connection_handler()->get_external_business_id(),
			'catalog_id'                    => facebook_for_woocommerce()->get_integration()->get_product_catalog_id(),
			'page_id'                       => facebook_for_woocommerce()->get_integration()->get_facebook_page_id(),
			'pixel_id'                      => facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
		];

		return array_merge( $request_data, $context );
	}
}
