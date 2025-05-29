<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\FBE\Configuration\Read;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE Configuration API read response object.
 */
class Response extends API\Response {

	/**
	 * Is Instagram Shopping enabled?
	 *
	 * @return boolean
	 */
	public function is_ig_shopping_enabled(): bool {

		if ( empty( $this->response_data['ig_shopping'] ) ) {
			return false;
		}
		return (bool) $this->response_data['ig_shopping']['enabled'] ?? false;
	}

	/**
	 * Is Instagram CTA enabled?
	 *
	 * @return boolean
	 */
	public function is_ig_cta_enabled(): bool {

		if ( empty( $this->response_data['ig_cta'] ) ) {
			return false;
		}
		return (bool) $this->response_data['ig_cta']['enabled'] ?? false;
	}

	/**
	 * Gets the commerce extension URI.
	 *
	 * @return string Commerce extension URI or empty string if not available.
	 */
	public function get_commerce_extension_uri(): string {

		if ( empty( $this->response_data['commerce_extension'] ) ) {
			return '';
		}
		return $this->response_data['commerce_extension']['uri'] ?? '';
	}
}
