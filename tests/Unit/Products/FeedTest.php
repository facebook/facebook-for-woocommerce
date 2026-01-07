<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Products;

use WooCommerce\Facebook\Products\Feed;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WooCommerce\Facebook\Products\Feed class.
 *
 * Tests the product feed functionality including URL generation,
 * feed secret management, and scheduling.
 *
 * @covers \WooCommerce\Facebook\Products\Feed
 */
class FeedTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var Feed|null
	 */
	private $feed;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any cached feed secret
		delete_option( Feed::OPTION_FEED_URL_SECRET );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->feed = null;
		delete_option( Feed::OPTION_FEED_URL_SECRET );
		parent::tearDown();
	}

	/**
	 * Test that GENERATE_FEED_ACTION constant is defined correctly.
	 */
	public function test_generate_feed_action_constant(): void {
		$this->assertSame(
			'wc_facebook_regenerate_feed',
			Feed::GENERATE_FEED_ACTION,
			'GENERATE_FEED_ACTION constant should have the expected value'
		);
	}

	/**
	 * Test that REQUEST_FEED_ACTION constant is defined correctly.
	 */
	public function test_request_feed_action_constant(): void {
		$this->assertSame(
			'wc_facebook_get_feed_data',
			Feed::REQUEST_FEED_ACTION,
			'REQUEST_FEED_ACTION constant should have the expected value'
		);
	}

	/**
	 * Test that OPTION_FEED_URL_SECRET constant is defined correctly.
	 */
	public function test_option_feed_url_secret_constant(): void {
		$this->assertSame(
			'wc_facebook_feed_url_secret',
			Feed::OPTION_FEED_URL_SECRET,
			'OPTION_FEED_URL_SECRET constant should have the expected value'
		);
	}

	/**
	 * Test that FEED_NAME constant is defined correctly.
	 */
	public function test_feed_name_constant(): void {
		$this->assertSame(
			'Product Feed by Facebook for WooCommerce plugin. DO NOT DELETE.',
			Feed::FEED_NAME,
			'FEED_NAME constant should have the expected value'
		);
	}

	/**
	 * Test that get_feed_secret generates and stores a new secret when none exists.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_get_feed_secret_generates_new_secret_when_none_exists(): void {
		// Ensure no secret exists
		delete_option( Feed::OPTION_FEED_URL_SECRET );

		$secret = Feed::get_feed_secret();

		$this->assertNotEmpty( $secret, 'Feed secret should not be empty' );
		$this->assertIsString( $secret, 'Feed secret should be a string' );
		$this->assertGreaterThan( 10, strlen( $secret ), 'Feed secret should be reasonably long' );
	}

	/**
	 * Test that get_feed_secret returns the same secret on subsequent calls.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_get_feed_secret_returns_same_secret_on_subsequent_calls(): void {
		$first_secret  = Feed::get_feed_secret();
		$second_secret = Feed::get_feed_secret();

		$this->assertSame(
			$first_secret,
			$second_secret,
			'Feed secret should be the same on subsequent calls'
		);
	}

	/**
	 * Test that get_feed_secret stores the secret in the database.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_get_feed_secret_stores_secret_in_database(): void {
		// Ensure no secret exists
		delete_option( Feed::OPTION_FEED_URL_SECRET );

		$secret = Feed::get_feed_secret();

		$stored_secret = get_option( Feed::OPTION_FEED_URL_SECRET );
		$this->assertSame(
			$secret,
			$stored_secret,
			'Feed secret should be stored in the database'
		);
	}

	/**
	 * Test that get_feed_secret returns existing secret from database.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_get_feed_secret_returns_existing_secret(): void {
		$existing_secret = 'test_secret_12345';
		update_option( Feed::OPTION_FEED_URL_SECRET, $existing_secret );

		$secret = Feed::get_feed_secret();

		$this->assertSame(
			$existing_secret,
			$secret,
			'Feed secret should return the existing secret from database'
		);
	}

	/**
	 * Test that get_feed_data_url returns a valid URL.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_data_url
	 */
	public function test_get_feed_data_url_returns_valid_url(): void {
		$url = Feed::get_feed_data_url();

		$this->assertNotEmpty( $url, 'Feed data URL should not be empty' );
		$this->assertIsString( $url, 'Feed data URL should be a string' );
		$this->assertStringContainsString( 'wc-api', $url, 'Feed data URL should contain wc-api parameter' );
	}

	/**
	 * Test that get_feed_data_url includes the secret parameter.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_data_url
	 */
	public function test_get_feed_data_url_includes_secret(): void {
		$url    = Feed::get_feed_data_url();
		$secret = Feed::get_feed_secret();

		$this->assertStringContainsString(
			'secret=' . $secret,
			$url,
			'Feed data URL should include the secret parameter'
		);
	}

	/**
	 * Test that get_feed_data_url includes the correct action.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_data_url
	 */
	public function test_get_feed_data_url_includes_correct_action(): void {
		$url = Feed::get_feed_data_url();

		$this->assertStringContainsString(
			'wc-api=' . Feed::REQUEST_FEED_ACTION,
			$url,
			'Feed data URL should include the correct wc-api action'
		);
	}

	/**
	 * Test that get_feed_data_url is based on home URL.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_data_url
	 */
	public function test_get_feed_data_url_is_based_on_home_url(): void {
		$url      = Feed::get_feed_data_url();
		$home_url = home_url( '/' );

		$this->assertStringStartsWith(
			parse_url( $home_url, PHP_URL_SCHEME ) . '://',
			$url,
			'Feed data URL should be based on home URL'
		);
	}

	/**
	 * Test that get_feed_data_url generates consistent URLs.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_data_url
	 */
	public function test_get_feed_data_url_generates_consistent_urls(): void {
		$first_url  = Feed::get_feed_data_url();
		$second_url = Feed::get_feed_data_url();

		$this->assertSame(
			$first_url,
			$second_url,
			'Feed data URL should be consistent across multiple calls'
		);
	}

	/**
	 * Test that Feed constructor can be instantiated.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::__construct
	 */
	public function test_feed_constructor_can_be_instantiated(): void {
		$feed = new Feed();

		$this->assertInstanceOf(
			Feed::class,
			$feed,
			'Feed should be instantiable'
		);
	}

	/**
	 * Test that feed generation action is registered.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::__construct
	 */
	public function test_feed_registers_generation_action(): void {
		$feed = new Feed();

		$this->assertTrue(
			has_action( Feed::GENERATE_FEED_ACTION, array( $feed, 'regenerate_feed' ) ) !== false,
			'Feed should register regenerate_feed action'
		);
	}

	/**
	 * Test that feed request action is registered.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::__construct
	 */
	public function test_feed_registers_request_action(): void {
		$feed = new Feed();

		$this->assertTrue(
			has_action( 'woocommerce_api_' . Feed::REQUEST_FEED_ACTION, array( $feed, 'handle_feed_data_request' ) ) !== false,
			'Feed should register handle_feed_data_request action'
		);
	}

	/**
	 * Test that feed upload request action is registered.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::__construct
	 */
	public function test_feed_registers_upload_request_action(): void {
		$feed = new Feed();

		$this->assertTrue(
			has_action( 'wc_facebook_feed_generation_completed', array( $feed, 'send_request_to_upload_feed' ) ) !== false,
			'Feed should register send_request_to_upload_feed action'
		);
	}

	/**
	 * Test that schedule_feed_generation registers heartbeat action.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::__construct
	 */
	public function test_feed_registers_heartbeat_action(): void {
		$feed = new Feed();

		$this->assertTrue(
			has_action( \WooCommerce\Facebook\Utilities\Heartbeat::HOURLY, array( $feed, 'schedule_feed_generation' ) ) !== false,
			'Feed should register schedule_feed_generation on heartbeat'
		);
	}

	/**
	 * Test that empty secret generates a new one.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_empty_string_secret_generates_new_one(): void {
		update_option( Feed::OPTION_FEED_URL_SECRET, '' );

		$secret = Feed::get_feed_secret();

		$this->assertNotEmpty( $secret, 'Empty string secret should trigger generation of new secret' );
		$this->assertNotSame( '', $secret, 'New secret should not be empty string' );
	}

	/**
	 * Test that feed secret is URL-safe.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_feed_secret_is_url_safe(): void {
		delete_option( Feed::OPTION_FEED_URL_SECRET );

		$secret = Feed::get_feed_secret();

		// URL encode and check if it's the same (meaning no special chars that need encoding)
		$encoded = rawurlencode( $secret );

		$this->assertSame(
			$secret,
			$encoded,
			'Feed secret should be URL-safe (no special characters requiring encoding)'
		);
	}

	/**
	 * Test that multiple Feed instances share the same secret.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_secret
	 */
	public function test_multiple_feed_instances_share_same_secret(): void {
		$secret_before = Feed::get_feed_secret();

		$feed1 = new Feed();
		$feed2 = new Feed();

		$secret_after = Feed::get_feed_secret();

		$this->assertSame(
			$secret_before,
			$secret_after,
			'Feed secret should be shared across all Feed instances'
		);
	}

	/**
	 * Test get_feed_data_url with special characters in home URL.
	 *
	 * @covers \WooCommerce\Facebook\Products\Feed::get_feed_data_url
	 */
	public function test_get_feed_data_url_handles_home_url_correctly(): void {
		$url = Feed::get_feed_data_url();

		// Verify the URL is valid by parsing it
		$parsed = parse_url( $url );

		$this->assertIsArray( $parsed, 'Feed data URL should be parseable' );
		$this->assertArrayHasKey( 'query', $parsed, 'Feed data URL should have query string' );
	}
}
