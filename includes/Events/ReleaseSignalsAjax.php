<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Events;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoint for releasing held signals.
 *
 * Receives queued browser events and sends them to CAPI, then returns
 * FBC/FBP attribution values for the frontend to set as cookies.
 *
 * @since 3.6.0
 */
class ReleaseSignalsAjax {

	/** @var string AJAX action name. */
	const ACTION = 'facebook_release_signals';

	/** @var string Nonce action. */
	const NONCE_ACTION = 'facebook_release_signals';

	/** @var int Maximum number of events per request. */
	const MAX_EVENTS = 20;

	/** @var int Maximum age of an event in seconds (30 minutes). */
	const MAX_EVENT_AGE = 1800;

	/** @var int Default rate-limit window in seconds (5 minutes). */
	const RATE_LIMIT_WINDOW = 300;

	/** @var int Default maximum accepted events per IP per window. */
	const RATE_LIMIT_MAX_EVENTS = 80;

	/** @var array Allowed event names. */
	const ALLOWED_EVENTS = array(
		'PageView',
		'ViewContent',
		'ViewCategory',
		'Search',
		'AddToCart',
		'InitiateCheckout',
		'Purchase',
		'Lead',
		'Subscribe',
	);

	/**
	 * Constructor — registers AJAX hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles the release-signals AJAX request.
	 */
	public function handle() {
		$raw_body = file_get_contents( 'php://input' );
		$body     = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request body.' ), 400 );
		}

		$nonce = isset( $body['security'] ) ? sanitize_text_field( $body['security'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		if ( $this->is_rate_limited() ) {
			wp_send_json_error( array( 'message' => 'Rate limit exceeded.' ), 429 );
		}

		$events = isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();
		$fbclid = isset( $body['fbclid'] ) ? sanitize_text_field( $body['fbclid'] ) : '';

		$events = array_slice( $events, 0, self::MAX_EVENTS );

		$sent_count = 0;
		$now        = time();

		try {
			$pixel_id = facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id();
			$api      = facebook_for_woocommerce()->get_api();
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => 'Plugin not configured.' ), 500 );
		}

		$fbp        = null;
		$fbc        = null;
		$fbp_domain = null;

		try {
			$param_builder = \WC_Facebookcommerce_EventsTracker::get_param_builder();
			$fbp           = $param_builder->getFbp();
			$fbc           = $param_builder->getFbc();

			foreach ( $param_builder->getCookiesToSet() as $cookie ) {
				if ( '_fbp' === $cookie->name ) {
					$fbp_domain = $cookie->domain;
					break;
				}
			}
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Attribution is best-effort here.
		} catch ( \Exception $e ) {
			// Silently continue — attribution is best-effort.
		}

		if ( empty( $fbc ) && ! empty( $fbclid ) ) {
			$fbc = 'fb.1.' . time() . '.' . $fbclid;
		}

		$valid_events = array();
		foreach ( $events as $event_data ) {
			if ( ! $this->validate_event( $event_data, $now ) ) {
				continue;
			}

			$user_data = isset( $event_data['user_data'] ) && is_array( $event_data['user_data'] )
				? $this->sanitize_user_data( $event_data['user_data'] )
				: array();

			$server_event_data = $this->build_server_event_data( $event_data, $user_data, $fbc, $fbp );

			$valid_events[] = new Event( $server_event_data );
		}

		if ( ! empty( $valid_events ) ) {
			$this->record_rate_limit_usage( count( $valid_events ) );

			try {
				$api->send_pixel_events( $pixel_id, $valid_events );
				$sent_count = count( $valid_events );
			} catch ( ApiException $e ) {
				facebook_for_woocommerce()->log( 'Release signals: could not send events: ' . $e->getMessage() );
			}
		}

