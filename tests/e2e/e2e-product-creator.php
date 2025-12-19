<?php
/**
 * E2E Product Creator - Creates WooCommerce products programmatically for testing
 *
 * Usage: php e2e-product-creator.php <product_type> [name] [price] [stock]
 * Example: php e2e-product-creator.php simple "Test Product" 19.99 10
 */

// Bootstrap WordPress

/**
 * If you are running this script from a "Local" wordpress setup, you may need to set the following environment variables:
 * MYSQL_HOME=/Users/<unixname>/Library/Application Support/Local/run/<id>/conf/mysql
 * PHPRC=/Users/<unixname>/Library/Application Support/Local/run/<id>/conf/php
 * also set $wp_path to the path of your local wp-load.php file: /Users/<unixname>/Local Sites/<sitename>/app/public/wp-load.php
 */

$wp_path = getenv('WORDPRESS_PATH') . '/wp-load.php';

if (!file_exists($wp_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'WordPress not found at: ' . $wp_path
    ]);
    exit(1);
}

require_once($wp_path);

/**
 * Product Creator Class
 */
class E2EProductCreator {

    /**
     * Create a simple product
     */
    public static function createSimpleProduct($name, $sku, $price = 19.99, $stock = 10, $category_ids = []) {
        try {
            // Verify WooCommerce is available
            if (!function_exists('wc_get_product')) {
                throw new Exception('WooCommerce not active');
            }

            // Create product
            $product = new WC_Product_Simple();

            // Set basic properties
            $product->set_name($name);
            $product->set_sku($sku);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_description('Test product created for E2E testing via API');
            $product->set_short_description('E2E test product');

            // Set price
            $product->set_regular_price($price);
            $product->set_price($price);

            // Set stock
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->set_stock_status('instock');

            // Set categories if provided
            if (!empty($category_ids)) {
                $product->set_category_ids($category_ids);
            }

            // Save product
            $product_id = $product->save();

            if (!$product_id) {
                throw new Exception('Failed to save product');
            }

            return [
                'success' => true,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
                'category_ids' => $category_ids,
                'message' => "Simple product created successfully with ID: {$product_id}"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a subscription product (using Subscriptions For WooCommerce by WPSwings - Free Version)
     *
     * This creates a simple product with subscription settings enabled via the WPSwings plugin.
     * The plugin uses post meta to designate a product as a subscription product:
     * - _wps_sfw_product = 'yes' (enables subscription)
     * - wps_sfw_subscription_number = interval count (e.g., 1 for monthly)
     * - wps_sfw_subscription_interval = period (day, week, month, year)
     * - wps_sfw_subscription_expiry_number = expiry count (optional)
     * - wps_sfw_subscription_expiry_interval = expiry period (optional)
     */
    public static function createSubscriptionProduct($name, $sku, $price = 19.99, $stock = 10, $subscription_interval = 'month', $subscription_number = 1, $category_ids = []) {
        try {
            // Verify WooCommerce is available
            if (!function_exists('wc_get_product')) {
                throw new Exception('WooCommerce not active');
            }

            // Verify Subscriptions For WooCommerce plugin is active
            if (!function_exists('wps_sfw_check_plugin_enable') || !wps_sfw_check_plugin_enable()) {
                throw new Exception('Subscriptions For WooCommerce plugin is not active or not enabled. Please enable it from WP Swings > Subscriptions for WooCommerce settings.');
            }

            // Create product as a simple product first
            $product = new WC_Product_Simple();

            // Set basic properties
            $product->set_name($name);
            $product->set_sku($sku);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_description('Test subscription product created for E2E testing via API');
            $product->set_short_description('E2E test subscription product');

            // Set price
            $product->set_regular_price($price);
            $product->set_price($price);

            // Set stock
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->set_stock_status('instock');

            // Set categories if provided
            if (!empty($category_ids)) {
                $product->set_category_ids($category_ids);
            }

            // Save product first to get the ID
            $product_id = $product->save();

            if (!$product_id) {
                throw new Exception('Failed to save product');
            }

            // Now add subscription meta fields (Subscriptions For WooCommerce by WPSwings)
            // Enable subscription for this product
            update_post_meta($product_id, '_wps_sfw_product', 'yes');

            // Set subscription interval (e.g., 1 month, 2 weeks, etc.)
            update_post_meta($product_id, 'wps_sfw_subscription_number', $subscription_number);
            update_post_meta($product_id, 'wps_sfw_subscription_interval', $subscription_interval);

            // Optional: Set expiry (empty = never expires)
            update_post_meta($product_id, 'wps_sfw_subscription_expiry_number', '');
            update_post_meta($product_id, 'wps_sfw_subscription_expiry_interval', '');

            // Optional: Set trial period (empty = no trial)
            update_post_meta($product_id, 'wps_sfw_subscription_free_trial_number', '');
            update_post_meta($product_id, 'wps_sfw_subscription_free_trial_interval', '');

            // Optional: Set initial signup fee (empty = no signup fee)
            update_post_meta($product_id, 'wps_sfw_subscription_initial_signup_price', '');

            return [
                'success' => true,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_url' => get_permalink($product_id),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
                'subscription_interval' => $subscription_interval,
                'subscription_number' => $subscription_number,
                'category_ids' => $category_ids,
                'message' => "Subscription product created successfully with ID: {$product_id}"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a variable product with variations
     */
    public static function createVariableProduct($name, $sku, $price = 29.99, $attributes = null) {
        try {
            // Verify WooCommerce is available
            if (!function_exists('wc_get_product')) {
                throw new Exception('WooCommerce not active');
            }

            // Create parent variable product
            $product = new WC_Product_Variable();

            // Set basic properties
            $product->set_name($name);
            $product->set_sku($sku);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_description('Test variable product created for E2E testing via API');
            $product->set_short_description('E2E test variable product');

            // Default attributes if not provided
            if (!$attributes) {
                $attributes = [
                    'Color' => ['Red', 'Blue', 'Green']
                ];
            }

            // Create attributes
            $product_attributes = [];
            foreach ($attributes as $attr_name => $attr_values) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($attr_name);
                $attribute->set_options($attr_values);
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $product_attributes[] = $attribute;
            }

            $product->set_attributes($product_attributes);

            // Save parent product
            $product_id = $product->save();

            if (!$product_id) {
                throw new Exception('Failed to save parent product');
            }

            // Create variations
            $variation_ids = [];
            foreach ($attributes as $attr_name => $attr_values) {
                foreach ($attr_values as $attr_value) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation->set_attributes([strtolower($attr_name) => $attr_value]);
                    $variation->set_regular_price($price);
                    $variation->set_price($price);
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity(10);
                    $variation->set_stock_status('instock');

                    $variation_id = $variation->save();
                    if ($variation_id) {
                        $variation_ids[] = $variation_id;
                    }
                }
            }

            return [
                'success' => true,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'variation_ids' => $variation_ids,
                'variation_count' => count($variation_ids),
                'message' => "Variable product created successfully with ID: {$product_id} and " . count($variation_ids) . " variations"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Main execution when called directly
if (php_sapi_name() === 'cli') {
    try {
        $product_type = isset($argv[1]) ? $argv[1] : 'simple';
        $name = isset($argv[2]) ? $argv[2] : 'Test Product ' . date('Y-m-d H:i:s');
        $price = isset($argv[3]) ? floatval($argv[3]) : 19.99;
        $stock = isset($argv[4]) ? intval($argv[4]) : 10;
        $sku = isset($argv[5]) ? $argv[5] : null;
        $category_ids_json = isset($argv[6]) ? $argv[6] : '[]';
        $category_ids = json_decode($category_ids_json, true) ?: [];

        if ($product_type === 'simple') {
            $result = E2EProductCreator::createSimpleProduct($name, $sku, $price, $stock, $category_ids);
        } elseif ($product_type === 'variable') {
            $result = E2EProductCreator::createVariableProduct($name, $sku, $price);
        } elseif ($product_type === 'subscription') {
            // For subscription products, additional params:
            // argv[7] = subscription_interval (day, week, month, year)
            // argv[8] = subscription_number (interval count)
            $subscription_interval = isset($argv[7]) ? $argv[7] : 'month';
            $subscription_number = isset($argv[8]) ? intval($argv[8]) : 1;
            $result = E2EProductCreator::createSubscriptionProduct($name, $sku, $price, $stock, $subscription_interval, $subscription_number, $category_ids);
        } else {
            $result = [
                'success' => false,
                'error' => "Unsupported product type: {$product_type}. Use 'simple', 'variable', or 'subscription'."
            ];
        }

        echo json_encode($result, JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit(1);
    }
}
