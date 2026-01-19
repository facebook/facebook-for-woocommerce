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
		if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected()
			|| ! facebook_for_woocommerce()->get_integration()->is_debug_mode_enabled() ) {
			return $tools;
		}

		$tools['wc_facebook_settings_reset'] = [
			'name'     => __( 'Facebook: Reset connection settings', 'facebook-for-woocommerce' ),
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

		// Invalidate the queue cache since we deleted jobs directly from the database
		delete_transient( 'wc_facebook_background_product_sync_queue_empty' );
		delete_transient( 'wc_facebook_background_product_sync_sync_in_progress' );

		return __( 'Background sync jobs have been deleted.', 'facebook-for-woocommerce' );
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
