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

use Automattic\WooCommerce\Admin\Features\Features as WooAdminFeatures;
use WooCommerce\Facebook\Admin\Settings_Screens;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\RolloutSwitches;

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings handler.
 *
 * @since 2.0.0
 */
class Settings {

	/** @var string base settings page ID */
	const PAGE_ID = 'wc-facebook';

	/** @var Abstract_Settings_Screen[] */
	private $screens;

	/** @var \WC_Facebookcommerce */
	private $plugin;

	/**
	 * Settings constructor.
	 *
	 * @param \WC_Facebookcommerce $plugin is the plugin instance of WC_Facebookcommerce
	 * @since 2.0.0
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {

		$this->plugin = $plugin;

		$this->screens = $this->build_menu_item_array();

		add_action( 'admin_init', array( $this, 'add_extra_screens' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
		add_action( 'wp_loaded', array( $this, 'save' ) );
		add_action( 'admin_notices', array( $this, 'display_fb_product_sets_removed_banner' ) );
	}

	/**
	 * Arranges the tabs. If the plugin is connected to FB, Advertise tab will be first, otherwise the Connection tab will be the first tab.
	 *
	 * @since 3.0.7
	 */
	public function build_menu_item_array(): array {

		$screens = array(
			Settings_Screens\Product_Sync::ID       => new Settings_Screens\Product_Sync(),
			Settings_Screens\Product_Attributes::ID => new Settings_Screens\Product_Attributes(),
		);

		return $screens;
	}

	public function add_extra_screens(): void {
		$rollout_switches                      = $this->plugin->get_rollout_switches();
		$is_connected                          = $this->plugin->get_connection_handler()->is_connected();
		$is_whatsapp_utility_messaging_enabled = $rollout_switches->is_switch_enabled( RolloutSwitches::WHATSAPP_UTILITY_MESSAGING );
		if ( true === $is_connected && true === $is_whatsapp_utility_messaging_enabled ) {
			$this->screens[ Settings_Screens\Whatsapp_Utility::ID ] = new Settings_Screens\Whatsapp_Utility();
		}

		$is_woo_all_products_sync_enbaled = $this->plugin->get_rollout_switches()->is_switch_enabled(
			RolloutSwitches::SWITCH_WOO_ALL_PRODUCTS_SYNC_ENABLED
		);
		/**
		 * If all products sync is not enabled should show the Product sync tab
		 */
		if ( true === $is_connected && false === $is_woo_all_products_sync_enbaled ) {
			$this->screens[ Settings_Screens\Product_Sync::ID ] = new Settings_Screens\Product_Sync();
		}
	}

