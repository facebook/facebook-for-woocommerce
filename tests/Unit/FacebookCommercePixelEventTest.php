<?php
declare( strict_types=1 );

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

class FacebookCommercePixelTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Unit tests for WC_Facebookcommerce_Pixel class.
	 */
	public function test_get_options_returns_default_options_when_no_options_exist() {
		$expected_options = array(
			WC_Facebookcommerce_Pixel::PIXEL_ID_KEY     => '0',
			WC_Facebookcommerce_Pixel::USE_PII_KEY      => 0,
			WC_Facebookcommerce_Pixel::USE_S2S_KEY      => false,
			WC_Facebookcommerce_Pixel::ACCESS_TOKEN_KEY => '',
		);

		$actual_options = WC_Facebookcommerce_Pixel::get_options();

		$this->assertEquals( $expected_options, $actual_options );
	}

	public function test_get_options_returns_merged_options_when_options_exist() {
		$default_options = array(
			WC_Facebookcommerce_Pixel::PIXEL_ID_KEY     => '0',
			WC_Facebookcommerce_Pixel::USE_PII_KEY      => 0,
			WC_Facebookcommerce_Pixel::USE_S2S_KEY      => false,
			WC_Facebookcommerce_Pixel::ACCESS_TOKEN_KEY => '',
		);

		$existing_options = array(
			WC_Facebookcommerce_Pixel::PIXEL_ID_KEY     => '123456789',
			WC_Facebookcommerce_Pixel::USE_PII_KEY      => 1,
			WC_Facebookcommerce_Pixel::USE_S2S_KEY      => true,
			WC_Facebookcommerce_Pixel::ACCESS_TOKEN_KEY => 'abc123',
		);

		$expected_options = array_merge( $default_options, $existing_options );

		update_option( WC_Facebookcommerce_Pixel::SETTINGS_KEY, $existing_options );

		$actual_options = WC_Facebookcommerce_Pixel::get_options();

		$this->assertEquals( $expected_options, $actual_options );
	}

	public function test_get_options_returns_default_options_when_options_are_not_an_array() {
		update_option( WC_Facebookcommerce_Pixel::SETTINGS_KEY, 'not an array' );

		$expected_options = array(
			WC_Facebookcommerce_Pixel::PIXEL_ID_KEY     => '0',
			WC_Facebookcommerce_Pixel::USE_PII_KEY      => 0,
			WC_Facebookcommerce_Pixel::USE_S2S_KEY      => false,
			WC_Facebookcommerce_Pixel::ACCESS_TOKEN_KEY => '',
		);

		$actual_options = WC_Facebookcommerce_Pixel::get_options();

		$this->assertEquals( $expected_options, $actual_options );
	}

}
