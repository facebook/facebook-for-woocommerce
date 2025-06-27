<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Plugin;

use WooCommerce\Facebook\Framework\Plugin\Dependencies;
use WooCommerce\Facebook\Framework\Plugin;
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
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );
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

		$this->assertInstanceOf( Dependencies::class, $dependencies );
		$this->assertEmpty( $dependencies->get_php_extensions() );
		$this->assertEmpty( $dependencies->get_php_functions() );
		$this->assertNotEmpty( $dependencies->get_php_settings() ); // Should have default settings
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
		$this->assertArrayHasKey( 'memory_limit', $dependencies->get_php_settings() );
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
	 * Test get_missing_php_functions with all functions available.
	 */
	public function test_get_missing_php_functions_with_all_available() {
		$args = array(
			'php_functions' => array( 'json_encode', 'json_decode' ), // Common functions that should be available
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$missing = $dependencies->get_missing_php_functions();
		$this->assertIsArray( $missing );
		// These functions should be available in most PHP installations
		$this->assertNotContains( 'json_encode', $missing );
		$this->assertNotContains( 'json_decode', $missing );
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
		$args = array(
			'php_extensions' => array( 'nonexistent_extension' ),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$admin_notice_handler->expects( $this->once() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'nonexistent_extension' ),
				$this->stringContains( 'missing-extensions' ),
				$this->anything()
			);

		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$dependencies->add_php_extension_notices();
	}

	/**
	 * Test add_php_function_notices with missing functions.
	 */
	public function test_add_php_function_notices_with_missing_functions() {
		$args = array(
			'php_functions' => array( 'nonexistent_function' ),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$admin_notice_handler->expects( $this->once() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'nonexistent_function' ),
				$this->stringContains( 'missing-functions' ),
				$this->anything()
			);

		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$dependencies->add_php_function_notices();
	}

	/**
	 * Test add_php_settings_notices with incompatible settings.
	 */
	public function test_add_php_settings_notices_with_incompatible_settings() {
		// Set up $_GET to simulate being on the WooCommerce settings page
		$_GET['page'] = 'wc-settings';

		$args = array(
			'php_settings' => array(
				'memory_limit' => '999999M', // Very high value
			),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$admin_notice_handler->expects( $this->once() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'PHP configuration settings' ),
				$this->stringContains( 'incompatibile-php-settings' ),
				$this->anything()
			);

		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$dependencies->add_php_settings_notices();

		// Clean up
		unset( $_GET['page'] );
	}

	/**
	 * Test add_php_settings_notices when not on WooCommerce settings page.
	 */
	public function test_add_php_settings_notices_when_not_on_wc_settings_page() {
		$args = array(
			'php_settings' => array(
				'memory_limit' => '999999M',
			),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$admin_notice_handler->expects( $this->never() )->method( 'add_admin_notice' );

		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		$dependencies->add_php_settings_notices();
	}

	/**
	 * Test add_deprecated_notices with PHP version < 5.6.
	 */
	public function test_add_deprecated_notices_with_old_php_version() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$admin_notice_handler->expects( $this->once() )
			->method( 'add_admin_notice' )
			->with(
				$this->stringContains( 'outdated version of PHP' ),
				'sv-wc-deprecated-php-version',
				$this->anything()
			);

		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		// Mock PHP_VERSION to be old
		$this->add_filter_with_safe_teardown( 'PHP_VERSION', function() {
			return '5.5.0';
		} );

		$dependencies->add_deprecated_notices();
	}

	/**
	 * Test add_deprecated_notices with PHP version >= 5.6.
	 */
	public function test_add_deprecated_notices_with_new_php_version() {
		$dependencies = new Dependencies( $this->plugin );

		// Mock the admin notice handler
		$admin_notice_handler = $this->createMock( \WooCommerce\Facebook\Framework\AdminNotice\Handler::class );
		$admin_notice_handler->expects( $this->never() )->method( 'add_admin_notice' );

		$this->plugin->method( 'get_admin_notice_handler' )->willReturn( $admin_notice_handler );

		// Mock PHP_VERSION to be new
		$this->add_filter_with_safe_teardown( 'PHP_VERSION', function() {
			return '7.4.0';
		} );

		$dependencies->add_deprecated_notices();
	}

	/**
	 * Test that hooks are added correctly.
	 */
	public function test_hooks_are_added() {
		$dependencies = new Dependencies( $this->plugin );

		// Verify that the admin_init hook is added
		$this->assertTrue( has_action( 'admin_init', array( $dependencies, 'add_admin_notices' ) ) );
	}

	/**
	 * Test parse_dependencies with empty arguments.
	 */
	public function test_parse_dependencies_with_empty_arguments() {
		$dependencies = new Dependencies( $this->plugin, array() );

		$this->assertEmpty( $dependencies->get_php_extensions() );
		$this->assertEmpty( $dependencies->get_php_functions() );
		$this->assertNotEmpty( $dependencies->get_php_settings() ); // Should have default settings
	}

	/**
	 * Test parse_dependencies merges custom settings with defaults.
	 */
	public function test_parse_dependencies_merges_custom_settings_with_defaults() {
		$args = array(
			'php_settings' => array(
				'memory_limit' => '512M',
			),
		);

		$dependencies = new Dependencies( $this->plugin, $args );

		$settings = $dependencies->get_php_settings();
		$this->assertArrayHasKey( 'memory_limit', $settings );
		$this->assertEquals( '512M', $settings['memory_limit'] );
		// Should also have default suhosin settings
		$this->assertArrayHasKey( 'suhosin.post.max_array_index_length', $settings );
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