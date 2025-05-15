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

class PluginUpdate {

    private \WC_Facebookcommerce $plugin;
    const ALL_PRODUCTS_PLUGIN_VERSION = '3.5.0';

    public function __construct(\WC_Facebookcommerce $plugin) {
        $this->plugin = $plugin;
        $this->add_hooks();
        $this->should_show_sync_all_banner();
        // Hook into the admin_notices to display the banner
        // add_action('admin_notices', 'fb_woocommerce_admin_banner_update_intimation');
       
    }

    private static function add_hooks() {
        add_action('in_admin_header', [ __CLASS__, 'fb_woocommerce_admin_banner_update_intimation' ] );
        add_action( 'upgrader_process_complete', [ __CLASS__, 'on_plugin_update' ], 10, 2 );
    }

    public static function on_plugin_update( $upgrader_object, $options ) {
        if (
            $options['action'] === 'update' &&
            $options['type'] === 'plugin' &&
            ! empty( $options['plugins'] )
        ) {
            foreach ( $options['plugins'] as $plugin ) {
                if ( strpos( $plugin, 'facebook-for-woocommerce/facebook-for-woocommerce.php' ) !== false ) {
                    error_log( 'Facebook for WooCommerce was updated.' );

                    // 👉 Add your upgrade logic here
                }
            }
        }
    }

    public function should_show_sync_all_banner() {
        $current_version = $this->plugin->get_version();

        // Show banner to inform users about the version upgrade changes.
        if($current_version <= self::ALL_PRODUCTS_PLUGIN_VERSION){
           
            // Update database !!
        }
    }

    function fb_woocommerce_admin_banner_update_intimation() {
        $screen = get_current_screen();
        if (isset($screen->id) && $screen->id === 'marketing_page_wc-facebook') {
            echo '
            <div class="notice notice-info is-dismissible" style="background-color: #ff6600; color: white; padding: 20px; text-align: center;">
                <h2 style="font-size: 20px; font-weight: bold;">🚨 Special Offer: 20% Off Your First Order 🚨</h2>
                <p style="font-size: 16px; margin: 10px 0;">Hurry up! Visit our <a href="https://www.facebook.com/yourshop" target="_blank" style="color: #ffffff; text-decoration: underline;">Facebook Shop</a> to grab this limited-time offer.</p>
                <a href="https://www.facebook.com/yourshop" target="_blank" style="display: inline-block; background-color: #003366; color: white; padding: 10px 20px; border-radius: 5px; font-size: 16px;">Shop Now on Facebook</a>
            </div>';
        }
    }
}



