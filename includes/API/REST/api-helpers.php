<?php
/**
 * API Helper Functions
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Get all API definitions for JavaScript.
 *
 * @since 2.3.5
 *
 * @return array
 */
function get_api_definitions() {
	$endpoints = [
		new Settings\Handler(),
	];
	
	$definitions = [];
	
	foreach ( $endpoints as $endpoint ) {
		$endpoint_definitions = $endpoint->get_js_api_definitions();
		$definitions = array_merge( $definitions, $endpoint_definitions );
	}
	
	return $definitions;
}

/**
 * Enqueue and localize the API JavaScript.
 *
 * @since 2.3.5
 *
 * @return void
 */
function enqueue_api_js() {
	// Only enqueue on the Facebook settings page
	if ( ! facebook_for_woocommerce()->is_plugin_settings() ) {
		return;
	}
	
	wp_enqueue_script(
		'facebook-for-woocommerce-api',
		facebook_for_woocommerce()->get_plugin_url() . '/assets/js/admin/facebook-for-woocommerce-api.js',
		[ 'jquery' ],
		\WC_Facebookcommerce::VERSION,
		true // Important: Load in footer
	);
	
	// Get API definitions
	$api_definitions = get_api_definitions();
	
	// Localize the script with API data
	wp_localize_script(
		'facebook-for-woocommerce-api',
		'fb_api_data',
		[
			'api_url'   => rest_url( Controller::API_NAMESPACE . '/' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'endpoints' => $api_definitions,
		]
	);
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_api_js' ); 