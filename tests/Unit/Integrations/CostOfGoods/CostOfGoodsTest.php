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

	public function given_no_cogs_providers_available_when_calculate_method_called_then_false_is_returned() {
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);

		$this->assertFalse(CostOfGoods::calculate_cogs_for_products([]));
	}

	public function given_cogs_provider_available_when_no_products_provided_then_false_is_returned() {
		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->willReturn( 10 );
		
		// Patch get_cogs_providers to return our mock
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([$cogs_provider_mock]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);

		$this->assertFalse(CostOfGoods::calculate_cogs_for_products([]));
	}

	public function given_cogs_provider_available_when_a_product_provided_then_cogs_is_returned() {
		$product = $this->createMock(stdClass::class);
		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->willReturn( 10 );
		
		// Patch get_cogs_providers to return our mock
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([$cogs_provider_mock]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);

		$this->assertEqual(CostOfGoods::calculate_cogs_for_products([$product]), 10);
	}

	public function given_cogs_provider_available_when_multiple_products_provided_and_all_have_cogs_then_sum_cogs_is_returned() {
		$product1 = $this->createMock(stdClass::class);
		$product2 = $this->createMock(stdClass::class);

		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->with($product1)->willReturn( 10 );
		$cogs_provider_mock->method( 'get_cogs_value' )->with($product2)->willReturn( 20 );
		
		// Patch get_cogs_providers to return our mock
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([$cogs_provider_mock]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);

		$this->assertEqual(CostOfGoods::calculate_cogs_for_products([$product1, $product2]), 30);
	}

	public function given_cogs_provider_available_when_multiple_products_provided_but_one_does_not_have_cogs_then_false_is_returned() {
		$product1 = $this->createMock(stdClass::class);
		$product2 = $this->createMock(stdClass::class);

		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
		$cogs_provider_mock->method( 'get_cogs_value' )->with($product1)->willReturn( 10 );
		$cogs_provider_mock->method( 'get_cogs_value' )->with($product2)->willReturn( 0 );
		
		// Patch get_cogs_providers to return our mock
		$reflection = new \ReflectionClass( CostOfGoods::class );
		$property = $reflection->getProperty('available_integrations');
		$property->setValue([$cogs_provider_mock]);
		$property = $reflection->getProperty('already_fetched');
		$property->setValue(true);

		$this->assertFalse(CostOfGoods::calculate_cogs_for_products([$product1, $product2]));
	}

	public function only_wooc_and_wpfactory_integrations_are_supported() {

		$expected = [
			'WooC'      => 'WooCCogsProvider',
			'WPFactory' => 'WPFactoryCogsProvider',
		];

		$this->assertEquals($expected, CostOfGoods::get_supported_integrations());
	}
}
