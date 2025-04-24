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
	 * Returns whether the repair request was successful.
	 *
	 * @return bool
	 * @since 3.4.8
	 */
	public function is_successful(): bool {
		return (bool) $this->success;
	}

	/**
	 * Returns the commerce partner integration ID.
	 *
	 * @return string
	 * @since 3.4.8
	 */
	public function get_commerce_partner_integration_id(): string {
		return $this->get_id() ?? '';
	}
}
