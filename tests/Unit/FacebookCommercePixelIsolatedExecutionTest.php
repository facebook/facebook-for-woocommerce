<?php
declare( strict_types=1 );

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WC_Facebookcommerce_Pixel isolated execution context methods.
 *
 * These tests validate moving from shared JavaScript execution context (wc_enqueue_js)
 * to isolated external script execution (wp_enqueue_script + wp_localize_script).
 *
 * @package FacebookCommerce
 */
class FacebookCommercePixelIsolatedExecutionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Reset static properties before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset static properties using reflection
		$this->reset_static_properties();

		// Set up a valid pixel ID for tests
		$this->set_pixel_id( '123456789' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->reset_static_properties();
		parent::tearDown();
	}

	/**
	 * Reset static properties on WC_Facebookcommerce_Pixel class.
	 * Note: setAccessible() is deprecated in PHP 8.5 but still required for PHP < 8.1
	 */
	private function reset_static_properties(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Pixel::class );

		// Reset $static_events
		$static_events = $reflection->getProperty( 'static_events' );
		$static_events->setAccessible( true );
		$static_events->setValue( null, [] );

		// Reset $script_enqueued
		$script_enqueued = $reflection->getProperty( 'script_enqueued' );
		$script_enqueued->setAccessible( true );
		$script_enqueued->setValue( null, false );

		// Reset $hooks_initialized
		$hooks_initialized = $reflection->getProperty( 'hooks_initialized' );
		$hooks_initialized->setAccessible( true );
		$hooks_initialized->setValue( null, false );
	}

	/**
	 * Helper to set pixel ID for tests.
	 */
	private function set_pixel_id( string $pixel_id ): void {
		update_option(
			WC_Facebookcommerce_Pixel::SETTINGS_KEY,
			array( WC_Facebookcommerce_Pixel::PIXEL_ID_KEY => $pixel_id )
		);
	}

	/**
	 * Helper to get static events via reflection.
	 */
	private function get_static_events(): array {
		$reflection    = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$static_events = $reflection->getProperty( 'static_events' );
		$static_events->setAccessible( true );
		return $static_events->getValue();
	}

	// =========================================================================
	// add_static_event() Tests
	// =========================================================================

	public function test_add_static_event_adds_event_to_queue(): void {
		WC_Facebookcommerce_Pixel::add_static_event(
			'ViewContent',
			array(
				'content_ids'  => array( '123' ),
				'content_type' => 'product',
				'value'        => 29.99,
				'currency'     => 'USD',
			)
		);

		$events = $this->get_static_events();

		$this->assertCount( 1, $events );
		$this->assertEquals( 'ViewContent', $events[0]['name'] );
		$this->assertEquals( 'track', $events[0]['method'] );
		$this->assertEquals( array( '123' ), $events[0]['params']['content_ids'] );
	}

	public function test_add_static_event_with_custom_method(): void {
		WC_Facebookcommerce_Pixel::add_static_event(
			'CustomEvent',
			array( 'key' => 'value' ),
			'trackCustom'
		);

		$events = $this->get_static_events();

		$this->assertCount( 1, $events );
		$this->assertEquals( 'trackCustom', $events[0]['method'] );
	}

	public function test_add_static_event_with_event_id(): void {
		WC_Facebookcommerce_Pixel::add_static_event(
			'AddToCart',
			array( 'content_ids' => array( '456' ) ),
			'track',
			'abc123-event-id'
		);

		$events = $this->get_static_events();

		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'eventId', $events[0] );
		$this->assertEquals( 'abc123-event-id', $events[0]['eventId'] );
	}

	public function test_add_static_event_without_event_id_does_not_include_key(): void {
		WC_Facebookcommerce_Pixel::add_static_event(
			'ViewContent',
			array( 'content_ids' => array( '789' ) )
		);

		$events = $this->get_static_events();

		$this->assertCount( 1, $events );
		$this->assertArrayNotHasKey( 'eventId', $events[0] );
	}

	public function test_add_static_event_multiple_events(): void {
		WC_Facebookcommerce_Pixel::add_static_event( 'ViewContent', array( 'id' => '1' ) );
		WC_Facebookcommerce_Pixel::add_static_event( 'AddToCart', array( 'id' => '2' ) );
		WC_Facebookcommerce_Pixel::add_static_event( 'Purchase', array( 'id' => '3' ) );

		$events = $this->get_static_events();

		$this->assertCount( 3, $events );
		$this->assertEquals( 'ViewContent', $events[0]['name'] );
		$this->assertEquals( 'AddToCart', $events[1]['name'] );
		$this->assertEquals( 'Purchase', $events[2]['name'] );
	}

	public function test_add_static_event_initializes_hooks(): void {
		// Hooks should not be initialized yet
		$reflection        = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$hooks_initialized = $reflection->getProperty( 'hooks_initialized' );
		$hooks_initialized->setAccessible( true );

		$this->assertFalse( $hooks_initialized->getValue() );

		// Adding an event should initialize hooks
		WC_Facebookcommerce_Pixel::add_static_event( 'ViewContent', array() );

		$this->assertTrue( $hooks_initialized->getValue() );
	}

	// =========================================================================
	// prepare_event_params() Tests
	// =========================================================================

	public function test_prepare_event_params_removes_event_name(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$method     = $reflection->getMethod( 'prepare_event_params' );
		$method->setAccessible( true );

		$params = array(
			'event_name'   => 'ViewContent',
			'content_ids'  => array( '123' ),
			'content_type' => 'product',
		);

		$result = $method->invoke( null, $params, 'ViewContent' );

		$this->assertArrayNotHasKey( 'event_name', $result['params'] );
		$this->assertArrayHasKey( 'content_ids', $result['params'] );
	}

	public function test_prepare_event_params_extracts_event_id(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$method     = $reflection->getMethod( 'prepare_event_params' );
		$method->setAccessible( true );

		$params = array(
			'event_id'    => 'my-unique-event-id',
			'content_ids' => array( '123' ),
		);

		$result = $method->invoke( null, $params, 'ViewContent' );

		$this->assertEquals( 'my-unique-event-id', $result['event_id'] );
		$this->assertArrayNotHasKey( 'event_id', $result['params'] );
	}

	public function test_prepare_event_params_unwraps_custom_data(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$method     = $reflection->getMethod( 'prepare_event_params' );
		$method->setAccessible( true );

		$params = array(
			'custom_data' => array(
				'content_ids'  => array( '123' ),
				'content_type' => 'product',
				'value'        => 29.99,
			),
		);

		$result = $method->invoke( null, $params, 'ViewContent' );

		$this->assertArrayHasKey( 'content_ids', $result['params'] );
		$this->assertArrayHasKey( 'value', $result['params'] );
		$this->assertArrayNotHasKey( 'custom_data', $result['params'] );
	}

	public function test_prepare_event_params_returns_empty_event_id_when_not_present(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$method     = $reflection->getMethod( 'prepare_event_params' );
		$method->setAccessible( true );

		$params = array( 'content_ids' => array( '123' ) );

		$result = $method->invoke( null, $params, 'ViewContent' );

		$this->assertEquals( '', $result['event_id'] );
	}

	public function test_prepare_event_params_adds_version_info(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$method     = $reflection->getMethod( 'prepare_event_params' );
		$method->setAccessible( true );

		$params = array( 'content_ids' => array( '123' ) );

		$result = $method->invoke( null, $params, 'ViewContent' );

		// Version info should be added by build_params()
		$this->assertArrayHasKey( 'source', $result['params'] );
		$this->assertArrayHasKey( 'pluginVersion', $result['params'] );
	}

	// =========================================================================
	// init_external_js_hooks() Tests
	// =========================================================================

	public function test_init_external_js_hooks_registers_wp_enqueue_scripts_action(): void {
		WC_Facebookcommerce_Pixel::init_external_js_hooks();

		$this->assertNotFalse(
			has_action( 'wp_enqueue_scripts', array( WC_Facebookcommerce_Pixel::class, 'enqueue_pixel_events_script' ) )
		);
	}

	public function test_init_external_js_hooks_registers_wp_footer_action(): void {
		WC_Facebookcommerce_Pixel::init_external_js_hooks();

		$this->assertNotFalse(
			has_action( 'wp_footer', array( WC_Facebookcommerce_Pixel::class, 'localize_pixel_events_data' ) )
		);
	}

	public function test_init_external_js_hooks_only_initializes_once(): void {
		WC_Facebookcommerce_Pixel::init_external_js_hooks();
		WC_Facebookcommerce_Pixel::init_external_js_hooks();
		WC_Facebookcommerce_Pixel::init_external_js_hooks();

		// Should only have one callback registered (not multiple)
		$reflection        = new ReflectionClass( WC_Facebookcommerce_Pixel::class );
		$hooks_initialized = $reflection->getProperty( 'hooks_initialized' );
		$hooks_initialized->setAccessible( true );

		$this->assertTrue( $hooks_initialized->getValue() );
	}

	// =========================================================================
	// WC_Facebookcommerce_Utils::get_deferred_events_transient_key() Tests
	// =========================================================================

	public function test_get_deferred_events_transient_key_returns_string(): void {
		$reflection = new ReflectionClass( WC_Facebookcommerce_Utils::class );
		$method     = $reflection->getMethod( 'get_deferred_events_transient_key' );
		$method->setAccessible( true );

		$key = $method->invoke( null );

		// Key should be a string (could be empty if no session)
		$this->assertIsString( $key );
	}

	// =========================================================================
	// Event Data Structure Tests
	// =========================================================================

	public function test_static_event_has_correct_structure(): void {
		WC_Facebookcommerce_Pixel::add_static_event(
			'ViewContent',
			array(
				'content_ids'  => array( 'SKU123' ),
				'content_type' => 'product',
				'value'        => 99.99,
				'currency'     => 'USD',
			),
			'track',
			'unique-event-123'
		);

		$events = $this->get_static_events();
		$event  = $events[0];

		// Verify structure matches what pixel-events.js expects
		$this->assertArrayHasKey( 'name', $event );
		$this->assertArrayHasKey( 'params', $event );
		$this->assertArrayHasKey( 'method', $event );
		$this->assertArrayHasKey( 'eventId', $event );

		// Verify types
		$this->assertIsString( $event['name'] );
		$this->assertIsArray( $event['params'] );
		$this->assertIsString( $event['method'] );
		$this->assertIsString( $event['eventId'] );
	}

	public function test_static_event_params_are_preserved(): void {
		$original_params = array(
			'content_ids'   => array( 'SKU123', 'SKU456' ),
			'content_type'  => 'product',
			'value'         => 149.99,
			'currency'      => 'EUR',
			'content_name'  => 'Test Product',
			'num_items'     => 2,
		);

		WC_Facebookcommerce_Pixel::add_static_event( 'Purchase', $original_params );

		$events = $this->get_static_events();
		$params = $events[0]['params'];

		$this->assertEquals( $original_params['content_ids'], $params['content_ids'] );
		$this->assertEquals( $original_params['value'], $params['value'] );
		$this->assertEquals( $original_params['currency'], $params['currency'] );
	}
}
