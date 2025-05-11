<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Repair;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Commerce Integration Repair API request object with payload.
 *
 * This class handles the creation of repair requests for the Commerce Integration API.
 * It constructs the necessary payload for repairing integration object between WooCommerce
 * and Facebook's Commerce platform.
 */
class RepairRequest extends API\Request {
	/**
	 * API request constructor.
	 *
	 * Initializes a new repair request with the required parameters for the Commerce Integration API.
	 * The request is used to repair or re-establish the connection between WooCommerce and Facebook's Commerce platform.
	 *
	 * @param string $fbe_external_business_id The external business ID associated with the Facebook Business Extension
	 * @param string $shop_domain The domain of the WooCommerce site
	 * @param string $admin_url The admin URL of the WooCommerce site
	 * @param string $extension_version The version of the Facebook for WooCommerce extension
	 * @param string $platform_type The type of the integration method
	 */
	public function __construct( $fbe_external_business_id, $shop_domain, $admin_url, $extension_version, $platform_type = 'SELF_SERVE_PLATFORM' ) {
		parent::__construct( '/commerce_partner_integrations_repair', 'POST' );

		$data = array(
			'fbe_external_business_id'              => $fbe_external_business_id,
			'shop_domain'                           => $shop_domain,
			'admin_url'                             => $admin_url,
			'extension_version'                     => $extension_version,
			'commerce_partner_seller_platform_type' => $platform_type,
		);

		$this->set_data( $data );
	}
}
