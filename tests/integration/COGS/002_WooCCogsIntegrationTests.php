<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\COGS\CogsIntegrationTestsBase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;

class WooCCogsIntegrationTests extends CogsIntegrationTestsBase
{
	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
	}

	public function test_given_wooc_cogs_is_disabled_when_wooc_provider_is_available_called_then_it_returns_false() {
		$this->disable_cogs_in_woo_settings();
		$instance = new WooCCogsProvider();
		$this->assertFalse($instance->is_available(), 'WooC COGS is expected to be disabled');
		$this->assertFalse(function_exists( 'get_option' ) && ( 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' ) ), 'woocommerce_feature_cost_of_goods_sold_enabled is expected to be disabled');
		$this->expectException(IntegrationIsNotAvailableException::class);
		$instance->get_cogs_value($this->create_simple_product());
	}

	public function test_given_wooc_cogs_is_enabled_when_wooc_provider_is_available_called_then_it_returns_true() {
		$this->enable_cogs_in_woo_settings();
		$this->assertTrue(function_exists( 'get_option' ) && ( 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' ) ), 'woocommerce_feature_cost_of_goods_sold_enabled is expected to be enabled');
		$this->assertTrue((new WooCCogsProvider())->is_available(), 'WooC COGS is expected to be enabled');
	}

	// Simple Product tests
	public function test_given_cogs_does_not_exist_for_simple_product_when_calculate_method_is_called_then_it_returns_false()
	{
		$this->enable_cogs_in_woo_settings();
		$product = $this->create_simple_product();
		
		$this->assertEquals(0, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$value = (new CostOfGoods(array('WooC' => 'WooCCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]);
		$this->assertEquals(false, $value);
	}

	public function test_given_cogs_exists_for_simple_product_when_calculate_method_is_called_then_it_returns_correct_value()
	{
		$this->enable_cogs_in_woo_settings();
		$cogs_value = 100.0;

		$product = $this->create_simple_product();
		$product->set_cogs_value($cogs_value);
		$product->save();

		$this->assertEquals($cogs_value, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$value = (new CostOfGoods(array('WooC' => 'WooCCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]);
		$this->assertEquals($cogs_value, $value);
	}

	public function test_given_cogs_provider_available_when_multiple_simple_products_provided_and_all_have_cogs_then_sum_cogs_is_returned() {
		$this->enable_cogs_in_woo_settings();

		$product1_cogs_value = 100.0;
		$product2_cogs_value = 150.0;

		$product1 = $this->create_simple_product();
		$product1->set_cogs_value($product1_cogs_value);
		$product1->save();

		$product2 = $this->create_simple_product();
		$product2->set_cogs_value($product2_cogs_value);
		$product2->save();

		$this->assertEquals($product1_cogs_value, $product1->get_cogs_total_value());
		$this->assertEquals($product2_cogs_value, $product2->get_cogs_total_value());

		$value = (new CostOfGoods(array('WooC' => 'WooCCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product1,1), $this->create_cogs_data_input($product2,1)]);
		$this->assertEquals($product1_cogs_value + $product2_cogs_value, $value);
	}

	public function test_given_cogs_provider_available_when_multiple_simple_products_provided_but_one_does_not_have_cogs_then_false_is_returned() {
		$this->enable_cogs_in_woo_settings();

		$product1_cogs_value = 100.0;
		
		$product1 = $this->create_simple_product();
		$product1->set_cogs_value($product1_cogs_value);
		$product1->save();

		$product2 = $this->create_simple_product();

		$this->assertEquals($product1_cogs_value, $product1->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');
		$this->assertEquals(0, $product2->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$value = (new CostOfGoods(array('WooC' => 'WooCCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product1,1), $this->create_cogs_data_input($product2,1)]);
		$this->assertEquals(false, $value);
	}

	// Variable Products tests
	public function test_given_cogs_does_not_exist_for_variable_product_when_calculate_method_is_called_then_it_returns_false()
	{
		$this->enable_cogs_in_woo_settings();
		$variable_product = $this->create_variable_product_with_variations();
		$variants = $variable_product->get_children();
		$product = wc_get_product($variants[0]);

		$this->assertEquals(0, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$this->assertEquals(false, (new CostOfGoods(array('WooC' => 'WooCCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]));
	}

	public function test_given_cogs_exists_for_variable_product_when_calculate_method_is_called_then_it_returns_correct_value()
	{
		$cogs_value = 100.0;
		$this->enable_cogs_in_woo_settings();

		$variable_product = $this->create_variable_product_with_variations();
		$variants = $variable_product->get_children();
		$product = wc_get_product($variants[0]);
		$product->set_cogs_value($cogs_value);
		$product->save();

		$this->assertEquals($cogs_value, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$this->assertEquals($cogs_value, (new CostOfGoods(array('WooC' => 'WooCCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]));
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}
}
