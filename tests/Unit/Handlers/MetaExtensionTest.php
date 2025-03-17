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
     * Test generate_iframe_management_url
     */
    public function test_generate_iframe_management_url() {
        // Set up the access token
        update_option('wc_facebook_access_token', 'test_access_token');
        
        // Test with empty business ID (should return empty string)
        $url = MetaExtension::generate_iframe_management_url('');
        $this->assertEmpty($url);
        
        // Test with valid business ID
        $business_id = '123456789';
        $expected_url = 'https://www.facebook.com/commerce/app/management/123456789/';
        
        // Add mock API response filter
        $this->add_mock_api_response_filter($business_id);
        
        $url = MetaExtension::generate_iframe_management_url($business_id);
        
        // Remove our filter
        remove_all_filters('pre_http_request');
        
        $this->assertEquals($expected_url, $url);
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
        
        // Check if the routes were registered
        $routes = rest_get_server()->get_routes();
        
        // Verify the update_fb_settings endpoint exists
        $this->assertArrayHasKey('/wc-facebook/v1/update_fb_settings', $routes);
        $this->assertArrayHasKey('POST', $routes['/wc-facebook/v1/update_fb_settings'][0]['methods']);
        
        // Just verify the permission callback and callback are set and callable
        $this->assertTrue(isset($routes['/wc-facebook/v1/update_fb_settings'][0]['callback']));
        $this->assertTrue(isset($routes['/wc-facebook/v1/update_fb_settings'][0]['permission_callback']));
        
        // Verify the uninstall endpoint exists
        $this->assertArrayHasKey('/wc-facebook/v1/uninstall', $routes);
        $this->assertArrayHasKey('POST', $routes['/wc-facebook/v1/uninstall'][0]['methods']);
        
        // Just verify the permission callback and callback are set and callable
        $this->assertTrue(isset($routes['/wc-facebook/v1/uninstall'][0]['callback']));
        $this->assertTrue(isset($routes['/wc-facebook/v1/uninstall'][0]['permission_callback']));
    }
    /**
     * Helper method to add a filter that mocks the Facebook API response
     *
     * @param string $business_id The business ID to use in the mock response
     */
    private function add_mock_api_response_filter($business_id) {
        add_filter('pre_http_request', function($preempt, $args, $url) use ($business_id) {
            // Check if this is the Facebook API call we want to mock
            if (strpos($url, 'graph.facebook.com') !== false && 
                strpos($url, 'fbe_business') !== false) {
                
                return [
                    'body' => json_encode([
                        'commerce_extension' => [
                            'uri' => "https://www.facebook.com/commerce/app/management/{$business_id}/"
                        ]
                    ]),
                    'response' => [
                        'code' => 200,
                        'message' => 'OK'
                    ],
                    'headers' => [],
                    'cookies' => [],
                    'filename' => null
                ];
            }
            
            // Not our API call, let WordPress handle it
            return $preempt;
        }, 10, 3);
    }
}
