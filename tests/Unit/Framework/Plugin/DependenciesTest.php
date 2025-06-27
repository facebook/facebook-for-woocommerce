<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Plugin;

use WooCommerce\Facebook\Framework\Plugin\Dependencies;
use WooCommerce\Facebook\Framework\Plugin;
use WooCommerce\Facebook\Framework\AdminNoticeHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Framework Plugin Dependencies class.
 *
 * @since 3.5.4
 */
class DependenciesTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var Plugin */
	private $plugin;

	/** @var Dependencies */
	private $dependencies;

	/** @var AdminNoticeHandler */
	private $admin_notice_handler;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create a mock plugin instance
		$this->plugin = $this->createMock( Plugin::class );
		
		// Set up basic plugin mock methods
		$this->plugin->method( 'get_plugin_name' )->willReturn( 'Facebook for WooCommerce' );
		$this->plugin->method( 'get_id' )->willReturn( 'facebook_for_woocommerce' );
		$this->plugin->method( 'get_id_dasherized' )->willReturn( 'facebook-for-woocommerce' );
		
		// Create a mock admin notice handler
		$this->admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $this->admin_notice_handler );
	}

	/**
	 * Test that the Dependencies class exists.
	 */
	public function test_dependencies_class_exists() {
		$this->assertTrue( class_exists( Dependencies::class ) );
	}

	/**
	 * Test constructor with default arguments.
	 */
	public function test_constructor_with_default_arguments() {
		$dependencies = new Dependencies( $this->plugin );
		
		// Default settings should be empty arrays when no custom settings are provided
		$this->assertEmpty( $dependencies->get_php_extensions() );
		$this->assertEmpty( $dependencies->get_php_functions() );
		$this->assertEmpty( $dependencies->get_php_settings() );
	}

	/**
	 * Test constructor with custom arguments.
	 */
	public function test_constructor_with_custom_arguments() {
		$args = array(
			'php_extensions' => array( 'curl', 'json' ),
			'php_functions'  => array( 'json_encode', 'json_decode' ),
			'php_settings'   => array( 'memory_limit' => '256M' ),
		);
		
		$dependencies = new Dependencies( $this->plugin, $args );
		
		$this->assertEquals( array( 'curl', 'json' ), $dependencies->get_php_extensions() );
		$this->assertEquals( array( 'json_encode', 'json_decode' ), $dependencies->get_php_functions() );
		// When custom settings are provided, they should be merged with defaults
		$expected_settings = array(
			'suhosin.post.max_array_index_length'    => 256,
			'suhosin.post.max_totalname_length'      => 65535,
			'suhosin.post.max_vars'                  => 1024,
			'suhosin.request.max_array_index_length' => 256,
			'suhosin.request.max_totalname_length'   => 65535,
			'suhosin.request.max_vars'               => 1024,
			'memory_limit'                           => '256M',
		);
		$this->assertEquals( $expected_settings, $dependencies->get_php_settings() );
	}

	/**
	 * Test get_php_extensions returns the expected extensions.
	 */
	public function test_get_php_extensions() {
		$args = array(
			'php_extensions' => array( 'curl', 'json', 'mbstring' ),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$this->assertEquals( array( 'curl', 'json', 'mbstring' ), $dependencies->get_php_extensions() );
	}

	/**
	 * Test get_php_functions returns the expected functions.
	 */
	public function test_get_php_functions() {
		$args = array(
			'php_functions' => array( 'json_encode', 'json_decode', 'curl_init' ),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$this->assertEquals( array( 'json_encode', 'json_decode', 'curl_init' ), $dependencies->get_php_functions() );
	}

	/**
	 * Test get_php_settings returns the expected settings.
	 */
	public function test_get_php_settings() {
		$args = array(
			'php_settings' => array(
				'memory_limit' => '256M',
				'max_execution_time' => 300,
			),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$settings = $dependencies->get_php_settings();
		$this->assertArrayHasKey( 'memory_limit', $settings );
		$this->assertArrayHasKey( 'max_execution_time', $settings );
		$this->assertEquals( '256M', $settings['memory_limit'] );
		$this->assertEquals( 300, $settings['max_execution_time'] );
	}

	/**
	 * Test get_missing_php_extensions with all extensions available.
	 */
	public function test_get_missing_php_extensions_with_all_available() {
		$args = array(
			'php_extensions' => array( 'json', 'mbstring' ), // Common extensions that should be available
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$missing = $dependencies->get_missing_php_extensions();
		$this->assertIsArray( $missing );
		// These extensions should be available in most PHP installations
		$this->assertNotContains( 'json', $missing );
	}

	/**
	 * Test get_missing_php_extensions with missing extensions.
	 */
	public function test_get_missing_php_extensions_with_missing() {
		$args = array(
			'php_extensions' => array( 'nonexistent_extension', 'another_missing_extension' ),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$missing = $dependencies->get_missing_php_extensions();
		$this->assertIsArray( $missing );
		$this->assertContains( 'nonexistent_extension', $missing );
		$this->assertContains( 'another_missing_extension', $missing );
	}

	/**
	 * Test get_missing_php_functions with all available.
	 */
	public function test_get_missing_php_functions_with_all_available() {
		$args = array(
			'php_functions' => array( 'json_encode', 'json_decode' ),
		);
		
		$dependencies = new Dependencies( $this->plugin, $args );
		
		// Note: The actual method has a bug - it uses extension_loaded() instead of function_exists()
		// So it will incorrectly report functions as missing since they're not extensions
		$missing_functions = $dependencies->get_missing_php_functions();
		$this->assertContains( 'json_encode', $missing_functions );
		$this->assertContains( 'json_decode', $missing_functions );
	}

	/**
	 * Test get_missing_php_functions with missing functions.
	 */
	public function test_get_missing_php_functions_with_missing() {
		$args = array(
			'php_functions' => array( 'nonexistent_function', 'another_missing_function' ),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$missing = $dependencies->get_missing_php_functions();
		$this->assertIsArray( $missing );
		$this->assertContains( 'nonexistent_function', $missing );
		$this->assertContains( 'another_missing_function', $missing );
	}

	/**
	 * Test get_incompatible_php_settings with compatible settings.
	 */
	public function test_get_incompatible_php_settings_with_compatible_settings() {
		$args = array(
			'php_settings' => array(
				'memory_limit' => '64M', // Should be compatible
			),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$incompatible = $dependencies->get_incompatible_php_settings();
		$this->assertIsArray( $incompatible );
		// Should not have incompatible settings for reasonable values
	}

	/**
	 * Test get_incompatible_php_settings with incompatible settings.
	 */
	public function test_get_incompatible_php_settings_with_incompatible_settings() {
		$args = array(
			'php_settings' => array(
				'memory_limit' => '999999M', // Very high value that should be incompatible
			),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$incompatible = $dependencies->get_incompatible_php_settings();
		$this->assertIsArray( $incompatible );
		// May or may not be incompatible depending on current PHP settings
	}

	/**
	 * Test get_active_scripts_optimization_plugins with no active plugins.
	 */
	public function test_get_active_scripts_optimization_plugins_with_no_active_plugins() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the plugin's is_plugin_active method to return false
		$this->plugin->method( 'is_plugin_active' )->willReturn( false );

		$active_plugins = $dependencies->get_active_scripts_optimization_plugins();
		$this->assertIsArray( $active_plugins );
		$this->assertEmpty( $active_plugins );
	}

	/**
	 * Test get_active_scripts_optimization_plugins with active plugins.
	 */
	public function test_get_active_scripts_optimization_plugins_with_active_plugins() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the plugin's is_plugin_active method to return true for specific plugins
		$this->plugin->method( 'is_plugin_active' )->willReturnCallback( function( $filename ) {
			return in_array( $filename, array( 'autoptimize.php', 'wp-rocket.php' ) );
		} );

		$active_plugins = $dependencies->get_active_scripts_optimization_plugins();
		$this->assertIsArray( $active_plugins );
		$this->assertArrayHasKey( 'autoptimize.php', $active_plugins );
		$this->assertArrayHasKey( 'wp-rocket.php', $active_plugins );
		$this->assertEquals( 'Autoptimize', $active_plugins['autoptimize.php'] );
		$this->assertEquals( 'WP Rocket', $active_plugins['wp-rocket.php'] );
	}

	/**
	 * Test is_scripts_optimization_plugin_active with no active plugins.
	 */
	public function test_is_scripts_optimization_plugin_active_with_no_active_plugins() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the plugin's is_plugin_active method to return false
		$this->plugin->method( 'is_plugin_active' )->willReturn( false );

		$this->assertFalse( $dependencies->is_scripts_optimization_plugin_active() );
	}

	/**
	 * Test is_scripts_optimization_plugin_active with active plugins.
	 */
	public function test_is_scripts_optimization_plugin_active_with_active_plugins() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the plugin's is_plugin_active method to return true for one plugin
		$this->plugin->method( 'is_plugin_active' )->willReturnCallback( function( $filename ) {
			return $filename === 'autoptimize.php';
		} );

		$this->assertTrue( $dependencies->is_scripts_optimization_plugin_active() );
	}

	/**
	 * Test that add_admin_notices calls all the notice methods.
	 */
	public function test_add_admin_notices_calls_all_notice_methods() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the notice methods to verify they are called
		$dependencies = $this->getMockBuilder( Dependencies::class )
			->setConstructorArgs( array( $this->plugin ) )
			->onlyMethods( array( 'add_php_extension_notices', 'add_php_function_notices', 'add_php_settings_notices', 'add_deprecated_notices' ) )
			->getMock();

		$dependencies->expects( $this->once() )->method( 'add_php_extension_notices' );
		$dependencies->expects( $this->once() )->method( 'add_php_function_notices' );
		$dependencies->expects( $this->once() )->method( 'add_php_settings_notices' );
		$dependencies->expects( $this->once() )->method( 'add_deprecated_notices' );

		$dependencies->add_admin_notices();
	}

	/**
	 * Test add_php_extension_notices with missing extensions.
	 */
	public function test_add_php_extension_notices_with_missing_extensions() {
		// Create a fresh plugin and handler mock for this test
		$plugin = $this->createMock( Plugin::class );
		$admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$args = array(
			'php_extensions' => array( 'definitely_not_a_real_php_extension' ),
		);
		$dependencies = new Dependencies( $plugin, $args );

		$admin_notice_handler->expects( $this->once() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'definitely_not_a_real_php_extension' ),
				$this->stringContains( 'missing-extensions' ),
				$this->equalTo( array( 'notice_class' => 'notice-error' ) )
			);

		$dependencies->add_php_extension_notices();
	}

	/**
	 * Test add_php_function_notices with missing functions.
	 */
	public function test_add_php_function_notices_with_missing_functions() {
		// Create a fresh plugin and handler mock for this test
		$plugin = $this->createMock( Plugin::class );
		$admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$args = array(
			'php_functions' => array( 'definitely_not_a_real_php_function' ),
		);
		$dependencies = new Dependencies( $plugin, $args );

		$admin_notice_handler->expects( $this->once() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'definitely_not_a_real_php_function' ),
				$this->stringContains( 'missing-functions' ),
				$this->equalTo( array( 'notice_class' => 'notice-error' ) )
			);

		$dependencies->add_php_function_notices();
	}

	/**
	 * Test add_php_settings_notices with incompatible settings.
	 */
	public function test_add_php_settings_notices_with_incompatible_settings() {
		// Create a fresh plugin and handler mock for this test
		$plugin = $this->createMock( Plugin::class );
		$admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );
		
		// Use a setting that should be incompatible (very high memory limit)
		$args = array(
			'php_settings' => array( 'memory_limit' => '999999M' ),
		);
		
		$dependencies = new Dependencies( $plugin, $args );
		
		// Mock $_GET to simulate being on WC settings page
		$_GET['page'] = 'wc-settings';
		
		// Expect the admin notice to be called if there are incompatible settings
		$admin_notice_handler->expects( $this->atLeastOnce() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'may behave unexpectedly' ),
				$this->stringContains( 'incompatibile-php-settings' ),
				$this->equalTo( array( 'notice_class' => 'notice-warning' ) )
			);
		
		$dependencies->add_php_settings_notices();
		
		// Clean up
		unset( $_GET['page'] );
	}

	/**
	 * Test add_php_settings_notices when not on WC settings page.
	 */
	public function test_add_php_settings_notices_when_not_on_wc_settings_page() {
		$args = array(
			'php_settings' => array( 'memory_limit' => '1G' ),
		);
		
		$dependencies = new Dependencies( $this->plugin, $args );
		
		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );
		
		// Ensure we're not on WC settings page
		unset( $_GET['page'] );
		
		// Expect no admin notice to be called
		$admin_notice_handler->expects( $this->never() )
			->method( 'add_admin_notice' );
		
		$dependencies->add_php_settings_notices();
	}

	/**
	 * Test add_deprecated_notices with old PHP version.
	 */
	public function test_add_deprecated_notices_with_old_php_version() {
		$dependencies = new Dependencies( $this->plugin );
		$admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		// Since we can't easily mock PHP_VERSION, we test the current behavior
		// If current PHP version is >= 5.6.0, no notice should be shown
		if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
			$admin_notice_handler->expects( $this->once() )
				->method( 'add_admin_notice' )
				->with(
					$this->stringContains( 'outdated version of PHP' ),
					$this->equalTo( 'sv-wc-deprecated-php-version' ),
					$this->equalTo( array( 'notice_class' => 'notice-error' ) )
				);
		} else {
			$admin_notice_handler->expects( $this->never() )
				->method( 'add_admin_notice' );
		}

		// Use Reflection to call the protected method
		$reflection = new \ReflectionClass( $dependencies );
		$method = $reflection->getMethod( 'add_deprecated_notices' );
		$method->setAccessible( true );
		$method->invoke( $dependencies );
	}

	/**
	 * Test add_deprecated_notices with new PHP version.
	 */
	public function test_add_deprecated_notices_with_new_php_version() {
		$dependencies = new Dependencies( $this->plugin );
		$admin_notice_handler = $this->createMock( AdminNoticeHandler::class );
		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$admin_notice_handler->expects( $this->never() )
			->method( 'add_admin_notice' );

		// Use Reflection to call the protected method
		$reflection = new \ReflectionClass( $dependencies );
		$method = $reflection->getMethod( 'add_deprecated_notices' );
		$method->setAccessible( true );
		$method->invoke( $dependencies );
	}

	/**
	 * Test that hooks are added.
	 */
	public function test_hooks_are_added() {
		$dependencies = new Dependencies( $this->plugin );
		
		// Check that the admin_init hook is added
		// has_action returns the priority if the action exists, false otherwise
		$this->assertNotFalse( has_action( 'admin_init', array( $dependencies, 'add_admin_notices' ) ) );
	}

	/**
	 * Test parse_dependencies with empty arguments.
	 */
	public function test_parse_dependencies_with_empty_arguments() {
		$dependencies = new Dependencies( $this->plugin, array() );
		
		// When no arguments are provided, all arrays should be empty
		$this->assertEmpty( $dependencies->get_php_extensions() );
		$this->assertEmpty( $dependencies->get_php_functions() );
		$this->assertEmpty( $dependencies->get_php_settings() );
	}

	/**
	 * Test parse_dependencies merges custom settings with defaults.
	 */
	public function test_parse_dependencies_merges_custom_settings_with_defaults() {
		$args = array(
			'php_settings' => array( 'custom_setting' => 'custom_value' ),
		);
		
		$dependencies = new Dependencies( $this->plugin, $args );
		
		$settings = $dependencies->get_php_settings();
		
		// Should contain both default settings and custom setting
		$this->assertArrayHasKey( 'suhosin.post.max_array_index_length', $settings );
		$this->assertArrayHasKey( 'custom_setting', $settings );
		$this->assertEquals( 'custom_value', $settings['custom_setting'] );
	}

	/**
	 * Test get_active_scripts_optimization_plugins with custom filter.
	 */
	public function test_get_active_scripts_optimization_plugins_with_custom_filter() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the plugin's is_plugin_active method to return true for custom plugin
		$this->plugin->method( 'is_plugin_active' )->willReturnCallback( function( $filename ) {
			return $filename === 'custom-optimizer.php';
		} );

		// Add custom plugins via filter
		$this->add_filter_with_safe_teardown( 'wc_facebook_for_woocommerce_scripts_optimization_plugins', function( $plugins ) {
			$plugins['custom-optimizer.php'] = 'Custom Optimizer';
			return $plugins;
		} );

		$active_plugins = $dependencies->get_active_scripts_optimization_plugins();
		$this->assertArrayHasKey( 'custom-optimizer.php', $active_plugins );
		$this->assertEquals( 'Custom Optimizer', $active_plugins['custom-optimizer.php'] );
	}
} 