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

	/** @var array Values for Manage Events  */
	const MANAGE_EVENT_VIEWS = array(
		'manage_order_placed',
		'manage_order_fulfilled',
		'manage_order_refunded',
	);


	/**
	 * Whatsapp Utility constructor.
	 */
	public function __construct() {
		$this->initHook();

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
		$is_payment_setup = (bool) get_option( 'wc_facebook_wa_integration_is_payment_setup', null );
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
				'is_payment_setup'           => $is_payment_setup,
				'i18n'                       => array(
					'result' => true,
				),
			)
		);
		$order_placed_event_config_id    = get_option( 'wc_facebook_wa_order_placed_event_config_id', null );
		$order_placed_language           = get_option( 'wc_facebook_wa_order_placed_language', 'en' );
		$order_fulfilled_event_config_id = get_option( 'wc_facebook_wa_order_fulfilled_event_config_id', null );
		$order_fulfilled_language        = get_option( 'wc_facebook_wa_order_fulfilled_language', 'en' );
		$order_refunded_event_config_id  = get_option( 'wc_facebook_wa_order_refunded_event_config_id', null );
		$order_refunded_language         = get_option( 'wc_facebook_wa_order_refunded_language', 'en' );
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
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'nonce'                    => wp_create_nonce( 'facebook-for-wc-whatsapp-events-nonce' ),
				'event'                    => $this->get_current_event(),
				'order_placed_enabled'     => ! empty( $order_placed_event_config_id ),
				'order_placed_language'    => $order_placed_language,
				'order_fulfilled_enabled'  => ! empty( $order_fulfilled_event_config_id ),
				'order_fulfilled_language' => $order_fulfilled_language,
				'order_refunded_enabled'   => ! empty( $order_refunded_event_config_id ),
				'order_refunded_language'  => $order_refunded_language,
				'i18n'                     => array(
					'result'                  => true,
					'generic_error'           => __( 'Something went wrong. Please try again.', 'facebook-for-woocommerce' ),
					'token_invalidated_error' => __( 'Your access token has been invalidated. Please disconnect and reconnect your whatsapp account.', 'facebook-for-woocommerce' ),

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
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-templates',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-templates.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-templates',
			'facebook_for_woocommerce_whatsapp_templates',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-templates-nonce' ),
				'i18n'     => array(
					'result' => true,
				),
			)
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-whatsapp-disconnect',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/whatsapp-disconnect.js',
			array( 'jquery', 'jquery-blockui', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_localize_script(
			'facebook-for-woocommerce-whatsapp-disconnect',
			'facebook_for_woocommerce_whatsapp_disconnect',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-disconnect-nonce' ),
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
		} elseif ( in_array( $view, self::MANAGE_EVENT_VIEWS, true ) ) {
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
				<div id="wc-fb-whatsapp-billing-success" class="custom-dashicon-check fbwa-hidden-element"></div>
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
		<div id="whatsapp-onboarding-done-button" class="card-item">
			<div class="whatsapp-onboarding-done-button">
				<a
					class="button button-primary fbwa-button"
					id="wc-whatsapp-onboarding-finish"
					href="#">
					<div id="wc-whatsapp-onboarding-finish-loading-state" class="fbwa-spinner fbwa-hidden-element"></div>
					<span><?php esc_html_e( 'Done', 'facebook-for-woocommerce' ); ?></span>
				</a>
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
				<div class="card-content">
					<h1><?php esc_html_e( 'Utility Messages', 'facebook-for-woocommerce' ); ?></h1>
					<p><?php esc_html_e( 'Manage which utility messages you want to send to customers. You can check performance of these messages in Whatsapp Manager.', 'facebook-for-woocommerce' ); ?>
						<a
							id="woocommerce-whatsapp-manager-insights"
							href="#"><?php esc_html_e( 'View insights', 'facebook-for-woocommerce' ); ?></a>
					</p>
				</div>
			</div>
			<div class="divider"></div>
			<div class="card-item event-config">
				<div>
					<div class="event-config-heading-container">
						<h3><?php esc_html_e( 'Order confirmation', 'facebook-for-woocommerce' ); ?></h3>
						<div class="event-config-status on-status fbwa-hidden-element" id="order-placed-active-status">
							<?php esc_html_e( 'On', 'facebook-for-woocommerce' ); ?>
						</div>
						<div class="event-config-status fbwa-hidden-element" id="order-placed-inactive-status">
							<?php esc_html_e( 'Off', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<span><?php esc_html_e( 'Send a confirmation to customers after they\'ve placed an order.', 'facebook-for-woocommerce' ); ?></span>
				</div>
				<div class="event-config-manage-button">
					<a
						id="woocommerce-whatsapp-manage-order-placed"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Manage', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>
			<div class="divider"></div>
			<div class="card-item event-config">
				<div>
					<div class="event-config-heading-container">
						<h3><?php esc_html_e( 'Order shipped', 'facebook-for-woocommerce' ); ?></h3>
						<div class="event-config-status on-status fbwa-hidden-element" id="order-fulfilled-active-status">
							<?php esc_html_e( 'On', 'facebook-for-woocommerce' ); ?>
						</div>
						<div class="event-config-status fbwa-hidden-element" id="order-fulfilled-inactive-status">
							<?php esc_html_e( 'Off', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<span><?php esc_html_e( 'Send a confirmation to customers when their order is shipped.', 'facebook-for-woocommerce' ); ?></span>
				</div>
				<div class="event-config-manage-button">
					<a
						id="woocommerce-whatsapp-manage-order-fulfilled"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Manage', 'facebook-for-woocommerce' ); ?></a>
				</div>
			</div>
			<div class="divider"></div>
			<div class="card-item event-config">
				<div>
					<div class="event-config-heading-container">
						<h3><?php esc_html_e( 'Order refunded', 'facebook-for-woocommerce' ); ?></h3>
						<div class="event-config-status on-status fbwa-hidden-element" id="order-refunded-active-status">
							<?php esc_html_e( 'On', 'facebook-for-woocommerce' ); ?>
						</div>
						<div class="event-config-status fbwa-hidden-element" id="order-refunded-inactive-status">
							<?php esc_html_e( 'Off', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<span><?php esc_html_e( 'Send a confirmation to customers when an order is refunded.', 'facebook-for-woocommerce' ); ?></span>
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
				<div class="card-content">
					<div class="event-config-heading-container">
						<h1><?php esc_html_e( 'Add WhatsApp option at checkout', 'facebook-for-woocommerce' ); ?></h1>
						<div id="wc-whatsapp-collect-consent-status" class="event-config-status on-status">
							<?php esc_html_e( 'On', 'facebook-for-woocommerce' ); ?>
						</div>
					</div>
					<span class="consent-update-card-subcontent">
							<?php esc_html_e( 'Adds a checkbox to your store\'s checkout page that lets customers request updates about their order on WhatsApp. This allows you to communicate with customers after they make a purchase. You can preview what this looks like ', 'facebook-for-woocommerce' ); ?>
							<a
								href="<?php echo esc_url( admin_url( 'post.php?post=' . get_option( 'woocommerce_checkout_page_id' ) . '&action=edit' ) ); ?>"
								id="wc-whatsapp-checkout-preview"
								target="_blank"
							><?php esc_html_e( 'checkout preview.', 'facebook-for-woocommerce' ); ?>
							</a>
					</span>
				</div>
				<div class="event-config-manage-button" id="wc-whatsapp-collect-consent-remove-container">
					<a
						id="wc-whatsapp-collect-consent-remove"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Remove', 'facebook-for-woocommerce' ); ?>
					</a>
				</div>
				<div class="event-config-manage-button fbwa-hidden-element" id="wc-whatsapp-collect-consent-add-container">
					<a
						id="wc-whatsapp-collect-consent-add"
						class="event-config-manage-button button"
						href="#"><?php esc_html_e( 'Add', 'facebook-for-woocommerce' ); ?>
					</a>
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
		<div class="onboarding-card">
			<div class="card-item event-config">
				<div class="card-content">
					<div class="event-config-heading-container">
						<h1><?php esc_html_e( 'Edit your profile', 'facebook-for-woocommerce' ); ?></h1>
					</div>
					<div class="consent-update-card-subcontent">
						<?php esc_html_e( 'Edit your connected WhatsApp Business account on your WhatsApp Manager.', 'facebook-for-woocommerce' ); ?>
					</div>
				</div>
			</div>
			<div class="divider"></div>
			<div class="disconnect-footer">
			<!-- Left section: Icon and info -->
			<div class="disconnect-footer-left">
				<img src="<?php echo esc_url( plugins_url( '../../../assets/images/whatsapp_icon.png', __FILE__ ) ); ?>"
					alt="WhatsApp Icon"
					class="whatsapp-icon">
				<div class="contact-info">
					<h3><?php echo esc_html( get_option( 'wc_facebook_wa_integration_wacs_phone_number' ) ); ?></h3>
					<p><?php echo esc_html( get_option( 'wc_facebook_wa_integration_waba_display_name' ) ); ?></p>
				</div>
			</div>
			<!-- Right section: Buttons -->
			<div class="disconnect-footer-right">
				<a id="wc-whatsapp-disconnect-button"
				class="button"
				href="#">
				<?php esc_html_e( 'Disconnect', 'facebook-for-woocommerce' ); ?>
				</a>
				<span class="disconnect-footer-right-separator"></span>
				<a id="wc-whatsapp-disconnect-edit"
				class="button button-primary"
				href="#">
				<?php esc_html_e( 'Edit', 'facebook-for-woocommerce' ); ?>
				</a>
			</div>
			</div>
			<div id="wc-fb-disconnect-warning-modal" class="warning-custom-modal">
				<div class="warning-modal-content">
					<h2><?php esc_html_e( 'Disconnect WhatsApp from WooCommerce?', 'facebook-for-woocommerce' ); ?></h2>
					<div class="warning-modal-body">
						<?php esc_html_e( 'Your WhatsApp Business account will be disconnected from WooCommerce, resulting in the loss of messaging features. To reconnect in the future, you\'ll need to set up the connection again. However, you can still view your old insights in WhatsApp Manager. ', 'facebook-for-woocommerce' ); ?>
					</div>
					<div class="warning-modal-footer">
						<a id="wc-fb-disconnect-warning-modal-cancel" class="button fbwa-button" href="#"><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></a>
						<a id="wc-fb-disconnect-warning-modal-confirm" class="button button-primary fbwa-button" href="#">
							<div id="wc-fb-disconnect-warning-modal-confirm-loading-state" class="fbwa-spinner fbwa-hidden-element"></div>
							<span><?php esc_html_e( 'Disconnect', 'facebook-for-woocommerce' ); ?></span>
						</a>
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
		$event = $this->get_current_event();
		?>
		<div class="onboarding-card">
			<div class="manage-event-card-item">
				<div class="card-content">
					<h1><b>
					<?php
					switch ( $event ) {
						case 'ORDER_PLACED':
							?>
							<?php esc_html_e( 'Manage order confirmation message', 'facebook-for-woocommerce' ); ?>
							<?php
							break;
						case 'ORDER_FULFILLED':
							?>
							<?php esc_html_e( 'Manage order shipped message', 'facebook-for-woocommerce' ); ?>
							<?php
							break;
						case 'ORDER_REFUNDED':
							?>
							<?php esc_html_e( 'Manage order refunded message', 'facebook-for-woocommerce' ); ?>
							<?php
							break;
					}
					?>
					</b></h1>
						<p>
						<?php
						switch ( $event ) {
							case 'ORDER_PLACED':
								?>
								<?php esc_html_e( 'Send a confirmation to customers after they\'ve placed an order.', 'facebook-for-woocommerce' ); ?>
									<?php
								break;
							case 'ORDER_FULFILLED':
								?>
								<?php esc_html_e( 'Send a confirmation to customers when their order has shipped.', 'facebook-for-woocommerce' ); ?>
									<?php
								break;
							case 'ORDER_REFUNDED':
								?>
								<?php esc_html_e( 'Send a confirmation whenever an order has been refunded.', 'facebook-for-woocommerce' ); ?>
									<?php
								break;
						}
						?>
				</p>
				</div>
			</div>
			<div class="divider"></div>
			<div class="manage-event-card-item">
				<p><b><?php esc_html_e( 'Select a language', 'facebook-for-woocommerce' ); ?></b></p>
				<select id="manage-event-language" class="manage-event-selector">
				</select>
			</div>
			<div class="manage-event-card-item">
				<div class="manage-event-template-block">
					<div class="manage-event-template-header">
						<input type="radio" name="template-status" id="active-template-status" value="ACTIVE" checked="checked" />
						<label for="active-template-status"><b>
						<?php
						switch ( $event ) {
							case 'ORDER_PLACED':
								?>
								<?php esc_html_e( 'Send order confirmation message', 'facebook-for-woocommerce' ); ?>
								<?php
								break;
							case 'ORDER_FULFILLED':
								?>
								<?php esc_html_e( 'Send order shipped message', 'facebook-for-woocommerce' ); ?>
								<?php
								break;
							case 'ORDER_REFUNDED':
								?>
								<?php esc_html_e( 'Send order refunded message', 'facebook-for-woocommerce' ); ?>
								<?php
								break;
						}
						?>
						</b></label>
					</div>
					<div class="divider"></div>
					<div class="manage-event-card-item fbwa-hidden-element" id="library-template-content"></div>
				</div>
				<div class="manage-event-template-block">
					<div class="manage-event-template-header">
						<input type="radio" name="template-status" id="inactive-template-status" value="INACTIVE" />
						<label for="inactive-template-status"><b>
						<?php
						switch ( $event ) {
							case 'ORDER_PLACED':
								?>
								<?php esc_html_e( 'Turn off order confirmation message', 'facebook-for-woocommerce' ); ?>
								<?php
								break;
							case 'ORDER_FULFILLED':
								?>
								<?php esc_html_e( 'Turn off order shipped message', 'facebook-for-woocommerce' ); ?>
								<?php
								break;
							case 'ORDER_REFUNDED':
								?>
								<?php esc_html_e( 'Turn off order refunded message', 'facebook-for-woocommerce' ); ?>
								<?php
								break;
						}
						?>
						</b></label>
					</div>
				</div>
				<div id="events-error-notice" class="manage-event-error-notice"></div>
			</div>
			<div class="manage-event-card-item manage-event-template-footer">
				<div class="manage-event-button">
					<a
						id="woocommerce-whatsapp-save-order-confirmation"
						class="button button-primary fbwa-button"
						href="#">
							<div id="woocommerce-whatsapp-save-loading-state" class="fbwa-spinner fbwa-hidden-element"></div>
							<span><?php esc_html_e( 'Save', 'facebook-for-woocommerce' ); ?></span>
					</a>
				</div>
				<div class="manage-event-button">
					<a
						id="woocommerce-whatsapp-cancel-order-confirmation"
						class="button fbwa-button"
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
	 * Gets the current event
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_current_event() {
		$view      = Helper::get_requested_value( 'view' );
		$event_val = str_replace( 'manage_', '', $view );
		return strtoupper( $event_val );
	}

	/**
	 * Gets the screen settings.
	 * Note: Need to implement this method to satisfy the interface.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return array();
	}
}
