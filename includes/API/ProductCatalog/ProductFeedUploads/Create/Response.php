<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Create;

use WooCommerce\Facebook\API\Response as ApiResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Product Catalog > Product Feed Upload > Create Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-feed/uploads/#Creating
 * @property-read array $data Facebook Product Feeds Upload.
 */
class Response extends ApiResponse {}
