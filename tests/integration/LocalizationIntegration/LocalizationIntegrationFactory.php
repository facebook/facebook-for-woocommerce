<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\LocalizationIntegration;

use WooCommerce\Facebook\Integrations\Abstract_Localization_Integration;
use WooCommerce\Facebook\Integrations\Polylang;
use WooCommerce\Facebook\Integrations\WPML;

/**
 * Factory class for creating localization integration instances.
 *
 * Automatically detects which localization plugin is installed and active,
 * then returns the appropriate integration instance.
 */
class LocalizationIntegrationFactory {

	/**
	 * Create an instance of the appropriate localization integration.
	 *
	 * @return Abstract_Localization_Integration|null Integration instance or null if no supported plugin found
	 */
	public static function create(): ?Abstract_Localization_Integration {
		// Try Polylang first
		$polylang = new Polylang();
		if ( $polylang->is_plugin_active() ) {
			return $polylang;
		}

		// Try WPML
		$wpml = new WPML();
		if ( $wpml->is_plugin_active() ) {
			return $wpml;
		}

		// Future: Add TranslatePress support
		// $translatepress = new TranslatePress();
		// if ( $translatepress->is_plugin_active() ) {
		//     return $translatepress;
		// }

		// Future: Add Weglot support
		// $weglot = new Weglot();
		// if ( $weglot->is_plugin_active() ) {
		//     return $weglot;
		// }

		return null; // No supported localization plugin found
	}

	/**
	 * Get a list of all supported localization plugins.
	 *
	 * @return array Array of plugin information
	 */
	public static function get_supported_plugins(): array {
		return [
			'polylang' => [
				'name' => 'Polylang',
				'file' => 'polylang/polylang.php',
				'class' => Polylang::class,
				'implemented' => true,
			],
			'wpml' => [
				'name' => 'WPML',
				'file' => 'sitepress-multilingual-cms/sitepress.php',
				'class' => WPML::class,
				'implemented' => true,
			],
			// Future implementations
			// 'translatepress' => [
			// 	'name' => 'TranslatePress',
			// 	'file' => 'translatepress-multilingual/index.php',
			// 	'class' => 'WooCommerce\Facebook\Integrations\TranslatePress',
			// 	'implemented' => false,
			// ],
			// 'weglot' => [
			// 	'name' => 'Weglot',
			// 	'file' => 'weglot/weglot.php',
			// 	'class' => 'WooCommerce\Facebook\Integrations\Weglot',
			// 	'implemented' => false,
			// ],
		];
	}

	/**
	 * Detect which localization plugins are installed (but not necessarily active).
	 *
	 * @return array Array of installed plugin information
	 */
	public static function detect_installed_plugins(): array {
		$supported = self::get_supported_plugins();
		$installed = [];

		foreach ( $supported as $key => $plugin ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin['file'];
			if ( file_exists( $plugin_file ) ) {
				$installed[ $key ] = $plugin;
				$installed[ $key ]['installed'] = true;

				// Check if it's active
				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$installed[ $key ]['active'] = is_plugin_active( $plugin['file'] );
			}
		}

		return $installed;
	}

	/**
	 * Get the name of the currently active localization plugin.
	 *
	 * @return string|null Plugin name or null if none active
	 */
	public static function get_active_plugin_name(): ?string {
		$integration = self::create();
		return $integration ? $integration->get_plugin_name() : null;
	}

	/**
	 * Check if any supported localization plugin is available.
	 *
	 * @return bool True if at least one supported plugin is active
	 */
	public static function has_active_plugin(): bool {
		return null !== self::create();
	}
}
