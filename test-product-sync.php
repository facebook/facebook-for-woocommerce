<?php
/**
 * Test script for Facebook product data generation
 * 
 * This script will load a product and run the prepare_product method
 * to test our attribute mapper without sending data to Facebook.
 */

// Ensure WP is loaded
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

// Make sure we have WooCommerce
if (!function_exists('WC')) {
    die('WooCommerce is not active');
}

// Check if we're logged in as admin
if (!current_user_can('manage_options')) {
    die('You need to be logged in as an administrator to run this test');
}

// Product ID to test (default to first product if not specified)
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : get_option('woocommerce_product_to_test', null);

if (!$product_id) {
    // Get first product
    $products = wc_get_products(array('limit' => 1));
    if (!empty($products)) {
        $product_id = $products[0]->get_id();
        update_option('woocommerce_product_to_test', $product_id);
    } else {
        die('No products found in your store.');
    }
}

echo "<h1>Testing Facebook Product Sync for Product #{$product_id}</h1>";

// Load the product
$product = wc_get_product($product_id);
if (!$product) {
    die("Product #{$product_id} not found");
}

echo "<p>Product: " . esc_html($product->get_name()) . "</p>";

// Create Facebook product
$fb_product = new WC_Facebook_Product($product->get_id());

// Log path to help with debugging
$log_file = WP_CONTENT_DIR . '/uploads/fb-product-debug.log';
echo "<p>Debug log will be written to: " . esc_html($log_file) . "</p>";

// Clear previous log
file_put_contents($log_file, "=== START FACEBOOK PRODUCT TEST ===\n\n");

// Run prepare_product for different types to test attributes
$retailer_id = \WC_Facebookcommerce_Utils::get_fb_retailer_id($product);

echo "<h2>Testing normal product preparation</h2>";
$normal_data = $fb_product->prepare_product($retailer_id, \WC_Facebook_Product::PRODUCT_PREP_TYPE_NORMAL);

echo "<h2>Testing items batch product preparation</h2>";
$batch_data = $fb_product->prepare_product($retailer_id, \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH);

echo "<h2>Testing feed product preparation</h2>";
$feed_data = $fb_product->prepare_product($retailer_id, \WC_Facebook_Product::PRODUCT_PREP_TYPE_FEED);

// Write log footer
file_put_contents($log_file, "=== END FACEBOOK PRODUCT TEST ===\n", FILE_APPEND);

echo "<p>Test complete! Check the debug log for detailed information.</p>";
echo "<p>Log File: <code>" . esc_html($log_file) . "</code></p>";
echo "<p><a href='?product_id=" . ($product_id + 1) . "'>Test next product</a></p>"; 