<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Handlers\PluginRender;
use WooCommerce\Facebook\RolloutSwitches;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Tests for the PluginRender class.
 */
class PluginRenderTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var \WC_Facebookcommerce
	 */
	private $plugin;

	/**
	 * @var PluginRender
	 */
	private $instance;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock the plugin instance
		$this->plugin = $this->getMockBuilder( \WC_Facebookcommerce::class )
			->disableOriginalConstructor()
			->getMock();

		// Create PluginRender instance
		$this->instance = new PluginRender( $this->plugin );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		// Clean up global variables
		$_POST = array();
		$_GET = array();
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test get_opt_out_time returns empty string when option is not set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_time
	 */
	public function test_get_opt_out_time_returns_empty_when_not_set() {
		// Option not set, should return empty string
		$result = PluginRender::get_opt_out_time();

		$this->assertIsString( $result );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_opt_out_time returns value when option is set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_time
	 */
	public function test_get_opt_out_time_returns_value_when_set() {
		$test_date = '2024-01-15 10:30:00';
		$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, $test_date );

		$result = PluginRender::get_opt_out_time();

		$this->assertIsString( $result );
		$this->assertEquals( $test_date, $result );
	}

	/**
	 * Test is_master_sync_on returns true when no opt-out time is set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::is_master_sync_on
	 */
	public function test_is_master_sync_on_returns_true_when_no_opt_out() {
		// No opt-out time set
		$result = PluginRender::is_master_sync_on();

		$this->assertIsBool( $result );
		$this->assertTrue( $result );
	}

	/**
	 * Test is_master_sync_on returns false when opted out.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::is_master_sync_on
	 */
	public function test_is_master_sync_on_returns_false_when_opted_out() {
		$test_date = '2024-01-15 10:30:00';
		$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, $test_date );

		$result = PluginRender::is_master_sync_on();

		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_opted_out_successfully_banner_class when opted out.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opted_out_successfully_banner_class
	 */
	public function test_get_opted_out_successfully_banner_class_when_opted_out() {
		// Set opt-out time to simulate opted out state
		$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, '2024-01-15 10:30:00' );

		$result = PluginRender::get_opted_out_successfully_banner_class();

		$this->assertIsString( $result );
		$this->assertEquals( 'notice notice-success is-dismissible', $result );
		$this->assertStringNotContainsString( 'hidden', $result );
	}

	/**
	 * Test get_opted_out_successfully_banner_class when not opted out.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opted_out_successfully_banner_class
	 */
	public function test_get_opted_out_successfully_banner_class_when_not_opted_out() {
		// No opt-out time set
		$result = PluginRender::get_opted_out_successfully_banner_class();

		$this->assertIsString( $result );
		$this->assertEquals( 'notice notice-success is-dismissible hidden', $result );
		$this->assertStringContainsString( 'hidden', $result );
	}

	/**
	 * Test get_opt_out_banner_class when opted out.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_banner_class
	 */
	public function test_get_opt_out_banner_class_when_opted_out() {
		// Set opt-out time to simulate opted out state
		$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, '2024-01-15 10:30:00' );

		$result = PluginRender::get_opt_out_banner_class();

		$this->assertIsString( $result );
		$this->assertEquals( 'notice notice-info is-dismissible hidden', $result );
		$this->assertStringContainsString( 'hidden', $result );
	}

	/**
	 * Test get_opt_out_banner_class when not opted out.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_banner_class
	 */
	public function test_get_opt_out_banner_class_when_not_opted_out() {
		// No opt-out time set
		$result = PluginRender::get_opt_out_banner_class();

		$this->assertIsString( $result );
		$this->assertEquals( 'notice notice-info is-dismissible', $result );
		$this->assertStringNotContainsString( 'hidden', $result );
	}

	/**
	 * Test get_opt_out_modal_message returns string with expected content.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_modal_message
	 */
	public function test_get_opt_out_modal_message_returns_string() {
		$result = PluginRender::get_opt_out_modal_message();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'Opt out of automatic product sync?', $result );
		$this->assertStringContainsString( '<h4>', $result );
		$this->assertStringContainsString( '<p>', $result );
		$this->assertStringContainsString( 'Meta catalog', $result );
	}

	/**
	 * Test get_opt_out_modal_buttons returns string with expected content.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_modal_buttons
	 */
	public function test_get_opt_out_modal_buttons_returns_string() {
		$result = PluginRender::get_opt_out_modal_buttons();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'Opt out', $result );
		$this->assertStringContainsString( 'modal_opt_out_button', $result );
		$this->assertStringContainsString( '<a', $result );
		$this->assertStringContainsString( 'button', $result );
	}

	/**
	 * Test upcoming_woo_all_products_banner on correct screen.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::upcoming_woo_all_products_banner
	 */
	public function test_upcoming_woo_all_products_banner_on_correct_screen() {
		// Mock get_current_screen
		$screen = new \stdClass();
		$screen->id = 'marketing_page_wc-facebook';
		$GLOBALS['current_screen'] = $screen;

		// Capture output
		ob_start();
		PluginRender::upcoming_woo_all_products_banner();
		$output = ob_get_clean();

		// Verify banner HTML is present
		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'opt_out_banner', $output );
		$this->assertStringContainsString( 'opted_our_successfullly_banner', $output );
		$this->assertStringContainsString( '3.5.3', $output );
		$this->assertStringContainsString( 'Opt out of automatic sync', $output );
	}

	/**
	 * Test upcoming_woo_all_products_banner not on wrong screen.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::upcoming_woo_all_products_banner
	 */
	public function test_upcoming_woo_all_products_banner_not_on_wrong_screen() {
		// Mock get_current_screen with wrong screen
		$screen = new \stdClass();
		$screen->id = 'dashboard';
		$GLOBALS['current_screen'] = $screen;

		// Capture output
		ob_start();
		PluginRender::upcoming_woo_all_products_banner();
		$output = ob_get_clean();

		// Verify no banner HTML is present
		$this->assertEmpty( $output );
	}

	/**
	 * Test upcoming_woo_all_products_banner when screen is not set.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::upcoming_woo_all_products_banner
	 */
	public function test_upcoming_woo_all_products_banner_when_screen_not_set() {
		// No screen set
		unset( $GLOBALS['current_screen'] );

		// Capture output
		ob_start();
		PluginRender::upcoming_woo_all_products_banner();
		$output = ob_get_clean();

		// Verify no banner HTML is present
		$this->assertEmpty( $output );
	}

	/**
	 * Test plugin_updated_banner when master sync is on.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::plugin_updated_banner
	 */
	public function test_plugin_updated_banner_when_master_sync_on() {
		// Mock get_current_screen
		$screen = new \stdClass();
		$screen->id = 'marketing_page_wc-facebook';
		$GLOBALS['current_screen'] = $screen;

		// Master sync is on (no opt-out time)
		// No transient set

		// Capture output
		ob_start();
		PluginRender::plugin_updated_banner();
		$output = ob_get_clean();

		// Verify banner HTML is present
		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'You\'ve updated to the latest plugin version', $output );
		$this->assertStringContainsString( 'all your products automatically sync to Meta', $output );
	}

	/**
	 * Test plugin_updated_banner when master sync is off.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::plugin_updated_banner
	 */
	public function test_plugin_updated_banner_when_master_sync_off() {
		// Mock get_current_screen
		$screen = new \stdClass();
		$screen->id = 'marketing_page_wc-facebook';
		$GLOBALS['current_screen'] = $screen;

		// Set opt-out time to simulate master sync off
		$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, '2024-01-15 10:30:00' );

		// Capture output
		ob_start();
		PluginRender::plugin_updated_banner();
		$output = ob_get_clean();

		// Verify banner HTML contains sync all products button
		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'plugin_updated_successfully_but_master_sync_off', $output );
		$this->assertStringContainsString( 'Sync all products', $output );
		$this->assertStringContainsString( 'sync_all_products', $output );
	}

	/**
	 * Test plugin_updated_banner on wrong screen.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::plugin_updated_banner
	 */
	public function test_plugin_updated_banner_on_wrong_screen() {
		// Mock get_current_screen with wrong screen
		$screen = new \stdClass();
		$screen->id = 'dashboard';
		$GLOBALS['current_screen'] = $screen;

		// Capture output
		ob_start();
		PluginRender::plugin_updated_banner();
		$output = ob_get_clean();

		// Verify no banner HTML is present
		$this->assertEmpty( $output );
	}

	/**
	 * Test opt_out_of_sync_clicked method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::opt_out_of_sync_clicked
	 */
	public function test_opt_out_of_sync_clicked_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'opt_out_of_sync_clicked' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'opt_out_of_sync_clicked' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test sync_all_clicked method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::sync_all_clicked
	 */
	public function test_sync_all_clicked_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'sync_all_clicked' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'sync_all_clicked' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test product_set_banner_closed method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::product_set_banner_closed
	 */
	public function test_product_set_banner_closed_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'product_set_banner_closed' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'product_set_banner_closed' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test reset_upcoming_version_banners method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::reset_upcoming_version_banners
	 */
	public function test_reset_upcoming_version_banners_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'reset_upcoming_version_banners' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'reset_upcoming_version_banners' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test reset_plugin_updated_successfully_banner method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::reset_plugin_updated_successfully_banner
	 */
	public function test_reset_plugin_updated_successfully_banner_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'reset_plugin_updated_successfully_banner' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'reset_plugin_updated_successfully_banner' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test reset_plugin_updated_successfully_but_master_sync_off_banner method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::reset_plugin_updated_successfully_but_master_sync_off_banner
	 */
	public function test_reset_plugin_updated_successfully_but_master_sync_off_banner_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'reset_plugin_updated_successfully_but_master_sync_off_banner' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'reset_plugin_updated_successfully_but_master_sync_off_banner' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test enqueue_assets method exists and has correct signature.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::enqueue_assets
	 */
	public function test_enqueue_assets_method_exists() {
		$this->assertTrue( method_exists( PluginRender::class, 'enqueue_assets' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'enqueue_assets' );
		$this->assertTrue( $reflection->isPublic() );
		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * Test should_show_banners method exists.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::should_show_banners
	 */
	public function test_should_show_banners_method_exists() {
		$this->assertTrue( method_exists( $this->instance, 'should_show_banners' ) );
		
		$reflection = new \ReflectionMethod( PluginRender::class, 'should_show_banners' );
		$this->assertTrue( $reflection->isPublic() );
	}

	/**
	 * Test class constants are defined.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender
	 */
	public function test_class_constants_are_defined() {
		$this->assertTrue( defined( 'WooCommerce\Facebook\Handlers\PluginRender::ALL_PRODUCTS_PLUGIN_VERSION' ) );
		$this->assertEquals( '3.5.3', PluginRender::ALL_PRODUCTS_PLUGIN_VERSION );

		$this->assertTrue( defined( 'WooCommerce\Facebook\Handlers\PluginRender::ACTION_OPT_OUT_OF_SYNC' ) );
		$this->assertEquals( 'wc_facebook_opt_out_of_sync', PluginRender::ACTION_OPT_OUT_OF_SYNC );

		$this->assertTrue( defined( 'WooCommerce\Facebook\Handlers\PluginRender::ACTION_SYNC_BACK_IN' ) );
		$this->assertEquals( 'wc_facebook_sync_back_in', PluginRender::ACTION_SYNC_BACK_IN );

		$this->assertTrue( defined( 'WooCommerce\Facebook\Handlers\PluginRender::MASTER_SYNC_OPT_OUT_TIME' ) );
		$this->assertEquals( 'wc_facebook_master_sync_opt_out_time', PluginRender::MASTER_SYNC_OPT_OUT_TIME );

		$this->assertTrue( defined( 'WooCommerce\Facebook\Handlers\PluginRender::ACTION_CLOSE_BANNER' ) );
		$this->assertEquals( 'wc_banner_close_action', PluginRender::ACTION_CLOSE_BANNER );

		$this->assertTrue( defined( 'WooCommerce\Facebook\Handlers\PluginRender::ACTION_PRODUCT_SET_BANNER_CLOSED' ) );
		$this->assertEquals( 'wc_facebook_product_set_banner_closed', PluginRender::ACTION_PRODUCT_SET_BANNER_CLOSED );
	}

	/**
	 * Test get_opt_out_time with various date formats.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_time
	 */
	public function test_get_opt_out_time_with_various_formats() {
		$test_dates = array(
			'2024-01-15 10:30:00',
			'2023-12-25 00:00:00',
			'2024-06-30 23:59:59',
		);

		foreach ( $test_dates as $test_date ) {
			$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, $test_date );
			$result = PluginRender::get_opt_out_time();
			$this->assertEquals( $test_date, $result );
		}
	}

	/**
	 * Test banner class methods return consistent format.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opted_out_successfully_banner_class
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_banner_class
	 */
	public function test_banner_classes_return_consistent_format() {
		// Test opted out state
		$this->mock_set_option( PluginRender::MASTER_SYNC_OPT_OUT_TIME, '2024-01-15 10:30:00' );
		
		$opted_out_class = PluginRender::get_opted_out_successfully_banner_class();
		$opt_out_class = PluginRender::get_opt_out_banner_class();

		// Both should contain 'notice' and 'is-dismissible'
		$this->assertStringContainsString( 'notice', $opted_out_class );
		$this->assertStringContainsString( 'is-dismissible', $opted_out_class );
		$this->assertStringContainsString( 'notice', $opt_out_class );
		$this->assertStringContainsString( 'is-dismissible', $opt_out_class );

		// When opted out, opted_out banner should be visible, opt_out banner should be hidden
		$this->assertStringNotContainsString( 'hidden', $opted_out_class );
		$this->assertStringContainsString( 'hidden', $opt_out_class );
	}

	/**
	 * Test modal message contains all required information.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_modal_message
	 */
	public function test_modal_message_contains_required_information() {
		$message = PluginRender::get_opt_out_modal_message();

		// Check for key phrases
		$this->assertStringContainsString( 'opt out', $message );
		$this->assertStringContainsString( 'automatic', $message );
		$this->assertStringContainsString( 'sync', $message );
		$this->assertStringContainsString( 'products', $message );
		$this->assertStringContainsString( 'Meta', $message );
		
		// Check for HTML structure
		$this->assertStringContainsString( '<h4>', $message );
		$this->assertStringContainsString( '</h4>', $message );
		$this->assertStringContainsString( '<p>', $message );
		$this->assertStringContainsString( '</p>', $message );
	}

	/**
	 * Test modal buttons contain required elements.
	 *
	 * @covers \WooCommerce\Facebook\Handlers\PluginRender::get_opt_out_modal_buttons
	 */
	public function test_modal_buttons_contain_required_elements() {
		$buttons = PluginRender::get_opt_out_modal_buttons();

		// Check for button structure
		$this->assertStringContainsString( '<a', $buttons );
		$this->assertStringContainsString( 'href=', $buttons );
		$this->assertStringContainsString( 'class=', $buttons );
		$this->assertStringContainsString( 'id=', $buttons );
		
		// Check for specific IDs and classes
		$this->assertStringContainsString( 'modal_opt_out_button', $buttons );
		$this->assertStringContainsString( 'button', $buttons );
		$this->assertStringContainsString( 'wc-forward', $buttons );
	}
}
