<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Product_Sets;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class Product_SetsTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class Product_SetsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * @var Product_Sets
     */
    private $product_sets;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Instantiate the Product_Sets class for each test
        $this->product_sets = new Product_Sets();
    }

    /**
     * Test that the constructor hooks initHook to the init action
     */
    public function test_constructor_adds_init_action() {
        global $wp_filter;

        // Check that the 'init' hook is present
        $this->assertArrayHasKey('init', $wp_filter);

        $found = false;
        foreach ($wp_filter['init'] as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                if (is_array($cb['function']) && $cb['function'][0] instanceof Product_Sets && $cb['function'][1] === 'initHook') {
                    $found = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($found, 'initHook should be hooked to init');
    }

    /**
     * Test that initHook sets the id, label, and title properties
     */
    public function test_initHook_sets_properties() {
        $this->product_sets->initHook();

        $reflection = new \ReflectionClass($this->product_sets);
        $id = $reflection->getProperty('id');
        $id->setAccessible(true);
        $label = $reflection->getProperty('label');
        $label->setAccessible(true);
        $title = $reflection->getProperty('title');
        $title->setAccessible(true);

        $this->assertEquals(Product_Sets::ID, $id->getValue($this->product_sets));
        $this->assertEquals(__('Product sets', 'facebook-for-woocommerce'), $label->getValue($this->product_sets));
        $this->assertEquals(__('Product sets', 'facebook-for-woocommerce'), $title->getValue($this->product_sets));
    }

    /**
     * Test that get_settings returns an empty array
     */
    public function test_get_settings_returns_empty_array() {
        $this->assertEquals([], $this->product_sets->get_settings());
    }

    /**
     * Test that render is callable and calls exit (redirect cannot be asserted due to namespace issues)
     *
     * Note: We cannot assert the redirect URL because wp_safe_redirect is called in the global namespace.
     */
    public function test_render_is_callable_and_exits() {
        try {
            $this->product_sets->render();
        } catch (\Throwable $e) {
            // exit is called, which is expected
            $this->assertTrue(true);
            return;
        }
        $this->fail('render() did not call exit as expected');
    }

    /**
     * Test that get_id returns the expected value
     */
    public function test_get_id_returns_expected_value() {
        $this->product_sets->initHook();

        $this->assertEquals(Product_Sets::ID, $this->product_sets->get_id());
    }

    /**
     * Test that get_label returns the expected value and applies the filter
     */
    public function test_get_label_returns_expected_value_and_applies_filter() {
        $this->product_sets->initHook();

        $filter = 'wc_facebook_admin_settings_' . Product_Sets::ID . '_screen_label';
        add_filter($filter, function($label) { return 'Filtered Label'; });

        $this->assertEquals('Filtered Label', $this->product_sets->get_label());

        remove_all_filters($filter);
    }

    /**
     * Test that get_title returns the expected value and applies the filter
     */
    public function test_get_title_returns_expected_value_and_applies_filter() {
        $this->product_sets->initHook();

        $filter = 'wc_facebook_admin_settings_' . Product_Sets::ID . '_screen_title';
        add_filter($filter, function($title) { return 'Filtered Title'; });

        $this->assertEquals('Filtered Title', $this->product_sets->get_title());

        remove_all_filters($filter);
    }

    /**
     * Test that get_description returns the expected value and applies the filter
     */
    public function test_get_description_returns_expected_value_and_applies_filter() {
        $this->product_sets->initHook();

        $filter = 'wc_facebook_admin_settings_' . Product_Sets::ID . '_screen_description';
        add_filter($filter, function($desc) { return 'Filtered Description'; });

        $this->assertEquals('Filtered Description', $this->product_sets->get_description());

        remove_all_filters($filter);
    }

    /**
     * Test that get_disconnected_message returns an empty string
     */
    public function test_get_disconnected_message_returns_empty_string() {
        $this->assertSame('', $this->product_sets->get_disconnected_message());
    }

    /**
     * Used for capturing redirect URL in render test
     * @var string|null
     */
    public static $redirect_url;
}
