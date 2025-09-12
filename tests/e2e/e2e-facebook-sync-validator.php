<?php
/**
 *  E2E Facebook Sync Validator - For Simple & Variable Products
 *
 * Validates product sync between WooCommerce and Facebook with comprehensive debugging
 * Follows same flow pattern for both product types: getData -> checkSync -> compareFields
 *
 * Usage: php e2e-facebook-sync-validator.php <product_id> [wait_seconds]
 */

// Bootstrap WordPress
$wp_path = '/tmp/wordpress/wp-load.php';

if (!file_exists($wp_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'WordPress not found at: ' . $wp_path
    ]);
    exit(1);
}

require_once($wp_path);

/**
 * Facebook Sync Validator Class
 */
class FacebookSyncValidator {

    private $product_id;
    private $product;
    private $integration;
    private $result;

    /**
     * Field mappings between WooCommerce and Facebook fields
     */
    private const FIELD_MAPPINGS = [
        'title' => 'name',
        'price' => 'price',
        'retailer_id' => 'retailer_id',
        'availability' => 'availability',
        'description' => 'description',
        'brand' => 'brand',
        'condition' => 'condition'
    ];

    /**
     * Helper method to add debug messages
     */
    private function debug($message) {
        $this->result['debug'][] = $message;
    }


    /**
     * Initialize the validator and verify dependencies
     */
    public function __construct($product_id, $wait_seconds = 5) {
        $this->product_id = (int)$product_id;
        $this->result = [
            'success' => false,
            'product_id' => $this->product_id,
            'product_type' => 'unknown',
            'sync_status' => 'unknown',
            'retailer_id' => null,
            'facebook_id' => null,
            'mismatches' => [],
            'summary' => [],
            'debug' => [],
            'error' => null
        ];

        // Wait for Facebook processing
        if ($wait_seconds > 0) {
            sleep($wait_seconds);
            $this->debug("Waited {$wait_seconds} seconds before validation");
        }

        $this->validateDependencies();
        $this->initializeProduct();
        $this->initializeIntegration();
    }

    /**
     * Check if required plugins and extensions are available
     */
    private function validateDependencies() {
        if (!function_exists('wc_get_product')) {
            throw new Exception('WooCommerce not active');
        }
        if (!function_exists('facebook_for_woocommerce')) {
            throw new Exception('Facebook plugin not loaded');
        }
        if (!$this->product_id) {
            throw new Exception('Product ID required');
        }
    }

    /**
     * Initialize product
     */
    private function initializeProduct() {
        $this->debug("Initializing product: {$this->product_id}");
        $this->product = wc_get_product($this->product_id);

        // Fail fast if the product ID doesn't exist in WooCommerce
        if (!$this->product) {
            throw new Exception("Product {$this->product_id} not found");
        }

        // Get and log retailer ID
        $retailer_id = WC_Facebookcommerce_Utils::get_fb_retailer_id($this->product);
        $this->debug("Product retailer ID: {$retailer_id} and type: {$this->product->get_type()}");

        $this->result['product_type'] = $this->product->get_type();
        $this->debug("Initialized {$this->result['product_type']} product: {$this->product->get_name()}");
    }

    /**
     * Set up Facebook API integration and verify configuration
     */
    private function initializeIntegration() {
        $this->integration = facebook_for_woocommerce()->get_integration();
        if (!$this->integration) {
            throw new Exception('Facebook integration not available');
        }
        if (!$this->integration->is_configured()) {
            throw new Exception('Facebook integration not configured');
        }
        $this->debug('Facebook integration initialized and configured');
    }

    /**
     * Main validation method - validates sync between WooCommerce and Facebook
     * 1. Get both platform data (WooCommerce + Facebook)
     * 2. Check sync status using fetched Facebook data
     * 3. Compare fields between platforms
     * 4. Set success based on sync status and no mismatches
     */
    public function validate() {
        try {
            $actual_type = $this->product->get_type();

            // Step 1: Get both platform data (WooCommerce + Facebook)
            $data = $this->getBothPlatformData($actual_type);

            // Step 2: Check sync status using fetched Facebook data
            $this->checkSyncStatus($data);

            // Step 3: Compare fields between platforms
            $this->compareFields($data);

            // Set success based on sync status and no mismatches
            $this->result['success'] = ($this->result['sync_status'] === 'synced' && count($this->result['mismatches']) === 0);
        } catch (Exception $e) {
            $this->result['error'] = $e->getMessage();
            $this->debug("Validation failed: " . $e->getMessage());
        }
        return $this->result;
    }

