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

defined( 'ABSPATH' ) || exit;


/**
 * The BatchLog handler.
 *
 * @since 3.5.0
 */
class BatchLogHandler extends LogHandlerBase {

	/**
	 * Action Scheduler hook for batch log flushing.
	 */
	const BATCH_LOG_FLUSH_HOOK = 'facebook_for_woocommerce_process_logs_batch';

	/**
	 * Action Scheduler group for batch log flushing.
	 */
	const BATCH_LOG_FLUSH_GROUP = 'wc_facebook_log_batch';

	/**
	 * Whether hooks have already been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Constructs a new BatchLog handler.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		self::register_batch_sender();
	}

	/**
	 * Registers the batch sender hook and recurring Action Scheduler event.
	 *
	 * @since 3.6.4
	 */
	public static function register_batch_sender() {
		if ( ! self::$registered ) {
			add_action( self::BATCH_LOG_FLUSH_HOOK, array( __CLASS__, 'process_logs_batch' ) );
			self::$registered = true;
		}

		self::ensure_batch_sender_scheduled();
	}

	/**
	 * Checks whether dependencies needed for batch sender scheduling are available.
	 *
	 * @since 3.6.4
	 *
	 * @return bool
	 */
	private static function can_schedule_batch_sender() {
		return did_action( 'action_scheduler_init' )
			&& function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'facebook_for_woocommerce' )
			&& class_exists( '\\WC_Facebookcommerce' );
	}

	/**
	 * Ensures recurring Action Scheduler job exists for batch log flushing.
	 *
	 * @since 3.6.4
	 */
	public static function ensure_batch_sender_scheduled() {
		if ( ! self::can_schedule_batch_sender() ) {
			return;
		}

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::BATCH_LOG_FLUSH_HOOK, [], self::BATCH_LOG_FLUSH_GROUP ) ) {
			return;
		}

		as_schedule_recurring_action( time() + ( 5 * MINUTE_IN_SECONDS ), 5 * MINUTE_IN_SECONDS, self::BATCH_LOG_FLUSH_HOOK, [], self::BATCH_LOG_FLUSH_GROUP, true );
	}

	/**
	 * Function that runs every five minutes.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public static function process_logs_batch() {

		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			return;
		}

		$plugin = facebook_for_woocommerce();
		if ( ! $plugin || ! method_exists( $plugin, 'get_integration' ) || ! $plugin->get_integration() ) {
			return;
		}

		if ( $plugin->get_integration()->is_meta_diagnosis_enabled() && get_transient( 'global_logging_message_queue' ) !== false && ! empty( get_transient( 'global_logging_message_queue' ) ) ) {
			$logs         = get_transient( 'global_logging_message_queue' );
			$chunked_logs = array_chunk( $logs, 20 );

			$chunked_failed_logs = array_map(
				function ( $logs_chunk ) {
					$logs_chunk_with_core_context = array_map(
						function ( $log ) {
							if ( empty( $log ) ) {
								return [];
							}
							return self::set_core_log_context( $log );
						},
						$logs_chunk
					);

					if ( empty( $logs_chunk_with_core_context ) ) {
						return [];
					}

					$context = [
						'event'      => 'persist_meta_logs',
						'extra_data' => [ 'meta_logs' => wp_json_encode( $logs_chunk_with_core_context ) ],
					];

					try {
						$response = facebook_for_woocommerce()->get_api()->log_to_meta( $context );
						if ( $response->success ) {
							return [];
						} else {
							Logger::log(
								'Bad response from Meta logging APIs',
								[],
								array(
									'should_send_log_to_meta' => false,
									'should_save_log_in_woocommerce' => true,
									'woocommerce_log_level'   => \WC_Log_Levels::ERROR,
								)
							);
							return $logs_chunk;
						}
					} catch ( \Exception $e ) {
						Logger::log(
							'Error persisting Meta logs: ' . $e->getMessage(),
							[],
							array(
								'should_send_log_to_meta' => false,
								'should_save_log_in_woocommerce' => true,
								'woocommerce_log_level'   => \WC_Log_Levels::ERROR,
							)
						);
						return $logs_chunk;
					}
				},
				$chunked_logs
			);

			$failed_logs = array_merge( ...$chunked_failed_logs );
			// Only keep the latest 100 failed logs, in case too much memory got eaten up on the host
			if ( count( $failed_logs ) > 100 ) {
				$failed_logs = array_slice( $failed_logs, -100 );
			}

			if ( ! empty( $failed_logs ) ) {
				set_transient( 'global_logging_message_queue', $failed_logs, HOUR_IN_SECONDS );
				return;
			}
		}

		set_transient( 'global_logging_message_queue', [], HOUR_IN_SECONDS );
	}
}
