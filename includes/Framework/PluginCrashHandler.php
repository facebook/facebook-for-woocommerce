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
 * Handles plugin fatal crashes on PHP shutdown.
 *
 * @since 3.6.4
 */
class PluginCrashHandler {

	/**
	 * Registers crash handling hooks.
	 *
	 * @since 3.6.4
	 */
	public function register() {
		register_shutdown_function( [ $this, 'handle_shutdown' ] );
	}

	/**
	 * Captures fatal plugin crashes on shutdown.
	 *
	 * @since 3.6.4
	 */
	public function handle_shutdown() {
		$error = error_get_last();

		if ( ! $this->is_supported_fatal_error( $error ) ) {
			return;
		}

		if ( ! $this->is_plugin_error( $error ) ) {
			return;
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
}
