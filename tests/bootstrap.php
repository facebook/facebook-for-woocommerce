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

tests_add_filter( 'muplugins_loaded', function () {
	load_plugins();
} );

tests_add_filter( 'init', function () {
	install_woocommerce();
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

	echo "Installing WooCommerce..." . PHP_EOL;
}

/**
 * Manually load plugins
 *
 * @global $fb_dir
 * @global $wc_dir
 */
function load_plugins() {
	global $fb_dir;
	global $wc_dir;

	require_once( $wc_dir . '/woocommerce.php' );
	update_option( 'woocommerce_db_version', WC()->version );

	require $fb_dir . '/facebook-for-woocommerce.php';
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

// Load WooCommerce meta box functions for tests
if ( ! function_exists( 'woocommerce_wp_hidden_input' ) ) {
	$wc_meta_functions_file = $wc_dir . '/includes/admin/wc-meta-box-functions.php';
	if ( file_exists( $wc_meta_functions_file ) ) {
		include_once $wc_meta_functions_file;
	}
}

// Mock WooCommerce function if still not available
if ( ! function_exists( 'woocommerce_wp_hidden_input' ) ) {
	function woocommerce_wp_hidden_input( $args ) {
		echo '<input type="hidden" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( $args['value'] ) . '">';
	}
}
