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
 * PluginRender
 * This is an class that is triggered for Opt in/ Opt out experience
 * from @ver 3.4.11
 */
class PluginRender {
	/** @var object storing plugin object */
	private \WC_Facebookcommerce $plugin;

	/** @var string opt out plugin version action */
	const ALL_PRODUCTS_PLUGIN_VERSION = '3.5.3';

	/** @var string opt out sync action */
	const ACTION_OPT_OUT_OF_SYNC = 'wc_facebook_opt_out_of_sync';

	/** @var string opt out sync action */
	const ACTION_SYNC_BACK_IN = 'wc_facebook_sync_back_in';

	/** @var string master sync option */
	const MASTER_SYNC_OPT_OUT_TIME = 'wc_facebook_master_sync_opt_out_time';

	/** @var string  action */
	const ACTION_CLOSE_BANNER = 'wc_banner_close_action';

	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;
		$this->should_show_banners();
		$this->add_hooks();
	}

	public static function enqueue_assets() {
		wp_enqueue_script( 'wc-backbone-modal', null, array( 'backbone' ) );
		wp_enqueue_script(
			'facebook-for-woocommerce-modal',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/modal.js',
			array( 'jquery', 'wc-backbone-modal', 'jquery-blockui' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
		wp_enqueue_script(
			'facebook-for-woocommerce-plugin-update',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/plugin-rendering.js',
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
				'banner_close'                    => wp_create_nonce( self::ACTION_CLOSE_BANNER ),
				'sync_back_in'                    => wp_create_nonce( self::ACTION_SYNC_BACK_IN ),
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
		add_action( 'wp_ajax_wc_banner_close_action', [ __CLASS__,  'reset_upcoming_version_banners' ] );
		add_action( 'wp_ajax_nopriv_wc_banner_close_action', [ __CLASS__,'reset_upcoming_version_banners' ] );
	}

	public function should_show_banners() {
		$current_version = $this->plugin->get_version();
		/**
		 * Case when current version is less or equal to latest
		 * but latest is below 3.5.1
		 * Should show the opt in/ opt out banner
		 */
		if ( version_compare( $current_version, self::ALL_PRODUCTS_PLUGIN_VERSION, '<' ) ) {
			if ( get_transient( 'upcoming_woo_all_products_banner_hide' ) ) {
				return;
			}
			add_action( 'admin_notices', [ __CLASS__, 'upcoming_woo_all_products_banner' ], 0, 1 );
		}
	}

	public static function get_opt_out_time() {
		$option_value = get_option( self::MASTER_SYNC_OPT_OUT_TIME );
		if ( ! $option_value ) {
			return '';
		}
		return $option_value;
	}

	public static function is_master_sync_on() {
		$option_value = self::get_opt_out_time();
		return '' === $option_value;
	}

	public static function upcoming_woo_all_products_banner() {
		$screen = get_current_screen();

		if ( isset( $screen->id ) && 'marketing_page_wc-facebook' === $screen->id ) {
			echo '<div id="opt_out_banner" class="' . esc_html( self::get_opt_out_banner_class() ) . '" style="padding: 15px">
            <h4>When you update to version <b>' . esc_html( self::ALL_PRODUCTS_PLUGIN_VERSION ) . '</b> your products will automatically sync to your catalog at Meta catalog</h4>
            The next time you update your Facebook for WooCommerce plugin, all your products will be synced automatically. This is to help you drive sales and optimize your ad performance. <a href="https://www.facebook.com/business/help/4049935305295468">Learn more about changes to how your products will sync to Meta</a>
                <p>
                    <a href="edit.php?post_type=product"> Review products </a>
                    <a href="javascript:void(0);" style="text-decoration: underline; cursor: pointer; margin-left: 10px" class="opt_out_of_sync_button"> Opt out of automatic sync</a>
                </p>
            </div>
            ';

			echo '<div id="opted_our_successfullly_banner" class="' . esc_html( self::get_opted_out_successfully_banner_class() ) . '" style="padding: 15px">
            <h4>You’ve opted out of automatic syncing on the next plugin update </h4>
                <p>
                    Products that are not synced will not be available for your customers to discover on your ads and shops. To manually add products, <a href="https://www.facebook.com/business/help/4049935305295468">learn how to sync products to your Meta catalog</a>
                </p>
            </div>';
		}
	}

	public static function opt_out_of_sync_clicked() {
			$latest_date = gmdate( 'Y-m-d H:i:s' );
			update_option( self::MASTER_SYNC_OPT_OUT_TIME, $latest_date );
			wp_send_json_success( 'Opted out successfully' );
	}

	/**
	 * Banner for initmation of WooAllProducts version will show up
	 * after a week
	 */
	public static function reset_upcoming_version_banners() {
		set_transient( 'upcoming_woo_all_products_banner_hide', true, 7 * DAY_IN_SECONDS );
	}


	public static function get_opted_out_successfully_banner_class() {
		$hidden              = ! self::is_master_sync_on();
		$opt_in_banner_class = 'notice notice-success is-dismissible';

		if ( $hidden ) {
			$opt_in_banner_class = 'notice notice-success is-dismissible';
		} else {
			$opt_in_banner_class = 'notice notice-success is-dismissible hidden';
		}
		return $opt_in_banner_class;
	}

	public static function get_opt_out_banner_class() {
		$hidden               = ! self::is_master_sync_on();
		$opt_out_banner_class = 'notice notice-info is-dismissible';

		if ( $hidden ) {
			$opt_out_banner_class = 'notice notice-info is-dismissible hidden';
		} else {
			$opt_out_banner_class = 'notice notice-info is-dismissible';
		}
		return $opt_out_banner_class;
	}

	public static function get_opt_out_modal_message() {
		return '
            <h4>Opt out of automatic product sync?</h4>
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

	public static function get_opt_out_modal_buttons() {
		return '
            <a href="javascript:void(0);" class="button wc-forward upgrade_plugin_button" id="modal_opt_out_button">
            	Opt out
            </a>
        ';
	}
}
