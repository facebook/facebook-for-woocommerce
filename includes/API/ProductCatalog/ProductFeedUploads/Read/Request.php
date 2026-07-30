<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Read;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Product Feeds > Read Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-feed-upload/#read_examples
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_feed_upload_id Facebook Product Feed Upload ID.
	 */
	public function __construct( string $product_feed_upload_id ) {
		parent::__construct( "/{$product_feed_upload_id}/?fields=error_count,warning_count,num_detected_items,num_persisted_items,url,end_time", 'GET' );
	}
}
