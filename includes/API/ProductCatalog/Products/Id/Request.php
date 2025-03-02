<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\Products\Id;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Products > Get Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/products/
 */
class Request extends ApiRequest {

	/**
	 * @param string                   $facebook_product_catalog_id Facebook Product Catalog ID.
	 * @param string                   $facebook_product_retailer_id Facebook Product Retailer ID.
	 * @param WC_Facebook_Product|null $woo_product product
	 * @param bool                     $is_call_before_sync Flag indicating if call is made before syncing products to Facebook.
	 */
	public function __construct( string $facebook_product_catalog_id, string $facebook_product_retailer_id, $woo_product, bool $is_call_before_sync ) {
		$is_new_product = ($woo_product->woo_product->get_date_created() == $woo_product->woo_product->get_date_modified());
		$should_use_filter_endpoint = $is_new_product && $is_call_before_sync;

		/**
		 * We use the endpoint with filter to get the product id and group id for new products to check if the product is already synced to Facebook.
		 */
		if ( $should_use_filter_endpoint ) {
			$path = "/{$facebook_product_catalog_id}/products";
			parent::__construct( $path, 'GET' );

			$this->set_params(
				array(
					'filter' => '{"retailer_id":{"eq":"' . $facebook_product_retailer_id . '"}}',
					'fields' => 'id,product_group{id}',
				)
			);
		} else {
			$path = "catalog:{$facebook_product_catalog_id}:" . base64_encode( $facebook_product_retailer_id ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			parent::__construct( "/{$path}/?fields=id,product_group{id}", 'GET' );
		}
	}
}
