<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved

 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

use WooCommerce\Facebook\Framework\Api\Exception;
use WooCommerce\Facebook\Utilities\Heartbeat;

defined( 'ABSPATH' ) || exit;

/**
 * The rollout switches is used to control available
 * features in the Facebook for WooCommerce plugin.
 */
class RolloutSwitches {
	/** @var \WC_Facebookcommerce commerce handler */
	private \WC_Facebookcommerce $plugin;

	public const SWITCH_ROLLOUT_FEATURES    = 'rollout_enabled';
	public const WHATSAPP_UTILITY_MESSAGING = 'whatsapp_utility_messages_enabled';
	public const SWITCH_PRODUCT_SETS_SYNC_ENABLED = 'product_sets_sync_enabled';

	private const ACTIVE_SWITCHES = array(
		self::SWITCH_ROLLOUT_FEATURES,
		self::WHATSAPP_UTILITY_MESSAGING,
		self::SWITCH_PRODUCT_SETS_SYNC_ENABLED,
	);
	/**
	 * Stores the rollout switches and their enabled/disabled states.
	 *
	 * @var array
	 */
	private array $rollout_switches = array();

	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;
		add_action( Heartbeat::HOURLY, array( $this, 'init' ) );
	}

	public function init() {
		$is_connected = $this->plugin->get_connection_handler()->is_connected();
		if ( ! $is_connected ) {
			return;
		}

		try {
			$external_business_id = $this->plugin->get_connection_handler()->get_external_business_id();
			$switches             = $this->plugin->get_api()->get_rollout_switches( $external_business_id );
			$data                 = $switches->get_data();
			foreach ( $data as $switch ) {
				if ( ! isset( $switch['switch'] ) || ! $this->is_switch_active( $switch['switch'] ) ) {
					continue;
				}
				$this->rollout_switches[ $switch['switch'] ] = (bool) $switch['enabled'];
			}
		} catch ( Exception $e ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$e,
				[
					'event'      => 'rollout_switches',
					'event_type' => 'init',
				]
			);
		}
	}

	/**
	 * Get if the switch is enabled or not.
	 * If the switch is not active ->
	 *   FALSE
	 *
	 * If the switch is active but not in the response ->
	 *    TRUE: we assume this is an old version of the plugin
	 *    and the backend since has changed and the switch was released
	 *    in the backend we will otherwise always return false for unreleased
	 *    features
	 *
	 * If the feature is active and in the response ->
	 *   we will return the value of the switch from the response
	 *
	 * @param string $switch_name The name of the switch.
	 */
	public function is_switch_enabled( string $switch_name ) {
		if ( ! $this->is_switch_active( $switch_name ) ) {
			return false;
		}

		return isset( $this->rollout_switches[ $switch_name ] ) ? $this->rollout_switches[ $switch_name ] : true;
	}

	public function is_switch_active( string $switch_name ): bool {
		return in_array( $switch_name, self::ACTIVE_SWITCHES, true );
	}
}
