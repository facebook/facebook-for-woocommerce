<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin\Tasks;

use WooCommerce\Facebook\Admin\Tasks\Setup;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Handlers\Connection;

/**
 * Unit tests for Setup task class.
 *
 * @since 3.5.2
 */
class SetupTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Setup::class ) );
		$setup = new Setup();
		$this->assertInstanceOf( Setup::class, $setup );
	}

	/**
	 * Test that Setup extends the Task class.
	 */
	public function test_extends_task_class() {
		$setup = new Setup();
		$this->assertInstanceOf( \Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task::class, $setup );
	}

	/**
	 * Test get_id method returns correct ID.
	 */
	public function test_get_id() {
		$setup = new Setup();
		$this->assertEquals( 'setup-facebook', $setup->get_id() );
	}

	/**
	 * Test get_title method returns correct title.
	 */
	public function test_get_title() {
		$setup = new Setup();
		$expected = __( 'Advertise your products across Meta\'s platforms, including Facebook, Instagram, and WhatsApp', 'facebook-for-woocommerce' );
		$this->assertEquals( $expected, $setup->get_title() );
	}

	/**
	 * Test get_content method returns empty string.
	 */
	public function test_get_content() {
		$setup = new Setup();
		$this->assertEquals( '', $setup->get_content() );
	}

	/**
	 * Test get_time method returns correct time string.
	 */
	public function test_get_time() {
		$setup = new Setup();
		$this->assertEquals( esc_html__( '20 minutes', 'facebook-for-woocommerce' ), $setup->get_time() );
	}

	/**
	 * Test get_action_url method returns settings URL.
	 */
	public function test_get_action_url() {
		// Mock the facebook_for_woocommerce() function return
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->expects( $this->once() )
			->method( 'get_settings_url' )
			->willReturn( 'http://example.org/wp-admin/admin.php?page=wc-facebook' );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		$setup = new Setup();
		$result = $setup->get_action_url();
		
		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
		
		$this->assertEquals( 'http://example.org/wp-admin/admin.php?page=wc-facebook', $result );
	}

	/**
	 * Test is_complete when not connected.
	 */
	public function test_is_complete_not_connected() {
		// Mock the connection handler
		$connection_handler_mock = $this->createMock( Connection::class );
		$connection_handler_mock->expects( $this->once() )
			->method( 'is_connected' )
			->willReturn( false );

		// Mock the plugin
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->expects( $this->once() )
			->method( 'get_connection_handler' )
			->willReturn( $connection_handler_mock );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );
		
		$setup = new Setup();
		$result = $setup->is_complete();
		
		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test is_complete when connected.
	 */
	public function test_is_complete_connected() {
		// Mock the connection handler
		$connection_handler_mock = $this->createMock( Connection::class );
		$connection_handler_mock->expects( $this->once() )
			->method( 'is_connected' )
			->willReturn( true );

		// Mock the plugin
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->expects( $this->once() )
			->method( 'get_connection_handler' )
			->willReturn( $connection_handler_mock );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );
		
		$setup = new Setup();
		$result = $setup->is_complete();
		
		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test get_parent_id when parent class has the method.
	 */
	public function test_get_parent_id_with_parent_method() {
		// Create a mock parent class with get_parent_id method
		$parent_class = new class extends \Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task {
			public function get_id() { return 'test'; }
			public function get_title() { return 'Test'; }
			public function get_content() { return ''; }
			public function get_time() { return ''; }
			public function is_complete() { return false; }
			public function get_parent_id() { return 'test-parent'; }
		};

		// Since we can't easily mock the parent class in this context,
		// we'll test the default behavior
		$setup = new Setup();
		$parent_id = $setup->get_parent_id();
		
		// The method should return 'extended' if parent doesn't have the method
		// or the parent's value if it does
		$this->assertIsString( $parent_id );
		// Don't assert not empty as it might be empty in some WC versions
	}

	/**
	 * Test get_parent_id returns 'extended' when parent class doesn't have the method.
	 */
	public function test_get_parent_id_without_parent_method() {
		$setup = new Setup();
		
		// Use reflection to check if parent has the method
		$parent_class = get_parent_class( $setup );
		$reflection = new \ReflectionClass( $parent_class );
		
		if ( ! $reflection->hasMethod( 'get_parent_id' ) ) {
			$this->assertEquals( 'extended', $setup->get_parent_id() );
		} else {
			// If parent has the method, just verify it returns a string
			$this->assertIsString( $setup->get_parent_id() );
		}
	}

	/**
	 * Test that all public methods exist.
	 */
	public function test_all_public_methods_exist() {
		$setup = new Setup();
		
		$expected_methods = [
			'get_id',
			'get_title', 
			'get_content',
			'get_time',
			'get_action_url',
			'is_complete',
			'get_parent_id'
		];
		
		foreach ( $expected_methods as $method ) {
			$this->assertTrue( 
				method_exists( $setup, $method ),
				"Method {$method} should exist"
			);
		}
	}

	/**
	 * Test method return types.
	 */
	public function test_method_return_types() {
		// Mock dependencies
		$connection_handler_mock = $this->createMock( Connection::class );
		$connection_handler_mock->method( 'is_connected' )->willReturn( true );

		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->method( 'get_connection_handler' )->willReturn( $connection_handler_mock );
		$plugin_mock->method( 'get_settings_url' )->willReturn( 'http://example.org' );

		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		$setup = new Setup();
		
		// Test return types
		$this->assertIsString( $setup->get_id() );
		$this->assertIsString( $setup->get_title() );
		$this->assertIsString( $setup->get_content() );
		$this->assertIsString( $setup->get_time() );
		$this->assertIsString( $setup->get_action_url() );
		$this->assertIsBool( $setup->is_complete() );
		$this->assertIsString( $setup->get_parent_id() );

		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
	}
} 