		wp_send_json_success(
			array(
				'fbp'        => $fbp,
				'fbc'        => $fbc,
				'fbp_domain' => $fbp_domain,
				'sent_count' => $sent_count,
			)
		);
	}

	/**
	 * Validates a single event from the queue.
	 *
	 * @param mixed $event_data The event data.
	 * @param int   $now        Current timestamp.
	 * @return bool
	 */
	private function validate_event( $event_data, $now ) {
		if ( ! is_array( $event_data ) ) {
			return false;
		}

		if ( empty( $event_data['event_name'] ) ) {
			return false;
		}

		if ( ! in_array( $event_data['event_name'], self::ALLOWED_EVENTS, true ) ) {
			return false;
		}

		if ( ! empty( $event_data['event_time'] ) ) {
			$event_time = absint( $event_data['event_time'] );
			if ( ( $now - $event_time ) > self::MAX_EVENT_AGE ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitizes custom data values.
	 *
	 * @param array $data Custom data.
	 * @return array
	 */
	private function sanitize_custom_data( $data ) {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			$key = sanitize_text_field( $key );
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_custom_data( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}

	/**
	 * Sanitizes user data values.
	 *
	 * @param array $data User data.
	 * @return array
	 */
	private function sanitize_user_data( $data ) {
		$allowed_keys = array(
			'em',
			'fn',
			'ln',
			'ph',
			'ct',
			'st',
			'zp',
			'country',
			'external_id',
			'click_id',
			'browser_id',
			'client_ip_address',
			'client_user_agent',
		);
		$sanitized    = array();
		foreach ( $data as $key => $value ) {
			$key = sanitize_text_field( $key );
			if ( in_array( $key, $allowed_keys, true ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}

	/**
	 * Builds a sanitized event payload for CAPI delivery.
	 *
	 * @param array       $event_data Raw event data from the queue.
	 * @param array       $user_data Sanitized user data.
	 * @param string|null $fbc Attribution click ID.
	 * @param string|null $fbp Attribution browser ID.
	 * @return array
	 */
	private function build_server_event_data( $event_data, $user_data, $fbc, $fbp ) {
		if ( ! empty( $fbc ) ) {
			$user_data['click_id'] = $fbc;
		}
		if ( ! empty( $fbp ) ) {
			$user_data['browser_id'] = $fbp;
		}

		$server_event_data = array(
			'event_name'  => sanitize_text_field( $event_data['event_name'] ),
			'custom_data' => isset( $event_data['custom_data'] ) && is_array( $event_data['custom_data'] )
				? $this->sanitize_custom_data( $event_data['custom_data'] )
				: array(),
			'user_data'   => $user_data,
		);

		if ( ! empty( $event_data['action_source'] ) ) {
			$server_event_data['action_source'] = sanitize_text_field( $event_data['action_source'] );
		}

		if ( ! empty( $event_data['event_source_url'] ) ) {
			$server_event_data['event_source_url'] = esc_url_raw( $event_data['event_source_url'] );
		}

		if ( ! empty( $event_data['referrer_url'] ) ) {
			$server_event_data['referrer_url'] = esc_url_raw( $event_data['referrer_url'] );
		}

		if ( ! empty( $event_data['event_id'] ) ) {
			$server_event_data['event_id'] = sanitize_text_field( $event_data['event_id'] );
		}

		if ( ! empty( $event_data['event_time'] ) ) {
			$server_event_data['event_time'] = absint( $event_data['event_time'] );
		}

		return $server_event_data;
	}

	/**
	 * Whether the current client is over its release-events rate limit.
	 *
	 * Counts accepted events (not requests) per IP per window. Both the
	 * window length and the cap are filterable.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	private function is_rate_limited() {
		$max = (int) apply_filters( 'wc_facebook_release_signals_rate_limit_max', self::RATE_LIMIT_MAX_EVENTS );

		if ( $max <= 0 ) {
			return false;
		}

		$key   = $this->get_rate_limit_key();
		$count = (int) get_transient( $key );

		return $count >= $max;
	}

	/**
	 * Records that the current client just had N events accepted, for rate-limit accounting.
	 *
	 * @since 3.6.0
	 *
	 * @param int $accepted_event_count Number of events accepted in this request.
	 */
	private function record_rate_limit_usage( $accepted_event_count ) {
		if ( $accepted_event_count <= 0 ) {
			return;
		}

		$window = (int) apply_filters( 'wc_facebook_release_signals_rate_limit_window', self::RATE_LIMIT_WINDOW );
		if ( $window <= 0 ) {
			return;
		}

		$key   = $this->get_rate_limit_key();
		$count = (int) get_transient( $key );

		set_transient( $key, $count + (int) $accepted_event_count, $window );
	}

	/**
	 * Builds a transient key scoped to the requesting client IP.
	 *
	 * @since 3.6.0
	 *
	 * @return string
	 */
	private function get_rate_limit_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return 'wc_fb_release_signals_rl_' . md5( $ip );
	}
}
