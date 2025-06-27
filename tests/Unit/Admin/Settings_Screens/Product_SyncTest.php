<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Product_Sync;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class Product_SyncTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class Product_SyncTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * @var Product_Sync
     */
    private $product_sync;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Instantiate the Product_Sync class for each test
        $this->product_sync = new Product_Sync();
    }

    /**
     * Test that the constructor hooks actions for init, enqueue, and custom fields
     */
    public function test_constructor_adds_hooks() {
        global $wp_filter;

        // Check that all expected hooks are present
        $this->assertArrayHasKey('init', $wp_filter);
        $this->assertArrayHasKey('admin_enqueue_scripts', $wp_filter);
        $this->assertArrayHasKey('woocommerce_admin_field_product_sync_title', $wp_filter);
        $this->assertArrayHasKey('woocommerce_admin_field_product_sync_google_product_categories', $wp_filter);
    }

    /**
     * Test that initHook sets the id, label, title, and documentation_url properties
     */
    public function test_initHook_sets_properties() {
        $this->product_sync->initHook();

        $reflection = new \ReflectionClass($this->product_sync);
        $id = $reflection->getProperty('id');
        $id->setAccessible(true);
        $label = $reflection->getProperty('label');
        $label->setAccessible(true);
        $title = $reflection->getProperty('title');
        $title->setAccessible(true);
        $doc_url = $reflection->getProperty('documentation_url');
        $doc_url->setAccessible(true);

        $this->assertEquals(Product_Sync::ID, $id->getValue($this->product_sync));
        $this->assertEquals(__('Product sync', 'facebook-for-woocommerce'), $label->getValue($this->product_sync));
        $this->assertEquals(__('Product sync', 'facebook-for-woocommerce'), $title->getValue($this->product_sync));
        $this->assertEquals('https://woocommerce.com/document/facebook-for-woocommerce/#product-sync-settings', $doc_url->getValue($this->product_sync));
    }

    /**
     * Test that get_settings returns an array
     */
    public function test_get_settings_returns_array() {
        $settings = $this->product_sync->get_settings();

        $this->assertIsArray($settings);
        $this->assertNotEmpty($settings);
    }

    /**
     * Test that get_id returns the expected value
     */
    public function test_get_id_returns_expected_value() {
        $this->product_sync->initHook();

        $this->assertEquals(Product_Sync::ID, $this->product_sync->get_id());
    }

    /**
     * Test that get_label returns the expected value and applies the filter
     */
    public function test_get_label_returns_expected_value_and_applies_filter() {
        $this->product_sync->initHook();

        $filter = 'wc_facebook_admin_settings_' . Product_Sync::ID . '_screen_label';
        add_filter($filter, function($label) { return 'Filtered Label'; });

        $this->assertEquals('Filtered Label', $this->product_sync->get_label());

        remove_all_filters($filter);
    }

    /**
     * Test that get_title returns the expected value and applies the filter
     */
    public function test_get_title_returns_expected_value_and_applies_filter() {
        $this->product_sync->initHook();

        $filter = 'wc_facebook_admin_settings_' . Product_Sync::ID . '_screen_title';
        add_filter($filter, function($title) { return 'Filtered Title'; });

        $this->assertEquals('Filtered Title', $this->product_sync->get_title());

        remove_all_filters($filter);
    }

    /**
     * Test that get_description returns the expected value and applies the filter
     */
    public function test_get_description_returns_expected_value_and_applies_filter() {
        $this->product_sync->initHook();

        $filter = 'wc_facebook_admin_settings_' . Product_Sync::ID . '_screen_description';
        add_filter($filter, function($desc) { return 'Filtered Description'; });

        $this->assertEquals('Filtered Description', $this->product_sync->get_description());

        remove_all_filters($filter);
    }

    /**
     * Test that get_disconnected_message returns a string containing the connect link
     */
    public function test_get_disconnected_message_contains_connect_link() {
        $msg = $this->product_sync->get_disconnected_message();

        $this->assertIsString($msg);
        $this->assertStringContainsString('connect to Facebook', $msg);
        $this->assertStringContainsString('<a ', $msg);
        $this->assertStringContainsString('</a>', $msg);
    }

    /**
     * Test that render_title is callable and outputs HTML
     */
    public function test_render_title_is_callable() {
        ob_start();
        $this->product_sync->render_title(['title' => 'Product sync']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('<h2>', $output);
    }

    /**
     * Test that render_google_product_category_field is callable and outputs HTML
     */
    public function test_render_google_product_category_field_is_callable() {
        ob_start();
        $this->product_sync->render_google_product_category_field([
            'id' => 'test_google_cat',
            'title' => 'Google Cat',
            'desc_tip' => 'desc',
            'type' => 'product_sync_google_product_categories',
            'value' => 'val',
        ]);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('<tr', $output);
    }

    /**
     * Test that enqueue_assets is callable and does not throw (integration test would be needed for full coverage)
     */
    public function test_enqueue_assets_is_callable() {
        try {
            // Should not throw even if dependencies are not fully mocked
            $this->product_sync->enqueue_assets();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('enqueue_assets() should not throw, got: ' . $e->getMessage());
        }
    }

    /**
     * Test the private get_default_google_product_category_modal_message method
     */
    public function test_get_default_google_product_category_modal_message() {
        $reflection = new \ReflectionClass($this->product_sync);
        $method = $reflection->getMethod('get_default_google_product_category_modal_message');
        $method->setAccessible(true);

        // Call the private method
        $msg = $method->invoke($this->product_sync);

        $this->assertIsString($msg);
        $this->assertStringContainsString('Products and categories that inherit this global setting', $msg);
    }

    /**
     * Test the private get_default_google_product_category_modal_message_empty method
     */
    public function test_get_default_google_product_category_modal_message_empty() {
        $reflection = new \ReflectionClass($this->product_sync);
        $method = $reflection->getMethod('get_default_google_product_category_modal_message_empty');
        $method->setAccessible(true);

        // Call the private method
        $msg = $method->invoke($this->product_sync);

        $this->assertIsString($msg);
        $this->assertStringContainsString('If you have cleared the Google Product Category', $msg);
    }

    /**
     * Test the private get_default_google_product_category_modal_buttons method
     */
    public function test_get_default_google_product_category_modal_buttons() {
        $reflection = new \ReflectionClass($this->product_sync);
        $method = $reflection->getMethod('get_default_google_product_category_modal_buttons');
        $method->setAccessible(true);

        // Call the private method
        $html = $method->invoke($this->product_sync);

        $this->assertIsString($html);
        $this->assertStringContainsString('button', $html);
        $this->assertStringContainsString('Update default Google product category', $html);
    }

    /**
     * Test that save is callable (integration test would be needed for full effect)
     */
    public function test_save_is_callable() {
        try {
            // Should not throw even if dependencies are not fully mocked
            $this->product_sync->save();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('save() should not throw, got: ' . $e->getMessage());
        }
    }
} 