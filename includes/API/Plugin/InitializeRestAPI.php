<?php
/**
 * REST API Initialization
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class to initialize the REST API functionality. Generates the JS request API template consumed by @connection-api-client.js
 * and initializes the REST API Framework Controller.
 *
 * @since 3.5.0
 */
class InitializeRestAPI {

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		// Register hooks
		add_action( 'admin_enqueue_scripts', [ $this, 'generate_js_request_framework' ] );
		$this->init_rest_api_framework();
	}

	/**
	 * Initialize the Controller handler.
	 *
	 * @since 3.5.0
	 */
	private function init_rest_api_framework() {
		new \WooCommerce\Facebook\API\Plugin\Controller();
	}

	/**
	 * Check if the REST framework should be generated.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function should_generate_rest_framework() {
		if ( ! facebook_for_woocommerce()->is_plugin_settings() ) {
			return false;
		}
		return true;
	}

	/**
	 * Get the API definitions.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function get_api_definitions() {
		wp_enqueue_script(
			'plugin-api-client',
			facebook_for_woocommerce()->get_plugin_url() . '/assets/js/admin/plugin-api-client.js',
			[ 'jquery' ],
			\WC_Facebookcommerce::VERSION,
			true // Important: Load in footer
		);

		// Create a dummy WP_REST_Request object
		$dummy_request = new \WP_REST_Request();

		// Collect API definitions from all handlers
		$api_definitions     = [];
			$api_definitions = [];

		foreach ( Controller::get_js_enabled_requests() as $request_class ) {
			// Skip if class doesn't exist
			if ( ! class_exists( $request_class ) ) {
				continue;
			}

			// Check if the class is JS exposable
			if ( ! method_exists( $request_class, 'is_js_exposable' ) || ! $request_class::is_js_exposable() ) {
				continue;
			}

			try {
				// Create an instance of the request class with the dummy request
				$request = new $request_class( $dummy_request );

				// Get the JS API definition directly from the request class
				if ( method_exists( $request, 'get_js_api_definition' ) ) {
					$definition                     = $request->get_js_api_definition();
					$class_name                     = $definition['className'];
					$api_definitions[ $class_name ] = $definition;
				}
			} catch ( \Exception $e ) {
				// Log error but continue with other requests
				error_log( 'Facebook for WooCommerce: Error creating request class ' . $request_class . ': ' . $e->getMessage() );
			}
		}
		return $api_definitions;
	}

	/**
	 * Enqueue and localize the API JavaScript.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function generate_js_request_framework() {
		if ( ! $this->should_generate_rest_framework() ) {
			return;
		}
		$api_definitions = $this->get_api_definitions();
		// Localize the script with API data
		wp_localize_script(
			'plugin-api-client',
			'fb_api_data',
			[
				'api_url'   => rest_url( Controller::API_NAMESPACE . '/' ),
				'endpoints' => $api_definitions,
			]
		);
	}
}
