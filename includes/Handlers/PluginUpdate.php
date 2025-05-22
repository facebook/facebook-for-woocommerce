<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Plugin\Compatibility;
use WooCommerce\Facebook\Products\Sync;
use WooCommerce\Facebook\Framework\Plugin\Exception;

/**
 * PluginUpdate
 * This is an class that is triggered for Opt in/ Opt out experience
 * from @ver 3.4.10
 */
class PluginUpdate {
	/** @var object storing plugin object */
	private \WC_Facebookcommerce $plugin;

	/** @var string opt out plugin version action */
	const ALL_PRODUCTS_PLUGIN_VERSION = '3.4.11';

	/** @var string opt out sync action */
	const ACTION_OPT_OUT_OF_SYNC = 'wc_facebook_opt_out_of_sync';

	/** @var string master sync option */
	const MASTER_SYNC_OPT_OUT_TIME = 'wc_facebook_master_sync_opt_out_time';

	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;
		$this->should_show_banners();
		$this->add_hooks();
	}

	public function enqueue_assets() {
		wp_enqueue_script( 'wc-backbone-modal', null, array( 'backbone' ) );
		wp_enqueue_script(
			'facebook-for-woocommerce-modal',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/modal.js',
			array( 'jquery', 'wc-backbone-modal', 'jquery-blockui' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-plugin-update',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/plugin-update.js',
			array( 'jquery', 'wc-backbone-modal', 'jquery-blockui', 'jquery-tiptip', 'facebook-for-woocommerce-modal', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION,
		);
		wp_localize_script(
			'facebook-for-woocommerce-plugin-update',
			'facebook_for_woocommerce_plugin_update',
			array(
				'ajax_url'                        => admin_url( 'admin-ajax.php' ),
				'set_excluded_terms_prompt_nonce' => wp_create_nonce( 'set-excluded-terms-prompt' ),
				'opt_out_of_sync'                 => wp_create_nonce( self::ACTION_OPT_OUT_OF_SYNC ),
				'sync_in_progress'                => Sync::is_sync_in_progress(),
				'opt_out_confirmation_message'    => self::get_opt_out_modal_message(),
				'opt_out_confirmation_buttons'    => self::get_opt_out_modal_buttons(),
			)
		);
	}

	private static function add_hooks() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__,  'enqueue_assets' ] );
		add_action( 'wp_ajax_wc_facebook_opt_out_of_sync', [ __CLASS__,  'opt_out_of_sync_clicked' ] );
		add_action( 'wp_ajax_nopriv_wc_facebook_opt_out_of_sync', [ __CLASS__,'opt_out_of_sync_clicked' ] );
		add_action( 'wp_ajax_wc_facebook_upgrade_plugin', [ __CLASS__,  'upgrade_facebook_woocommerce_plugin' ] );
		add_action( 'wp_ajax_nopriv_wc_facebook_upgrade_plugin', [ __CLASS__,'upgrade_facebook_woocommerce_plugin' ] );
		add_action( 'wp_ajax_wc_facebook_sync_all_products', [ __CLASS__,  'sync_all_clicked' ] );
		add_action( 'wp_ajax_nopriv_wc_facebook_sync_all_products', [ __CLASS__,'sync_all_clicked' ] );
	}

	public static function get_opt_out_time() {
		try {
			$option_value = get_option( self::MASTER_SYNC_OPT_OUT_TIME );
			return $option_value;
		} catch ( Exception $e ) {
			error_log( 'Error while fetching master sync option: ' . $e->getMessage() );
			return null;
		}
	}

	public static function is_master_sync_on() {
		$option_value = self::get_opt_out_time();
		return '' === $option_value;
	}

	/**
	 * Latest plugin version
	 * available on WooCommerce store
	 * If unable to fetch gets the current plugin version
	 * Should be anything above 3.4.10
	 */
	public function get_latest_plugin_version() {
		$latest_plugin = Compatibility::get_latest_facebook_woocommerce_version();
		if ( ! $latest_plugin ) {
			$latest_plugin = $this->plugin->get_version();
		}
		return $latest_plugin;
	}

	public function should_show_banners() {
		$current_version = $this->plugin->get_version();
		$latest_version  = $this->get_latest_plugin_version();
		/**
		 * Case when current version is less or equal to latest
		 * but latest is below 3.4.11
		 * Should show the opt in/ opt out banner
		 */
		if ( self::compare_versions( $latest_version, $current_version ) >= 0 && self::compare_versions( $latest_version, self::ALL_PRODUCTS_PLUGIN_VERSION ) < 0 ) {
			add_action( 'admin_notices', [ __CLASS__, 'upcoming_version_change_banner' ], 0, 1 );
		} elseif ( self::compare_versions( $latest_version, self::ALL_PRODUCTS_PLUGIN_VERSION ) >= 0 && self::compare_versions( $latest_version, $current_version ) > 0 ) {
			/**
			 * If latest version is above All products version show the update banner accordingly
			 * also show for update banner in case latest version is above current version
			 */
			add_action( 'admin_notices', [ __CLASS__, 'plugin_update_avaialble_banner' ], 0, 1 );
		} elseif ( get_transient( 'show_plugin_updated_notice' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'plugin_updated_banner' ] );
			delete_transient( 'show_plugin_updated_notice' );
		}
	}

	public function upcoming_version_change_banner() {
		$screen = get_current_screen();

		if ( isset( $screen->id ) && 'marketing_page_wc-facebook' === $screen->id ) {
			echo '<div id="opt_out_banner" class="' . esc_html( self::get_opt_out_banner_class() ) . '" style="padding: 15px">
            <h2>When you update to version <b>' . esc_html( self::get_latest_plugin_version() ) . '</b> your products will automatically sync to your catalog at Meta catalog</h2>
            The next time you update your Facebook for WooCommerce plugin, all your products will be synced automatically. This is to help you drive sales and optimize your ad performance. <a href="https://www.facebook.com/business/help/4049935305295468">Learn more about changes to how your products will sync to Meta</a>
                <p>
                    <a href="edit.php?post_type=product"> Review products </a>
                    <a href="javascript:void(0);" style="text-decoration: underline; cursor: pointer; margin-left: 10px" class="opt_out_of_sync_button"> Opt out of automatic sync</a>
                </p>
            </div>
            ';

			echo '<div id="opt_in_banner" class="' . esc_html( self::get_opt_in_banner_class() ) . '" style="padding: 15px">
            <h2>You’ve opted out of automatic syncing on the next plugin update </h2>
                <p>
                    Products that are not synced will not be available for your customers to discover on your ads and shops. To manually add products, <a href="https://www.facebook.com/business/help/4049935305295468">learn how to sync products to your Meta catalog</a>
                </p>
            </div>';
		}
	}

	public function plugin_update_avaialble_banner() {
		$screen = get_current_screen();

		if ( isset( $screen->id ) && 'marketing_page_wc-facebook' === $screen->id ) {
			echo '<div id="opt_out_banner_update_available" class="' . esc_html( self::get_opt_out_banner_class() ) . '" style="padding: 15px">
            <h2>Version ' . esc_html( self::get_latest_plugin_version() ) . ' is available: This release includes automatic product syncing</h2>
            A new version of our plugin is now available. When you update, all your products will now be automatically synced to Meta for seamless advertising. Opt out before you update if want to sync your products later.
                <p>
                   <a href="javascript:void(0);" class="button wc-forward upgrade_plugin_button">
                        Update now
                    </a>
                    <a href="javascript:void(0);" style="text-decoration: underline; cursor: pointer; margin-left: 10px" class="opt_out_of_sync_button"> Opt out of automatic sync</a>
                </p>
            </div>';

			echo '<div id="opt_in_banner_update_available" class="' . esc_html( self::get_opt_in_banner_class() ) . '" style="padding: 15px">
            <h2>Update to the latest plugin version </h2>
                <p>
                    A new version of our plugin is now available, featuring improved performance and simplified features. Since you’ve opted out of the automatic product sync, it will not be part of this update. Update now to get the best experience possible.
                </p>
                <p>
                    <a href="javascript:void(0);" class="button wc-forward upgrade_plugin_button">
                        Update now
                    </a>
                </p>
            </div>';
		}
	}

	public function plugin_updated_banner() {
		$screen = get_current_screen();

		if ( isset( $screen->id ) && 'marketing_page_wc-facebook' === $screen->id ) {

			if ( self::is_master_sync_on() ) {
				echo '<div class="notice notice-success is-dismissible" style="padding: 15px">
                <h2>You’ve updated to the latest plugin version</h2>
                    <p>
                        As part of this update, all your products automatically sync to Meta. It may take some time before all your products are synced. If you change your mind, go to WooCommerce > Products and select which products to un-sync. <a href="https://www.facebook.com/business/help/4049935305295468"> About syncing products to Meta </a>
                    </p>
                </div>';
			} else {
				$hidden                 = self::is_master_sync_on();
				$opted_out_banner_class = $hidden ? 'hidden' : '';
				$opted_in_banner_class  = ! $hidden ? 'hidden' : '';

				echo '<div id="opted_out_banner_updated_plugin" class="notice notice-success is-dismissible ' . esc_html( $opted_out_banner_class ) . '"" style="padding: 15px">
                    <h2>You’ve updated to the latest plugin version</h2>   
                        <p>
                            To see all the changes, view the changelog. Since you’ve opted out of automatically syncing all your products, some of your products are not yet on Meta. We recommend turning on auto syncing to help drive your sales and improve ad performance. About syncing products to Meta
                        </p>
                        <p>
                            <a href="javascript:void(0);" class="button wc-forward" id="sync_all_products">
                                Sync all products
                            </a>
                        </p>
                    </div>';

				echo '<div id="opted_in_banner_updated_plugin" class="notice notice-success is-dismissible ' . esc_html( $opted_in_banner_class ) . '"" style="padding: 15px">
                    <h2>Your products will be synced automatically</h2>   
                        <p>
                            It may take some time before all your products are synced. If you change your mind, go to WooCommerce > Products and select which products to un-sync.<a href="https://www.facebook.com/business/help/4049935305295468"> About syncing products to Meta</a>
                        </p>
                    </div>';
			}
		}
	}

	public function opt_out_of_sync_clicked() {
		try {
			$latest_date = gmdate( 'Y-m-d H:i:s' );
			update_option( self::MASTER_SYNC_OPT_OUT_TIME, $latest_date );
			wp_send_json_success( 'Opted out successfully' );
		} catch ( Exception $e ) {
			error_log( 'Error while updating WP option: ' . $e->getMessage() );
			wp_send_json_error( 'Failed to opt out' );
		}
	}

	public function sync_all_clicked() {
		try {
			update_option( self::MASTER_SYNC_OPT_OUT_TIME, '' );
			wp_send_json_success( 'Opted in successfully' );
		} catch ( Exception $e ) {
			error_log( 'Error while updating WP option: ' . $e->getMessage() );
			wp_send_json_error( 'Failed to opt in' );
		}
	}

	public function upgrade_facebook_woocommerce_plugin() {
		$plugin_slug = 'facebook-for-woocommerce';
		$plugin_file = "$plugin_slug/$plugin_slug.php";

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data       = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
		$installed_version = $plugin_data['Version'];

		$response = wp_remote_get( "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug={$plugin_slug}" );

		if ( is_wp_error( $response ) ) {
			error_log( 'Error while upgrading plugin' );
			wp_send_json_error( 'Failed to upgrade plugin' );
		}

		$plugin_info = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $plugin_info->version ) ) {
			error_log( 'Failed to fetch plugin version' );
			wp_send_json_error( 'Failed to fetch plugin version' );
		}

		$latest_version = $plugin_info->version;

		if ( version_compare( $installed_version, $latest_version, '<' ) ) {
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->upgrade( $plugin_file );
			activate_plugin( $plugin_file );
			return $result ? wp_send_json_success( 'Upgraded to lates version :' . $latest_version ) : wp_send_json_error( 'Upgrade failed failed!' );
		}

		set_transient( 'show_plugin_updated_notice', true, 1000 );
		return wp_send_json_success( 'Plugin up to date' );
	}


	/**
	 * Utils for this class
	 *
	 * @param string $version1 is the first version
	 * @param string $version2 is the second vesion
	 */
	public function compare_versions( $version1, $version2 ) {
		$parts1 = explode( '.', $version1 );
		$parts2 = explode( '.', $version2 );

		$max_length = max( count( $parts1 ), count( $parts2 ) );

		for ( $i = 0; $i < $max_length; $i++ ) {
			$num1 = isset( $parts1[ $i ] ) ? (int) $parts1[ $i ] : 0;
			$num2 = isset( $parts2[ $i ] ) ? (int) $parts2[ $i ] : 0;

			if ( $num1 > $num2 ) {
				return 1; // $version1 is greater
			}
			if ( $num1 < $num2 ) {
				return -1; // $version2 is greater
			}
		}

		return 0; // Versions are equal
	}

	private function get_opt_in_banner_class() {
		$hidden              = ! self::is_master_sync_on();
		$opt_in_banner_class = 'notice notice-success is-dismissible';

		if ( $hidden ) {
			$opt_in_banner_class = 'notice notice-success is-dismissible';
		} else {
			$opt_in_banner_class = 'notice notice-success is-dismissible hidden';
		}
		return $opt_in_banner_class;
	}

	private function get_opt_out_banner_class() {
		$hidden               = ! self::is_master_sync_on();
		$opt_out_banner_class = 'notice notice-info is-dismissible';

		if ( $hidden ) {
			$opt_out_banner_class = 'notice notice-info is-dismissible hidden';
		} else {
			$opt_out_banner_class = 'notice notice-info is-dismissible';
		}
		return $opt_out_banner_class;
	}

	private function get_opt_out_modal_message() {
		return '
            <h2>Opt out of automatic product sync?</h2>
            <p>
                If you opt out, we will not be syncing your products to your Meta catalog even after you update your Facebook for WooCommerce plugin.
            </p>

            <p>
                However, we strongly recommend syncing all products to help drive sales and optimize ad performance. Products that aren’t synced will not be available for your customers to discover and buy in your ads and shops.
            </p>

            <p>
                If you change your mind later, you can easily un-sync your products by going to WooCommerce > Products.
            </p>
        ';
	}

	private function get_opt_out_modal_buttons() {
		return '
            <a href="javascript:void(0);" class="button wc-forward upgrade_plugin_button" id="modal_opt_out_button">
               Opt out
            </a>
        ';
	}
}
