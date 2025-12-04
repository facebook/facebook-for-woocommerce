<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WC_Product_Variation;
use WC_Product_Attribute;
use WC_Product;

class CogsIntegrationTests extends IntegrationTestCase
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
		wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )->change_feature_enable('cost_of_goods_sold', true);
	}

	private function disable_cogs_in_woo_settings() {}

	public function test_given_cogs_exists_for_product_when_calculate_method_is_called_then_it_returns_correct_value()
	{
		$this->enable_cogs_in_woo_settings();

		$this->assertTrue(WooCCogsProvider::is_available());

		$product = $this->create_simple_product();
		update_post_meta($product->get_id(), '_cost_of_goods', 100.0);
		$product->save();

		$this->assertTrue($product->get_cogs_value() == null, 'cogs is not enabled at a product level');

		$this->assertEquals(100.0, $product->get_cogs_total_value());

		$value = CostOfGoods::calculate_cogs_for_products([$product]);
		$this->assertEquals(100.0, $value);
	}
	// public function test_given_cogs_provider_available_when_multiple_products_provided_and_all_have_cogs_then_sum_cogs_is_returned() {
	// 		$product1 = $this->createMock(WC_Product::class);
	// 		$product1->method('get_cogs_total_value')->willReturn(10.0);
	// 		$product2 = $this->createMock(WC_Product::class);
	// 		$product2->method('get_cogs_total_value')->willReturn(20.0);

	// 		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
	// 		$cogs_provider_mock->method('get_cogs_value')->willReturn( 10.0 );
	// 		$cogs_provider_mock->expects($this->exactly($product2))->method('get_cogs_value')->willReturn( 20.0 );

	// 		// Patch get_cogs_providers to return our mock
	// 		$reflection = new \ReflectionClass( CostOfGoods::class );
	// 		$reflection->setStaticPropertyValue('available_integrations', [$cogs_provider_mock]);
	// 		$reflection->setStaticPropertyValue('already_fetched', true);

	// 		$this->assertEquals(30.0, CostOfGoods::calculate_cogs_for_products([$product1, $product2]));
	// 	}

	// 	public function test_given_cogs_provider_available_when_multiple_products_provided_but_one_does_not_have_cogs_then_false_is_returned() {
	// 		$product1 = $this->createMock(WC_Product::class);
	// 		$product1->method('get_cogs_total_value')->willReturn(10.0);
	// 		$product2 = $this->createMock(WC_Product::class);
	// 		$product2->method('get_cogs_total_value')->willReturn(0.0);

	// 		$cogs_provider_mock = $this->createMock( AbstractCogsProvider::class );
	// 		$cogs_provider_mock->expects($this->exactly($product1))->method('get_cogs_value')->willReturn( 10.0 );
	// 		$cogs_provider_mock->expects($this->exactly($product2))->method('get_cogs_value')->willReturn( 0.0 );

	// 		// Patch get_cogs_providers to return our mock
	// 		$reflection = new \ReflectionClass( CostOfGoods::class );
	// 		$reflection->setStaticPropertyValue('available_integrations', [$cogs_provider_mock]);
	// 		$reflection->setStaticPropertyValue('already_fetched', true);

	// 		$this->assertFalse(CostOfGoods::calculate_cogs_for_products([$product1, $product2]));
	// 	}
	public function Given_Cogs_Does_Not_Exist_for_A_Product_When_calculate_method_is_called_Then_it_returns_false()
	{
		$this->assertFalse(true);
	}

	public function Given_cogs_doesnt_exist_for_one_product_in_an_order_When_calculate_method_is_called_Then_false_is_returned()
	{

		$this->assertFalse(true);
	}
	/**
	 * Placeholder. These tests should be added:
	 * 1. Testing WooC integration with older WooC versions
	 * 2. WPFactory plugin is installed but inactive
	 * 3. WooC Cogs is Disabled / Enabled. When WooC Cogs is disabled, WooCCogsProvider should return false in is_available
	 * 4. Test for Simple & Variable products
	 */
	public function Given_Single_Purcahse_Event_When_SendingEvent_Then_RequestContainsValues()
	{

		$this->assertTrue(false);
	}

	/* is_available = false, if:
		- is_woo_integration is false
		- WC_Product->get_cogs_total_value returns null
		- false is returned from: wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )->feature_is_enabled( 'cost_of_goods_sold' )
		- false is returned from get_option('woocommerce_feature_cost_of_goods_sold_enabled')

		
*/
	public function given_()
	{
		if (! class_exists('WC_Facebookcommerce_Utils')) {
			eval('class WC_Facebookcommerce_Utils {
				public static function is_woocommerce_integration() { return true; }
			}');
		} else {
		}

		// Mock WC_Product
		if (! class_exists('WC_Product')) {
			eval('class WC_Product {
				public function get_cogs_total_value() { return 88.88; }
			}');
		}
		// Mock get_option
		if (! function_exists('get_option')) {
			eval('function get_option($key) {
				if ($key === "woocommerce_feature_cost_of_goods_sold_enabled") return "yes";
				return null;
			}');
		}
	}

	public function given_product_has_cogs_value_when_get_cogs_value_is_called_then_correct_value_returned() {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_cogs_total_value' )->willReturn( 10.0 );
		
		$reflection = new \ReflectionClass( WPFactoryCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', null);
		$instance = $reflection->newInstance();
		
		$value = $instance->get_cogs_value($product);
		$this->assertEquals(10.0, $value);
	}

	public function given_woo_integration_is_not_available_when_is_available_called_then_it_returns_false()
	{
		if (class_exists('WC_Facebookcommerce_Utils')) {
			\WC_Facebookcommerce_Utils::staticExpects($this->any())->method('is_woocommerce_integration')->willReturn(false);
		} else {
			$this->assertTrue(false);
			eval('class WC_Facebookcommerce_Utils {
				public static function is_woocommerce_integration() { return true; }
			}');
		}
		$reflection = new \ReflectionClass(WooCCogsProvider::class);
		$instance = $reflection->newInstance();
		$this->expectException(\IntegrationIsNotAvailableException::class);
	}

	/* is_available = false, if:
		- is_woo_integration is false
		- WC_Product->get_cogs_total_value returns null
		- false is returned from: wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )->feature_is_enabled( 'cost_of_goods_sold' )
		- false is returned from get_option('woocommerce_feature_cost_of_goods_sold_enabled')

		
*/

	

	private function set_up_variable_product()
	{

		$size_attribute = new WC_Product_Attribute();
		$size_attribute->set_name('Size');
		$size_attribute->set_options(['Small', 'Medium', 'Large']);
		$size_attribute->set_visible(true);
		$size_attribute->set_variation(true);

		$color_attribute = new WC_Product_Attribute();
		$color_attribute->set_name('Color');
		$color_attribute->set_options(['Red', 'Blue', 'Black']);
		$color_attribute->set_visible(true);
		$color_attribute->set_variation(true);

		$variable_product = $this->create_variable_product([
			'Size' => $size_attribute,
			'Color' => $color_attribute
		]);

		$variations = [
			['attributes' => ['size' => 'Small', 'color' => 'Red'], 'price' => '29.99'],
			['attributes' => ['size' => 'Medium', 'color' => 'Blue'], 'price' => '34.99'],
			['attributes' => ['size' => 'Large', 'color' => 'Black'], 'price' => '39.99']
		];

		$created_variations = [];
		foreach ($variations as $variation_data) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id($variable_product->get_id());
			$variation->set_attributes($variation_data['attributes']);
			$variation->set_regular_price($variation_data['price']);
			$variation->set_status('publish');
			$variation->save();
			$created_variations[] = $variation;
		}

		return $created_variations;
	}

	public function should_not_use_value_if_cogs_is_zero()
	{
		$this->assertTrue(false);
	}

	private function set_up_simple_products($count)
	{
		$this->disable_facebook_sync();
		$products = [];

		for ($i = 1; $i <= $count; $i++) {
			$products[] = $this->create_simple_product([
				'name' => "Simple Product {$i}",
				'regular_price' => (10 + $i) . '.99',
				'sku' => "Simple-{$i}",
				'status' => 'publish'
			]);
		}
		return $products;
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}
}
