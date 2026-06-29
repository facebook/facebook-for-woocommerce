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
use WooCommerce\Facebook\API as Facebook_API;
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
	 * Transient key prefix for paused-period crash aggregates.
	 */
	const PAUSED_CRASH_AGGREGATE_KEY_PREFIX = 'wc_facebook_paused_crash_agg_';

	/**
	 * Transient key for paused-period aggregate fingerprint index.
	 */
	const PAUSED_CRASH_AGGREGATE_INDEX_KEY = 'wc_facebook_paused_crash_agg_index';

	/**
	 * Hook used to replay paused crash aggregates after backoff.
	 */
	const REPLAY_PAUSED_CRASH_AGGREGATES_HOOK = 'facebook_for_woocommerce_replay_paused_crash_aggregates';

	/**
	 * Max paused crash aggregate fingerprints to retain locally.
	 */
	const PAUSED_CRASH_MAX_DISTINCT_FINGERPRINTS = 100;

	/**
	 * Constructs a new ErrorLog handler.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		add_action( self::META_LOG_API, array( $this, 'process_error_log' ), 10, 1 );
		add_action( self::REPLAY_PAUSED_CRASH_AGGREGATES_HOOK, array( __CLASS__, 'maybe_replay_paused_crash_aggregates' ) );
	}

	/**
	 * Sends one log payload to Meta.
	 *
	 * @internal
	 *
	 * @param array $raw_context log context.
	 * @since 3.5.0
	 */
	public function process_error_log( $raw_context ) {
		if ( ! self::is_meta_diagnosis_enabled_for_reporting() ) {
			self::release_crash_queue_lock( $raw_context );
			return;
		}

		$is_crash_event = self::is_plugin_crash_event( $raw_context );
		if ( $is_crash_event ) {
			self::maybe_replay_paused_crash_aggregates();

			if ( self::is_crash_reporting_paused() ) {
				self::store_crash_aggregate_only( $raw_context );
				self::release_crash_queue_lock( $raw_context );
				return;
			}
		}

		$context = self::set_core_log_context( $raw_context );
		try {
			$response   = self::send_log_to_meta_request( $context );
			$is_success = is_object( $response ) && isset( $response->success ) && $response->success;
			if ( ! $is_success ) {
				$status_code = self::extract_meta_error_status_code( $response );
				if ( 429 === $status_code && $is_crash_event ) {
					self::pause_crash_reporting();
					self::store_crash_aggregate_only( $context );
					self::release_crash_queue_lock( $raw_context );
					return;
				}

				self::log_local_error( 'Bad response from log_to_meta request' );
				self::release_crash_queue_lock( $raw_context );
				return;
			}

			self::release_crash_queue_lock( $raw_context );
		} catch ( Throwable $e ) {
			self::release_crash_queue_lock( $raw_context );
			self::log_local_error( 'Error persisting error logs: ' . $e->getMessage() );
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

		$is_crash_event = self::is_plugin_crash_event( $request_data );
		if ( $is_crash_event ) {
			self::maybe_replay_paused_crash_aggregates();

			if ( self::is_crash_reporting_paused() ) {
				self::store_crash_aggregate_only( $request_data );
				return true;
			}
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
	 * Replays paused crash aggregates once pause window has expired.
	 *
	 * @since 3.6.4
	 */
	public static function maybe_replay_paused_crash_aggregates() {
		if ( self::is_crash_reporting_paused() ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$index = get_transient( self::PAUSED_CRASH_AGGREGATE_INDEX_KEY );
		if ( ! is_array( $index ) || empty( $index ) ) {
			return;
		}

		foreach ( array_keys( $index ) as $fingerprint ) {
			$fingerprint = (string) $fingerprint;
			if ( '' === $fingerprint ) {
				continue;
			}

			$key       = self::PAUSED_CRASH_AGGREGATE_KEY_PREFIX . $fingerprint;
			$aggregate = get_transient( $key );
			if ( ! is_array( $aggregate ) ) {
				unset( $index[ $fingerprint ] );
				continue;
			}

			$last_sample  = isset( $aggregate['last_sample'] ) && is_array( $aggregate['last_sample'] ) ? $aggregate['last_sample'] : [];
			$request_data = [
				'event'             => 'plugin_crash',
				'event_type'        => isset( $last_sample['event_type'] ) ? (string) $last_sample['event_type'] : 'fatal_error',
				'exception_message' => isset( $last_sample['message'] ) ? (string) $last_sample['message'] : 'Crash replayed after pause window',
				'extra_data'        => [
					'fingerprint'           => $fingerprint,
					'file'                  => isset( $last_sample['file'] ) ? (string) $last_sample['file'] : '',
					'line'                  => isset( $last_sample['line'] ) ? (int) $last_sample['line'] : 0,
					'aggregate_count'       => isset( $aggregate['count'] ) ? (int) $aggregate['count'] : 1,
					'aggregate_first_seen'  => isset( $aggregate['first_seen'] ) ? (int) $aggregate['first_seen'] : time(),
					'aggregate_last_seen'   => isset( $aggregate['last_seen'] ) ? (int) $aggregate['last_seen'] : time(),
					'aggregate_last_sample' => $last_sample,
					'replayed_after_pause'  => true,
				],
			];

			$enqueued = false;

			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::META_LOG_API, [ $request_data ], self::META_LOG_API_GROUP ) ) {
				$enqueued = true;
			} else {
				try {
					$action_id = as_enqueue_async_action( self::META_LOG_API, [ $request_data ], self::META_LOG_API_GROUP, true );
					$enqueued  = ! empty( $action_id );
				} catch ( Throwable $e ) {
					$enqueued = false;
				}
			}

			if ( $enqueued ) {
				delete_transient( $key );
				unset( $index[ $fingerprint ] );
			} else {
				error_log( '[FBW_CRASH_OBS] paused replay enqueue failed: reason=replay_enqueue_failed fingerprint=' . $fingerprint . ' aggregate_count=' . (int) ( $aggregate['count'] ?? 0 ) . ' index_size=' . (int) count( $index ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		set_transient( self::PAUSED_CRASH_AGGREGATE_INDEX_KEY, $index, DAY_IN_SECONDS );
	}

	/**
	 * Pauses crash reporting for a short backoff window.
	 *
	 * @since 3.6.4
	 */
	private static function pause_crash_reporting() {
		$backoff_seconds = 15 * MINUTE_IN_SECONDS;
		$resume_at       = time() + $backoff_seconds;

		set_transient( self::CRASH_REPORTING_PAUSE_KEY, $resume_at, $backoff_seconds );

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$hook      = self::REPLAY_PAUSED_CRASH_AGGREGATES_HOOK;
		$group     = self::META_LOG_API_GROUP;
		$timestamp = $resume_at + MINUTE_IN_SECONDS;

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, [], $group ) ) {
			return;
		}

		as_schedule_single_action( $timestamp, $hook, [], $group );
	}

	/**
	 * Checks whether payload is a plugin crash event.
	 *
	 * @since 3.6.4
	 *
	 * @param array $context log payload.
	 * @return bool
	 */
	private static function is_plugin_crash_event( array $context ) {
		return isset( $context['event'] ) && 'plugin_crash' === (string) $context['event'];
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
		$key         = self::PAUSED_CRASH_AGGREGATE_KEY_PREFIX . $fingerprint;
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

		$index = get_transient( self::PAUSED_CRASH_AGGREGATE_INDEX_KEY );
		if ( ! is_array( $index ) ) {
			$index = [];
		}

		$index[ $fingerprint ] = [
			'count'     => $count + 1,
			'last_seen' => $now,
		];

		if ( count( $index ) > self::PAUSED_CRASH_MAX_DISTINCT_FINGERPRINTS ) {
			uasort(
				$index,
				static function ( $a, $b ) {
					$a_last_seen = isset( $a['last_seen'] ) ? (int) $a['last_seen'] : 0;
					$b_last_seen = isset( $b['last_seen'] ) ? (int) $b['last_seen'] : 0;
					if ( $a_last_seen === $b_last_seen ) {
						return 0;
					}
					return ( $a_last_seen > $b_last_seen ) ? -1 : 1;
				}
			);

			$trimmed = array_slice( $index, self::PAUSED_CRASH_MAX_DISTINCT_FINGERPRINTS, null, true );
			$index   = array_slice( $index, 0, self::PAUSED_CRASH_MAX_DISTINCT_FINGERPRINTS, true );

			foreach ( array_keys( $trimmed ) as $trimmed_fingerprint ) {
				delete_transient( self::PAUSED_CRASH_AGGREGATE_KEY_PREFIX . (string) $trimmed_fingerprint );
			}
		}

		set_transient( self::PAUSED_CRASH_AGGREGATE_INDEX_KEY, $index, DAY_IN_SECONDS );
	}

	/**
	 * Releases per-fingerprint crash queue lock.
	 *
	 * @since 3.6.4
	 *
	 * @param array $context crash payload.
	 */
	private static function release_crash_queue_lock( array $context ) {
		$fingerprint = isset( $context['extra_data']['fingerprint'] ) ? (string) $context['extra_data']['fingerprint'] : '';
		if ( '' === $fingerprint ) {
			return;
		}

		delete_transient( PluginCrashHandler::CRASH_QUEUE_LOCK_PREFIX . $fingerprint );
	}

	/**
	 * Sends one Meta log request, including disabled-mode fallback when plugin instance is unavailable.
	 *
	 * @since 3.6.4
	 *
	 * @param array $context normalized log payload.
	 * @return object|null
	 */
	private static function send_log_to_meta_request( array $context ) {
		if ( function_exists( 'facebook_for_woocommerce' ) ) {
			try {
				$plugin = facebook_for_woocommerce();
				if ( $plugin && method_exists( $plugin, 'get_api' ) ) {
					$api = $plugin->get_api();
					if ( $api && method_exists( $api, 'log_to_meta' ) ) {
						return $api->log_to_meta( $context );
					}
				}
			} catch ( Throwable $e ) {
				unset( $e );
				// Fall back to direct API client below.
			}
		}

		$access_token = self::get_reporting_access_token();
		if ( '' === $access_token ) {
			return null;
		}

		try {
			$api = new Facebook_API( $access_token );
			return $api->log_to_meta( $context );
		} catch ( Throwable $e ) {
			unset( $e );
			return null;
		}
	}

	/**
	 * Extracts Meta API status code from a response object.
	 *
	 * @since 3.6.4
	 *
	 * @param mixed $response API response object.
	 * @return int
	 */
	private static function extract_meta_error_status_code( $response ) {
		if ( is_object( $response ) && method_exists( $response, 'get_api_error_code' ) ) {
			return (int) $response->get_api_error_code();
		}

		return 0;
	}

	/**
	 * Logs local diagnostics without relying on a fully initialized plugin instance.
	 *
	 * @since 3.6.4
	 *
	 * @param string $message message to log.
	 */
	private static function log_local_error( $message ) {
		if ( function_exists( 'facebook_for_woocommerce' ) ) {
			try {
				Logger::log(
					(string) $message,
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					)
				);
				return;
			} catch ( Throwable $e ) {
				unset( $e );
				// Fall back to PHP error_log.
			}
		}

		error_log( 'Meta for WooCommerce crash reporting: ' . (string) $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Gets access token for crash/reporting calls in disabled mode.
	 *
	 * @since 3.6.4
	 *
	 * @return string
	 */
	private static function get_reporting_access_token() {
		$option_keys = [
			'wc_facebook_access_token',
			'wc_facebook_merchant_access_token',
		];

		foreach ( $option_keys as $option_key ) {
			$token = get_option( $option_key, '' );
			if ( is_string( $token ) && '' !== trim( $token ) ) {
				return trim( $token );
			}
		}

		return '';
	}

	/**
	 * Checks whether Meta diagnosis reporting is enabled.
	 *
	 * Uses integration opt-in when available, with option fallback for disabled mode.
	 *
	 * @since 3.6.4
	 *
	 * @return bool
	 */
	private static function is_meta_diagnosis_enabled_for_reporting() {
		$option_enabled = 'yes' === get_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'no' );

		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			return $option_enabled;
		}

		try {
			$plugin = facebook_for_woocommerce();

			if ( $plugin && method_exists( $plugin, 'get_integration' ) ) {
				$integration = $plugin->get_integration();
				if ( $integration && method_exists( $integration, 'is_meta_diagnosis_enabled' ) ) {
					return (bool) $integration->is_meta_diagnosis_enabled();
				}
			}
		} catch ( Throwable $e ) {
			unset( $e );
			// Use option fallback below.
		}

		return $option_enabled;
	}

	/**
	 * Utility method for sending exception logs to Meta.
	 *
	 * @since 3.5.0
	 *
	 * @param Throwable $error error object.
	 * @param array     $context optional error message attributes.
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
