<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\CostOfGoods\WPFactoryCogsProvider;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WPFactory CostsOfGoods class.
 *
 * @since 3.5.2
 */
class WPFactoryCogsProviderTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering
{
	public function setUp(): void
	{
		// Mock the global function alg_wc_cog
		if (!function_exists('alg_wc_cog')) {
			eval('function alg_wc_cog() {
				return (object)[
					"core" => (object)[
						"products" => (object)[
							"get_product_cost" => function($id) { return 42; }
						]
					]
				];
			}');
		}
	}
	
	public function testIsAvailableReturnsTrueWhenAlgWcCogExists()
	{
		$this->assertTrue(WPFactoryCogsProvider::is_available());
	}

	public function testConstructorThrowsExceptionIfNotAvailable()
	{
		// Temporarily remove alg_wc_cog
		runkit_function_remove('alg_wc_cog');
		$this->expectException(IntegrationIsNotAvailableException::class);
		new WPFactoryCogsProvider();
		// Restore alg_wc_cog
		$this->setUp();
	}

	public function testGetCogsValueReturnsProductCost()
	{
		$provider = new WPFactoryCogsProvider();
		$product = $this->createMock(stdClass::class);
		$product->method('get_id')->willReturn(123);
		// Patch alg_wc_cog()->core->products->get_product_cost to return a value
		
		$alg_wc_cog = alg_wc_cog();
		$alg_wc_cog->core->products->get_product_cost = function($id) {
			return 99.99;
		};
		
		// Use reflection to set the closure
		$alg_wc_cog->core->products->get_product_cost = function($id) {
			return 99.99;
		};

		// Simulate the call
		$result = $provider->get_cogs_value($product);
		$this->assertEquals(99.99, $result);
	}
}