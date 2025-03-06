<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Handlers\Connection;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles Meta Commerce Extension functionality and configuration.
 *
 * @since 2.0.0
 */
class MetaExtension {

	/** @var string Client token */
	const CLIENT_TOKEN = '474166926521348|92e978eb27baf47f9df578b48d430a2e';
	const APP_ID       = '474166926521348';

	/** @var string API version */
	const API_VERSION = 'v22.0';

	/** @var string Commerce Hub base URL */
	const COMMERCE_HUB_URL = 'https://www.commercepartnerhub.com/';

	/** @var string Option names for Facebook settings */
	const OPTION_ACCESS_TOKEN                    = 'wc_facebook_access_token';
	const OPTION_MERCHANT_ACCESS_TOKEN           = 'wc_facebook_merchant_access_token';
	const OPTION_PAGE_ACCESS_TOKEN               = 'wc_facebook_page_access_token';
	const OPTION_SYSTEM_USER_ID                  = 'wc_facebook_system_user_id';
	const OPTION_BUSINESS_MANAGER_ID             = 'wc_facebook_business_manager_id';
	const OPTION_AD_ACCOUNT_ID                   = 'wc_facebook_ad_account_id';
	const OPTION_INSTAGRAM_BUSINESS_ID           = 'wc_facebook_instagram_business_id';
	const OPTION_COMMERCE_MERCHANT_SETTINGS_ID   = 'wc_facebook_commerce_merchant_settings_id';
	const OPTION_EXTERNAL_BUSINESS_ID            = 'wc_facebook_external_business_id';
	const OPTION_COMMERCE_PARTNER_INTEGRATION_ID = 'wc_facebook_commerce_partner_integration_id';
	const OPTION_PRODUCT_CATALOG_ID              = 'wc_facebook_product_catalog_id';
	const OPTION_PIXEL_ID                        = 'wc_facebook_pixel_id';
	const OPTION_PROFILES                        = 'wc_facebook_profiles';
	const OPTION_INSTALLED_FEATURES              = 'wc_facebook_installed_features';
	const OPTION_HAS_CONNECTED_FBE_2             = 'wc_facebook_has_connected_fbe_2';
	const OPTION_HAS_AUTHORIZED_PAGES            = 'wc_facebook_has_authorized_pages_read_engagement';

