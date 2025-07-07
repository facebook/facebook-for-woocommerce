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

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Centralised Logger class for the plugin
 *
 * @since 3.5.3
 */
class Logger {

	/** @var string the "debug mode" setting ID */
	const SETTING_ENABLE_DEBUG_MODE = 'wc_facebook_enable_debug_mode';
	/** @var string the "meta diagnosis" setting ID */
	const SETTING_ENABLE_META_DIAGNOSIS = 'wc_facebook_enable_meta_diagnosis';
	/** @var string the message queue for this plugin handled in BatchLogHandler class */
	const LOGGING_MESSAGE_QUEUE = 'global_logging_message_queue';

	/**
	 * Centralised Logger function for the plugin
	 *
	 * @since 3.5.3
	 *
	 * @param string    $message log message
	 * @param array     $context optional body of log with whole context
	 * @param array     $log_options optional options for logging place and levels
	 * @param Throwable $exception error object
	 */
	public static function log(
		$message,
		$context = [],
		$log_options = [
			'should_send_log_to_meta'        => false,
			'should_save_log_in_woocommerce' => false,
			'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
		],
		?Throwable $exception = null
	) {
		if ( $exception ) {
			$exception_context = [
				'event'             => $context['event'] ?? 'error_log',
				'exception_message' => $exception->getMessage(),
				'exception_trace'   => $exception->getTraceAsString(),
				'exception_code'    => $exception->getCode(),
				'exception_class'   => get_class( $exception ),
			];
			$context           = array_merge( $exception_context, $context );
		}

		$is_debug_mode_enabled = 'yes' === get_option( self::SETTING_ENABLE_META_DIAGNOSIS );
		if ( $is_debug_mode_enabled && array_key_exists( 'should_save_log_in_woocommerce', $log_options ) && $log_options['should_save_log_in_woocommerce'] ) {
			facebook_for_woocommerce()->log( $message . ' : ' . wp_json_encode( $context ), null, $log_options['woocommerce_log_level'] );
		}

		$is_meta_diagnosis_enabled = facebook_for_woocommerce()->get_integration()->is_meta_diagnosis_enabled();
		if ( $is_meta_diagnosis_enabled && array_key_exists( 'should_send_log_to_meta', $log_options ) && $log_options['should_send_log_to_meta'] ) {
			$extra_data                = $context['extra_data'] ?? [];
			$extra_data['message']     = $message;
			$extra_data['php_version'] = phpversion();
			$context['extra_data']     = $extra_data;

			$logs = get_transient( self::LOGGING_MESSAGE_QUEUE );
			if ( ! $logs ) {
				$logs = [];
			}
			$logs[] = $context;
			set_transient( self::LOGGING_MESSAGE_QUEUE, $logs, HOUR_IN_SECONDS );
		}
	}
}