    /**
     * Get both WooCommerce and Facebook data for any product type
     */
    private function getBothPlatformData($product_type) {
        $this->debug("Fetching both platform data for {$product_type} product");

        if ($product_type === 'variable') {
            return $this->getVariableProductData();
        } else {
            return $this->getSimpleProductData();
        }
    }

    /**
     * Get data for simple products
     */
    private function getSimpleProductData() {
        // Get WooCommerce data
        $retailer_id = WC_Facebookcommerce_Utils::get_fb_retailer_id($this->product);
        $this->result['retailer_id'] = $retailer_id;

        $woo_data = $this->extractWooCommerceFields($this->product, $retailer_id);
        $this->debug("Extracted WooCommerce data for simple product");
        // $this->debug("WooCommerce data: " . json_encode($woo_data, JSON_PRETTY_PRINT));

        // Get Facebook data
        $facebook_data = $this->fetchFacebookData($retailer_id, 'simple');

        return [
            'type' => 'simple',
            'woo_data' => [$woo_data],
            'facebook_data' => [$facebook_data]
        ];
    }

    /**
     * Get data for variable products (variations only)
     */
    private function getVariableProductData() {
        $failed_variations = [];
        $woo_data_array = [];
        $facebook_data_array = [];

        // Set parent retailer_id for result tracking
        $this->result['retailer_id']  = WC_Facebookcommerce_Utils::get_fb_retailer_id($this->product);

        // All variations
        $variations = $this->product->get_children();
        $this->debug("Processing " . count($variations) . " variations: [" . implode(', ', $variations) . "]");

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);

