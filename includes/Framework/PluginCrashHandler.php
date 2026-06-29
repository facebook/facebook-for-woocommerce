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
 * Handles plugin fatal crashes on PHP shutdown.
 *
 * Known limitation: PHP memory-exhaustion fatals may leave too little memory for
 * the shutdown handler to run. In that case disable-flag writes and crash queueing
 * may be skipped for that request.
 *
 * @since 3.6.4
 */
class PluginCrashHandler {

	/**
	 * Cache group for crash report deduplication locks.
	 */
	const CRASH_REPORT_CACHE_GROUP = 'wc_facebook_crash_reports';

	/**
	 * Transient key for crash report rate limiting.
	 */
	const CRASH_RATE_LIMIT_KEY = 'wc_facebook_crash_rate_limit';

	/**
	 * Transient key prefix for per-fingerprint crash aggregates.
	 */
	const CRASH_AGGREGATE_KEY_PREFIX = 'wc_facebook_crash_agg_';

	/**
	 * Transient key for aggregate fingerprint index.
	 */
	const CRASH_AGGREGATE_INDEX_KEY = 'wc_facebook_crash_agg_index';

	/**
	 * Transient key prefix for per-fingerprint queue locks.
	 */
	const CRASH_QUEUE_LOCK_PREFIX = 'wc_facebook_crash_queue_lock_';

	/**
	 * Max distinct crash fingerprints to retain in local aggregates.
	 */
	const CRASH_MAX_DISTINCT_FINGERPRINTS = 100;

	/**
	 * Soft cap for queued crash reports in Action Scheduler.
	 */
	const CRASH_MAX_QUEUED_REPORTS = 50;

	/**
	 * Max payload size for queued crash report data.
	 */
	const CRASH_MAX_PAYLOAD_BYTES = 16000;

	/**
	 * Max length of aggregate last sample message.
	 */
	const CRASH_MAX_SAMPLE_MESSAGE_LENGTH = 200;

	/**
	 * Max random delay before queued crash reports are sent.
	 */
	const CRASH_REPORT_MAX_JITTER_SECONDS = 3600;

	/**
	 * Max number of crash reports allowed in the rate-limit window.
	 */
	const CRASH_RATE_LIMIT_MAX = 10;

	/**
	 * Rate-limit window length in seconds.
	 */
	const CRASH_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Previously registered exception handler.
	 *
	 * @var callable|null
	 */
	private $previous_exception_handler;

	/**
	 * Last uncaught throwable normalized into an error-like payload.
	 *
	 * @var array|null
	 */
	private $captured_throwable_error;

	/**
	 * Whether crash hooks were already registered for this request.
	 *
	 * @var bool
	 */
	private static $is_registered = false;

	/**
	 * Registers crash handling hooks.
	 *
	 * @since 3.6.4
	 */
	public function register() {
		if ( self::$is_registered ) {
			return;
		}

		$this->previous_exception_handler = set_exception_handler( [ $this, 'handle_uncaught_exception' ] );
		register_shutdown_function( [ $this, 'handle_shutdown' ] );
		self::$is_registered = true;
	}

	/**
	 * Captures uncaught throwables so shutdown handling can process them.
	 *
	 * @since 3.6.4
	 *
	 * @param Throwable $throwable uncaught throwable.
	 */
	public function handle_uncaught_exception( Throwable $throwable ) {
		$this->captured_throwable_error = $this->normalize_throwable_to_error( $throwable );

		if ( is_callable( $this->previous_exception_handler ) ) {
			call_user_func( $this->previous_exception_handler, $throwable );
		}
	}

	/**
	 * Captures fatal plugin crashes on shutdown.
	 *
	 * @since 3.6.4
	 */
	public function handle_shutdown() {
		$error = $this->normalize_captured_error( error_get_last(), 'fatal_error' );

		if ( ! $this->is_supported_fatal_error( $error ) ) {
			$error = $this->normalize_captured_error( $this->captured_throwable_error, 'uncaught_exception' );
		}

		if ( ! $this->is_supported_fatal_error( $error ) ) {
			return;
		}

		if ( ! $this->is_plugin_error( $error ) ) {
			return;
		}

		$this->write_disable_flag();

		try {
			$normalized_report = $this->normalize_crash_report_payload( $error );
			$fingerprint       = $this->generate_crash_fingerprint( $normalized_report );
			$aggregate         = $this->increment_crash_aggregate( $fingerprint, $normalized_report );
			$report_to_queue   = $this->attach_aggregate_to_report( $normalized_report, $aggregate, $fingerprint );
			$report_to_queue   = $this->apply_payload_size_guard( $report_to_queue );
		} catch ( Throwable $e ) {
			unset( $e );
			return;
		}

		if ( ErrorLogHandler::is_crash_reporting_paused() ) {
			return;
		}

		$dedup_lock_storage = null;
		if ( ! $this->should_queue_crash_report( $normalized_report, $dedup_lock_storage ) ) {
			$this->increment_duplicate_crash_counter( $normalized_report );
			return;
		}

		if ( $this->should_rate_limit_crash_report() ) {
			$this->increment_rate_limited_counter();
			$this->log_crash_observability( '[FBW_DEDUP_DEBUG] report skipped before enqueue: reason=rate_limited fingerprint=' . $fingerprint );
			$this->release_crash_dedup_lock( $normalized_report, 'rate_limited', $dedup_lock_storage );
			return;
		}

		$queue_size = 0;
		if ( ! $this->can_queue_more_crash_reports( $queue_size ) ) {
			if ( ! $this->maybe_trim_queue_for_prioritized_report( $report_to_queue, $queue_size ) ) {
				$this->log_crash_observability( '[FBW_CRASH_OBS] crash report dropped: reason=queue_cap fingerprint=' . $fingerprint . ' aggregate_count=' . (int) ( $report_to_queue['extra_data']['aggregate_count'] ?? 0 ) . ' queue_size=' . (int) $queue_size . ' queue_max=' . (int) self::CRASH_MAX_QUEUED_REPORTS );
				$this->log_crash_observability( '[FBW_DEDUP_DEBUG] report dropped before enqueue: reason=queue_cap fingerprint=' . $fingerprint );
				$this->release_crash_dedup_lock( $normalized_report, 'queue_cap', $dedup_lock_storage );
				return;
			}
		}

		if ( $this->has_pending_crash_queue_lock( $fingerprint ) ) {
			$this->log_crash_observability( '[FBW_DEDUP_DEBUG] report skipped before enqueue: reason=pending_queue_lock fingerprint=' . $fingerprint );
			$this->release_crash_dedup_lock( $normalized_report, 'pending_queue_lock', $dedup_lock_storage );
			return;
		}

		if ( ! $this->queue_crash_report( $report_to_queue ) ) {
			$this->log_crash_observability( '[FBW_DEDUP_DEBUG] report dropped before enqueue: reason=enqueue_failed fingerprint=' . $fingerprint );
			$this->release_crash_dedup_lock( $normalized_report, 'enqueue_failed', $dedup_lock_storage );
			$this->log_fallback( $report_to_queue );
			return;
		}

		$this->set_pending_crash_queue_lock( $fingerprint );
	}

