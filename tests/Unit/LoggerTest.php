<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Framework\Logger;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use Exception;
use RuntimeException;
use InvalidArgumentException;

/**
 * Unit tests for Logger class.
 *
 * @since 3.5.3
 */
class LoggerTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Mock plugin instance.
	 *
	 * @var object
	 */
	private $mock_plugin;

	/**
	 * Mock integration instance.
	 *
	 * @var object
	 */
	private $mock_integration;

	/**
	 * Track transients set during tests.
	 *
	 * @var array
	 */
	private $original_transients = [];

	/**
	 * Track transient values for testing.
	 *
	 * @var array
	 */
	private $test_transients = [];

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->original_transients = [];
		$this->test_transients = [];
		
		// Create mock integration
		$this->mock_integration = $this->createMock( \stdClass::class );
		$this->mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( false );
		
		// Create mock plugin
		$this->mock_plugin = $this->createMock( \stdClass::class );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		$this->mock_plugin->method( 'log' )
			->willReturn( null );
		
		// Mock the global facebook_for_woocommerce() function
		if ( ! function_exists( 'WooCommerce\Facebook\Framework\facebook_for_woocommerce' ) ) {
			eval( '
				namespace WooCommerce\Facebook\Framework;
				function facebook_for_woocommerce() {
					return $GLOBALS["test_mock_plugin"];
				}
			' );
		}
		$GLOBALS['test_mock_plugin'] = $this->mock_plugin;
		
		// Mock transient functions
		$this->add_filter_with_safe_teardown( 'pre_transient_' . Logger::LOGGING_MESSAGE_QUEUE, function( $value ) {
			if ( array_key_exists( Logger::LOGGING_MESSAGE_QUEUE, $this->test_transients ) ) {
				return $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
			}
			return false;
		} );
		
		$this->add_filter_with_safe_teardown( 'pre_set_transient_' . Logger::LOGGING_MESSAGE_QUEUE, function( $value, $expiration, $transient_value ) {
			$this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ] = $transient_value;
			return $transient_value;
		}, 10, 3 );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->test_transients = [];
		unset( $GLOBALS['test_mock_plugin'] );
		parent::tearDown();
	}

	/**
	 * Test that the Logger class exists.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Logger::class ) );
	}

	/**
	 * Test that required constants are defined.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::SETTING_ENABLE_DEBUG_MODE
	 * @covers \WooCommerce\Facebook\Framework\Logger::SETTING_ENABLE_META_DIAGNOSIS
	 * @covers \WooCommerce\Facebook\Framework\Logger::LOGGING_MESSAGE_QUEUE
	 */
	public function test_constants_are_defined() {
		$this->assertTrue( defined( 'WooCommerce\Facebook\Framework\Logger::SETTING_ENABLE_DEBUG_MODE' ) );
		$this->assertEquals( 'wc_facebook_enable_debug_mode', Logger::SETTING_ENABLE_DEBUG_MODE );
		
		$this->assertTrue( defined( 'WooCommerce\Facebook\Framework\Logger::SETTING_ENABLE_META_DIAGNOSIS' ) );
		$this->assertEquals( 'wc_facebook_enable_meta_diagnosis', Logger::SETTING_ENABLE_META_DIAGNOSIS );
		
		$this->assertTrue( defined( 'WooCommerce\Facebook\Framework\Logger::LOGGING_MESSAGE_QUEUE' ) );
		$this->assertEquals( 'global_logging_message_queue', Logger::LOGGING_MESSAGE_QUEUE );
	}

	/**
	 * Test log with minimal parameters.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_minimal_parameters() {
		// Should not throw any errors
		Logger::log( 'Test message' );
		
		// Verify no exception was thrown
		$this->assertTrue( true );
	}

	/**
	 * Test log with context array.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_context() {
		$context = [
			'event' => 'test_event',
			'data' => 'test_data',
		];
		
		Logger::log( 'Test message', $context );
		
		// Verify no exception was thrown
		$this->assertTrue( true );
	}

	/**
	 * Test log with exception object.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_exception() {
		$exception = new Exception( 'Test exception message', 123 );
		$context = [];
		
		// Enable meta diagnosis to capture the context
		$this->mock_integration = $this->createMock( \stdClass::class );
		$this->mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		
		Logger::log( 'Test message', $context, [ 'should_send_log_to_meta' => true ], $exception );
		
		// Verify transient was set with exception details
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertIsArray( $logs );
		$this->assertCount( 1, $logs );
		$this->assertEquals( 'error_log', $logs[0]['event'] );
		$this->assertEquals( 'Test exception message', $logs[0]['exception_message'] );
		$this->assertEquals( 123, $logs[0]['exception_code'] );
		$this->assertEquals( 'Exception', $logs[0]['exception_class'] );
		$this->assertArrayHasKey( 'exception_trace', $logs[0] );
	}

	/**
	 * Test log with exception preserves event from context.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_exception_preserves_event() {
		$exception = new RuntimeException( 'Runtime error' );
		$context = [ 'event' => 'custom_event' ];
		
		// Enable meta diagnosis to capture the context
		$this->mock_integration = $this->createMock( \stdClass::class );
		$this->mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		
		Logger::log( 'Test message', $context, [ 'should_send_log_to_meta' => true ], $exception );
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertEquals( 'custom_event', $logs[0]['event'] );
	}

	/**
	 * Test log with exception adds default event.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_exception_adds_default_event() {
		$exception = new InvalidArgumentException( 'Invalid argument' );
		
		// Enable meta diagnosis to capture the context
		$this->mock_integration = $this->createMock( \stdClass::class );
		$this->mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		
		Logger::log( 'Test message', [], [ 'should_send_log_to_meta' => true ], $exception );
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertEquals( 'error_log', $logs[0]['event'] );
	}

	/**
	 * Test log to WooCommerce when debug enabled.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_woocommerce_when_debug_enabled() {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'yes' );
		
		$mock_plugin = $this->createMock( \stdClass::class );
		$mock_plugin->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->stringContains( 'Test message' ),
				$this->isNull(),
				$this->equalTo( \WC_Log_Levels::DEBUG )
			);
		$mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		
		$GLOBALS['test_mock_plugin'] = $mock_plugin;
		
		Logger::log(
			'Test message',
			[ 'data' => 'test' ],
			[
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level' => \WC_Log_Levels::DEBUG,
			]
		);
	}

	/**
	 * Test log to WooCommerce when debug disabled.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_woocommerce_when_debug_disabled() {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'no' );
		
		$mock_plugin = $this->createMock( \stdClass::class );
		$mock_plugin->expects( $this->never() )
			->method( 'log' );
		$mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		
		$GLOBALS['test_mock_plugin'] = $mock_plugin;
		
		Logger::log(
			'Test message',
			[],
			[ 'should_save_log_in_woocommerce' => true ]
		);
	}

	/**
	 * Test log to WooCommerce with custom log level.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_woocommerce_with_custom_log_level() {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'yes' );
		
		$mock_plugin = $this->createMock( \stdClass::class );
		$mock_plugin->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->isNull(),
				$this->equalTo( \WC_Log_Levels::ERROR )
			);
		$mock_plugin->method( 'get_integration' )
			->willReturn( $this->mock_integration );
		
		$GLOBALS['test_mock_plugin'] = $mock_plugin;
		
		Logger::log(
			'Error message',
			[],
			[
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level' => \WC_Log_Levels::ERROR,
			]
		);
	}

	/**
	 * Test log to Meta when diagnosis enabled.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_when_diagnosis_enabled() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertIsArray( $logs );
		$this->assertCount( 1, $logs );
	}

	/**
	 * Test log to Meta when diagnosis disabled.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_when_diagnosis_disabled() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( false );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$this->assertArrayNotHasKey( Logger::LOGGING_MESSAGE_QUEUE, $this->test_transients );
	}

	/**
	 * Test log to Meta adds message to extra_data.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_adds_message_to_extra_data() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertArrayHasKey( 'extra_data', $logs[0] );
		$this->assertEquals( 'Test message', $logs[0]['extra_data']['message'] );
	}

	/**
	 * Test log to Meta adds PHP version.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_adds_php_version() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertArrayHasKey( 'extra_data', $logs[0] );
		$this->assertEquals( phpversion(), $logs[0]['extra_data']['php_version'] );
	}

	/**
	 * Test log to Meta preserves existing extra_data.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_preserves_existing_extra_data() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log(
			'Test message',
			[
				'event' => 'test_event',
				'extra_data' => [
					'custom_field' => 'custom_value',
				],
			],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertEquals( 'custom_value', $logs[0]['extra_data']['custom_field'] );
		$this->assertEquals( 'Test message', $logs[0]['extra_data']['message'] );
		$this->assertEquals( phpversion(), $logs[0]['extra_data']['php_version'] );
	}

	/**
	 * Test log to Meta creates new transient.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_creates_new_transient() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		// Ensure transient doesn't exist
		$this->assertArrayNotHasKey( Logger::LOGGING_MESSAGE_QUEUE, $this->test_transients );
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$this->assertArrayHasKey( Logger::LOGGING_MESSAGE_QUEUE, $this->test_transients );
		$this->assertIsArray( $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ] );
	}

	/**
	 * Test log to Meta appends to existing transient.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_to_meta_appends_to_existing_transient() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		// Set existing transient
		$this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ] = [
			[ 'event' => 'existing_event' ],
		];
		
		Logger::log(
			'New message',
			[ 'event' => 'new_event' ],
			[ 'should_send_log_to_meta' => true ]
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertCount( 2, $logs );
		$this->assertEquals( 'existing_event', $logs[0]['event'] );
		$this->assertEquals( 'new_event', $logs[1]['event'] );
	}

	/**
	 * Test log with both options enabled.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_both_options_enabled() {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'yes' );
		
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		
		$mock_plugin = $this->createMock( \stdClass::class );
		$mock_plugin->expects( $this->once() )
			->method( 'log' );
		$mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		$GLOBALS['test_mock_plugin'] = $mock_plugin;
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[
				'should_save_log_in_woocommerce' => true,
				'should_send_log_to_meta' => true,
			]
		);
		
		// Verify Meta logging occurred
		$this->assertArrayHasKey( Logger::LOGGING_MESSAGE_QUEUE, $this->test_transients );
	}

	/**
	 * Test log with both options disabled.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_both_options_disabled() {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'no' );
		
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( false );
		
		$mock_plugin = $this->createMock( \stdClass::class );
		$mock_plugin->expects( $this->never() )
			->method( 'log' );
		$mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		$GLOBALS['test_mock_plugin'] = $mock_plugin;
		
		Logger::log(
			'Test message',
			[ 'event' => 'test_event' ],
			[
				'should_save_log_in_woocommerce' => true,
				'should_send_log_to_meta' => true,
			]
		);
		
		// Verify no Meta logging occurred
		$this->assertArrayNotHasKey( Logger::LOGGING_MESSAGE_QUEUE, $this->test_transients );
	}

	/**
	 * Test log with empty message.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_empty_message() {
		Logger::log( '' );
		
		// Should not throw any errors
		$this->assertTrue( true );
	}

	/**
	 * Test log with empty context.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_empty_context() {
		Logger::log( 'Test message', [] );
		
		// Should not throw any errors
		$this->assertTrue( true );
	}

	/**
	 * Test log with complex context.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_complex_context() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		$complex_context = [
			'event' => 'complex_event',
			'nested' => [
				'level1' => [
					'level2' => 'deep_value',
				],
			],
			'object' => (object) [ 'property' => 'value' ],
			'array' => [ 1, 2, 3 ],
		];
		
		Logger::log(
			'Test message',
			$complex_context,
			[ 'should_send_log_to_meta' => true ]
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertEquals( 'complex_event', $logs[0]['event'] );
		$this->assertArrayHasKey( 'nested', $logs[0] );
	}

	/**
	 * Test log with null exception.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_with_null_exception() {
		Logger::log( 'Test message', [], [], null );
		
		// Should not throw any errors
		$this->assertTrue( true );
	}

	/**
	 * Test log exception context structure.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_exception_context_structure() {
		$exception = new RuntimeException( 'Test exception', 456 );
		
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log(
			'Test message',
			[],
			[ 'should_send_log_to_meta' => true ],
			$exception
		);
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$log = $logs[0];
		
		$this->assertArrayHasKey( 'event', $log );
		$this->assertArrayHasKey( 'exception_message', $log );
		$this->assertArrayHasKey( 'exception_trace', $log );
		$this->assertArrayHasKey( 'exception_code', $log );
		$this->assertArrayHasKey( 'exception_class', $log );
		
		$this->assertEquals( 'Test exception', $log['exception_message'] );
		$this->assertEquals( 456, $log['exception_code'] );
		$this->assertEquals( 'RuntimeException', $log['exception_class'] );
		$this->assertIsString( $log['exception_trace'] );
	}

	/**
	 * Test multiple log calls accumulate in transient.
	 *
	 * @covers \WooCommerce\Facebook\Framework\Logger::log
	 */
	public function test_log_multiple_calls_accumulate_in_transient() {
		$mock_integration = $this->createMock( \stdClass::class );
		$mock_integration->method( 'is_meta_diagnosis_enabled' )
			->willReturn( true );
		$this->mock_plugin->method( 'get_integration' )
			->willReturn( $mock_integration );
		
		Logger::log( 'Message 1', [ 'event' => 'event_1' ], [ 'should_send_log_to_meta' => true ] );
		Logger::log( 'Message 2', [ 'event' => 'event_2' ], [ 'should_send_log_to_meta' => true ] );
		Logger::log( 'Message 3', [ 'event' => 'event_3' ], [ 'should_send_log_to_meta' => true ] );
		
		$logs = $this->test_transients[ Logger::LOGGING_MESSAGE_QUEUE ];
		$this->assertCount( 3, $logs );
		$this->assertEquals( 'event_1', $logs[0]['event'] );
		$this->assertEquals( 'event_2', $logs[1]['event'] );
		$this->assertEquals( 'event_3', $logs[2]['event'] );
	}
}
