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
class ErrorLogHandler extends LogHandlerBase {

	/**
	 * Hook name for Meta Log API.
	 */
	const META_LOG_API = 'facebook_for_woocommerce_log_api';

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
		$context = self::set_core_log_context( $raw_context );
		try {
			$response = facebook_for_woocommerce()->get_api()->log_to_meta( $context );
			if ( $response->success ) {
				WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( 'Error log: ' . wp_json_encode( $context ), \WC_Log_Levels::ERROR );
			} else {
				WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( 'Bad response from log_to_meta request', \WC_Log_Levels::ERROR );
			}
		} catch ( \Exception $e ) {
			WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( 'Error persisting error logs: ' . $e->getMessage(), \WC_Log_Levels::ERROR );
		}
	}

	/**
	 * Utility function for sending exception logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param Throwable $error error object
	 * @param array     $context wiki: https://www.internalfb.com/wiki/Commerce_Platform/Teams/3P_Ecosystems_(3PE)/3rd_Party_platforms/Woo_Commerce/How_To_Use_WooCommerce_Side_Logging/
	 */
	public static function log_exception_to_meta( Throwable $error, array $context = [] ) {
		$extra_data                = WC_Facebookcommerce_Utils::get_context_data( $context, 'extra_data', [] );
		$extra_data['php_version'] = phpversion();

		$request_data = [
			'event'             => WC_Facebookcommerce_Utils::get_context_data( $context, 'event', 'error_log' ),
			'event_type'        => WC_Facebookcommerce_Utils::get_context_data( $context, 'event_type' ),
			'exception_message' => $error->getMessage(),
			'exception_trace'   => $error->getTraceAsString(),
			'exception_code'    => $error->getCode(),
			'exception_class'   => get_class( $error ),
			'order_id'          => WC_Facebookcommerce_Utils::get_context_data( $context, 'order_id' ),
			'promotion_id'      => WC_Facebookcommerce_Utils::get_context_data( $context, 'promotion_id' ),
			'incoming_params'   => WC_Facebookcommerce_Utils::get_context_data( $context, 'incoming_params' ),
			'extra_data'        => $extra_data,
		];

		// Check if Action Scheduler is available
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'facebook_for_woocommerce_log_api', array( $request_data ) );
		} else {
			// Handle the absence of the Action Scheduler
			WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( 'Action Scheduler is not available.' );
		}
	}
}
