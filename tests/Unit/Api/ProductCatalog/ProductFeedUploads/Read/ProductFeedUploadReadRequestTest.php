<?php
declare( strict_types=1 );

use WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Read\Request;

/**
 * Request unit test class for reading product feed uploads.
 */
class ProductFeedUploadReadRequestTest extends WP_UnitTestCase {

    /**
     * @return void
     */
    public function test_request() {
        $product_feed_upload_id = 'product_feed_upload_id';
        $request = new Request($product_feed_upload_id);

        $expected_path = '/product_feed_upload_id/?fields=error_count,warning_count,num_detected_items,num_persisted_items,url,end_time';

        $this->assertEquals('GET', $request->get_method());
        $this->assertEquals($expected_path, $request->get_path());
    }
}
