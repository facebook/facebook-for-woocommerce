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
	 * Action Scheduler group used for Meta log API jobs.
	 */
	const META_LOG_API_GROUP = 'wc_facebook_log_api';

	/**
	 * Transient key used to pause crash reporting after Meta rate-limit responses.
	 */
	const CRASH_REPORTING_PAUSE_KEY = 'wc_facebook_crash_reporting_paused_until';

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
		if ( ! self::is_meta_diagnosis_enabled_for_reporting() ) {
			return;
		}

		if ( self::is_crash_reporting_paused() ) {
			self::store_crash_aggregate_only( $raw_context );
			return;
		}

		$context = self::set_core_log_context( $raw_context );
		try {
			$response = facebook_for_woocommerce()->get_api()->log_to_meta( $context );
			if ( ! $response->success ) {
				$status_code = is_object( $response ) && method_exists( $response, 'get_api_error_code' ) ? (int) $response->get_api_error_code() : 0;
				if ( 429 === $status_code ) {
					self::pause_crash_reporting();
					self::store_crash_aggregate_only( $context );
					return;
				}

				Logger::log(
					'Bad response from log_to_meta request',
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::log(
				'Error persisting error logs: ' . $e->getMessage(),
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
		}
	}

	/**
	 * Enqueues a Meta log API request through Action Scheduler.
	 *
	 * @since 3.6.4
	 *
	 * @param array $request_data normalized log payload.
	 * @param bool  $unique whether to enforce a unique async action.
	 * @return bool
	 */
	public static function enqueue_meta_log_request( array $request_data, $unique = true ) {
		if ( ! self::is_meta_diagnosis_enabled_for_reporting() ) {
			return false;
		}

		if ( self::is_crash_reporting_paused() ) {
			self::store_crash_aggregate_only( $request_data );
			return true;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		if ( $unique && function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::META_LOG_API, [ $request_data ], self::META_LOG_API_GROUP ) ) {
			return true;
		}

		try {
			$action_id = as_enqueue_async_action( self::META_LOG_API, [ $request_data ], self::META_LOG_API_GROUP, $unique );
			return ! empty( $action_id );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Checks whether Meta diagnosis reporting is enabled.
	 *
	 * Uses the existing integration opt-in and fails closed when unavailable.
	 *
	 * @since 3.6.4
	 *
	 * @return bool
	 */
	/**
	 * Checks whether crash reporting is currently paused due to Meta rate limits.
	 *
	 * @since 3.6.4
	 *
	 * @return bool
	 */
	public static function is_crash_reporting_paused() {
		$paused_until = (int) get_transient( self::CRASH_REPORTING_PAUSE_KEY );
		if ( $paused_until <= time() ) {
			if ( $paused_until > 0 ) {
				delete_transient( self::CRASH_REPORTING_PAUSE_KEY );
			}
			return false;
		}

		return true;
	}

	/**
	 * Pauses crash reporting for a short backoff window.
	 *
	 * @since 3.6.4
	 */
	private static function pause_crash_reporting() {
		$backoff_seconds = 15 * MINUTE_IN_SECONDS;
		set_transient( self::CRASH_REPORTING_PAUSE_KEY, time() + $backoff_seconds, $backoff_seconds );
	}

	/**
	 * Stores/updates local crash aggregate while reporting is paused.
	 *
	 * @since 3.6.4
	 *
	 * @param array $context crash payload.
	 */
	private static function store_crash_aggregate_only( array $context ) {
		$fingerprint = isset( $context['extra_data']['fingerprint'] ) ? (string) $context['extra_data']['fingerprint'] : 'default';
		$key         = 'wc_facebook_paused_crash_agg_' . $fingerprint;
		$current     = get_transient( $key );
		$now         = time();

		$count      = is_array( $current ) && isset( $current['count'] ) ? (int) $current['count'] : 0;
		$first_seen = is_array( $current ) && isset( $current['first_seen'] ) ? (int) $current['first_seen'] : $now;
		$message    = isset( $context['exception_message'] ) ? (string) $context['exception_message'] : '';
		if ( function_exists( 'mb_substr' ) ) {
			$message = mb_substr( $message, 0, 200 );
		} else {
			$message = substr( $message, 0, 200 );
		}

		set_transient(
			$key,
			[
				'count'       => $count + 1,
				'first_seen'  => $first_seen,
				'last_seen'   => $now,
				'last_sample' => [
					'event_type' => isset( $context['event_type'] ) ? (string) $context['event_type'] : '',
					'message'    => $message,
					'file'       => isset( $context['extra_data']['file'] ) ? (string) $context['extra_data']['file'] : '',
					'line'       => isset( $context['extra_data']['line'] ) ? (int) $context['extra_data']['line'] : 0,
				],
			],
			DAY_IN_SECONDS
		);
	}

	private static function is_meta_diagnosis_enabled_for_reporting() {
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			return false;
		}

		try {
			$plugin = facebook_for_woocommerce();

			if ( ! $plugin || ! method_exists( $plugin, 'get_integration' ) ) {
				return false;
			}

			$integration = $plugin->get_integration();
			return $integration && method_exists( $integration, 'is_meta_diagnosis_enabled' ) && $integration->is_meta_diagnosis_enabled();
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Utility function for sending exception logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param Throwable $error error object
	 * @param array     $context optional error message attributes
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

		if ( ! self::enqueue_meta_log_request( $request_data, true ) ) {
			Logger::log(
				'Action Scheduler is not available or enqueue failed.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
		}
	}
}
