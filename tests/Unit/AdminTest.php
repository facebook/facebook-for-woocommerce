<?php
namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Admin;
use WC_Product_Simple;
use function get_post;
use function set_current_screen;
use WP_UnitTestCase;
use PHPUnit\Framework\TestCase;

/**
 * @group admin
 */
class AdminTest extends TestCase {
    /** @var Admin */
    protected $admin;

    /** @var \WC_Product_Simple */
    protected $product;

    /** @var string */
    protected $wc_plugin_dir;

    public function setUp(): void {
        parent::setUp();
        
        // Find WooCommerce plugin directory
        $this->wc_plugin_dir = WP_PLUGIN_DIR . '/woocommerce';
        if (!file_exists($this->wc_plugin_dir)) {
            $this->wc_plugin_dir = dirname(dirname(dirname(__DIR__))) . '/woocommerce';
        }
        
        if (!file_exists($this->wc_plugin_dir)) {
            $this->markTestSkipped('WooCommerce plugin is required for this test.');
            return;
        }

        // Include WooCommerce admin functions if not already included
        if (!function_exists('woocommerce_wp_select')) {
            require_once $this->wc_plugin_dir . '/includes/admin/wc-admin-functions.php';
            require_once $this->wc_plugin_dir . '/includes/admin/wc-meta-box-functions.php';
        }
        
        // Set up WordPress admin environment
        set_current_screen('edit-post');
        
        // Create real Admin instance
        $this->admin = new Admin();
        
        // Create a test product
        $this->product = new \WC_Product_Simple();
        $this->product->save();
        
        // Set up the global post
        $GLOBALS['post'] = get_post($this->product->get_id());
    }

    public function tearDown(): void {
        parent::tearDown();
        
        // Clean up WordPress admin environment
        set_current_screen('front');
        
        // Clean up
        if ($this->product) {
            $this->product->delete(true);
        }
    }

    /**
     * Test that deprecation notice is not shown for new products
     */
    public function test_deprecation_notice_not_shown_for_new_products() {
        ob_start();
        $this->admin->add_product_settings_tab_content();
        $content = ob_get_clean();

        $this->assertStringNotContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringNotContainsString('Some attributes are no longer supported', $content);
    }

    /**
     * Test that deprecation notice is shown when product has Facebook description
     */
    public function test_deprecation_notice_shown_with_fb_description() {
        $this->product->update_meta_data('fb_product_description', 'Test description');
        $this->product->save();

        ob_start();
        $this->admin->add_product_settings_tab_content();
        $content = ob_get_clean();

        $this->assertStringContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringContainsString('Some attributes are no longer supported', $content);
        $this->assertStringContainsString('Facebook Description, Custom Image URL, and Facebook Price are no longer supported', $content);
    }

    /**
     * Test that deprecation notice is shown when product has custom image URL
     */
    public function test_deprecation_notice_shown_with_custom_image() {
        $this->product->update_meta_data('fb_product_image', 'https://example.com/image.jpg');
        $this->product->save();

        ob_start();
        $this->admin->add_product_settings_tab_content();
        $content = ob_get_clean();

        $this->assertStringContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringContainsString('Some attributes are no longer supported', $content);
    }

    /**
     * Test that deprecation notice is shown when product has custom price
     */
    public function test_deprecation_notice_shown_with_custom_price() {
        $this->product->update_meta_data('fb_product_price', '99.99');
        $this->product->save();

        ob_start();
        $this->admin->add_product_settings_tab_content();
        $content = ob_get_clean();

        $this->assertStringContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringContainsString('Some attributes are no longer supported', $content);
    }

    /**
     * Test that notice dismiss button exists and has correct structure
     */
    public function test_deprecation_notice_has_dismiss_button() {
        $this->product->update_meta_data('fb_product_description', 'Test description');
        $this->product->save();

        ob_start();
        $this->admin->add_product_settings_tab_content();
        $content = ob_get_clean();

        $this->assertStringContainsString('button type="button" class="notice-dismiss"', $content);
        $this->assertStringContainsString('Dismiss this notice', $content);
    }

    /**
     * Test that deprecation notice has correct styling
     */
    public function test_deprecation_notice_has_correct_styling() {
        $this->product->update_meta_data('fb_product_description', 'Test description');
        $this->product->save();

        ob_start();
        $this->admin->add_product_settings_tab_content();
        $content = ob_get_clean();

        $this->assertStringContainsString('style="padding: 1px; margin: 10px 10px 0;"', $content);
    }

    /**
     * Test that variation notice is not shown when no deprecated fields exist
     */
    public function test_variation_notice_not_shown_without_deprecated_fields() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();
        
