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
use WooCommerce\Facebook\Framework\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Admin enhanced settings handler.
 *
 * @since 3.5.0
 */
class Enhanced_Settings {

	/** @var string */
	const PAGE_ID = 'wc-facebook';

	/** @var Abstract_Settings_Screen[] */
	private $screens;

	/**
	 * TODO: Remove these hookds once catalog changes are complete
	 *
	 * @var string
	 */
	const SUBMENU_PAGE_ID = 'edit-tags.php?taxonomy=fb_product_set&post_type=product';

	/** @var \WC_Facebookcommerce */
	private $plugin;


	/**
	 * Enhanced settings constructor.
	 *
	 * @since 3.5.0
	 *
	 * @param \WC_Facebookcommerce $plugin is the plugin instance of WC_Facebookcommerce
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;

		$this->screens = $this->build_menu_item_array();

		add_action( 'admin_init', array( $this, 'add_extra_screens' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
		add_action( 'wp_loaded', array( $this, 'save' ) );

		// TODO: Remove these hookds once catalog changes are complete
		add_filter( 'parent_file', array( $this, 'set_parent_and_submenu_file' ) );
		add_action( 'all_admin_notices', array( $this, 'add_tabs_to_product_sets_taxonomy' ) );
	}

	/**
	 * Arranges the tabs.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function build_menu_item_array(): array {
		$is_connected                     = $this->plugin->get_connection_handler()->is_connected();
		$is_woo_all_products_sync_enbaled = $this->plugin->get_rollout_switches()->is_switch_enabled(
			RolloutSwitches::SWITCH_WOO_ALL_PRODUCTS_SYNC_ENABLED
		);

		if ( $is_connected ) {
			if ( $is_woo_all_products_sync_enbaled ) {
				$screens = array(
					Settings_Screens\Shops::ID        => new Settings_Screens\Shops(),
					Settings_Screens\Product_Sets::ID => new Settings_Screens\Product_Sets(),
				);
			} else {
				/**
				 * If not enabled then the product sync tab should show itself
				 */
				$screens = array(
					Settings_Screens\Shops::ID        => new Settings_Screens\Shops(),
					Settings_Screens\Product_Sync::ID => new Settings_Screens\Product_Sync(),
					Settings_Screens\Product_Sets::ID => new Settings_Screens\Product_Sets(),
				);
			}
		} else {
			$screens = [ Settings_Screens\Shops::ID => new Settings_Screens\Shops() ];
		}

		return $screens;
	}

	/**
	 * Add extra screens to $this->screens - basic settings_screens
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function add_extra_screens(): void {
		$rollout_switches                      = $this->plugin->get_rollout_switches();
		$is_connected                          = $this->plugin->get_connection_handler()->is_connected();
		$is_whatsapp_utility_messaging_enabled = $rollout_switches->is_switch_enabled( RolloutSwitches::WHATSAPP_UTILITY_MESSAGING );

		if ( true === $is_connected && true === $is_whatsapp_utility_messaging_enabled ) {
			$this->screens[ Settings_Screens\Whatsapp_Utility::ID ] = new Settings_Screens\Whatsapp_Utility();
		}
	}

	/**
	 * Adds the Facebook menu item.
	 *
	 * @since 3.5.0
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
	 * Checks if marketing feature is enabled.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
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
					'title'     => [ __( 'Facebook for WooCommerce', 'facebook-for-woocommerce' ) ],
				)
			);
		}
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 3.5.0
	 */
	public function render() {
		$current_tab = $this->get_current_tab();
		$screen      = $this->get_screen( $current_tab );

		Logger::log(
			'User visited the Facebook for WooCommerce settings' . $current_tab . 'tab',
			array(
				'flow_name' => 'settings',
				'flow_step' => $current_tab . '_tab_rendered',
			),
			array(
				'should_send_log_to_meta'        => true,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

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
	 * Renders the Facebook for WooCommerce extension navigation tabs.
	 *
	 * @since 3.5.0
	 *
	 * @param string $current_tab
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
	 * Gets the current tab ID.
	 *
	 * @since 3.5.0
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
	 * @since 3.5.0
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
	 * @since 3.5.0
	 *
	 * @param string $screen_id
	 * @return Abstract_Settings_Screen | null
	 */
	public function get_screen( $screen_id ) {
		$screens = $this->get_screens();

		return ! empty( $screens[ $screen_id ] ) && $screens[ $screen_id ] instanceof Abstract_Settings_Screen ? $screens[ $screen_id ] : null;
	}

	/**
	 * Gets the available screens.
	 *
	 * @since 3.5.0
	 *
	 * @return Abstract_Settings_Screen[]
	 */
	public function get_screens() {
		/**
		 * Filters the admin settings screens.
		 *
		 * @since 3.5.0
		 *
		 * @param array $screens
		 */
		$screens = (array) apply_filters( 'wc_facebook_admin_settings_screens', $this->screens, $this );

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
	 * @since 3.5.0
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
		 * @since 3.5.0
		 *
		 * @param array $tabs
		 */
		return (array) apply_filters( 'wc_facebook_admin_settings_tabs', $tabs, $this );
	}

		/**
		 * TODO: Remove this function once catalog changes are complete
		 *
		 * Set the parent and submenu file while accessing Facebook Product Sets in the marketing menu.
		 *
		 * @since 3.5.0
		 *
		 * @param string $parent_file
		 * @return string
		 */
	public function set_parent_and_submenu_file( $parent_file ) {
		global $pagenow, $submenu_file;

		$root_menu_item = $this->root_menu_item();

		if ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
			if ( isset( $_GET['taxonomy'] ) && 'fb_product_set' === $_GET['taxonomy'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$parent_file  = $root_menu_item;
				$submenu_file = self::PAGE_ID; //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		return $parent_file;
	}

	/**
	 * TODO: Remove this function once catalog changes are complete
	 *
	 * Add the Facebook for WooCommerce tabs to the Facebook Product Set taxonomy page.
	 * Renders the tabs (hidden by default) at the stop of the page,
	 * then moves them to the correct DOM location with JavaScript and displays them.
	 *
	 * @since 3.3.0
	 */
	public function add_tabs_to_product_sets_taxonomy() {

		// Only load this on the edit-tags.php page
		$screen                  = get_current_screen();
		$is_taxonomy_list_page   = 'edit-tags' === $screen->base;
		$is_taxonomy_term_page   = 'term' === $screen->base;
		$is_taxonomy_page        = $is_taxonomy_list_page || $is_taxonomy_term_page;
		$is_product_set_taxonomy = 'fb_product_set' === $screen->taxonomy && $is_taxonomy_page;

		if ( $is_product_set_taxonomy ) {
			$this->render_tabs( Settings_Screens\Product_Sets::ID );
			?>
				<style>
					.facebook-for-woocommerce-tabs {
						margin: 30px 20px 0 20px;
					}
					#wpbody-content > .wrap > h1 {
						font-size: 1.3em;
						font-weight: 600;
					}

					@media (max-width: 782px) {
						.facebook-for-woocommerce-tabs {
								padding-top: 19px;
								margin-bottom: -1px;
						}
						.edit-tags-php .facebook-for-woocommerce-tabs {
							clear: both;
							padding-top: 0;
							position: relative;
							top: -10px;
							margin-bottom: -11px;
						}
				</style>
			<?php
		}
	}
}
