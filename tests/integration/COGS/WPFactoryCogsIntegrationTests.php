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
	/**
	 * Set up test environment
	 */
	public function setUp(): void
	{
		parent::setUp();
		$abspath = '/tmp/wordpress/';
		require_once $abspath . 'wp-admin/includes/plugin-install.php';
		$active_plugins = get_option('active_plugins', []);
		if (!in_array('woocommerce/woocommerce.php', $active_plugins, true)) {
			$active_plugins[] = 'woocommerce/woocommerce.php';
			update_option('active_plugins', $active_plugins);
		}
		var_dump('==========SetUp==============');
		var_dump('==========START==============');
		if ( ! file_exists( '/tmp/wordpress/wp-content/plugins/cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php' ) ) {
			require_once $abspath . 'wp-admin/includes/admin.php';
			include_once $abspath . 'wp-admin/includes/class-wp-upgrader.php';
			require_once $abspath . 'wp-admin/includes/class-plugin-upgrader.php';
			require_once $abspath . 'wp-admin/includes/plugin.php';
			
			$response = wp_remote_get( 'https://downloads.wordpress.org/plugin/cost-of-goods-for-woocommerce.zip' );
			$plugin_zip = wp_upload_bits( 'cost-of-goods-for-woocommerce.zip', null, wp_remote_retrieve_body( $response ) );
			$upgrader = new \Plugin_Upgrader();
			$result = $upgrader->install( $plugin_zip['file'] );
			
			if ( is_wp_error( $result ) ) {
				throw new \Exception('Cannot install/enable WPFactory plugin');
			}
			var_dump('Upgrader install result:' . $result);
		}
		var_dump( 'cogs file exists: '. file_exists( '/tmp/wordpress/wp-content/plugins/cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php' ) );
		// var_dump( get_included_files() );
		var_dump('==========END==============');
		$this->disable_facebook_sync();
	}

	private function enable_wpfactory_cogs_plugin() {
		var_dump('==========enable_wpfactory_cogs_plugin==============');
		var_dump('==========START==============');
		var_dump('Is woo active?' . (is_plugin_active('woocommerce/woocommerce.php') ? 'YES' : 'NO'));
		var_dump('Is woo integration?' . (\WC_Facebookcommerce_Utils::is_woocommerce_integration() ? 'YES' : 'NO'));
		var_dump('Is wpfactory active?' . (is_plugin_active('cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php') ? 'YES' : 'NO'));
		var_dump('==========END==============');
		$res = activate_plugin( 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php' );
		$this->assertEquals(null, $res);
		require_once '/tmp/wordpress/wp-content/plugins/cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php';
		$res = activate_plugin( 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php' );
		$this->assertEquals(null, $res);
		var_dump('==========START==============');
		var_dump('function_exists(alg_wc_cog): ' . (function_exists('alg_wc_cog') ? 'YES' : 'NO'));
		var_dump( get_included_files() );
		var_dump('==========END==============');
		var_dump('==========START==============');
		var_dump('Is woo active?' . (is_plugin_active('woocommerce/woocommerce.php') ? 'YES' : 'NO'));
		var_dump('Is wpfactory active?' . (is_plugin_active('cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php') ? 'YES' : 'NO'));
		var_dump('==========END==============');
		// do_action( 'before_woocommerce_init' );
    	// do_action( 'woocommerce_init' );
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
