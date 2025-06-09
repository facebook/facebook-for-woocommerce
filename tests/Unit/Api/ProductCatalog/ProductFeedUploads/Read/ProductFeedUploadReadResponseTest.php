<?php
declare( strict_types=1 );

use WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Read\Response;

/**
 * Response unit test class for reading product feed uploads.
 */
class ProductFeedUploadReadResponseTest extends WP_UnitTestCase {

    /**
     * @return void
     */
    public function test_response() {
        $json = '{
            "id": "product_feed_upload_id",
            "data": {
                "error_count": 0,
                "warning_count": 2,
                "num_detected_items": 100,
                "num_persisted_items": 98,
                "url": "http://example.com/feed.xml",
                "end_time": "2023-10-01T12:00:00+0000"
            }
        }';

        $response = new Response($json);

        $this->assertEquals('product_feed_upload_id', $response->id);
        $this->assertEquals(0, $response->data['error_count']);
        $this->assertEquals(2, $response->data['warning_count']);
        $this->assertEquals(100, $response->data['num_detected_items']);
        $this->assertEquals(98, $response->data['num_persisted_items']);
        $this->assertEquals('http://example.com/feed.xml', $response->data['url']);
        $this->assertEquals('2023-10-01T12:00:00+0000', $response->data['end_time']);
    }
}
