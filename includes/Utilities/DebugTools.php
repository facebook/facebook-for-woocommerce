<?php

namespace WooCommerce\Facebook\Utilities;

/**
 * Class DebugTools
 *
 * @since 3.0.5
 */
class DebugTools {

	/**
	 * Initialize the class.
	 *
	 * @since 3.0.5
	 */
	public function __construct() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_filter( 'woocommerce_debug_tools', [ $this, 'add_debug_tool' ] );
		}
	}

	/**
	 * Adds clear settings tool to WC system status -> tools page.
	 *
	 * @since 3.0.5
	 *
	 * @param array $tools system status tools.
	 * @return array
	 */
	public function add_debug_tool( $tools ) {
		// Add connection reset tools (always visible)
		$tools['wc_facebook_reset_connection_only'] = [
			'name'     => __( 'Facebook: Reset connection data', 'facebook-for-woocommerce' ),
			'button'   => __( 'Reset connection', 'facebook-for-woocommerce' ),
			'desc'     => __( 'This tool will reset only your Facebook connection tokens and IDs, preserving your other settings like product sync preferences, excluded categories, and debug mode. Use this to reconnect without losing your configuration.', 'facebook-for-woocommerce' ),
			'callback' => [ $this, 'reset_connection_data' ],
		];

		$tools['wc_facebook_reset_all_settings'] = [
			'name'     => __( 'Facebook: Reset all settings', 'facebook-for-woocommerce' ),
			'button'   => __( 'Reset all settings', 'facebook-for-woocommerce' ),
			'desc'     => __( 'This tool will completely reset ALL Facebook-related settings and connection data, returning the plugin to a fresh installation state. This includes connection tokens, product sync settings, excluded categories, debug mode, and all other configuration. Use this for a complete fresh start.', 'facebook-for-woocommerce' ),
			'callback' => [ $this, 'reset_all_settings' ],
		];

		// Only show debug tools when connected and debug mode is enabled
		if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected()
			|| ! facebook_for_woocommerce()->get_integration()->is_debug_mode_enabled() ) {
			return $tools;
		}

		$tools['wc_facebook_settings_reset'] = [
			'name'     => __( 'Facebook: Reset connection settings (legacy)', 'facebook-for-woocommerce' ),
			'button'   => __( 'Reset settings', 'facebook-for-woocommerce' ),
			'desc'     => __( 'This tool will clear your Facebook settings to reset them, allowing you to rebuild your connection.', 'facebook-for-woocommerce' ),
			'callback' => [ $this, 'clear_facebook_settings' ],
		];

		$tools['wc_facebook_delete_background_jobs'] = [
			'name'     => __( 'Facebook: Delete Background Sync Jobs', 'facebook-for-woocommerce' ),
			'button'   => __( 'Clear Background Sync Jobs', 'facebook-for-woocommerce' ),
			'desc'     => __( 'This tool will clear your clear background sync jobs from the options table.', 'facebook-for-woocommerce' ),
			'callback' => [ $this, 'clean_up_old_background_sync_options' ],
		];

		$tools['reset_all_product_fb_settings'] = [
			'name'     => __( 'Facebook: Reset all products', 'facebook-for-woocommerce' ),
			'button'   => __( 'Reset products Facebook settings', 'facebook-for-woocommerce' ),
			'desc'     => __( 'This tool will reset Facebook settings for all products on your WooCommerce store.', 'facebook-for-woocommerce' ),
			'callback' => [ $this, 'reset_all_product_fb_settings' ],
		];

		return $tools;
	}

	/**
	 * Runs the Delete Background Sync Jobs tool.
	 *
	 * @since 3.0.5
	 *
	 * @return string
	 */
	public function clean_up_old_background_sync_options() {
		global $wpdb;

		// Delete job entries (but not cache transients which use different pattern)
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wc_facebook_background_product_sync_job_%'" );

		// Invalidate all sync-related caches since we deleted jobs directly from the database
		delete_transient( 'wc_facebook_background_product_sync_queue_empty' );
		delete_transient( 'wc_facebook_background_product_sync_sync_in_progress' );
		delete_transient( 'wc_facebook_sync_in_progress' );

		return __( 'Background sync jobs have been deleted.', 'facebook-for-woocommerce' );
	}

	/**
	 * Resets only the Facebook connection data.
	 *
	 * This preserves settings like product sync preferences, excluded categories, etc.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function reset_connection_data() {
		facebook_for_woocommerce()->get_connection_handler()->reset_connection_only();

		return esc_html__( 'Facebook connection data has been reset. Your other settings have been preserved. You can now reconnect to Facebook.', 'facebook-for-woocommerce' );
	}

	/**
	 * Resets all Facebook settings completely.
	 *
	 * This deletes ALL Facebook-related options, returning the plugin to a fresh state.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function reset_all_settings() {
		facebook_for_woocommerce()->get_connection_handler()->reset_connection();

		return esc_html__( 'All Facebook settings have been completely reset. The plugin is now in a fresh installation state.', 'facebook-for-woocommerce' );
	}

	/**
	 * Runs the clear settings tool.
	 *
	 * @since 3.0.5
	 *
	 * @return string
	 */
	public function clear_facebook_settings() {
		// Disconnect FB.
		facebook_for_woocommerce()->get_connection_handler()->disconnect();

		return esc_html__( 'Cleared all Facebook settings!', 'facebook-for-woocommerce' );
	}

	/**
	 * Runs the reset all catalog products settings tool.
	 *
	 * @since 3.0.5
	 *
	 * @return string
	 */
	public function reset_all_product_fb_settings() {
		facebook_for_woocommerce()->job_manager->reset_all_product_fb_settings->queue_start();
		return esc_html__( 'Reset products Facebook settings job started!', 'facebook-for-woocommerce' );
	}
}
