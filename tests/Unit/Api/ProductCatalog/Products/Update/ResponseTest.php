<?php
declare( strict_types=1 );

namespace Unit\Api\ProductCatalog\Products\Update;

use WP_UnitTestCase;
use WooCommerce;

/**
 * Api unit test clas.
 */
class ResponseTest extends WP_UnitTestCase {
	/**
	 * @return void
	 */
	public function test_request() {
		$json     = '{"success":true}';
		$response = new WooCommerce\Facebook\API\ProductCatalog\Products\Update\Response( $json );

		$this->assertTrue( $response->success );
	}
}
