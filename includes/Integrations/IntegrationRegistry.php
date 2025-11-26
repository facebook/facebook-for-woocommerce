<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized registry for localization integrations and active plugin detection.
 *
 * Provides discovery mechanism for available localization integrations, manages
 * instantiation of integration classes, and tracks active plugin availability
 * to send plugin telemetry data to Facebook/Meta.
 *
 * @since 3.5.9
 */
class IntegrationRegistry {

	/**
	 * @var array<string, string> Map of integration keys to class names
	 */
	private static $localization_integrations = [
		'polylang' => Polylang::class,
		'wpml' => WPML::class,
	];

	/**
	 * @var array<string, Abstract_Localization_Integration> Cached integration instances
	 */
	private static $integration_instances = [];

	/**
	 * Get all localization integration keys
	 *
	 * @return array<string> Array of integration keys
	 */
	public static function get_localization_integration_keys(): array {
		return array_keys( self::$localization_integrations );
	}

	/**
	 * Get a specific localization integration instance
	 *
	 * @param string $integration_key Integration key (e.g., 'polylang')
	 * @return Abstract_Localization_Integration|null Integration instance or null if not found
	 */
	public static function get_localization_integration( string $integration_key ): ?Abstract_Localization_Integration {
		if ( ! isset( self::$localization_integrations[ $integration_key ] ) ) {
			return null;
		}

		// Return cached instance if available
		if ( isset( self::$integration_instances[ $integration_key ] ) ) {
			return self::$integration_instances[ $integration_key ];
		}

		$class_name = self::$localization_integrations[ $integration_key ];

		// Verify class exists and extends the abstract base
		if ( ! class_exists( $class_name ) || ! is_subclass_of( $class_name, Abstract_Localization_Integration::class ) ) {
			return null;
		}

		// Create and cache the instance
		$instance = new $class_name();
		self::$integration_instances[ $integration_key ] = $instance;

		return $instance;
	}

	/**
	 * Get all localization integration instances
	 *
	 * @return array<string, Abstract_Localization_Integration> Array of integration instances keyed by integration key
	 */
	public static function get_all_localization_integrations(): array {
		$integrations = [];

		foreach ( self::get_localization_integration_keys() as $key ) {
			$integration = self::get_localization_integration( $key );
			if ( $integration ) {
				$integrations[ $key ] = $integration;
			}
		}

		return $integrations;
	}

	/**
	 * Get availability data for all localization integrations
	 *
	 * @return array<string, array> Array of integration availability data keyed by integration key
	 */
	public static function get_all_localization_availability_data(): array {
		$availability_data = [];

		foreach ( self::get_all_localization_integrations() as $key => $integration ) {
			$availability_data[ $key ] = self::get_integration_availability_data( $integration );
		}

		return $availability_data;
	}

	/**
	 * Get availability data for a specific integration
	 *
	 * @param Abstract_Localization_Integration $integration Integration instance
	 * @return array Integration availability data
	 */
	private static function get_integration_availability_data( Abstract_Localization_Integration $integration ): array {
		// Use the standardized method from the abstract base class
		return $integration->get_availability_data();
	}

	/**
	 * Register a new localization integration
	 *
	 * @param string $key Integration key
	 * @param string $class_name Integration class name
	 * @return bool True if registered successfully, false otherwise
	 */
	public static function register_localization_integration( string $key, string $class_name ): bool {
		// Verify class exists and extends the abstract base
		if ( ! class_exists( $class_name ) || ! is_subclass_of( $class_name, Abstract_Localization_Integration::class ) ) {
			return false;
		}

		self::$localization_integrations[ $key ] = $class_name;

		// Clear cached instance if it exists
		unset( self::$integration_instances[ $key ] );

		return true;
	}

	/**
	 * Check if any localization plugin is active and properly configured
	 *
	 * @return bool True if at least one localization plugin is available
	 */
	public static function has_active_localization_plugin(): bool {
		$integrations = self::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_available() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the first active localization integration
	 *
	 * Returns the first integration that has an active plugin (regardless of configuration).
	 * This is useful for getting language information even if the integration isn't fully configured.
	 *
	 * **Multiple Localization Plugins:**
	 * Integrations are checked in the order they appear in $localization_integrations array:
	 * 1. Polylang (checked first)
	 * 2. WPML (checked second)
	 *
	 * If both plugins are somehow active, only Polylang's integration will be returned and used.
	 *
	 * **In Practice:**
	 * Based on telemetry data from Facebook for WooCommerce users, ZERO sites have both
	 * WPML and Polylang activated simultaneously. This is because:
	 * - WPML throws a fatal error during initialization if Polylang is already active
	 * - If Polylang is activated after WPML, WPML's functionality is disabled
	 *
	 * **Plugin Conflicts:**
	 * The order of precedence (Polylang first) is intentional but rarely matters in practice
	 * due to the plugin conflicts mentioned above. If conflicts are resolved in future versions
	 * of these plugins, this method will consistently return Polylang when both are active.
	 *
	 * @return Abstract_Localization_Integration|null The first active integration or null if none active
	 * @since 3.6.0
	 */
	public static function get_active_localization_integration(): ?Abstract_Localization_Integration {
		$integrations = self::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_plugin_active() ) {
				return $integration;
			}
		}

		return null;
	}

	/**
	 * Clear all cached integration instances
	 *
	 * Useful for testing or when integration states might have changed
	 */
	public static function clear_cache(): void {
		self::$integration_instances = [];
	}

	/**
	 * Get list of active plugin names
	 *
	 * @return array Array of active plugin names
	 */
	public static function get_all_active_plugin_data(): array {
		try {
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
		} catch ( \Exception $e ) {
			// Log error but return empty array to prevent breaking the update process
			if ( class_exists( 'WooCommerce\Facebook\Framework\Logger' ) ) {
				\WooCommerce\Facebook\Framework\Logger::log(
					'Error getting active plugin data: ' . $e->getMessage(),
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					)
				);
			}
			return [];
		}
	}
}
