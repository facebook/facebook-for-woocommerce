<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Integration\COGS;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Integrations\CostOfGoods\WPFactoryCogsProvider;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;
use WC_Product_Variation;
use WC_Product_Variable;

class WPFactoryCogsIntegrationTests extends IntegrationTestCase
{
	const PLUGIN_DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/cost-of-goods-for-woocommerce.zip';

	const PLUGIN_FILE_PATH = 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php';

	const WOOCOMMERCE_PLUGIN_FILE_PATH = 'woocommerce/woocommerce.php';
	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		
		$this->mark_plugin_as_active(self::WOOCOMMERCE_PLUGIN_FILE_PATH);

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE_PATH ) ) {
			require_once ABSPATH . 'wp-admin/includes/admin.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			
			$response = wp_remote_get( self::PLUGIN_DOWNLOAD_URL );
			$plugin_zip = wp_upload_bits( 'cost-of-goods-for-woocommerce.zip', null, wp_remote_retrieve_body( $response ) );
			$upgrader = new \Plugin_Upgrader();
			$result = $upgrader->install( $plugin_zip['file'] );
			
			if ( is_wp_error( $result ) ) {
				throw new \Exception('Cannot install/enable WPFactory plugin');
			}
		}
		$this->disable_facebook_sync();
	}

	private function enable_wpfactory_cogs_plugin() {
		$this->assertTrue(function_exists('is_plugin_active'));
		$this->assertTrue(is_plugin_active(self::WOOCOMMERCE_PLUGIN_FILE_PATH));
		$is_wpfactory_active = is_plugin_active(self::PLUGIN_FILE_PATH);
		if ( ! $is_wpfactory_active ) {
			$this->assertTrue(function_exists('activate_plugin'));
			require_once WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE_PATH;
			activate_plugin( self::PLUGIN_FILE_PATH );
			$this->assertTrue(is_plugin_active(self::PLUGIN_FILE_PATH));
		}
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

	// // Simple Product tests
	// public function test_given_cogs_does_not_exist_for_simple_product_when_calculate_method_is_called_then_it_returns_false() {
	// 	$this->enable_wpfactory_cogs_plugin();
	// 	$product = $this->create_simple_product();
		
	// 	$this->assertEquals(0, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

	// 	$value = (new CostOfGoods())->calculate_cogs_for_products([$product]);
	// 	$this->assertEquals(false, $value);
	// }

	// public function test_given_cogs_exists_for_simple_product_when_calculate_method_is_called_then_it_returns_correct_value() {
	// 	$this->enable_wpfactory_cogs_plugin();
	// 	$cogs_value = 100.0;

	// 	$product = $this->create_simple_product();
	// 	$product->set_cogs_value($cogs_value);
	// 	$product->save();

	// 	$this->assertEquals($cogs_value, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

	// 	$value = (new CostOfGoods())->calculate_cogs_for_products([$product]);
	// 	$this->assertEquals($cogs_value, $value);
	// }

	// public function test_given_cogs_provider_available_when_multiple_simple_products_provided_and_all_have_cogs_then_sum_cogs_is_returned() {
	// 	$this->enable_wpfactory_cogs_plugin();

	// 	$product1_cogs_value = 100.0;
	// 	$product2_cogs_value = 150.0;

	// 	$product1 = $this->create_simple_product();
	// 	$product1->set_cogs_value($product1_cogs_value);
	// 	$product1->save();

	// 	$product2 = $this->create_simple_product();
	// 	$product2->set_cogs_value($product2_cogs_value);
	// 	$product2->save();

	// 	$this->assertEquals($product1_cogs_value, $product1->get_cogs_total_value());
	// 	$this->assertEquals($product2_cogs_value, $product2->get_cogs_total_value());

	// 	$value = (new CostOfGoods())->calculate_cogs_for_products([$product1, $product2]);
	// 	$this->assertEquals($product1_cogs_value + $product2_cogs_value, $value);
	// }

	// public function test_given_cogs_provider_available_when_multiple_simple_products_provided_but_one_does_not_have_cogs_then_false_is_returned() {
	// 	$this->enable_wpfactory_cogs_plugin();

	// 	$product1_cogs_value = 100.0;
		
	// 	$product1 = $this->create_simple_product();
	// 	$product1->set_cogs_value($product1_cogs_value);
	// 	$product1->save();

	// 	$product2 = $this->create_simple_product();

	// 	$this->assertEquals($product1_cogs_value, $product1->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');
	// 	$this->assertEquals(0, $product2->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

	// 	$value = (new CostOfGoods())->calculate_cogs_for_products([$product1, $product2]);
	// 	$this->assertEquals(false, $value);
	// }

	// // Variable Products tests
	// private function create_variable_product_with_variations(): WC_Product_Variable {
	// 	// Create variable product
	// 	$variable_product = $this->create_variable_product();

	// 	// Create variations
	// 	$variations = [
	// 		[ 'attributes' => [ 'size' => 'Small' ], 'price' => '25.99' ],
	// 		[ 'attributes' => [ 'size' => 'Medium' ], 'price' => '29.99' ],
	// 		[ 'attributes' => [ 'size' => 'Large' ], 'price' => '34.99' ]
	// 	];

	// 	foreach ( $variations as $variation_data ) {
	// 		$variation = new WC_Product_Variation();
	// 		$variation->set_parent_id( $variable_product->get_id() );
	// 		$variation->set_attributes( $variation_data['attributes'] );
	// 		$variation->set_regular_price( $variation_data['price'] );
	// 		$variation->set_status( 'publish' );
	// 		$variation->save();
	// 	}

	// 	return $variable_product;
	// }

	// public function test_given_cogs_does_not_exist_for_variable_product_when_calculate_method_is_called_then_it_returns_false() {
	// 	$this->enable_wpfactory_cogs_plugin();
	// 	$variable_product = $this->create_variable_product_with_variations();
	// 	$variants = $variable_product->get_children();
	// 	$product = wc_get_product($variants[0]);

	// 	$this->assertEquals(0, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');

	// 	$this->assertEquals(false, (new CostOfGoods())->calculate_cogs_for_products([$product]));
	// }

	// public function test_given_cogs_exists_for_variable_product_when_calculate_method_is_called_then_it_returns_correct_value() {
	// 	$cogs_value = 100.0;
	// 	$this->enable_wpfactory_cogs_plugin();

	// 	$variable_product = $this->create_variable_product_with_variations();
	// 	$variants = $variable_product->get_children();
	// 	$product = wc_get_product($variants[0]);
	// 	$product->set_cogs_value($cogs_value);
	// 	$product->save();

	// 	$this->assertEquals($cogs_value, $product->get_cogs_total_value(), 'Incorrect value is set for Product WooC COGS');
	// 	$this->assertEquals($cogs_value, (new CostOfGoods())->calculate_cogs_for_products([$product]));
	// }

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void
	{
		deactivate_plugins( 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php' );
		parent::tearDown();
	}
}
