<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
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
	/** @var string prefix for the transients backing the throttle log option */
	const THROTTLE_TRANSIENT_PREFIX = 'wc_facebook_log_throttle_';

	/**
	 * Centralised Logger function for the plugin
	 *
	 * @since 3.5.3
	 *
	 * @param string    $message log message
	 * @param array     $context optional body of log with whole context
	 * @param array     $log_options optional options for logging place and levels. Supports a
	 *                  'throttle' array to rate limit repetitive logs, see self::should_log()
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
		$log_to_woocommerce = 'yes' === get_option( self::SETTING_ENABLE_META_DIAGNOSIS )
			&& ! empty( $log_options['should_save_log_in_woocommerce'] );

		$log_to_meta = facebook_for_woocommerce()->get_integration()->is_meta_diagnosis_enabled()
			&& ! empty( $log_options['should_send_log_to_meta'] );

		// Both sinks are opt-in and off by default, so bail before consulting the throttle:
		// a suppressed log must not spend the caller's throttle budget, or the first clamp on
		// a store with diagnostics off would silence the hour (and the daily cap) for a log
		// nobody ever received.
		if ( ! $log_to_woocommerce && ! $log_to_meta ) {
			return;
		}

		if ( ! self::should_log( $log_options ) ) {
			return;
		}

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

		if ( $log_to_woocommerce ) {
			facebook_for_woocommerce()->log( $message . ' : ' . wp_json_encode( $context ), null, $log_options['woocommerce_log_level'] );
		}

		if ( $log_to_meta ) {
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

	/**
	 * Determines whether a log should be written, applying the optional 'throttle' log option.
	 *
	 * Some conditions repeat on every page view, or across every product in a catalogue, and
	 * would otherwise flood both the WooCommerce log and Meta. Callers opt in by passing a
	 * 'throttle' array in $log_options:
	 *
	 *     'throttle' => [
	 *         'key'         => 'quantity_clamp:123', // identifies this specific occurrence
	 *         'interval'    => HOUR_IN_SECONDS,      // min seconds between logs for that key
	 *         'group'       => 'quantity_clamp',     // optional, for the daily cap below
	 *         'max_per_day' => 10,                   // optional, cap across the whole group
	 *     ]
	 *
	 * The interval limits repeats of one occurrence; the group cap limits the total across
	 * related occurrences, so a caller iterating many distinct keys still cannot flood.
	 * Omitting 'throttle', or passing an empty 'key', logs unconditionally.
	 *
	 * Only reached once self::log() knows a sink will actually receive the message, so the
	 * budget tracks logs that were written rather than logs that were merely attempted.
	 *
	 * @since 3.7.7
	 *
	 * @param array $log_options the log options passed to self::log()
	 * @return bool whether the log should be written
	 */
	private static function should_log( $log_options ) {

		if ( empty( $log_options['throttle'] ) || ! is_array( $log_options['throttle'] ) ) {
			return true;
		}

		$throttle = array_merge(
			[
				'key'         => '',
				'interval'    => HOUR_IN_SECONDS,
				'group'       => '',
				'max_per_day' => 0,
			],
			$log_options['throttle']
		);

		if ( '' === (string) $throttle['key'] ) {
			return true;
		}

		$key_transient = self::THROTTLE_TRANSIENT_PREFIX . md5( (string) $throttle['key'] );

		// Already logged this occurrence within the interval.
		if ( get_transient( $key_transient ) ) {
			return false;
		}

		$group_transient = '';
		$group_count     = 0;

		if ( '' !== (string) $throttle['group'] && (int) $throttle['max_per_day'] > 0 ) {

			$group_transient = self::THROTTLE_TRANSIENT_PREFIX . 'group_' . md5( (string) $throttle['group'] );
			$group_count     = (int) get_transient( $group_transient );

			if ( $group_count >= (int) $throttle['max_per_day'] ) {
				return false;
			}
		}

		set_transient( $key_transient, 1, max( 1, (int) $throttle['interval'] ) );

		if ( '' !== $group_transient ) {
			set_transient( $group_transient, $group_count + 1, DAY_IN_SECONDS );
		}

		return true;
	}
}
