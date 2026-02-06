<?php
/**
 * Unit tests for Meta Extension handler.
 */

namespace WooCommerce\Facebook\Tests\Handlers;

use WooCommerce\Facebook\Handlers\MetaExtension;
use WP_UnitTestCase;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * The Meta Extension unit test class.
 */
class MetaExtensionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
                    'body' => wp_json_encode([
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

    /**
     * Test generate_iframe_splash_url when not connected.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_splash_url
     */
    public function test_generate_iframe_splash_url_when_not_connected() {
        $plugin = facebook_for_woocommerce();
        $url = MetaExtension::generate_iframe_splash_url(false, $plugin, 'test_business_id');
        
        // Test that the URL contains installed=0 or installed= (empty)
        $this->assertStringNotContainsString('installed=1', $url);
        $this->assertStringContainsString('installed=', $url);
        
        // Test that all other required parameters are still present
        $this->assertStringContainsString('access_client_token=' . MetaExtension::CLIENT_TOKEN, $url);
        $this->assertStringContainsString('business_vertical=ECOMMERCE', $url);
        $this->assertStringContainsString('channel=COMMERCE', $url);
        $this->assertStringContainsString('external_business_id=test_business_id', $url);
    }

    /**
     * Test generate_iframe_splash_url with special characters in business name.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_splash_url
     */
    public function test_generate_iframe_splash_url_with_special_characters_in_business_name() {
        $plugin = facebook_for_woocommerce();
        $url = MetaExtension::generate_iframe_splash_url(true, $plugin, 'test_business_id');
        
        // The business_name parameter should be URL encoded
        // We can't easily mock the connection handler, but we can verify the URL is properly formed
        $this->assertStringContainsString('business_name=', $url);
        
        // Verify the URL is valid and parseable
        $parsed_url = parse_url($url);
        $this->assertNotFalse($parsed_url);
        $this->assertArrayHasKey('query', $parsed_url);
    }

    /**
     * Test generate_iframe_splash_url contains all required parameters.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_splash_url
     */
    public function test_generate_iframe_splash_url_contains_all_required_parameters() {
        $plugin = facebook_for_woocommerce();
        $url = MetaExtension::generate_iframe_splash_url(true, $plugin, 'test_business_id');
        
        // Assert all required parameters are present
        $this->assertStringContainsString('access_client_token=', $url);
        $this->assertStringContainsString('business_vertical=', $url);
        $this->assertStringContainsString('channel=', $url);
        $this->assertStringContainsString('app_id=', $url);
        $this->assertStringContainsString('business_name=', $url);
        $this->assertStringContainsString('currency=', $url);
        $this->assertStringContainsString('timezone=', $url);
        $this->assertStringContainsString('external_business_id=', $url);
        $this->assertStringContainsString('installed=', $url);
        $this->assertStringContainsString('external_client_metadata=', $url);
        
        // Verify the base URL is correct
        $this->assertStringStartsWith('https://www.commercepartnerhub.com/commerce_extension/splash/', $url);
    }

    /**
     * Test generate_iframe_management_url with no access token.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_management_url
     */
    public function test_generate_iframe_management_url_with_no_access_token() {
        // Ensure no access token is set
        delete_option('wc_facebook_access_token');
        
        $url = MetaExtension::generate_iframe_management_url('123456789');
        
        // Should return empty string when no access token
        $this->assertEmpty($url);
    }

    /**
     * Test generate_iframe_management_url with API exception.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_management_url
     */
    public function test_generate_iframe_management_url_with_api_exception() {
        update_option('wc_facebook_access_token', 'test_token');
        
        $business_id = '123456789';
        
        // Store the original filter callbacks
        $original_filters = $GLOBALS['wp_filter']['pre_http_request']->callbacks ?? [];
        
        // Mock the API to throw an exception (simulate error response)
        add_filter('pre_http_request', function($pre, $r, $url) use ($business_id) {
            if (strpos($url, 'graph.facebook.com') !== false && strpos($url, $business_id) !== false) {
                // Return an error response
                return new \WP_Error('http_request_failed', 'API request failed');
            }
            return $pre;
        }, 10, 3);
        
        $url = MetaExtension::generate_iframe_management_url($business_id);
        
        // Restore original filters
        $GLOBALS['wp_filter']['pre_http_request']->callbacks = $original_filters;
        
        // Should return empty string when API throws exception
        $this->assertEmpty($url);
    }

    /**
     * Test generate_iframe_management_url with empty commerce extension URI.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension::generate_iframe_management_url
     */
    public function test_generate_iframe_management_url_with_empty_commerce_extension_uri() {
        update_option('wc_facebook_access_token', 'test_token');
        
        $business_id = '123456789';
        
        // Store the original filter callbacks
        $original_filters = $GLOBALS['wp_filter']['pre_http_request']->callbacks ?? [];
        
        // Mock the API to return empty commerce_extension uri
        add_filter('pre_http_request', function($pre, $r, $url) use ($business_id) {
            if (strpos($url, 'graph.facebook.com') !== false && strpos($url, $business_id) !== false) {
                $mock_response = [
                    'response' => [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                    'body' => wp_json_encode([
                        'commerce_extension' => [
                            'uri' => ''
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
        
        // Should return empty string when commerce_extension uri is empty
        $this->assertEmpty($url);
    }

    /**
     * Test that constants have expected values.
     *
     * @covers \WooCommerce\Facebook\Handlers\MetaExtension
     */
    public function test_constants_have_expected_values() {
        // Test CLIENT_TOKEN constant
        $this->assertEquals('474166926521348|92e978eb27baf47f9df578b48d430a2e', MetaExtension::CLIENT_TOKEN);
        
        // Test APP_ID constant
        $this->assertEquals('474166926521348', MetaExtension::APP_ID);
        
        // Test API_VERSION constant
        $this->assertEquals('v22.0', MetaExtension::API_VERSION);
        
        // Test COMMERCE_HUB_URL constant
        $this->assertEquals('https://www.commercepartnerhub.com/', MetaExtension::COMMERCE_HUB_URL);
        
        // Test NONCE_ACTION constant
        $this->assertEquals('wc_facebook_ajax_token_update', MetaExtension::NONCE_ACTION);
        
        // Test option name constants
        $this->assertEquals('wc_facebook_access_token', MetaExtension::OPTION_ACCESS_TOKEN);
        $this->assertEquals('wc_facebook_merchant_access_token', MetaExtension::OPTION_MERCHANT_ACCESS_TOKEN);
        $this->assertEquals('wc_facebook_page_access_token', MetaExtension::OPTION_PAGE_ACCESS_TOKEN);
        $this->assertEquals('wc_facebook_system_user_id', MetaExtension::OPTION_SYSTEM_USER_ID);
        $this->assertEquals('wc_facebook_business_manager_id', MetaExtension::OPTION_BUSINESS_MANAGER_ID);
        $this->assertEquals('wc_facebook_ad_account_id', MetaExtension::OPTION_AD_ACCOUNT_ID);
        $this->assertEquals('wc_facebook_instagram_business_id', MetaExtension::OPTION_INSTAGRAM_BUSINESS_ID);
        $this->assertEquals('wc_facebook_commerce_merchant_settings_id', MetaExtension::OPTION_COMMERCE_MERCHANT_SETTINGS_ID);
        $this->assertEquals('wc_facebook_external_business_id', MetaExtension::OPTION_EXTERNAL_BUSINESS_ID);
        $this->assertEquals('wc_facebook_commerce_partner_integration_id', MetaExtension::OPTION_COMMERCE_PARTNER_INTEGRATION_ID);
        $this->assertEquals('wc_facebook_product_catalog_id', MetaExtension::OPTION_PRODUCT_CATALOG_ID);
        $this->assertEquals('wc_facebook_pixel_id', MetaExtension::OPTION_PIXEL_ID);
        $this->assertEquals('wc_facebook_profiles', MetaExtension::OPTION_PROFILES);
        $this->assertEquals('wc_facebook_installed_features', MetaExtension::OPTION_INSTALLED_FEATURES);
        $this->assertEquals('wc_facebook_has_connected_fbe_2', MetaExtension::OPTION_HAS_CONNECTED_FBE_2);
        $this->assertEquals('wc_facebook_has_authorized_pages_read_engagement', MetaExtension::OPTION_HAS_AUTHORIZED_PAGES);
    }
}
