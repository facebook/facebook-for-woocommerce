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
 * @since 3.6.4
 */
class PluginCrashHandler {

	/**
	 * Disable flag transient key fallback.
	 */
	const DISABLE_FLAG_TRANSIENT = 'wc_facebook_plugin_disabled';

	/**
	 * Cache group for crash report deduplication locks.
	 */
	const CRASH_REPORT_CACHE_GROUP = 'wc_facebook_crash_reports';

	/**
	 * Transient key for crash report rate limiting.
	 */
	const CRASH_RATE_LIMIT_KEY = 'wc_facebook_crash_rate_limit';

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
	 * Registers crash handling hooks.
	 *
	 * @since 3.6.4
	 */
	public function register() {
		$this->previous_exception_handler = set_exception_handler( [ $this, 'handle_uncaught_exception' ] );
		register_shutdown_function( [ $this, 'handle_shutdown' ] );
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

		$normalized_report = $this->normalize_crash_report_payload( $error );
		if ( ! $this->should_queue_crash_report( $normalized_report ) ) {
			$this->increment_duplicate_crash_counter( $normalized_report );
			return;
		}

		if ( $this->should_rate_limit_crash_report() ) {
			$this->increment_rate_limited_counter();
			return;
		}

		if ( ! $this->queue_crash_report( $normalized_report ) ) {
			$this->log_fallback( $normalized_report );
		}
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
		$state = get_transient( self::CRASH_RATE_LIMIT_KEY );

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
			set_transient(
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

		set_transient(
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
		$state = get_transient( self::CRASH_RATE_LIMIT_KEY );

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

		set_transient( self::CRASH_RATE_LIMIT_KEY, $state, self::CRASH_RATE_LIMIT_WINDOW );
	}

	/**
	 * Determines whether a crash report should be queued based on dedup lock.
	 *
	 * @since 3.6.4
	 *
	 * @param array $report normalized crash report payload.
	 * @return bool
	 */
	private function should_queue_crash_report( array $report ) {
		$fingerprint = $this->generate_crash_fingerprint( $report );

		if ( '' === $fingerprint ) {
			return true;
		}

		$cache_key = 'crash_lock_' . $fingerprint;

		return wp_cache_add( $cache_key, 1, self::CRASH_REPORT_CACHE_GROUP, HOUR_IN_SECONDS );
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
		$current       = get_transient( $transient_key );
		$count         = is_array( $current ) && isset( $current['count'] ) ? (int) $current['count'] : 0;

		set_transient(
			$transient_key,
			[
				'count'     => $count + 1,
				'last_seen' => time(),
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
			isset( $top_frame['function'] ) ? (string) $top_frame['function'] : '',
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
			if ( is_string( $raw_payload ) && '' !== $raw_payload ) {
				$decoded = json_decode( $raw_payload, true );
				if ( is_array( $decoded ) && isset( $decoded['crash_count'] ) ) {
					return $decoded;
				}
			}
		}

		$transient_payload = get_transient( self::DISABLE_FLAG_TRANSIENT );
		if ( is_array( $transient_payload ) && isset( $transient_payload['crash_count'] ) ) {
			return $transient_payload;
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
		$crash_count = isset( $existing_payload['crash_count'] ) ? (int) $existing_payload['crash_count'] : 0;

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
		return (bool) set_transient( self::DISABLE_FLAG_TRANSIENT, $payload, 0 );
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
		return ErrorLogHandler::enqueue_meta_log_request( $report, true );
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
				'error_class'      => $error_class,
				'php_error_type'   => $this->get_php_error_type_label( isset( $error['type'] ) ? (int) $error['type'] : 0 ),
				'error_type'       => isset( $error['type'] ) ? (int) $error['type'] : 0,
				'file'             => $this->sanitize_file_path( isset( $error['file'] ) ? (string) $error['file'] : '' ),
				'line'             => isset( $error['line'] ) ? (int) $error['line'] : 0,
				'plugin_stack'     => $this->extract_plugin_stack_frames( isset( $error['trace'] ) && is_array( $error['trace'] ) ? $error['trace'] : [] ),
				'plugin_version'   => $this->get_plugin_version(),
				'php_version'      => PHP_VERSION,
				'wp_version'       => isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '',
				'wc_version'       => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
				'request_context'  => $this->get_request_context(),
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
		$sanitized = $this->sanitize_sensitive_values( $message );

		// Strip absolute paths (Unix + Windows style).
		$sanitized = preg_replace( '#(?:[A-Za-z]:)?(?:/|\\\\)[^\s"\']+#', '[path]', $sanitized );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $sanitized, 0, 500 );
		}

		return substr( $sanitized, 0, 500 );
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
			'/(token|access_token|auth|authorization|secret|api[_-]?key|password|cookie|set-cookie|request_body|body)\s*[:=]\s*[^\s,;"\']+/i',
			'/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
			// Redact long token-like strings (mixed letters+digits) and long hex strings.
			'/\b(?=[A-Za-z0-9_\-]{24,}\b)(?=[A-Za-z0-9_\-]*[A-Za-z])(?=[A-Za-z0-9_\-]*\d)[A-Za-z0-9_\-]+\b/',
			'/\b[a-f0-9]{32,}\b/i',
		];

		$replacements = [
			'$1=[redacted]',
			'Bearer [redacted]',
			'[redacted_token]',
			'[redacted_token]',
		];

		return preg_replace( $patterns, $replacements, $text );
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
