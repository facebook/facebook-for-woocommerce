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
use WooCommerce\Facebook\Framework\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * The rollout switches is used to control available
 * features in the Facebook for WooCommerce plugin.
 */
class RolloutSwitches {
	/** @var \WC_Facebookcommerce commerce handler */
	private \WC_Facebookcommerce $plugin;

	public const SWITCH_ROLLOUT_FEATURES              = 'rollout_enabled';
	public const WHATSAPP_UTILITY_MESSAGING           = 'whatsapp_utility_messages_enabled';
	public const SWITCH_PRODUCT_SETS_SYNC_ENABLED     = 'product_sets_sync_enabled';
	public const SWITCH_WOO_ALL_PRODUCTS_SYNC_ENABLED = 'woo_all_products_sync_enabled';
	public const SWITCH_OFFER_MANAGEMENT_ENABLED      = 'offer_management_enabled';
	private const SETTINGS_KEY                        = 'wc_facebook_for_woocommerce_rollout_switches';

	private const ACTIVE_SWITCHES = array(
		self::SWITCH_ROLLOUT_FEATURES,
		self::WHATSAPP_UTILITY_MESSAGING,
		self::SWITCH_PRODUCT_SETS_SYNC_ENABLED,
		self::SWITCH_WOO_ALL_PRODUCTS_SYNC_ENABLED,
		self::SWITCH_OFFER_MANAGEMENT_ENABLED,
	);

	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;
	}

	public function init() {
		$is_connected = $this->plugin->get_connection_handler()->is_connected();
		if ( ! $is_connected ) {
			return;
		}

		$flag_name = '_wc_facebook_for_woocommerce_rollout_switch_flag';
		if ( 'yes' === get_transient( $flag_name ) ) {
			return;
		}
		set_transient( $flag_name, 'yes', 60 * MINUTE_IN_SECONDS );

		try {
			$external_business_id = $this->plugin->get_connection_handler()->get_external_business_id();
			$switches             = $this->plugin->get_api()->get_rollout_switches( $external_business_id );
			$data                 = $switches->get_data();
			if ( empty( $data ) ) {
				throw new Exception( 'Empty data' );
			}
			$fb_options = array();
			foreach ( $data as $switch ) {
				if ( ! isset( $switch['switch'] ) || ! $this->is_switch_active( $switch['switch'] ) ) {
					continue;
				}
				$fb_options[ $switch['switch'] ] = (bool) $switch['enabled'] ? 'yes' : 'no';
			}
			update_option( self::SETTINGS_KEY, $fb_options );
		} catch ( Exception $e ) {
			$fb_options = get_option( self::SETTINGS_KEY );
			if ( empty( $fb_options ) ) {
				$fb_options = array();
			}
			foreach ( $this->get_active_switches() as $switch_name ) {
				// if the switch is not in the response and we have a failure
				// we fallback to the old value first and false otherwise
				if ( ! isset( $fb_options[ $switch_name ] ) ) {
					$fb_options[ $switch_name ] = 'no';
				}
			}
			update_option( self::SETTINGS_KEY, $fb_options );
			Logger::log(
				$e->getMessage(),
				array(
					'flow_name' => 'rollout_switches',
					'flow_step' => 'init',
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
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
		$features = get_option( self::SETTINGS_KEY );
		if ( empty( $features ) ) {
			return false;
		}

		if ( ! isset( $features[ $switch_name ] ) ) {
			return true;
		}

		return 'yes' === $features[ $switch_name ] ? true : false;
	}

	public function is_switch_active( string $switch_name ): bool {
		return in_array( $switch_name, self::ACTIVE_SWITCHES, true );
	}

	public function get_active_switches(): array {
		return self::ACTIVE_SWITCHES;
	}
}
