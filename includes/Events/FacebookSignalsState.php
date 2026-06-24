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

defined( 'ABSPATH' ) || exit;

/**
 * Static per-request state for held signal delivery.
 *
 * When signals are held, frontend events are queued and public-request CAPI
 * sends are suppressed until those signals are released again.
 *
 * @since 3.6.0
 */
class FacebookSignalsState {

	/** @var bool Whether signals are currently held for this request. */
	private static $held = false;

	/** @var array<string, array> Queued CAPI events keyed by event ID. */
	private static $queued_events = array();

	/** @var array Attribution data captured while signals are held (e.g. fbclid). */
	private static $attribution_data = array();

	/** @var string Cookie name for signal state. */
	const COOKIE_NAME = 'wc_facebook_signals_state';

	/**
	 * Hold signals for the current request and set the browser cookie
	 * so client-side JS knows the state on cached pages.
	 */
	public static function hold() {
		self::$held = true;
		self::set_state_cookie( 'held' );
	}

	/**
	 * Release signals for the current request and update the browser cookie.
	 */
	public static function release() {
		self::$held = false;
		self::set_state_cookie( 'active' );
	}

	/**
	 * Sets the signal-state cookie in the HTTP response headers.
	 *
	 * @param string $state 'held' or 'active'.
	 */
	private static function set_state_cookie( $state ) {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			$state,
			time() + YEAR_IN_SECONDS,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			false
		);

		$_COOKIE[ self::COOKIE_NAME ] = $state;
	}

	/**
	 * Queue a CAPI event for release when signals are unheld.
	 *
	 * @param Event $event Event to queue.
	 */
	public static function queue_event( Event $event ) {
		$event_id = $event->get_id();
		if ( empty( $event_id ) ) {
			return;
		}

		self::$queued_events[ $event_id ] = $event->get_data();
	}

	/**
	 * Get a queued CAPI event by event ID.
	 *
	 * @param string $event_id Event ID.
	 * @return array|null
	 */
	public static function get_queued_event( $event_id ) {
		return isset( self::$queued_events[ $event_id ] ) ? self::$queued_events[ $event_id ] : null;
	}

	/**
	 * Whether signals are currently held.
	 *
	 * Exposes a filter so external code can control the held state.
	 *
	 * @return bool
	 */
	public static function is_held() {
		/**
		 * Filters whether Facebook signals are currently held.
		 *
		 * @since 3.6.0
		 *
		 * @param bool $held Whether signals are held.
		 */
		return (bool) apply_filters( 'facebook_signals_held', self::$held );
	}

	/**
	 * Store attribution data captured while signals are held.
	 *
	 * @param string $key   Data key (e.g. 'fbclid').
	 * @param string $value Data value.
	 */
	public static function set_attribution_data( $key, $value ) {
		self::$attribution_data[ $key ] = $value;
	}

	/**
	 * Retrieve stored attribution data.
	 *
	 * @param string $key Data key.
	 * @return string|null
	 */
	public static function get_attribution_data( $key ) {
		return isset( self::$attribution_data[ $key ] ) ? self::$attribution_data[ $key ] : null;
	}
}
