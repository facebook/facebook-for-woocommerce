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
use WooCommerce\Facebook\Framework\Helper;

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
		add_action( 'admin_footer', array( $this, 'render_message_handler' ) );
	}


	/**
	 * Renders the message handler script in the footer.
	 *
	 * @since 3.5.0
	 */
	public function render_message_handler() {
		if ( ! $this->is_whatsapp_admin_page() ) {
			return;
		}

		wp_add_inline_script( 'plugin-api-client', $this->generate_inline_enhanced_onboarding_script(), 'after' );
	}

	/**
	 * Enqueues the assets.
	 *
	 * @since 3.5.0
	 *
	 * @internal
	 */
	public function enqueue_assets() {

		if ( ! $this->is_whatsapp_admin_page() ) {
			return;
		}

		wp_enqueue_style( 'wc-facebook-admin-whatsapp-enhanced', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-whatsapp-enhanced.css', array(), \WC_Facebookcommerce::VERSION );
	}


	/**
	 * Adds the WhatsApp menu item.
	 *
	 * @since 3.5.0
	 */
	public function add_menu_item() {
		$rollout_switches                           = $this->plugin->get_rollout_switches();
		$is_connected                               = $this->plugin->get_connection_handler()->is_connected();
		$is_whatsapp_utility_messaging_beta_enabled = $rollout_switches->is_switch_enabled( RolloutSwitches::WHATSAPP_UTILITY_MESSAGING_BETA_EXPERIENCE );

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

		$this->connect_to_enhanced_admin( $this->is_marketing_enabled() ? 'marketing_page_wc-whatsapp' : 'woocommerce_page_wc-whatsapp' );
	}

	/**
	 * Enables admin support for the main WhatsApp settings page.
	 *
	 * @since 3.5.0
	 *
	 * @param string $screen_id
	 */
	private function connect_to_enhanced_admin( $screen_id ) {
		if ( is_callable( 'wc_admin_connect_page' ) ) {
			wc_admin_connect_page(
				array(
					'id'        => self::PAGE_ID,
					'screen_id' => $screen_id,
					'path'      => add_query_arg( 'page', self::PAGE_ID, 'admin.php' ),
					'title'     => [ __( 'WhatsApp for WooCommerce', 'facebook-for-woocommerce' ) ],
				)
			);
		}
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

		return is_callable( '\Automattic\WooCommerce\Admin\Features\Features::is_enabled' )
				&& \Automattic\WooCommerce\Admin\Features\Features::is_enabled( 'marketing' );
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
	 * Checks if the page is WhatsApp admin page.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	private function is_whatsapp_admin_page() {
		return is_admin() && self::PAGE_ID === Helper::get_requested_value( 'page' );
	}

	/**
	 * Renders the whatsapp utility settings page.
	 *
	 * @since 3.5.0
	 */
	public function render() {
		$whatsapp_connection = $this->plugin->get_whatsapp_connection_handler();
		$is_connected        = $whatsapp_connection->is_connected();

		if ( $is_connected ) {
			$iframe_url = \WooCommerce\Facebook\Handlers\WhatsAppExtension::generate_wa_iframe_management_url( $this->plugin, );
		} else {
			$iframe_url = \WooCommerce\Facebook\Handlers\WhatsAppExtension::generate_wa_iframe_splash_url(
				$this->plugin,
				$whatsapp_connection->get_whatsapp_external_id()
			);
		}

		if ( empty( $iframe_url ) ) {
			return $this->error_banner();
		}
		?>
		<div class="facebook-whatsapp-iframe-container">
			<iframe
				id="facebook-whatsapp-iframe-enhanced"
				src="<?php echo esc_url( $iframe_url ); ?>"
				></iframe>
		</div>
		<?php
	}

	private function error_banner() {
		?>
		<div class="facebook-whatsapp-iframe-error-container">
			<div class="notice notice-error" style="margin: 0; padding-bottom: 20px;">
				<h3><?php esc_html_e( 'WhatsApp Utility Connection Error', 'facebook-for-woocommerce' ); ?></h3>
				<p><?php esc_html_e( 'There was an error loading the WhatsApp Utility Message Integration. Please try reloading the page or resetting your settings.', 'facebook-for-woocommerce' ); ?></p>
				<div style="margin-top: 15px;">
					<button
						type="button"
						class="button button-primary"
						onclick="window.location.reload();"
						style="margin-right: 10px;"
					>
						<?php esc_html_e( 'Reload Page', 'facebook-for-woocommerce' ); ?>
					</button>
					<button
						type="button"
						class="button button-secondary"
						onclick="if(confirm('<?php echo esc_js( __( 'Are you sure you want to reset WhatsApp settings? This action cannot be undone and you will have to re-onboard.', 'facebook-for-woocommerce' ) ); ?>')) { resetWhatsAppSettings(); }"
					>
						<?php esc_html_e( 'Reset Settings', 'facebook-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			function resetWhatsAppSettings() {
				// Use the same API client that's available on the page
				if (typeof whatsAppAPI !== 'undefined') {
					whatsAppAPI.uninstallWhatsAppSettings()
						.then(function(response) {
							if (response.success) {
								window.location.reload();
							}
						})
						.catch(function(error) {
							console.error('Error during settings reset:', error);
							alert('<?php echo esc_js( __( 'Error resetting settings. Please try again or contact support.', 'facebook-for-woocommerce' ) ); ?>');
						});
				}
			}
		</script>
		<?php
	}

	/**
	 * Generates the inline script for the whatsapp iframe onboarding flow.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function generate_inline_enhanced_onboarding_script() {
		// Generate a fresh nonce for this request
		$nonce = wp_json_encode( wp_create_nonce( 'wp_rest' ) );

		// Create the inline script with HEREDOC syntax for better JS readability
		return <<<JAVASCRIPT
			const whatsAppAPI = GeneratePluginAPIClient({$nonce});
			window.addEventListener('message', function(event) {
				const message = event.data;
				const messageEvent = message.event;

				if (messageEvent === 'CommerceExtension::WA_INSTALL' && message.success) {

					const requestBody = {
						access_token: message.access_token,
						business_id: message.business_id,
						phone_number_id: message.phone_number_id,
						waba_id: message.waba_id,
						wa_installation_id: message.wa_installation_id,
					};

					whatsAppAPI.updateWhatsAppSettings(requestBody)
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

				if (messageEvent === 'CommerceExtension::WA_RESIZE') {
					const iframe = document.getElementById('facebook-whatsapp-iframe-enhanced');
					if ( iframe ) {
						if ( message.height ) {
							iframe.height = message.height;
						}
						if ( message.width ) {
							iframe.width = message.width;
						}
					}
				}

				if (messageEvent === 'CommerceExtension::WA_UNINSTALL') {
					whatsAppAPI.uninstallWhatsAppSettings()
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
