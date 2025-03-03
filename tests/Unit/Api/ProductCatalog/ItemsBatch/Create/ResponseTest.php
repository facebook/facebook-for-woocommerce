<?php
declare( strict_types=1 );

namespace Api\ProductCatalog\ItemsBatch\Create;

use WooCommerce;
use WP_UnitTestCase;

/**
 * Test cases for Items Batch create API response
 */
class ResponseTest extends WP_UnitTestCase {
	/**
	 * Tests response endpoint config
	 *
	 * @return void
	 */
	public function test_response(): void {
		$json = '{"handles": ["items_1", "item_2", "item_3"],"validation_status": ["success"]}';

		$response = new WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create\Response ( $json );

		$this->assertEquals( [ "items_1", "item_2", "item_3" ], $response->handles );
		$this->assertEquals( [ "success" ], $response->validation_status );
	}
}
