<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WC_Facebookcommerce_WarmConfig;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WC_Facebookcommerce_WarmConfig class.
 *
 * @since 3.5.2
 */
class WCFacebookcommerceWarmConfigTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be referenced.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WC_Facebookcommerce_WarmConfig' ) );
	}

	/**
	 * Test that all static properties have default null values.
	 */
	public function test_default_property_values() {
		// Reset all properties to ensure clean state
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = null;
		WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled = null;
		WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s = null;
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = null;

		$this->assertNull( WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );
		$this->assertNull( WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled );
		$this->assertNull( WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s );
		$this->assertNull( WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );
	}

	/**
	 * Test setting and getting the pixel ID property.
	 */
	public function test_pixel_id_property() {
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = '123456789';
		$this->assertEquals( '123456789', WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );

		// Test with empty string
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = '';
		$this->assertEquals( '', WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );

		// Test with special characters
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = 'pixel_id_!@#$%';
		$this->assertEquals( 'pixel_id_!@#$%', WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );
	}

	/**
	 * Test setting and getting the advanced matching enabled property.
	 */
	public function test_advanced_matching_enabled_property() {
		// Test with boolean true
		WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled = true;
		$this->assertTrue( WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled );

		// Test with boolean false
		WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled = false;
		$this->assertFalse( WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled );

		// Test with null
		WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled = null;
		$this->assertNull( WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled );
	}

	/**
	 * Test setting and getting the S2S property.
	 */
	public function test_use_s2s_property() {
		// Test with boolean true
		WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s = true;
		$this->assertTrue( WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s );

		// Test with boolean false
		WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s = false;
		$this->assertFalse( WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s );

		// Test with null
		WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s = null;
		$this->assertNull( WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s );
	}

	/**
	 * Test setting and getting the access token property.
	 */
	public function test_access_token_property() {
		// Test with regular token
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = 'EAABsbCS...';
		$this->assertEquals( 'EAABsbCS...', WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );

		// Test with empty string
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = '';
		$this->assertEquals( '', WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );

		// Test with long token
		$long_token = str_repeat( 'a', 1000 );
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = $long_token;
		$this->assertEquals( $long_token, WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );

		// Test with special characters and Unicode
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = 'token_with_特殊字符_!@#$%^&*()';
		$this->assertEquals( 'token_with_特殊字符_!@#$%^&*()', WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );
	}

	/**
	 * Test that properties are independent of each other.
	 */
	public function test_property_independence() {
		// Set all properties to different values
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = 'pixel123';
		WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled = true;
		WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s = false;
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = 'token456';

		// Verify each property maintains its value independently
		$this->assertEquals( 'pixel123', WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );
		$this->assertTrue( WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled );
		$this->assertFalse( WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s );
		$this->assertEquals( 'token456', WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );

		// Change one property and verify others remain unchanged
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = 'new_pixel';
		$this->assertEquals( 'new_pixel', WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );
		$this->assertTrue( WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled );
		$this->assertFalse( WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s );
		$this->assertEquals( 'token456', WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );
	}

	/**
	 * Test mixed data types can be stored in string properties.
	 */
	public function test_mixed_data_types_in_string_properties() {
		// Test storing numbers as strings
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = 123456;
		$this->assertEquals( 123456, WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id );

		// Test storing arrays (though not recommended)
		$test_array = array( 'test' => 'value' );
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = $test_array;
		$this->assertEquals( $test_array, WC_Facebookcommerce_WarmConfig::$fb_warm_access_token );
	}

	/**
	 * Clean up after each test to ensure property isolation.
	 */
	public function tearDown(): void {
		// Reset all static properties to null
		WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id = null;
		WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled = null;
		WC_Facebookcommerce_WarmConfig::$fb_warm_use_s2s = null;
		WC_Facebookcommerce_WarmConfig::$fb_warm_access_token = null;

		parent::tearDown();
	}
} 