	/**
	 * Checks if a crash report is already queued for this fingerprint.
	 *
	 * @since 3.6.4
	 *
	 * @param string $fingerprint crash fingerprint.
	 * @return bool
	 */
	private function has_pending_crash_queue_lock( $fingerprint ) {
		if ( '' === $fingerprint ) {
			return false;
		}

		return false !== $this->safe_get_transient( self::CRASH_QUEUE_LOCK_PREFIX . $fingerprint );
	}

	/**
	 * Sets a per-fingerprint queue lock after successful enqueue.
	 *
	 * @since 3.6.4
	 *
	 * @param string $fingerprint crash fingerprint.
	 */
	private function set_pending_crash_queue_lock( $fingerprint ) {
		if ( '' === $fingerprint ) {
			return;
		}

		$this->safe_set_transient( self::CRASH_QUEUE_LOCK_PREFIX . $fingerprint, 1, HOUR_IN_SECONDS );
	}

	/**
	 * Increments local aggregate for a crash fingerprint.
	 *
	 * @since 3.6.4
	 *
	 * @param string $fingerprint crash fingerprint.
	 * @param array  $report normalized crash report payload.
	 * @return array
	 */
	private function increment_crash_aggregate( $fingerprint, array $report ) {
		$key            = $this->get_crash_aggregate_key( $fingerprint );
		$current        = $this->safe_get_transient( $key );
		$now            = time();
		$count          = is_array( $current ) && isset( $current['count'] ) ? (int) $current['count'] : 0;
		$first          = is_array( $current ) && isset( $current['first_seen'] ) ? (int) $current['first_seen'] : $now;
		$sample_message = isset( $report['exception_message'] ) ? (string) $report['exception_message'] : '';
		if ( function_exists( 'mb_substr' ) ) {
			$sample_message = mb_substr( $sample_message, 0, self::CRASH_MAX_SAMPLE_MESSAGE_LENGTH );
		} else {
			$sample_message = substr( $sample_message, 0, self::CRASH_MAX_SAMPLE_MESSAGE_LENGTH );
		}

		$sample = [
			'event_type' => isset( $report['event_type'] ) ? (string) $report['event_type'] : '',
			'message'    => $sample_message,
			'file'       => isset( $report['extra_data']['file'] ) ? (string) $report['extra_data']['file'] : '',
			'line'       => isset( $report['extra_data']['line'] ) ? (int) $report['extra_data']['line'] : 0,
		];

		$aggregate = [
			'count'       => $count + 1,
			'first_seen'  => $first,
			'last_seen'   => $now,
			'last_sample' => $sample,
		];

		$this->safe_set_transient( $key, $aggregate, DAY_IN_SECONDS );
		$this->update_crash_aggregate_index( $fingerprint, (int) $aggregate['count'], (int) $aggregate['last_seen'] );

		return $aggregate;
	}

	/**
	 * Updates aggregate index and trims less frequent fingerprints when needed.
	 *
	 * @since 3.6.4
	 *
	 * @param string $fingerprint fingerprint key.
	 * @param int    $count aggregate count.
	 * @param int    $last_seen unix timestamp.
	 */
	private function update_crash_aggregate_index( $fingerprint, $count, $last_seen ) {
		$index = $this->safe_get_transient( self::CRASH_AGGREGATE_INDEX_KEY );
		if ( ! is_array( $index ) ) {
			$index = [];
		}

		$index[ $fingerprint ] = [
			'count'     => (int) $count,
			'last_seen' => (int) $last_seen,
		];

		if ( count( $index ) > self::CRASH_MAX_DISTINCT_FINGERPRINTS ) {
			uasort(
				$index,
				function ( $a, $b ) {
					$count_cmp = ( $b['count'] ?? 0 ) <=> ( $a['count'] ?? 0 );
					if ( 0 !== $count_cmp ) {
						return $count_cmp;
					}
					return ( $b['last_seen'] ?? 0 ) <=> ( $a['last_seen'] ?? 0 );
				}
			);

			$index = array_slice( $index, 0, self::CRASH_MAX_DISTINCT_FINGERPRINTS, true );

			$kept = array_fill_keys( array_keys( $index ), true );
			foreach ( $this->get_all_known_aggregate_fingerprints() as $known_fingerprint ) {
				if ( isset( $kept[ $known_fingerprint ] ) ) {
					continue;
				}
				$this->safe_delete_transient( $this->get_crash_aggregate_key( $known_fingerprint ) );
			}
		}

		$this->safe_set_transient( self::CRASH_AGGREGATE_INDEX_KEY, $index, DAY_IN_SECONDS );
	}

	/**
	 * Gets all fingerprints tracked in the aggregate index.
	 *
	 * @since 3.6.4
	 *
	 * @return array
	 */
	private function get_all_known_aggregate_fingerprints() {
		$index = $this->safe_get_transient( self::CRASH_AGGREGATE_INDEX_KEY );
		return is_array( $index ) ? array_keys( $index ) : [];
	}

	/**
	 * Attaches aggregate metadata to a report payload.
	 *
	 * @since 3.6.4
	 *
	 * @param array  $report normalized report.
	 * @param array  $aggregate aggregate payload.
	 * @param string $fingerprint crash fingerprint.
	 * @return array
	 */
	private function attach_aggregate_to_report( array $report, array $aggregate, $fingerprint ) {
		if ( ! isset( $report['extra_data'] ) || ! is_array( $report['extra_data'] ) ) {
			$report['extra_data'] = [];
		}

		$report['extra_data']['aggregate_count']       = isset( $aggregate['count'] ) ? (int) $aggregate['count'] : 1;
		$report['extra_data']['aggregate_first_seen']  = isset( $aggregate['first_seen'] ) ? (int) $aggregate['first_seen'] : time();
		$report['extra_data']['aggregate_last_seen']   = isset( $aggregate['last_seen'] ) ? (int) $aggregate['last_seen'] : time();
		$report['extra_data']['aggregate_last_sample'] = isset( $aggregate['last_sample'] ) && is_array( $aggregate['last_sample'] ) ? $aggregate['last_sample'] : [];
		$report['extra_data']['fingerprint']           = (string) $fingerprint;

		return $report;
	}

	/**
	 * Clears local aggregate for a crash fingerprint after successful queue.
	 *
	 * @since 3.6.4
	 *
	 * @param string $fingerprint crash fingerprint.
	 */
	private function clear_crash_aggregate( $fingerprint ) {
		$this->safe_delete_transient( $this->get_crash_aggregate_key( $fingerprint ) );

		$index = $this->safe_get_transient( self::CRASH_AGGREGATE_INDEX_KEY );
		if ( is_array( $index ) && isset( $index[ $fingerprint ] ) ) {
			unset( $index[ $fingerprint ] );
			$this->safe_set_transient( self::CRASH_AGGREGATE_INDEX_KEY, $index, DAY_IN_SECONDS );
		}
	}

