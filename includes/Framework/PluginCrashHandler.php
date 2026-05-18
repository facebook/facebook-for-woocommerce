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
	 * Disable flag option key.
	 */
	const DISABLE_FLAG_OPTION = 'wc_facebook_plugin_crash_disabled';

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
		$error = error_get_last();

		if ( ! $this->is_supported_fatal_error( $error ) ) {
			$error = $this->captured_throwable_error;
		}

		if ( ! $this->is_supported_fatal_error( $error ) ) {
			return;
		}

		if ( ! $this->is_plugin_error( $error ) ) {
			return;
		}

		$this->write_disable_flag();

		if ( ! $this->queue_crash_report( $error ) ) {
			$this->log_fallback( $error );
		}
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

		return [
			'type'    => $type,
			'message' => $throwable->getMessage(),
			'file'    => $throwable->getFile(),
			'line'    => $throwable->getLine(),
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
	 * Writes a minimal temporary disable flag for the first crash handling iteration.
	 *
	 * No recovery/isolation logic is implemented yet; this only stores a marker.
	 *
	 * @since 3.6.4
	 */
	private function write_disable_flag() {
		update_option( self::DISABLE_FLAG_OPTION, 'yes' );
	}

	/**
	 * Queues a crash report for asynchronous processing.
	 *
	 * Uses Action Scheduler only when available.
	 *
	 * @since 3.6.4
	 *
	 * @param array $error last PHP error.
	 * @return bool
	 */
	private function queue_crash_report( array $error ) {
		// Guard: Action Scheduler may not be loaded in some environments.
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		$report = [
			'event'      => 'plugin_crash',
			'event_type' => 'fatal_error',
			'extra_data' => [
				'error_type' => (int) $error['type'],
				'file'       => isset( $error['file'] ) ? (string) $error['file'] : '',
				'line'       => isset( $error['line'] ) ? (int) $error['line'] : 0,
			],
		];

		if ( isset( $error['message'] ) && is_string( $error['message'] ) ) {
			$report['exception_message'] = $error['message'];
		}

		try {
			$action_id = as_enqueue_async_action( ErrorLogHandler::META_LOG_API, [ $report ] );
			return ! empty( $action_id );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Logs crash report data when queueing fails.
	 *
	 * @since 3.6.4
	 *
	 * @param array $error last PHP error.
	 */
	private function log_fallback( array $error ) {
		$payload = [
			'event'   => 'plugin_crash_queue_failed',
			'message' => isset( $error['message'] ) ? (string) $error['message'] : '',
			'type'    => isset( $error['type'] ) ? (int) $error['type'] : 0,
			'file'    => isset( $error['file'] ) ? (string) $error['file'] : '',
			'line'    => isset( $error['line'] ) ? (int) $error['line'] : 0,
		];

		error_log( 'Meta for WooCommerce crash capture fallback: ' . wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
