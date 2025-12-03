<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use stdClass;
use WooCommerce\Facebook\Integrations\CostOfGoods\CostOfGoods;
use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WC_Product;
/**
 * Unit tests for WooCommerce CostsOfGoods class.
 *
 * @since 3.5.2
 */
class WooCCogsProviderTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function test_given_no_cogs_providers_available_when_calculate_method_called_then_false_is_returned() {
		$reflection = new \ReflectionClass( WooCCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', true);
		
		$this->assertFalse(CostOfGoods::calculate_cogs_for_products([]));
	}

	public function setUp(): void {

		// // Mock WC_Facebookcommerce_Utils::is_woocommerce_integration
		// if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) {
		// 	eval( 'class WC_Facebookcommerce_Utils {
		// 		public static function is_woocommerce_integration() { return true; }
		// 	}' );
		// }
		// // Mock WC_Product
		// if ( ! class_exists( 'WC_Product' ) ) {
		// 	eval( 'class WC_Product {
		// 		public function get_cogs_total_value() { return 88.88; }
		// 	}' );
		// }
		// // Mock get_option
		// if ( ! function_exists( 'get_option' ) ) {
		// 	eval( 'function get_option($key) {
		// 		if ($key === "woocommerce_feature_cost_of_goods_sold_enabled") return "yes";
		// 		return null;
		// 	}' );
		// }
	}
	
	public function test_given_provider_is_unavailable_when_instantiated_then_exception_thrown() {
		$product = $this->createMock( WC_Product::class );
		$reflection = new \ReflectionClass( WooCCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', false);
		$instance = $reflection->newInstance();
		
		$instance->get_cogs_value($product);
		$this->expectException( IntegrationIsNotAvailableException::class );
	}

	public function test_given_product_has_cogs_value_when_get_cogs_value_is_called_then_correct_value_returned() {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_cogs_total_value' )->willReturn( 10 );
		
		$reflection = new \ReflectionClass( WooCCogsProvider::class );
		$reflection->setStaticPropertyValue('is_available', true);
		$instance = $reflection->newInstance();

		$value = $instance->get_cogs_value($product);
		$this->assertEqual(10, $value);
	}

	public function test_given_woo_integration_is_not_available_when_is_available_called_then_it_returns_false() {
		if ( class_exists( 'WC_Facebookcommerce_Utils' ) ) {
			\WC_Facebookcommerce_Utils::staticExpects($this->any())->method('is_woocommerce_integration')->willReturn(false);
		} else {
			$this->assertTrue(false);
			eval( 'class WC_Facebookcommerce_Utils {
				public static function is_woocommerce_integration() { return true; }
			}' );
		}
		$reflection = new \ReflectionClass( WooCCogsProvider::class );
		$instance = $reflection->newInstance();
		$this->expectException( IntegrationIsNotAvailableException::class );
	}

	/* is_available = false, if:
		- is_woo_integration is false
		- WC_Product->get_cogs_total_value returns null
		- false is returned from: wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )->feature_is_enabled( 'cost_of_goods_sold' )
		- false is returned from get_option('woocommerce_feature_cost_of_goods_sold_enabled')

		
*/
	public function given_() {
		if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) {
			eval( 'class WC_Facebookcommerce_Utils {
				public static function is_woocommerce_integration() { return true; }
			}' );
		} else {

		}

		// Mock WC_Product
		if ( ! class_exists( 'WC_Product' ) ) {
			eval( 'class WC_Product {
				public function get_cogs_total_value() { return 88.88; }
			}' );
		}
		// Mock get_option
		if ( ! function_exists( 'get_option' ) ) {
			eval( 'function get_option($key) {
				if ($key === "woocommerce_feature_cost_of_goods_sold_enabled") return "yes";
				return null;
			}' );
		}
	}
}
