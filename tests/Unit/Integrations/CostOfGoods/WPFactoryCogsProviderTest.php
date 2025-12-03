<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

if ( ! function_exists( 'alg_wc_cog' ) ) {
	$GLOBALS['alg_wc_cog']='alg_wc_cog';
	function alg_wc_cog() {
		$ret = new stdClass();
		$ret->core = new stdClass();
		$ret->core->products = new class {
			public function get_product_cost($p) {
				return $p->get_cogs_total_value();
			}
		};
		return $ret;
	}
}

use WooCommerce\Facebook\Integrations\CostOfGoods\WPFactoryCogsProvider;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;
use WC_Product;
use stdClass;

/**
 * Unit tests for WPFactory CostsOfGoods class.
 *
 * @since 3.5.2
 */
class WPFactoryCogsProviderTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering
{
	public function test_given_no_cogs_providers_available_when_calculate_method_called_then_false_is_returned() {
		$reflection = new \ReflectionClass( WPFactoryCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', true);
		
		$this->assertFalse(CostOfGoods::calculate_cogs_for_products([]));
	}
	
	public function test_given_provider_is_unavailable_when_instantiated_then_exception_thrown() {
		$reflection = new \ReflectionClass( WPFactoryCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', false);
		try{
			$reflection->newInstance();
			$this->assertFalse(true, 'Exception was expected but not thrown');
		} catch (IntegrationIsNotAvailableException $e) {
			$this->assertTrue(true, 'Exception was thrown properly');
		}
	}

	public function test_given_product_has_cogs_value_when_get_cogs_value_is_called_then_correct_value_returned() {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_cogs_total_value' )->willReturn( 10.0 );
		
		$reflection = new \ReflectionClass( WPFactoryCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', null);
		$instance = $reflection->newInstance();
		
		$value = $instance->get_cogs_value($product);
		$this->assertEquals(10.0, $value);
	}
}