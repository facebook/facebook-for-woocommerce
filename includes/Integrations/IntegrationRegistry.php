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
