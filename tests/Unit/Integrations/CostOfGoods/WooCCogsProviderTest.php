<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WC_Product;
/**
 * Unit tests for WooCommerce CostsOfGoods class.
 *
 * @since 3.5.2
 */
class WooCCogsProviderTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function test_given_provider_is_unavailable_when_instantiated_then_exception_thrown() {
		$product = $this->createMock( WC_Product::class );
		$mock = $this->createMock( WooCCogsProvider::class );
		$mock->method('is_available')->willReturn(false);
		try{
			$mock->get_cogs_value($product);
			$this->assertFalse(true, 'Exception was expected but not thrown');
		} catch (IntegrationIsNotAvailableException $e) {
			$this->assertTrue(true, 'Exception was thrown properly');
		}
	}

	public function test_given_product_has_cogs_value_when_get_cogs_value_is_called_then_correct_value_returned() {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_cogs_total_value' )->willReturn( 10.0 );
		
		$mock = $this->createMock( WooCCogsProvider::class );
		$mock->method('is_available')->willReturn(true);
		
		$this->assertEquals(10.0, $mock->get_cogs_value($product));
	}
}
