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

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Framework\ErrorLogHandler;
use Throwable;

/**
 * Centralised Logger
 *
 * @since 3.5.4
 */
class Logger {

	const PLUGIN_VERSION = \WC_Facebookcommerce::VERSION;
	/** @var string the "debug mode" setting ID */
	const SETTING_ENABLE_DEBUG_MODE = 'wc_facebook_enable_debug_mode';
	/** @var string the "meta diagnosis" setting ID */
	const SETTING_ENABLE_META_DIAGNOSIS = 'wc_facebook_enable_meta_diagnosis';


	/**
	 * Utility function for sending exception logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param string    $message
	 * @param Throwable $error optional error object
	 * @param array     $context optional context array
	 * @param bool      $send_log_to_meta optional
	 * @param bool      $save_log_in_woocommerce optional
	 * @param string    $level optional log level represents log's tag
	 */
	public static function log( ?Throwable $error, array $context = [], $should_send_log_to_meta = false, $should_save_log_in_woocommerce = false, $woocommerce_log_message = null, $woocommerce_log_level = null ) {
		$is_debug_mode_enabled = 'yes' === get_option( self::SETTING_ENABLE_META_DIAGNOSIS );
		if ( $should_save_log_in_woocommerce && $is_debug_mode_enabled ) {
			facebook_for_woocommerce()->log( $woocommerce_log_message, null, $woocommerce_log_level );
		}

		$is_meta_diagnosis_enabled = (bool) ( 'yes' === get_option( self::SETTING_ENABLE_META_DIAGNOSIS ) );
		if ( $should_send_log_to_meta && $is_meta_diagnosis_enabled ) {
			if ( $error ) {
				$error_data = [
					'exception_message' => $error->getMessage(),
					'exception_trace'   => $error->getTraceAsString(),
					'exception_code'    => $error->getCode(),
					'exception_class'   => get_class( $error ),
				];
			}

			$log_data = array_merge( $context, $error_data );

			$logs = get_transient( 'global_logging_message_queue' );
			if ( ! $logs ) {
				$logs = [];
			}
			$logs[] = $log_data;
			set_transient( 'global_logging_message_queue', $logs, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Gets a value from the context array, or a default if the key is not set.
	 *
	 * @param array  $context
	 * @param string $key
	 * @param mixed  $default_value
	 * @return mixed
	 */
	private static function get_context_data( array $context, string $key, $default_value = null ) {
		return $context[ $key ] ?? $default_value;
	}

	/**
	 * Saves errors or messages to WooCommerce (WP admin page:WooCommerce->Status).
	 *
	 * Only logs if debug mode is enabled and WP_DEBUG and WP_DEBUG_LOG are true in wp-config.php.
	 *
	 * @param string $message
	 * @param string $level
	 */
	public static function log_with_debug_mode_enabled( $message, $level = null ) {
		// if this file is being included outside the plugin, or the plugin setting is disabled
		if ( ! function_exists( 'facebook_for_woocommerce' ) || ! facebook_for_woocommerce()->get_integration()->is_debug_mode_enabled() ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = wp_json_encode( $message );
		} else {
			$message = sanitize_textarea_field( $message );
		}

		facebook_for_woocommerce()->log( $message, null, $level );
	}



	/**
	 * Utility function for sending exception logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param Throwable $error error object
	 * @param array     $context wiki: https://www.internalfb.com/wiki/Commerce_Platform/Teams/3P_Ecosystems_(3PE)/3rd_Party_platforms/Woo_Commerce/How_To_Use_WooCommerce_Side_Logging/
	 */
	public static function log_exception_immediately_to_meta( Throwable $error, array $context = [] ) {
		ErrorLogHandler::log_exception_to_meta( $error, $context );
	}

	/**
	 * Utility function for sending logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message
	 * @param array  $context wiki: https://www.internalfb.com/wiki/Commerce_Platform/Teams/3P_Ecosystems_(3PE)/3rd_Party_platforms/Woo_Commerce/How_To_Use_WooCommerce_Side_Logging/
	 */
	public static function log_to_meta( string $message, array $context = [] ) {
		$extra_data            = self::get_context_data( $context, 'extra_data', [] );
		$extra_data['message'] = $message;
		$context['extra_data'] = $extra_data;

		// Push logging request to global message queue function.
		$logs = get_transient( 'global_logging_message_queue' );
		if ( ! $logs ) {
			$logs = [];
		}
		$logs[] = $context;
		set_transient( 'global_logging_message_queue', $logs, HOUR_IN_SECONDS );
	}

	/**
	 * Utility function for development logging.
	 *
	 * @param string $message
	 * @param array  $obj
	 * @param bool   $error
	 * @param string $ems
	 */
	public static function fblog(
		$message,
		$obj = [],
		$error = false,
		$ems = ''
	) {
		if ( $error ) {
			$obj['plugin_version'] = self::PLUGIN_VERSION;
			$obj['php_version']    = phpversion();
		}
		$message = wp_json_encode(
			array(
				'message' => $message,
				'object'  => $obj,
			)
		);

		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$ems = $ems ?: self::$ems;
		if ( $ems ) {
			try {
				facebook_for_woocommerce()->get_api()->log( $ems, $message, $error );
			} catch ( ApiException $e ) {
				$message = sprintf( 'There was an error trying to log: %s', $e->getMessage() );
				facebook_for_woocommerce()->log( $message );
			}
		} else {
			error_log(
				'external merchant setting is null, something wrong here: ' .
				$message
			);
		}
	}

	/**
	 * Utility function for development Tip Events logging.
	 *
	 * @param string $tip_id
	 * @param string $channel_id
	 * @param string $event
	 * @param string $ems
	 */
	public static function tip_events_log( $tip_id, $channel_id, $event, $ems = '' ) {
		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$ems = $ems ?: self::$ems;
		if ( $ems ) {
			try {
				facebook_for_woocommerce()->get_api()->log_tip_event( $tip_id, $channel_id, $event );
			} catch ( ApiException $e ) {
				$message = sprintf( 'There was an error while logging tip events: %s', $e->getMessage() );
				facebook_for_woocommerce()->log( $message );
			}
		} else {
			error_log( 'external merchant setting is null' );
		}
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
				self::log_with_debug_mode_enabled( 'Error log: ' . wp_json_encode( $context ), \WC_Log_Levels::ERROR );
			} else {
				self::log_with_debug_mode_enabled( 'Bad response from log_to_meta request', \WC_Log_Levels::ERROR );
			}
		} catch ( \Exception $e ) {
			self::log_with_debug_mode_enabled( 'Error persisting error logs: ' . $e->getMessage(), \WC_Log_Levels::ERROR );
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
		$extra_data                = self::get_context_data( $context, 'extra_data', [] );
		$extra_data['php_version'] = phpversion();

		$request_data = [
			'event'             => self::get_context_data( $context, 'event', 'error_log' ),
			'event_type'        => self::get_context_data( $context, 'event_type' ),
			'exception_message' => $error->getMessage(),
			'exception_trace'   => $error->getTraceAsString(),
			'exception_code'    => $error->getCode(),
			'exception_class'   => get_class( $error ),
			'order_id'          => self::get_context_data( $context, 'order_id' ),
			'promotion_id'      => self::get_context_data( $context, 'promotion_id' ),
			'incoming_params'   => self::get_context_data( $context, 'incoming_params' ),
			'extra_data'        => $extra_data,
		];

		// Check if Action Scheduler is available
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'facebook_for_woocommerce_log_api', array( $request_data ) );
		} else {
			// Handle the absence of the Action Scheduler
			self::log_with_debug_mode_enabled( 'Action Scheduler is not available.' );
		}
	}

	/**
	 * Prefill the log context with basic information.
	 *
	 * @since 3.5.0
	 *
	 * @param array $context log context
	 */
	public static function set_core_log_context( array $context ) {
		$request_data = [
			'commerce_merchant_settings_id'   => facebook_for_woocommerce()->get_connection_handler()->get_commerce_merchant_settings_id(),
			'commerce_partner_integration_id' => facebook_for_woocommerce()->get_connection_handler()->get_commerce_partner_integration_id(),
			'external_business_id'            => facebook_for_woocommerce()->get_connection_handler()->get_external_business_id(),
			'catalog_id'                      => facebook_for_woocommerce()->get_integration()->get_product_catalog_id(),
			'page_id'                         => facebook_for_woocommerce()->get_integration()->get_facebook_page_id(),
			'pixel_id'                        => facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
			'seller_platform_app_version'     => self::PLUGIN_VERSION,
		];

		return array_merge( $request_data, $context );
	}
}
