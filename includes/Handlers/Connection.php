<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

use WooCommerce\Facebook\API;
use WooCommerce\Facebook\API\Exceptions\ConnectApiException;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Utilities\Heartbeat;

defined( 'ABSPATH' ) or exit;

/**
 * The connection handler.
 *
 * @since 2.0.0
 */
class Connection {


	/** @var string Facebook client identifier */
	const CLIENT_ID = '474166926521348';

	/** @var string Facebook OAuth URL */
	const OAUTH_URL = 'https://facebook.com/dialog/oauth';

	/** @var string WooCommerce connection proxy URL */
	const PROXY_URL = 'https://api.woocommerce.com/integrations/v2/auth/facebook/';

	const PROXY_TOKEN_EXCHANGE_URL = 'https://api.woocommerce.com/integrations/v2/exchange/facebook/';

	/** @var string WooCommerce connection for APP Store login URL */
	const APP_STORE_LOGIN_URL = 'https://api.woocommerce.com/integrations/app-store-login/facebook/';

	/** @var string WooCommerce connection authentication URL */
	const CONNECTION_AUTHENTICATION_URL = 'https://api.woocommerce.com/integrations/auth/facebookcommerce/';

	/** @var string the Standard Auth type */
	const AUTH_TYPE_STANDARD = 'standard';

	/** @var string the action callback for the connection */
	const ACTION_CONNECT = 'wc_facebook_connect';

	const ACTION_EXCHANGE = 'wc_facebook_exchange';

	/** @var string the action callback for the disconnection */
	const ACTION_DISCONNECT = 'wc_facebook_disconnect';

	/** @var string the action callback for FBE redirection */
	const ACTION_FBE_REDIRECT = 'wc_fbe_redirect';

	/** @var string the action callback for the connection */
	const ACTION_CONNECT_COMMERCE = 'wc_facebook_connect_commerce';

	/** @var string the WordPress option name where the external business ID is stored */
	const OPTION_EXTERNAL_BUSINESS_ID = 'wc_facebook_external_business_id';

	/** @var string the business manager ID option name */
	const OPTION_BUSINESS_MANAGER_ID = 'wc_facebook_business_manager_id';

	/** @var string the ad account ID option name */
	const OPTION_AD_ACCOUNT_ID = 'wc_facebook_ad_account_id';

	/** @var string the system user ID option name */
	const OPTION_SYSTEM_USER_ID = 'wc_facebook_system_user_id';

	/** @var string the system user access token option name */
	const OPTION_ACCESS_TOKEN = 'wc_facebook_access_token';

	/** @var string the merchant access token option name */
	const OPTION_MERCHANT_ACCESS_TOKEN = 'wc_facebook_merchant_access_token';

	/** @var string the page access token option name */
	const OPTION_PAGE_ACCESS_TOKEN = 'wc_facebook_page_access_token';

	/** @var string the Commerce manager ID option name */
	const OPTION_COMMERCE_MANAGER_ID = 'wc_facebook_commerce_manager_id';

	/** @var string webhook event subscribed object */
	const WEBHOOK_SUBSCRIBED_OBJECT = 'user';

	/** @var string webhook event subscribed field */
	const WEBHOOK_SUBSCRIBED_FIELD = 'fbe_install';

	/** @var string Instagram Business ID option name */
	const OPTION_INSTAGRAM_BUSINESS_ID = 'wc_facebook_instagram_business_id';

	/** @var string the Commerce merchant settings ID option name */
	const OPTION_COMMERCE_MERCHANT_SETTINGS_ID = 'wc_facebook_commerce_merchant_settings_id';

	/** @var string|null the generated external merchant settings ID */
	private $external_business_id;

	/** @var \WC_Facebookcommerce */
	private $plugin;

	/** @var array */
	protected $proxy_error_messages;

	/**
	 * Constructs a new Connection.
	 *
	 * @since 2.0.0
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {

		$this->plugin = $plugin;

		add_action( Heartbeat::HOURLY, array( $this, 'refresh_business_configuration' ) );

		add_action( Heartbeat::DAILY, array( $this, 'refresh_installation_data' ) );

		add_action( 'woocommerce_api_' . self::ACTION_CONNECT, array( $this, 'handle_connect' ) );

		add_action( 'admin_action_' . self::ACTION_DISCONNECT, array( $this, 'handle_disconnect' ) );

		add_action( 'woocommerce_api_' . self::ACTION_FBE_REDIRECT, array( $this, 'handle_fbe_redirect' ) );

		add_action( 'fbe_webhook', array( $this, 'fbe_install_webhook' ) );

		add_action( 'rest_api_init', array( $this, 'init_extras_endpoint' ) );
	}


	/**
	 * Refreshes the local business configuration data with the latest from Facebook.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function refresh_business_configuration() {

		// bail if not connected
		if ( ! $this->is_connected() ) {
			return;
		}

		try {

			$response = $this->get_plugin()->get_api()->get_business_configuration( $this->get_external_business_id() );
			facebook_for_woocommerce()->get_tracker()->track_facebook_business_config(
				$response->is_ig_shopping_enabled(),
				$response->is_ig_cta_enabled()
			);

		} catch ( ApiException $exception ) {

			$this->get_plugin()->log( 'Could not refresh business configuration. ' . $exception->getMessage() );
		}

	}


	/**
	 * Refreshes the connected installation data.
	 *
	 * @since 2.0.0
	 */
	public function refresh_installation_data() {

		// bail if not connected
		if ( ! $this->is_connected() ) {
			return;
		}

		try {

			$this->update_installation_data();

		} catch ( ApiException $exception ) {

			$this->get_plugin()->log( 'Could not refresh installation data. ' . $exception->getMessage() );
		}

	}


