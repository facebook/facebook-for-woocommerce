<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\ProductCatalog\LocalizedItemsBatch\Create;

use WooCommerce\Facebook\API\Request as BaseRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog localized items batch API.
 *
 * @since 3.6.0
 */
class Request extends BaseRequest {

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID
	 * @param array  $requests array of localized batch requests
	 */
	public function __construct( $product_catalog_id, $requests ) {
		parent::__construct( "/{$product_catalog_id}/localized_items_batch", 'POST' );

		// Set parameters as form data (not JSON data)
		$this->set_params( [
			'allow_upsert' => true,
			'requests'     => json_encode( $requests ),
			'item_type'    => 'PRODUCT_ITEM',
		] );
	}

	/**
	 * Override to return form data instead of JSON.
	 *
	 * @since 3.6.0
	 * @return string
	 */
	public function to_string() {
		return http_build_query( $this->get_params() );
	}

	/**
	 * Override to provide form-specific headers.
	 *
	 * @since 3.6.0
	 * @return array
	 */
	public function get_request_specific_headers(): array {
		return [
			'content-type' => 'application/x-www-form-urlencoded',
		];
	}
}
