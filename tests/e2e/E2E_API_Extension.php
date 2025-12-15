<?php
/**
 * E2E API Extension - Minimal extension for testing
 * 
 * Extends WooCommerce\Facebook\API to expose protected methods needed for E2E testing.
 */

namespace WooCommerce\Facebook\Tests\E2E;

use WooCommerce\Facebook\API;

class E2E_API_Extension extends API {
    
    /**
     * Query products from Facebook catalog
     */
    public function query_catalog_products($catalog_id, $limit = 1) {
        $request = new \WooCommerce\Facebook\API\Request("/{$catalog_id}/products", 'GET');
        $request->set_params(['limit' => $limit]);
        $response = $this->perform_request($request);
        return $response->get_data();
    }
}
