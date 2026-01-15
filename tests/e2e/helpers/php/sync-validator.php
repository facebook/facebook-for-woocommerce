<?php
/**
 * E2E Facebook Sync Validator - For Products & Categories
 *
 * Validates sync between WooCommerce and Facebook with comprehensive debugging
 * Follows same flow pattern for all entity types: getData -> checkSync -> compareFields
 *
 * Location: tests/e2e/helpers/php/sync-validator.php
 *
 * Usage:
 *   Products:   php helpers/php/sync-validator.php <product_id> [wait_seconds] [max_retries]
 *   Categories: php helpers/php/sync-validator.php --type=category <category_id> [wait_seconds] [max_retries]
 */

// Bootstrap WordPress
$wp_path = getenv('WORDPRESS_PATH') . '/wp-load.php';
$wp_url = getenv('WORDPRESS_URL');

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
    private $max_retries;

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
        'condition' => 'condition',
        'image_url' => 'image_url'
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
    public function __construct($product_id, $wait_seconds = 5, $max_retries = 6) {
        $this->product_id = (int)$product_id;
        $this->max_retries = (int)$max_retries;
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
            $this->result['raw_data'] = $data;

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
            'size' => $product_data['size'] ?? '',
            'image_url' => $product_data['image_link'] ?? ''
        ];
    }

    /**
     * Fetch Facebook data via API
     */
    private function fetchFacebookData($retailer_id, $context = 'simple') {
        global $wp_url;

        $api = facebook_for_woocommerce()->get_api();
        $catalog_id = $this->integration->get_product_catalog_id();
        $fields = 'id,name,price,description,availability,retailer_id,condition,brand,color,size,image_url,product_group{id},product_sets{id,retailer_id}';

        $retry_count = 0;

        do {
            try {
                $response = $api->get_product_facebook_fields($catalog_id, $retailer_id, $fields);

                if ($response && $response->response_data && isset($response->response_data['data'][0])) {
                    $fb_data = $response->response_data['data'][0];
                    $this->debug(
                        $retry_count === 0
                            ? "Successfully fetched Facebook data for {$retailer_id}"
                            : "Successfully fetched Facebook data for {$retailer_id} on retry #" . ($retry_count + 1)
                    );

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
                        'image_url' => (!empty($fb_data['image_url'])) ? $fb_data['image_url'] : ($wp_url . '/wp-content/uploads/woocommerce-placeholder.webp'),
                        'product_group_id' => $fb_data['product_group']['id'] ?? null,
                        'product_sets' => $fb_data['product_sets']['data'],
                        'found' => true
                    ];
                }
            } catch (Exception $e) {
                $this->debug("Facebook API error for {$retailer_id}: " . $e->getMessage());
                return ['found' => false, 'error' => $e->getMessage()];
            }

            $retry_count++;
            if ($retry_count < $this->max_retries) {
                $backoff_seconds = pow(2, $retry_count);
                $this->debug("Facebook API retry attempt #{$retry_count} of {$this->max_retries} for retailer_id: {$retailer_id} (waiting {$backoff_seconds}s)");
                sleep($backoff_seconds);
            }
        } while ($retry_count < $this->max_retries);

        $this->debug("No Facebook data found for retailer_id: {$retailer_id}");
        return ['found' => false];
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

/**
 * Category Sync Validator Class
 *
 * Validates WooCommerce category sync to Facebook product sets
 */
class CategorySyncValidator {

    private $category_id;
    private $category;
    private $integration;
    private $result;
    private $max_retries;