	/** @var string Nonce action */
	const NONCE_ACTION = 'wc_facebook_ajax_token_update';

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( __CLASS__, 'init_rest_endpoint' ) );
	}

	// ==========================
	// = Settings Management    =
	// ==========================

	/**
	 * Validates if the required tokens are present.
	 *
	 * @since 2.0.0
	 * @param array $tokens Array of tokens to validate.
	 * @return bool True if all required tokens are present.
	 */
	private static function validate_required_tokens( $tokens ) {
		return ! empty( $tokens['access_token'] ) && ! empty( $tokens['merchant_access_token'] ) && ! empty( $tokens['page_access_token'] );
	}

	/**
	 * Updates Facebook settings options.
	 *
	 * @since 2.0.0
	 * @param array $settings Array of settings to update.
	 * @return void
	 */
	private static function update_settings( $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( ! empty( $key ) ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Sanitizes and retrieves a value from an array.
	 *
	 * @since 2.0.0
	 * @param array  $data Array to retrieve value from.
	 * @param string $key Key to retrieve.
	 * @param bool   $sanitize Whether to sanitize the value.
	 * @return mixed|string The value or empty string if not set.
	 */
	private static function get_param_value( $data, $key, $sanitize = true ) {
		if ( ! isset( $data[ $key ] ) ) {
			return '';
		}

		$value = $data[ $key ];

		if ( $sanitize && is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return $value;
	}

	/**
	 * Maps request parameters to option names.
	 *
	 * @since 2.0.0
	 * @param array $params Request parameters.
	 * @return array Mapped options with values.
	 */
	private static function map_params_to_options( $params ) {
		$options = array();

		// Define parameter to option mapping
		$mapping = array(
			'access_token'                    => self::OPTION_ACCESS_TOKEN,
			'merchant_access_token'           => self::OPTION_MERCHANT_ACCESS_TOKEN,
			'page_access_token'               => self::OPTION_PAGE_ACCESS_TOKEN,
			'system_user_id'                  => self::OPTION_SYSTEM_USER_ID,
			'business_manager_id'             => self::OPTION_BUSINESS_MANAGER_ID,
			'ad_account_id'                   => self::OPTION_AD_ACCOUNT_ID,
			'instagram_business_id'           => self::OPTION_INSTAGRAM_BUSINESS_ID,
			'commerce_merchant_settings_id'   => self::OPTION_COMMERCE_MERCHANT_SETTINGS_ID,
			'external_business_id'            => self::OPTION_EXTERNAL_BUSINESS_ID,
			'commerce_partner_integration_id' => self::OPTION_COMMERCE_PARTNER_INTEGRATION_ID,
			'product_catalog_id'              => self::OPTION_PRODUCT_CATALOG_ID,
			'pixel_id'                        => self::OPTION_PIXEL_ID,
			'profiles'                        => self::OPTION_PROFILES,
			'installed_features'              => self::OPTION_INSTALLED_FEATURES,
			// Integration settings with special handling
			'page_id'                         => \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID,
			'catalog_id'                      => \WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID,
		);

		// Process each parameter
		foreach ( $mapping as $param_key => $option_name ) {
			if ( isset( $params[ $param_key ] ) ) {
				// Skip if this is an alias and we've already processed the canonical field
				if ( 'product_catalog_id' === $param_key && isset( $params['catalog_id'] ) ) {
					continue;
				}

				// Determine if we should sanitize
				$sanitize = ! in_array( $param_key, array( 'profiles', 'installed_features' ), true );

				$options[ $option_name ] = self::get_param_value( $params, $param_key, $sanitize );
			}
		}

		return $options;
	}

	/**
	 * Updates connection status flags based on tokens.
	 *
	 * @since 2.0.0
	 * @param array $params Parameters containing tokens.
	 * @return void
	 */
	private static function update_connection_status( $params ) {
		if ( ! empty( $params['access_token'] ) ) {
			update_option( self::OPTION_HAS_CONNECTED_FBE_2, 'yes' );
		}

		if ( ! empty( $params['page_access_token'] ) ) {
			update_option( self::OPTION_HAS_AUTHORIZED_PAGES, 'yes' );
		}
	}

	/**
	 * Clears Facebook integration options.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function clear_integration_options() {
		$options = array(
			// Connection handler options
			self::OPTION_ACCESS_TOKEN,
			self::OPTION_MERCHANT_ACCESS_TOKEN,
			self::OPTION_PAGE_ACCESS_TOKEN,
			self::OPTION_SYSTEM_USER_ID,
			self::OPTION_BUSINESS_MANAGER_ID,
			self::OPTION_AD_ACCOUNT_ID,
			self::OPTION_INSTAGRAM_BUSINESS_ID,
			self::OPTION_COMMERCE_MERCHANT_SETTINGS_ID,
			self::OPTION_EXTERNAL_BUSINESS_ID,
			self::OPTION_COMMERCE_PARTNER_INTEGRATION_ID,

			// Additional data stored during connection
			self::OPTION_PROFILES,
			self::OPTION_INSTALLED_FEATURES,

			// Connection status flags
			self::OPTION_HAS_CONNECTED_FBE_2,
			self::OPTION_HAS_AUTHORIZED_PAGES,
		);

		// Clear all options
		foreach ( $options as $option_name ) {
			if ( in_array( $option_name, array( self::OPTION_PROFILES, self::OPTION_INSTALLED_FEATURES ), true ) ) {
				update_option( $option_name, null );
			} elseif ( in_array( $option_name, array( self::OPTION_HAS_CONNECTED_FBE_2, self::OPTION_HAS_AUTHORIZED_PAGES ), true ) ) {
				update_option( $option_name, 'no' );
			} else {
				update_option( $option_name, '' );
			}
		}

		// Integration settings - use constants for consistency
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, '' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, '' );
	}

	// ==========================
	// = API Communication      =
	// ==========================

	/**
	 * Makes an API call to Facebook's Graph API.
	 *
	 * @param string $method HTTP method (GET, POST, etc.)
	 * @param string $endpoint API endpoint
	 * @param array  $params Request parameters
	 * @return array Response data
	 * @throws \Exception If the request fails.
	 */
	private static function call_api( $method, $endpoint, $params ) {
		$url = 'https://graph.facebook.com/' . self::API_VERSION . '/' . $endpoint;

		if ( 'GET' === $method ) {
			$url = add_query_arg( $params, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $params );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		// Check for API errors
		if ( $status_code >= 400 ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error', 'facebook-for-woocommerce' );
			throw new \Exception( sprintf( 'Facebook API error (%d): %s', $status_code, $error_message ) );
		}

		return $data;
	}

	// ==========================
	// = REST API Endpoints     =
	// ==========================

	/**
	 * REST API endpoint initialization.
	 *
	 * @return void
	 */
	public static function init_rest_endpoint() {
		register_rest_route(
			'wc-facebook/v1',
			'update_fb_settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_update_fb_settings' ),
				'permission_callback' => array( __CLASS__, 'rest_update_fb_settings_permission_callback' ),
			)
		);

		register_rest_route(
			'wc-facebook/v1',
			'uninstall',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_handle_uninstall' ),
				'permission_callback' => array( __CLASS__, 'rest_update_fb_settings_permission_callback' ),
			)
		);
	}

	/**
	 * Permission callback for the REST API endpoint.
	 *
	 * @return bool
	 */
	public static function rest_update_fb_settings_permission_callback() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * REST API endpoint callback to update Facebook settings.
	 *
	 * Expects POST parameters:
	 *  - merchant_access_token: merchant access token (required).
	 *  - access_token: system user access token (required).
	 *  - page_access_token: page access token.
	 *  - product_catalog_id: product catalog ID.
	 *  - pixel_id: pixel ID.
	 *  - page_id: page ID.
	 *  - commerce_partner_integration_id: commerce partner integration ID.
	 *  - profiles: profiles data.
	 *  - installed_features: installed features data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_update_fb_settings( WP_REST_Request $request ) {
		// Get JSON data from request body
		$params = $request->get_json_params();

		// Required parameter check
		if ( empty( $params['merchant_access_token'] ) ) {
			return new WP_Error(
				'missing_token',
				__( 'Missing merchant access token', 'facebook-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		// Map parameters to options and update settings
		$options = self::map_params_to_options( $params );
		self::update_settings( $options );

		// Update connection status flags
		self::update_connection_status( $params );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Facebook settings updated successfully', 'facebook-for-woocommerce' ),
			),
			200
		);
	}

	/**
	 * REST API endpoint callback to handle uninstall requests.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_handle_uninstall() {
		try {
			// Try to disconnect from Facebook API first
			$external_business_id = get_option( self::OPTION_EXTERNAL_BUSINESS_ID, '' );
			if ( ! empty( $external_business_id ) ) {
				try {
					facebook_for_woocommerce()->get_api()->delete_mbe_connection( (string) $external_business_id );
				} catch ( \Exception $e ) {
					facebook_for_woocommerce()->log( sprintf( 'Error during API uninstall: %s', $e->getMessage() ) );
					// Continue with local disconnection even if API call fails
				}
			}

			// Clear all integration options
			self::clear_integration_options();

			// Get the integration instance and update the product catalog ID
			$integration = facebook_for_woocommerce()->get_integration();
			if ( $integration ) {
				$integration->update_product_catalog_id( '' );
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Facebook integration successfully uninstalled', 'facebook-for-woocommerce' ),
				),
				200
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'uninstall_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	// ==========================
	// = IFrame Management      =
	// ==========================

	/**
	 * Generates the Commerce Hub iframe splash page URL.
	 *
	 * @param bool   $is_connected         Whether the plugin is currently connected.
	 * @param object $plugin               The plugin instance.
	 * @param string $external_business_id External business ID.
	 * @return string
	 */
	public static function generate_iframe_splash_url( $is_connected, $plugin, $external_business_id ): string {
		$connection_handler       = facebook_for_woocommerce()->get_connection_handler();
		$external_client_metadata = array(
			'shop_domain'                           => wc_get_page_permalink( 'shop' ),
			'admin_url'                             => admin_url(),
			'client_version'                        => $plugin->get_version(),
			'commerce_partner_seller_platform_type' => 'SELF_SERVE_PLATFORM',
			'country_code'                          => WC()->countries->get_base_country(),
			'platform_store_id'                     => get_current_blog_id(),
		);
		return add_query_arg(
			array(
				'access_client_token'      => self::CLIENT_TOKEN,
				'business_vertical'        => 'ECOMMERCE',
				'channel'                  => 'COMMERCE',
				'app_id'                   => Connection::CLIENT_ID,
				'business_name'            => rawurlencode( $connection_handler->get_business_name() ),
				'currency'                 => get_woocommerce_currency(),
				'timezone'                 => $connection_handler->get_timezone_string(),
				'external_business_id'     => $external_business_id,
				'installed'                => $is_connected,
				'external_client_metadata' => rawurlencode( wp_json_encode( $external_client_metadata ) ),
			),
			self::COMMERCE_HUB_URL . 'commerce_extension/splash/'
		);
	}

	/**
	 * Generates the Commerce Hub iframe management page URL.
	 *
	 * @param string $external_business_id External business ID.
	 * @return string
	 */
	public static function generate_iframe_management_url( $external_business_id ) {
		$access_token = get_option( self::OPTION_ACCESS_TOKEN, '' );

		if ( empty( $access_token ) ) {
			return '';
		}

		try {
			$request = array(
				'access_token'             => $access_token,
				'fields'                   => 'commerce_extension',
				'fbe_external_business_id' => $external_business_id,
			);

			$response = self::call_api( 'GET', 'fbe_business', $request );

			if ( ! empty( $response['commerce_extension']['uri'] ) ) {
				return $response['commerce_extension']['uri'];
			}
		} catch ( \Exception $e ) {
			facebook_for_woocommerce()->log( 'Facebook Commerce Extension URL Error: ' . $e->getMessage() );
		}
		return '';
	}
}
