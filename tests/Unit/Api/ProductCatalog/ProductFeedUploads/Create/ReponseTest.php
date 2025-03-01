<?php
declare( strict_types=1 );

use WooCommerce\Facebook\API\ProductCatalog\ProductFeedUploads\Create\Response;

/**
 * Response unit test class.
 */
class ProductFeedUploadResponseTest extends WP_UnitTestCase {

    /**
     * @return void
     */
    public function test_response() {
        $json = '{
            "id": "product_feed_upload_id",
            "data": {
                "upload_status": "success"
            }
        }';

        $response = new Response($json);

        $this->assertEquals('product_feed_upload_id', $response->id);
        $this->assertEquals('success', $response->data['upload_status']);
    }
}
