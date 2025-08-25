<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin;

use WooCommerce\Facebook\RolloutSwitches;
use WooCommerce\Facebook\Framework\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Admin WhatsApp Integration Settings handler.
 *
 * @since 3.5.0
 */
class WhatsApp_Integration_Settings {

	/** @var string */
	const PAGE_ID = 'wc-whatsapp';


	/** @var \WC_Facebookcommerce */
	private $plugin;


	/**
	 * WhatsApp Integration Settings constructor.
	 *
	 * @since 3.5.0
	 *
	 * @param \WC_Facebookcommerce $plugin is the plugin instance of WC_Facebookcommerce
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
	}


	/**
	 * Adds the WhatsApp menu item.
	 *
	 * @since 3.5.0
	 */
	public function add_menu_item() {
		$rollout_switches                           = $this->plugin->get_rollout_switches();
		$is_connected                               = $this->plugin->get_connection_handler()->is_connected();
		$is_whatsapp_utility_messaging_beta_enabled = $rollout_switches->is_switch_enabled( RolloutSwitches::WHATSAPP_UTILITY_MESSAGING_BETA_EXPERIENCE_DOGFOODING ); // TODO: update to prod GK during launch

		if ( ! $is_connected || ! $is_whatsapp_utility_messaging_beta_enabled ) {
			return;
		}

		$root_menu_item = $this->root_menu_item();

		add_submenu_page(
			$root_menu_item,
			__( 'WhatsApp for WooCommerce', 'facebook-for-woocommerce' ),
			__( 'WhatsApp', 'facebook-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_ID,
			[ $this, 'render' ],
			5
		);
	}

	/**
	 * Checks if marketing feature is enabled in woocommerce.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function is_marketing_enabled() {
		if ( class_exists( WooAdminFeatures::class ) ) {
			return WooAdminFeatures::is_enabled( 'marketing' );
		}

		return is_callable( '\Automattic\WooCommerce\Admin\Loader::is_feature_enabled' )
				&& \Automattic\WooCommerce\Admin\Loader::is_feature_enabled( 'marketing' );
	}

	/**
	 * Gets the root menu item.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function root_menu_item() {
		if ( $this->is_marketing_enabled() ) {
			return 'woocommerce-marketing';
		}

		return 'woocommerce';
	}

	/**
	 * Renders the whatsapp utility settings page.
	 *
	 * @since 3.5.0
	 */
	public function render() {
		?>
				<div style="display: flex; justify-content: center; max-width: 1200px; margin: 0 auto;">
					<h1><?php echo esc_html( 'Whatsapp Utility Screen' ); ?></h1>
				</div>
		<?php
	}
}
