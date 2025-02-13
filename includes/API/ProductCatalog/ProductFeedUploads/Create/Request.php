<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Create;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Product Feed Upload > Create Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-feed/uploads/#Creating
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_feed_id Facebook Product Feed ID.
	 * @param array  $data Facebook Product Feed Data.
	 */
	public function __construct( string $product_feed_id, array $data ) {
		parent::__construct( "/{$product_feed_id}/uploads", 'POST' );
		parent::set_data( $data );
	}
}
