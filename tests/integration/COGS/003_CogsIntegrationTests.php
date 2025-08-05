<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\COGS\CogsIntegrationTestsBase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WC_Product_Variation;
use WC_Product_Attribute;
use WC_Product;

class CogsIntegrationTests extends CogsIntegrationTestsBase
{
	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->install_3p_plugin(WPFactoryCogsIntegrationTests::PLUGIN_FILE_PATH, WPFactoryCogsIntegrationTests::PLUGIN_SLUG, WPFactoryCogsIntegrationTests::PLUGIN_DOWNLOAD_URL);
	}

	public function test_given_no_cogs_providers_available_when_calculate_method_called_then_false_is_returned() {
		$this->assertFalse((new CostOfGoods(array()))->calculate_cogs_for_products($this->create_simple_product()));
	}

	public function test_given_wooc_and_wpfactory_cogs_are_enabled_when_product_has_only_wooc_value_and_calculate_method_is_called_then_wooc_value_is_returned() {
		$this->enable_cogs_in_woo_settings();
		$this->enable_wpfactory_cogs_plugin();

		$cogs_value = 100.0;
		$cogs_provider = new CostOfGoods(
			array(
				'WooC' => 'WooCCogsProvider',
				'WPFactory' => 'WPFactoryCogsProvider'
		));

		$product = $this->create_simple_product();
		$product->set_cogs_value($cogs_value);
		$product->save();

		$this->assertEquals($cogs_value, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');
		$this->assertEquals($cogs_value, $cogs_provider->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]));
	}

	public function test_given_wooc_and_wpfactory_cogs_are_enabled_when_product_has_both_wooc_and_wpfactory_cogs_values_and_calculate_method_is_called_then_wooc_value_is_returned() {
		$this->enable_cogs_in_woo_settings();
		$this->enable_wpfactory_cogs_plugin();

		$wooc_cogs_value = 100.0;
		$wpfactory_cogs_value = 150.0;
		$cogs_provider = new CostOfGoods(
			array(
				'WooC' => 'WooCCogsProvider',
				'WPFactory' => 'WPFactoryCogsProvider'
		));

		$product = $this->create_simple_product();
		$product->set_cogs_value($wooc_cogs_value);
		$product->update_meta_data( '_alg_wc_cog_cost', $wpfactory_cogs_value );
		$product->save();

		$this->assertEquals($wooc_cogs_value, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');
		$this->assertEquals($wooc_cogs_value, $cogs_provider->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]));
	}

	public function test_given_wooc_and_wpfactory_cogs_are_enabled_and_two_products_exist_when_product1_has_wooc_cogs_and_product2_has_wpfactory_cogs_values_and_calculate_method_is_called_then_both_values_are_used() {
		$this->enable_cogs_in_woo_settings();
		$this->enable_wpfactory_cogs_plugin();

		$product1_cogs = 100.0;
		$product2_cogs = 150.0;
		$cogs_provider = new CostOfGoods(
			array(
				'WooC' => 'WooCCogsProvider',
				'WPFactory' => 'WPFactoryCogsProvider'
		));

		$product1 = $this->create_simple_product(); 
		$product1->set_cogs_value($product1_cogs);
		$product1->save();
		
		$product2 = $this->create_simple_product();
		$product2->update_meta_data( '_alg_wc_cog_cost', $product2_cogs );
		$product2->save();
		
		$this->assertEquals($product1_cogs + $product2_cogs, $cogs_provider->calculate_cogs_for_products([$this->create_cogs_data_input($product1,1), $this->create_cogs_data_input($product2,1)]));
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		deactivate_plugins( WPFactoryCogsIntegrationTests::PLUGIN_FILE_PATH );
		parent::tearDown();
	}
}
