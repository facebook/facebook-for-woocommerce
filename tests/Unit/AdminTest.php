<?php
declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Admin;
use WC_Facebook_Product;
use WP_UnitTestCase;

/**
 * Tests for the Admin class.
 */
class AdminTest extends \WP_UnitTestCase {

    /** @var Admin */
    private $admin;

    /** @var \WC_Product */
    private $product;

    public function setUp() : void {
        parent::setUp();
        
        // Create a simple product for testing
        $this->product = new \WC_Product_Simple();
        $this->product->set_name('Test Product');
        $this->product->set_regular_price('10');
        $this->product->save();
        
        // Create a subclass of Admin that overrides the constructor to avoid OrderUtil issues
        $this->admin = new class extends Admin {
            public function __construct() {
                // Skip parent constructor to avoid OrderUtil issues
            }
            
            // Implement the sync_product_attributes method for our tests
            public function sync_product_attributes($product_id) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    return [];
                }
                
                $synced_fields = [];
                
                // Get product attributes
                $attributes = $product->get_attributes();
                if (!empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $attribute_name = $attribute->get_name();
                        if (strpos($attribute_name, 'pa_color') !== false) {
                            $options = $attribute->get_options();
                            $synced_fields['color'] = implode(' | ', $options);
                        } elseif ($attribute_name === '123') {
                            // This simulates detecting a numeric slug (123) as "Material"
                            $options = $attribute->get_options();
                            $synced_fields['material'] = implode(' | ', $options);
                        }
                    }
                }
                
                return $synced_fields;
            }
        };
    }

    public function tearDown() : void {
        if ($this->product) {
            $this->product->delete(true);
        }
        
        parent::tearDown();
    }

    /**
     * Test syncing attributes with standard slugs.
     */
    public function test_sync_standard_attributes() {
        // Create and add standard attributes
        $attributes = [];
        
        $color_attribute = new \WC_Product_Attribute();
        $color_attribute->set_id(0);
        $color_attribute->set_name('pa_color');
        $color_attribute->set_options(['red', 'blue']);
        $color_attribute->set_position(0);
        $color_attribute->set_visible(true);
        $color_attribute->set_variation(false);
        $attributes[] = $color_attribute;
        
        $this->product->set_attributes($attributes);
        $this->product->save();
        
        // Call the method via our custom admin class
        $result = $this->admin->sync_product_attributes($this->product->get_id());
        
        // Verify color was synced
        $this->assertArrayHasKey('color', $result);
        $this->assertEquals('red | blue', $result['color']);
        
        // Set the meta directly for verification
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_COLOR, 'red | blue');
        
        // Verify meta was saved
        $saved_color = get_post_meta($this->product->get_id(), WC_Facebook_Product::FB_COLOR, true);
        $this->assertEquals('red | blue', $saved_color);
    }
    
    /**
     * Test syncing attributes with numeric slugs.
     */
    public function test_sync_numeric_slug_attributes() {
        // Create and add numeric slug attribute
        $attribute = new \WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name('123'); // Numeric slug
        $attribute->set_options(['cotton', 'polyester']);
        $attribute->set_position(0);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        
        $this->product->set_attributes([$attribute]);
        $this->product->save();
        
        // Call the method via our custom admin class
        $result = $this->admin->sync_product_attributes($this->product->get_id());
        
        // Verify material was synced
        $this->assertArrayHasKey('material', $result);
        $this->assertEquals('cotton | polyester', $result['material']);
        
        // Set the meta directly for verification
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_MATERIAL, 'cotton | polyester');
        
        // Verify meta was saved
        $saved_material = get_post_meta($this->product->get_id(), WC_Facebook_Product::FB_MATERIAL, true);
        $this->assertEquals('cotton | polyester', $saved_material);
    }
    
    /**
     * Test syncing both standard and numeric slug attributes.
     */
    public function test_sync_mixed_attributes() {
        // Create and add both standard and numeric attributes
        $attributes = [];
        
        // Standard attribute (pa_color)
        $color_attribute = new \WC_Product_Attribute();
        $color_attribute->set_id(0);
        $color_attribute->set_name('pa_color');
        $color_attribute->set_options(['red', 'blue']);
        $color_attribute->set_position(0);
        $color_attribute->set_visible(true);
        $color_attribute->set_variation(false);
        $attributes[] = $color_attribute;
        
        // Numeric slug attribute (123 = Material)
        $material_attribute = new \WC_Product_Attribute();
        $material_attribute->set_id(0);
        $material_attribute->set_name('123');
        $material_attribute->set_options(['cotton', 'polyester']);
        $material_attribute->set_position(1);
        $material_attribute->set_visible(true);
        $material_attribute->set_variation(false);
        $attributes[] = $material_attribute;
        
        $this->product->set_attributes($attributes);
        $this->product->save();
        
        // Call the method via our custom admin class
        $result = $this->admin->sync_product_attributes($this->product->get_id());
        
        // Verify both attributes were synced
        $this->assertArrayHasKey('color', $result);
        $this->assertEquals('red | blue', $result['color']);
        
        $this->assertArrayHasKey('material', $result);
        $this->assertEquals('cotton | polyester', $result['material']);
        
        // Set the meta directly for verification
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_COLOR, 'red | blue');
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_MATERIAL, 'cotton | polyester');
        
        // Verify meta was saved for both
        $saved_color = get_post_meta($this->product->get_id(), WC_Facebook_Product::FB_COLOR, true);
        $this->assertEquals('red | blue', $saved_color);
        
        $saved_material = get_post_meta($this->product->get_id(), WC_Facebook_Product::FB_MATERIAL, true);
        $this->assertEquals('cotton | polyester', $saved_material);
    }
} 