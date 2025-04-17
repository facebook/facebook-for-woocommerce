<?php
/**
 * Unit tests for REST API functionality.
 */

namespace WooCommerce\Facebook\Tests\Api\REST;

use WooCommerce\Facebook\API\Plugin\InitializeRestAPI;
use WooCommerce\Facebook\API\Plugin\Settings\Handler;
use WooCommerce\Facebook\API\Plugin\Settings\Update\Request as UpdateRequest;
use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * The REST API unit test class.
 */
class RestAPITest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * Test REST API routes are registered
     */
    public function test_rest_routes_are_registered() {
        $routes = rest_get_server()->get_routes();
        // Verify the update endpoint exists
        $this->assertArrayHasKey('/wc-facebook/v1/settings/update', $routes);
        // Verify the uninstall endpoint exists
        $this->assertArrayHasKey('/wc-facebook/v1/settings/uninstall', $routes);
    }

    /**
     * Test settings update with valid data using reflection
     */
    public function test_settings_update_succeeds_with_valid_data() {
        // Skip this test if the Handler class doesn't exist
        if (!class_exists(Handler::class)) {
            $this->markTestSkipped('Handler class not found');
            return;
        }

        // Mock the update_option function
        if (!function_exists('update_option')) {
            function update_option($option, $value) {
                global $wp_options;
                if (!isset($wp_options)) {
                    $wp_options = [];
                }
                $wp_options[$option] = $value;
                return true;
            }
        }

        // Mock the get_option function
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                global $wp_options;
                if (!isset($wp_options)) {
                    $wp_options = [];
                }
                return isset($wp_options[$option]) ? $wp_options[$option] : $default;
            }
        }

        // Mock the wc_bool_to_string function
        if (!function_exists('wc_bool_to_string')) {
            function wc_bool_to_string($bool) {
                return $bool ? 'yes' : 'no';
            }
        }

        // Create a handler instance
        $handler = new Handler();

        // Create test data
        $test_data = [
            'merchant_access_token' => 'test_merchant_token',
            'access_token' => 'test_access_token',
            'page_access_token' => 'test_page_token',
            'product_catalog_id' => '123456',
            'pixel_id' => '789012',
            'msger_chat' => 'yes',
            'commerce_merchant_settings_id' => '789012',
            'commerce_partner_integration_id' => '12311344'
        ];

        // Use reflection to access private methods
        $reflection = new \ReflectionClass(Handler::class);

        // Test map_params_to_options
        $map_params_method = $reflection->getMethod('map_params_to_options');
        $map_params_method->setAccessible(true);
        $options = $map_params_method->invokeArgs($handler, [$test_data]);

        // Verify options are mapped correctly
        $this->assertEquals('test_access_token', $options['wc_facebook_access_token']);
        $this->assertEquals('test_merchant_token', $options['wc_facebook_merchant_access_token']);
        $this->assertEquals('123456', $options['wc_facebook_product_catalog_id']);
        $this->assertEquals('789012', $options['wc_facebook_commerce_merchant_settings_id']);

        // Test update_settings
        $update_settings_method = $reflection->getMethod('update_settings');
        $update_settings_method->setAccessible(true);
        $update_settings_method->invokeArgs($handler, [$options]);

        // Test update_connection_status
        $update_connection_method = $reflection->getMethod('update_connection_status');
        $update_connection_method->setAccessible(true);
        $update_connection_method->invokeArgs($handler, [$test_data]);

        // Verify options were updated
        $this->assertEquals('test_access_token', get_option('wc_facebook_access_token'));
        $this->assertEquals('test_merchant_token', get_option('wc_facebook_merchant_access_token'));
        $this->assertEquals('123456', get_option('wc_facebook_product_catalog_id'));
        $this->assertEquals('789012', get_option('wc_facebook_pixel_id'));
        $this->assertEquals('yes', get_option('wc_facebook_has_connected_fbe_2'));
        $this->assertEquals('yes', get_option('wc_facebook_has_authorized_pages_read_engagement'));
        $this->assertEquals('yes', get_option('wc_facebook_enable_messenger'));
    }

    /**
     * Test settings update with missing merchant token
     */
    public function test_settings_update_fails_if_missing_merchant_token() {
        // Skip this test if the UpdateRequest class doesn't exist
        if (!class_exists(UpdateRequest::class)) {
            $this->markTestSkipped('UpdateRequest class not found');
            return;
        }

        // Create a mock WP_REST_Request
        $request = $this->createMock('WP_REST_Request');

        // Set up the mock to return our test data
        $request->method('get_json_params')
            ->willReturn([
                'access_token' => 'test_access_token',
                // Missing merchant_access_token
            ]);

        $request->method('get_params')
            ->willReturn([
                'access_token' => 'test_access_token',
                // Missing merchant_access_token
            ]);

        // Create an UpdateRequest instance
        $update_request = new UpdateRequest($request);

        // Test the validate method
        $validation_result = $update_request->validate();

        // Check that validation fails with the expected error
        $this->assertInstanceOf('WP_Error', $validation_result);
        $this->assertEquals('missing_merchant_token', $validation_result->get_error_code());

    }

    /**
     * Test JS API definitions match classes using JS_Exposable trait
     */
    public function test_js_exposable_classes_match_api_definitions() {

        // Create a partial mock of InitializeRestAPI
        $initializeRestAPI = $this->getMockBuilder(InitializeRestAPI::class)
                                  ->onlyMethods(['should_generate_rest_framework'])
                                  ->getMock();

        // Force should_generate_rest_framework to return true
        $initializeRestAPI->method('should_generate_rest_framework')
                         ->willReturn(true);

        // Get API definitions
        $api_definitions = $initializeRestAPI->get_api_definitions();

        // Find all classes using the JS_Exposable trait
        $js_exposable_classes = [];

        // Get all declared classes
        $all_classes = get_declared_classes();

        foreach ($all_classes as $class) {
            // Skip if not in the WooCommerce\Facebook namespace
            if (strpos($class, 'WooCommerce\\Facebook\\') !== 0) {
                continue;
            }

            // Use reflection to check if class uses the JS_Exposable trait
            $reflection = new \ReflectionClass($class);
            $traits = $reflection->getTraitNames();

            if (in_array('WooCommerce\\Facebook\\API\\Plugin\\Traits\\JS_Exposable', $traits)) {
                // Skip abstract classes
                if ($reflection->isAbstract()) {
                    continue;
                }

                try {
                    // Create a mock WP_REST_Request for constructor
                    $request = $this->createMock('WP_REST_Request');

                    // Instantiate the class
                    $instance = new $class($request);

                    // Get the JS function name
                    if (method_exists($instance, 'get_js_function_name')) {
                        $function_name = $instance->get_js_function_name();
                        $js_exposable_classes[$function_name] = $class;
                    }
                } catch (\Exception $e) {
                    // Log error but continue
                    echo "Error instantiating $class: " . $e->getMessage() . "\n";
                }
            }
        }

        // Verify each API definition has a corresponding class
        foreach ($api_definitions as $function_name => $definition) {
            $this->assertArrayHasKey($function_name, $js_exposable_classes,
                "API definition '$function_name' has no corresponding JS_Exposable class");
        }

        // Verify each JS exposable class has a corresponding API definition
        foreach ($js_exposable_classes as $function_name => $class) {
            $this->assertArrayHasKey($function_name, $api_definitions,
                "JS_Exposable class '$class' with function name '$function_name' has no corresponding API definition");
        }
    }

	/**
	 * Test that options set in a previous test are reset due to isolation.
	 */
	public function test_options_are_reset_after_previous_test() {
		// These options were set in test_settings_update_succeeds_with_valid_data
		// Due to setUp/tearDown isolation, they should now return their default value (false)
		$this->assertFalse( get_option('wc_facebook_access_token'), 'Option wc_facebook_access_token should be reset.' );
		$this->assertFalse( get_option('wc_facebook_merchant_access_token'), 'Option wc_facebook_merchant_access_token should be reset.' );
		$this->assertFalse( get_option('wc_facebook_product_catalog_id'), 'Option wc_facebook_product_catalog_id should be reset.' );
		$this->assertFalse( get_option('wc_facebook_pixel_id'), 'Option wc_facebook_pixel_id should be reset.' );
		$this->assertFalse( get_option('wc_facebook_has_connected_fbe_2'), 'Option wc_facebook_has_connected_fbe_2 should be reset.' );
		$this->assertFalse( get_option('wc_facebook_has_authorized_pages_read_engagement'), 'Option wc_facebook_has_authorized_pages_read_engagement should be reset.' );
		$this->assertFalse( get_option('wc_facebook_enable_messenger'), 'Option wc_facebook_enable_messenger should be reset.' );
	}
}
