<?php
namespace WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Admin\Settings_Screens\Connection;

/**
 * Class ConnectionTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class ConnectionTest extends TestCase {

    /**
     * Helper method to invoke private/protected methods
     *
     * @param object $object     Object instance
     * @param string $methodName Method name to call
     * @param array  $parameters Parameters to pass into method
     *
     * @return mixed Method return value
     */
    private function invoke_method($object, $methodName, array $parameters = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test that render method calls render_facebook_iframe when enhanced onboarding is enabled
     */
    public function test_render_facebook_box_iframe() {
        // Create a partial mock of the facebook_for_woocommerce() class
        $fb_for_wc = $this->getMockBuilder(WC_Facebookcommerce::class)
            ->onlyMethods(['use_enhanced_onboarding'])
            ->getMock();

        // Configure the mock to return true for use_enhanced_onboarding
	    $fb_for_wc->expects($this->once())
            ->method('use_enhanced_onboarding')
            ->willReturn(true);

        // Start output buffering to capture the render output
	    $connection = new Connection($fb_for_wc);
        ob_start();
        $connection->render();
        $output = ob_get_clean();

        // Since we can't directly test the private render_facebook_iframe method,
        // we'll verify that the render method doesn't output the legacy Facebook box
        // when enhanced onboarding is enabled
        $this->assertStringNotContainsString('wc-facebook-connection-box', $output);
    }

	/**
	 * Test that render method calls render_facebook_iframe when enhanced onboarding is disabled
	 */
	public function test_render_facebook_box_legacy() {
		// Create a partial mock of the facebook_for_woocommerce() class
		$fb_for_wc = $this->getMockBuilder(WC_Facebookcommerce::class)
		                  ->onlyMethods(['use_enhanced_onboarding'])
		                  ->getMock();

		// Configure the mock to return false for use_enhanced_onboarding
		$fb_for_wc->expects($this->once())
		          ->method('use_enhanced_onboarding')
		          ->willReturn(false);

		// Start output buffering to capture the render output
		$connection = new Connection($fb_for_wc);
		ob_start();
		$connection->render();
		$output = ob_get_clean();

		// Since we can't directly test the private render_facebook_iframe method,
		// we'll verify that the render method doesn't output the legacy Facebook box
		// when enhanced onboarding is enabled
		$this->assertStringContainsString('wc-facebook-connection-box', $output);
	}

    /**
     * Test that render_message_handler outputs the expected JavaScript
     */
    public function test_render_message_handler() {
        // Create a mock of the Connection class
        $connection_mock = $this->getMockBuilder(Connection::class)
	        ->setConstructorArgs([facebook_for_woocommerce()])
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();

        // Configure the mock to return true for is_current_screen_page
        $connection_mock->method('is_current_screen_page')
            ->willReturn(true);

        // Call the method
        $output = $connection_mock->generate_inline_enhanced_onboarding_script();

        // Assert JavaScript event listeners and handlers
        $this->assertStringContainsString('window.addEventListener(\'message\'', $output);
        $this->assertStringContainsString('CommerceExtension::INSTALL', $output);
        $this->assertStringContainsString('CommerceExtension::RESIZE', $output);
        $this->assertStringContainsString('CommerceExtension::UNINSTALL', $output);

        // Assert fetch request setup - check for wpApiSettings.root instead of hardcoded path
        $this->assertStringContainsString('GeneratePluginAPIClient', $output);
        $this->assertStringContainsString('fbAPI.updateSettings', $output);
    }

    /**
     * Test that render_message_handler doesn't output when not on current screen
     */
    public function test_render_message_handler_not_current_screen() {
        $connection_mock = $this->getMockBuilder(Connection::class)
	        ->setConstructorArgs([facebook_for_woocommerce()])
	        ->onlyMethods(['is_current_screen_page'])
            ->getMock();

        $connection_mock->method('is_current_screen_page')
            ->willReturn(false);

        ob_start();
        $connection_mock->render_message_handler();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * Test that the management URL is used when merchant token exists
     */
    public function test_renders_management_url_based_on_merchant_token() {
		$connection = new Connection(facebook_for_woocommerce());

        // Set up the merchant token
        update_option('wc_facebook_merchant_access_token', 'test_token');

        // Use output buffering to capture the iframe HTML
        ob_start();
        $this->invoke_method($connection, 'render_facebook_iframe');
        $output = ob_get_clean();

        // Check that the iframe is rendered
        $this->assertStringContainsString('<iframe', $output);
        $this->assertStringContainsString('frameborder="0"', $output);
        $this->assertStringContainsString('id="facebook-commerce-iframe"', $output);
    }
}
