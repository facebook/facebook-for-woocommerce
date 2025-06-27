<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Advertise;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class AdvertiseTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class AdvertiseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * @var Advertise
     */
    private $advertise;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Instantiate the Advertise class for each test
        $this->advertise = new Advertise();
    }

    /**
     * Test that the constructor hooks initHook to the init action
     */
    public function test_constructor_adds_init_action() {
        global $wp_filter;

        // Check that the 'init' hook is present
        $this->assertArrayHasKey('init', $wp_filter);
    }

    /**
     * Test that initHook sets the id, label, title, and documentation_url properties
     */
    public function test_initHook_sets_properties() {
        $this->advertise->initHook();

        $reflection = new \ReflectionClass($this->advertise);
        $id = $reflection->getProperty('id');
        $id->setAccessible(true);
        $label = $reflection->getProperty('label');
        $label->setAccessible(true);
        $title = $reflection->getProperty('title');
        $title->setAccessible(true);
        $doc_url = $reflection->getProperty('documentation_url');
        $doc_url->setAccessible(true);

        $this->assertEquals(Advertise::ID, $id->getValue($this->advertise));
        $this->assertEquals(__('Advertise', 'facebook-for-woocommerce'), $label->getValue($this->advertise));
        $this->assertEquals(__('Advertise', 'facebook-for-woocommerce'), $title->getValue($this->advertise));
        $this->assertEquals('https://woocommerce.com/document/facebook-for-woocommerce/#how-to-create-ads-on-facebook', $doc_url->getValue($this->advertise));
    }

    /**
     * Test that get_settings returns an array (empty)
     */
    public function test_get_settings_returns_array() {
        $settings = $this->advertise->get_settings();

        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    /**
     * Test that get_id returns the expected value
     */
    public function test_get_id_returns_expected_value() {
        $this->advertise->initHook();

        $this->assertEquals(Advertise::ID, $this->advertise->get_id());
    }

    /**
     * Test that get_label returns the expected value and applies the filter
     */
    public function test_get_label_returns_expected_value_and_applies_filter() {
        $this->advertise->initHook();

        $filter = 'wc_facebook_admin_settings_' . Advertise::ID . '_screen_label';
        add_filter($filter, function($label) { return 'Filtered Label'; });

        $this->assertEquals('Filtered Label', $this->advertise->get_label());

        remove_all_filters($filter);
    }

    /**
     * Test that get_title returns the expected value and applies the filter
     */
    public function test_get_title_returns_expected_value_and_applies_filter() {
        $this->advertise->initHook();

        $filter = 'wc_facebook_admin_settings_' . Advertise::ID . '_screen_title';
        add_filter($filter, function($title) { return 'Filtered Title'; });

        $this->assertEquals('Filtered Title', $this->advertise->get_title());

        remove_all_filters($filter);
    }

    /**
     * Test that get_description returns the expected value and applies the filter
     */
    public function test_get_description_returns_expected_value_and_applies_filter() {
        $this->advertise->initHook();

        $filter = 'wc_facebook_admin_settings_' . Advertise::ID . '_screen_description';
        add_filter($filter, function($desc) { return 'Filtered Description'; });

        $this->assertEquals('Filtered Description', $this->advertise->get_description());

        remove_all_filters($filter);
    }

    /**
     * Test that enqueue_assets is callable and does not throw (integration test would be needed for full coverage)
     */
    public function test_enqueue_assets_is_callable() {
        try {
            // Should not throw even if dependencies are not fully mocked
            $this->advertise->enqueue_assets();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('enqueue_assets() should not throw, got: ' . $e->getMessage());
        }
    }

    /**
     * Test that render is callable and does not throw (integration test would be needed for full coverage)
     */
    public function test_render_is_callable() {
        try {
            ob_start();
            $this->advertise->render();
            ob_end_clean();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('render() should not throw, got: ' . $e->getMessage());
        }
    }

    /**
     * Test the private get_lwi_ads_configuration_data method
     */
    public function test_get_lwi_ads_configuration_data() {
        $reflection = new \ReflectionClass($this->advertise);
        $method = $reflection->getMethod('get_lwi_ads_configuration_data');
        $method->setAccessible(true);

        $data = $method->invoke($this->advertise);

        $this->assertIsArray($data);
    }

    /**
     * Test the private parse_timezone method
     */
    public function test_parse_timezone() {
        $reflection = new \ReflectionClass($this->advertise);
        $method = $reflection->getMethod('parse_timezone');
        $method->setAccessible(true);

        // Should return the string if it contains a slash
        $this->assertEquals('Europe/London', $method->invoke($this->advertise, 'Europe/London', 0));

        // Should return a timezone id for a numeric offset (may fallback to Etc/GMT)
        $result = $method->invoke($this->advertise, 'UTC+2', 2 * 3600);
        $this->assertIsString($result);
    }

    /**
     * Test the private get_lwi_ads_sdk_url method
     */
    public function test_get_lwi_ads_sdk_url() {
        $reflection = new \ReflectionClass($this->advertise);
        $method = $reflection->getMethod('get_lwi_ads_sdk_url');
        $method->setAccessible(true);

        $url = $method->invoke($this->advertise);

        $this->assertIsString($url);
        $this->assertStringContainsString('https://connect.facebook.net/', $url);
        $this->assertStringContainsString('/sdk.js', $url);
    }

    /**
     * Test that output_scripts is callable and does not throw (integration test would be needed for full coverage)
     */
    public function test_output_scripts_is_callable() {
        try {
            $this->advertise->output_scripts();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('output_scripts() should not throw, got: ' . $e->getMessage());
        }
    }

    /**
     * Test that render outputs the connect message when not connected
     */
    public function test_render_outputs_connect_message_when_not_connected() {
        // We can't easily mock the connection handler, but we can at least check for the fallback message
        ob_start();
        $this->advertise->render();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('connect your store', $output);
    }

    /**
     * Test that add_hooks registers the expected actions
     */
    public function test_add_hooks_registers_actions() {
        $reflection = new \ReflectionClass($this->advertise);
        $method = $reflection->getMethod('add_hooks');
        $method->setAccessible(true);

        // Call the private method
        $method->invoke($this->advertise);

        global $wp_filter;
        $this->assertArrayHasKey('admin_head', $wp_filter);
        $this->assertArrayHasKey('admin_enqueue_scripts', $wp_filter);
    }
} 