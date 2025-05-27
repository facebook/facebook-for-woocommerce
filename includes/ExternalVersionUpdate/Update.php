<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\ExternalVersionUpdate;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Facebookcommerce_Utils;
use WooCommerce\Facebook\Utilities\Heartbeat;

/**
 * Facebook for WooCommerce External Plugin Version Update.
 *
 * Whenever this plugin gets updated, we need to inform the Meta server of the new version.
 * This is done by sending a request to the Meta server with the new version number.
 *
 * @since 3.0.10
 */
class Update {

	/** @var string Name of the option that stores the latest version that was sent to the Meta server. */
	const LATEST_VERSION_SENT = 'facebook_for_woocommerce_latest_version_sent_to_server';

	/**
	 * Update class constructor.
	 *
	 * @since 3.0.10
	 */
	public function __construct() {
		add_action( Heartbeat::DAILY, array( $this, 'maybe_update_external_plugin_version' ) );
	}

	/**
	 * Check if we need to inform the Meta server of a new version.
	 *
	 * @since 3.0.10
	 * @return bool
	 */
	public function maybe_update_external_plugin_version() {
		if ( ! $this->should_update_version() ) {
			return false;
		}

		return $this->send_new_version_to_facebook_server();
	}

	/**
	 * Checks if the plugin version needs to be updated.
	 *
	 * @since 3.0.10
	 * @return bool
	 */
	public function should_update_version() {
		$plugin = facebook_for_woocommerce();

		if ( ! $plugin->get_connection_handler()->is_connected() ) {
			// If the plugin is not connected, we don't need to send the version to the Meta server.
			return false;
		}

		return true;
	}

	/**
	 * Sends the latest plugin version to the Meta server.
	 *
	 * @since 3.0.10
	 * @return bool
	 */
	public function send_new_version_to_facebook_server() {

		$plugin = facebook_for_woocommerce();

		// Send the request to the Meta server with the latest plugin version.
		try {
			$external_business_id         = $plugin->get_connection_handler()->get_external_business_id();
			$is_woo_all_product_opted_out = $plugin->get_plugin_render_handler()->is_master_sync_on() === false;
			$response                     = $plugin->get_api()->update_plugin_version_configuration( $external_business_id, $is_woo_all_product_opted_out, WC_Facebookcommerce_Utils::PLUGIN_VERSION );
			if ( $response->has_api_error() ) {
				// If the request fails, we should retry it in the next heartbeat.
				return false;
			}
			return update_option( self::LATEST_VERSION_SENT, WC_Facebookcommerce_Utils::PLUGIN_VERSION );
		} catch ( Exception $e ) {
			WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( $e->getMessage() );
			// If the request fails, we should retry it in the next heartbeat.
			return false;
		}
	}
}
