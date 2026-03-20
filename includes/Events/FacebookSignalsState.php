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

	/** @var array Attribution data captured while signals are held (e.g. fbclid). */
	private static $attribution_data = array();

	/**
	 * Hold signals for the current request.
	 */
	public static function hold() {
		self::$held = true;
	}

	/**
	 * Release signals for the current request.
	 */
	public static function release() {
		self::$held = false;
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
