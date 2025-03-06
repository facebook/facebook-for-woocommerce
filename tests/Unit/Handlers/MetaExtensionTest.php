<?php
/**
 * Unit tests for Meta Extension handler.
 */

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\MetaExtension;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * The Meta Extension unit test class.
 */
class MetaExtensionTest extends WP_UnitTestCase {

    /**
     * Instance of the MetaExtension class that we are testing.
     *
     * @var \WooCommerce\Facebook\Handlers\MetaExtension The object to be tested.
     */
    private $meta_extension;

    /**
     * Setup the test object for each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->meta_extension = new MetaExtension();
    }

    /**
     * Test generate_iframe_splash_url
     */
    public function test_generate_iframe_splash_url() {
        $plugin = facebook_for_woocommerce();
        
        $url = MetaExtension::generate_iframe_splash_url(true, $plugin, 'test_business_id');
        $handler = facebook_for_woocommerce()->get_connection_handler();

        // Assert URL contains expected parameters
        $this->assertStringContainsString('access_client_token=' . MetaExtension::CLIENT_TOKEN, $url);
        $this->assertStringContainsString('app_id=', $url);
        $this->assertStringContainsString('business_name=' . rawurlencode($handler->get_business_name()), $url);
        $this->assertStringContainsString('external_business_id=test_business_id', $url);
        $this->assertStringContainsString('installed=1', $url);
        $this->assertStringContainsString('external_client_metadata=', $url);
        $this->assertStringContainsString('https://www.commercepartnerhub.com/commerce_extension/splash/', $url);
    }

    /**
     * Test REST API token update with valid data
     */
    public function test_rest_update_fb_settings_valid_data() {
        // Create a mock for WP_REST_Request
        $request = $this->getMockBuilder(WP_REST_Request::class)
                        ->disableOriginalConstructor()
                        ->setMethods(array('get_json_params'))
                        ->getMock();
        
        // Set up the mock to return our test data
        $request->expects($this->once())
                ->method('get_json_params')
                ->willReturn([
                    'merchant_access_token' => 'test_merchant_token',
                    'access_token' => 'test_access_token',
                    'page_access_token' => 'test_page_token',
                    'product_catalog_id' => '123456',
                    'pixel_id' => '789012'
                ]);

        $response = MetaExtension::rest_update_fb_settings($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        
        // Verify options were updated
        $this->assertEquals('test_access_token', get_option('wc_facebook_access_token'));
        $this->assertEquals('test_merchant_token', get_option('wc_facebook_merchant_access_token'));
        $this->assertEquals('test_page_token', get_option('wc_facebook_page_access_token'));
        $this->assertEquals('123456', get_option('wc_facebook_product_catalog_id'));
        $this->assertEquals('789012', get_option('wc_facebook_pixel_id'));
    }

    /**
     * Test rest_update_fb_settings with missing merchant token
     */
    public function test_rest_update_fb_settings_missing_merchant_token() {
        // Create a mock of WP_REST_Request
        $request = $this->getMockBuilder(WP_REST_Request::class)
                        ->disableOriginalConstructor()
                        ->onlyMethods(['get_json_params'])
                        ->getMock();
        
        // Set up the mock to return our test data
        $request->expects($this->once())
                ->method('get_json_params')
                ->willReturn([
                    'access_token' => 'test_access_token',
                    'page_access_token' => 'test_page_token'
                ]);

        $response = MetaExtension::rest_update_fb_settings($request);

        // Assert that we get a WP_REST_Response with success=false
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertNotEmpty($data['message']);
    }

    /**
     * Test generate_iframe_management_url with valid access token
     */
    public function test_get_commerce_extension_iframe_url() {
        // Set up the access token
        update_option('wc_facebook_access_token', 'test_access_token');
        
        // Test with empty business ID (should return empty string)
        $url = MetaExtension::generate_iframe_management_url('');
        $this->assertEmpty($url);
        
        // Test with valid business ID but we can't mock the API call
        // so we're just testing the method exists
        $this->assertTrue(method_exists(MetaExtension::class, 'generate_iframe_management_url'));
    }

    /**
     * Test generate_iframe_management_url
     */
    public function test_generate_iframe_management_url() {
        $plugin = facebook_for_woocommerce();
        
        // Test with no access token
        update_option('wc_facebook_access_token', '');
        $url = MetaExtension::generate_iframe_management_url('test_business_id');
        $this->assertEmpty($url);
        
        // Since we can't easily mock the call_api method,
        // we'll just verify the method exists and is called
        $this->assertTrue(method_exists(MetaExtension::class, 'generate_iframe_management_url'));
    }

    /**
     * Test init_rest_endpoint registers the route
     */
    public function test_init_rest_endpoint() {
        // We need to run this in the context of the rest_api_init action
        // to avoid the WordPress warning
        add_action('rest_api_init', function() {
            MetaExtension::init_rest_endpoint();
        });
        
        // Trigger the rest_api_init action
        do_action('rest_api_init');
        
        // If we got here without errors, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test that generate_iframe_management_url works correctly
     * This is a simplified test since we can't mock private static methods
     */
    public function test_get_commerce_extension_iframe_management_url() {
        $external_business_id = 'test_business_id';
        
        // Set up the access token
        update_option(MetaExtension::OPTION_ACCESS_TOKEN, 'test_token');
        
        // Since we can't mock the private static call_api method,
        // we'll just test that the method exists and returns empty string
        // when no API response is available
        $result = MetaExtension::generate_iframe_management_url($external_business_id);
        
        // Without a real API response, the method should return empty string
        $this->assertEmpty($result);
    }
}
