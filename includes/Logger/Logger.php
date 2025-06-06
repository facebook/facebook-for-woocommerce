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

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Framework\ErrorLogHandler;
use Throwable;

/**
 * Centralised Logger
 *
 * @since 3.5.4
 */
class Logger extends LogHandlerBase {

		/** @var string */
	public static $ems = null;

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
		 * Gets a value from the context array, or a default if the key is not set.
		 *
		 * @param array  $context
		 * @param string $key
		 * @param mixed  $default_value
		 * @return mixed
		 */
	public static function get_context_data( array $context, string $key, $default_value = null ) {
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
}
