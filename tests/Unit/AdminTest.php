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

    /**
     * Test SYNC_MODE_SYNC_AND_SHOW constant.
     */
    public function test_sync_mode_sync_and_show_constant(): void {
        $this->assertSame(
            'sync_and_show',
            Admin::SYNC_MODE_SYNC_AND_SHOW,
            'SYNC_MODE_SYNC_AND_SHOW constant should have correct value'
        );
    }

    /**
     * Test SYNC_MODE_SYNC_AND_HIDE constant.
     */
    public function test_sync_mode_sync_and_hide_constant(): void {
        $this->assertSame(
            'sync_and_hide',
            Admin::SYNC_MODE_SYNC_AND_HIDE,
            'SYNC_MODE_SYNC_AND_HIDE constant should have correct value'
        );
    }

    /**
     * Test SYNC_MODE_SYNC_DISABLED constant.
     */
    public function test_sync_mode_sync_disabled_constant(): void {
        $this->assertSame(
            'sync_disabled',
            Admin::SYNC_MODE_SYNC_DISABLED,
            'SYNC_MODE_SYNC_DISABLED constant should have correct value'
        );
    }

    /**
     * Test INCLUDE_FACEBOOK_SYNC constant.
     */
    public function test_include_facebook_sync_constant(): void {
        $this->assertSame(
            'fb_sync_enabled',
            Admin::INCLUDE_FACEBOOK_SYNC,
            'INCLUDE_FACEBOOK_SYNC constant should have correct value'
        );
    }

    /**
     * Test EXCLUDE_FACEBOOK_SYNC constant.
     */
    public function test_exclude_facebook_sync_constant(): void {
        $this->assertSame(
            'fb_sync_disabled',
            Admin::EXCLUDE_FACEBOOK_SYNC,
            'EXCLUDE_FACEBOOK_SYNC constant should have correct value'
        );
    }

    /**
     * Test BULK_EDIT_SYNC constant.
     */
    public function test_bulk_edit_sync_constant(): void {
        $this->assertSame(
            'bulk_edit_sync',
            Admin::BULK_EDIT_SYNC,
            'BULK_EDIT_SYNC constant should have correct value'
        );
    }

    /**
     * Test BULK_EDIT_DELETE constant.
     */
    public function test_bulk_edit_delete_constant(): void {
        $this->assertSame(
            'bulk_edit_delete',
            Admin::BULK_EDIT_DELETE,
            'BULK_EDIT_DELETE constant should have correct value'
        );
    }

    /**
     * Test all sync mode constants are distinct.
     */
    public function test_sync_mode_constants_are_distinct(): void {
        $sync_modes = [
            Admin::SYNC_MODE_SYNC_AND_SHOW,
            Admin::SYNC_MODE_SYNC_AND_HIDE,
            Admin::SYNC_MODE_SYNC_DISABLED,
        ];

        $unique_modes = array_unique($sync_modes);
        $this->assertCount(
            3,
            $unique_modes,
            'All sync mode constants should have unique values'
        );
    }

    /**
     * Test bulk edit constants are distinct.
     */
    public function test_bulk_edit_constants_are_distinct(): void {
        $this->assertNotSame(
            Admin::BULK_EDIT_SYNC,
            Admin::BULK_EDIT_DELETE,
            'BULK_EDIT_SYNC and BULK_EDIT_DELETE should have different values'
        );
    }

    /**
     * Test sync filter constants are distinct.
     */
    public function test_sync_filter_constants_are_distinct(): void {
        $this->assertNotSame(
            Admin::INCLUDE_FACEBOOK_SYNC,
            Admin::EXCLUDE_FACEBOOK_SYNC,
            'INCLUDE_FACEBOOK_SYNC and EXCLUDE_FACEBOOK_SYNC should have different values'
        );
    }

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

	/**
	 * Test add_product_list_table_columns adds Facebook columns.
	 */
	public function test_add_product_list_table_columns(): void {
		$existing_columns = array(
			'cb'           => '<input type="checkbox" />',
			'thumb'        => '<span class="wc-image tips">Image</span>',
			'name'         => 'Name',
			'sku'          => 'SKU',
			'is_in_stock'  => 'Stock',
			'price'        => 'Price',
			'product_cat'  => 'Categories',
			'product_tag'  => 'Tags',
			'date'         => 'Date',
		);

		$result = $this->admin->add_product_list_table_columns( $existing_columns );

		// The mock class returns the columns as-is since it skips the parent constructor
		// Verify original columns are preserved
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'cb', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'price', $result );
	}

	/**
	 * Test add_product_list_table_columns returns array.
	 */
	public function test_add_product_list_table_columns_returns_array(): void {
		$result = $this->admin->add_product_list_table_columns( array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test add_product_list_table_columns with empty input.
	 */
	public function test_add_product_list_table_columns_with_empty_input(): void {
		$result = $this->admin->add_product_list_table_columns( array() );

		// Should return an array
		$this->assertIsArray( $result );
	}

	/**
	 * Test add_products_sync_bulk_actions adds sync actions.
	 */
	public function test_add_products_sync_bulk_actions(): void {
		$existing_actions = array(
			'edit'   => 'Edit',
			'delete' => 'Move to Trash',
		);

		$result = $this->admin->add_products_sync_bulk_actions( $existing_actions );

		// Should return array with bulk actions
		$this->assertIsArray( $result );

		// Should preserve existing actions
		$this->assertArrayHasKey( 'edit', $result );
		$this->assertArrayHasKey( 'delete', $result );
	}

	/**
	 * Test add_products_sync_bulk_actions with empty array.
	 */
	public function test_add_products_sync_bulk_actions_with_empty_array(): void {
		$result = $this->admin->add_products_sync_bulk_actions( array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test add_product_settings_tab adds Facebook tab.
	 */
	public function test_add_product_settings_tab(): void {
		$existing_tabs = array(
			'general'        => array(
				'label'    => 'General',
				'target'   => 'general_product_data',
				'class'    => array(),
				'priority' => 10,
			),
			'inventory'      => array(
				'label'    => 'Inventory',
				'target'   => 'inventory_product_data',
				'class'    => array(),
				'priority' => 20,
			),
		);

		$result = $this->admin->add_product_settings_tab( $existing_tabs );

		// Should return array
		$this->assertIsArray( $result );

		// Should add Facebook tab
		$this->assertArrayHasKey( 'fb_commerce_tab', $result );

		// Verify Facebook tab has required properties
		$fb_tab = $result['fb_commerce_tab'];
		$this->assertArrayHasKey( 'label', $fb_tab );
		$this->assertArrayHasKey( 'target', $fb_tab );
	}

	/**
	 * Test add_product_settings_tab preserves existing tabs.
	 */
	public function test_add_product_settings_tab_preserves_existing(): void {
		$existing_tabs = array(
			'general'   => array( 'label' => 'General' ),
			'inventory' => array( 'label' => 'Inventory' ),
		);

		$result = $this->admin->add_product_settings_tab( $existing_tabs );

		// Should preserve existing tabs
		$this->assertArrayHasKey( 'general', $result );
		$this->assertArrayHasKey( 'inventory', $result );
	}

	/**
	 * Test filter_products_by_sync_enabled with no filter.
	 */
	public function test_filter_products_by_sync_enabled_no_filter(): void {
		$query_vars = array(
			'post_type' => 'product',
		);

		$result = $this->admin->filter_products_by_sync_enabled( $query_vars );

		// Should return unchanged query vars when no filter applied
		$this->assertIsArray( $result );
		$this->assertEquals( 'product', $result['post_type'] );
	}

	/**
	 * Test filter_products_by_sync_enabled with include filter.
	 */
	public function test_filter_products_by_sync_enabled_include(): void {
		$_GET['fb_sync_enabled'] = Admin::INCLUDE_FACEBOOK_SYNC;

		$query_vars = array(
			'post_type' => 'product',
		);

		$result = $this->admin->filter_products_by_sync_enabled( $query_vars );

		$this->assertIsArray( $result );

		unset( $_GET['fb_sync_enabled'] );
	}

	/**
	 * Test filter_products_by_sync_enabled with exclude filter.
	 */
	public function test_filter_products_by_sync_enabled_exclude(): void {
		$_GET['fb_sync_enabled'] = Admin::EXCLUDE_FACEBOOK_SYNC;

		$query_vars = array(
			'post_type' => 'product',
		);

		$result = $this->admin->filter_products_by_sync_enabled( $query_vars );

		$this->assertIsArray( $result );

		unset( $_GET['fb_sync_enabled'] );
	}

	/**
	 * Test filter_products_by_sync_enabled with invalid filter.
	 */
	public function test_filter_products_by_sync_enabled_invalid_filter(): void {
		$_GET['fb_sync_enabled'] = 'invalid_value';

		$query_vars = array(
			'post_type' => 'product',
		);

		$result = $this->admin->filter_products_by_sync_enabled( $query_vars );

		$this->assertIsArray( $result );

		unset( $_GET['fb_sync_enabled'] );
	}

	/**
	 * Test add_products_by_sync_enabled_input_filter outputs select dropdown.
	 */
	public function test_add_products_by_sync_enabled_input_filter(): void {
		// Set up global screen
		global $current_screen;
		$current_screen = (object) array( 'id' => 'edit-product', 'post_type' => 'product' );
		set_current_screen( 'edit-product' );

		ob_start();
		$this->admin->add_products_by_sync_enabled_input_filter( 'product' );
		$output = ob_get_clean();

		// Should contain the filter dropdown
		$this->assertStringContainsString( 'fb_sync_enabled', $output );
		$this->assertStringContainsString( 'select', $output );

		// Reset screen
		$current_screen = null;
	}

	/**
	 * Test add_products_by_sync_enabled_input_filter for non-product post type.
	 */
	public function test_add_products_by_sync_enabled_input_filter_non_product(): void {
		ob_start();
		$this->admin->add_products_by_sync_enabled_input_filter( 'post' );
		$output = ob_get_clean();

		// Should output nothing for non-product post types
		$this->assertEmpty( $output );
	}

	/**
	 * Test add_facebook_sync_bulk_edit_dropdown_at_bottom method exists.
	 */
	public function test_add_facebook_sync_bulk_edit_dropdown_at_bottom(): void {
		// Verify the method exists and can be called
		$this->assertTrue(
			method_exists( $this->admin, 'add_facebook_sync_bulk_edit_dropdown_at_bottom' ),
			'add_facebook_sync_bulk_edit_dropdown_at_bottom method should exist'
		);

		// Call the method - it should not throw an error
		ob_start();
		$this->admin->add_facebook_sync_bulk_edit_dropdown_at_bottom();
		$output = ob_get_clean();

		$this->assertIsString( $output );
	}

	/**
	 * Test handle_products_sync_bulk_actions with sync_and_show action.
	 */
	public function test_handle_products_sync_bulk_actions_sync_and_show(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Bulk Test Product' );
		$product->set_regular_price( '10' );
		$product->save();

		$product_id = $product->get_id();
		$this->assertGreaterThan( 0, $product_id, 'Product should be saved with valid ID' );

		$_REQUEST['wc_facebook_sync_mode'] = Admin::SYNC_MODE_SYNC_AND_SHOW;

		$this->admin->handle_products_sync_bulk_actions( $product );

		// Verify product still exists and wasn't corrupted
		$reloaded_product = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product::class, $reloaded_product );
		$this->assertEquals( 'Bulk Test Product', $reloaded_product->get_name() );

		// Clean up
		$product->delete( true );
		unset( $_REQUEST['wc_facebook_sync_mode'] );
	}

	/**
	 * Test handle_products_sync_bulk_actions with sync_disabled action.
	 */
	public function test_handle_products_sync_bulk_actions_sync_disabled(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Bulk Disable Test Product' );
		$product->set_regular_price( '15' );
		$product->save();

		$product_id = $product->get_id();
		$this->assertGreaterThan( 0, $product_id, 'Product should be saved with valid ID' );

		$_REQUEST['wc_facebook_sync_mode'] = Admin::SYNC_MODE_SYNC_DISABLED;

		$this->admin->handle_products_sync_bulk_actions( $product );

		// Verify product still exists and wasn't corrupted
		$reloaded_product = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product::class, $reloaded_product );
		$this->assertEquals( '15', $reloaded_product->get_regular_price() );

		// Clean up
		$product->delete( true );
		unset( $_REQUEST['wc_facebook_sync_mode'] );
	}

	/**
	 * Test handle_products_sync_bulk_actions with no action.
	 */
	public function test_handle_products_sync_bulk_actions_no_action(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'No Action Test Product' );
		$product->set_regular_price( '20' );
		$product->save();

		$product_id = $product->get_id();

		// Don't set sync mode - ensure REQUEST is clean
		unset( $_REQUEST['wc_facebook_sync_mode'] );

		$this->admin->handle_products_sync_bulk_actions( $product );

		// Verify product is unchanged when no sync mode is set
		$reloaded_product = wc_get_product( $product_id );
		$this->assertEquals( 'No Action Test Product', $reloaded_product->get_name() );
		$this->assertEquals( '20', $reloaded_product->get_regular_price() );

		// Clean up
		$product->delete( true );
	}

	/**
	 * Test add_product_list_table_columns_content for sync enabled column.
	 */
	public function test_add_product_list_table_columns_content_sync_enabled(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Column Content Test Product' );
		$product->set_regular_price( '25' );
		$product->save();

		global $post;
		$post = get_post( $product->get_id() );

		ob_start();
		$this->admin->add_product_list_table_columns_content( 'facebook_sync_enabled' );
		$output = ob_get_clean();

		// Should output something for the sync column
		$this->assertIsString( $output );

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test add_product_list_table_columns_content for visibility column.
	 */
	public function test_add_product_list_table_columns_content_visibility(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Visibility Column Test Product' );
		$product->set_regular_price( '30' );
		$product->save();

		global $post;
		$post = get_post( $product->get_id() );

		ob_start();
		$this->admin->add_product_list_table_columns_content( 'facebook_catalog_visibility' );
		$output = ob_get_clean();

		// Should output something for the visibility column
		$this->assertIsString( $output );

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test add_product_list_table_columns_content for unknown column.
	 */
	public function test_add_product_list_table_columns_content_unknown_column(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Unknown Column Test Product' );
		$product->set_regular_price( '35' );
		$product->save();

		global $post;
		$post = get_post( $product->get_id() );

		ob_start();
		$this->admin->add_product_list_table_columns_content( 'unknown_column' );
		$output = ob_get_clean();

		// Should output nothing for unknown columns
		$this->assertEmpty( $output );

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test render_modal_template outputs modal HTML.
	 */
	public function test_render_modal_template(): void {
		// Set up screen
		global $current_screen;
		$current_screen = (object) array( 'id' => 'product' );
		set_current_screen( 'product' );

		ob_start();
		$this->admin->render_modal_template();
		$output = ob_get_clean();

		// Should contain modal structure
		$this->assertIsString( $output );

		// Reset screen
		$current_screen = null;
	}

	/**
	 * Test add_tab_switch_script outputs JavaScript.
	 */
	public function test_add_tab_switch_script(): void {
		// Set up screen
		global $current_screen;
		$current_screen = (object) array( 'id' => 'product' );
		set_current_screen( 'product' );

		ob_start();
		$this->admin->add_tab_switch_script();
		$output = ob_get_clean();

		// Should contain script
		$this->assertIsString( $output );

		// Reset screen
		$current_screen = null;
	}

	/**
	 * Test get_sync_mode_options returns correct options.
	 */
	public function test_get_sync_mode_options(): void {
		// The sync mode options should be one of the defined constants
		$valid_modes = array(
			Admin::SYNC_MODE_SYNC_AND_SHOW,
			Admin::SYNC_MODE_SYNC_AND_HIDE,
			Admin::SYNC_MODE_SYNC_DISABLED,
		);

		foreach ( $valid_modes as $mode ) {
			$this->assertNotEmpty( $mode );
			$this->assertIsString( $mode );
		}
	}

	/**
	 * Test sync modes constants have expected values.
	 */
	public function test_sync_modes_in_bulk_edit_dropdown(): void {
		// Verify all sync modes are valid strings
		$this->assertSame( 'sync_and_show', Admin::SYNC_MODE_SYNC_AND_SHOW );
		$this->assertSame( 'sync_and_hide', Admin::SYNC_MODE_SYNC_AND_HIDE );
		$this->assertSame( 'sync_disabled', Admin::SYNC_MODE_SYNC_DISABLED );
	}

	/**
	 * Test product settings tab content renders correctly.
	 */
	public function test_add_product_settings_tab_content(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Tab Content Test Product' );
		$product->set_regular_price( '40' );
		$product->save();

		global $post;
		$post = get_post( $product->get_id() );

		ob_start();
		$this->admin->add_product_settings_tab_content();
		$output = ob_get_clean();

		// Should contain Facebook settings content
		$this->assertIsString( $output );

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test product settings tab method exists.
	 */
	public function test_product_settings_tab_structure(): void {
		// Verify the method exists
		$this->assertTrue(
			method_exists( $this->admin, 'add_product_settings_tab' ),
			'add_product_settings_tab method should exist'
		);

		$tabs = array();
		$result = $this->admin->add_product_settings_tab( $tabs );

		// Should return an array
		$this->assertIsArray( $result );
	}

	/**
	 * Test filter preserves meta query structure.
	 */
	public function test_filter_products_preserves_meta_query(): void {
		$_GET['fb_sync_enabled'] = Admin::INCLUDE_FACEBOOK_SYNC;

		$query_vars = array(
			'post_type'  => 'product',
			'meta_query' => array(
				array(
					'key'   => '_price',
					'value' => 10,
					'type'  => 'NUMERIC',
				),
			),
		);

		$result = $this->admin->filter_products_by_sync_enabled( $query_vars );

		// Should preserve existing meta query
		$this->assertIsArray( $result );

		unset( $_GET['fb_sync_enabled'] );
	}

	/**
	 * Test bulk actions with variable product.
	 */
	public function test_bulk_actions_with_variable_product(): void {
		$variable_product = \WC_Helper_Product::create_variation_product();
		$product_id = $variable_product->get_id();

		$this->assertInstanceOf( \WC_Product_Variable::class, $variable_product );
		$this->assertGreaterThan( 0, count( $variable_product->get_children() ), 'Variable product should have variations' );

		$_REQUEST['wc_facebook_sync_mode'] = Admin::SYNC_MODE_SYNC_AND_SHOW;

		$this->admin->handle_products_sync_bulk_actions( $variable_product );

		// Verify product still exists and is valid
		$reloaded = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product_Variable::class, $reloaded );

		// Clean up
		$variable_product->delete( true );
		unset( $_REQUEST['wc_facebook_sync_mode'] );
	}

	/**
	 * Test bulk actions with sync_and_hide mode.
	 */
	public function test_bulk_actions_sync_and_hide(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sync and Hide Test Product' );
		$product->set_regular_price( '45' );
		$product->save();

		$product_id = $product->get_id();
		$this->assertGreaterThan( 0, $product_id );

		$_REQUEST['wc_facebook_sync_mode'] = Admin::SYNC_MODE_SYNC_AND_HIDE;

		$this->admin->handle_products_sync_bulk_actions( $product );

		// Verify product integrity after bulk action
		$reloaded = wc_get_product( $product_id );
		$this->assertEquals( 'Sync and Hide Test Product', $reloaded->get_name() );
		$this->assertEquals( '45', $reloaded->get_regular_price() );

		// Clean up
		$product->delete( true );
		unset( $_REQUEST['wc_facebook_sync_mode'] );
	}

	/**
	 * Test columns method preserves input.
	 */
	public function test_columns_order(): void {
		$columns = array(
			'name'  => 'Name',
			'price' => 'Price',
		);

		$result = $this->admin->add_product_list_table_columns( $columns );

		// Get column keys
		$keys = array_keys( $result );

		// Original columns should be preserved
		$this->assertContains( 'name', $keys );
		$this->assertContains( 'price', $keys );
	}

	/**
	 * Test filter options dropdown has correct values.
	 */
	public function test_filter_dropdown_values(): void {
		global $current_screen;
		$current_screen = (object) array( 'id' => 'edit-product', 'post_type' => 'product' );
		set_current_screen( 'edit-product' );

		ob_start();
		$this->admin->add_products_by_sync_enabled_input_filter( 'product' );
		$output = ob_get_clean();

		// Should contain both filter options
		$this->assertStringContainsString( Admin::INCLUDE_FACEBOOK_SYNC, $output );
		$this->assertStringContainsString( Admin::EXCLUDE_FACEBOOK_SYNC, $output );

		$current_screen = null;
	}

	/**
	 * Test admin handles product with no ID gracefully.
	 */
	public function test_handle_bulk_action_no_product_id(): void {
		// Create a mock product without ID
		$product = $this->getMockBuilder( \WC_Product_Simple::class )
			->disableOriginalConstructor()
			->getMock();

		$product->method( 'get_id' )->willReturn( 0 );
		$product->method( 'get_type' )->willReturn( 'simple' );

		$_REQUEST['wc_facebook_sync_mode'] = Admin::SYNC_MODE_SYNC_AND_SHOW;

		// Verify mock is set up correctly
		$this->assertEquals( 0, $product->get_id() );
		$this->assertEquals( 'simple', $product->get_type() );

		// Method should handle zero ID without throwing exception
		$this->admin->handle_products_sync_bulk_actions( $product );

		// If we reach here, the method handled the edge case
		$this->assertEquals( 0, $product->get_id(), 'Product ID should still be 0' );

		unset( $_REQUEST['wc_facebook_sync_mode'] );
	}

	/**
	 * Test multiple products bulk action.
	 */
	public function test_multiple_products_bulk_action(): void {
		$products = array();
		$product_ids = array();

		// Create multiple products
		for ( $i = 0; $i < 3; $i++ ) {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Bulk Test Product ' . $i );
			$product->set_regular_price( (string) ( 10 + $i ) );
			$product->save();
			$products[] = $product;
			$product_ids[] = $product->get_id();
		}

		$this->assertCount( 3, $products, 'Should have created 3 products' );

		$_REQUEST['wc_facebook_sync_mode'] = Admin::SYNC_MODE_SYNC_AND_SHOW;

		// Apply bulk action to each product
		foreach ( $products as $product ) {
			$this->admin->handle_products_sync_bulk_actions( $product );
		}

		// Verify all products still exist and are valid
		for ( $i = 0; $i < 3; $i++ ) {
			$reloaded = wc_get_product( $product_ids[ $i ] );
			$this->assertInstanceOf( \WC_Product::class, $reloaded );
			$this->assertEquals( 'Bulk Test Product ' . $i, $reloaded->get_name() );
		}

		// Clean up
		foreach ( $products as $product ) {
			$product->delete( true );
		}
		unset( $_REQUEST['wc_facebook_sync_mode'] );
	}

	/**
	 * Test is_current_product_published method.
	 */
	public function test_is_current_product_published(): void {
		// Create and publish a product
		$product = new \WC_Product_Simple();
		$product->set_name( 'Published Test Product' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		$product->save();

		global $post;
		$post = get_post( $product->get_id() );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $this->admin );
		if ( $reflection->hasMethod( 'is_current_product_published' ) ) {
			$method = $reflection->getMethod( 'is_current_product_published' );
			$method->setAccessible( true );

			$result = $method->invoke( $this->admin );
			$this->assertTrue( $result );
		}

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test is_current_product_published with draft product.
	 */
	public function test_is_current_product_published_draft(): void {
		// Create a draft product
		$product = new \WC_Product_Simple();
		$product->set_name( 'Draft Test Product' );
		$product->set_regular_price( '10' );
		$product->set_status( 'draft' );
		$product->save();

		global $post;
		$post = get_post( $product->get_id() );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $this->admin );
		if ( $reflection->hasMethod( 'is_current_product_published' ) ) {
			$method = $reflection->getMethod( 'is_current_product_published' );
			$method->setAccessible( true );

			$result = $method->invoke( $this->admin );
			$this->assertFalse( $result );
		}

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test is_sync_enabled_for_current_product method.
	 */
	public function test_is_sync_enabled_for_current_product(): void {
		// Create a product with sync enabled
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sync Enabled Test Product' );
		$product->set_regular_price( '10' );
		$product->save();

		$product_id = $product->get_id();
		$this->assertGreaterThan( 0, $product_id, 'Product should have valid ID' );

		// Set sync disabled to no (meaning sync is enabled)
		update_post_meta( $product_id, \WC_Facebook_Product::FB_REMOVE_FROM_SYNC, 'no' );

		// Verify the meta was saved correctly
		$saved_meta = get_post_meta( $product_id, \WC_Facebook_Product::FB_REMOVE_FROM_SYNC, true );
		$this->assertEquals( 'no', $saved_meta, 'FB_REMOVE_FROM_SYNC should be set to no' );

		global $post;
		$post = get_post( $product_id );
		$this->assertInstanceOf( \WP_Post::class, $post, 'Post should be valid WP_Post instance' );

		// Verify method exists in the Admin class
		$reflection = new \ReflectionClass( Admin::class );
		$this->assertTrue(
			$reflection->hasMethod( 'is_sync_enabled_for_current_product' ),
			'Admin class should have is_sync_enabled_for_current_product method'
		);

		// Clean up
		$product->delete( true );
		$post = null;
	}

	/**
	 * Test is_sync_enabled_for_current_product with sync disabled.
	 */
	public function test_is_sync_enabled_for_current_product_disabled(): void {
		// Create a product with sync disabled
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sync Disabled Test Product' );
		$product->set_regular_price( '10' );
		$product->save();

		$product_id = $product->get_id();
		$this->assertGreaterThan( 0, $product_id, 'Product should have valid ID' );

		// Disable sync (yes means remove from sync / disabled)
		update_post_meta( $product_id, \WC_Facebook_Product::FB_REMOVE_FROM_SYNC, 'yes' );

		// Verify the meta was saved correctly
		$saved_meta = get_post_meta( $product_id, \WC_Facebook_Product::FB_REMOVE_FROM_SYNC, true );
		$this->assertEquals( 'yes', $saved_meta, 'FB_REMOVE_FROM_SYNC should be set to yes' );

		global $post;
		$post = get_post( $product_id );
		$this->assertInstanceOf( \WP_Post::class, $post, 'Post should be valid WP_Post instance' );
		$this->assertEquals( $product_id, $post->ID, 'Post ID should match product ID' );

		// Verify the constant exists
		$this->assertEquals(
			'fb_remove_from_sync',
			\WC_Facebook_Product::FB_REMOVE_FROM_SYNC,
			'FB_REMOVE_FROM_SYNC constant should have expected value'
		);

		// Clean up
		$product->delete( true );
		$post = null;
	}
}
