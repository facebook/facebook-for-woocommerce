<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Handlers\WhatsAppConnection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Tests for the WhatsAppConnection class.
 *
 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection
 */
class WhatsAppConnectionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var WhatsAppConnection */
	private $instance;

	/** @var \WC_Facebookcommerce|\PHPUnit\Framework\MockObject\MockObject */
	private $plugin;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a mock plugin instance
		$this->plugin = $this->getMockBuilder( \WC_Facebookcommerce::class )
			->disableOriginalConstructor()
			->getMock();

		$this->instance = new WhatsAppConnection( $this->plugin );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that the class can be instantiated.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::__construct
	 */
	public function test_constructor_instantiates_class() {
		$this->assertInstanceOf( WhatsAppConnection::class, $this->instance );
	}

	/**
	 * Test that the plugin property is set correctly in constructor.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::__construct
	 */
	public function test_constructor_sets_plugin_property() {
		$reflection = new \ReflectionClass( $this->instance );
		$property = $reflection->getProperty( 'plugin' );
		$property->setAccessible( true );

		$this->assertSame( $this->plugin, $property->getValue( $this->instance ) );
	}

	/**
	 * Test get_access_token returns the stored access token.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_access_token
	 */
	public function test_get_access_token_returns_stored_value() {
		$expected_token = 'test_access_token_12345';
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, $expected_token );

		$result = $this->instance->get_access_token();

		$this->assertIsString( $result );
		$this->assertEquals( $expected_token, $result );
	}

	/**
	 * Test get_access_token returns empty string when not set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_access_token
	 */
	public function test_get_access_token_returns_empty_string_when_not_set() {
		$result = $this->instance->get_access_token();

		$this->assertIsString( $result );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_access_token returns empty string as default.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_access_token
	 */
	public function test_get_access_token_returns_default_empty_string() {
		// Don't set any option value
		$result = $this->instance->get_access_token();

		$this->assertIsString( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_wa_installation_id returns the stored installation ID.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_wa_installation_id
	 */
	public function test_get_wa_installation_id_returns_stored_value() {
		$expected_id = 'installation_id_67890';
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_INSTALLATION_ID, $expected_id );

		$result = $this->instance->get_wa_installation_id();

		$this->assertIsString( $result );
		$this->assertEquals( $expected_id, $result );
	}

	/**
	 * Test get_wa_installation_id returns empty string when not set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_wa_installation_id
	 */
	public function test_get_wa_installation_id_returns_empty_string_when_not_set() {
		$result = $this->instance->get_wa_installation_id();

		$this->assertIsString( $result );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_wa_installation_id returns empty string as default.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_wa_installation_id
	 */
	public function test_get_wa_installation_id_returns_default_empty_string() {
		// Don't set any option value
		$result = $this->instance->get_wa_installation_id();

		$this->assertIsString( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test is_connected returns true when access token exists.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::is_connected
	 */
	public function test_is_connected_returns_true_when_access_token_exists() {
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, 'valid_token' );

		$result = $this->instance->is_connected();

		$this->assertIsBool( $result );
		$this->assertTrue( $result );
	}

	/**
	 * Test is_connected returns false when access token is empty.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::is_connected
	 */
	public function test_is_connected_returns_false_when_access_token_is_empty() {
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, '' );

		$result = $this->instance->is_connected();

		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}

	/**
	 * Test is_connected returns false when access token is not set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::is_connected
	 */
	public function test_is_connected_returns_false_when_access_token_not_set() {
		// Don't set any option value
		$result = $this->instance->is_connected();

		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_whatsapp_external_id returns stored external ID.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_returns_stored_value() {
		$expected_id = 'existing_external_id_123';
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, $expected_id );

		$result = $this->instance->get_whatsapp_external_id();

		$this->assertIsString( $result );
		$this->assertEquals( $expected_id, $result );
	}

	/**
	 * Test get_whatsapp_external_id generates ID from blog name when not set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_generates_from_blog_name() {
		// Mock WordPress functions
		add_filter( 'pre_option_blogname', function() {
			return 'My Test Blog';
		} );

		$result = $this->instance->get_whatsapp_external_id();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringStartsWith( 'mytestblog-', $result );
		
		// Verify the generated ID was stored
		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, $result );
	}

	/**
	 * Test get_whatsapp_external_id generates ID from URL when blog name is empty.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_generates_from_url_when_blog_name_empty() {
		// Mock WordPress functions
		add_filter( 'pre_option_blogname', function() {
			return '';
		} );
		
		add_filter( 'pre_option_siteurl', function() {
			return 'https://www.example.com';
		} );

		$result = $this->instance->get_whatsapp_external_id();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringStartsWith( 'examplecom-', $result );
		
		// Verify the generated ID was stored
		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, $result );
	}

	/**
	 * Test get_whatsapp_external_id caches the value in property.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_caches_value_in_property() {
		$expected_id = 'cached_external_id';
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, $expected_id );

		// First call
		$result1 = $this->instance->get_whatsapp_external_id();
		
		// Second call should return cached value
		$result2 = $this->instance->get_whatsapp_external_id();

		$this->assertEquals( $result1, $result2 );
		$this->assertEquals( $expected_id, $result2 );
	}

	/**
	 * Test get_whatsapp_external_id returns same ID on subsequent calls.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_returns_same_id_on_subsequent_calls() {
		add_filter( 'pre_option_blogname', function() {
			return 'Test Site';
		} );

		// First call generates and stores ID
		$first_result = $this->instance->get_whatsapp_external_id();
		
		// Second call should return the same ID (from cache)
		$second_result = $this->instance->get_whatsapp_external_id();

		$this->assertEquals( $first_result, $second_result );
	}

	/**
	 * Test get_whatsapp_external_id handles special characters in blog name.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_handles_special_characters() {
		add_filter( 'pre_option_blogname', function() {
			return 'My @#$% Special! Blog & Co.';
		} );

		$result = $this->instance->get_whatsapp_external_id();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		// sanitize_key should remove special characters
		$this->assertStringStartsWith( 'myspecialblogco-', $result );
	}

	/**
	 * Test get_whatsapp_external_id handles URL with http/https/www.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_strips_url_prefixes() {
		add_filter( 'pre_option_blogname', function() {
			return '';
		} );
		
		add_filter( 'pre_option_siteurl', function() {
			return 'https://www.mysite.com';
		} );

		$result = $this->instance->get_whatsapp_external_id();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		// Should strip http, https, www
		$this->assertStringStartsWith( 'mysitecom-', $result );
	}

	/**
	 * Test update_whatsapp_external_business_id with valid string.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_with_valid_string() {
		$test_value = 'new_external_id_456';

		$this->instance->update_whatsapp_external_business_id( $test_value );

		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, $test_value );
	}

	/**
	 * Test update_whatsapp_external_business_id with empty string.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_with_empty_string() {
		$this->instance->update_whatsapp_external_business_id( '' );

		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, '' );
	}

	/**
	 * Test update_whatsapp_external_business_id with null value.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_with_null() {
		$this->instance->update_whatsapp_external_business_id( null );

		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, '' );
	}

	/**
	 * Test update_whatsapp_external_business_id with non-string value.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_with_integer() {
		$this->instance->update_whatsapp_external_business_id( 12345 );

		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, '' );
	}

	/**
	 * Test update_whatsapp_external_business_id with array value.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_with_array() {
		$this->instance->update_whatsapp_external_business_id( array( 'test' ) );

		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, '' );
	}

	/**
	 * Test update_whatsapp_external_business_id persists value.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_persists_value() {
		$test_value = 'persisted_external_id';

		$this->instance->update_whatsapp_external_business_id( $test_value );

		// Verify the value can be retrieved
		$stored_value = $this->mock_get_option( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID );
		$this->assertEquals( $test_value, $stored_value );
	}

	/**
	 * Test update_whatsapp_external_business_id can update existing value.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::update_whatsapp_external_business_id
	 */
	public function test_update_whatsapp_external_business_id_updates_existing_value() {
		// Set initial value
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, 'old_value' );

		// Update with new value
		$new_value = 'updated_value';
		$this->instance->update_whatsapp_external_business_id( $new_value );

		$this->assertOptionUpdated( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, $new_value );
	}

	/**
	 * Test that all option constants are defined correctly.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection
	 */
	public function test_option_constants_are_defined() {
		$this->assertEquals( 'wc_facebook_wa_utility_access_token', WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN );
		$this->assertEquals( 'wc_facebook_wa_external_business_id', WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID );
		$this->assertEquals( 'wc_facebook_wa_business_id', WhatsAppConnection::OPTION_WA_BUSINESS_ID );
		$this->assertEquals( 'wc_facebook_wa_waba_id', WhatsAppConnection::OPTION_WA_WABA_ID );
		$this->assertEquals( 'wc_facebook_wa_phone_number_id', WhatsAppConnection::OPTION_WA_PHONE_NUMBER_ID );
		$this->assertEquals( 'wc_facebook_wa_installation_id', WhatsAppConnection::OPTION_WA_INSTALLATION_ID );
		$this->assertEquals( 'wc_facebook_wa_integration_config_id', WhatsAppConnection::OPTION_WA_INTEGRATION_CONFIG_ID );
	}

	/**
	 * Test get_whatsapp_external_id with empty string stored value.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::get_whatsapp_external_id
	 */
	public function test_get_whatsapp_external_id_generates_when_empty_string_stored() {
		// Set empty string as stored value
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID, '' );

		add_filter( 'pre_option_blogname', function() {
			return 'Generated Blog';
		} );

		$result = $this->instance->get_whatsapp_external_id();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringStartsWith( 'generatedblog-', $result );
	}

	/**
	 * Test is_connected with various truthy access token values.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::is_connected
	 */
	public function test_is_connected_with_various_truthy_values() {
		$truthy_values = array(
			'valid_token',
			'1',
			'true',
			'any_non_empty_string',
		);

		foreach ( $truthy_values as $value ) {
			$this->mock_set_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, $value );
			$result = $this->instance->is_connected();
			$this->assertTrue( $result, "Failed for value: {$value}" );
		}
	}

	/**
	 * Test is_connected with various falsy access token values.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\WhatsAppConnection::is_connected
	 */
	public function test_is_connected_with_various_falsy_values() {
		$falsy_values = array(
			'',
			'0',
			false,
			null,
		);

		foreach ( $falsy_values as $value ) {
			$this->mock_set_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, $value );
			$result = $this->instance->is_connected();
			$this->assertFalse( $result, "Failed for value: " . var_export( $value, true ) );
		}
	}
}
