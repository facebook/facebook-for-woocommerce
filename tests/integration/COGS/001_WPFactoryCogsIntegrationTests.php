<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\COGS\CogsIntegrationTestsBase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WPFactoryCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;

class WPFactoryCogsIntegrationTests extends CogsIntegrationTestsBase
{
	const PLUGIN_DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/cost-of-goods-for-woocommerce.zip';

	const PLUGIN_FILE_PATH = 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php';

	const PLUGIN_SLUG = 'cost-of-goods-for-woocommerce';
	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->install_3p_plugin(self::PLUGIN_FILE_PATH, self::PLUGIN_SLUG, self::PLUGIN_DOWNLOAD_URL);
	}

	public function test_given_wpfactory_cogs_is_disabled_when_wpfactory_provider_is_available_called_then_it_returns_false() {
		$instance = new WPFactoryCogsProvider();
		$this->assertFalse($instance->is_available(), 'WPFactory COGS is expected to be disabled');
		$this->expectException(IntegrationIsNotAvailableException::class);
		$instance->get_cogs_value($this->create_simple_product());
	}

	public function test_given_wpfactory_cogs_is_enabled_when_wpfactory_provider_is_available_called_then_it_returns_true() {
		$this->enable_wpfactory_cogs_plugin();
		$this->assertTrue(is_plugin_active('cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php'));
		$this->assertTrue((new WPFactoryCogsProvider())->is_available(), 'WPFactory COGS is expected to be enabled');
	}

	// Simple Product tests
	public function test_given_cogs_does_not_exist_for_simple_product_when_calculate_method_is_called_then_it_returns_false() {
		$this->enable_wpfactory_cogs_plugin();
		$product = $this->create_simple_product();
		
		$value = (new CostOfGoods(array('WPFactory' => 'WPFactoryCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]);
		$this->assertEquals(false, $value);
	}

	public function test_given_cogs_exists_for_simple_product_when_calculate_method_is_called_then_it_returns_correct_value() {
		$this->enable_wpfactory_cogs_plugin();
		$cogs_value = 100.0;

		$product = $this->create_simple_product();
		$product->update_meta_data( '_alg_wc_cog_cost', $cogs_value );
		$product->save();
		
		$value = (new CostOfGoods(array('WPFactory' => 'WPFactoryCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]);
		$this->assertEquals($cogs_value, $value);
	}

	public function test_given_cogs_provider_available_when_multiple_simple_products_provided_and_all_have_cogs_then_sum_cogs_is_returned() {
		$this->enable_wpfactory_cogs_plugin();

		$product1_cogs_value = 100.0;
		$product2_cogs_value = 150.0;

		$product1 = $this->create_simple_product();
		$product1->update_meta_data( '_alg_wc_cog_cost', $product1_cogs_value );
		$product1->save();

		$product2 = $this->create_simple_product();
		$product2->update_meta_data( '_alg_wc_cog_cost', $product2_cogs_value );
		$product2->save();

		$value = (new CostOfGoods(array('WPFactory' => 'WPFactoryCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product1,1), $this->create_cogs_data_input($product2,1)]);
		$this->assertEquals($product1_cogs_value + $product2_cogs_value, $value);
	}

	public function test_given_cogs_provider_available_when_multiple_simple_products_provided_but_one_does_not_have_cogs_then_false_is_returned() {
		$this->enable_wpfactory_cogs_plugin();

		$product1_cogs_value = 100.0;
		
		$product1 = $this->create_simple_product();
		$product1->update_meta_data( '_alg_wc_cog_cost', $product1_cogs_value );
		$product1->save();

		$product2 = $this->create_simple_product();

		$value = (new CostOfGoods(array('WPFactory' => 'WPFactoryCogsProvider')))->calculate_cogs_for_products(
			[$this->create_cogs_data_input($product1,1), $this->create_cogs_data_input($product2,1)]);
		$this->assertEquals(false, $value);
	}

	// Variable Products tests
	public function test_given_cogs_does_not_exist_for_variable_product_when_calculate_method_is_called_then_it_returns_false() {
		$this->enable_wpfactory_cogs_plugin();
		$variable_product = $this->create_variable_product_with_variations();
		$variants = $variable_product->get_children();
		$product = wc_get_product($variants[0]);

		$this->assertEquals(false, (new CostOfGoods(array('WPFactory' => 'WPFactoryCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]));
	}

	public function test_given_cogs_exists_for_variable_product_when_calculate_method_is_called_then_it_returns_correct_value() {
		$cogs_value = 100.0;
		$this->enable_wpfactory_cogs_plugin();

		$variable_product = $this->create_variable_product_with_variations();
		$variants = $variable_product->get_children();
		$product = wc_get_product($variants[0]);
		$product->update_meta_data( '_alg_wc_cog_cost', $cogs_value );
		$product->save();

		$this->assertEquals($cogs_value, (new CostOfGoods(array('WPFactory' => 'WPFactoryCogsProvider')))->calculate_cogs_for_products([$this->create_cogs_data_input($product,1)]));
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		deactivate_plugins( self::PLUGIN_FILE_PATH );
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_alg_wc_cog_cost'");
		delete_option('wc_cog_settings');
		parent::tearDown();
	}
}
