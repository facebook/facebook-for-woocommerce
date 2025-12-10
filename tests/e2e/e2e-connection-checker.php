<?php
/**
 * E2E Facebook Connection Checker
 *
 * Validates Facebook for WooCommerce plugin connection status
 *
 * Usage:
 *   php e2e-connection-checker.php
 */

// Bootstrap WordPress
$wp_path = getenv('WORDPRESS_PATH') . '/wp-load.php';

if (!file_exists($wp_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'WordPress not found at: ' . $wp_path
    ]);
    exit(1);
}

require_once($wp_path);

// Check if Facebook for WooCommerce plugin is loaded
if (!function_exists('facebook_for_woocommerce')) {
    echo json_encode([
        'success' => false,
        'error' => 'Facebook for WooCommerce plugin not loaded'
    ]);
    exit(1);
}

try {
    // Get connection handler
    $conn = facebook_for_woocommerce()->get_connection_handler();

    // Gather connection data
    $result = [
        'success' => true,
        'connected' => $conn->is_connected(),
        'access_token' => $conn->get_access_token(),
        'pixel_id' => get_option('wc_facebook_pixel_id'),
        'business_manager_id' => $conn->get_business_manager_id()
    ];

    echo json_encode($result);
    exit(0);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ]);
    exit(1);
}
