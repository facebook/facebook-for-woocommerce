<?php
declare( strict_types=1 );

/**
 * Api unit test clas.
 */
class ResponseTest extends WP_UnitTestCase {
	/**
	 * @return void
	 */
	public function test_request() {
		$json     = '{"id":"facebook-product-id"}';
		$response = new WooCommerce\Facebook\API\ProductCatalog\Products\Create\Response( $json );

		$this->assertEquals( 'facebook-product-id', $response->id );
	}
}
