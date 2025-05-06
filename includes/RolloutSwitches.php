<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved

 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

use WooCommerce\Facebook\Utilities\Heartbeat;

defined( 'ABSPATH' ) || exit;

/**
 * The rollout switches is used to control available
 * features in the Facebook for WooCommerce plugin.
 */
class RolloutSwitches {
	/** @var WooCommerce\Facebook\Commerce commerce handler */
	private \WC_Facebookcommerce $plugin;

	public const SWITCH_ROLLOUT_FEATURES = 'rollout_enabled';
	public const WHATSAPP_UTILITY_MESSAGING = 'whatsapp_utility_messages_enabled';

	private const ACTIVE_SWITCHES = array(
		self::SWITCH_ROLLOUT_FEATURES,
		self::WHATSAPP_UTILITY_MESSAGING,
	);
	/**
	 * Stores the rollout switches and their enabled/disabled states.
	 *
	 * @var array
	 */
	private $rollout_switches = array();

	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;
		// $this->init();
		add_action( Heartbeat::HOURLY, array( $this, 'init' ) );
	}

	public function init() {
		$external_business_id = $this->plugin->get_connection_handler()->get_external_business_id();
		if ( empty( $external_business_id ) ) {
			return;
		}

		$swiches = $this->plugin->get_api()->get_rollout_switches( $external_business_id );
		$data    = $swiches->get_data();
		foreach ( $data as $switch ) {
			if ( ! isset( $switch['switch'] ) || ! $this->is_switch_active( $switch['switch'] ) ) {
				continue;
			}
			$this->rollout_switches[ $switch['switch'] ] = (bool) $switch['enabled'];
		}

		wc_get_logger()->info(
			sprintf(
					/* translators: %s $response */
				 'WAUM rollout switches init: %1$s ',
				json_encode( $this->rollout_switches ),
			)
		);
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

		wc_get_logger()->info(
			sprintf(
					/* translators: %s $response */
				 'WAUM rollout switches: %1$s ',
				json_encode( $this->rollout_switches ),
			)
		);

		return isset($this->rollout_switches[ $switch_name ]) ? $this->rollout_switches[ $switch_name ] : true;
	}

	public function is_switch_active( string $switch_name ) {
		return in_array( $switch_name, self::ACTIVE_SWITCHES, true );
	}
}
