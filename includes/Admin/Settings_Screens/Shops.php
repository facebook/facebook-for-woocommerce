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
 * Shops settings screen object.
 *
 * @since 3.5.0
 */
class Shops extends Abstract_Settings_Screen {

	/** @var string */
	const ID = 'shops';

	/**
	 * Shops constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'initHook' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'add_notices' ) );
		add_action( 'admin_footer', array( $this, 'render_message_handler' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueues the wp-api script and the Facebook REST API JavaScript client.
	 *
	 * @since 3.5.0
	 *
	 * @internal
	 */
	public function enqueue_admin_scripts() {
		if ( $this->is_current_screen_page() ) {
			wp_enqueue_script( 'wp-api' );
		}
	}

	/**
	 * Initializes this settings page's properties.
	 *
	 * @since 3.5.0
	 */
	public function initHook(): void {
		$this->id    = self::ID;
		$this->label = __( 'Shops', 'facebook-for-woocommerce' );
		$this->title = __( 'Shops', 'facebook-for-woocommerce' );
	}

	/**
	 * Adds admin notices.
	 *
	 * @since 3.5.0
	 *
	 * @internal
	 */
	public function add_notices() {
		if ( get_transient( 'wc_facebook_connection_failed' ) ) {
			$message = sprintf(
			/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - <a> tag, %4$s - </a> tag, %5$s - <a> tag, %6$s - </a> tag */
				__( '%1$sHeads up!%2$s It looks like there was a problem with reconnecting your site to Facebook. Please %3$sclick here%4$s to try again, or %5$sget in touch with our support team%6$s for assistance.', 'facebook-for-woocommerce' ),
				'<strong>',
				'</strong>',
				'<a href="' . esc_url( facebook_for_woocommerce()->get_connection_handler()->get_connect_url() ) . '">',
				'</a>',
				'<a href="' . esc_url( facebook_for_woocommerce()->get_support_url() ) . '" target="_blank">',
				'</a>'
			);

			facebook_for_woocommerce()->get_admin_notice_handler()->add_admin_notice(
				$message,
				'wc_facebook_connection_failed',
				array(
					'notice_class' => 'error',
				)
			);

			delete_transient( 'wc_facebook_connection_failed' );
		}
	}


	/**
	 * Enqueues the assets.
	 *
	 * @since 3.5.0
	 *
	 * @internal
	 */
	public function enqueue_assets() {
		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		wp_enqueue_style( 'wc-facebook-admin-connection-settings', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-connection.css', array(), \WC_Facebookcommerce::VERSION );
	}


	/**
	 * Renders the screen.
	 *
	 * @since 3.5.0
	 */
	public function render() {
		$this->render_facebook_iframe();
	}

	/**
	 * Renders the appropriate Facebook iframe based on connection status.
	 *
	 * @since 3.5.0
	 */
	private function render_facebook_iframe() {
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
	<div style="display: flex; justify-content: center; max-width: 1200px; margin: 0 auto;">
		<iframe
		id="facebook-commerce-iframe-enhanced"
		src="<?php echo esc_url( $iframe_url ); ?>"
		></iframe>
	</div>
		<?php
	}

	/**
	 * Renders the message handler script in the footer.
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
	 * Generates the inline script for the enhanced onboarding flow.
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
					const iframe = document.getElementById('facebook-commerce-iframe-enhanced');
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


	/**
	 * Gets the screen settings.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function get_settings() {

		return array(

			array(
				'title' => __( 'Debug', 'facebook-for-woocommerce' ),
				'type'  => 'title',
			),

			array(
				'id'       => \WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE,
				'title'    => __( 'Enable debug mode', 'facebook-for-woocommerce' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Log plugin events for debugging.', 'facebook-for-woocommerce' ),
				/* translators: %s URL to the documentation page. */
				'desc_tip' => sprintf( __( 'Only enable this if you are experiencing problems with the plugin. <a href="%s" target="_blank">Learn more</a>.', 'facebook-for-woocommerce' ), 'https://woocommerce.com/document/facebook-for-woocommerce/#debug-tools' ),
				'default'  => 'no',
			),

			array(
				'id'       => \WC_Facebookcommerce_Integration::SETTING_ENABLE_NEW_STYLE_FEED_GENERATOR,
				'title'    => __( 'Experimental! Enable new style feed generation', 'facebook-for-woocommerce' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Use new, memory improved, feed generation process.', 'facebook-for-woocommerce' ),
				/* translators: %s URL to the documentation page. */
				'desc_tip' => sprintf( __( 'This is an experimental feature in testing phase. Only enable this if you are experiencing problems with feed generation. <a href="%s" target="_blank">Learn more</a>.', 'facebook-for-woocommerce' ), 'https://woocommerce.com/document/facebook-for-woocommerce/#feed-generation' ),
				'default'  => 'no',
			),
			array( 'type' => 'sectionend' ),
		);
	}
}
