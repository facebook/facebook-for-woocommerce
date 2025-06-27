<?php
namespace WooCommerce\Facebook\Tests\Admin;

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;

/**
 * Class Abstract_Settings_ScreenTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin
 */
class Abstract_Settings_ScreenTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * @var Abstract_Settings_Screen
     */
    private $screen;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Use an anonymous class as a concrete implementation for testing
        $this->screen = new class extends Abstract_Settings_Screen {
            public function __construct() {
                $this->id = 'test_screen';
                $this->label = 'Test Label';
                $this->title = 'Test Title';
                $this->description = 'Test Description';
                $this->documentation_url = 'https://example.com/docs';
            }
            public function get_settings(): array {
                return [
                    ['id' => 'setting_1', 'type' => 'text', 'default' => 'foo'],
                    ['type' => 'sectionend']
                ];
            }
        };
    }

    /**
     * Test get_id returns the expected value
     */
    public function test_get_id_returns_id() {
        // Assert the ID is as set in the test double
        $this->assertEquals('test_screen', $this->screen->get_id());
    }

    /**
     * Test get_label returns the expected value and applies the filter
     */
    public function test_get_label_returns_label_and_applies_filter() {
        $filter = 'wc_facebook_admin_settings_test_screen_screen_label';

        // Add a filter to override the label
        add_filter($filter, function($label) { return 'Filtered Label'; });

        $this->assertEquals('Filtered Label', $this->screen->get_label());

        // Clean up
        remove_all_filters($filter);
    }

    /**
     * Test get_title returns the expected value and applies the filter
     */
    public function test_get_title_returns_title_and_applies_filter() {
        $filter = 'wc_facebook_admin_settings_test_screen_screen_title';

        // Add a filter to override the title
        add_filter($filter, function($title) { return 'Filtered Title'; });

        $this->assertEquals('Filtered Title', $this->screen->get_title());

        // Clean up
        remove_all_filters($filter);
    }

    /**
     * Test get_description returns the expected value and applies the filter
     */
    public function test_get_description_returns_description_and_applies_filter() {
        $filter = 'wc_facebook_admin_settings_test_screen_screen_description';

        // Add a filter to override the description
        add_filter($filter, function($desc) { return 'Filtered Description'; });

        $this->assertEquals('Filtered Description', $this->screen->get_description());

        // Clean up
        remove_all_filters($filter);
    }

    /**
     * Test get_disconnected_message returns an empty string by default
     */
    public function test_get_disconnected_message_returns_empty_string() {
        // Should be empty by default
        $this->assertSame('', $this->screen->get_disconnected_message());
    }

    /**
     * Test get_settings returns the expected array
     */
    public function test_get_settings_returns_expected_array() {
        $settings = $this->screen->get_settings();

        // Should be an array with the expected structure
        $this->assertIsArray($settings);
        $this->assertEquals('setting_1', $settings[0]['id']);
        $this->assertEquals('sectionend', $settings[1]['type']);
    }

    /**
     * Test save calls woocommerce_update_options (smoke test)
     */
    public function test_save_calls_woocommerce_update_options() {
        // This test is mostly a smoke test, as the function is a wrapper
        try {
            $this->screen->save();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('save() should not throw, got: ' . $e->getMessage());
        }
    }

    /**
     * Test render returns early if get_settings returns empty
     */
    public function test_render_returns_early_if_settings_empty() {
        $screen = new class extends Abstract_Settings_Screen {
            public function get_settings(): array { return []; }
        };

        ob_start();
        $screen->render();
        $output = ob_get_clean();

        // Should not output anything
        $this->assertEmpty($output);
    }

    /**
     * Test render outputs form and actions if settings are present and connection is mocked as connected
     */
    public function test_render_outputs_form_when_connected() {
        $screen = $this->screen;

        // Mock global functions and handlers
        global $wp_filter;
        $wp_filter['wc_facebook_admin_test_screen_settings'] = [];

        // Mock plugin and WooCommerce functions if not already defined
        if (!function_exists('facebook_for_woocommerce')) {
            eval('function facebook_for_woocommerce() { return new class {
                public function get_connection_handler() {
                    return new class {
                        public function is_connected() { return true; }
                        public function has_previously_connected_fbe_1() { return false; }
                    };
                }
            }; }');
        }
        if (!function_exists('woocommerce_admin_fields')) {
            eval('function woocommerce_admin_fields($settings) { echo "<div>fields</div>"; }');
        }
        if (!function_exists('wp_nonce_field')) {
            eval('function wp_nonce_field() { echo "<input type=\"hidden\" name=\"_wpnonce\" value=\"abc\">"; }');
        }
        if (!function_exists('__')) {
            eval('function __($str) { return $str; }');
        }
        if (!function_exists('submit_button')) {
            eval('function submit_button() { echo "<button>Save changes</button>"; }');
        }

        ob_start();
        $screen->render();
        $output = ob_get_clean();

        // Should output a form and the save button
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Save changes', $output);
    }

    /**
     * Test render outputs disconnected message if not connected and message is set
     */
    public function test_render_outputs_disconnected_message_when_not_connected() {
        $screen = new class extends Abstract_Settings_Screen {
            public function get_settings(): array {
                return [['id' => 'setting_1', 'type' => 'text', 'default' => 'foo']];
            }
            public function get_disconnected_message() { return 'Disconnected!'; }
        };

        // Mock plugin and WooCommerce functions if not already defined
        if (!function_exists('facebook_for_woocommerce')) {
            eval('function facebook_for_woocommerce() { return new class {
                public function get_connection_handler() {
                    return new class {
                        public function is_connected() { return false; }
                        public function has_previously_connected_fbe_1() { return false; }
                    };
                }
            }; }');
        }
        if (!function_exists('woocommerce_admin_fields')) {
            eval('function woocommerce_admin_fields($settings) { echo "<div>fields</div>"; }');
        }

        ob_start();
        $screen->render();
        $output = ob_get_clean();

        // Should output the disconnected message
        $this->assertStringContainsString('Disconnected!', $output);
    }

    /**
     * Test protected is_current_screen_page returns false when page does not match
     */
    public function test_is_current_screen_page_returns_false() {
        $reflection = new \ReflectionClass($this->screen);
        $method = $reflection->getMethod('is_current_screen_page');
        $method->setAccessible(true);

        // Mock Settings and Helper classes if not already defined
        if (!class_exists('WooCommerce\\Facebook\\Admin\\Settings')) {
            eval('namespace WooCommerce\\Facebook\\Admin; class Settings { const PAGE_ID = "fb_page"; }');
        }
        if (!class_exists('WooCommerce\\Facebook\\Framework\\Helper')) {
            eval('namespace WooCommerce\\Facebook\\Framework; class Helper { public static function get_requested_value($key, $default = null) { return "other_page"; } }');
        }

        $result = $method->invoke($this->screen);

        // Should return false since the page does not match
        $this->assertFalse($result);
    }

    /**
     * Test protected maybe_render_learn_more_link outputs link if documentation_url is set
     */
    public function test_maybe_render_learn_more_link_outputs_link() {
        $reflection = new \ReflectionClass($this->screen);
        $method = $reflection->getMethod('maybe_render_learn_more_link');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($this->screen, 'Test Label');
        $output = ob_get_clean();

        // Should output the learn more link
        $this->assertStringContainsString('Learn more about', $output);
        $this->assertStringContainsString('example.com/docs', $output);
    }

    /**
     * Test protected maybe_render_learn_more_link outputs nothing if documentation_url is empty
     */
    public function test_maybe_render_learn_more_link_outputs_nothing_if_no_url() {
        $screen = new class extends Abstract_Settings_Screen {
            public function get_settings(): array { return []; }
            public function __construct() { $this->documentation_url = ''; }
        };
        $reflection = new \ReflectionClass($screen);
        $method = $reflection->getMethod('maybe_render_learn_more_link');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($screen, 'Test Label');
        $output = ob_get_clean();

        // Should not output anything
        $this->assertEmpty($output);
    }

    /**
     * Test get_id, get_label, get_title, get_description with empty/null values
     */
    public function test_getters_with_empty_values() {
        $screen = new class extends Abstract_Settings_Screen {
            public function get_settings(): array { return []; }
            public function __construct() { $this->id = null; $this->label = null; $this->title = null; $this->description = null; }
        };

        // Should handle nulls gracefully
        $this->assertNull($screen->get_id());
        $this->assertSame('', $screen->get_label());
        $this->assertSame('', $screen->get_title());
        $this->assertSame('', $screen->get_description());
    }

    /**
     * Test get_settings returns empty array edge case
     */
    public function test_get_settings_returns_empty_array() {
        $screen = new class extends Abstract_Settings_Screen {
            public function get_settings(): array { return []; }
        };

        // Should return an empty array
        $this->assertIsArray($screen->get_settings());
        $this->assertEmpty($screen->get_settings());
    }
} 