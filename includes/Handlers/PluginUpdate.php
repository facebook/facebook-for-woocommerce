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
    }

    private static function add_hooks() {
        add_action('wp_ajax_wc_facebook_opt_out_of_sync', [ __CLASS__,  'opt_out_of_sync_clicked']);
        add_action('wp_ajax_nopriv_wc_facebook_opt_out_of_sync', [ __CLASS__,'opt_out_of_sync_clicked']); 
        
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

        /**
         * Show the banner if the user is having a version lower than that of the ALl products version
         */

        if($current_version <= self::ALL_PRODUCTS_PLUGIN_VERSION){
            
            add_action('admin_notices', [ __CLASS__, 'fb_woocommerce_admin_banner_upcoming_version_change' ], 0); 
        }
    }

    public function fb_woocommerce_admin_banner_upcoming_version_change() {
        $screen = get_current_screen();
        if (isset($screen->id) && $screen->id === 'marketing_page_wc-facebook') {
            echo '<div class="notice notice-info is-dismissible" style="padding: 15px">
            When you update to version <b>'.self::ALL_PRODUCTS_PLUGIN_VERSION.'</b> and above, your products will automatically sync to your catalog at Meta
            The next time you update your Facebook for WooCommerce plugin, all your products will be synced automatically. This is to help you drive sales and optimize your ad performance.<a href="https://www.facebook.com"> Learn more about changes to how your products will sync to Meta </a>
                <p>
                    <a href="edit.php?post_type=product"> Review products </a>
                    <a href="javascript:void(0);" style="text-decoration: underline; cursor: pointer; margin-left: 10px" id="opt_out_of_sync_button"> Opt out of automatic sync</a>
                </p>
            </div>';
        }
    }

    function opt_out_of_sync_clicked() {
        error_log("🔥 custom_woocommerce_action was triggered!"); // Log execution
       
        $some_variable = isset($_POST['variable1']) ? sanitize_text_field($_POST['variable1']) : '';
        $another_variable = isset($_POST['variable2']) ? sanitize_text_field($_POST['variable2']) : '';

        $response = array(
            "message" => "WooCommerce Action Executed!",
            "variable1" => $some_variable,
            "variable2" => $another_variable,
            "status" => "success"
        );
        
        wp_send_json_success($response);
    }
}



