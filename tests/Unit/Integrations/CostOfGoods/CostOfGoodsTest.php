<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for CostsOfGoods class.
 *
 * @since 3.5.2
 */
class CostOfGoodsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function setUp(): void {
		// Mock providers
		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->willReturn( 10 );
		
		// Patch get_cogs_providers to return our mock
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([$cogs_provider_mock]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);
	}

	public function testCalculateCogsForProductsReturnsSum() {
		$product1 = $this->createMock(stdClass::class);
		$product2 = $this->createMock(stdClass::class);

		$result = CostOfGoods::calculate_cogs_for_products([$product1, $product2]);
		
		$this->assertEquals(20, $result);
	}

	public function Given_Cogs_Exists_for_Product_When_calculate_method_is_called_Then_it_returns_correct_value()
	{
		$this->assertFalse(true);
	}

	public function Given_Cogs_Does_Not_Exist_for_Product_When_calculate_method_is_called_Then_it_returns_false()
	{
		$this->assertFalse(true);
	}

	public function Given_cogs_doesnt_exist_for_one_product_in_an_order_When_calculate_method_is_called_Then_false_is_returned()
	{

		$this->assertFalse(true);
	}

	public function testCalculateCogsForProductsReturnsFalseIfProviderNotAvailable() {
		// Patch get_cogs_providers to return empty
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);
		
		$product = $this->createMock(stdClass::class);
		$result = CostOfGoods::calculate_cogs_for_products([$product]);

		$this->assertFalse($result);
	}

	public function testGetSupportedIntegrationsReturnsArray() {

		$expected = [
			'WooC'      => 'WooCCogsProvider',
			'WPFactory' => 'WPFactoryCogsProvider',
		];

		$this->assertEquals($expected, CostOfGoods::get_supported_integrations());
	}
}
