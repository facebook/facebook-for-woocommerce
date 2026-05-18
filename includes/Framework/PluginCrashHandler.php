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
 * Handles plugin crashes on PHP shutdown.
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
	 * Handles shutdown.
	 *
	 * @since 3.6.4
	 */
	public function handle_shutdown() {
		// Fatal detection and crash handling will be implemented in follow-up commits.
	}
}
