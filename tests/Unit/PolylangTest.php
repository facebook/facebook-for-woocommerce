<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Integrations\Polylang;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for Polylang integration class.
 *
 * @since 3.6.0
 */
class PolylangTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var Polylang
	 */
	private $instance;

	/**
	 * @var \WC_Product
	 */
	private $product;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Define the plugin directory constant if not already defined
		if ( ! defined( 'WC_FACEBOOKCOMMERCE_PLUGIN_DIR' ) ) {
			define( 'WC_FACEBOOKCOMMERCE_PLUGIN_DIR', dirname( dirname( dirname( __DIR__ ) ) ) );
		}

		$this->instance = new Polylang();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		if ( $this->product && $this->product->get_id() ) {
			wp_delete_post( $this->product->get_id(), true );
		}

		parent::tearDown();
	}

	/**
	 * Test that get_plugin_file_name returns the correct value.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_plugin_file_name
	 */
	public function test_get_plugin_file_name_returns_correct_value() {
		$result = $this->instance->get_plugin_file_name();

		$this->assertEquals( 'polylang/polylang.php', $result );
	}

	/**
	 * Test that get_plugin_file_name returns a string.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_plugin_file_name
	 */
	public function test_get_plugin_file_name_returns_string() {
		$result = $this->instance->get_plugin_file_name();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test that get_plugin_name returns the correct value.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_plugin_name
	 */
	public function test_get_plugin_name_returns_correct_value() {
		$result = $this->instance->get_plugin_name();

		$this->assertEquals( 'Polylang', $result );
	}

	/**
	 * Test that get_plugin_name returns a string.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_plugin_name
	 */
	public function test_get_plugin_name_returns_string() {
		$result = $this->instance->get_plugin_name();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test that is_plugin_active returns false when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::is_plugin_active
	 */
	public function test_is_plugin_active_returns_false_when_plugin_not_active() {
		$result = $this->instance->is_plugin_active();

		// In test environment, Polylang is not active
		$this->assertFalse( $result );
	}

	/**
	 * Test that is_plugin_active returns a boolean.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::is_plugin_active
	 */
	public function test_is_plugin_active_returns_boolean() {
		$result = $this->instance->is_plugin_active();

		$this->assertIsBool( $result );
	}

	/**
	 * Test that get_available_languages returns empty array when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_available_languages
	 */
	public function test_get_available_languages_returns_empty_when_plugin_not_active() {
		$result = $this->instance->get_available_languages();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that get_available_languages returns an array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_available_languages
	 */
	public function test_get_available_languages_returns_array() {
		$result = $this->instance->get_available_languages();

		$this->assertIsArray( $result );
	}

	/**
	 * Test that get_default_language returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_default_language
	 */
	public function test_get_default_language_returns_null_when_plugin_not_active() {
		$result = $this->instance->get_default_language();

		$this->assertNull( $result );
	}

	/**
	 * Test that get_default_language returns string or null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_default_language
	 */
	public function test_get_default_language_returns_string_or_null() {
		$result = $this->instance->get_default_language();

		$this->assertTrue( is_string( $result ) || is_null( $result ) );
	}

	/**
	 * Test that get_product_language returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_product_language
	 */
	public function test_get_product_language_returns_null_when_plugin_not_active() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$result = $this->instance->get_product_language( $this->product->get_id() );

		$this->assertNull( $result );
	}

	/**
	 * Test that get_product_language works with valid product ID.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_product_language
	 */
	public function test_get_product_language_with_valid_product_id() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$result = $this->instance->get_product_language( $this->product->get_id() );

		$this->assertTrue( is_string( $result ) || is_null( $result ) );
	}

	/**
	 * Test that switch_to_language returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::switch_to_language
	 */
	public function test_switch_to_language_returns_null_when_plugin_not_active() {
		$result = $this->instance->switch_to_language( 'es_ES' );

		$this->assertNull( $result );
	}

	/**
	 * Test that switch_to_language returns string or null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::switch_to_language
	 */
	public function test_switch_to_language_returns_string_or_null() {
		$result = $this->instance->switch_to_language( 'en_US' );

		$this->assertTrue( is_string( $result ) || is_null( $result ) );
	}

	/**
	 * Test that restore_language executes without error.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::restore_language
	 */
	public function test_restore_language_executes_without_error() {
		try {
			$this->instance->restore_language( 'en' );
			$this->assertTrue( true );
		} catch ( \Throwable $e ) {
			$this->fail( 'restore_language should not throw an exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Test that is_pro_version returns false when constant is not defined.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::is_pro_version
	 */
	public function test_is_pro_version_returns_false_when_constant_not_defined() {
		$result = $this->instance->is_pro_version();

		$this->assertFalse( $result );
		$this->assertFalse( defined( 'POLYLANG_PRO' ) );
	}

	/**
	 * Test that is_pro_version returns a boolean.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::is_pro_version
	 */
	public function test_is_pro_version_returns_boolean() {
		$result = $this->instance->is_pro_version();

		$this->assertIsBool( $result );
	}

	/**
	 * Test that get_products_from_default_language returns empty array when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_returns_empty_when_plugin_not_active() {
		$result = $this->instance->get_products_from_default_language();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that get_products_from_default_language returns an array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_returns_array() {
		$result = $this->instance->get_products_from_default_language();

		$this->assertIsArray( $result );
	}

	/**
	 * Test that get_products_from_default_language works with custom limit.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_with_custom_limit() {
		$result = $this->instance->get_products_from_default_language( 5, 0 );

		$this->assertIsArray( $result );
	}

	/**
	 * Test that get_products_from_default_language works with offset.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_with_offset() {
		$result = $this->instance->get_products_from_default_language( 10, 5 );

		$this->assertIsArray( $result );
	}

	/**
	 * Test that get_product_translation_details returns empty array when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_product_translation_details
	 */
	public function test_get_product_translation_details_returns_empty_when_plugin_not_active() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$result = $this->instance->get_product_translation_details( $this->product->get_id() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that get_product_translation_details returns correct structure.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_product_translation_details
	 */
	public function test_get_product_translation_details_returns_correct_structure() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$result = $this->instance->get_product_translation_details( $this->product->get_id() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test that create_product_translation returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::create_product_translation
	 */
	public function test_create_product_translation_returns_null_when_plugin_not_active() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$result = $this->instance->create_product_translation(
			$this->product->get_id(),
			'es_ES',
			[ 'name' => 'Producto traducido' ]
		);

		$this->assertNull( $result );
	}

	/**
	 * Test that create_product_translation returns null when original product does not exist.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::create_product_translation
	 */
	public function test_create_product_translation_returns_null_when_original_product_not_exists() {
		$result = $this->instance->create_product_translation(
			999999,
			'es_ES',
			[ 'name' => 'Test' ]
		);

		$this->assertNull( $result );
	}

	/**
	 * Test that create_product_translation works with translated data.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::create_product_translation
	 */
	public function test_create_product_translation_with_translated_data() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$translated_data = [
			'name'              => 'Producto traducido',
			'description'       => 'Descripción traducida',
			'short_description' => 'Descripción corta traducida',
		];

		$result = $this->instance->create_product_translation(
			$this->product->get_id(),
			'es_ES',
			$translated_data
		);

		// In test environment without Polylang, this should return null
		$this->assertNull( $result );
	}

	/**
	 * Test that create_product_translation works with minimal data.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::create_product_translation
	 */
	public function test_create_product_translation_with_minimal_data() {
		$this->product = \WC_Helper_Product::create_simple_product();

		$result = $this->instance->create_product_translation(
			$this->product->get_id(),
			'fr_FR',
			[]
		);

		$this->assertNull( $result );
	}

	/**
	 * Test that get_availability_data returns correct structure.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_availability_data
	 */
	public function test_get_availability_data_returns_correct_structure() {
		$result = $this->instance->get_availability_data();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'plugin_name', $result );
		$this->assertArrayHasKey( 'plugin_file', $result );
		$this->assertArrayHasKey( 'is_installed', $result );
		$this->assertArrayHasKey( 'is_active', $result );
	}

	/**
	 * Test that get_availability_data works when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_availability_data
	 */
	public function test_get_availability_data_when_plugin_not_active() {
		$result = $this->instance->get_availability_data();

		$this->assertIsArray( $result );
		$this->assertFalse( $result['is_active'] );
	}

	/**
	 * Test that get_integration_status returns 'Not Available' when plugin is not installed.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_integration_status
	 */
	public function test_get_integration_status_returns_not_available_when_not_installed() {
		$result = $this->instance->get_integration_status();

		// In test environment, Polylang is not installed
		$this->assertEquals( 'Not Available', $result );
	}

	/**
	 * Test that get_integration_status returns a string.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::get_integration_status
	 */
	public function test_get_integration_status_returns_string() {
		$result = $this->instance->get_integration_status();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test that is_eligible_for_language_override_feeds returns true.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::is_eligible_for_language_override_feeds
	 */
	public function test_is_eligible_for_language_override_feeds_returns_true() {
		$result = $this->instance->is_eligible_for_language_override_feeds();

		$this->assertTrue( $result );
	}

	/**
	 * Test that is_eligible_for_language_override_feeds returns a boolean.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Polylang::is_eligible_for_language_override_feeds
	 */
	public function test_is_eligible_for_language_override_feeds_returns_boolean() {
		$result = $this->instance->is_eligible_for_language_override_feeds();

		$this->assertIsBool( $result );
	}
}
