<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\FBE\Installation\Read;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE Installation API read response object.
 */
class Response extends API\Response {
	/**
	 * Gets the pixel ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_pixel_id() {
		return $this->get_data()['pixel_id'] ?? '';
	}


	/**
	 * Gets the business manager ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_business_manager_id() {
		return $this->get_data()['business_manager_id'] ?? '';
	}


	/**
	 * Gets the ad account ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_ad_account_id() {
		return $this->get_data()['ad_account_id'] ?? '';
	}


	/**
	 * Returns Facebook Catalog id.
	 *
	 * @return string
	 */
	public function get_catalog_id(): string {
		return $this->get_data()['catalog_id'] ?? '';
	}


	/**
	 * Returns facebook page id.
	 *
	 * @return string
	 */
	public function get_page_id(): string {
		$pages = $this->get_data()['pages'] ?? '';
		return is_array( $pages ) ? current( $pages ) : '';
	}


	/**
	 * Gets Instagram Business ID.
	 *
	 * @since 2.1.5
	 *
	 * @return string
	 */
	public function get_instagram_business_id() {
		$instagram_profiles = $this->get_data()['instagram_profiles'] ?? '';
		if ( empty( $instagram_profiles ) ) {
			return '';
		}
		return is_array( $instagram_profiles ) ? current( $instagram_profiles ) : $instagram_profiles;
	}


	/**
	 * Gets the commerce merchant settings ID.
	 *
	 * @since 2.1.5
	 *
	 * @return string
	 */
	public function get_commerce_merchant_settings_id() {
		return $this->get_data()['commerce_merchant_settings_id'] ?? '';
	}

	/**
	 * Gets the commerce partner integration ID.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_commerce_partner_integration_id() {
		return $this->get_data()['commerce_partner_integration_id'] ?? '';
	}


	/**
	 * Gets the profiles.
	 *
	 * @since 2.0.0
	 *
	 * @return string[]
	 */
	public function get_profiles() {
		return $this->get_data()['profiles'] ?? [];
	}


	/**
	 * Gets the response data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->response_data['data'][0] ?? [];
	}
}
