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

// Define WPML constants before loading plugins to ensure proper initialization
if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
	define( 'ICL_SITEPRESS_VERSION', '4.6.8' );
}

tests_add_filter( 'muplugins_loaded', function () {
	load_plugins();
} );

tests_add_filter( 'init', function () {
	install_woocommerce();
} );

tests_add_filter( 'plugins_loaded', function () {
	activate_wpml();
} );

tests_add_filter( 'init', function () {
	setup_basic_wpml_language();
}, 5 );

tests_add_filter( 'wp_loaded', function () {
	initialize_wpml_filters();
}, 10 );

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

	// Check if WPML is installed - FAIL if not
	$wpml_file = $wp_plugins_dir . '/sitepress-multilingual-cms/sitepress.php';
	if ( ! file_exists( $wpml_file ) ) {
		echo "ERROR: WPML plugin is not installed!" . PHP_EOL;
		echo "Expected location: {$wpml_file}" . PHP_EOL;
		echo "Please run: ./bin/install-wp-tests-with-wpml.sh facebook_woo_test root password" . PHP_EOL;
		exit( 1 );
	}

	require_once( $wpml_file );

	require $fb_dir . '/facebook-for-woocommerce.php';
}

/**
 * Properly activate WPML after WordPress is fully loaded
 */
function activate_wpml() {
	global $wp_plugins_dir;

	$wpml_file = $wp_plugins_dir . '/sitepress-multilingual-cms/sitepress.php';
	if ( file_exists( $wpml_file ) ) {
		$wpml_basename = plugin_basename( $wpml_file );

		// Ensure plugin.php functions are available
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Activate the plugin properly
		$result = activate_plugin( $wpml_basename );

		if ( is_wp_error( $result ) ) {
			echo "Failed to activate WPML: " . $result->get_error_message() . PHP_EOL;
		}
	}
}

/**
 * Set up basic WPML configuration for testing using WPML's public API
 */
function setup_basic_wpml_language() {
	global $wp_plugins_dir, $sitepress;

	$wpml_file = $wp_plugins_dir . '/sitepress-multilingual-cms/sitepress.php';
	if ( ! file_exists( $wpml_file ) ) {
		return; // WPML not installed, skip setup
	}

	// Wait for WPML to be fully loaded
	if ( ! isset( $sitepress ) || ! is_object( $sitepress ) ) {
		return;
	}

	// Set up basic WPML options for a working installation using WPML's API
	$wpml_options = get_option( 'icl_sitepress_settings', [] );
	if ( empty( $wpml_options ) ) {
		$default_options = [
			'default_language' => 'en',
			'existing_content_language_verified' => 1,
			'setup_complete' => 1,
			'language_negotiation_type' => 1,
			'theme_localization_type' => 1,
			'icl_lso_header' => 0,
			'icl_lso_flags' => 0,
			'icl_lso_native_lang' => 1,
			'icl_lso_display_lang' => 1,
			'built_with_tm' => 0,
			'language_domains' => [],
			'languages_order' => [ 'en', 'es' ],
			'active_languages' => [ 'en' => 1, 'es' => 1 ],
		];

		update_option( 'icl_sitepress_settings', $default_options );
	}

	// Use WPML's built-in language setup instead of direct database manipulation
	setup_wpml_languages_via_api();

	// Define ICL_LANGUAGE_CODE if not already defined
	if ( ! defined( 'ICL_LANGUAGE_CODE' ) ) {
		define( 'ICL_LANGUAGE_CODE', 'en' );
	}

	// Trigger WPML's language setup
	do_action( 'wpml_loaded' );
}

/**
 * Set up WPML languages using WPML's internal API methods
 */