            try {
                $var_retailer_id = WC_Facebookcommerce_Utils::get_fb_retailer_id($variation);
                $woo_data_array[] = $this->extractWooCommerceFields($variation, $var_retailer_id);
                $facebook_data_array[] = $this->fetchFacebookData($var_retailer_id, 'variable');

                $this->debug("Extracted variation {$variation_id} data successfully");
            } catch (Exception $e) {
                $failed_variations[] = $variation_id;
                $this->debug("Variation {$variation_id} data extraction failed: " . $e->getMessage());
            }
        }

        // Summary for variable products
        $total_variations = count($variations);
        $successful_variations = $total_variations - count($failed_variations);
        $this->result['summary'] = [
            'total_variations' => $total_variations,
            'successful_variations' => $successful_variations,
            'failed_variations' => count($failed_variations),
            'failed_variation_ids' => $failed_variations
        ];

        if (count($failed_variations) > 0) {
            $this->debug("Failed to process variations: " . implode(', ', $failed_variations));
        }

        return [
            'type' => 'variable',
            'woo_data' => $woo_data_array,
            'facebook_data' => $facebook_data_array
        ];
    }

    /**
     * Extract WooCommerce product fields
     */
    private function extractWooCommerceFields($product, $retailer_id) {
        // Create Facebook product wrapper to get the prepared data; variations will have parent product
        $fb_product = $product->get_parent_id() ?
            new WC_Facebook_Product($product, new WC_Facebook_Product(wc_get_product($product->get_parent_id()))) :
            new WC_Facebook_Product($product);

        $product_data = $fb_product->prepare_product($retailer_id, WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH);
        return [
            'id' => $product->get_id(),  // Always include id for both simple and variable products
            'title' => $product_data['title'] ?? $product->get_name(),
            'price' => $product_data['price'] ?? $product->get_regular_price(),
            'description' => $this->truncateText($product_data['description'] ?? '', 100),
            'availability' => $product_data['availability'] ?? '',
            'retailer_id' => $retailer_id,
            'condition' => $product_data['condition'] ?? '',
            'brand' => $product_data['brand'] ?? '',
            'color' => $product_data['color'] ?? '',
            'size' => $product_data['size'] ?? ''
        ];
    }

    /**
     * Fetch Facebook data via API
     */
    private function fetchFacebookData($retailer_id, $context = 'simple') {
        try {
            $api = facebook_for_woocommerce()->get_api();
            $catalog_id = $this->integration->get_product_catalog_id();

            // Use get_product_facebook_fields with full fields string
            $fields = 'id,name,price,description,availability,retailer_id,condition,brand,color,size,product_group{id}';
            $response = $api->get_product_facebook_fields($catalog_id, $retailer_id, $fields);

            // Log the full API response for debugging
            // $this->debug("Facebook API response for {$retailer_id}: " . json_encode($response, JSON_PRETTY_PRINT));

            if ($response && $response->response_data && isset($response->response_data['data'][0])) {
                $fb_data = $response->response_data['data'][0];
                $this->debug("Successfully fetched Facebook data for {$retailer_id}");

                return [
                    'id' => $fb_data['id'] ?? null,
                    'name' => $fb_data['name'] ?? '',
                    'price' => $fb_data['price'] ?? '',
                    'description' => $fb_data['description'] ?? '',
                    'availability' => $fb_data['availability'] ?? '',
                    'retailer_id' => $fb_data['retailer_id'] ?? '',
                    'condition' => $fb_data['condition'] ?? '',
                    'brand' => $fb_data['brand'] ?? '',
                    'color' => $fb_data['color'] ?? '',
                    'size' => $fb_data['size'] ?? '',
                    'product_group_id' => $fb_data['product_group']['id'] , //Simple products also have some product_group id
                    'found' => true
                ];

            } else {
                $this->debug("No Facebook data found for retailer_id: {$retailer_id}");
                return ['found' => false];
            }

        } catch (Exception $e) {
            $this->debug("Facebook API error for {$retailer_id}: " . $e->getMessage());
            return ['found' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if products are synced to Facebook (unified for both simple and variable)
     */
    private function checkSyncStatus($data) {
        $total_product_count = count($data['woo_data']);
        $synced_products = array_filter($data['facebook_data'], function($fb_data) {
            return $fb_data['found'] ?? false;
        });
        $synced_count = count($synced_products);

        // Get unique product group IDs from synced products
        $product_group_ids = array_unique(array_filter(array_map(function($fb_data) {
            return $fb_data['product_group_id'] ?? null;
        }, $synced_products)));

        // Synced if:
        // 1. ALL products/variations exist in Facebook
        // 2. All products/variations belong to the same product group
        if ($total_product_count > 0 && $synced_count === $total_product_count && count($product_group_ids) === 1) {
            $this->result['sync_status'] = 'synced';
            $this->result['facebook_id'] = reset($product_group_ids); // Use the common group ID
            $this->debug("{$data['type']} Product {$this->result['retailer_id']} is fully synced with Facebook product group: {$this->result['facebook_id']}");

        } else {
            $this->result['sync_status'] = 'not_synced';

            if ($synced_count < $total_product_count) {
                // Find missing products/variations
                $missing_items = [];
                for ($i = 0; $i < $total_product_count; $i++) {
                    if (!($data['facebook_data'][$i]['found'] ?? false)) {
                        // cos we can't just loop on $data['facebook_data'] as it does not have retailer_id during failure fetches
                        $product_id = $data['woo_data'][$i]['id'] ?? "unknown_{$i}";
                        $retailer_id = $data['woo_data'][$i]['retailer_id'] ?? "unknown_retailer_{$i}";
                        $missing_items[] = "ID:{$product_id} (retailer:{$retailer_id})";
                    }
                }

                $this->debug("Products/variations not synced to Facebook: " . implode(', ', $missing_items));

            } elseif (count($product_group_ids) > 1) {
                $product_type = $data['type'] === 'variable' ? 'variations' : 'product';
                $this->debug("{$data['type']} product not synced - {$product_type} belong to different product groups: " . implode(', ', $product_group_ids));
            }
        }
    }

    /**
     * Compare fields between WooCommerce and Facebook for all products
     */
    private function compareFields($data) {
        $mismatches = [];
        $compared_products = 0;

        // Loop through each product (simple = 1 item, variable = N items)
        for ($i = 0; $i < count($data['woo_data']); $i++) {
            $woo_data = $data['woo_data'][$i];
            $facebook_data = $data['facebook_data'][$i];

            if (!($facebook_data['found'] ?? false)) {
                continue; // Skip products not found in Facebook
                // these are logged in checkSyncStatus as missing variations
            }

            $compared_products++;

            // Use the consistent id field from woo_data for both simple and variable products
            $product_id = $woo_data['id'] ?? $this->product_id;

            $product_mismatches = $this->compareProductFields(
                $woo_data,
                $facebook_data,
                $product_id
            );

            if (count($product_mismatches) > 0) {
                $mismatches = array_merge($mismatches, $product_mismatches);
                $this->debug("Found mismatches for product/variation -  {$product_id}");
            }
        }

        $this->result['mismatches'] = $mismatches;
        $this->debug("Compared fields for {$compared_products} products, found " . count($mismatches) . " total mismatches");
    }

    /**
     * Compare fields for a single product
     */
    private function compareProductFields($woo_data, $facebook_data, $product_id) {
        $mismatches = [];

        foreach (self::FIELD_MAPPINGS as $woo_field => $fb_field) {
            $woo_value = $woo_data[$woo_field] ?? '';
            $fb_value = $facebook_data[$fb_field] ?? '';

            $normalized_woo = $this->normalizeValue($woo_value, $woo_field);
            $normalized_fb = $this->normalizeValue($fb_value, $woo_field);

            if ($normalized_woo !== $normalized_fb) {
                $this->debug("MISMATCH {$woo_field}: WooCommerce='{$woo_value}' (normalized='{$normalized_woo}') vs Facebook='{$fb_value}' (normalized='{$normalized_fb}')");

                $mismatches["{$product_id}_{$woo_field}"] = [
                    'product_id' => $product_id,
                    'field' => $woo_field,
                    'woocommerce_value' => $woo_value,
                    'facebook_value' => $fb_value
                ];
            }
        }

        return $mismatches;
    }

    /**
     * Helper function to truncate text with ellipsis
     */
    private function truncateText($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }

    private function normalizeValue($value, $field = '') {
        $normalized = trim(strtolower((string)$value));

        // Special handling for price fields
        if ($field === 'price') {
            return $this->normalizePrice($normalized);
        }

        return $normalized;
    }

    /**
     * Normalize price values to handle different currency formats
     * Examples:
     * "34 GBP" -> "34.00"
     * "£34.00" -> "34.00"
     * "$25.99" -> "25.99"
     * "19.99 USD" -> "19.99"
     */
    private function normalizePrice($price) {
        if (empty($price)) return '';

        // Remove currency symbols and codes
        $price = preg_replace('/[^\d.,]/', '', (string)$price);
        $price = preg_replace('/,(?=\d{3,})/', '', $price); // Remove thousands separators
        $price = str_replace(',', '.', $price); // Convert comma decimals

        return is_numeric($price) ? number_format((float)$price, 2, '.', '') : $price;
    }

    public function getJsonResult() {
        return json_encode($this->result, JSON_PRETTY_PRINT);
    }

    public static function validateProduct($product_id, $wait_seconds = 5) {
        $validator = new self($product_id, $wait_seconds);
        return $validator->validate();
    }
}

// Main execution when called directly
if (php_sapi_name() === 'cli') {
    try {
        $product_id = isset($argv[1]) ? (int)$argv[1] : null;
        $wait_seconds = isset($argv[2]) ? (int)$argv[2] : 10;

        if (!$product_id) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit(1);
        }

        $validator = new FacebookSyncValidator($product_id, $wait_seconds);
        $result = $validator->validate();
        echo $validator->getJsonResult();

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => ["Exception: " . $e->getMessage()]
        ]);
        exit(1);
    }
}
