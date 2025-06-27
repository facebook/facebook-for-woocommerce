<?php

class Test_Admin_Sync_Indicator extends WP_Ajax_UnitTestCase {
    private $admin;
    private $product;

    public function setUp(): void {
        parent::setUp();
        
        // Set up admin environment
        if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }
        
        $this->admin = new \WooCommerce\Facebook\Admin();
        
        // Create a test product
        $this->product = new \WC_Product_Simple();
        $this->product->set_name('Test Product');
        $this->product->set_regular_price('10');
        $this->product->save();
        
        // Set up the request
        $_POST['action'] = 'sync_facebook_attributes';
        $_POST['product_id'] = $this->product->get_id();
        $_POST['nonce'] = wp_create_nonce('sync_facebook_attributes');
        
        // Add the AJAX action
        add_action('wp_ajax_sync_facebook_attributes', [$this->admin, 'ajax_sync_facebook_attributes']);
    }

    /**
     * Test attribute syncing functionality
     */
    public function test_sync_product_attributes() {
        // Test basic attribute sync
        $attributes = [
            $this->create_product_attribute('color', 'blue'),
            $this->create_product_attribute('size', 'large'),
        ];
        
        $this->product->set_attributes($attributes);
        $this->product->save();

        $synced_fields = \WooCommerce\Facebook\ProductAttributeMapper::get_and_save_mapped_attributes($this->product);

        $this->assertArrayHasKey('color', $synced_fields);
        $this->assertEquals('blue', $synced_fields['color']);
        $this->assertEquals('blue', get_post_meta($this->product->get_id(), \WC_Facebook_Product::FB_COLOR, true));
        
        $this->assertArrayHasKey('size', $synced_fields);
        $this->assertEquals('large', $synced_fields['size']);
        $this->assertEquals('large', get_post_meta($this->product->get_id(), \WC_Facebook_Product::FB_SIZE, true));
    }

    /**
     * Test British spelling handling
     */
    public function test_colour_spelling_variant() {
        $attributes = [
            $this->create_product_attribute('colour', 'red'),
        ];
        
        $this->product->set_attributes($attributes);
        $this->product->save();

        $synced_fields = \WooCommerce\Facebook\ProductAttributeMapper::get_and_save_mapped_attributes($this->product);

        $this->assertArrayHasKey('color', $synced_fields);
        $this->assertEquals('red', $synced_fields['color']);
    }

    /**
     * Test attribute removal
     */
    public function test_attribute_removal() {
        // First add and sync an attribute
        $attributes = [
            $this->create_product_attribute('material', 'cotton'),
        ];
        
        $this->product->set_attributes($attributes);
        $this->product->save();

        // Initial sync - verify material is present
        $initial_synced_fields = \WooCommerce\Facebook\ProductAttributeMapper::get_and_save_mapped_attributes($this->product);
        $this->assertArrayHasKey('material', $initial_synced_fields);
        $this->assertEquals('cotton', $initial_synced_fields['material']);
        
        // Then remove the attribute
        $this->product->set_attributes([]);
        $this->product->save();

        // Sync again after removal
        $synced_fields = \WooCommerce\Facebook\ProductAttributeMapper::get_and_save_mapped_attributes($this->product);

        // After removal, the field should not be present in synced fields array
        $this->assertArrayNotHasKey('material', $synced_fields);
    }

    /**
     * Test multiple attribute values
     */
    public function test_multiple_attribute_values() {
        $attribute = $this->create_product_attribute('size', ['small', 'medium', 'large']);
        
        $this->product->set_attributes([$attribute]);
        $this->product->save();

        $synced_fields = \WooCommerce\Facebook\ProductAttributeMapper::get_and_save_mapped_attributes($this->product);

        $this->assertArrayHasKey('size', $synced_fields);
        // The expected format may vary based on implementation, but at minimum the value should contain all options
        $this->assertStringContainsString('small', $synced_fields['size']);
        $this->assertStringContainsString('medium', $synced_fields['size']);
        $this->assertStringContainsString('large', $synced_fields['size']);
    }

    /**
     * Test AJAX endpoint
     */
    public function test_ajax_sync_facebook_attributes() {
        // Set up test attributes
        $attribute = new \WC_Product_Attribute();
        $attribute->set_name('color');
        $attribute->set_options(['Blue']);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        
        $this->product->set_attributes([$attribute]);
        $this->product->save();

        // Set up the AJAX request with proper nonce
        $_REQUEST['_ajax_nonce'] = wp_create_nonce('sync_facebook_attributes');
        $_REQUEST['action'] = 'sync_facebook_attributes';
        $_REQUEST['product_id'] = $this->product->get_id();

        // Make the AJAX call
        try {
            $this->_handleAjax('sync_facebook_attributes');
        } catch (\WPAjaxDieContinueException $e) {
            // We expect this exception for successful AJAX responses
            $response = json_decode($this->_last_response);
            
            $this->assertTrue($response->success);
            $this->assertIsObject($response->data);
            $this->assertEquals('Blue', $response->data->color);
            return;
        } catch (\WPAjaxDieStopException $e) {
            $this->fail('Nonce verification failed: ' . $e->getMessage());
        }

        $this->fail('WPAjaxDieContinueException not thrown');
    }

    /**
     * Helper function to create test product
     */
    private function create_test_product() {
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_regular_price('10.00');
        $product->save();
        return $product;
    }

    /**
     * Helper function to create product attribute
     */
    private function create_product_attribute($name, $value) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_name($name);
        $attribute->set_options(is_array($value) ? $value : [$value]);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        return $attribute;
    }

    public function tearDown(): void {
        parent::tearDown();
        // Clean up
        if ($this->product) {
            $this->product->delete(true);
        }
    }
} 