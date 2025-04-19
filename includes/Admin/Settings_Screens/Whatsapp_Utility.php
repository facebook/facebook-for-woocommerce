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

	/** @var flag to test Utility Messages Overview changes until check for integration config is implemented */
	const WHATSAPP_UTILITY_MESSAGES_OVERVIEW_FLAG = true;

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
		wp_localize_script(
			'facebook-for-woocommerce-connect-whatsapp',
			'facebook_for_woocommerce_whatsapp_onboarding_progress',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-onboarding-progress-nonce' ),
				'i18n'     => array(
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
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'facebook-for-wc-whatsapp-billing-nonce' ),
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
		if ( self::WHATSAPP_UTILITY_MESSAGES_OVERVIEW_FLAG ) {
			$view = $this->get_current_view();
			if ( 'manage_event' === $view ) {
				$this->render_manage_events_view();
			} else {
				$this->render_utility_message_overview();
			}
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
			class="button"
			href="#"
		><?php esc_html_e( 'Connect Whatsapp Account', 'facebook-for-woocommerce' ); ?></a>
	</div>
	</div>
	<div class="divider"></div>
	<div class="card-item">
	<h2><?php esc_html_e( 'Collect phone numbers at checkout', 'facebook-for-woocommerce' ); ?></h2>
	<p><?php esc_html_e( 'To collect phone numbers, a checkbox will be added to your store’s checkout page. This lets customers sign up to receive your messages. You can preview what this looks like in your checkout page preview.', 'facebook-for-woocommerce' ); ?></p>
		<p><?php esc_html_e( 'This will allow you to send messages to your customers on WhatsApp.', 'facebook-for-woocommerce' ); ?></p>
		<div class="whatsapp-onboarding-button">
		<a
			class="button"
			id="wc-whatsapp-collect-consent"
			href="#"
		><?php esc_html_e( 'Add', 'facebook-for-woocommerce' ); ?></a>
		</div>
	</div>
	<div class="divider"></div>
	<div class="card-item">
		<h2><?php esc_html_e( 'Add a payment method', 'facebook-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Confirm your payment method in Billings & payments.', 'facebook-for-woocommerce' ); ?>
				<a
					href="#"
					id="wc-whatsapp-about-pricing"
				><?php esc_html_e( 'About pricing', 'facebook-for-woocommerce' ); ?>
				</a>

			</p>
			<div class="add-payment-section">
				<div class="review-payment-block">
					<div class="review-payment-content">
					<?php esc_html_e( 'Add a payment method', 'facebook-for-woocommerce' ); ?>
					</div>
						<div class="add-payment-button">
							<a
								class="button"
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
						href="<?php echo esc_html( admin_url( 'admin.php?page=' . self::PAGE_ID . '&tab=' . self::ID . '&view=manage_event' ) ); ?>"><?php esc_html_e( 'Manage', 'facebook-for-woocommerce' ); ?></a>
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
						class="button"
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
					<div class="card-item">
						<h4>[Header]</h4>
						<h4>[Body]</h4>
						<h4>[Call to Action]</h4>
					</div>
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
						href="#"><?php esc_html_e( 'Save', 'facebook-for-woocommerce' ); ?></a>
				</div>
				<div class="manage-event-button">

					<a
						id="woocommerce-whatsapp-cancel-order-confirmation"
						class="button"
						href="<?php echo esc_html( admin_url( 'admin.php?page=' . self::PAGE_ID . '&tab=' . self::ID ) ); ?>"><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></a>
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
