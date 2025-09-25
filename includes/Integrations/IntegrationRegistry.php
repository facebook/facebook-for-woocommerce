<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Integration registry for active plugin detection.
 *
 * Provides discovery mechanism for active plugin availability
 * to send plugin telemetry data to Facebook/Meta.
 *
 * @since 3.5.9
 */
class IntegrationRegistry {

	/**
	 * Get list of active plugin names
	 *
	 * @return array Array of active plugin names
	 */
	public static function get_all_active_plugin_data(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins_list = get_option( 'active_plugins', [] );
		$all_plugins = get_plugins();
		$active_plugins_data = [];

		foreach ( $active_plugins_list as $plugin_file ) {
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$plugin_data = $all_plugins[ $plugin_file ];
				$active_plugins_data[] = $plugin_data['Name'];
			}
		}

		return $active_plugins_data;
	}

	/**
	 * Generate a sanitized key from plugin name
	 *
	 * @param string $plugin_name Plugin name
	 * @return string Sanitized key
	 */
	private static function generate_plugin_key( string $plugin_name ): string {
		// Convert to lowercase, replace spaces and special chars with underscores
		$key = strtolower( $plugin_name );
		$key = preg_replace( '/[^a-z0-9_]/', '_', $key );
		$key = preg_replace( '/_+/', '_', $key ); // Remove multiple underscores
		$key = trim( $key, '_' ); // Remove leading/trailing underscores

		return $key;
	}

	/**
	 * Get count of active plugins
	 *
	 * @return int Number of active plugins
	 */
	public static function get_active_plugin_count(): int {
		$active_plugins = get_option( 'active_plugins', [] );
		return count( $active_plugins );
	}

	/**
	 * Check if a specific plugin is active by plugin file
	 *
	 * @param string $plugin_file Plugin file path
	 * @return bool True if plugin is active
	 */
	public static function is_plugin_active( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file );
	}

}
