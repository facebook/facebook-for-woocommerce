<?php

declare( strict_types=1 );

namespace Api\ProductCatalog\Products\Delete;

use WooCommerce;
use WP_UnitTestCase;

/**
 * Test cases for product delete API response
 */
class ResponseTest extends WP_UnitTestCase {
	/**
	 * Tests response value
	 *
	 * @return void
	 */
	public function test_response() {
		$json     = '{"success":true}';
		$response = new WooCommerce\Facebook\API\ProductCatalog\Products\Delete\Response( $json );

		$this->assertTrue( $response->success );
	}
}