        $this->assertStringNotContainsString('notice notice-warning', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation notice is shown when deprecated fields exist
     */
    public function test_variation_notice_shown_with_deprecated_fields() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('notice notice-warning', $content);
        $this->assertStringContainsString('Some attributes are no longer supported', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation notice has correct styling
     */
    public function test_variation_notice_has_correct_styling() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('style="padding: 1px; margin: 10px 10px 0;"', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation notice has dismiss button
     */
    public function test_variation_notice_has_dismiss_button() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('button type="button" class="notice-dismiss"', $content);
        $this->assertStringContainsString('Dismiss this notice', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields are not shown when no deprecated fields exist
     */
    public function test_variation_fields_not_shown_without_deprecated_fields() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();
        
        $this->assertStringNotContainsString('Facebook Description', $content);
        $this->assertStringNotContainsString('Custom Image URL', $content);
        $this->assertStringNotContainsString('Facebook Price', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields are shown when deprecated fields exist
     */
    public function test_variation_fields_shown_with_deprecated_fields() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->update_meta_data('fb_product_image', 'https://example.com/image.jpg');
        $variation->update_meta_data('fb_product_price', '99.99');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('Facebook Description', $content);
        $this->assertStringContainsString('Custom Image URL', $content);
        $this->assertStringContainsString('Facebook Price', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields show correct values
     */
    public function test_variation_fields_show_correct_values() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test description content');
        $variation->update_meta_data('fb_product_image', 'https://example.com/test-image.jpg');
        $variation->update_meta_data('fb_product_price', '99.99');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('Test description content', $content);
        $this->assertStringContainsString('https://example.com/test-image.jpg', $content);
        $this->assertStringContainsString('99.99', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields inherit parent values when not set
     */
    public function test_variation_fields_inherit_parent_values() {
        $product = new \WC_Product_Variable();
        $product->update_meta_data('fb_product_description', 'Parent description');
        $product->update_meta_data('fb_product_image', 'https://example.com/parent-image.jpg');
        $product->update_meta_data('fb_product_price', '199.99');
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('Parent description', $content);
        $this->assertStringContainsString('https://example.com/parent-image.jpg', $content);
        $this->assertStringContainsString('199.99', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields handle empty values correctly
     */
    public function test_variation_fields_handle_empty_values() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', '');
        $variation->update_meta_data('fb_product_image', '');
        $variation->update_meta_data('fb_product_price', '');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('value=""', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields handle special characters correctly
     */
    public function test_variation_fields_handle_special_characters() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test & description with <special> "characters"');
        $variation->update_meta_data('fb_product_image', 'https://example.com/image?param=value&special=true');
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString('Test &amp; description with &lt;special&gt; &quot;characters&quot;', $content);
        $this->assertStringContainsString('https://example.com/image?param=value&amp;special=true', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields handle long text values correctly
     */
    public function test_variation_fields_handle_long_text() {
        $product = new \WC_Product_Variable();
        $product->save();

        $long_description = str_repeat('This is a very long description. ', 50);
        $long_image_url = 'https://example.com/image/' . str_repeat('very-long-path-segment-', 10) . '.jpg';

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', $long_description);
        $variation->update_meta_data('fb_product_image', $long_image_url);
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString($long_description, $content);
        $this->assertStringContainsString($long_image_url, $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields handle multiple variations correctly
     */
    public function test_variation_fields_handle_multiple_variations() {
        $product = new \WC_Product_Variable();
        $product->save();

        // Create multiple variations
        $variations = [];
        for ($i = 1; $i <= 3; $i++) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->update_meta_data('fb_product_description', "Description {$i}");
            $variation->update_meta_data('fb_product_image', "https://example.com/image-{$i}.jpg");
            $variation->update_meta_data('fb_product_price', "99.{$i}9");
            $variation->save();
            $variations[] = $variation;
        }

        // Test each variation
        foreach ($variations as $index => $variation) {
            ob_start();
            $this->admin->add_product_variation_edit_fields($index, [], get_post($variation->get_id()));
            $content = ob_get_clean();

            $this->assertStringContainsString("Description " . ($index + 1), $content);
            $this->assertStringContainsString("image-" . ($index + 1), $content);
            $this->assertStringContainsString("99." . ($index + 1) . "9", $content);
        }

        // Clean up
        foreach ($variations as $variation) {
            $variation->delete(true);
        }
        $product->delete(true);
    }

    /**
     * Test that variation fields handle HTML content correctly
     */
    public function test_variation_fields_handle_html_content() {
        $product = new \WC_Product_Variable();
        $product->save();

        $html_description = '<p>This is a <strong>bold</strong> description with <a href="#">link</a></p>';
        
        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', $html_description);
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString(htmlspecialchars($html_description, ENT_QUOTES), $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields handle Unicode characters correctly
     */
    public function test_variation_fields_handle_unicode_characters() {
        $product = new \WC_Product_Variable();
        $product->save();

        $unicode_description = 'Description with Unicode: 🚀 emoji, 汉字 Chinese, Español Spanish';
        
        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', $unicode_description);
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        $this->assertStringContainsString($unicode_description, $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test that variation fields handle price formatting correctly
     */
    public function test_variation_fields_handle_price_formatting() {
        $product = new \WC_Product_Variable();
        $product->save();

        $test_prices = [
            '99.99',
            '1234.56',
            '0.99',
            '1000000.00',
            '.99'
        ];

        foreach ($test_prices as $index => $price) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->update_meta_data('fb_product_price', $price);
            $variation->save();

            ob_start();
            $this->admin->add_product_variation_edit_fields($index, [], get_post($variation->get_id()));
            $content = ob_get_clean();

            $this->assertStringContainsString(wc_format_decimal($price), $content);

            $variation->delete(true);
        }

        // Clean up
        $product->delete(true);
    }

    /**
     * Test that variation fields handle invalid values correctly
     */
    public function test_variation_fields_handle_invalid_values() {
        $product = new \WC_Product_Variable();
        $product->save();

        $invalid_values = [
            'fb_product_image' => 'not-a-valid-url',
            'fb_product_price' => 'not-a-number',
            'fb_product_description' => null
        ];

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        foreach ($invalid_values as $key => $value) {
            $variation->update_meta_data($key, $value);
        }
        $variation->save();

        ob_start();
        $this->admin->add_product_variation_edit_fields(0, [], get_post($variation->get_id()));
        $content = ob_get_clean();

        // Verify that invalid values are handled gracefully
        $this->assertStringContainsString('value=""', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }
} 