function setup_wpml_languages_via_api() {
	global $sitepress, $wpdb;

	if ( ! isset( $sitepress ) || ! is_object( $sitepress ) ) {
		return;
	}

	// Ensure WPML database tables exist by triggering WPML's own setup
	if ( method_exists( $sitepress, 'maybe_install' ) ) {
		$sitepress->maybe_install();
	}

	// Use WPML's language data to populate languages
	$languages_table = $wpdb->prefix . 'icl_languages';

	// Check if languages table exists and has data
	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$languages_table}'" ) === $languages_table;

	if ( $table_exists ) {
		$existing_languages = $wpdb->get_col( "SELECT code FROM {$languages_table}" );

		// Only add languages if they don't exist
		if ( empty( $existing_languages ) ) {
			// Use WPML's built-in language data
			$default_languages = [
				[
					'code' => 'en',
					'english_name' => 'English',
					'major' => 1,
					'active' => 1,
					'default_locale' => 'en_US',
					'tag' => 'en-US',
					'encode_url' => 0,
				],
				[
					'code' => 'es',
					'english_name' => 'Spanish',
					'major' => 1,
					'active' => 1,
					'default_locale' => 'es_ES',
					'tag' => 'es-ES',
					'encode_url' => 0,
				],
			];

			foreach ( $default_languages as $language ) {
				$existing = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$languages_table} WHERE code = %s",
					$language['code']
				) );

				if ( ! $existing ) {
					$wpdb->insert( $languages_table, $language );
				}
			}
		}
	}

	// Refresh WPML's internal language cache if method exists
	if ( method_exists( $sitepress, 'get_active_languages' ) ) {
		// This will trigger WPML to refresh its language cache
		$sitepress->get_active_languages();
	}
}

/**
 * Initialize WPML filters and global objects for proper integration testing
 */
function initialize_wpml_filters() {
	global $sitepress, $wpdb;

	// Ensure WPML is loaded and active
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return;
	}

	// Initialize the global $sitepress object if it doesn't exist
	if ( ! isset( $sitepress ) || ! is_object( $sitepress ) ) {
		// Create a minimal sitepress object for testing
		$sitepress = new \stdClass();
		$sitepress->settings = get_option( 'icl_sitepress_settings', [] );
	}

	// Register WPML's core filters that the integration relies on
	add_filter( 'wpml_active_languages', __NAMESPACE__ . '\wpml_test_get_active_languages', 10, 2 );
	add_filter( 'wpml_default_language', __NAMESPACE__ . '\wpml_test_get_default_language' );
	add_filter( 'wpml_object_id', __NAMESPACE__ . '\wpml_test_get_object_id', 10, 4 );

	// Set the current language context
	if ( ! defined( 'ICL_LANGUAGE_CODE' ) ) {
		define( 'ICL_LANGUAGE_CODE', 'en' );
	}
}

/**
 * Mock WPML's wpml_active_languages filter for testing
 */
function wpml_test_get_active_languages( $languages = null, $args = [] ) {
	if ( ! is_null( $languages ) ) {
		return $languages;
	}

	return [
		'en' => [
			'id' => '1',
			'active' => '1',
			'native_name' => 'English',
			'missing' => 0,
			'translated_name' => 'English',
			'language_code' => 'en',
			'country_flag_url' => '',
			'url' => home_url( '/' ),
			'default_locale' => 'en_US',
			'tag' => 'en-US',
		],
		'es' => [
			'id' => '2',
			'active' => '1',
			'native_name' => 'Español',
			'missing' => 0,
			'translated_name' => 'Spanish',
			'language_code' => 'es',
			'country_flag_url' => '',
			'url' => home_url( '/es/' ),
			'default_locale' => 'es_ES',
			'tag' => 'es-ES',
		],
	];
}

/**
 * Mock WPML's wpml_default_language filter for testing
 */
function wpml_test_get_default_language( $language = null ) {
	if ( ! is_null( $language ) ) {
		return $language;
	}

	$settings = get_option( 'icl_sitepress_settings', [] );
	return $settings['default_language'] ?? 'en';
}

/**
 * Mock WPML's wpml_object_id filter for testing
 */
function wpml_test_get_object_id( $element_id, $element_type = 'post', $return_original_if_missing = false, $language_code = null ) {
	global $wpdb;

	// If no language specified, return original ID
	if ( ! $language_code ) {
		return $element_id;
	}

	// For testing purposes, we'll simulate some basic translation logic
	// In a real scenario, this would query the icl_translations table
	$translations_table = $wpdb->prefix . 'icl_translations';

	// Check if the translations table exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$translations_table}'" ) !== $translations_table ) {
		return $return_original_if_missing ? $element_id : null;
	}

	// Look for existing translation
	$translation_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT element_id FROM {$translations_table}
		WHERE element_type = %s
		AND language_code = %s
		AND trid = (
			SELECT trid FROM {$translations_table}
			WHERE element_id = %d AND element_type = %s
			LIMIT 1
		)",
		$element_type,
		$language_code,
		$element_id,
		$element_type
	) );

	if ( $translation_id ) {
		return (int) $translation_id;
	}

	// Return original if no translation found and flag is set
	return $return_original_if_missing ? $element_id : null;
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