    /**
     * Field mappings between WooCommerce category and Facebook product set
     */
    private const FIELD_MAPPINGS = [
        'name' => 'name',
        'retailer_id' => 'retailer_id'
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
    public function __construct($category_id, $wait_seconds = 5, $max_retries = 6) {
        $this->category_id = (int)$category_id;
        $this->max_retries = (int)$max_retries;
        $this->result = [
            'success' => false,
            'category_id' => $this->category_id,
            'term_taxonomy_id' => null,
            'sync_status' => 'unknown',
            'retailer_id' => null,
            'facebook_product_set_id' => null,
            'mismatches' => [],
            'debug' => [],
            'error' => null
        ];

        // Wait for Facebook processing
        if ($wait_seconds > 0) {
            sleep($wait_seconds);
            $this->debug("Waited {$wait_seconds} seconds before validation");
        }

        $this->initializeIntegration();
        $this->validateDependencies();

        // Initialize category - if it fails, we still allow validation to continue
        // but it will fail gracefully in the validate() method
        if (!$this->initializeCategory()) {
            return;
        }
    }

    /**
     * Check if required functions are available
     */
    private function validateDependencies() {
        if (!function_exists('get_term')) {
            throw new Exception('WordPress term functions not available');
        }
        if (!function_exists('facebook_for_woocommerce')) {
            throw new Exception('Facebook plugin not loaded');
        }
        if (!$this->category_id) {
            throw new Exception('Category ID required');
        }
    }

    /**
     * Initialize category
     */
    private function initializeCategory() {
        $this->debug("Initializing category: {$this->category_id}");
        $this->category = get_term($this->category_id, 'product_cat');

        if (is_wp_error($this->category) || !$this->category) {
            $this->result['success'] = false;
            $this->result['error'] = "Category {$this->category_id} not found in WooCommerce";
            $this->result['message'] = "The category does not exist in WooCommerce. It may have been deleted.";
            $this->debug("Category {$this->category_id} not found in WooCommerce");
            return false;
        }

        $this->result['term_taxonomy_id'] = $this->category->term_taxonomy_id;
        $this->debug("Initialized category: {$this->category->name} (term_taxonomy_id: {$this->category->term_taxonomy_id})");
        return true;
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
     * Main validation method - validates sync between WooCommerce category and Facebook product set
     */
    public function validate() {
        try {
            // Step 1: Get WooCommerce category data
            $woo_data = $this->getCategoryData();
            $this->debug("Extracted WooCommerce category data");

            // Step 2: Fetch Facebook product set data
            $retailer_id = $this->getRetailerId($this->category);
            $this->result['retailer_id'] = $retailer_id;
            $fb_data = $this->fetchFacebookProductSetData($retailer_id);

            $this->result['raw_data'] = [
                'woo_data' => $woo_data,
                'facebook_data' => $fb_data
            ];
            // Step 3: Check sync status
            $this->checkSyncStatus($woo_data, $fb_data);

            // Step 4: Compare fields if synced
            if ($fb_data['found']) {
                $this->compareFields($woo_data, $fb_data);
            }

            // Set success based on sync status and no mismatches
            $this->result['success'] = (
                $this->result['sync_status'] === 'synced' &&
                count($this->result['mismatches']) === 0
            );

        } catch (Exception $e) {
            $this->result['error'] = $e->getMessage();
            $this->debug("Validation failed: " . $e->getMessage());
        }

        return $this->result;
    }

    /**
     * Extract WooCommerce category data
     */
    private function getCategoryData() {
        $external_url = get_term_link($this->category, 'product_cat');

        // Handle WP_Error from get_term_link
        if (is_wp_error($external_url)) {
            $external_url = '';
        }

        return [
            'name' => $this->category->name ?? '',
            'description' => $this->category->description ?? '',
            'external_url' => $external_url ?? '',
            'retailer_id' => $this->getRetailerId($this->category) ?? '',
            'term_taxonomy_id' => $this->category->term_taxonomy_id ?? ''
        ];
    }

    /**
     * Get retailer ID for category (uses term_taxonomy_id)
     */
    private function getRetailerId($category) {
        // Important: Categories use term_taxonomy_id as retailer_id (not term_id)
        return isset($category->term_taxonomy_id) ? (string)$category->term_taxonomy_id : $this->category_id;
    }

    /**
     * Fetch Facebook product set data via API with retry logic
     */
    private function fetchFacebookProductSetData($retailer_id) {
        $api = facebook_for_woocommerce()->get_api();
        $catalog_id = $this->integration->get_product_catalog_id();
        $retry_count = 0;

        do {
            try {
                $response = $api->read_product_set_item($catalog_id, $retailer_id);
                $product_set_id = $response->get_product_set_id();

                if ($product_set_id) {
                    $this->debug(
                        $retry_count === 0
                            ? "Successfully fetched product set for retailer_id: {$retailer_id}"
                            : "Successfully fetched product set on retry #" . ($retry_count + 1)
                    );

                    // Get full product set data from response
                    $response_data = $response->response_data["data"];
                    $product_set_data = is_array($response_data) && !empty($response_data) ?
                        array_values(array_filter($response_data, function($item) {
                            return isset($this->category->name) && $item['name'] === $this->category->name;
                        }))[0] : null;

                    if ($product_set_data) {
                        // Parse metadata if it's a JSON string
                        $metadata = [];
                        if (isset($product_set_data['metadata'])) {
                            $metadata = is_string($product_set_data['metadata'])
                                ? json_decode($product_set_data['metadata'], true)
                                : $product_set_data['metadata'];
                        }

                        return [
                            'id' => $product_set_id,
                            'name' => $product_set_data['name'] ?? '',
                            'retailer_id' => $product_set_data['retailer_id'] ?? '',
                            'found' => true
                        ];
                    }
                }

            } catch (Exception $e) {
                $this->debug("Facebook API error for retailer_id {$retailer_id}: " . $e->getMessage());
            }

            $retry_count++;
            if ($retry_count < $this->max_retries) {
                $backoff_seconds = pow(2, $retry_count);
                $this->debug("Retry attempt #{$retry_count} of {$this->max_retries} for retailer_id: {$retailer_id} (waiting {$backoff_seconds}s)");
                sleep($backoff_seconds);
            }

        } while ($retry_count < $this->max_retries);

        $this->debug("No product set found for retailer_id: {$retailer_id} after {$this->max_retries} retries");
        return ['found' => false];
    }

    /**
     * Check if category is synced as product set
     */
    private function checkSyncStatus($woo_data, $fb_data) {
        if ($fb_data['found']) {
            $this->result['sync_status'] = 'synced';
            $this->result['facebook_product_set_id'] = $fb_data['id'];
            $this->debug("Category is synced as product set: {$fb_data['id']}");
        } else {
            $this->result['sync_status'] = 'not_synced';
            $this->debug("Category is NOT synced to Facebook");
        }
    }

    /**
     * Compare fields between WooCommerce category and Facebook product set
     */
    private function compareFields($woo_data, $fb_data) {
        $mismatches = [];

        foreach (self::FIELD_MAPPINGS as $woo_field => $fb_field) {
            $woo_value = $woo_data[$woo_field] ?? '';
            $fb_value = $fb_data[$fb_field] ?? '';

            $normalized_woo = $this->normalizeValue($woo_value);
            $normalized_fb = $this->normalizeValue($fb_value);

            if ($normalized_woo !== $normalized_fb) {
                $this->debug("MISMATCH {$woo_field}: WooCommerce='{$woo_value}' vs Facebook='{$fb_value}'");

                $mismatches["{$this->category_id}_{$woo_field}"] = [
                    'field' => $woo_field,
                    'woocommerce_value' => $woo_value,
                    'facebook_value' => $fb_value
                ];
            }
        }

        $this->result['mismatches'] = $mismatches;
        $this->debug("Compared fields, found " . count($mismatches) . " mismatches");
    }

    /**
     * Normalize values for comparison
     */
    private function normalizeValue($value) {
        // Handle WP_Error objects
        if (is_wp_error($value)) {
            return '';
        }
        return trim(strtolower((string)$value));
    }

    /**
     * Get JSON result
     */
    public function getJsonResult() {
        return json_encode($this->result, JSON_PRETTY_PRINT);
    }
}

// Main execution when called directly
if (php_sapi_name() === 'cli') {
    try {
        // Check if --type=category flag is present
        $is_category = in_array('--type=category', $argv);

        if ($is_category) {
            // Category validation mode
            // Usage: php e2e-facebook-sync-validator.php --type=category <category_id> [wait_seconds] [max_retries]

            $category_id = isset($argv[2]) ? (int)$argv[2] : null;
            $wait_seconds = isset($argv[3]) ? (int)$argv[3] : 10;
            $max_retries = isset($argv[4]) ? (int)$argv[4] : 6;

            if (!$category_id) {
                echo json_encode(['success' => false, 'error' => 'Category ID required']);
                exit(1);
            }

            $validator = new CategorySyncValidator($category_id, $wait_seconds, $max_retries);

        } else {
            // Product validation mode (existing - unchanged)
            // Usage: php e2e-facebook-sync-validator.php <product_id> [wait_seconds] [max_retries]

            $product_id = isset($argv[1]) ? (int)$argv[1] : null;
            $wait_seconds = isset($argv[2]) ? (int)$argv[2] : 10;
            $max_retries = isset($argv[3]) ? (int)$argv[3] : 6;

            if (!$product_id) {
                echo json_encode(['success' => false, 'error' => 'Product ID required']);
                exit(1);
            }

            $validator = new FacebookSyncValidator($product_id, $wait_seconds, $max_retries);
        }

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
