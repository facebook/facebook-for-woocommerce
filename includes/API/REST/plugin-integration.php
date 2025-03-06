<?php
/**
 * Sample implementation for the main plugin file.
 *
 * @package FacebookCommerce
 */

/**
 * Initialize the REST API.
 *
 * This code should be added to the main plugin file.
 */
function facebook_for_woocommerce_init_rest_api() {
    require_once( __DIR__ . '/bootstrap.php' );
    \WooCommerce\Facebook\API\REST\init();
}
add_action( 'init', 'facebook_for_woocommerce_init_rest_api', 20 );

/**
 * Enqueue the REST API JavaScript.
 *
 * This code should be added to the admin enqueue scripts function.
 */
function facebook_for_woocommerce_enqueue_rest_api_js() {
    // Only enqueue on the Facebook settings page
    if ( ! facebook_for_woocommerce()->is_plugin_settings() ) {
        return;
    }

    wp_enqueue_script(
        'facebook-for-woocommerce-connection-api',
        facebook_for_woocommerce()->get_plugin_url() . '/assets/js/admin/facebook-for-woocommerce-connection-api.js',
        [ 'jquery' ],
        \WC_Facebookcommerce::VERSION,
        true
    );

    wp_localize_script(
        'facebook-for-woocommerce-connection-api',
        'fb_woo_settings',
        [
            'api_url' => rest_url( \WooCommerce\Facebook\API\REST\Controller::API_NAMESPACE . '/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'facebook_for_woocommerce_enqueue_rest_api_js' );

/**
 * Example of how to use the REST API in JavaScript.
 *
 * This is just for demonstration purposes.
 */
function facebook_for_woocommerce_rest_api_usage_example() {
    ?>
    <script>
        // Example of how to use the REST API
        jQuery(document).ready(function($) {
            // Update settings
            $('#update-settings-button').on('click', function() {
                var settings = {
                    merchant_access_token: 'your-token',
                    access_token: 'your-token',
                    pixel_id: 'your-pixel-id'
                };

                FacebookWooCommerceConnectionAPI.updateSettings(settings)
                    .then(function(response) {
                        console.log('Settings updated:', response);
                    })
                    .catch(function(error) {
                        console.error('Error updating settings:', error);
                    });
            });

            // Uninstall
            $('#uninstall-button').on('click', function() {
                if (confirm('Are you sure you want to uninstall?')) {
                    FacebookWooCommerceConnectionAPI.uninstall()
                        .then(function(response) {
                            console.log('Uninstalled:', response);
                        })
                        .catch(function(error) {
                            console.error('Error uninstalling:', error);
                        });
                }
            });

            // Get connection extras
            FacebookWooCommerceConnectionAPI.getConnectionExtras()
                .then(function(extras) {
                    console.log('Connection extras:', extras);
                })
                .catch(function(error) {
                    console.error('Error getting connection extras:', error);
                });
        });
    </script>
    <?php
}
add_action( 'admin_footer', 'facebook_for_woocommerce_rest_api_usage_example' ); 