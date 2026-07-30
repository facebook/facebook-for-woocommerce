<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Read;

use WooCommerce\Facebook\API\Response as ApiResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Product Catalog > Product Feeds > Read Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-feed-upload/#read_examples
 * @property-read array $data Facebook Product Feeds.
 */
class Response extends ApiResponse {}
