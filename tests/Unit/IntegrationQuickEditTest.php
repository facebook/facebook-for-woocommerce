<?php
declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit;

use WC_Facebookcommerce_Integration;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Simple tests for the WC_Facebookcommerce_Integration::on_product_quick_edit_save method.
 */
class IntegrationQuickEditTest extends \WP_UnitTestCase {

    /** @var WC_Product_Simple */
    private $product;

    public function setUp(): void {
        parent::setUp();

        $this->product = new WC_Product_Simple();
        $this->product->set_name('Test Product');
        $this->product->set_regular_price('10.00');
        $this->product->set_status('publish');
        $this->product->save();
    }

    public function tearDown(): void {
        if ($this->product) {
            wp_delete_post($this->product->get_id(), true);
        }
        parent::tearDown();
    }

    /**
     * Test that the method exists and doesn't throw exceptions
     */
    public function test_method_exists_and_runs() {
        $facebook_for_woocommerce = $this->createMock(\WC_Facebookcommerce::class);
        $integration = new WC_Facebookcommerce_Integration($facebook_for_woocommerce);

        $this->assertTrue(method_exists($integration, 'on_product_quick_edit_save'));

        // Should not throw exception
        $integration->on_product_quick_edit_save($this->product);
        $this->assertTrue(true);
    }

    /**
     * Test with null input
     */
    public function test_handles_null_input() {
        $facebook_for_woocommerce = $this->createMock(\WC_Facebookcommerce::class);
        $integration = new WC_Facebookcommerce_Integration($facebook_for_woocommerce);

        // Should not throw exception with null
        $integration->on_product_quick_edit_save(null);
        $this->assertTrue(true);
    }

    /**
     * Test with draft product
     */
    public function test_handles_draft_product() {
        $draft_product = new WC_Product_Simple();
        $draft_product->set_name('Draft Product');
        $draft_product->set_status('draft');
        $draft_product->save();

        $facebook_for_woocommerce = $this->createMock(\WC_Facebookcommerce::class);
        $integration = new WC_Facebookcommerce_Integration($facebook_for_woocommerce);

        // Should not throw exception
        $integration->on_product_quick_edit_save($draft_product);
        $this->assertTrue(true);

        wp_delete_post($draft_product->get_id(), true);
    }

    /**
     * Test error handling doesn't break execution
     */
    public function test_error_handling() {
        $facebook_for_woocommerce = $this->createMock(\WC_Facebookcommerce::class);
        $sync_handler = $this->createMock(\WooCommerce\Facebook\Products\Sync::class);

        // Mock sync handler to throw exception
        $sync_handler->method('create_or_update_products')
                    ->willThrowException(new \Exception('Test error'));

        $facebook_for_woocommerce->method('get_products_sync_handler')
                                 ->willReturn($sync_handler);

        $integration = new WC_Facebookcommerce_Integration($facebook_for_woocommerce);

        // Should catch exception and not break
        $integration->on_product_quick_edit_save($this->product);
        $this->assertTrue(true);
    }

    /**
     * Test method signature
     */
    public function test_method_signature() {
        $reflection = new \ReflectionMethod(WC_Facebookcommerce_Integration::class, 'on_product_quick_edit_save');

        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(1, $reflection->getNumberOfParameters());

        $parameters = $reflection->getParameters();
        $this->assertEquals('product', $parameters[0]->getName());
    }
}
