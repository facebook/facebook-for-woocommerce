<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\CostOfGoods\AbstractCogsProvider;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WC_Product;
/**
 * Unit tests for CostsOfGoods class.
 *
 * @since 3.5.2
 */
class CostOfGoodsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function test_given_no_cogs_providers_available_when_calculate_method_called_then_false_is_returned() {
		$mock = $this->getMockBuilder(CostOfGoods::class)
		->onlyMethods(['get_cogs_providers'])
		->disableOriginalConstructor()
		->getMock();
		// $mock = $this->createMock( CostOfGoods::class );
		$mock->method('get_cogs_providers')->willReturn([]);
		
		$this->assertFalse($mock->calculate_cogs_for_products([]));
	}

	public function test_given_cogs_provider_available_when_no_products_provided_then_false_is_returned() {
		
		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->willReturn( 10.0 );

		$mock = $this->createMock( CostOfGoods::class );
		$mock->method('get_cogs_providers')->willReturn([$cogs_provider_mock]);
		
		$this->assertFalse($mock->calculate_cogs_for_products([]));
	}

	public function test_given_cogs_provider_available_when_returned_cogs_value_is_zero_then_false_is_returned() {
		$product = $this->createMock(WC_Product::class);
		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->willReturn( 0 );
		
		$mock = $this->createMock( CostOfGoods::class );
		$mock->method('get_cogs_providers')->willReturn([$cogs_provider_mock]);
		
		$this->assertFalse($mock->calculate_cogs_for_products([$product]));
	}

	public function test_given_cogs_provider_available_when_a_product_provided_then_cogs_is_returned() {
		$product = $this->createMock(WC_Product::class);
		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->willReturn( 10.0 );
		
		$mock = $this->createMock( CostOfGoods::class );
		$mock->method('get_cogs_providers')->willReturn([$cogs_provider_mock]);

		$this->assertEquals(10.0, $mock->calculate_cogs_for_products([$product]));
	}

	public function only_wooc_and_wpfactory_integrations_are_supported() {
		$expected = [
			'WooC'      => 'WooCCogsProvider',
			'WPFactory' => 'WPFactoryCogsProvider',
		];

		$this->assertEquals($expected, (new CostOfGoods())->get_supported_integrations());
	}
}
