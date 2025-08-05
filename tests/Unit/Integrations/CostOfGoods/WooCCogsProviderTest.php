<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\CostOfGoods\WooCCogsProvider;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WooCommerce CostsOfGoods class.
 *
 * @since 3.5.2
 */
class WooCCogsProviderTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function setUp(): void {

		// Mock WC_Facebookcommerce_Utils::is_woocommerce_integration
		if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) {
			eval( 'class WC_Facebookcommerce_Utils {
				public static function is_woocommerce_integration() { return true; }
			}' );
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
	
	public function testIsAvailableReturnsTrueWhenAllConditionsMet() {
		$this->assertTrue( WooCCogsProvider::is_available() );
	}
	
	public function testConstructorThrowsExceptionIfNotAvailable() {
		// Patch is_woocommerce_integration to return false
		WC_Facebookcommerce_Utils::$integration = false;
		$this->expectException( IntegrationIsNotAvailableException::class );
		new WooCCogsProvider();
		// Restore
		WC_Facebookcommerce_Utils::$integration = true;
	}

	public function testGetCogsValueReturnsProductCogsTotal() {
		$provider = new WooCCogsProvider();
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_cogs_total_value' )->willReturn( 77.77 );
		$result = $provider->get_cogs_value( $product );
		$this->assertEquals( 77.77, $result );
	}
}
