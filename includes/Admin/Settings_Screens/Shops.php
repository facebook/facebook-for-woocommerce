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

	/** @var string */
	const ACTION_SYNC_PRODUCTS = 'wc_facebook_sync_products';

	/** @var string */
	const ACTION_SYNC_COUPONS = 'wc_facebook_sync_coupons';

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

		wp_enqueue_script(
			'wc-facebook-enhanced-settings-sync',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/enhanced-settings-sync.js',
			array( 'jquery' ),
			\WC_Facebookcommerce::PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'wc-facebook-enhanced-settings-sync',
			'wc_facebook_enhanced_settings_sync',
			array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'sync_products_nonce' => wp_create_nonce( self::ACTION_SYNC_PRODUCTS ),
				'sync_coupons_nonce'  => wp_create_nonce( self::ACTION_SYNC_COUPONS ),
			)
		);
	}

	/**
	 * Renders the screen.
	 *
	 * @since 3.5.0
	 */
	public function render() {
		$is_connected = facebook_for_woocommerce()->get_connection_handler()->is_connected();

		$this->render_facebook_iframe();

		if ( $is_connected ) {
			$this->render_troubleshooting_button_and_drawer();
		}
	}

	/**
	 * Renders the appropriate Facebook iframe based on connection status.
	 *
	 * @since 3.5.0
	 *
	 * @param bool $is_connected
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
	 * Renders the troubleshooting button and drawer.
	 *
	 * @since 3.5.0
	 */
	private function render_troubleshooting_button_and_drawer() {
		?>
	<!-- Toggle Button -->
	<div class="centered-container">
		<button id="toggle-troubleshooting-drawer" class="drawer-toggle-button">
			Troubleshooting
			<span id="caret" class="caret"></span>
		</button>
	</div>

	<!-- Drawer -->
	<div id="troubleshooting-drawer" class="settings-drawer" style="display: none;">
		<div class="settings-drawer-content">
			<table class="form-table">
				<tbody>
					<tr valign="top" class="wc-facebook-connected-sample">
						<th scope="row" class="titledesc">
							Product data sync
						</th>
						<td class="forminp">
							<button
								id="wc-facebook-enhanced-settings-sync-products"
								class="button"
								type="button">
								<?php esc_html_e( 'Sync now', 'facebook-for-woocommerce' ); ?>
							</button>
							<p id="product-sync-description" class="sync-description">
								Manually sync your products from WooCommerce to your shop. It may take a couple of minutes for the changes to populate.
							</p>
						</td>
					</tr>
					<tr valign="top" class="wc-facebook-connected-sample">
						<th scope="row" class="titledesc">
							Coupon codes sync
						</th>
						<td class="forminp">
							<button
								id="wc-facebook-enhanced-settings-sync-coupons"
								class="button"
								type="button">
								<?php esc_html_e( 'Sync now', 'facebook-for-woocommerce' ); ?>
							</button>
							<p id="coupon-sync-description" class="sync-description">
								Manually sync your coupons from WooCommerce to your shop. It may take a couple of minutes for the changes to populate.
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php parent::render(); ?>
		</div>
	</div>

	<style>
		.centered-container {
			display: flex;
			justify-content: center;
			align-items: center;
			width: 100%;
			margin-top: 20px;
		}

		.drawer-toggle-button {
			width: 100%;
			max-width: 1100px;
			background-color: #fff;
			border: 1px solid #ccc;
			padding: 10px 20px;
			text-align: left;
			cursor: pointer;
			font-size: 16px;
			font-weight: 600;
			position: relative;
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20px;
			box-sizing: border-box;
		}

		.drawer-toggle-button:hover {
			background-color: #f9f9f9;
		}

		.caret {
			width: 0;
			height: 0;
			border-left: 5px solid transparent;
			border-right: 5px solid transparent;
			border-top: 5px solid #000;
			transition: transform 0.3s ease;
		}

		.settings-drawer {
			width: 100%;
			max-width: 1100px;
			background-color: #fff;
			border-bottom: 1px solid #ccc;
			border-right: 1px solid #ccc;
			border-left: 1px solid #ccc;
			overflow: hidden;
			transition: max-height 0.3s ease, margin-bottom 0.3s ease;
			max-height: 0;
			margin: 0 auto;
			box-sizing: border-box;
		}

		.settings-drawer-content {
			padding: 20px;
			padding-bottom: 0;
		}

		.button:disabled {
			background-color: #f1f1f1;
			cursor: not-allowed;
		}

		.sync-description {
			font-size: 12px;
			color: #666;
			padding-top: 8px;
		}
	</style>

	<script>
		// Toggle drawer visibility
		document.getElementById('toggle-troubleshooting-drawer').addEventListener('click', function() {
			var drawer = document.getElementById('troubleshooting-drawer');
			var caret = document.getElementById('caret');
			var button = document.getElementById('toggle-troubleshooting-drawer');

			if (drawer.style.maxHeight === '0px' || drawer.style.maxHeight === '') {
				drawer.style.display = 'block';
				drawer.style.maxHeight = drawer.scrollHeight + 'px';
				caret.style.transform = 'rotate(180deg)';
				drawer.style.marginBottom = '20px';
				button.style.marginBottom = '0';
			} else {
				drawer.style.maxHeight = '0';
				setTimeout(function() {
					drawer.style.display = 'none';
				}, 300);
				caret.style.transform = 'rotate(0deg)';
				drawer.style.marginBottom = '0';
				button.style.marginBottom = '20px';
			}
		});
	</script>
		<?php
	}

	/**
	 * Gets the screen settings.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_settings() {
		return array(
			array(
				//phpcs:ignore WordPress.WP.I18n.NoEmptyStrings
				'title' => __( '', 'facebook-for-woocommerce' ),
				'type'  => 'title',
			),

			array(
				'id'       => \WC_Facebookcommerce_Integration::SETTING_ENABLE_META_DIAGNOSIS,
				'title'    => __( 'Upload plugin events to Meta', 'facebook-for-woocommerce' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Enable', 'facebook-for-woocommerce' ),
				'desc_tip' => sprintf( __( 'Allow Meta to monitor your logs and help fix issues. Personally identifiable information will not be collected.', 'facebook-for-woocommerce' ) ),
				'default'  => 'yes',
			),

			array(
				'id'       => \WC_Facebookcommerce_Integration::SETTING_ENABLE_DEBUG_MODE,
				'title'    => __( 'Log plugin events to your computer for debugging', 'facebook-for-woocommerce' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Enable', 'facebook-for-woocommerce' ),
				/* translators: %s URL to the documentation page. */
				'desc_tip' => sprintf( __( 'Only enable this if you are experiencing problems with the plugin. <a href="%s" target="_blank">Learn more</a>.', 'facebook-for-woocommerce' ), 'https://woocommerce.com/document/facebook-for-woocommerce/#debug-tools' ),
				'default'  => 'no',
			),

			array(
				'id'       => \WC_Facebookcommerce_Integration::SETTING_ENABLE_NEW_STYLE_FEED_GENERATOR,
				'title'    => __( '[Experimental] Use new, memory improved, feed generation process', 'facebook-for-woocommerce' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Enable', 'facebook-for-woocommerce' ),
				/* translators: %s URL to the documentation page. */
				'desc_tip' => sprintf( __( 'This is an experimental feature in testing phase. Only enable this if you are experiencing problems with feed generation. <a href="%s" target="_blank">Learn more</a>.', 'facebook-for-woocommerce' ), 'https://woocommerce.com/document/facebook-for-woocommerce/#feed-generation' ),
				'default'  => 'no',
			),

			array( 'type' => 'sectionend' ),
		);
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
}
