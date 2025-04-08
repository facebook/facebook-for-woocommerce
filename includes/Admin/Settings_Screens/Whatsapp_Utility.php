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

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * The Whatsapp Utility settings screen object.
 */
class Whatsapp_Utility extends Abstract_Settings_Screen {


	/** @var string screen ID */
	const ID = 'whatsapp_utility';


	/**
	 * Whatsapp Utility constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'initHook' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Initializes this whatsapp utility settings page's properties.
	 */
	public function initHook(): void {
		$this->id    = self::ID;
		$this->label = __( 'WhatsApp Utility', 'facebook-for-woocommerce' );
		$this->title = __( 'Whatsapp Utility', 'facebook-for-woocommerce' );
	}

	/**
	 * Enqueue the assets.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function enqueue_assets() {

		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		wp_enqueue_style( 'wc-facebook-admin-whatsapp-settings', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-whatsapp-utility.css', array(), \WC_Facebookcommerce::VERSION );
		wp_enqueue_script(
			'facebook-for-woocommerce-connect-whatsapp',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-connection.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-consent',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-consent.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-consent',
			'facebook_for_woocommerce_whatsapp_consent',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-consent-nonce' ),
				'i18n'     => array(
					'result' => true,
				),
			)
		);
	}


	/**
	 * Renders the screen.
	 *
	 * @since 2.0.0
	 */
	public function render() {

		?>

	<div class="onboarding-card">
	<div class="card-item">
	<h1><b><?php esc_html_e( 'Send Updates to customers on WhatsApp', 'facebook-for-woocommerce' ); ?></b></h1>
		<?php esc_html_e( 'Send important updates and notifications directly to customers through WhatsApp.', 'facebook-for-woocommerce' ); ?>
	</div>
	<div class="divider"></div>
	<div class="card-item">
	<h2><?php esc_html_e( 'Get started with WhatsApp utility messages', 'facebook-for-woocommerce' ); ?></h2>
	<p><?php esc_html_e( 'Connect your WhatsApp Business Account to start sending utility messages.', 'facebook-for-woocommerce' ); ?></p>
	<div class="whatsapp-onboarding-button">
	<a
			id="woocommerce-whatsapp-connection"
			class="button button-primary"
			href="#"
		><?php esc_html_e( 'Connect Whatsapp Account', 'facebook-for-woocommerce' ); ?></a>
	</div>
	</div>
	<div class="divider"></div>
	<div class="card-item">
	<h2>Collect phone numbers at checkout</h2>
	<p>To collect phone numbers, a checkbox will be added to your store’s checkout page. This lets customers sign up to receive your messages. You can preview what this looks like in your checkout page preview.</p>
		<p>This will allow you to send messages to your customers on WhatsApp.</p>
		<div class="whatsapp-onboarding-button">
		<a
			class="button button-primary"
			id="wc-whatsapp-collect-consent"
			href="#"
		><?php esc_html_e( 'Collect', 'facebook-for-woocommerce' ); ?></a>
		</div>
	</div>
	<div class="divider"></div>
	<div class="card-item">
		<h2>Add a payment method</h2>
		<p>Confirm your payment method in Billings & payments.
				<a
					href="#"
					id="wc-whatsapp-about-pricing"
				><?php esc_html_e( 'About pricing', 'facebook-for-woocommerce' ); ?>
				</a>

			</p>
			<div class="add-payment-section">
				<div class="review-payment-block">
					<div class="review-payment-content">
					Add a payment method
					</div>
						<div class="add-payment-button">
							<a
								class="button button-primary"
								id="wc-whatsapp-add-payment"
								href="#"
								><?php esc_html_e( 'Add', 'facebook-for-woocommerce' ); ?>
							</a>
						</div>
				</div>
				<div class="whatsapp-onboarding-button">
					<a
						class="button button-primary"
						id="wc-whatsapp-onboarding-finish"
						href="#"
					><?php esc_html_e( 'Finish', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>

		</div>
	</div>
		<?php

		parent::render();
	}

	/**
	 * Gets the screen settings.
	 * Note: Need to implement this method to satisfy the interface.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_settings() {
		return array();
	}
}
