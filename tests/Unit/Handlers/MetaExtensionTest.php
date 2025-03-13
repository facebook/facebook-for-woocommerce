<?php
/**
 * Unit tests for Meta Extension handler.
 */

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\MetaExtension;
use PHPUnit\Framework\TestCase;

/**
 * The Meta Extension unit test class.
 */
class MetaExtensionTest extends TestCase {

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
        
        // Test that the URL contains expected parameters
        $this->assertStringContainsString('access_client_token=' . MetaExtension::CLIENT_TOKEN, $url);
        $this->assertStringContainsString('business_vertical=ECOMMERCE', $url);
        $this->assertStringContainsString('channel=COMMERCE', $url);
        $this->assertStringContainsString('external_business_id=test_business_id', $url);
        $this->assertStringContainsString('installed=1', $url);
        $this->assertStringContainsString('https://www.commercepartnerhub.com/commerce_extension/splash/', $url);
    }

    /**
     * Test generate_iframe_management_url
     */
    public function test_generate_iframe_management_url() {
        update_option( 'wc_facebook_access_token', 'test_merchant_token' );
        
        // Test with empty business ID (should return empty string)
        $url = MetaExtension::generate_iframe_management_url('');
        $this->assertEmpty($url);
        
        // Test with valid business ID
        $business_id = '123456789';
        $expected_url = 'https://www.facebook.com/commerce/app/management/123456789/';
        
        // Store the original filter callbacks
        $original_filters = $GLOBALS['wp_filter']['pre_http_request']->callbacks ?? [];
        
        // Mock the API response using WordPress filters
        add_filter('pre_http_request', function($pre, $r, $url) use ($business_id, $expected_url) {
            // Only intercept calls to the Facebook API
            if (strpos($url, 'graph.facebook.com') !== false && strpos($url, $business_id) !== false) {
                // Create a mock response that the API class can process
                $mock_response = [
                    'response' => [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                    'body' => json_encode([
                        'commerce_extension' => [
                            'uri' => $expected_url
                        ]
                    ])
                ];
                return $mock_response;
            }
            return $pre;
        }, 10, 3);
        
        $url = MetaExtension::generate_iframe_management_url($business_id);
        
        // Restore original filters
        $GLOBALS['wp_filter']['pre_http_request']->callbacks = $original_filters;
        
        $this->assertEquals($expected_url, $url);
    }
}
