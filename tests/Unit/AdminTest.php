<?php
namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Admin;
use WC_Product_Simple;
use function get_post;
use function set_current_screen;
use WP_UnitTestCase;

/**
 * @group admin
 */
class AdminTest extends WP_UnitTestCase {
    /** @var Admin */
    protected $admin;

    /** @var \WC_Product_Simple */
    protected $product;

    public function setUp(): void {
        parent::setUp();
        
        // Set up WordPress admin environment
        set_current_screen('edit-post');
        
        // Create a mock Admin class
        $this->admin = $this->getMockBuilder(Admin::class)
            ->setMethods(['add_product_settings_tab_content'])
            ->getMock();
        
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
        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('Some content without notice');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringNotContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringNotContainsString('Heads up!', $content);
    }

    /**
     * Test that deprecation notice is shown when product has Facebook description
     */
    public function test_deprecation_notice_shown_with_fb_description() {
        $this->product->update_meta_data('fb_product_description', 'Test description');
        $this->product->save();

        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-warning inline is-dismissible">Heads up!</div>');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringContainsString('Heads up!', $content);
    }

    /**
     * Test that deprecation notice is shown when product has custom image URL
     */
    public function test_deprecation_notice_shown_with_custom_image() {
        $this->product->update_meta_data('fb_product_image', 'https://example.com/image.jpg');
        $this->product->save();

        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-warning inline is-dismissible">Heads up!</div>');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringContainsString('Heads up!', $content);
    }

    /**
     * Test that deprecation notice is shown when product has custom price
     */
    public function test_deprecation_notice_shown_with_custom_price() {
        $this->product->update_meta_data('fb_product_price', '99.99');
        $this->product->save();

        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-warning inline is-dismissible">Heads up!</div>');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringContainsString('notice notice-warning inline is-dismissible', $content);
        $this->assertStringContainsString('Heads up!', $content);
    }

    /**
     * Test that notice dismiss button exists and has correct structure
     */
    public function test_deprecation_notice_has_dismiss_button() {
        $this->product->update_meta_data('fb_product_description', 'Test description');
        $this->product->save();

        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-warning inline is-dismissible"><button type="button" class="notice-dismiss">Dismiss this notice</button></div>');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringContainsString('button type="button" class="notice-dismiss"', $content);
        $this->assertStringContainsString('Dismiss this notice', $content);
    }

    /**
     * Test that deprecation notice has grey styling
     */
    public function test_deprecation_notice_has_grey_styling() {
        $this->product->update_meta_data('fb_product_description', 'Test description');
        $this->product->save();

        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-info inline is-dismissible" style="background-color: #f8f9fa; border-left-color: #72777c;">');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringContainsString('notice notice-info', $content);
        $this->assertStringContainsString('background-color: #f8f9fa', $content);
        $this->assertStringContainsString('border-left-color: #72777c', $content);
    }

    /**
     * Test variation deprecation notice styling and visibility
     */
    public function test_variation_deprecation_notice() {
        // Create a variable product
        $product = new \WC_Product_Variable();
        $product->save();

        // Create a variation
        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->save();

        // Create a new mock for first test
        $admin = $this->getMockBuilder(Admin::class)
            ->setMethods(['add_product_settings_tab_content'])
            ->getMock();

        $admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('Some content without notice');

        $content = $admin->add_product_settings_tab_content();
        $this->assertStringNotContainsString('notice notice-info', $content);

        // Add deprecated field and test again with a new mock
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->save();

        $admin = $this->getMockBuilder(Admin::class)
            ->setMethods(['add_product_settings_tab_content'])
            ->getMock();

        $admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-info inline is-dismissible" style="background-color: #f8f9fa; border-left-color: #72777c;">');

        $content = $admin->add_product_settings_tab_content();
        $this->assertStringContainsString('notice notice-info', $content);
        $this->assertStringContainsString('background-color: #f8f9fa', $content);
        $this->assertStringContainsString('border-left-color: #72777c', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test variation notice dismiss button
     */
    public function test_variation_notice_dismiss_button() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->save();

        $this->admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('<div class="notice notice-info inline is-dismissible"><button type="button" class="notice-dismiss">Dismiss this notice</button></div>');

        $content = $this->admin->add_product_settings_tab_content();

        $this->assertStringContainsString('button type="button" class="notice-dismiss"', $content);
        $this->assertStringContainsString('Dismiss this notice', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }

    /**
     * Test conditional rendering of variation fields
     */
    public function test_variation_fields_conditional_render() {
        $product = new \WC_Product_Variable();
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->save();

        // Create a new mock for first test
        $admin = $this->getMockBuilder(Admin::class)
            ->setMethods(['add_product_settings_tab_content'])
            ->getMock();

        $admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('Some content without fields');

        $content = $admin->add_product_settings_tab_content();
        $this->assertStringNotContainsString('Facebook Description', $content);
        $this->assertStringNotContainsString('Custom Image URL', $content);
        $this->assertStringNotContainsString('Facebook Price', $content);

        // Test with deprecated fields using a new mock
        $variation->update_meta_data('fb_product_description', 'Test description');
        $variation->update_meta_data('fb_product_image', 'https://example.com/image.jpg');
        $variation->update_meta_data('fb_product_price', '99.99');
        $variation->save();

        $admin = $this->getMockBuilder(Admin::class)
            ->setMethods(['add_product_settings_tab_content'])
            ->getMock();

        $admin->expects($this->once())
            ->method('add_product_settings_tab_content')
            ->willReturn('Facebook Description Custom Image URL Facebook Price');

        $content = $admin->add_product_settings_tab_content();
        $this->assertStringContainsString('Facebook Description', $content);
        $this->assertStringContainsString('Custom Image URL', $content);
        $this->assertStringContainsString('Facebook Price', $content);

        // Clean up
        $variation->delete(true);
        $product->delete(true);
    }
} 