	/**
	 * Adds the Facebook menu item.
	 *
	 * @since 2.0.0
	 */
	public function add_menu_item() {
		$root_menu_item = $this->root_menu_item();

		add_submenu_page(
			$root_menu_item,
			__( 'Facebook for WooCommerce', 'facebook-for-woocommerce' ),
			__( 'Facebook', 'facebook-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_ID,
			[ $this, 'render' ],
			5
		);
		$this->connect_to_enhanced_admin( $this->is_marketing_enabled() ? 'marketing_page_wc-facebook' : 'woocommerce_page_wc-facebook' );
	}

	/**
	 * Get root menu item.
	 *
	 * @since 3.2.10
	 * return string Root menu item slug.
	 */
	public function root_menu_item() {
		if ( $this->is_marketing_enabled() ) {
			return 'woocommerce-marketing';
		}

		return 'woocommerce';
	}

	/**
	 * Check if marketing feature is enabled.
	 *
	 * @since 3.2.10
	 * return bool Is marketing enabled.
	 */
	public function is_marketing_enabled() {
		if ( class_exists( WooAdminFeatures::class ) ) {
			return WooAdminFeatures::is_enabled( 'marketing' );
		}

		return is_callable( '\Automattic\WooCommerce\Admin\Loader::is_feature_enabled' )
				&& \Automattic\WooCommerce\Admin\Loader::is_feature_enabled( 'marketing' );
	}

	/**
	 * Enables enhanced admin support for the main Facebook settings page.
	 *
	 * @since 2.2.0
	 *
	 * @param string $screen_id the ID to connect to
	 */
	private function connect_to_enhanced_admin( $screen_id ) {
		$is_woo_all_products_sync_enbaled = $this->plugin->get_rollout_switches()->is_switch_enabled(
			RolloutSwitches::SWITCH_WOO_ALL_PRODUCTS_SYNC_ENABLED
		);

		if ( is_callable( 'wc_admin_connect_page' ) ) {
			$crumbs = array(
				__( 'Facebook for WooCommerce', 'facebook-for-woocommerce' ),
			);
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET['tab'] ) ) {
				//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				switch ( $_GET['tab'] ) {
					case Settings_Screens\Product_Sync::ID:
						/**
						 * If all proudcts sync not enabled
						 * Show the product sync tab
						 */
						if ( ! $is_woo_all_products_sync_enbaled ) {
							$crumbs[] = __( 'Product sync', 'facebook-for-woocommerce' );
						}
						break;
				}
			}
			wc_admin_connect_page(
				array(
					'id'        => self::PAGE_ID,
					'screen_id' => $screen_id,
					'path'      => add_query_arg( 'page', self::PAGE_ID, 'admin.php' ),
					'title'     => $crumbs,
				)
			);
		}
	}


	/**
	 * Renders the settings page.
	 *
	 * @since 2.0.0
	 */
	public function render() {
		$current_tab = $this->get_current_tab();
		$screen      = $this->get_screen( $current_tab );
		?>
		<div class="wrap woocommerce">
			<?php $this->render_tabs( $current_tab ); ?>
			<?php facebook_for_woocommerce()->get_message_handler()->show_messages(); ?>
			<?php if ( $screen ) : ?>
				<h1 class="screen-reader-text"><?php echo esc_html( $screen->get_title() ); ?></h1>
				<p><?php echo wp_kses_post( $screen->get_description() ); ?></p>
				<?php $screen->render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Facebook for WooCommerce extension navigation tabs.
	 *
	 * @since 3.3.0
	 *
	 * @param string $current_tab The current tab ID.
	 */
	public function render_tabs( $current_tab ) {
		$tabs = $this->get_tabs();
		?>
		<nav class="nav-tab-wrapper woo-nav-tab-wrapper facebook-for-woocommerce-tabs">
			<?php foreach ( $tabs as $id => $label ) : ?>
				<?php $url = admin_url( 'admin.php?page=' . self::PAGE_ID . '&tab=' . esc_attr( $id ) ); ?>
				<?php if ( 'whatsapp_utility' === $id ) : ?>
					<?php
					$wa_integration_config_id = get_option( 'wc_facebook_wa_integration_config_id', '' );
					if ( ! empty( $wa_integration_config_id ) ) {
						$url .= '&view=utility_settings';
					}
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $current_tab === $id ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $current_tab === $id ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Get the current tab ID.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	protected function get_current_tab() {
		$tabs        = $this->get_tabs();
		$current_tab = Helper::get_requested_value( 'tab' );
		if ( ! $current_tab ) {
			$current_tab = current( array_keys( $tabs ) );
		}
		return $current_tab;
	}


	/**
	 * Saves the settings page.
	 *
	 * @since 2.0.0
	 */
	public function save() {
		if ( ! is_admin() || Helper::get_requested_value( 'page' ) !== self::PAGE_ID ) {
			return;
		}
		$screen = $this->get_screen( Helper::get_posted_value( 'screen_id' ) );
		if ( ! $screen ) {
			return;
		}
		if ( ! Helper::get_posted_value( 'save_' . $screen->get_id() . '_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'facebook-for-woocommerce' ) );
		}
		check_admin_referer( 'wc_facebook_admin_save_' . $screen->get_id() . '_settings' );
		try {
			$screen->save();
			facebook_for_woocommerce()->get_message_handler()->add_message( __( 'Your settings have been saved.', 'facebook-for-woocommerce' ) );
		} catch ( PluginException $exception ) {
			facebook_for_woocommerce()->get_message_handler()->add_error(
				sprintf(
				/* translators: Placeholders: %s - user-friendly error message */
					__( 'Your settings could not be saved. %s', 'facebook-for-woocommerce' ),
					$exception->getMessage()
				)
			);
		}
	}


	/**
	 * Gets a settings screen object based on ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $screen_id desired screen ID
	 * @return Abstract_Settings_Screen|null
	 */
	public function get_screen( $screen_id ) {
		$screens = $this->get_screens();
		return ! empty( $screens[ $screen_id ] ) && $screens[ $screen_id ] instanceof Abstract_Settings_Screen ? $screens[ $screen_id ] : null;
	}


	/**
	 * Gets the available screens.
	 *
	 * @since 2.0.0
	 *
	 * @return Abstract_Settings_Screen[]
	 */
	public function get_screens() {
		/**
		 * Filters the admin settings screens.
		 *
		 * @since 2.0.0
		 *
		 * @param array $screens available screen objects
		 */
		$screens = (array) apply_filters( 'wc_facebook_admin_settings_screens', $this->screens, $this );
		// ensure no bogus values are added via filter
		$screens = array_filter(
			$screens,
			function ( $value ) {
				return $value instanceof Abstract_Settings_Screen;
			}
		);
		return $screens;
	}


	/**
	 * Gets the tabs.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_tabs() {
		$tabs = [];
		foreach ( $this->get_screens() as $screen_id => $screen ) {
			$tabs[ $screen_id ] = $screen->get_label();
		}
		/**
		 * Filters the admin settings tabs.
		 *
		 * @since 2.0.0
		 *
		 * @param array $tabs tab data, as $id => $label
		 */
		return (array) apply_filters( 'wc_facebook_admin_settings_tabs', $tabs, $this );
	}

	public function display_fb_product_sets_removed_banner() {
		$dismissed = get_transient( 'fb_product_set_banner_dismissed' );
		if ( $dismissed ) {
			return; // Banner dismissed, do not show
		}

		$screen = get_current_screen();
		if ( ! $screen || ( 'marketing_page_wc-facebook' !== $screen->id && 'woocommerce_page_wc-facebook' !== $screen->id ) ) {
			return;
		}

		$fb_catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
		?>
			<div class="notice notice-info is-dismissible fb-product-set-banner">
				<p><strong>The Product Sets tab has been removed</strong></p>
				<p>The Product Sets tab is no longer available in the plugin. All product sets you created previously remain intact and accessible. Your WooCommerce categories will continue to sync automatically as product sets to your Meta catalog. To update synced sets, please <a href="edit-tags.php?taxonomy=product_cat&post_type=product" target="_blank" rel="noopener noreferrer">edit your categories in WooCommerce</a>. To view and manage your synced product sets, visit <a href="https://business.facebook.com/commerce/catalogs/<?php echo esc_attr( $fb_catalog_id ); ?>/sets" target="_blank" rel="noopener noreferrer">Commerce Manager</a>.</p>
			</div>
		<?php
	}
}
