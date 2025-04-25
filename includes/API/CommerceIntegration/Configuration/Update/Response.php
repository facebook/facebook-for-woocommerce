<?php

namespace WooCommerce\Facebook\API\CommerceIntegration\Configuration\Update;

use WooCommerce\Facebook\API\Response as ApiResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Commerce Integration Configuration Update API.
 *
 * @property-read bool $success Whether the update request was successful
 * @property-read string $commerce_partner_integration_id The ID of the commerce partner integration
 */
class Response extends ApiResponse {
	/**
	 * Returns whether the update request was successful.
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
