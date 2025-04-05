<?php

/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin\Settings_Screens;

defined('ABSPATH') || exit;

use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * The Whatsapp Utility settings screen object.
 */
class Whatsapp_Utility extends Abstract_Settings_Screen
{


	/** @var string screen ID */
	const ID = 'whatsapp_utility';

	/** @var flag to test Utility Messages Overview changes until check for integration config is implemented */
	const WHATSAPP_UTILITY_MESSAGES_OVERVIEW_FLAG = false;

	/**
	 * Whatsapp Utility constructor.
	 */
	public function __construct()
	{
		add_action('init', array($this, 'initHook'));

		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Initializes this whatsapp utility settings page's properties.
	 */
	public function initHook(): void
	{
		$this->id    = self::ID;
		$this->label = __('WhatsApp Utility', 'facebook-for-woocommerce');
		$this->title = __('Whatsapp Utility', 'facebook-for-woocommerce');
	}

	/**
	 * Enqueue the assets.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function enqueue_assets()
	{

		if (! $this->is_current_screen_page()) {
			return;
		}

		wp_enqueue_style('wc-facebook-admin-whatsapp-settings', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-whatsapp-utility.css', array(), \WC_Facebookcommerce::VERSION);
		wp_enqueue_script(
			'facebook-for-woocommerce-connect-whatsapp',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-connection.js',
			array('jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select'),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
	}


	/**
	 * Renders the screen.
	 *
	 * @since 2.0.0
	 */

	public function render()
	{
		if(self::WHATSAPP_UTILITY_MESSAGES_OVERVIEW_FLAG){
			$this->render_utility_message_overview();
		}
		else{
			$this->render_utility_message_onboarding();
		}
		
        parent::render();
	}

	public function render_utility_message_onboarding(){
        ?>
    <div class="onboarding-card">
    <h2><?php esc_html_e( 'Get started with WhatsApp utility messages', 'facebook-for-woocommerce' ); ?></h2>
    <p><?php esc_html_e( 'Connect your WhatsApp Business Account to start sending utility messages.', 'facebook-for-woocommerce' ); ?></p>
    <a
            id="woocommerce-whatsapp-connection"
            class="connect-button"
            href="#"
        ><?php esc_html_e( 'Connect Whatsapp Account', 'facebook-for-woocommerce' ); ?></a>
    </div>
        <?php

	}

	public function render_utility_message_overview(){
		?>
		<div class="onboarding-card">
			<h1><?php esc_html_e('Utility Messages', 'facebook-for-woocommerce'); ?></h2>
				<p><?php esc_html_e('Manage which utility messages you want to send to customers. You can check performance of these messages in Whatsapp Manager.', 'facebook-for-woocommerce'); ?>
					<a
						id="woocommerce-whatsapp-manager-insights"
						href="#"><?php esc_html_e('View insights', 'facebook-for-woocommerce'); ?></a>
				</p>
				<hr />
				<div style="overflow: hidden">
					<div style="float: left;">
						<h3><?php esc_html_e('Order confirmation', 'facebook-for-woocommerce'); ?></h3>
						<p><?php esc_html_e('Send a confirmation to customers after they\'ve placed an order.'); ?></p>
					</div>
					<a
						id="woocommerce-whatsapp-manage-order-confirmation"
						class="button"
						href="#"
						style="float: right; margin-top: 30px; margin-right: 30px;"><?php esc_html_e('Manage', 'facebook-for-woocommerce'); ?></a>
				</div>
				<hr />
				<div style="overflow: hidden">
					<div style="float: left;">
						<h3><?php esc_html_e('Order shipped', 'facebook-for-woocommerce'); ?></h3>
						<p><?php esc_html_e('Send a confirmation to customers when their order is shipped.'); ?></p>
					</div>
					<a
						id="woocommerce-whatsapp-manage-order-shipped"
						class="button"
						href="#"
						style="float: right; margin-top: 30px; margin-right: 30px;"><?php esc_html_e('Manage', 'facebook-for-woocommerce'); ?></a>
				</div>
				<hr />
				<div style="overflow: hidden">
					<div style="float: left;">
						<h3><?php esc_html_e('Order refunded', 'facebook-for-woocommerce'); ?></h3>
						<p><?php esc_html_e('Send a confirmation to customers when an order is refunded.'); ?></p>
					</div>
					<a
						id="woocommerce-whatsapp-manage-order-refunded"
						class="button"
						href="#"
						style="float: right; margin-top: 30px; margin-right: 30px;"><?php esc_html_e('Manage', 'facebook-for-woocommerce'); ?></a>
				</div>
		</div>
<?php

	}

	/**
	 * Gets the screen settings.
	 * Note: Need to implement this method to satisfy the interface.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_settings()
	{
		return array();
	}
}
