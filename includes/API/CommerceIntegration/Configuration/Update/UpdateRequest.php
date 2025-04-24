<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Configuration\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Commerce Integration Configuration Update API request object with payload.
 *
 * This class handles the creation of configuration update requests for the Commerce Integration API.
 * It constructs the necessary payload for updating integration settings between WooCommerce
 * and Facebook's Commerce platform.
 */
class UpdateRequest extends API\Request {
	/**
	 * API request constructor.
	 *
	 * Initializes a new configuration update request with the required parameters for the Commerce Integration API.
	 * The request is used to update the configuration settings between WooCommerce and Facebook's Commerce platform.
	 *
	 * @param string      $commerce_integration_id The ID of the commerce integration to update
	 * @param string|null $extension_version The version of the Facebook for WooCommerce extension
	 * @param string|null $admin_url The admin URL of the WooCommerce site
	 * @param string|null $country_code ISO2 country code
	 * @param string|null $currency ISO currency code
	 * @param string|null $platform_store_id The ID of the current website on a multisite setup
	 * @param string      $commerce_partner_seller_platform_type The type of commerce partner platform
	 * @param string      $installation_status The installation status of the integration
	 */
	public function __construct(
		string $commerce_integration_id,
		?string $extension_version = null,
		?string $admin_url = null,
		?string $country_code = null,
		?string $currency = null,
		?string $platform_store_id = null,
		string $commerce_partner_seller_platform_type = 'SELF_SERVE',
		string $installation_status = 'ACCESS_TOKEN_DEPOSITED'
	) {
		parent::__construct( "/$commerce_integration_id", 'POST' );

		$data = array(
			'commerce_partner_seller_platform_type' => $commerce_partner_seller_platform_type,
			'installation_status'                   => $installation_status,
		);

		if ( $extension_version ) {
			$data['extension_version'] = $extension_version;
		}

		if ( $admin_url ) {
			$data['admin_url'] = $admin_url;
		}

		if ( $country_code ) {
			$data['country_code'] = $country_code;
		}

		if ( $currency ) {
			$data['currency'] = $currency;
		}

		if ( $platform_store_id ) {
			$data['platform_store_id'] = $platform_store_id;
		}

		$this->set_data( $data );
	}
}
