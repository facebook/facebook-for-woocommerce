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

use WooCommerce\Facebook\Products\Sync;
use WooCommerce\Facebook\Framework\Plugin\Exception;

/**
 * PluginUpdate
 * This is an class that is triggered for Opt in/ Opt out experience
 */
class PluginUpdate {
	/** @var object storing plugin object */
	private \WC_Facebookcommerce $plugin;

	/** @var string opt out plugin version action */
	// TODO: Update the version accordingly
	const ALL_PRODUCTS_PLUGIN_VERSION = '3.5.0';

	/** @var string opt out sync action */
	const ACTION_OPT_OUT_OF_SYNC = 'wc_facebook_opt_out_of_sync';

	/** @var string master sync option */
	const MASTER_SYNC_OPT_OUT_TIME = 'wc_facebook_master_sync_opt_out_time';

	public function __construct( \WC_Facebookcommerce $plugin ) {
		$this->plugin = $plugin;
		$this->should_show_sync_all_banner();
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
			)
		);
	}

	private static function add_hooks() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__,  'enqueue_assets' ] );
		add_action( 'wp_ajax_wc_facebook_opt_out_of_sync', [ __CLASS__,  'opt_out_of_sync_clicked' ] );
		add_action( 'wp_ajax_nopriv_wc_facebook_opt_out_of_sync', [ __CLASS__,'opt_out_of_sync_clicked' ] );
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

	public function should_show_sync_all_banner() {
		$current_version = $this->plugin->get_version();

		/**
		 * Show the banner if the user is having a version lower than that of the ALl products version
		 */
		if ( $current_version <= self::ALL_PRODUCTS_PLUGIN_VERSION ) {
			add_action( 'admin_notices', [ __CLASS__, 'upcoming_version_change_banner' ], 0 );
		}
	}

	public function upcoming_version_change_banner() {
		$screen               = get_current_screen();
		$hidden               = ! self::is_master_sync_on();
		$opt_out_banner_class = 'notice notice-info is-dismissible';
		$opt_in_banner_class  = 'notice notice-success is-dismissible';

		if ( $hidden ) {
			$opt_in_banner_class  = 'notice notice-success is-dismissible';
			$opt_out_banner_class = 'notice notice-info is-dismissible hidden';
		} else {
			$opt_out_banner_class = 'notice notice-info is-dismissible';
			$opt_in_banner_class  = 'notice notice-success is-dismissible hidden';
		}

		// TODO: Update the links
		if ( isset( $screen->id ) && 'marketing_page_wc-facebook' === $screen->id ) {
			echo '<div id="opt_out_banner" class="' . esc_html( $opt_out_banner_class ) . '" style="padding: 15px">
            <h2>When you update to version <b>' . esc_html( self::ALL_PRODUCTS_PLUGIN_VERSION ) . '</b> your products will automatically sync to your catalog at Meta catalog</h2>
            The next time you update your Facebook for WooCommerce plugin, all your products will be synced automatically. This is to help you drive sales and optimize your ad performance. <a href="https://www.facebook.com">Learn more about changes to how your products will sync to Meta </a>
                <p>
                    <a href="edit.php?post_type=product"> Review products </a>
                    <a href="javascript:void(0);" style="text-decoration: underline; cursor: pointer; margin-left: 10px" id="opt_out_of_sync_button"> Opt out of automatic sync</a>
                </p>
            </div>';

			echo '<div id="opt_in_banner" class="' . esc_html( $opt_in_banner_class ) . '" style="padding: 15px">
            <h2>You’ve opted out of automatic syncing on the next plugin update </h2>
                <p>
                    Products that are not synced will not be available for your customers to discover on your ads and shops. To manually add products, <a href="https://www.facebook.com">learn how to sync products to your Meta catalog</a>
                </p>
            </div>';
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
}
