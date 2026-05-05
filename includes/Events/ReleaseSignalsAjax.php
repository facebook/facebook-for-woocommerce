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

		// Signal release must work for guest shoppers as well as logged-in users.
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

		$events      = isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();
		$attribution = isset( $body['attribution'] ) && is_array( $body['attribution'] ) ? $body['attribution'] : array();
		$fbc         = $this->sanitize_attribution_value( $attribution, 'fbc' );
		$fbp         = $this->sanitize_attribution_value( $attribution, 'fbp' );

		$events = array_slice( $events, 0, self::MAX_EVENTS );

		$sent_count = 0;
		$now        = time();

		try {
			$pixel_id = facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id();
			$api      = facebook_for_woocommerce()->get_api();
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => 'Plugin not configured.' ), 500 );
		}

		$cookie_domains = $this->get_attribution_cookie_domains();

		// Expose held attribution to Event::get_click_id()/get_browser_id(), so
		// released events use the same attribution path as normal CAPI events.
		$restore_cookie_fbc = $this->temporarily_set_superglobal_value( '_COOKIE', '_fbc', $fbc );
		$restore_cookie_fbp = $this->temporarily_set_superglobal_value( '_COOKIE', '_fbp', $fbp );

		// Resolve attribution once via Event so cookie reads go through the same
		// path as normal CAPI events.
		$this->resolve_attribution_defaults( $fbc, $fbp );

		$valid_events = array();
		foreach ( $events as $event_data ) {
			if ( ! $this->validate_event( $event_data, $now ) ) {
				continue;
			}

			$user_data = isset( $event_data['user_data'] ) && is_array( $event_data['user_data'] )
				? $this->sanitize_user_data( $event_data['user_data'] )
				: array();

			$valid_events[] = $this->build_server_event( $event_data, $user_data );
		}

		$this->restore_superglobal_value( '_COOKIE', '_fbc', $restore_cookie_fbc );
		$this->restore_superglobal_value( '_COOKIE', '_fbp', $restore_cookie_fbp );

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
				'fbp_domain' => $cookie_domains['fbp'],
				'fbc_domain' => $cookie_domains['fbc'],
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
			if ( in_array( $key, $allowed_keys, true ) && ! is_array( $value ) && ! is_object( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}

	/**
	 * Sanitizes a single attribution value from the request payload.
	 *
	 * @param array  $data Attribution data.
	 * @param string $key  Attribution key.
	 * @return string
	 */
	private function sanitize_attribution_value( $data, $key ) {
		if ( ! isset( $data[ $key ] ) || is_array( $data[ $key ] ) || is_object( $data[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( $data[ $key ] );
	}

	/**
	 * Gets the cookie domains ParamBuilder would use for attribution cookies.
	 *
	 * This is returned to the browser when backend-provided attribution values
	 * need to be written after release, so they use the same domain scope as the
	 * normal ParamBuilder cookie path.
	 *
	 * @return array{fbp:string|null,fbc:string|null}
	 */
	private function get_attribution_cookie_domains() {
		$domains = array(
			'fbp' => null,
			'fbc' => null,
		);

		try {
			$param_builder = \WC_Facebookcommerce_EventsTracker::get_param_builder();

			foreach ( $param_builder->getCookiesToSet() as $cookie ) {
				if ( '_fbp' === $cookie->name ) {
					$domains['fbp'] = $cookie->domain;
				} elseif ( '_fbc' === $cookie->name ) {
					$domains['fbc'] = $cookie->domain;
				}
			}
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Attribution cookie domains are best-effort.
		} catch ( \Exception $e ) {
			// Silently continue — attribution cookie domains are best-effort.
		}

		return $domains;
	}

	/**
	 * Temporarily sets a superglobal value and returns data needed to restore it.
	 *
	 * @param string $superglobal Superglobal name without dollar sign.
	 * @param string $key         Key to set.
	 * @param string $value       Value to set.
	 * @return array
	 */
	private function temporarily_set_superglobal_value( $superglobal, $key, $value ) {
		$had_value = isset( $GLOBALS[ $superglobal ][ $key ] );
		$original  = $had_value ? $GLOBALS[ $superglobal ][ $key ] : null;

		if ( '' !== $value && null !== $value ) {
			$GLOBALS[ $superglobal ][ $key ] = $value;
		}

		return array(
			'had_value' => $had_value,
			'original'  => $original,
		);
	}

	/**
	 * Restores a superglobal value saved by temporarily_set_superglobal_value().
	 *
	 * @param string $superglobal Superglobal name without dollar sign.
	 * @param string $key         Key to restore.
	 * @param array  $restore     Restore data.
	 */
	private function restore_superglobal_value( $superglobal, $key, $restore ) {
		if ( ! empty( $restore['had_value'] ) ) {
			$GLOBALS[ $superglobal ][ $key ] = $restore['original'];
		} else {
			unset( $GLOBALS[ $superglobal ][ $key ] );
		}
	}

	/**
	 * Resolves missing fbc/fbp values via Event so released signals share the
	 * same attribution path (cookies) as normal CAPI events.
	 *
	 * @param string $fbc Attribution click ID reference.
	 * @param string $fbp Attribution browser ID reference.
	 */
	private function resolve_attribution_defaults( &$fbc, &$fbp ) {
		if ( ! empty( $fbc ) && ! empty( $fbp ) ) {
			return;
		}

		$resolver  = new Event( array( 'event_name' => 'PageView' ) );
		$user_data = $resolver->get_user_data();

		if ( empty( $fbc ) && ! empty( $user_data['click_id'] ) ) {
			$fbc = $user_data['click_id'];
		}

		if ( empty( $fbp ) && ! empty( $user_data['browser_id'] ) ) {
			$fbp = $user_data['browser_id'];
		}
	}

	/**
	 * Builds a sanitized Event object for CAPI delivery.
	 *
	 * @param array $event_data Raw event data from the queue.
	 * @param array $user_data  Sanitized user data.
	 * @return Event
	 */
	private function build_server_event( $event_data, $user_data ) {
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

		return new Event( $server_event_data );
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