	/**
	 * Builds aggregate transient key for a fingerprint.
	 *
	 * @since 3.6.4
	 *
	 * @param string $fingerprint crash fingerprint.
	 * @return string
	 */
	private function get_crash_aggregate_key( $fingerprint ) {
		return self::CRASH_AGGREGATE_KEY_PREFIX . ( '' !== $fingerprint ? $fingerprint : 'default' );
	}

	/**
	 * Applies a lightweight payload-size guard before queueing.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report normalized crash report payload.
	 * @return array
	 */
	private function apply_payload_size_guard( array $report ) {
		$json = wp_json_encode( $report );
		if ( is_string( $json ) && strlen( $json ) <= self::CRASH_MAX_PAYLOAD_BYTES ) {
			return $report;
		}

		$bytes_before   = is_string( $json ) ? strlen( $json ) : 0;
		$trimmed_fields = [];
		$fingerprint    = isset( $report['extra_data']['fingerprint'] ) ? (string) $report['extra_data']['fingerprint'] : '';
		$agg_count      = isset( $report['extra_data']['aggregate_count'] ) ? (int) $report['extra_data']['aggregate_count'] : 0;

		if ( isset( $report['extra_data']['plugin_stack'] ) && is_array( $report['extra_data']['plugin_stack'] ) ) {
			$report['extra_data']['plugin_stack'] = array_slice( $report['extra_data']['plugin_stack'], 0, 2 );
			$trimmed_fields[]                     = 'plugin_stack';
		}

		if ( isset( $report['extra_data']['aggregate_last_sample'] ) ) {
			unset( $report['extra_data']['aggregate_last_sample'] );
			$trimmed_fields[] = 'aggregate_last_sample';
		}

		if ( isset( $report['exception_message'] ) && is_string( $report['exception_message'] ) ) {
			if ( function_exists( 'mb_substr' ) ) {
				$report['exception_message'] = mb_substr( $report['exception_message'], 0, 250 );
			} else {
				$report['exception_message'] = substr( $report['exception_message'], 0, 250 );
			}
			$trimmed_fields[] = 'exception_message';
		}

		$json_after  = wp_json_encode( $report );
		$bytes_after = is_string( $json_after ) ? strlen( $json_after ) : 0;
		error_log( '[FBW_CRASH_OBS] crash payload trimmed: reason=payload_size fingerprint=' . $fingerprint . ' aggregate_count=' . $agg_count . ' bytes_before=' . $bytes_before . ' bytes_after=' . $bytes_after . ' trimmed_fields=' . implode( ',', $trimmed_fields ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		return $report;
	}

	/**
	 * Soft limit guard for queued crash reports.
	 *
	 * @since 3.6.4
	 *
	 * @param int $queue_size receives current queue size snapshot.
	 * @return bool
	 */
	private function can_queue_more_crash_reports( &$queue_size = 0 ) {
		$queue_size = 0;
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return true;
		}

		$actions = as_get_scheduled_actions(
			[
				'hook'     => ErrorLogHandler::META_LOG_API,
				'group'    => ErrorLogHandler::META_LOG_API_GROUP,
				'status'   => [ 'pending', 'in-progress' ],
				'per_page' => self::CRASH_MAX_QUEUED_REPORTS + 1,
			],
			'ids'
		);

		if ( ! is_array( $actions ) ) {
			return true;
		}

		$queue_size = count( $actions );
		return $queue_size <= self::CRASH_MAX_QUEUED_REPORTS;
	}

	/**
	 * Trims one low-value pending queued crash report when queue is full and incoming report is higher value.
	 *
	 * Strategy: prefer keeping reports with higher aggregate_count; when tied, keep existing and drop incoming.
	 *
	 * @since 3.6.4
	 *
	 * @param array $incoming_report incoming normalized crash payload.
	 * @param int   $queue_size current queue size snapshot.
	 * @return bool true when a lower-value pending report was removed and incoming report should proceed.
	 */
	protected function maybe_trim_queue_for_prioritized_report( array $incoming_report, $queue_size ) {
		$incoming_count = $this->get_report_aggregate_count( $incoming_report );
		$incoming_fp    = isset( $incoming_report['extra_data']['fingerprint'] ) ? (string) $incoming_report['extra_data']['fingerprint'] : '';
		$actions        = $this->get_pending_crash_queue_actions( self::CRASH_MAX_QUEUED_REPORTS + 10 );

		if ( ! is_array( $actions ) || empty( $actions ) ) {
			return false;
		}

		$best_candidate_report = null;
		$best_candidate_id     = 0;
		$best_count            = null;

		foreach ( $actions as $action ) {
			if ( ! is_object( $action ) || ! method_exists( $action, 'get_args' ) ) {
				continue;
			}

			$args = $action->get_args();
			if ( ! is_array( $args ) || ! isset( $args[0] ) || ! is_array( $args[0] ) ) {
				continue;
			}

			$candidate_report = $args[0];
			$candidate_count  = $this->get_report_aggregate_count( $candidate_report );
			$candidate_id     = method_exists( $action, 'get_id' ) ? (int) $action->get_id() : 0;

			if ( null === $best_count || $candidate_count < $best_count ) {
				$best_count            = $candidate_count;
				$best_candidate_report = $candidate_report;
				$best_candidate_id     = $candidate_id;
			}
		}

		if ( ! is_array( $best_candidate_report ) || null === $best_count ) {
			return false;
		}

		if ( $incoming_count <= $best_count ) {
			$this->log_crash_observability( '[FBW_CRASH_OBS] crash report dropped: reason=queue_priority fingerprint=' . $incoming_fp . ' aggregate_count=' . (int) $incoming_count . ' queue_size=' . (int) $queue_size . ' kept_min_aggregate_count=' . (int) $best_count );
			return false;
		}

		$removed = $this->unschedule_pending_crash_action( $best_candidate_report, $best_candidate_id );
		if ( empty( $removed ) ) {
			return false;
		}

		$removed_fp = isset( $best_candidate_report['extra_data']['fingerprint'] ) ? (string) $best_candidate_report['extra_data']['fingerprint'] : '';
		if ( '' !== $removed_fp ) {
			$this->safe_delete_transient( self::CRASH_QUEUE_LOCK_PREFIX . $removed_fp );
		}
		$this->log_crash_observability( '[FBW_CRASH_OBS] crash queue trimmed: reason=queue_priority_replace removed_fingerprint=' . $removed_fp . ' removed_aggregate_count=' . (int) $best_count . ' incoming_fingerprint=' . $incoming_fp . ' incoming_aggregate_count=' . (int) $incoming_count . ' queue_size=' . (int) $queue_size );

		return true;
	}

	/**
	 * Extracts aggregate count from report payload.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report crash report.
	 * @return int
	 */
	protected function get_report_aggregate_count( array $report ) {
		$count = isset( $report['extra_data']['aggregate_count'] ) ? (int) $report['extra_data']['aggregate_count'] : 1;
		return max( 1, $count );
	}

	/**
	 * Returns pending crash actions from Action Scheduler.
	 *
	 * @since 3.6.4
	 *
	 * @param int $per_page max actions to fetch.
	 * @return array
	 */
	protected function get_pending_crash_queue_actions( $per_page ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return [];
		}

		return as_get_scheduled_actions(
			[
				'hook'     => ErrorLogHandler::META_LOG_API,
				'group'    => ErrorLogHandler::META_LOG_API_GROUP,
				'status'   => 'pending',
				'orderby'  => 'date',
				'order'    => 'ASC',
				'per_page' => (int) $per_page,
			],
			OBJECT
		);
	}