	/**
	 * Retrieves and stores the connected installation data.
	 *
	 * @since 2.0.0
	 *
	 * @throws ApiException
	 */
	private function update_installation_data() {

		$response = $this->get_plugin()->get_api()->get_installation_ids( $this->get_external_business_id() );

		$page_id = sanitize_text_field( $response->get_page_id() );

		if ( $page_id ) {

			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, $page_id );

			// get and store a current access token for the configured page
			$page_access_token = $this->retrieve_page_access_token( $page_id );

			$this->update_page_access_token( $page_access_token );
		}

		if ( $response->get_pixel_id() ) {
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, sanitize_text_field( $response->get_pixel_id() ) );
		}

		if ( $response->get_catalog_id() ) {
			update_option( \WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID, sanitize_text_field( $response->get_catalog_id() ) );
		}

		if ( $response->get_business_manager_id() ) {
			$this->update_business_manager_id( sanitize_text_field( $response->get_business_manager_id() ) );
		}

		if ( $response->get_ad_account_id() ) {
			$this->update_ad_account_id( sanitize_text_field( $response->get_ad_account_id() ) );
		}

		if ( $response->get_instagram_business_id() ) {
			$this->update_instagram_business_id( sanitize_text_field( $response->get_instagram_business_id() ) );
		}

		if ( $response->get_commerce_merchant_settings_id() ) {
			$this->update_commerce_merchant_settings_id( sanitize_text_field( $response->get_commerce_merchant_settings_id() ) );
		}
	}


	/**
	 * Processes the returned connection.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function handle_connect() {
		// don't handle anything unless the user can manage WooCommerce settings
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		try {
			if ( empty( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['nonce'] ) ), self::ACTION_CONNECT ) ) {
				throw new ApiException( 'Invalid nonce' );
			}

			$is_error   = ! empty( $_GET['err'] );
			$error_code = ! empty( $_GET['err_code'] ) ? stripslashes( wc_clean( wp_unslash( $_GET['err_code'] ) ) ) : '';
			if ( $is_error && $error_code ) {
				throw new ConnectApiException( $error_code );
			}

			$facebook_auth_code = $_GET['code'] ?? '';
			if ( empty( $facebook_auth_code ) ) {
				throw new ApiException( 'Facebook auth code is missing.' );
			}

			$state = $_GET['state'] ?? '';
			if ( empty( $state ) ) {
				throw new ApiException( 'Missing state query parameter.' );
			}

			$parameters_string = '?' . http_build_query( array(
					'nonce'                => wp_create_nonce( self::ACTION_EXCHANGE ),
					'code'                 => $facebook_auth_code,
					'external_business_id' => $this->get_external_business_id(),
					'type'                 => self::AUTH_TYPE_STANDARD,
					'state'                => $state,
				) );

			$request_url = self::PROXY_TOKEN_EXCHANGE_URL . $parameters_string;
			$response    = wp_safe_remote_get(
				$request_url,
				array(
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new ApiException( 'WooCommerce Connect Server token exchange has failed.' );
			}

			$token_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $token_data[ 'status' ] ) && $token_data[ 'status' ] === 500 ) {
				throw new ApiException( 'WooCommerce Connect Server token exchange has failed.' );
			}

			// Check that request was initiated from the server.
			if ( ! wp_verify_nonce( $token_data['nonce'] ?? '', self::ACTION_EXCHANGE ) ) {
				throw new ApiException( 'Exchange nonce is not valid.' );
			}

			$merchant_access_token    = wc_clean( wp_unslash( $token_data['merchant_access_token']    ?? '' ) ) ;
			$system_user_access_token = wc_clean( wp_unslash( $token_data['system_user_access_token'] ?? '' ) ) ;
			$system_user_id           = wc_clean( wp_unslash( $token_data['system_user_id']           ?? '' ) ) ;

			if ( ! $merchant_access_token ) {
				throw new ApiException( 'Access token is missing' );
			}
			if ( ! $system_user_access_token ) {
				throw new ApiException( 'System User access token is missing' );
			}
			if ( ! $system_user_id ) {
				throw new ApiException( 'System User ID is missing' );
			}
			$this->update_access_token( $system_user_access_token );
			$this->update_merchant_access_token( $merchant_access_token );
			$this->update_system_user_id( $system_user_id );
			$this->update_installation_data();
			// Allow opt-out of full batch-API sync, for example if store has a large number of products.
			if ( facebook_for_woocommerce()->get_integration()->allow_full_batch_api_sync() ) {
				facebook_for_woocommerce()->get_products_sync_handler()->create_or_update_all_products();
			}
			else {
				facebook_for_woocommerce()->log( 'Initial full product sync disabled by filter hook `facebook_for_woocommerce_allow_full_batch_api_sync`', 'facebook_for_woocommerce_connect' );
			}
			facebook_for_woocommerce()->get_product_sets_sync_handler()->sync_all_product_sets();
			update_option( 'wc_facebook_has_connected_fbe_2', 'yes' );
			update_option( 'wc_facebook_has_authorized_pages_read_engagement', 'yes' );
			// redirect to the Commerce onboarding if directed to do so
			if ( ! empty( Helper::get_requested_value( 'connect_commerce' ) ) ) {
				wp_safe_redirect( $this->get_commerce_connect_url() );
				exit;
			}
			facebook_for_woocommerce()->get_message_handler()->add_message( __( 'Connection successful!', 'facebook-for-woocommerce' ) );
			wp_safe_redirect( facebook_for_woocommerce()->get_advertise_tab_url() );
			exit;
		} catch ( ApiException $exception ) {
			facebook_for_woocommerce()->log( sprintf( 'Connection failed: %s', $exception->getMessage() ) );
			set_transient( 'wc_facebook_connection_failed', time(), 30 );
		} catch ( ConnectApiException $exception ) {
			$message = $this->prepare_connect_server_message_for_user_display( $exception->getMessage() );
			facebook_for_woocommerce()->log( sprintf( 'Failed to connect to Facebook. Reason: %s', $message ), 'facebook_for_woocommerce_connect' );
			set_transient( 'wc_facebook_connection_failed', time(), 30 );
		}

		wp_safe_redirect( facebook_for_woocommerce()->get_settings_url() );
		exit;
	}

	/**
	 * Prepares the error message from the connect server for the logs.
	 *
	 * @since 2.6.8
	 * @param string $message Message string that needs formatting.
	 * @return string Formatted message string ready for logging.
	 */
	public function prepare_connect_server_message_for_user_display( $message ) {
			/*
			 * In some scenarios the connect server message is a JSON encoded object.
			 * This happens when we have detailed information from Facebook API endpoint about the error.
			 * We want to print it pretty for the customers.
			 */
			$decoded_message = json_decode( $message );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				// If error is the first key we want to use just the body to simplify the message.
				$decoded_message = isset( $decoded_message->error ) ? $decoded_message->error : $decoded_message;
				$message         = json_encode( $decoded_message, JSON_PRETTY_PRINT );
			}
			return $message;
	}


	/**
	 * Disconnects the integration using the Graph API.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function handle_disconnect() {
		check_admin_referer( self::ACTION_DISCONNECT );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to uninstall Facebook Business Extension.', 'facebook-for-woocommerce' ) );
		}
		try {
			$external_business_id = $this->get_external_business_id();
			if ( null != $external_business_id ) {
				$response = facebook_for_woocommerce()->get_api()->delete_mbe_connection((string) $external_business_id );
				facebook_for_woocommerce()->get_message_handler()->add_message( __( 'Disconnection successful.', 'facebook-for-woocommerce' ) );

				$body = wp_remote_retrieve_body( $response );
				$body = json_decode( $body, true );
				if ( ! is_array( $body ) || empty( $body['data'] ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
					facebook_for_woocommerce()->log( 'Failed to disconnect' );
					facebook_for_woocommerce()->log( print_r( $body, true ) );
					throw new ApiException(
						sprintf(wp_remote_retrieve_response_message( $response ))
					);
				}
			} else {
				facebook_for_woocommerce()->log( 'External business id not found for the disconnection procedure, connection will be reset.' );
			}
		} catch ( ApiException $exception ) {
			facebook_for_woocommerce()->log( sprintf( 'An error occurred during disconnection: %s.', $exception->getMessage() ) );
		} catch ( \Exception $exception ) {
			facebook_for_woocommerce()->log( sprintf( 'Internal error occurred during disconnection: %s.', $exception->getMessage() ) );
		} finally {
			$this->disconnect();
			facebook_for_woocommerce()->log( sprintf( 'Your Facebook connection settings have been reset.' ) );
			wp_safe_redirect( facebook_for_woocommerce()->get_settings_url() );
			exit;
		}
	}


	/**
	 * Disconnects the plugin.
	 *
	 * Deletes local asset data.
	 *
	 * @since 2.0.0
	 */
	public function disconnect() {
		$this->update_access_token( '' );
		$this->update_page_access_token( '' );
		$this->update_merchant_access_token( '' );
		$this->update_system_user_id( '' );
		$this->update_business_manager_id( '' );
		$this->update_ad_account_id( '' );
		$this->update_instagram_business_id( '' );
		$this->update_commerce_merchant_settings_id( '' );
		$this->update_external_business_id( '' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, '' );
		update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, '' );
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id( '' );
	}


	/**
	 * Retrieves the configured page access token remotely.
	 *
	 * @since 2.1.0
	 *
	 * @param string $page_id desired Facebook page ID
	 * @return string
	 * @throws ApiException
	 */
	private function retrieve_page_access_token( $page_id ) {
		facebook_for_woocommerce()->log( 'Retrieving page access token' );
		$api_url = Api::GRAPH_API_URL . Api::API_VERSION;
		$response = wp_remote_get( $api_url . '/me/accounts?access_token=' . $this->get_access_token() );
		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );
		if ( ! is_array( $body ) || empty( $body['data'] ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			facebook_for_woocommerce()->log( print_r( $body, true ) );
			throw new ApiException(
				sprintf(
					/* translators: Placeholders: %s - API error message */
					__( 'Could not retrieve page access data. %s', 'facebook for woocommerce' ),
					wp_remote_retrieve_response_message( $response )
				)
			);
		}
		$page_access_tokens = wp_list_pluck( $body['data'], 'access_token', 'id' );
		// bail if the user isn't authorized to manage the page
		if ( empty( $page_access_tokens[ $page_id ] ) ) {
			throw new ApiException(
				sprintf(
				/* translators: Placeholders: %s - Facebook page ID */
					__( 'Page %s not authorized.', 'facebook-for-woocommerce' ),
					$page_id
				)
			);
		}
		return $page_access_tokens[ $page_id ];
	}


	/**
	 * Gets the API access token.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_access_token() {
		$access_token = get_option( self::OPTION_ACCESS_TOKEN, '' );
		/**
		 * Filters the API access token.
		 *
		 * @since 2.0.0
		 *
		 * @param string $access_token access token
		 * @param Connection $connection connection handler instance
		 */
		return apply_filters( 'wc_facebook_connection_access_token', $access_token, $this );
	}


	/**
	 * Gets the merchant access token.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_merchant_access_token() {
		$access_token = get_option( self::OPTION_MERCHANT_ACCESS_TOKEN, '' );
		/**
		 * Filters the merchant access token.
		 *
		 * @since 2.0.0
		 *
		 * @param string $access_token access token
		 * @param Connection $connection connection handler instance
		 */
		return apply_filters( 'wc_facebook_connection_merchant_access_token', $access_token, $this );
	}


	/**
	 * Gets the page access token.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_page_access_token() {
		$access_token = get_option( self::OPTION_PAGE_ACCESS_TOKEN, '' );
		/**
		 * Filters the page access token.
		 *
		 * @since 2.1.0
		 *
		 * @param string $access_token page access token
		 * @param Connection $connection connection handler instance
		 */
		return (string) apply_filters( 'wc_facebook_connection_page_access_token', $access_token, $this );
	}


	/**
	 * Gets the URL to start the connection flow.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $connect_commerce whether to connect to Commerce after successful FBE connection
	 * @return string
	 */
	public function get_connect_url( $connect_commerce = false ) {
		// nosemgrep: audit.php.wp.security.xss.query-arg
		return add_query_arg( rawurlencode_deep( $this->get_connect_parameters( $connect_commerce ) ), self::OAUTH_URL );
	}


	/**
	 * Builds the Commerce connect URL.
	 *
	 * The base URL is https://www.facebook.com/commerce_manager/onboarding with two query variables:
	 * - app_id - the developer app ID
	 * - redirect_url - the URL where the user will land after onboarding is complete
	 *
	 * The redirect URL must be an approved domain, so it must be the connect.woocommerce.com proxy app. In that URL, we
	 * include the final site URL, which is where the merchant will redirect to with the data that needs to be stored.
	 * So the final URL looks like this without encoding:
	 *
	 * https://www.facebook.com/commerce_manager/onboarding/?app_id={id}&redirect_url=https://connect.woocommerce.com/auth/facebook/?site_url=https://example.com/?wc-api=wc_facebook_connect_commerce&nonce=1234
	 *
	 * If testing only, &is_test_mode=true can be appended to the URL using the wc_facebook_commerce_connect_url filter
	 * to trigger the test account flow, where fake US-based business details can be used.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_commerce_connect_url() {
		// build the site URL to which the user will ultimately return
		$site_url = add_query_arg(
			array(
				'wc-api' => self::ACTION_CONNECT_COMMERCE,
				'nonce'  => wp_create_nonce( self::ACTION_CONNECT_COMMERCE ),
			),
			home_url( '/' )
		);
		// build the proxy app URL where the user will land after onboarding, to be redirected to the site URL
		$redirect_url = add_query_arg( 'site_url', urlencode( $site_url ), $this->get_connection_authentication_url() );
		// build the final connect URL, direct to Facebook
		$connect_url = add_query_arg(
			array(
				'app_id'       => $this->get_client_id(), // this endpoint calls the client ID "app ID"
				'redirect_url' => urlencode( $redirect_url ),
			),
			'https://www.facebook.com/commerce_manager/onboarding/'
		);
		/**
		 * Filters the URL used to connect to Facebook Commerce.
		 *
		 * @since 2.1.0
		 *
		 * @param string $connect_url connect URL
		 */
		return apply_filters( 'wc_facebook_commerce_connect_url', $connect_url );
	}


	/**
	 * Gets the URL for disconnecting.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_disconnect_url() {
		return wp_nonce_url( add_query_arg( 'action', self::ACTION_DISCONNECT, admin_url( 'admin.php' ) ), self::ACTION_DISCONNECT );
	}


	/**
	 * Gets the scopes that will be requested during the connection flow.
	 *
	 * @since 2.0.0
	 *
	 * @link https://developers.facebook.com/docs/marketing-api/access/#access_token
	 *
	 * @return string[]
	 */
	public function get_scopes() {
		$scopes = array(
			'manage_business_extension',
			'catalog_management',
			'ads_management',
			'ads_read',
			'pages_read_engagement', // this scope is needed to enable order management if using the Commerce feature
			'instagram_basic',
		);
		/**
		 * Filters the scopes that will be requested during the connection flow.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $scopes connection scopes
		 * @param Connection $connection connection handler instance
		 */
		return (array) apply_filters( 'wc_facebook_connection_scopes', $scopes, $this );
	}


	/**
	 * Gets the stored external business ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_external_business_id() {
		if ( ! is_string( $this->external_business_id ) ) {
			$external_id = get_option( self::OPTION_EXTERNAL_BUSINESS_ID );
			if ( ! is_string( $external_id ) || empty( $external_id ) ) {
				/**
				 * Filters the shop's business external ID.
				 *
				 * This is passed to Facebook when connecting.
				 * Should be non-empty and without special characters, otherwise the ID will be obtained from the site URL as fallback.
				 *
				 * @since 2.0.0
				 *
				 * @param string $external_id the shop's business external ID
				 */
				$external_id = sanitize_key( (string) apply_filters( 'wc_facebook_connection_business_id', get_bloginfo( 'name' ) ) );
				if ( empty( $external_id ) ) {
					$external_id = sanitize_key( str_replace( array( 'http', 'https', 'www' ), '', get_bloginfo( 'url' ) ) );
				}
				$external_id = uniqid( sprintf( '%s-', $external_id ), false );
				$this->update_external_business_id( $external_id );
			}
			$this->external_business_id = $external_id;
		}

		/**
		 * Filters the external business ID.
		 *
		 * @since 2.0.0
		 *
		 * @param string $external_business_id stored external business ID
		 * @param Connection $connection connection handler instance
		 */
		return (string) apply_filters( 'wc_facebook_external_business_id', $this->external_business_id, $this );
	}


	/**
	 * Gets the site's business name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_business_name() {
		$business_name = get_bloginfo( 'name' );
		/**
		 * Filters the shop's business name.
		 *
		 * This is passed to Facebook when connecting.
		 * Defaults to the site name. Should be non-empty, otherwise the site URL will be used as fallback.
		 *
		 * @since 2.0.0
		 *
		 * @param string $business_name the shop's business name
		 */
		$business_name = trim( (string) apply_filters( 'wc_facebook_connection_business_name', is_string( $business_name ) ? $business_name : '' ) );
		if ( empty( $business_name ) ) {
			$business_name = get_bloginfo( 'url' );
		}
		return html_entity_decode( $business_name, ENT_QUOTES, 'UTF-8' );
	}


	/**
	 * Gets the business manager ID value.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_business_manager_id() {
		$business_manager_id = get_option( self::OPTION_BUSINESS_MANAGER_ID, '' );
		/**
		 * Filters the Business Manager ID.
		 *
		 * @since 3.0.31
		 *
		 * @param string     $business_manager_id Business Manager ID.
		 * @param Connection $connection          The Connection handler instance.
		 */
		return (string) apply_filters( 'wc_facebook_business_manager_id', $business_manager_id, $this );
	}


	/**
	 * Gets the ad account ID value.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_ad_account_id() {
		return get_option( self::OPTION_AD_ACCOUNT_ID, '' );
	}


	/**
	 * Gets the System User ID value.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_system_user_id() {
		return get_option( self::OPTION_SYSTEM_USER_ID, '' );
	}


	/**
	 * Gets the Commerce manager ID value.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_commerce_manager_id() {
		return get_option( self::OPTION_COMMERCE_MANAGER_ID, '' );
	}


	/**
	 * Gets Instagram Business ID value.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_instagram_business_id() {
		return get_option( self::OPTION_INSTAGRAM_BUSINESS_ID, '' );
	}


	/**
	 * Gets Commerce merchant settings ID value.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_commerce_merchant_settings_id() {
		return get_option( self::OPTION_COMMERCE_MERCHANT_SETTINGS_ID, '' );
	}


	/**
	 * Gets the proxy URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string URL
	 */
	public function get_proxy_url() {
		/**
		 * Filters the proxy URL.
		 *
		 * @since 2.0.0
		 *
		 * @param string $proxy_url the connection proxy URL
		 */
		return (string) apply_filters( 'wc_facebook_connection_proxy_url', self::PROXY_URL );
	}


	/**
	 * Gets APP Store Login URL.
	 *
	 * @since 2.3.0
	 *
	 * @return string URL
	 */
	public function get_app_store_login_url() {
		/**
		 * Filters App Store login URL.
		 *
		 * @since 2.3.0
		 *
		 * @param string $app_store_login_url the connection App Store login URL
		 */
		return (string) apply_filters( 'wc_facebook_connection_app_store_login_url', self::APP_STORE_LOGIN_URL );
	}

	/**
	 * Gets connect server authentication url.
	 *
	 * @since 2.6.8
	 *
	 * @return string URL
	 */
	public function get_connection_authentication_url() {
		/**
		 * Filters App Store login URL.
		 *
		 * @since 2.6.8
		 *
		 * @param string $connection_authentication_url the connection App Store login URL
		 */
		return (string) apply_filters( 'wc_facebook_connection_authentication_url', self::CONNECTION_AUTHENTICATION_URL );
	}

	/**
	 * Gets the full redirect URL where the user will return to after OAuth.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_redirect_url() {
		$redirect_url = add_query_arg(
			array(
				'wc-api'               => self::ACTION_CONNECT,
				'external_business_id' => $this->get_external_business_id(),
				'nonce'                => wp_create_nonce( self::ACTION_CONNECT ),
				'type'                 => self::AUTH_TYPE_STANDARD,
			),
			home_url( '/' )
		);
		/**
		 * Filters the redirect URL where the user will return to after OAuth.
		 *
		 * @since 2.0.0
		 *
		 * @param string $redirect_url redirect URL
		 * @param Connection $connection connection handler instance
		 */
		return (string) apply_filters( 'wc_facebook_connection_redirect_url', $redirect_url, $this );
	}


	/**
	 * Gets the full set of connection parameters for starting OAuth.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $connect_commerce whether to connect to Commerce after successful FBE connection
	 * @return array
	 */
	public function get_connect_parameters( $connect_commerce = false ) {
		$state = $this->get_redirect_url();
		if ( $connect_commerce ) {
			$state = add_query_arg( 'connect_commerce', true, $state );
		}

		/**
		 * Filters the connection parameters.
		 *
		 * @since 2.0.0
		 *
		 * @param array $parameters connection parameters
		 */
		// nosemgrep: audit.php.wp.security.xss.query-arg
		return apply_filters(
			'wc_facebook_connection_parameters',
			array(
				'client_id'     => $this->get_client_id(),
				'redirect_uri'  => $this->get_proxy_url(),
				'state'         => $state,
				'display'       => 'page',
				'response_type' => 'code',
				'scope'         => implode( ',', $this->get_scopes() ),
				'extras'        => json_encode( $this->get_connect_parameters_extras() ),
			)
		);
	}


	/**
	 * Gets connection parameters extras.
	 *
	 * @see Connection::get_connect_parameters()
	 *
	 * @since 2.0.0
	 *
	 * @return array associative array (to be converted to JSON encoded for connection purposes)
	 */
	private function get_connect_parameters_extras() {
		$parameters = array(
			'setup'           => array(
				'external_business_id' => $this->get_external_business_id(),
				'timezone'             => $this->get_timezone_string(),
				'currency'             => get_woocommerce_currency(),
				'business_vertical'    => 'ECOMMERCE',
				'domain'               => home_url(),
				'channel'              => 'DEFAULT',
			),
			'business_config' => array(
				'business' => array(
					'name' => $this->get_business_name(),
				),
			),
			'repeat'          => false,
		);
		if ( $external_merchant_settings_id = facebook_for_woocommerce()->get_integration()->get_external_merchant_settings_id() ) {
			$parameters['setup']['merchant_settings_id'] = $external_merchant_settings_id;
		}
		return $parameters;
	}


	/**
	 * Gets the configured timezone string using values accepted by Facebook
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_timezone_string() {
		$timezone = wc_timezone_string();
		// convert +05:30 and +05:00 into Etc/GMT+5 - we ignore the minutes because Facebook does not allow minute offsets
		if ( preg_match( '/([+-])(\d{2}):\d{2}/', $timezone, $matches ) ) {
			$hours    = (int) $matches[2];
			$timezone = "Etc/GMT{$matches[1]}{$hours}";
		}
		return $timezone;
	}


	/**
	 * Stores the given ID value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value the business manager ID
	 */
	public function update_business_manager_id( $value ) {
		update_option( self::OPTION_BUSINESS_MANAGER_ID, $value );
	}


	/**
	 * Stores the given ID value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value the ad account ID
	 */
	public function update_ad_account_id( $value ) {
		update_option( self::OPTION_AD_ACCOUNT_ID, $value );
	}


	/**
	 * Stores the given system user ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value the ID
	 */
	public function update_system_user_id( $value ) {
		update_option( self::OPTION_SYSTEM_USER_ID, $value );
	}


	/**
	 * Stores the given Commerce manager ID.
	 *
	 * @since 2.1.0
	 *
	 * @param string $id the ID
	 */
	public function update_commerce_manager_id( $id ) {
		update_option( self::OPTION_COMMERCE_MANAGER_ID, $id );
	}


	/**
	 * Stores the given Instagram Business ID.
	 *
	 * @since 2.3.0
	 *
	 * @param string $id the ID
	 */
	public function update_instagram_business_id( $id ) {
		update_option( self::OPTION_INSTAGRAM_BUSINESS_ID, $id );
	}


	/**
	 * Stores the given Commerce merchant settings ID.
	 *
	 * @since 2.3.0
	 *
	 * @param string $id the ID
	 */
	public function update_commerce_merchant_settings_id( $id ) {
		update_option( self::OPTION_COMMERCE_MERCHANT_SETTINGS_ID, $id );
	}


	/**
	 * Stores the given token value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value the access token
	 */
	public function update_access_token( $value ) {
		update_option( self::OPTION_ACCESS_TOKEN, $value );
	}


	/**
	 * Stores the given merchant access token.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value the access token
	 */
	public function update_merchant_access_token( $value ) {
		update_option( self::OPTION_MERCHANT_ACCESS_TOKEN, $value );
	}


	/**
	 * Stores the given page access token.
	 *
	 * @since 2.1.0
	 *
	 * @param string $value the access token
	 */
	public function update_page_access_token( $value ) {
		update_option( self::OPTION_PAGE_ACCESS_TOKEN, is_string( $value ) ? $value : '' );
	}

	/**
	 * Stores the given external business id.
	 *
	 * @since 2.6.13
	 *
	 * @param string $value external business id
	 */
	public function update_external_business_id( $value ) {
		update_option( self::OPTION_EXTERNAL_BUSINESS_ID, is_string( $value ) ? $value : '' );
	}


	/**
	 * Determines whether the site is connected.
	 *
	 * A site is connected if there is an access token stored.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_connected() {
		return (bool) $this->get_access_token();
	}


	/**
	 * Determines whether the site has previously connected to FBE 2.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_previously_connected_fbe_2() {
		return 'yes' === get_option( 'wc_facebook_has_connected_fbe_2' );
	}


	/**
	 * Determines whether the site has previously connected to FBE 1.x.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_previously_connected_fbe_1() {
		$integration = $this->get_plugin()->get_integration();
		return $integration && $integration->get_external_merchant_settings_id();
	}


	/**
	 * Gets the client ID for connection.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_client_id() {
		/**
		 * Filters the client ID.
		 *
		 * @since 2.0.0
		 *
		 * @param string $client_id the client ID
		 */
		return apply_filters( 'wc_facebook_connection_client_id', self::CLIENT_ID );
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 2.0.0
	 *
	 * @return \WC_Facebookcommerce
	 */
	public function get_plugin() {
		return $this->plugin;
	}


	/**
	 * Process WebHook User object, install field
	 *
	 * @since 2.3.0
	 * @link https://developers.facebook.com/docs/marketing-api/fbe/fbe2/guides/get-features#webhook
	 *
	 * @param object $data WebHook event data.
	 */
	public function fbe_install_webhook( $data ) {
		// Reject other objects other than subscribed object
		if ( empty( $data ) || ! isset( $data->object ) || self::WEBHOOK_SUBSCRIBED_OBJECT !== $data->object ) {
			$this->get_plugin()->log( 'Wrong (or empty) WebHook Event received' );
			$this->get_plugin()->log( print_r( $data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			return;
		}
		$log_data = array();
		$this->get_plugin()->log( 'WebHook User Event received' );
		$this->get_plugin()->log( print_r( $data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$entry = (array) $data->entry[0];
		if ( empty( $entry ) ) {
			return;
		}
		// Filter event by subscribed field
		$event = array_filter(
			$entry['changes'],
			function( $change ) {
				return self::WEBHOOK_SUBSCRIBED_FIELD === $change->field;
			}
		);
		$values = ! empty( $event[0] ) ? $event[0]->value : '';
		if ( empty( $values ) ) {
			return;
		}
		/**
		 * If profiles, pages and instagram_profiles fields are not included in the Webhook payload, this means the business has uninstalled FBE.
		 * In this case also the field access_token will not be included.
		 *
		 * @link https://developers.facebook.com/docs/marketing-api/fbe/fbe2/guides/get-features#what-s-included-with-webhooks-
		 */
		if ( empty( $values->access_token ) ) {

			delete_option( 'wc_facebook_has_connected_fbe_2' );
			delete_option( 'wc_facebook_has_authorized_pages_read_engagement' );

			$this->disconnect();

			return;
		}
		update_option( 'wc_facebook_has_connected_fbe_2', 'yes' );
		update_option( 'wc_facebook_has_authorized_pages_read_engagement', 'yes' );
		$system_user_access_token = ! empty( $values->access_token ) ? sanitize_text_field( $values->access_token ) : '';
		$this->update_access_token( $system_user_access_token );
		$log_data[ self::OPTION_ACCESS_TOKEN ] = 'Token was saved';
		if ( ! empty( $entry['uid'] ) ) {
			$this->update_system_user_id( sanitize_text_field( $entry['uid'] ) );
			$log_data[ self::OPTION_SYSTEM_USER_ID ] = sanitize_text_field( $entry['uid'] );
		}
		$merchant_access_token = ! empty( $values->merchant_access_token ) ? sanitize_text_field( $values->merchant_access_token ) : '';
		$this->update_merchant_access_token( $merchant_access_token );
		$log_data[ self::OPTION_MERCHANT_ACCESS_TOKEN ] = 'Token was saved';

		if ( ! empty( $values->install_time ) ) {
			update_option( \WC_Facebookcommerce_Integration::OPTION_PIXEL_INSTALL_TIME, sanitize_text_field( $values->install_time ) );
			$log_data[ \WC_Facebookcommerce_Integration::OPTION_PIXEL_INSTALL_TIME ] = sanitize_text_field( $values->install_time );
		}

		if ( ! empty( $values->business_id ) ) {
			$this->update_external_business_id( sanitize_text_field( $values->business_id ) );
			$log_data[ self::OPTION_EXTERNAL_BUSINESS_ID ] = sanitize_text_field( $values->business_id );
		}

		if ( ! empty( $values->pixel_id ) ) {
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, sanitize_text_field( $values->pixel_id ) );
			$log_data[ \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID ] = sanitize_text_field( $values->pixel_id );
		}

		if ( ! empty( $values->catalog_id ) ) {
			update_option( \WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID, sanitize_text_field( $values->catalog_id ) );
			$log_data[ \WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID ] = sanitize_text_field( $values->catalog_id );
		}

		if ( ! empty( $values->business_manager_id ) ) {
			$this->update_business_manager_id( sanitize_text_field( $values->business_manager_id ) );
			$log_data[ self::OPTION_BUSINESS_MANAGER_ID ] = sanitize_text_field( $values->business_manager_id );
		}

		if ( ! empty( $values->ad_account_id ) ) {
			$this->update_ad_account_id( sanitize_text_field( $values->ad_account_id ) );
			$log_data[ self::OPTION_AD_ACCOUNT_ID ] = sanitize_text_field( $values->ad_account_id );
		}

		if ( ! empty( $values->instagram_profiles ) ) {
			$instagram_business_id = current( $values->instagram_profiles );
			$this->update_instagram_business_id( sanitize_text_field( $instagram_business_id ) );
			$log_data[ self::OPTION_INSTAGRAM_BUSINESS_ID ] = sanitize_text_field( $instagram_business_id );
		}

		if ( ! empty( $values->commerce_merchant_settings_id ) ) {
			$this->update_commerce_merchant_settings_id( sanitize_text_field( $values->commerce_merchant_settings_id ) );
			$log_data[ self::OPTION_COMMERCE_MERCHANT_SETTINGS_ID ] = sanitize_text_field( $values->commerce_merchant_settings_id );
		}

		if ( ! empty( $values->pages ) ) {
			$page_id = current( $values->pages );
			try {
				update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, sanitize_text_field( $page_id ) );
				$log_data[ \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID ] = sanitize_text_field( $page_id );
				// get and store a current access token for the configured page
				$page_access_token = $this->retrieve_page_access_token( $page_id );
				$this->update_page_access_token( $page_access_token );
				$log_data[ self::OPTION_PAGE_ACCESS_TOKEN ] = sanitize_text_field( $page_access_token );
			} catch ( \Exception $e ) {
				$this->get_plugin()->log( 'Could not request Page Token: ' . $e->getMessage() );
			}
		}//end if

		$this->get_plugin()->log( 'WebHook User event saved data' );
		$this->get_plugin()->log( print_r( $log_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}


	/**
	 * Register Extras REST API endpoint
	 *
	 * @since 2.3.0
	 */
	public function init_extras_endpoint() {
		register_rest_route(
			'wc-facebook/v1',
			'extras',
			array(
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => array( $this, 'extras_callback' ),
					'permission_callback' => array( $this, 'extras_permission_callback' ),
				),
			)
		);
	}


	/**
	 * FBE Extras endpoint permissions
	 *
	 * @since 2.3.0
	 *
	 * @return boolean
	 */
	public function extras_permission_callback() {
		return current_user_can( 'manage_woocommerce' );
	}


	/**
	 * Return FBE extras
	 *
	 * @since 2.3.0
	 *
	 * @return \WP_REST_Response
	 */
	public function extras_callback() {
		$extras = $this->get_connect_parameters_extras();
		if ( empty( $extras ) ) {
			return new \WP_REST_Response( null, 204 );
		}
		return new \WP_REST_Response( $extras, 200 );
	}


	/**
	 * Process FBE App Store login flow redirection
	 *
	 * @since 2.3.0
	 */
	public function handle_fbe_redirect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to finish App Store login.', 'facebook-for-woocommerce' ) );
		}
		$redirect_uri = isset( $_REQUEST['redirect_uri'] ) ? base64_decode( wc_clean( wp_unslash( $_REQUEST['redirect_uri'] ) ) ) : ''; //phpcs:ignore
		// To ensure that we are not sharing any user data with other parties, only redirect to the redirect_uri if it matches the regular expression
		if ( empty( $redirect_uri ) || ! preg_match( '/https?:\/\/(www\.|m\.|l\.)?(\d{5}\.od\.)?(facebook|instagram|whatsapp)\.com(\/.*)?/', explode( '?', $redirect_uri )[0] ) ) {
			wp_safe_redirect( site_url() );
			exit;
		}
		if ( empty( $_REQUEST['success'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url_params = array(
				'store_url'    => '',
				'redirect_uri' => rawurlencode( $redirect_uri ),
				'errors'       => array( 'You need to grant access to WooCommerce.' ),
			);
			$redirect_url = add_query_arg(
				$url_params,
				$this->get_app_store_login_url()
			);
		} else {
			$redirect_url = $redirect_uri . '&extras=' . rawurlencode_deep( wp_json_encode( $this->get_connect_parameters_extras() ) );
		}
		wp_redirect( $redirect_url ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
