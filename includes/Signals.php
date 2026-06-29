<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

use WooCommerce\Facebook\Events\FacebookSignalsState;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the public signal state for Meta Pixel/CAPI delivery.
 *
 * Stores a small first-party cookie and exposes a frontend bridge that can
 * hold or release signal delivery on the current site.
 *
 * @since 3.6.0
 */
class Signals {

	/** @var string Cookie name for signal state. */
	const COOKIE_NAME = 'wc_facebook_signals_state';

	/** @var string AJAX action for updating signal state. */
	const AJAX_ACTION = 'wc_facebook_update_signals_state';

	/** @var string Nonce action for signal state AJAX. */
	const NONCE_ACTION = 'wc_facebook_signals_state_nonce';

	/** @var string Signals are active. */
	const STATE_ACTIVE = 'active';

	/** @var string Signals are held. */
	const STATE_HELD = 'held';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_update_state' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_update_state' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Returns the current signal state from the cookie.
	 *
	 * @return string|null
	 */
	public static function get_signal_state() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$state = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		if ( in_array( $state, array( self::STATE_ACTIVE, self::STATE_HELD ), true ) ) {
			return $state;
		}

		return null;
	}

	/**
	 * Whether signals are currently active.
	 *
	 * @return bool
	 */
	public static function is_signals_active() {
		return self::STATE_ACTIVE === self::get_signal_state();
	}

	/**
	 * AJAX handler: updates the signal-state cookie.
	 *
	 * Expected POST params:
	 *  - security : nonce
	 *  - state    : 'active' or 'held'
	 */
	public function handle_update_state() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		$state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : self::STATE_HELD;
		$state = $this->normalize_state( $state );

		if ( self::STATE_HELD === $state ) {
			FacebookSignalsState::hold();
		} else {
			FacebookSignalsState::release();
		}

		wp_send_json_success(
			array(
				'state' => $state,
			)
		);
	}

	/**
	 * Whether signal delivery should currently be held.
	 *
	 * Undefined state is treated as active.
	 *
	 * @return bool
	 */
	public static function should_hold_signals(): bool {
		return self::STATE_HELD === self::get_signal_state();
	}

	/**
	 * Enqueues the frontend signal-state helper.
	 */
	public function enqueue_script() {
		if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected() ) {
			return;
		}

		wp_enqueue_script(
			'wc-facebook-signals',
			plugins_url( 'assets/js/facebook-for-woocommerce-signals.js', __DIR__ ),
			array(),
			\WC_Facebookcommerce::PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'wc-facebook-signals',
			'wc_facebook_signals_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'action'   => self::AJAX_ACTION,
			)
		);
	}

	/**
	 * Normalizes a signal state value.
	 *
	 * @param string $state Candidate state.
	 * @return string
	 */
	private function normalize_state( string $state ) {
		return self::STATE_ACTIVE === $state ? self::STATE_ACTIVE : self::STATE_HELD;
	}
}
