<?php
/**
 * E2E Test Helpers - Generic helper functions for E2E testing
 *
 * Usage: php e2e-helpers.php <operation> [args...]
 *
 * Available operations:
 *   - verify_products_facebook_fields_cleared
 *   - verify_facebook_catalog_empty
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

// Load E2E API extension for catalog queries
require_once(__DIR__ . '/E2E_API_Extension.php');

/**
 * E2E Test Helpers Class
 */
class E2ETestHelpers {

    /**
     * Verify all products have Facebook fields cleared
     */
    public static function verifyProductsFacebookFieldsCleared() {
        try {
            // Get all products and product variations
            $products = get_posts([
                'post_type' => ['product', 'product_variation'],
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);

            $issues = [];

            foreach ($products as $product) {
                $meta = get_post_meta($product->ID);
                $fb_fields = [];

                foreach ($meta as $key => $value) {
                    if (strpos($key, 'fb_') === 0 || strpos($key, '_fb_') === 0 || strpos($key, 'facebook_') === 0) {
                        if (!empty($value[0])) {
                            $fb_fields[$key] = $value[0];
                        }
                    }
                }

                if (!empty($fb_fields)) {
                    $issues[] = [
                        'id' => $product->ID,
                        'type' => $product->post_type,
                        'fields' => $fb_fields
                    ];
                }
            }

            return [
                'success' => empty($issues),
                'total' => count($products),
                'issues' => $issues
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

}

// Main execution when called directly
if (php_sapi_name() === 'cli') {
    try {
        $operation = isset($argv[1]) ? $argv[1] : '';

        if (empty($operation)) {
            echo json_encode([
                'success' => false,
                'error' => 'No operation specified. Available operations: verify_products_facebook_fields_cleared, verify_facebook_catalog_empty'
            ]);
            exit(1);
        }

        // Route to appropriate method
        switch ($operation) {
            case 'verify_products_facebook_fields_cleared':
                $result = E2ETestHelpers::verifyProductsFacebookFieldsCleared();
                break;

            case 'verify_facebook_catalog_empty':
                $result = E2ETestHelpers::verifyFacebookCatalogEmpty();
                break;

            default:
                $result = [
                    'success' => false,
                    'error' => "Unknown operation: {$operation}. Available operations: verify_products_facebook_fields_cleared, verify_facebook_catalog_empty"
                ];
                break;
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
