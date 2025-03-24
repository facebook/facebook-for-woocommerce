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
 * The Shops settings screen object.
 *
 * @since 3.2.0
 */
class Shops extends Abstract_Settings_Screen {

	/** @var string */
	const ID = 'shops';

	/**
	 * The Shops constructor.
	 *
	 * @since 3.2.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'initHook' ) );

		add_action( 'admin_footer', array( $this, 'render_message_handler' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Initialize this settings page's properties.
	 *
	 * @since 3.2.0
	 */
	public function initHook(): void {
		$this->id    = self::ID;
		$this->label = __( 'Shops', 'facebook-for-woocommerce' );
		$this->title = __( 'Shops', 'facebook-for-woocommerce' );
	}

	/**
	 * Enqueue the wp-api script and the Facebook REST API JavaScript client.
	 *
	 * @internal
	 */
	public function enqueue_admin_scripts() {
		if ( $this->is_current_screen_page() ) {
			wp_enqueue_script( 'wp-api' );
		}
	}

	/**
	 * Render the appropriate Facebook iframe based on connection status.
	 *
	 * @since 3.2.0
	 */
	public function render() {
		$connection            = facebook_for_woocommerce()->get_connection_handler();
		$is_connected          = $connection->is_connected();
		$merchant_access_token = get_option( 'wc_facebook_merchant_access_token', '' );

		if ( ! empty( $merchant_access_token ) && $is_connected ) {
			$iframe_url = \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_management_url(
				$connection->get_external_business_id()
			);
		} else {
			$iframe_url = \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_splash_url(
				$is_connected,
				$connection->get_plugin(),
				$connection->get_external_business_id()
			);
		}

		if ( empty( $iframe_url ) ) {
			return;
		}

		?>
	<div style="padding: 20px; background: white; display: flex; justify-content: center; min-height: 800px;">
		<iframe
		src="<?php echo esc_url( $iframe_url ); ?>"
		frameborder="0"
		style="background: white; border: none; max-width: 1100px; width: 90%;"
		id="facebook-commerce-iframe"></iframe>
	</div>
		<?php
	}

	/**
	 * Get the screen settings.
	 *
	 * @since 3.5.0
	 */
	public function get_settings() {}

	/**
	 * Render the message handler script in the footer.
	 *
	 * @since 3.5.0
	 */
	public function render_message_handler() {
		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		wp_add_inline_script( 'plugin-api-client', $this->generate_inline_enhanced_onboarding_script(), 'after' );
	}

	/**
	 * Generate inline script for the enhanced onboarding flow.
	 *
	 * @since 3.5.0
	 */
	public function generate_inline_enhanced_onboarding_script() {
		$nonce = wp_json_encode( wp_create_nonce( 'wp_rest' ) );

		return <<<JAVASCRIPT
			const fbAPI = GeneratePluginAPIClient({$nonce});
			window.addEventListener('message', function(event) {
				const message = event.data;
				const messageEvent = message.event;

				if (messageEvent === 'CommerceExtension::INSTALL' && message.success) {
					const requestBody = {
						access_token: message.access_token,
						merchant_access_token: message.access_token,
						page_access_token: message.access_token,
						product_catalog_id: message.catalog_id,
						pixel_id: message.pixel_id,
						page_id: message.page_id,
						business_manager_id: message.business_manager_id,
						commerce_merchant_settings_id: message.installed_features.find(f => f.feature_type === 'fb_shop')?.connected_assets?.commerce_merchant_settings_id || '',
						ad_account_id: message.installed_features.find(f => f.feature_type === 'ads')?.connected_assets?.ad_account_id || '',
						commerce_partner_integration_id: message.commerce_partner_integration_id || '',
						profiles: message.profiles,
						installed_features: message.installed_features
					};

					fbAPI.updateSettings(requestBody)
						.then(function(response) {
							if (response.success) {
								window.location.reload();
							} else {
								console.error('Error updating Facebook settings:', response);
							}
						})
						.catch(function(error) {
							console.error('Error during settings update:', error);
						});
				}

				if (messageEvent === 'CommerceExtension::RESIZE') {
					const iframe = document.getElementById('facebook-commerce-iframe');
					if (iframe && message.height) {
						iframe.height = message.height;
					}
				}

				if (messageEvent === 'CommerceExtension::UNINSTALL') {
					fbAPI.uninstallSettings()
						.then(function(response) {
							if (response.success) {
								window.location.reload();
							}
						})
						.catch(function(error) {
							console.error('Error during uninstall:', error);
							window.location.reload();
						});
				}
			});
		JAVASCRIPT;
	}
}
