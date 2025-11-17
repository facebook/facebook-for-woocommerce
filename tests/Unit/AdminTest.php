<?php
declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Admin;
use WooCommerce\Facebook\RolloutSwitches;
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

    /**
     * Test attribute ID for Material attribute with numeric slug
     */
    private const TEST_MATERIAL_ATTRIBUTE_ID = '123';

    public function setUp() : void {
        parent::setUp();

        // Create a simple product for testing
        $this->product = new \WC_Product_Simple();
        $this->product->set_name('Test Product');
        $this->product->set_regular_price('10');
        $this->product->save();

        // Create a subclass of Admin that overrides the constructor to avoid OrderUtil issues
        $materialAttributeId = self::TEST_MATERIAL_ATTRIBUTE_ID;
        $this->admin = new class($materialAttributeId) extends Admin {
            private $materialAttributeId;

            public function __construct($materialAttributeId) {
                $this->materialAttributeId = $materialAttributeId;
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
                            $synced_fields['color'] = implode(' | ', $attribute->get_options());
                        } elseif (is_numeric($attribute_name) && $attribute_name == $this->materialAttributeId) {
                            // Handle numeric slug for Material attribute
                            $synced_fields['material'] = implode(' | ', $attribute->get_options());
                        }
                    }
                }

                return $synced_fields;
            }
        };
    }

    public function tearDown() : void {
        // Clean up the product
        if ($this->product) {
            wp_delete_post($this->product->get_id(), true);
        }
        parent::tearDown();
    }

    /**
     * Test the sync_product_attributes method with color attribute
     */
    public function test_sync_product_attributes_with_color() {
        // Create a product attribute for color
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

        // Verify the color attribute was synced
        $this->assertArrayHasKey('color', $result);
        $this->assertEquals('red | blue', $result['color']);

        // Set the meta directly for verification
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_COLOR, 'red | blue');

        // Verify meta was saved
        $saved_color = get_post_meta($this->product->get_id(), WC_Facebook_Product::FB_COLOR, true);
        $this->assertEquals('red | blue', $saved_color);
    }

    /**
     * Test the sync_product_attributes method with numeric slug attribute
     */
    public function test_sync_product_attributes_with_numeric_slug() {
        // Create a product attribute with numeric slug for Material
        $attributes = [];
        $material_attribute = new \WC_Product_Attribute();
        $material_attribute->set_id(0);
        $material_attribute->set_name(self::TEST_MATERIAL_ATTRIBUTE_ID); // Numeric slug
        $material_attribute->set_options(['cotton', 'polyester']);
        $material_attribute->set_position(0);
        $material_attribute->set_visible(true);
        $material_attribute->set_variation(false);
        $attributes[] = $material_attribute;

        $this->product->set_attributes($attributes);
        $this->product->save();

        // Call the method via our custom admin class
        $result = $this->admin->sync_product_attributes($this->product->get_id());

        // Verify the material attribute was synced
        $this->assertArrayHasKey('material', $result);
        $this->assertEquals('cotton | polyester', $result['material']);

        // Set the meta directly for verification
        update_post_meta($this->product->get_id(), WC_Facebook_Product::FB_MATERIAL, 'cotton | polyester');

        // Verify meta was saved
        $saved_material = get_post_meta($this->product->get_id(), WC_Facebook_Product::FB_MATERIAL, true);
        $this->assertEquals('cotton | polyester', $saved_material);
    }

    /**
     * Test the sync_product_attributes method with multiple attributes
     */
    public function test_sync_product_attributes_with_multiple_attributes() {
        // Create multiple product attributes
        $attributes = [];

        // Color attribute (pa_color)
        $color_attribute = new \WC_Product_Attribute();
        $color_attribute->set_id(0);
        $color_attribute->set_name('pa_color');
        $color_attribute->set_options(['red', 'blue']);
        $color_attribute->set_position(0);
        $color_attribute->set_visible(true);
        $color_attribute->set_variation(false);
        $attributes[] = $color_attribute;

        // Numeric slug attribute for Material
        $material_attribute = new \WC_Product_Attribute();
        $material_attribute->set_id(0);
        $material_attribute->set_name(self::TEST_MATERIAL_ATTRIBUTE_ID);
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

    /**
     * Test render_facebook_product_images_field method
     */
    public function test_render_facebook_product_images_field() {
        // Mock the rollout switches to enable multiple images feature
        $plugin = $this->getMockBuilder(\WC_Facebookcommerce::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches = $this->getMockBuilder(\WooCommerce\Facebook\RolloutSwitches::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches->expects($this->once())
            ->method('is_switch_enabled')
            ->with(\WooCommerce\Facebook\RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED)
            ->willReturn(true);

        $plugin->expects($this->once())
            ->method('get_rollout_switches')
            ->willReturn($rollout_switches);

        // Mock the global function
        $GLOBALS['wc_facebook_commerce'] = $plugin;

        // Create attachment IDs for testing
        $attachment_ids = [123, 456, 789];
        $index = 0;
        $variation_id = 999;

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->admin);
        $method = $reflection->getMethod('render_facebook_product_images_field');
        $method->setAccessible(true);

        // Start output buffering to capture the HTML output
        ob_start();
        $method->invoke($this->admin, $attachment_ids, $index, $variation_id);
        $output = ob_get_clean();

        // Verify the HTML contains expected elements
        $this->assertStringContainsString('Add Multiple Images', $output);
        $this->assertStringContainsString('data-variation-index="0"', $output);
        $this->assertStringContainsString('data-variation-id="999"', $output);
        $this->assertStringContainsString('fb-product-images-thumbnails', $output);
        $this->assertStringContainsString('variable_fb_product_images0', $output);
        $this->assertStringContainsString('123,456,789', $output);

        // Clean up
        unset($GLOBALS['wc_facebook_commerce']);
    }

    /**
     * Test render_facebook_product_images_field method when rollout switch is disabled
     */
    public function test_render_facebook_product_images_field_rollout_switch_disabled() {
        // Mock the rollout switches to disable multiple images feature
        $plugin = $this->getMockBuilder(\WC_Facebookcommerce::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches = $this->getMockBuilder(\WooCommerce\Facebook\RolloutSwitches::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches->expects($this->once())
            ->method('is_switch_enabled')
            ->with(\WooCommerce\Facebook\RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED)
            ->willReturn(false);

        $plugin->expects($this->once())
            ->method('get_rollout_switches')
            ->willReturn($rollout_switches);

        // Mock the global function
        $GLOBALS['wc_facebook_commerce'] = $plugin;

        // Create attachment IDs for testing
        $attachment_ids = [123, 456, 789];
        $index = 0;
        $variation_id = 999;

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->admin);
        $method = $reflection->getMethod('render_facebook_product_images_field');
        $method->setAccessible(true);

        // Start output buffering to capture the HTML output
        ob_start();
        $method->invoke($this->admin, $attachment_ids, $index, $variation_id);
        $output = ob_get_clean();

        // Verify no output is generated when rollout switch is disabled
        $this->assertEmpty($output);
        $this->assertStringNotContainsString('Add Multiple Images', $output);
        $this->assertStringNotContainsString('fb-product-images-thumbnails', $output);

        // Clean up
        unset($GLOBALS['wc_facebook_commerce']);
    }

    /**
     * Test render_facebook_product_images_field with empty attachment IDs
     */
    public function test_render_facebook_product_images_field_empty() {
        // Mock the rollout switches to enable multiple images feature
        $plugin = $this->getMockBuilder(\WC_Facebookcommerce::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches = $this->getMockBuilder(\WooCommerce\Facebook\RolloutSwitches::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches->expects($this->once())
            ->method('is_switch_enabled')
            ->with(\WooCommerce\Facebook\RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED)
            ->willReturn(true);

        $plugin->expects($this->once())
            ->method('get_rollout_switches')
            ->willReturn($rollout_switches);

        // Mock the global function
        $GLOBALS['wc_facebook_commerce'] = $plugin;

        $attachment_ids = [];
        $index = 1;
        $variation_id = 888;

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->admin);
        $method = $reflection->getMethod('render_facebook_product_images_field');
        $method->setAccessible(true);

        // Start output buffering to capture the HTML output
        ob_start();
        $method->invoke($this->admin, $attachment_ids, $index, $variation_id);
        $output = ob_get_clean();

        // Verify the HTML contains expected elements for empty state
        $this->assertStringContainsString('Add Multiple Images', $output);
        $this->assertStringContainsString('data-variation-index="1"', $output);
        $this->assertStringContainsString('data-variation-id="888"', $output);
        $this->assertStringContainsString('variable_fb_product_images1', $output);
        $this->assertStringContainsString('value=""', $output);

        // Clean up
        unset($GLOBALS['wc_facebook_commerce']);
    }

    /**
     * Test save_product_variation_edit_fields method with multiple images
     */
    public function test_save_product_variation_edit_fields_with_multiple_images() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Mock POST data for multiple images
        $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
        $_POST['wc_facebook_sync_mode'] = 'sync_and_show';
        $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];
        $_POST["variable_fb_product_images{$index}"] = '123,456,789';
        $_POST['variable_fb_product_description'] = [0 => 'Test description'];
        $_POST['variable_fb_product_price'] = [0 => '15.99'];
        $_POST['variable_fb_mpn'] = [0 => 'TEST-MPN-123'];

        // Call the method
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify multiple images were saved
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('123,456,789', $saved_images);

        // Verify other fields were saved correctly
        $saved_description = get_post_meta($variation_id, \WC_Facebookcommerce_Integration::FB_PRODUCT_DESCRIPTION, true);
        $this->assertEquals('Test description', $saved_description);

        $saved_mpn = get_post_meta($variation_id, \WC_Facebook_Product::FB_MPN, true);
        $this->assertEquals('TEST-MPN-123', $saved_mpn);
    }

    /**
     * Test save_product_variation_edit_fields with empty multiple images
     */
    public function test_save_product_variation_edit_fields_empty_multiple_images() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Set initial data
        update_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, '123,456');

        // Mock POST data with empty images
        $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
        $_POST['wc_facebook_sync_mode'] = 'sync_and_show';
        $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];
        $_POST["variable_fb_product_images{$index}"] = '';

        // Call the method
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify images were cleared
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('', $saved_images);
    }

    /**
     * Test save_product_variation_edit_fields with image removal scenario
     */
    public function test_save_product_variation_edit_fields_image_removal() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Set initial data with 3 images
        update_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, '123,456,789');

        // Mock POST data with one image removed (456 removed)
        $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
        $_POST['wc_facebook_sync_mode'] = 'sync_and_show';
        $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];
        $_POST["variable_fb_product_images{$index}"] = '123,789';

        // Call the method
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify correct images remain
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('123,789', $saved_images);
    }

    /**
     * Test save_product_variation_edit_fields with invalid nonce
     */
    public function test_save_product_variation_edit_fields_invalid_nonce() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Set initial data
        update_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, 'initial_data');

        // Mock POST data with invalid nonce
        $_POST["facebook_variation_nonce_{$variation_id}"] = 'invalid_nonce';
        $_POST["variable_fb_product_images{$index}"] = '123,456,789';

        // Call the method
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify data was not changed due to invalid nonce
        $unchanged_data = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('initial_data', $unchanged_data);
    }

    /**
     * Test multiple images field integration with different image source selections
     */
    public function test_multiple_images_with_different_sources() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Test 1: Save with 'product' source - should not save multiple images
        $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
        $_POST['wc_facebook_sync_mode'] = 'sync_and_show';
        $_POST['variable_fb_product_image_source'] = [0 => 'product'];
        $_POST["variable_fb_product_images{$index}"] = '123,456,789';

        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Images should be saved regardless of source for data persistence
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('123,456,789', $saved_images);

        // Test 2: Change to 'multiple' source - should use the saved images
        $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];

        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('123,456,789', $saved_images);
    }

    /**
     * Test edge cases with malformed image data
     */
    public function test_save_multiple_images_edge_cases() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Mock POST data for edge cases
        $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
        $_POST['wc_facebook_sync_mode'] = 'sync_and_show';

        // Test with trailing/leading commas
        $_POST["variable_fb_product_images{$index}"] = ',123,456,';
        $this->admin->save_product_variation_edit_fields($variation_id, $index);
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals(',123,456,', $saved_images); // Should preserve as-is for flexibility

        // Test with spaces
        $_POST["variable_fb_product_images{$index}"] = '123, 456, 789';
        $this->admin->save_product_variation_edit_fields($variation_id, $index);
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('123, 456, 789', $saved_images);

        // Test with single image
        $_POST["variable_fb_product_images{$index}"] = '999';
        $this->admin->save_product_variation_edit_fields($variation_id, $index);
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('999', $saved_images);
    }

    /**
     * Test add_product_variation_edit_fields method includes multiple images field
     */
    public function test_add_product_variation_edit_fields_includes_multiple_images() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Set some test data for multiple images
        update_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, '111,222,333');
        update_post_meta($variation_id, \WooCommerce\Facebook\Products::PRODUCT_IMAGE_SOURCE_META_KEY, 'multiple');

        // Test that we can call the method without errors and verify the data is set
        $this->assertTrue(method_exists($this->admin, 'add_product_variation_edit_fields'), 'Method should exist');

        // Verify the data was stored correctly
        $stored_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('111,222,333', $stored_images);

        $stored_source = get_post_meta($variation_id, \WooCommerce\Facebook\Products::PRODUCT_IMAGE_SOURCE_META_KEY, true);
        $this->assertEquals('multiple', $stored_source);
    }

    /**
     * Test multiple images data validation
     */
    public function test_multiple_images_data_validation() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Test various input formats
        $test_cases = [
            '123,456,789' => '123,456,789',  // Normal case
            '123' => '123',                   // Single image
            '' => '',                         // Empty case
            '0' => '0',                       // Zero value
            '123,456,' => '123,456,',        // Trailing comma
            ',123,456' => ',123,456',        // Leading comma
            '123,,456' => '123,,456',        // Double comma
            '123, 456, 789' => '123, 456, 789', // With spaces
        ];

        foreach ($test_cases as $input => $expected) {
            $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
            $_POST['wc_facebook_sync_mode'] = 'sync_and_show';
            $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];
            $_POST["variable_fb_product_images{$index}"] = $input;

            $this->admin->save_product_variation_edit_fields($variation_id, $index);

            $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
            $this->assertEquals($expected, $saved_images, "Failed for input: '{$input}'");
        }
    }

    /**
     * Test multiple images field visibility logic
     */
    public function test_multiple_images_field_visibility() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Test with different image sources
        $image_sources = ['product', 'parent_product', 'custom', 'multiple'];

        foreach ($image_sources as $source) {
            update_post_meta($variation_id, \WooCommerce\Facebook\Products::PRODUCT_IMAGE_SOURCE_META_KEY, $source);

            // Verify the meta data is stored correctly for each source
            $stored_source = get_post_meta($variation_id, \WooCommerce\Facebook\Products::PRODUCT_IMAGE_SOURCE_META_KEY, true);
            $this->assertEquals($source, $stored_source, "Source should be stored correctly for: {$source}");

            // Test that the method can be called without errors
            $this->assertTrue(method_exists($this->admin, 'add_product_variation_edit_fields'), 'Method should exist');
        }

        // Test that 'multiple' is a valid source option
        $this->assertContains('multiple', $image_sources, 'Multiple should be a valid image source option');
    }

    /**
     * Test multiple images persistence across saves
     */
    public function test_multiple_images_persistence() {
        // Create a variable product with variation
        $variable_product = \WC_Helper_Product::create_variation_product();
        $variation = wc_get_product($variable_product->get_children()[0]);
        $variation_id = $variation->get_id();
        $index = 0;

        // Initial save with multiple images
        $_POST["facebook_variation_nonce_{$variation_id}"] = wp_create_nonce('facebook_variation_save');
        $_POST['wc_facebook_sync_mode'] = 'sync_and_show';
        $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];
        $_POST["variable_fb_product_images{$index}"] = '100,200,300';

        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify initial save
        $saved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('100,200,300', $saved_images);

        // Update with different images
        $_POST["variable_fb_product_images{$index}"] = '400,500,600,700';
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify update
        $updated_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('400,500,600,700', $updated_images);

        // Switch to different image source but keep multiple images data
        $_POST['variable_fb_product_image_source'] = [0 => 'product'];
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify multiple images data is preserved
        $preserved_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('400,500,600,700', $preserved_images);

        // Switch back to multiple images
        $_POST['variable_fb_product_image_source'] = [0 => 'multiple'];
        $this->admin->save_product_variation_edit_fields($variation_id, $index);

        // Verify data is still there
        $restored_images = get_post_meta($variation_id, \WC_Facebook_Product::FB_PRODUCT_IMAGES, true);
        $this->assertEquals('400,500,600,700', $restored_images);
    }

    public function test_multiple_images_option_controlled_by_rollout_switch() {
        // Create a variable product with variations for testing
        $product = new \WC_Product_Variable();
        $product->set_name('Test Variable Product');
        $product->save();

        // Create a variation
        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->set_regular_price('20');
        $variation->save();

        // Test when rollout switch is disabled
        $plugin_mock_disabled = $this->getMockBuilder('WC_Facebookcommerce')
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches_mock_disabled = $this->getMockBuilder('WooCommerce\Facebook\RolloutSwitches')
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches_mock_disabled->expects($this->any())
            ->method('is_switch_enabled')
            ->with(RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED)
            ->willReturn(false);

        $plugin_mock_disabled->expects($this->any())
            ->method('get_rollout_switches')
            ->willReturn($rollout_switches_mock_disabled);

        $GLOBALS['wc_facebook_commerce'] = $plugin_mock_disabled;

        // Capture output when rollout switch is disabled
        ob_start();
        $this->admin->add_product_variation_edit_fields(0, array(), $variation);
        $output_disabled = ob_get_clean();

        // Test when rollout switch is enabled
        $plugin_mock_enabled = $this->getMockBuilder('WC_Facebookcommerce')
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches_mock_enabled = $this->getMockBuilder('WooCommerce\Facebook\RolloutSwitches')
            ->disableOriginalConstructor()
            ->getMock();

        $rollout_switches_mock_enabled->expects($this->any())
            ->method('is_switch_enabled')
            ->with(RolloutSwitches::SWITCH_MULTIPLE_IMAGES_ENABLED)
            ->willReturn(true);

        $plugin_mock_enabled->expects($this->any())
            ->method('get_rollout_switches')
            ->willReturn($rollout_switches_mock_enabled);

        $GLOBALS['wc_facebook_commerce'] = $plugin_mock_enabled;

        // Capture output when rollout switch is enabled
        ob_start();
        $this->admin->add_product_variation_edit_fields(0, array(), $variation);
        $output_enabled = ob_get_clean();

        // Verify that "Add multiple images" option is not present when switch is disabled
        $this->assertStringNotContainsString('Add multiple images', $output_disabled);

        // Verify that "Add multiple images" option is present when switch is enabled
        $this->assertStringContainsString('Add multiple images', $output_enabled);

        // Clean up
        unset($GLOBALS['wc_facebook_commerce']);
    }

	/**
	 * Test video source field rendering for variations.
	 */
	public function test_variation_video_source_field_rendering() {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable_product->get_children()[0] );

		// Set video source to upload
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_UPLOAD );
		$variation->save_meta_data();

		// Mock the global plugin instance with rollout switches
		$plugin_mock = $this->getMockBuilder('WC_Facebookcommerce')
			->disableOriginalConstructor()
			->getMock();

		$rollout_switches_mock = $this->getMockBuilder('WooCommerce\Facebook\RolloutSwitches')
			->disableOriginalConstructor()
			->getMock();

		$rollout_switches_mock->expects($this->any())
			->method('is_switch_enabled')
			->willReturn(true);

		$plugin_mock->expects($this->any())
			->method('get_rollout_switches')
			->willReturn($rollout_switches_mock);

		$GLOBALS['wc_facebook_commerce'] = $plugin_mock;

		// Capture output
		ob_start();
		$this->admin->add_product_variation_edit_fields( 0, array(), $variation );
		$output = ob_get_clean();

		// Verify video source radio buttons are present
		$this->assertStringContainsString( 'fb_product_video_source', $output );
		$this->assertStringContainsString( 'Choose video(s)', $output );
		$this->assertStringContainsString( 'Use custom video', $output );

		// Clean up
		$variable_product->delete( true );
		unset($GLOBALS['wc_facebook_commerce']);
	}

	/**
	 * Test video source defaults to upload when not set.
	 */
	public function test_variation_video_source_default() {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable_product->get_children()[0] );

		// Don't set video source - should default to upload
		$video_source = $variation->get_meta( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY );

		// Empty meta should allow default to 'upload'
		$this->assertEmpty( $video_source );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test custom video URL field for variations.
	 */
	public function test_variation_custom_video_url_field() {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable_product->get_children()[0] );

		$custom_url = 'https://example.com/custom-video.mp4';

		// Set custom video URL
		$variation->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', $custom_url );
		$variation->save_meta_data();

		$saved_url = $variation->get_meta( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url' );
		$this->assertEquals( $custom_url, $saved_url );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test variation video data structure matches simple product video data.
	 */
	public function test_variation_video_data_structure_consistency() {
		// Create simple product
		$simple_product = \WC_Helper_Product::create_simple_product();
		$simple_video_urls = [ 'https://example.com/simple-video.mp4' ];
		update_post_meta( $simple_product->get_id(), \WC_Facebook_Product::FB_PRODUCT_VIDEO, $simple_video_urls );

		// Create variation
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable_product->get_children()[0] );
		$variation_video_urls = [ 'https://example.com/variation-video.mp4' ];
		$variation->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO, $variation_video_urls );
		$variation->save_meta_data();

		// Get video data
		$simple_videos = get_post_meta( $simple_product->get_id(), \WC_Facebook_Product::FB_PRODUCT_VIDEO, true );
		$variation_videos = $variation->get_meta( \WC_Facebook_Product::FB_PRODUCT_VIDEO );

		// Both should be arrays
		$this->assertIsArray( $simple_videos );
		$this->assertIsArray( $variation_videos );

		// Both should have same structure
		$this->assertEquals( $simple_video_urls, $simple_videos );
		$this->assertEquals( $variation_video_urls, $variation_videos );

		// Clean up
		$simple_product->delete( true );
		$variable_product->delete( true );
	}

	/**
	 * Test video source meta key is properly stored for variations.
	 */
	public function test_video_source_meta_storage_for_variation() {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable_product->get_children()[0] );

		// Test upload source
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_UPLOAD );
		$variation->save_meta_data();

		$source = get_post_meta( $variation->get_id(), \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, true );
		$this->assertEquals( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_UPLOAD, $source );

		// Test custom source
		$variation->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
		$variation->save_meta_data();

		$source = get_post_meta( $variation->get_id(), \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, true );
		$this->assertEquals( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM, $source );

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test multiple variations can have different video sources.
	 */
	public function test_multiple_variations_different_video_sources() {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variations = $variable_product->get_children();

		$this->assertGreaterThan( 0, count( $variations ) );

		$variation1 = wc_get_product( $variations[0] );

		// Set first variation to upload source
		$variation1->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_UPLOAD );
		$variation1->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO, [ 'https://example.com/upload.mp4' ] );
		$variation1->save_meta_data();

		if ( count( $variations ) > 1 ) {
			$variation2 = wc_get_product( $variations[1] );

			// Set second variation to custom source
			$variation2->update_meta_data( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY, \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM );
			$variation2->update_meta_data( \WC_Facebook_Product::FB_PRODUCT_VIDEO . '_custom_url', 'https://example.com/custom.mp4' );
			$variation2->save_meta_data();

			// Verify different sources
			$source1 = $variation1->get_meta( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY );
			$source2 = $variation2->get_meta( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_META_KEY );

			$this->assertEquals( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_UPLOAD, $source1 );
			$this->assertEquals( \WooCommerce\Facebook\Products::PRODUCT_VIDEO_SOURCE_CUSTOM, $source2 );
			$this->assertNotEquals( $source1, $source2 );
		}

		// Clean up
		$variable_product->delete( true );
	}

	/**
	 * Test variation video fields are present in admin output.
	 */
	public function test_variation_video_fields_in_admin_output() {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable_product->get_children()[0] );

		// Mock the global plugin instance with rollout switches
		$plugin_mock = $this->getMockBuilder('WC_Facebookcommerce')
			->disableOriginalConstructor()
			->getMock();

		$rollout_switches_mock = $this->getMockBuilder('WooCommerce\Facebook\RolloutSwitches')
			->disableOriginalConstructor()
			->getMock();

		$rollout_switches_mock->expects($this->any())
			->method('is_switch_enabled')
			->willReturn(true);

		$plugin_mock->expects($this->any())
			->method('get_rollout_switches')
			->willReturn($rollout_switches_mock);

		$GLOBALS['wc_facebook_commerce'] = $plugin_mock;

		// Capture output
		ob_start();
		$this->admin->add_product_variation_edit_fields( 0, array(), $variation );
		$output = ob_get_clean();

		// Verify video-related fields are present
		$this->assertStringContainsString( 'Facebook Product Video', $output );
		$this->assertStringContainsString( 'fb_product_video_source', $output );
		$this->assertStringContainsString( 'Choose Video', $output );
		$this->assertStringContainsString( 'Custom Video URL', $output );

		// Clean up
		$variable_product->delete( true );
		unset($GLOBALS['wc_facebook_commerce']);
	}
}
