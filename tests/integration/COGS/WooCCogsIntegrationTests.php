<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WC_Product_Variation;
use WC_Product_Variable;

class WooCCogsIntegrationTests extends IntegrationTestCase
{

	/**
	 * @var API
	 */
	private $api;

	/**
	 * @var bool Whether we have valid credentials for full integration testing
	 */
	private $has_valid_credentials = false;

	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->disable_facebook_sync();
	}

	private function enable_cogs_in_woo_settings() {
		wc_get_container()
		->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )
		->change_feature_enable('cost_of_goods_sold', true);
	}

	private function disable_cogs_in_woo_settings() {
		wc_get_container()
		->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )
		->change_feature_enable('cost_of_goods_sold', false);
	}

	// public function test_given_wooc_cogs_is_disabled_when_wooc_provider_is_available_called_then_it_returns_false() {
	// 	$this->disable_cogs_in_woo_settings();
	// 	$this->assertFalse(WooCCogsProvider::is_available(), 'WooC COGS is expected to be disabled');
	// 	$this->assertFalse(function_exists( 'get_option' ) && ( 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' ) ), 'woocommerce_feature_cost_of_goods_sold_enabled is expected to be disabled');
	// }

	public function test_given_wooc_cogs_is_enabled_when_wooc_provider_is_available_called_then_it_returns_true() {
		$this->enable_cogs_in_woo_settings();
		$this->assertTrue(function_exists( 'get_option' ) && ( 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' ) ), 'woocommerce_feature_cost_of_goods_sold_enabled is expected to be enabled');
		$this->assertEquals(10, WooCCogsProvider::is_available(), 'WooC COGS is expected to be enabled');
	}

	// Simple Product tests
	public function test_given_cogs_does_not_exist_for_simple_product_when_calculate_method_is_called_then_it_returns_false()
	{
		$this->enable_cogs_in_woo_settings();
		$product = $this->create_simple_product();
		
		$this->assertEquals(0, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$value = CostOfGoods::calculate_cogs_for_products([$product]);
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

		$value = CostOfGoods::calculate_cogs_for_products([$product]);
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

		$value = CostOfGoods::calculate_cogs_for_products([$product1, $product2]);
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

		$value = CostOfGoods::calculate_cogs_for_products([$product1, $product2]);
		$this->assertEquals(false, $value);
	}

	// Variable Products tests
	private function create_variable_product_with_variations(): WC_Product_Variable {
		// Create variable product
		$variable_product = $this->create_variable_product();

		// Create variations
		$variations = [
			[ 'attributes' => [ 'size' => 'Small' ], 'price' => '25.99' ],
			[ 'attributes' => [ 'size' => 'Medium' ], 'price' => '29.99' ],
			[ 'attributes' => [ 'size' => 'Large' ], 'price' => '34.99' ]
		];

		foreach ( $variations as $variation_data ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $variable_product->get_id() );
			$variation->set_attributes( $variation_data['attributes'] );
			$variation->set_regular_price( $variation_data['price'] );
			$variation->set_status( 'publish' );
			$variation->save();
		}

		return $variable_product;
	}

	public function test_given_cogs_does_not_exist_for_variable_product_when_calculate_method_is_called_then_it_returns_false()
	{
		$this->enable_cogs_in_woo_settings();
		$variable_product = $this->create_variable_product_with_variations();
		$variants = $variable_product->get_children();
		$product = wc_get_product($variants[0]);

		$this->assertEquals(0, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

		$this->assertEquals(false, CostOfGoods::calculate_cogs_for_products([$product]));
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

		$this->assertEquals($cogs_value, CostOfGoods::calculate_cogs_for_products([$product]));
	}
	/**
	 * Placeholder. These tests should be added:
	 * 1. Testing WooC integration with older WooC versions
	 * 2. WPFactory plugin is installed but inactive
	 * 3. WooC Cogs is Disabled / Enabled. When WooC Cogs is disabled, WooCCogsProvider should return false in is_available
	 * 4. Test for Simple & Variable products
	 */
	public function Given_Single_Purcahse_Event_When_SendingEvent_Then_RequestContainsValues() // Should be an E2E test
	{
		$this->assertTrue(false);
	}

	/* is_available = false, if:
		- is_woo_integration is false
		- WC_Product->get_cogs_total_value returns null
		- false is returned from: wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )->feature_is_enabled( 'cost_of_goods_sold' )
		- false is returned from get_option('woocommerce_feature_cost_of_goods_sold_enabled')

		
*/

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}
}
