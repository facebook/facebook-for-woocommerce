<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Admin\Settings_Screens\Connection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class ConnectionTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class ConnectionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
	 * Test the rendering of the Facebook connection box
	 */
	public function test_render_facebook_box() {
		// Start output buffering to capture the render output
		$connection = new Connection();
		ob_start();
		$connection->render();
		$output = ob_get_clean();

		$this->assertStringContainsString('wc-facebook-connection-box', $output);
	}
}
