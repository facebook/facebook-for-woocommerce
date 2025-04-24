<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Repair;

use WooCommerce\Facebook\API\Response as ApiResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Commerce Integration Repair API.
 *
 * @property-read string commerce_partner_integration_id The ID of the commerce partner integration
 */
class Response extends ApiResponse {
	/**
	 * Returns the commerce partner integration ID.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_commerce_partner_integration_id(): string {
		return $this->get_id() ?? '';
	}
} 