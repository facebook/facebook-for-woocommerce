<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_Background_Process', false ) ) {
	// Do not attempt to create this class without WP_Background_Process
	return;
}

/**
 * Facebook Background Process
 *
 * Handles background processing of product synchronization with Facebook.
 */
class WC_Facebookcommerce_Background_Process extends WP_Background_Process {

		/**
		 * @var WC_Facebookcommerce_Integration instance.
		 */
		private $commerce;

	public function __construct( $commerce ) {
		$this->commerce = $commerce; // Full WC_Facebookcommerce_Integration obj
	}

		/**
		 * __get method for backward compatibility.
		 *
		 * @param string $key property name
		 * @return mixed
		 * @since 3.0.32
		 */
	public function __get( $key ) {
		// Add warning for private properties.
		if ( 'commerce' === $key ) {
			/* translators: %s property name. */
			_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'The %s property is private and should not be accessed outside its class.', 'facebook-for-woocommerce' ), esc_html( $key ) ), '3.0.32' );
			return $this->$key;
		}

		return null;
	}

		/**
		 * @var string
		 */
		protected $action = 'fb_commerce_background_process';

	public function dispatch() {
		$commerce   = $this->commerce;
		$dispatched = parent::dispatch();

		if ( is_wp_error( $dispatched ) ) {
			WC_Facebookcommerce_Utils::log(
				sprintf(
					'Unable to dispatch FB Background processor: %s',
					$dispatched->get_error_message()
				),
				array( 'source' => 'wc_facebook_background_process' )
			);
		}
	}

	public function get_item_count() {
		$commerce = $this->commerce;
		return (int) get_transient( $commerce::FB_SYNC_REMAINING );
	}

		/**
		 * Handle cron healthcheck
		 *
		 * Restart the background process if not already running
		 * and data exists in the queue.
		 */
	public function handle_cron_healthcheck() {
		$commerce = $this->commerce;
		if ( $this->is_process_running() ) {
			// Background process already running, no-op
			return true;  // Return "is running? status"
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->clear_scheduled_event();
			delete_transient( $commerce::FB_SYNC_REMAINING );
			return;
		}

		$this->handle();
		return true;
	}

		/**
		 * Schedule fallback event.
		 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event(
				time() + 10,
				$this->cron_interval_identifier,
				$this->cron_hook_identifier
			);
		}
	}

		/**
		 * Is the processor updating?
		 *
		 * @return boolean
		 */
	public function is_updating() {
		return false === $this->is_queue_empty();
	}

		/**
		 * Is the processor running?
		 *
		 * @return boolean
		 */
	public function is_running() {
		return $this->is_process_running();
	}

		/**
		 * Process individual product
		 *
		 * Returns false to remove the item from the queue
		 * (would return item if it needed additional processing).
		 *
		 * @param mixed $item Queue item to iterate over
		 *
		 * @return mixed
		 */
	protected function task( $item ) {
		$commerce      = $this->commerce;  // PHP5 compatibility for static access
		$remaining     = $this->get_item_count();
		$count_message = sprintf(
			'Background syncing products to Facebook. Products remaining: %1$d',
			$remaining
		);

		$this->commerce->display_sticky_message( $count_message, true );

		$this->commerce->on_product_publish( $item );
		--$remaining;
		set_transient(
			$commerce::FB_SYNC_IN_PROGRESS,
			true,
			$commerce::FB_SYNC_TIMEOUT
		);
		set_transient(
			$commerce::FB_SYNC_REMAINING,
			$remaining
		);

		return false;
	}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
	protected function complete() {
		$commerce = $this->commerce;  // PHP5 compatibility for static access
		delete_transient( $commerce::FB_SYNC_IN_PROGRESS );
		delete_transient( $commerce::FB_SYNC_REMAINING );
		WC_Facebookcommerce_Utils::log( 'Background sync complete!' );
		WC_Facebookcommerce_Utils::fblog( 'Background sync complete!' );
		$this->commerce->remove_sticky_message();
		$this->commerce->display_info_message( 'Facebook product sync complete!' );
		parent::complete();
	}
}
