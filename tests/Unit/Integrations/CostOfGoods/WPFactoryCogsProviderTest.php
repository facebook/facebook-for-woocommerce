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
		$wpfmock = $this->createMock( WPFactoryCogsProvider::class );
		$wpfmock->method('is_available')->willReturn(true);
		
		$cogsmock = $this->createMock( CostOfGoods::class );
		$cogsmock->method('get_cogs_providers')->willReturn([$wpfmock]);
		$this->assertFalse($cogsmock->calculate_cogs_for_products([]));
	}
	
	public function test_given_provider_is_unavailable_when_instantiated_then_exception_thrown() {
		$product = $this->createMock( WC_Product::class );
		$wpfmock = $this->createMock( WPFactoryCogsProvider::class );
		$wpfmock->method('is_available')->willReturn(false);
		try{
			$wpfmock->get_cogs_value($product);
			$this->assertFalse(true, 'Exception was expected but not thrown');
		} catch (IntegrationIsNotAvailableException $e) {
			$this->assertTrue(true, 'Exception was thrown properly');
		}
	}
}