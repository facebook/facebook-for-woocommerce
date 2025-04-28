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
use WooCommerce\Facebook\Framework\Helper;

/**
 * The Whatsapp Utility settings screen object.
 */
class Whatsapp_Utility extends Abstract_Settings_Screen {

	/** @var string page ID */
	const PAGE_ID = 'wc-facebook';

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
		$this->label = __( 'Utility messages', 'facebook-for-woocommerce' );
		$this->title = __( 'Utility messages', 'facebook-for-woocommerce' );
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
		$waba_id            = get_option( 'wc_facebook_wa_integration_waba_id', '' );
		$whatsapp_connected = ! empty( $waba_id );
		wp_localize_script(
			'facebook-for-woocommerce-connect-whatsapp',
			'facebook_for_woocommerce_whatsapp_onboarding_progress',
			array(
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'nonce'                        => wp_create_nonce( 'facebook-for-wc-whatsapp-onboarding-progress-nonce' ),
				'whatsapp_onboarding_complete' => $whatsapp_connected,
				'i18n'                         => array(
					'result' => true,
				),
			)
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-consent',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-consent.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		$consent_collection_enabled = get_option( 'wc_facebook_whatsapp_consent_collection_setting_status', null ) === 'enabled';
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-consent',
			'facebook_for_woocommerce_whatsapp_consent',
			array(
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'nonce'                        => wp_create_nonce( 'facebook-for-wc-whatsapp-consent-nonce' ),
				'whatsapp_onboarding_complete' => $whatsapp_connected,
				'consent_collection_enabled'   => $consent_collection_enabled,
				'i18n'                         => array(
					'result' => true,
				),
			)
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-billing',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-billing.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-billing',
			'facebook_for_woocommerce_whatsapp_billing',
			array(
				'ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'nonce'                      => wp_create_nonce( 'facebook-for-wc-whatsapp-billing-nonce' ),
				'consent_collection_enabled' => $consent_collection_enabled,
				'i18n'                       => array(
					'result' => true,
				),
			)
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-events',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-events.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-events',
			'facebook_for_woocommerce_whatsapp_events',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-events-nonce' ),
				'i18n'     => array(
					'result' => true,
				),
			)
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-finish',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-finish.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
			wp_localize_script(
				'facebook-for-woocommerce-whatsapp-finish',
				'facebook_for_woocommerce_whatsapp_finish',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-finish-nonce' ),
					'i18n'     => array( // will generate i18 pot translation
						'payment_setup_error'         => __( 'To proceed, add a payment method to make future purchases on your accounts.', 'facebook-for-woocommerce' ),
						'onboarding_incomplete_error' => __( 'Whatsapp Business Account Onboarding is not complete or has failed.', 'facebook-for-woocommerce' ),
						'generic_error'               => __( 'Something went wrong. Please try again.', 'facebook-for-woocommerce' ),
					),
				)
			);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-consent-remove',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-consent-remove.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-consent-remove',
			'facebook_for_woocommerce_whatsapp_consent_remove',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-consent-disable-nonce' ),
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
		$view = $this->get_current_view();
		if ( 'utility_settings' === $view ) {
			$this->render_utility_message_overview();
		} elseif ( 'manage_event' === $view ) {
			$this->render_manage_events_view();
		} else {
			$this->render_utility_message_onboarding();
		}
		parent::render();
	}

	/**
	 * Renders the WhatsApp Utility Onboarding screen.
	 */
	public function render_utility_message_onboarding() {

		?>

	<div class="onboarding-card">
		<div class="card-item">
			<div class="card-content">
				<h1><?php esc_html_e( 'Send Updates to customers on WhatsApp', 'facebook-for-woocommerce' ); ?></h1>
				<?php esc_html_e( 'Send important updates and notifications directly to customers on WhatsApp.', 'facebook-for-woocommerce' ); ?>
			</div>
		</div>
		<div class="divider"></div>
		<div class="card-item">
			<div class="card-content-icon">
				<div id="wc-fb-whatsapp-connect-success" class="custom-dashicon-check fbwa-hidden-element"></div>
				<div id="wc-fb-whatsapp-connect-inprogress" class="custom-dashicon-halfcircle fbwa-hidden-element"></div>
				<div class="card-content">
					<h2><?php esc_html_e( 'Connect your WhatApp Business account', 'facebook-for-woocommerce' ); ?></h2>
					<p id="wc-fb-whatsapp-onboarding-subcontent"><?php esc_html_e( 'Allows WooCommerce to connect to your WhatsApp account. ', 'facebook-for-woocommerce' ); ?></p>
				</div>
			</div>
			<div id="wc-fb-whatsapp-onboarding-button-wrapper" class="whatsapp-onboarding-button">
				<a
					id="woocommerce-whatsapp-connection"
					class="button"
					href="#"
				><?php esc_html_e( 'Connect', 'facebook-for-woocommerce' ); ?></a>
			</div>
		</div>
		<div class="divider"></div>
		<div class="card-item">
			<div class="card-content-icon">
				<div id="wc-fb-whatsapp-consent-collection-notstarted" class="custom-dashicon-circle fbwa-hidden-element"></div>
				<div id="wc-fb-whatsapp-consent-collection-success" class="custom-dashicon-check fbwa-hidden-element"></div>
				<div id="wc-fb-whatsapp-consent-collection-inprogress" class="custom-dashicon-halfcircle fbwa-hidden-element"></div>
				<div class="card-content">
					<h2><?php esc_html_e( 'Add WhatsApp option at checkout', 'facebook-for-woocommerce' ); ?></h2>
					<p id="wc-fb-whatsapp-consent-subcontent"><?php esc_html_e( 'Adds a checkbox to your store’s checkout page that lets customers request updates about their order on WhatsApp. This allows you to communicate with customers after they make a purchase. You can remove this anytime.', 'facebook-for-woocommerce' ); ?></p>
				</div>
			</div>
			<div id="wc-fb-whatsapp-consent-button-wrapper" class="whatsapp-onboarding-button">
			<a
				class="button"
				id="wc-whatsapp-collect-consent"
				href="#"
			><?php esc_html_e( 'Add', 'facebook-for-woocommerce' ); ?></a>
			</div>
		</div>
		<div class="divider"></div>
		<div class="card-item">
			<div class="card-content-icon">
				<div id="wc-fb-whatsapp-billing-notstarted" class="custom-dashicon-circle fbwa-hidden-element"></div>
				<div id="wc-fb-whatsapp-billing-inprogress" class="custom-dashicon-halfcircle fbwa-hidden-element"></div>
				<div class="card-content">
					<h2><?php esc_html_e( 'Add a payment method', 'facebook-for-woocommerce' ); ?></h2>
					<div id="wc-fb-whatsapp-billing-subcontent">
						<p><?php esc_html_e( 'Review and update your payment method in Billings & payments.', 'facebook-for-woocommerce' ); ?>
							<a
								href="https://developers.facebook.com/docs/whatsapp/pricing/#rate-cards"
								id="wc-whatsapp-about-pricing"
								target="_blank"
							><?php esc_html_e( 'About pricing', 'facebook-for-woocommerce' ); ?>
							</a>
						</p>
					</div>
				</div>
			</div>
			<div id="wc-fb-whatsapp-billing-button-wrapper" class="whatsapp-onboarding-button">
				<a
					class="button"
					id="wc-whatsapp-add-payment"
					href="#"
				><?php esc_html_e( 'Review', 'facebook-for-woocommerce' ); ?></a>
			</div>
		</div>
		<div class="error-notice-wrapper">
			<div id="payment-method-error-notice"></div>
		</div>
		<div class="divider"></div>
		<div class="card-item">
			<div class="whatsapp-onboarding-button">
				<a
					class="button button-primary"
					id="wc-whatsapp-onboarding-finish"
					href="#"
				><?php esc_html_e( 'Done', 'facebook-for-woocommerce' ); ?></a>
			</div>
		</div>
	</div>
		<?php
	}

	/**
	 * Renders the WhatsApp Utility Overview screen.
	 */
	public function render_utility_message_overview() {
		?>
		<div class="onboarding-card">
			<div class="card-item">
				<h1><?php esc_html_e( 'Utility Messages', 'facebook-for-woocommerce' ); ?></h1>
				<p><?php esc_html_e( 'Manage which utility messages you want to send to customers. You can check performance of these messages in Whatsapp Manager.', 'facebook-for-woocommerce' ); ?>
					<a
						id="woocommerce-whatsapp-manager-insights"
						href="#"><?php esc_html_e( 'View insights', 'facebook-for-woocommerce' ); ?></a>
				</p>
			</div>
			<div class="divider"></div>
			<div class="card-item event-config">
				<div>
					<div class="event-config-heading-container">
						<h3><?php esc_html_e( 'Order confirmation', 'facebook-for-woocommerce' ); ?></h3>
						<div class="event-config-status on-status">
							<?php esc_html_e( 'On', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<p><?php esc_html_e( 'Send a confirmation to customers after they\'ve placed an order.', 'facebook-for-woocommerce' ); ?></p>
				</div>
				<div class="event-config-manage-button">
					<a
						id="woocommerce-whatsapp-manage-order-confirmation"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Manage', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>
			<div class="divider"></div>
			<div class="card-item event-config">
				<div>
					<div class="event-config-heading-container">
						<h3><?php esc_html_e( 'Order shipped', 'facebook-for-woocommerce' ); ?></h3>
						<div class="event-config-status">
							<?php esc_html_e( 'Off', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<p><?php esc_html_e( 'Send a confirmation to customers when their order is shipped.', 'facebook-for-woocommerce' ); ?></p>
				</div>
				<div class="event-config-manage-button">
					<a
						id="woocommerce-whatsapp-manage-order-shipped"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Manage', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>
			<div class="divider"></div>
			<div class="card-item event-config">
				<div>
					<div class="event-config-heading-container">
						<h3><?php esc_html_e( 'Order refunded', 'facebook-for-woocommerce' ); ?></h3>
						<div class="event-config-status">
							<?php esc_html_e( 'Off', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<p><?php esc_html_e( 'Send a confirmation to customers when an order is refunded.', 'facebook-for-woocommerce' ); ?></p>
				</div>
				<div class="event-config-manage-button">
					<a
						id="woocommerce-whatsapp-manage-order-refunded"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Manage', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>
			<div class="divider"></div>
		</div>
		<div class="onboarding-card">
			<div class="card-item event-config">
					<div>
						<div class="event-config-heading-container">
							<h3><?php esc_html_e( 'Add WhatsApp option at checkout', 'facebook-for-woocommerce' ); ?></h3>
							<div class="event-config-status on-status">
								<?php esc_html_e( 'On', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<span class="consent-update-card-subcontent">
						<?php esc_html_e( 'Adds a checkbox to your store\'s checkout page that lets customers request updates about their order on WhatsApp. This allows you to communicate with customers after they make a purchase. You can preview what this looks like ', 'facebook-for-woocommerce' ); ?>
						<a
							href="<?php echo admin_url( 'post.php?post=' . get_option( 'woocommerce_checkout_page_id' ) . '&action=edit' ); ?>"
							id="wc-whatsapp-checkout-preview"
							target="_blank"
						><?php esc_html_e( 'checkout preview.', 'facebook-for-woocommerce' ); ?>
						</a>
					</span>
					<div class="event-config-manage-button">
						<a
							id="wc-whatsapp-collect-consent-remove"
							class="event-config-manage-button button"
							href="#"><?php esc_html_e( 'Remove', 'facebook-for-woocommerce' ); ?></a>
					</div>
				</div>
			</div>
			<div id="wc-fb-warning-modal" class="warning-custom-modal">
				<div class="warning-modal-content">
					<h2><?php esc_html_e( 'Stop sending messages to customers ?', 'facebook-for-woocommerce' ); ?></h2>
				<div class="warning-modal-body">
				<?php esc_html_e( 'Removing this means customers won\'t be able to receive WhatsApp messages from your business. You\'ll remove the checkbox from your checkout page and stop collecting phone numbers from customers.', 'facebook-for-woocommerce' ); ?>
				</div>
				<div class="warning-modal-footer">
					<button id="wc-fb-warning-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></button>
					<button id="wc-fb-warning-modal-confirm" class="button button-primary"><?php esc_html_e( 'Remove', 'facebook-for-woocommerce' ); ?></button>
				</div>
				</div>
			</div>
		</div>
		<?php
	}


	/**
	 * Renders the view to manage WhatsApp Utility Events.
	 */
	public function render_manage_events_view() {
		?>
		<div class="onboarding-card">
			<div class="card-item">
				<h1><b><?php esc_html_e( 'Manage order confirmation message', 'facebook-for-woocommerce' ); ?></b></h1>
				<p><?php esc_html_e( 'Send a confirmation to customers after they\'ve placed an order.', 'facebook-for-woocommerce' ); ?></p>
			</div>
			<div class="divider"></div>
			<div class="card-item">
				<p><b><?php esc_html_e( 'Select a language', 'facebook-for-woocommerce' ); ?></b></p>
				<select id="manage-event-language">
					<option value="en_US">English (US)</option>
					<option value="en_UK">English (UK)</option>
				</select>
			</div>
			<div class="card-item">
				<div class="manage-event-template-block">
					<div class="manage-event-template-header">
						<input type="radio" name="template-status" value="on" />
						<label for="template-status"><b><?php esc_html_e( 'Send order confirmation message', 'facebook-for-woocommerce' ); ?> </b></label>
					</div>
					<div class="divider"></div>
					<div class="card-item fbwa-hidden-element" id="library-template-content"></div>
				</div>
				<div class="manage-event-template-block">
					<div class="manage-event-template-header">
						<input type="radio" name="template-status" value="off" />
						<label for="template-status"><b><?php esc_html_e( 'Turn off order confirmation', 'facebook-for-woocommerce' ); ?> </b></label>
					</div>
				</div>
			</div>
			<div class="card-item manage-event-template-footer">
				<div class="manage-event-button">
					<a
						id="woocommerce-whatsapp-save-order-confirmation"
						class="button button-primary"
						href="#"><?php esc_html_e( 'Save', 'facebook-for-woocommerce' ); ?>
					</a>
				</div>
				<div class="manage-event-button">
					<a
						id="woocommerce-whatsapp-cancel-order-confirmation"
						class="button"
						href="<?php echo esc_html( admin_url( 'admin.php?page=' . self::PAGE_ID . '&tab=' . self::ID . '&view=utility_settings' ) ); ?>"><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Gets the current view
	 * Note: Need to implement this method to satisfy the interface.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_current_view() {
		$current_view = Helper::get_requested_value( 'view' );
		return $current_view;
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
