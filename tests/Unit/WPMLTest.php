<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Integrations\WPML;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests for the WPML integration class.
 *
 * @covers \WooCommerce\Facebook\Integrations\WPML
 */
class WPMLTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var WPML */
	private $instance;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = new WPML();
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		delete_option( 'fb_wmpl_language_visibility' );
		parent::tearDown();
	}

	/**
	 * Test get_plugin_file_name returns correct plugin file.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_plugin_file_name
	 */
	public function test_get_plugin_file_name() {
		$this->assertEquals( 'sitepress-multilingual-cms/sitepress.php', $this->instance->get_plugin_file_name() );
	}

	/**
	 * Test get_plugin_name returns correct plugin name.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_plugin_name
	 */
	public function test_get_plugin_name() {
		$this->assertEquals( 'WPML', $this->instance->get_plugin_name() );
	}

	/**
	 * Test is_plugin_active returns false when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::is_plugin_active
	 */
	public function test_is_plugin_active_returns_false_when_plugin_not_active() {
		$this->assertFalse( $this->instance->is_plugin_active() );
	}

	/**
	 * Test get_available_languages returns empty array when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_available_languages
	 */
	public function test_get_available_languages_returns_empty_when_not_active() {
		$result = $this->instance->get_available_languages();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_available_languages returns locales from WPML filter.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_available_languages
	 */
	public function test_get_available_languages_returns_locales_from_wpml_filter() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$wpml_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return [
					'en' => [
						'code' => 'en',
						'default_locale' => 'en_US',
					],
					'es' => [
						'code' => 'es',
						'default_locale' => 'es_ES',
					],
					'fr' => [
						'code' => 'fr',
						'default_locale' => 'fr_FR',
					],
				];
			}
		);

		$result = $this->instance->get_available_languages();
		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertContains( 'en_US', $result );
		$this->assertContains( 'es_ES', $result );
		$this->assertContains( 'fr_FR', $result );
	}

	/**
	 * Test get_available_languages falls back to code when default_locale is not available.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_available_languages
	 */
	public function test_get_available_languages_falls_back_to_code() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$wpml_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return [
					'en' => [
						'code' => 'en',
					],
				];
			}
		);

		$result = $this->instance->get_available_languages();
		$this->assertEquals( [ 'en' ], $result );
	}

	/**
	 * Test get_default_language returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_default_language
	 */
	public function test_get_default_language_returns_null_when_not_active() {
		$result = $this->instance->get_default_language();
		$this->assertNull( $result );
	}

	/**
	 * Test get_default_language returns locale from WPML filter.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_default_language
	 */
	public function test_get_default_language_returns_locale_from_wpml_filter() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$default_lang_filter = $this->add_filter_with_safe_teardown(
			'wpml_default_language',
			function() {
				return 'en';
			}
		);

		$active_languages_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return [
					'en' => [
						'code' => 'en',
						'default_locale' => 'en_US',
					],
				];
			}
		);

		$result = $this->instance->get_default_language();
		$this->assertEquals( 'en_US', $result );
	}

	/**
	 * Test get_default_language falls back to code when locale is not available.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_default_language
	 */
	public function test_get_default_language_falls_back_to_code() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$default_lang_filter = $this->add_filter_with_safe_teardown(
			'wpml_default_language',
			function() {
				return 'en';
			}
		);

		$active_languages_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return null;
			}
		);

		$result = $this->instance->get_default_language();
		$this->assertEquals( 'en', $result );
	}

	/**
	 * Test get_product_language returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_product_language
	 */
	public function test_get_product_language_returns_null_when_not_active() {
		$result = $this->instance->get_product_language( 123 );
		$this->assertNull( $result );
	}

	/**
	 * Test get_product_language returns language code from WPML filter.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_product_language
	 */
	public function test_get_product_language_returns_language_code() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$wpml_filter = $this->add_filter_with_safe_teardown(
			'wpml_post_language_details',
			function( $empty, $product_id ) {
				if ( 123 === $product_id ) {
					return [
						'language_code' => 'es',
					];
				}
				return null;
			},
			10,
			2
		);

		$result = $this->instance->get_product_language( 123 );
		$this->assertEquals( 'es', $result );
	}

	/**
	 * Test switch_to_language returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::switch_to_language
	 */
	public function test_switch_to_language_returns_null_when_not_active() {
		$result = $this->instance->switch_to_language( 'es_ES' );
		$this->assertNull( $result );
	}

	/**
	 * Test switch_to_language switches language and returns original.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::switch_to_language
	 */
	public function test_switch_to_language_switches_and_returns_original() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$current_lang_filter = $this->add_filter_with_safe_teardown(
			'wpml_current_language',
			function() {
				return 'en';
			}
		);

		$active_languages_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return [
					'en' => [
						'code' => 'en',
						'default_locale' => 'en_US',
					],
					'es' => [
						'code' => 'es',
						'default_locale' => 'es_ES',
					],
				];
			}
		);

		$switched_to = null;
		$switch_action = $this->add_filter_with_safe_teardown(
			'wpml_switch_language',
			function( $lang ) use ( &$switched_to ) {
				$switched_to = $lang;
			}
		);

		$result = $this->instance->switch_to_language( 'es_ES' );
		$this->assertEquals( 'en', $result );
		$this->assertEquals( 'es', $switched_to );
	}

	/**
	 * Test restore_language does nothing when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::restore_language
	 */
	public function test_restore_language_does_nothing_when_not_active() {
		$this->instance->restore_language( 'en' );
		$this->assertTrue( true );
	}

	/**
	 * Test restore_language switches back to original language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::restore_language
	 */
	public function test_restore_language_switches_back() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$switched_to = null;
		$switch_action = $this->add_filter_with_safe_teardown(
			'wpml_switch_language',
			function( $lang ) use ( &$switched_to ) {
				$switched_to = $lang;
			}
		);

		$this->instance->restore_language( 'en' );
		$this->assertEquals( 'en', $switched_to );
	}

	/**
	 * Test get_products_from_default_language returns empty when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_returns_empty_when_not_active() {
		$result = $this->instance->get_products_from_default_language();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_product_translation_details returns empty when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_product_translation_details
	 */
	public function test_get_product_translation_details_returns_empty_when_not_active() {
		$result = $this->instance->get_product_translation_details( 123 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_product_translation_details returns translation information.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_product_translation_details
	 */
	public function test_get_product_translation_details_returns_translation_info() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$product = \WC_Helper_Product::create_simple_product();

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$default_lang_filter = $this->add_filter_with_safe_teardown(
			'wpml_default_language',
			function() {
				return 'en';
			}
		);

		$active_languages_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return [
					'en' => [
						'code' => 'en',
						'default_locale' => 'en_US',
					],
					'es' => [
						'code' => 'es',
						'default_locale' => 'es_ES',
					],
				];
			}
		);

		$object_id_filter = $this->add_filter_with_safe_teardown(
			'wpml_object_id',
			function( $product_id, $post_type, $return_original, $language_code ) {
				if ( 'es' === $language_code ) {
					return 999;
				}
				return $product_id;
			},
			10,
			4
		);

		$result = $this->instance->get_product_translation_details( $product->get_id() );
		$this->assertIsArray( $result );
		$this->assertEquals( $product->get_id(), $result['product_id'] );
		$this->assertEquals( 'en_US', $result['default_language'] );
		$this->assertArrayHasKey( 'translations', $result );
		$this->assertArrayHasKey( 'es_ES', $result['translations'] );
		$this->assertEquals( 999, $result['translations']['es_ES'] );

		wp_delete_post( $product->get_id(), true );
	}

	/**
	 * Test get_availability_data includes WPML-specific data.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_availability_data
	 */
	public function test_get_availability_data_includes_wpml_specific_data() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$active_plugins_filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$default_lang_filter = $this->add_filter_with_safe_teardown(
			'wpml_default_language',
			function() {
				return 'en';
			}
		);

		$active_languages_filter = $this->add_filter_with_safe_teardown(
			'wpml_active_languages',
			function() {
				return [
					'en' => [
						'code' => 'en',
						'default_locale' => 'en_US',
					],
				];
			}
		);

		$result = $this->instance->get_availability_data();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'plugin_name', $result );
		$this->assertEquals( 'WPML', $result['plugin_name'] );
		$this->assertArrayHasKey( 'languages', $result );
		$this->assertArrayHasKey( 'default_language', $result );
		$this->assertArrayHasKey( 'has_legacy_multi_language_setup', $result );
	}

	/**
	 * Test get_integration_status returns not available when not installed.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::get_integration_status
	 */
	public function test_get_integration_status_returns_not_available_when_not_installed() {
		$this->assertEquals( 'Not Available', $this->instance->get_integration_status() );
	}

	/**
	 * Test has_legacy_multi_language_setup returns false when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::has_legacy_multi_language_setup
	 */
	public function test_has_legacy_multi_language_setup_returns_false_when_not_active() {
		$this->assertFalse( $this->instance->has_legacy_multi_language_setup() );
	}

	/**
	 * Test has_legacy_multi_language_setup returns false when no settings.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::has_legacy_multi_language_setup
	 */
	public function test_has_legacy_multi_language_setup_returns_false_when_no_settings() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		$this->assertFalse( $this->instance->has_legacy_multi_language_setup() );
	}

	/**
	 * Test has_legacy_multi_language_setup returns false when one visible language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::has_legacy_multi_language_setup
	 */
	public function test_has_legacy_multi_language_setup_returns_false_when_one_visible_language() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		update_option( 'fb_wmpl_language_visibility', [ 'en' => 1 ] );

		$this->assertFalse( $this->instance->has_legacy_multi_language_setup() );
	}

	/**
	 * Test has_legacy_multi_language_setup returns true when multiple visible languages.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::has_legacy_multi_language_setup
	 */
	public function test_has_legacy_multi_language_setup_returns_true_when_multiple_visible_languages() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		update_option( 'fb_wmpl_language_visibility', [ 'en' => 1, 'es' => 1 ] );

		$this->assertTrue( $this->instance->has_legacy_multi_language_setup() );
	}

	/**
	 * Test is_eligible_for_language_override_feeds returns true when no legacy setup.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::is_eligible_for_language_override_feeds
	 */
	public function test_is_eligible_for_language_override_feeds_returns_true_when_no_legacy_setup() {
		$this->assertTrue( $this->instance->is_eligible_for_language_override_feeds() );
	}

	/**
	 * Test is_eligible_for_language_override_feeds returns false when legacy setup.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\WPML::is_eligible_for_language_override_feeds
	 */
	public function test_is_eligible_for_language_override_feeds_returns_false_when_legacy_setup() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.5.0' );
		}

		$filter = $this->add_filter_with_safe_teardown(
			'option_active_plugins',
			function() {
				return [ 'sitepress-multilingual-cms/sitepress.php' ];
			}
		);

		update_option( 'fb_wmpl_language_visibility', [ 'en' => 1, 'es' => 1 ] );

		$this->assertFalse( $this->instance->is_eligible_for_language_override_feeds() );
	}
}
