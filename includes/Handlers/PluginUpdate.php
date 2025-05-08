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
    const ALL_PRODUCTS_PLUGIN_VERSION = '3.4.5';

    public function __construct(\WC_Facebookcommerce $plugin) {
        $this->plugin = $plugin;
        $this->add_hooks();
        $this->should_show_sync_all_banner();
    }

    private static function add_hooks() {
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
        if($current_version >= self::ALL_PRODUCTS_PLUGIN_VERSION){
            // Update database !!
        }
    }
}