	/**
	 * Unschedules one pending crash action, preferring Action Scheduler ID cancellation.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report queued crash report payload.
	 * @param int   $action_id queued Action Scheduler ID when available.
	 * @return int
	 */
	protected function unschedule_pending_crash_action( array $report, $action_id = 0 ) {
		$action_id = (int) $action_id;

		if ( $action_id > 0 && class_exists( 'ActionScheduler' ) && method_exists( 'ActionScheduler', 'store' ) ) {
			try {
				$store = \ActionScheduler::store();
				if ( is_object( $store ) && method_exists( $store, 'cancel_action' ) ) {
					$store->cancel_action( $action_id );
					return 1;
				}
			} catch ( Throwable $e ) {
				unset( $e );
				// Fall back to payload-based unschedule below.
			}
		}

		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return 0;
		}

		return (int) as_unschedule_action( ErrorLogHandler::META_LOG_API, [ $report ], ErrorLogHandler::META_LOG_API_GROUP );
	}

	/**
	 * Emits crash observability log marker.
	 *
	 * @since 3.6.4
	 *
	 * @param string $message message to log.
	 */
	protected function log_crash_observability( $message ) {
		error_log( (string) $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}


	/**
	 * Checks if crash reporting is currently rate limited.
	 *
	 * Uses a simple rolling window counter in a dedicated transient.
	 *
	 * @since 3.6.4
	 *
	 * @return bool True when sending should be skipped.
	 */
	private function should_rate_limit_crash_report() {
		$now   = time();
		$state = $this->safe_get_transient( self::CRASH_RATE_LIMIT_KEY );

		if ( ! is_array( $state ) ) {
			$state = [
				'window_started_at' => $now,
				'count'             => 0,
				'limited_count'     => 0,
				'last_seen'         => 0,
			];
		}

		$window_started_at = isset( $state['window_started_at'] ) ? (int) $state['window_started_at'] : 0;
		$count             = isset( $state['count'] ) ? (int) $state['count'] : 0;
		$limited_count     = isset( $state['limited_count'] ) ? (int) $state['limited_count'] : 0;

		if ( $window_started_at <= 0 || ( $now - $window_started_at ) >= self::CRASH_RATE_LIMIT_WINDOW ) {
			$window_started_at = $now;
			$count             = 0;
			$limited_count     = 0;
		}

		if ( $count >= self::CRASH_RATE_LIMIT_MAX ) {
			$this->safe_set_transient(
				self::CRASH_RATE_LIMIT_KEY,
				[
					'window_started_at' => $window_started_at,
					'count'             => $count,
					'limited_count'     => $limited_count,
					'last_seen'         => $now,
				],
				self::CRASH_RATE_LIMIT_WINDOW
			);

			return true;
		}

		$this->safe_set_transient(
			self::CRASH_RATE_LIMIT_KEY,
			[
				'window_started_at' => $window_started_at,
				'count'             => $count + 1,
				'limited_count'     => $limited_count,
				'last_seen'         => $now,
			],
			self::CRASH_RATE_LIMIT_WINDOW
		);

		return false;
	}

	/**
	 * Increments local counters for rate-limited reports.
	 *
	 * @since 3.6.4
	 */
	private function increment_rate_limited_counter() {
		$now   = time();
		$state = $this->safe_get_transient( self::CRASH_RATE_LIMIT_KEY );

		if ( ! is_array( $state ) ) {
			$state = [
				'window_started_at' => $now,
				'count'             => 0,
				'limited_count'     => 0,
				'last_seen'         => 0,
			];
		}

		$state['limited_count'] = isset( $state['limited_count'] ) ? ( (int) $state['limited_count'] + 1 ) : 1;
		$state['last_seen']     = $now;

		$this->safe_set_transient( self::CRASH_RATE_LIMIT_KEY, $state, self::CRASH_RATE_LIMIT_WINDOW );
	}

	/**
	 * Determines whether a crash report should be queued based on dedup lock.
	 *
	 * @since 3.6.4
	 *
	 * @param array       $report normalized crash report payload.
	 * @param string|null $lock_storage receives lock backend used for acquisition.
	 * @return bool
	 */
	private function should_queue_crash_report( array $report, &$lock_storage = null ) {
		$fingerprint = $this->generate_crash_fingerprint( $report );

		if ( '' === $fingerprint ) {
			$lock_storage = null;
			return true;
		}

		$cache_key = 'crash_lock_' . $fingerprint;

		// Prefer object cache lock when an external cache backend is available.
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$lock_storage = 'object_cache';
			$acquired     = wp_cache_add( $cache_key, 1, self::CRASH_REPORT_CACHE_GROUP, HOUR_IN_SECONDS );
			if ( $acquired ) {
				$this->log_crash_observability( '[FBW_DEDUP_DEBUG] dedup lock created: storage=object_cache fingerprint=' . $fingerprint );
			} else {
				$this->log_crash_observability( '[FBW_DEDUP_DEBUG] dedup lock blocked: storage=object_cache fingerprint=' . $fingerprint );
			}
			return $acquired;
		}

		// Fallback lock for non-persistent object cache environments.
		$lock_storage  = 'transient';
		$transient_key = 'wc_facebook_crash_lock_' . $fingerprint;
		if ( false !== $this->safe_get_transient( $transient_key ) ) {
			$this->log_crash_observability( '[FBW_DEDUP_DEBUG] dedup lock blocked: storage=transient fingerprint=' . $fingerprint );
			return false;
		}

		$this->safe_set_transient( $transient_key, 1, HOUR_IN_SECONDS );
		$this->log_crash_observability( '[FBW_DEDUP_DEBUG] dedup lock created: storage=transient fingerprint=' . $fingerprint );
		return true;
	}

	/**
	 * Releases dedup lock when report is skipped or dropped before enqueue.
	 *
	 * @since 3.6.4
	 *
	 * @param array       $report normalized crash report payload.
	 * @param string      $reason release reason for observability logs.
	 * @param string|null $lock_storage lock backend used for acquisition.
	 */
	private function release_crash_dedup_lock( array $report, $reason = 'unknown', $lock_storage = null ) {
		$fingerprint = $this->generate_crash_fingerprint( $report );
		if ( '' === $fingerprint ) {
			return;
		}

		$cache_key = 'crash_lock_' . $fingerprint;

		if ( 'object_cache' === $lock_storage ) {
			wp_cache_delete( $cache_key, self::CRASH_REPORT_CACHE_GROUP );
		} elseif ( 'transient' === $lock_storage ) {
			$this->safe_delete_transient( 'wc_facebook_crash_lock_' . $fingerprint );
		}

		$this->log_crash_observability( '[FBW_DEDUP_DEBUG] dedup lock released: reason=' . (string) $reason . ' storage=' . (string) $lock_storage . ' fingerprint=' . $fingerprint );
	}

	/**
	 * Increments local duplicate crash counters for suppressed reports.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report normalized crash report payload.
	 */
	private function increment_duplicate_crash_counter( array $report ) {
		$fingerprint = $this->generate_crash_fingerprint( $report );

		if ( '' === $fingerprint ) {
			return;
		}

		$transient_key = 'wc_facebook_crash_dup_' . $fingerprint;
		$current       = $this->safe_get_transient( $transient_key );
		$now           = time();

		$count      = is_array( $current ) && isset( $current['count'] ) ? (int) $current['count'] : 0;
		$first_seen = is_array( $current ) && isset( $current['first_seen'] ) ? (int) $current['first_seen'] : $now;

		$last_sample = [
			'event_type' => isset( $report['event_type'] ) ? (string) $report['event_type'] : '',
			'message'    => isset( $report['exception_message'] ) ? (string) $report['exception_message'] : '',
			'file'       => isset( $report['extra_data']['file'] ) ? (string) $report['extra_data']['file'] : '',
			'line'       => isset( $report['extra_data']['line'] ) ? (int) $report['extra_data']['line'] : 0,
		];

		$this->safe_set_transient(
			$transient_key,
			[
				'first_seen'  => $first_seen,
				'last_seen'   => $now,
				'count'       => $count + 1,
				'last_sample' => $last_sample,
			],
			DAY_IN_SECONDS
		);
	}

	/**
	 * Generates a crash fingerprint used for deduplication.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report normalized crash report payload.
	 * @return string
	 */
	private function generate_crash_fingerprint( array $report ) {
		$extra      = isset( $report['extra_data'] ) && is_array( $report['extra_data'] ) ? $report['extra_data'] : [];
		$stack      = isset( $extra['plugin_stack'] ) && is_array( $extra['plugin_stack'] ) ? $extra['plugin_stack'] : [];
		$top_frame  = ! empty( $stack ) && is_array( $stack[0] ) ? $stack[0] : [];
		$components = [
			isset( $report['event_type'] ) ? (string) $report['event_type'] : '',
			isset( $report['exception_message'] ) ? (string) $report['exception_message'] : '',
			isset( $extra['file'] ) ? (string) $extra['file'] : '',
			isset( $extra['line'] ) ? (string) $extra['line'] : '',
			isset( $top_frame['file'] ) ? (string) $top_frame['file'] : '',
			isset( $top_frame['line'] ) ? (string) $top_frame['line'] : '',
			isset( $extra['plugin_version'] ) ? (string) $extra['plugin_version'] : '',
		];

		$fingerprint_source = implode( '|', $components );
		if ( '' === trim( $fingerprint_source ) ) {
			return '';
		}

		return md5( $fingerprint_source );
	}

	/**
	 * Checks whether the captured error is one of the supported fatal types.
	 *
	 * @since 3.6.4
	 *
	 * @param array|null $error last PHP error.
	 * @return bool
	 */
	private function is_supported_fatal_error( $error ) {
		if ( ! is_array( $error ) || empty( $error['type'] ) ) {
			return false;
		}

		return in_array( (int) $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true );
	}

	/**
	 * Normalizes an uncaught throwable to an error-like payload.
	 *
	 * @since 3.6.4
	 *
	 * @param Throwable $throwable throwable instance.
	 * @return array
	 */
	private function normalize_throwable_to_error( Throwable $throwable ) {
		$type = $throwable instanceof \ParseError ? E_PARSE : E_ERROR;

		return $this->normalize_captured_error(
			[
				'type'            => $type,
				'message'         => $throwable->getMessage(),
				'file'            => $throwable->getFile(),
				'line'            => $throwable->getLine(),
				'exception_class' => get_class( $throwable ),
				'trace'           => $throwable->getTrace(),
			],
			'uncaught_exception'
		);
	}

	/**
	 * Normalizes captured error data into a stable internal shape.
	 *
	 * @since 3.6.4
	 *
	 * @param array|null $error captured error payload.
	 * @param string     $source source type (fatal_error or uncaught_exception).
	 * @return array|null
	 */
	private function normalize_captured_error( $error, $source ) {
		if ( ! is_array( $error ) ) {
			return null;
		}

		return [
			'type'            => isset( $error['type'] ) ? (int) $error['type'] : 0,
			'message'         => isset( $error['message'] ) ? (string) $error['message'] : '',
			'file'            => isset( $error['file'] ) ? (string) $error['file'] : '',
			'line'            => isset( $error['line'] ) ? (int) $error['line'] : 0,
			'exception_class' => isset( $error['exception_class'] ) ? (string) $error['exception_class'] : '',
			'trace'           => isset( $error['trace'] ) && is_array( $error['trace'] ) ? $error['trace'] : [],
			'source'          => 'uncaught_exception' === $source ? 'uncaught_exception' : 'fatal_error',
		];
	}

	/**
	 * Checks whether a fatal error originated from this plugin path.
	 *
	 * @since 3.6.4
	 *
	 * @param array $error last PHP error.
	 * @return bool
	 */
	private function is_plugin_error( array $error ) {
		if ( empty( $error['file'] ) || ! is_string( $error['file'] ) ) {
			return false;
		}

		if ( ! defined( 'WC_FACEBOOK_PLUGIN_PATH' ) || ! is_string( WC_FACEBOOK_PLUGIN_PATH ) || '' === WC_FACEBOOK_PLUGIN_PATH ) {
			return false;
		}

		$error_file  = wp_normalize_path( $error['file'] );
		$plugin_path = trailingslashit( wp_normalize_path( WC_FACEBOOK_PLUGIN_PATH ) );

		return 0 === strpos( $error_file, $plugin_path );
	}

	/**
	 * Writes disable flag state.
	 *
	 * Primary storage is a file-based flag in uploads; if file write fails,
	 * falls back to a transient. If both writes fail, logs to error_log.
	 *
	 * Known limitation: during memory-exhaustion shutdowns this method may not run
	 * (or may fail early) due to insufficient memory in the dying process.
	 *
	 * @since 3.6.4
	 */
	private function write_disable_flag() {
		$existing_payload = $this->get_existing_disable_flag_payload();
		$payload          = $this->build_next_disable_flag_payload( $existing_payload );

		if ( $this->write_disable_flag_file( $payload ) ) {
			return;
		}

		if ( $this->write_disable_flag_transient( $payload ) ) {
			return;
		}

		error_log( 'Meta for WooCommerce crash capture: failed to write disable flag file and transient fallback.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Reads a transient during crash shutdown handling without letting storage errors escape.
	 *
	 * @since 3.6.4
	 *
	 * @param string $key transient key.
	 * @return mixed
	 */
	private function safe_get_transient( $key ) {
		try {
			return get_transient( $key );
		} catch ( Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Writes a transient during crash shutdown handling without letting storage errors escape.
	 *
	 * @since 3.6.4
	 *
	 * @param string $key transient key.
	 * @param mixed  $value transient value.
	 * @param int    $expiration expiration in seconds.
	 * @return bool
	 */
	private function safe_set_transient( $key, $value, $expiration ) {
		try {
			return (bool) set_transient( $key, $value, $expiration );
		} catch ( Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Deletes a transient during crash shutdown handling without letting storage errors escape.
	 *
	 * @since 3.6.4
	 *
	 * @param string $key transient key.
	 * @return bool
	 */
	private function safe_delete_transient( $key ) {
		try {
			return (bool) delete_transient( $key );
		} catch ( Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Gets the existing disable flag payload from file or transient fallback.
	 *
	 * @since 3.6.4
	 *
	 * @return array
	 */
	private function get_existing_disable_flag_payload() {
		$flag_file = $this->get_disable_flag_file_path();

		if ( is_readable( $flag_file ) ) {
			$raw_payload = @file_get_contents( $flag_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $raw_payload ) {
				error_log( 'Meta for WooCommerce crash capture: failed to read disable flag file.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			} elseif ( is_string( $raw_payload ) && '' !== $raw_payload ) {
				$decoded = json_decode( $raw_payload, true );
				if ( is_array( $decoded ) && isset( $decoded['crash_count'] ) ) {
					return $this->is_disable_window_active( $decoded ) ? $decoded : [];
				}
			}
		}

		$transient_payload = $this->safe_get_transient( \WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT );

		if ( is_array( $transient_payload ) && isset( $transient_payload['crash_count'] ) ) {
			return $this->is_disable_window_active( $transient_payload ) ? $transient_payload : [];
		}

		return [];
	}

	/**
	 * Builds the next disable flag payload.
	 *
	 * @since 3.6.4
	 *
	 * @param array $existing_payload existing payload values.
	 * @return array
	 */
	private function build_next_disable_flag_payload( array $existing_payload ) {
		$crash_count = ( isset( $existing_payload['crash_count'] ) && $this->is_disable_window_active( $existing_payload ) ) ? (int) $existing_payload['crash_count'] : 0;

		return [
			'timestamp'   => time(),
			'crash_count' => $crash_count + 1,
		];
	}

	/**
	 * Writes the disable flag file.
	 *
	 * @since 3.6.4
	 *
	 * @param array $payload disable flag payload.
	 * @return bool
	 */
	private function write_disable_flag_file( array $payload ) {
		$flag_file = $this->get_disable_flag_file_path();
		$flag_dir  = dirname( $flag_file );

		if ( ! is_dir( $flag_dir ) && ! wp_mkdir_p( $flag_dir ) ) {
			return false;
		}

		$bytes_written = @file_put_contents( $flag_file, wp_json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $bytes_written ) {
			error_log( 'Meta for WooCommerce crash capture: failed to write disable flag file.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return false !== $bytes_written;
	}

	/**
	 * Writes the disable flag transient fallback.
	 *
	 * @since 3.6.4
	 *
	 * @param array $payload disable flag payload.
	 * @return bool
	 */
	private function write_disable_flag_transient( array $payload ) {
		return $this->safe_set_transient( \WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT, $payload, MONTH_IN_SECONDS );
	}

	/**
	 * Checks whether the disable window is still active for a payload.
	 *
	 * @since 3.6.4
	 *
	 * @param array $payload disable flag payload.
	 * @return bool
	 */
	private function is_disable_window_active( array $payload ) {
		if ( ! isset( $payload['timestamp'], $payload['crash_count'] ) ) {
			return false;
		}

		$window_seconds = $this->get_disable_window_seconds( (int) $payload['crash_count'] );
		if ( null === $window_seconds ) {
			return true;
		}

		return ( time() - (int) $payload['timestamp'] ) < $window_seconds;
	}

	/**
	 * Returns disable window length by crash count.
	 *
	 * @since 3.6.4
	 *
	 * @param int $crash_count crash count value.
	 * @return int|null Seconds for temporary disable, or null for permanent disable.
	 */
	private function get_disable_window_seconds( $crash_count ) {
		if ( $crash_count <= 1 ) {
			return 10 * MINUTE_IN_SECONDS;
		}

		if ( 2 === $crash_count ) {
			return HOUR_IN_SECONDS;
		}

		return null;
	}

	/**
	 * Gets the disable flag file path.
	 *
	 * @since 3.6.4
	 *
	 * @return string
	 */
	private function get_disable_flag_file_path() {
		return trailingslashit( WP_CONTENT_DIR ) . 'uploads/facebook-for-woocommerce/.disabled';
	}

	/**
	 * Queues a crash report for asynchronous processing.
	 *
	 * Uses Action Scheduler only when available.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report normalized crash report payload.
	 * @return bool
	 */
	private function queue_crash_report( array $report ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return ErrorLogHandler::enqueue_meta_log_request( $report, true );
		}

		$delay = function_exists( 'wp_rand' ) ? wp_rand( 60, self::CRASH_REPORT_MAX_JITTER_SECONDS ) : mt_rand( 60, self::CRASH_REPORT_MAX_JITTER_SECONDS );

		try {
			$action_id = as_schedule_single_action( time() + $delay, ErrorLogHandler::META_LOG_API, [ $report ], ErrorLogHandler::META_LOG_API_GROUP, true );
			return ! empty( $action_id );
		} catch ( Throwable $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Normalizes and sanitizes crash payload for stable reporting.
	 *
	 * @since 3.6.4
	 *
	 * @param array $error captured PHP fatal error data.
	 * @return array
	 */
	private function normalize_crash_report_payload( array $error ) {
		$message     = isset( $error['message'] ) ? (string) $error['message'] : '';
		$event_type  = ( isset( $error['source'] ) && 'uncaught_exception' === $error['source'] ) ? 'uncaught_exception' : 'fatal_error';
		$error_class = isset( $error['exception_class'] ) ? (string) $error['exception_class'] : '';

		$payload = [
			'event'             => 'plugin_crash',
			'event_type'        => $event_type,
			'exception_message' => $this->sanitize_message( $message ),
			'extra_data'        => [
				'error_class'     => $error_class,
				'php_error_type'  => $this->get_php_error_type_label( isset( $error['type'] ) ? (int) $error['type'] : 0 ),
				'error_type'      => isset( $error['type'] ) ? (int) $error['type'] : 0,
				'file'            => $this->sanitize_file_path( isset( $error['file'] ) ? (string) $error['file'] : '' ),
				'line'            => isset( $error['line'] ) ? (int) $error['line'] : 0,
				'plugin_stack'    => $this->extract_plugin_stack_frames( isset( $error['trace'] ) && is_array( $error['trace'] ) ? $error['trace'] : [] ),
				'plugin_version'  => $this->get_plugin_version(),
				'php_version'     => PHP_VERSION,
				'wp_version'      => isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '',
				'wc_version'      => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
				'request_context' => $this->get_request_context(),
			],
		];

		return $payload;
	}

	/**
	 * Gets plugin version in a shutdown-safe way.
	 *
	 * @since 3.6.4
	 *
	 * @return string
	 */
	private function get_plugin_version() {
		if ( class_exists( '\\WC_Facebook_Loader' ) && defined( '\\WC_Facebook_Loader::PLUGIN_VERSION' ) ) {
			return (string) constant( '\\WC_Facebook_Loader::PLUGIN_VERSION' );
		}

		return '';
	}

	/**
	 * Gets the request context for crash reporting.
	 *
	 * @since 3.6.4
	 *
	 * @return string
	 */
	private function get_request_context() {
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return 'ajax';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( is_admin() ) {
			return 'admin';
		}

		return 'frontend';
	}

	/**
	 * Extracts up to five plugin stack frames.
	 *
	 * @since 3.6.4
	 *
	 * @param array $trace throwable trace.
	 * @return array
	 */
	private function extract_plugin_stack_frames( array $trace ) {
		$frames = [];

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) || ! is_string( $frame['file'] ) ) {
				continue;
			}

			$sanitized_file = $this->sanitize_file_path( $frame['file'] );
			if ( '' === $sanitized_file || 0 !== strpos( $sanitized_file, 'plugin:' ) ) {
				continue;
			}

			$frames[] = [
				'file' => $sanitized_file,
				'line' => isset( $frame['line'] ) ? (int) $frame['line'] : 0,
			];

			if ( count( $frames ) >= 5 ) {
				break;
			}
		}

		return $frames;
	}

	/**
	 * Sanitizes a message sample and truncates to 500 characters.
	 *
	 * @since 3.6.4
	 *
	 * @param string $message message text.
	 * @return string
	 */
	private function sanitize_message( $message ) {
		try {
			$sanitized = $this->sanitize_sensitive_values_with_parser( (string) $message );
			$sanitized = $this->sanitize_paths_with_parser( $sanitized );
		} catch ( Throwable $e ) {
			$sanitized = $this->sanitize_sensitive_values( (string) $message );
			$sanitized = preg_replace( '#(?:[A-Za-z]:)?(?:/|\\\\)[^\s"\']+#', '[path]', $sanitized );
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( (string) $sanitized, 0, 500 );
		}

		return substr( (string) $sanitized, 0, 500 );
	}

	/**
	 * Sanitizes file paths.
	 *
	 * @since 3.6.4
	 *
	 * @param string $path absolute file path.
	 * @return string
	 */
	private function sanitize_file_path( $path ) {
		if ( '' === $path ) {
			return '';
		}

		$normalized_path = wp_normalize_path( $path );
		$plugin_path     = defined( 'WC_FACEBOOK_PLUGIN_PATH' ) ? trailingslashit( wp_normalize_path( WC_FACEBOOK_PLUGIN_PATH ) ) : '';

		if ( '' !== $plugin_path && 0 === strpos( $normalized_path, $plugin_path ) ) {
			return 'plugin:' . ltrim( substr( $normalized_path, strlen( $plugin_path ) ), '/' );
		}

		return basename( $normalized_path );
	}

	/**
	 * Redacts token/key-like values from text.
	 *
	 * @since 3.6.4
	 *
	 * @param string $text input text.
	 * @return string
	 */
	private function sanitize_sensitive_values( $text ) {
		$patterns = [
			// Common secret-like key/value pairs.
			'/(token|access_token|auth|authorization|secret|api[_-]?key|password|cookie|set-cookie|request_body|body|headers?)\s*[:=]\s*("[^"]*"|\'[^\']*\'|\{[^\}]*\}|\[[^\]]*\]|[^\r\n]+)/i',
			// Explicit request-like header/body lines.
			'/(authorization|cookie|set-cookie|request_body|body|headers?)\s*:\s*[^\r\n]*/i',
			// Authorization bearer values.
			'/\bBearer\s+(?:Bearer\s+)?[A-Za-z0-9\-._~+\/]+=*/i',
			// JWT-like strings.
			'/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9._-]+\.[A-Za-z0-9._-]+\b/',
			// Redact long token-like strings (mixed letters+digits) and long hex strings.
			'/\b(?=[A-Za-z0-9_\-]{24,}\b)(?=[A-Za-z0-9_\-]*[A-Za-z])(?=[A-Za-z0-9_\-]*\d)[A-Za-z0-9_\-]+\b/',
			'/\b[a-f0-9]{32,}\b/i',
			// Basic PII redaction.
			'/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
			'/(?<!\w)\+?\d[\d\s().-]{7,}\d(?!\w)/',
		];

		$replacements = [
			'$1=[redacted]',
			'$1: [redacted]',
			'Bearer [redacted_token]',
			'[redacted_token]',
			'[redacted_token]',
			'[redacted_token]',
			'[redacted_email]',
			'[redacted_phone]',
		];

		return preg_replace( $patterns, $replacements, (string) $text );
	}

	/**
	 * Parser-first sanitizer for sensitive values in log messages.
	 *
	 * @since 3.6.4
	 *
	 * @param string $text input text.
	 * @return string
	 */
	private function sanitize_sensitive_values_with_parser( $text ) {
		$lines = preg_split( "/\\r\\n|\\n|\\r/", (string) $text );
		if ( ! is_array( $lines ) ) {
			return (string) $text;
		}

		$sensitive_keys = [
			'token',
			'access_token',
			'auth',
			'authorization',
			'secret',
			'apikey',
			'api_key',
			'password',
			'cookie',
			'setcookie',
			'set-cookie',
			'requestbody',
			'request_body',
			'body',
			'headers',
			'header',
			'phone',
			'telephone',
			'mobile',
		];

		foreach ( $lines as $index => $line ) {
			$line = (string) $line;
			$eq   = strpos( $line, '=' );
			$col  = strpos( $line, ':' );

			$pos = false;
			$sep = '';
			if ( false !== $eq && false !== $col ) {
				$pos = min( $eq, $col );
				$sep = $eq < $col ? '=' : ':';
			} elseif ( false !== $eq ) {
				$pos = $eq;
				$sep = '=';
			} elseif ( false !== $col ) {
				$pos = $col;
				$sep = ':';
			}

			if ( false !== $pos ) {
				$key      = trim( substr( $line, 0, $pos ) );
				$key_norm = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $key ) );
				if ( in_array( $key_norm, $sensitive_keys, true ) ) {
					$lines[ $index ] = '=' === $sep ? $key . '=[redacted]' : $key . ': [redacted]';
					continue;
				}
			}

			$tokens = preg_split( '/(\s+)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( ! is_array( $tokens ) ) {
				continue;
			}

			$tokens_count = count( $tokens );
			for ( $i = 0; $i < $tokens_count; $i++ ) {
				$token = (string) $tokens[ $i ];
				if ( '' === trim( $token ) ) {
					continue;
				}

				if ( 0 === strcasecmp( $token, 'Bearer' ) ) {
					$tokens[ $i ] = 'Bearer';
					for ( $j = $i + 1; $j < $tokens_count; $j++ ) {
						if ( '' === trim( (string) $tokens[ $j ] ) ) {
							continue;
						}
						$tokens[ $j ] = '[redacted_token]';
						break;
					}
					continue;
				}

				$tokens[ $i ] = $this->redact_sensitive_token( $token );
			}

			$lines[ $index ] = implode( '', $tokens );
		}

		$sanitized = implode( "\n", $lines );
		return preg_replace( '/(?<!\w)\+?\d[\d\s().-]{7,}\d(?!\w)/', '[redacted_phone]', $sanitized );
	}

	/**
	 * Parser-based absolute path masking for message text.
	 *
	 * @since 3.6.4
	 *
	 * @param string $text input text.
	 * @return string
	 */
	private function sanitize_paths_with_parser( $text ) {
		$tokens = preg_split( '/(\s+)/', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $tokens ) ) {
			return (string) $text;
		}

		foreach ( $tokens as $i => $token ) {
			$token = (string) $token;
			if ( '' === trim( $token ) ) {
				continue;
			}

			$parts = $this->split_token_wrappers( $token );
			$core  = $parts['core'];
			if ( '' === $core ) {
				continue;
			}

			if ( $this->looks_like_absolute_path( $core ) ) {
				$tokens[ $i ] = $parts['prefix'] . '[path]' . $parts['suffix'];
			}
		}

		return implode( '', $tokens );
	}

	/**
	 * Redacts sensitive token/PII-like samples while preserving simple wrappers.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return string
	 */
	private function redact_sensitive_token( $token ) {
		$parts = $this->split_token_wrappers( (string) $token );
		$core  = $parts['core'];
		if ( '' === $core ) {
			return (string) $token;
		}

		$replacement = '';
		if ( $this->looks_like_jwt( $core ) || $this->looks_like_long_hex( $core ) || $this->looks_like_long_token( $core ) ) {
			$replacement = '[redacted_token]';
		} elseif ( false !== strpos( $core, '@' ) && false !== strpos( substr( $core, strpos( $core, '@' ) ), '.' ) ) {
			$replacement = '[redacted_email]';
		} elseif ( $this->looks_like_phone( $core ) ) {
			$replacement = '[redacted_phone]';
		}

		if ( '' === $replacement ) {
			return (string) $token;
		}

		return $parts['prefix'] . $replacement . $parts['suffix'];
	}

	/**
	 * Splits a token into prefix/core/suffix wrappers.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return array
	 */
	private function split_token_wrappers( $token ) {
		$prefix_chars = "\"'([{<";
		$suffix_chars = "\"')]}>.,;:";

		$start = 0;
		$end   = strlen( (string) $token ) - 1;

		while ( $start <= $end && false !== strpos( $prefix_chars, $token[ $start ] ) ) {
			++$start;
		}

		while ( $end >= $start && false !== strpos( $suffix_chars, $token[ $end ] ) ) {
			--$end;
		}

		if ( $end < $start ) {
			return [
				'prefix' => (string) $token,
				'core'   => '',
				'suffix' => '',
			];
		}

		return [
			'prefix' => substr( $token, 0, $start ),
			'core'   => substr( $token, $start, $end - $start + 1 ),
			'suffix' => substr( $token, $end + 1 ),
		];
	}

	/**
	 * Checks whether token resembles an absolute Unix or Windows path.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return bool
	 */
	private function looks_like_absolute_path( $token ) {
		if ( '' === $token ) {
			return false;
		}

		if ( '/' === $token[0] ) {
			return true;
		}

		return strlen( $token ) > 2
			&& ctype_alpha( $token[0] )
			&& ':' === $token[1]
			&& ( '\\' === $token[2] || '/' === $token[2] );
	}

	/**
	 * Checks whether token resembles a JWT.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return bool
	 */
	private function looks_like_jwt( $token ) {
		if ( 0 !== strpos( $token, 'eyJ' ) ) {
			return false;
		}

		$parts = explode( '.', $token );
		return count( $parts ) >= 3;
	}

	/**
	 * Checks whether token resembles a long mixed token.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return bool
	 */
	private function looks_like_long_token( $token ) {
		$token_length = strlen( $token );
		if ( $token_length < 24 ) {
			return false;
		}

		$has_alpha = false;
		$has_digit = false;
		for ( $i = 0; $i < $token_length; $i++ ) {
			$char = $token[ $i ];
			if ( ctype_alpha( $char ) ) {
				$has_alpha = true;
			}
			if ( ctype_digit( $char ) ) {
				$has_digit = true;
			}
			if ( ! ctype_alnum( $char ) && '_' !== $char && '-' !== $char ) {
				return false;
			}
		}

		return $has_alpha && $has_digit;
	}

	/**
	 * Checks whether token resembles a long hex blob.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return bool
	 */
	private function looks_like_long_hex( $token ) {
		return strlen( $token ) >= 32 && ctype_xdigit( $token );
	}

	/**
	 * Checks whether token resembles a phone number.
	 *
	 * @since 3.6.4
	 *
	 * @param string $token token text.
	 * @return bool
	 */
	private function looks_like_phone( $token ) {
		$digits = preg_replace( '/\D/', '', $token );
		if ( ! is_string( $digits ) || strlen( $digits ) < 9 ) {
			return false;
		}

		$token_length = strlen( $token );
		for ( $i = 0; $i < $token_length; $i++ ) {
			$char = $token[ $i ];
			if ( ctype_digit( $char ) ) {
				continue;
			}
			if ( false === strpos( '+-(). ', $char ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Gets human-readable PHP error type label.
	 *
	 * @since 3.6.4
	 *
	 * @param int $type PHP error type.
	 * @return string
	 */
	private function get_php_error_type_label( $type ) {
		$map = [
			E_ERROR         => 'E_ERROR',
			E_PARSE         => 'E_PARSE',
			E_CORE_ERROR    => 'E_CORE_ERROR',
			E_COMPILE_ERROR => 'E_COMPILE_ERROR',
		];

		return isset( $map[ $type ] ) ? $map[ $type ] : 'UNKNOWN';
	}

	/**
	 * Logs crash report data when queueing fails.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report normalized crash payload.
	 */
	private function log_fallback( array $report ) {
		error_log( 'Meta for WooCommerce crash capture fallback: ' . wp_json_encode( $report ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
