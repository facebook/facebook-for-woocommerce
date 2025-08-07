<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Tests;

use WC_Install;

define( 'FB_TESTS_DIR', __DIR__ );
define( 'FB_TESTS_DATA_DIR', FB_TESTS_DIR . '/data' );

global $fb_dir;
global $wp_plugins_dir;
global $wc_dir;

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: path_join( sys_get_temp_dir(), '/wordpress-tests-lib' );
validate_file_exits( "{$wp_tests_dir}/includes/functions.php" );

$wp_core_dir    = getenv( 'WP_CORE_DIR' ) ?: path_join( sys_get_temp_dir(), '/wordpress' );
$wp_plugins_dir = path_join( $wp_core_dir, '/wp-content/plugins' );

$fb_dir = dirname( __FILE__, 2 ); // ../../

$wc_dir = getenv( 'WC_DIR' );
if ( ! $wc_dir ) {
	// Check if WooCommerce exists in the core plugin folder. The `bin/install-wp-tests.sh` script clones a copy there.
	$wc_dir = path_join( $wp_plugins_dir, '/woocommerce' );
	if ( ! file_exists( "{$wc_dir}/woocommerce.php" ) ) {
		// Check if WooCommerce exists in parent directory of the plugin (in case the plugin is located in a WordPress installation's `wp-content/plugins` folder)
		$wc_dir = path_join( dirname( $fb_dir ), '/woocommerce' );
	}
}
validate_file_exits( "{$wc_dir}/woocommerce.php" );

// Require the composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once "{$wp_tests_dir}/includes/functions.php";

// Define Polylang constants before loading plugins to ensure proper initialization
if ( ! defined( 'PLL_ADMIN' ) ) {
	define( 'PLL_ADMIN', true );
}

tests_add_filter( 'muplugins_loaded', function () {
	load_plugins();
} );

tests_add_filter( 'init', function () {
	install_woocommerce();
} );

tests_add_filter( 'plugins_loaded', function () {
	activate_polylang();
	setup_basic_polylang_language();
} );

// Start up the WP testing environment.
require "{$wp_tests_dir}/includes/bootstrap.php";

// Include WooCommerce test helpers
$wc_tests_dir = $wc_dir . '/tests';
if ( file_exists( $wc_dir . '/tests/legacy/bootstrap.php' ) ) {
	$wc_tests_dir .= '/legacy';
}

require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-product.php';
require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-shipping.php';
require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-customer.php';
require_once $wc_tests_dir . '/framework/vendor/class-wp-test-spy-rest-server.php';

/**
 * Load WooCommerce for testing
 *
 * @global $wc_dir
 */
function install_woocommerce() {
	global $wc_dir;

	define( 'WP_UNINSTALL_PLUGIN', true );
	define( 'WC_REMOVE_ALL_DATA', true );

	include $wc_dir . '/uninstall.php';

	WC_Install::install();

	// Initialize the WC Admin extension.
	if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Install' ) ) {
		\Automattic\WooCommerce\Internal\Admin\Install::create_tables();
		\Automattic\WooCommerce\Internal\Admin\Install::create_events();
	} elseif ( class_exists( '\Automattic\WooCommerce\Admin\Install' ) ) {
		\Automattic\WooCommerce\Admin\Install::create_tables();
		\Automattic\WooCommerce\Admin\Install::create_events();
	}

	// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
	$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	wp_roles();
}

/**
 * Manually load plugins
 *
 * @global $fb_dir
 * @global $wc_dir
 * @global $wp_plugins_dir
 */
function load_plugins() {
	global $fb_dir;
	global $wc_dir;
	global $wp_plugins_dir;

	require_once( $wc_dir . '/woocommerce.php' );
	update_option( 'woocommerce_db_version', WC()->version );

	// Check if Polylang is installed - FAIL if not
	$polylang_file = $wp_plugins_dir . '/polylang/polylang.php';
	if ( ! file_exists( $polylang_file ) ) {
		echo "ERROR: Polylang plugin is not installed!" . PHP_EOL;
		echo "Expected location: {$polylang_file}" . PHP_EOL;
		echo "Please run: ./bin/install-wp-tests-with-polylang.sh facebook_woo_test root password" . PHP_EOL;
		exit( 1 );
	}

	require_once( $polylang_file );
	require $fb_dir . '/facebook-for-woocommerce.php';
}

/**
 * Properly activate Polylang after WordPress is fully loaded
 */
function activate_polylang() {
	global $wp_plugins_dir;

	$polylang_file = $wp_plugins_dir . '/polylang/polylang.php';
	if ( file_exists( $polylang_file ) ) {
		$polylang_basename = plugin_basename( $polylang_file );

		// Ensure plugin.php functions are available
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Activate the plugin properly
		$result = activate_plugin( $polylang_basename );

		if ( is_wp_error( $result ) ) {
			echo "Failed to activate Polylang: " . $result->get_error_message() . PHP_EOL;
		}
	}
}

/**
 * Set up a basic English language for Polylang testing
 */
function setup_basic_polylang_language() {
	// Set up minimal Polylang options
	$polylang_options = get_option( 'polylang', [] );
	if ( empty( $polylang_options ) || empty( $polylang_options['default_lang'] ) ) {
		$default_options = [
			'version' => '3.7.3',
			'default_lang' => 'en',
			'rewrite' => 1,
			'hide_default' => 0,
			'force_lang' => 0,
			'redirect_lang' => 0,
			'media_support' => 1,
			'uninstall' => 0,
			'sync' => [],
			'post_types' => [ 'post', 'page', 'product' ],
			'taxonomies' => [ 'category', 'post_tag', 'product_cat', 'product_tag' ],
			'domains' => [],
			'language_taxonomies' => [ 'language', 'post_language', 'term_language' ],
		];

		update_option( 'polylang', $default_options );
	}

	// Use Polylang's API to add English language if available
	if ( isset( $GLOBALS['polylang'] ) && is_object( $GLOBALS['polylang'] ) ) {
		if ( property_exists( $GLOBALS['polylang'], 'model' ) && is_object( $GLOBALS['polylang']->model ) ) {
			$model = $GLOBALS['polylang']->model;

			if ( property_exists( $model, 'languages' ) && is_object( $model->languages ) && method_exists( $model->languages, 'add' ) ) {
				// Check if English language already exists
				$existing_languages = $model->languages->get_list();
				$english_exists = false;

				foreach ( $existing_languages as $lang ) {
					if ( $lang->slug === 'en' ) {
						$english_exists = true;
						break;
					}
				}

				if ( ! $english_exists ) {
					$model->languages->add( [
						'name' => 'English',
						'slug' => 'en',
						'locale' => 'en_US',
						'rtl' => false,
						'term_group' => 1,
						'flag' => 'us',
						'no_default_cat' => true,
					] );
				}
			}
		}
	}
}

/**
 * Checks whether a file exists and throws an error if it doesn't.
 *
 * @param string $file_name
 */
function validate_file_exits( string $file_name ) {
	if ( ! file_exists( $file_name ) ) {
		echo "Could not find {$file_name}, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
	}
}

/**
 * @param string $base
 * @param string $path
 *
 * @return string
 */
function path_join( string $base, string $path ) {
	return rtrim( $base, '/\\' ) . '/' . ltrim( $path, '/\\' );
}
