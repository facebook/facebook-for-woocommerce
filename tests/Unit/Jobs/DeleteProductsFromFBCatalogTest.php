<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\DeleteProductsFromFBCatalog;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * @covers \WooCommerce\Facebook\Jobs\DeleteProductsFromFBCatalogTest
 */
class DeleteProductsFromFBCatalogTest extends AbstractWPUnitTestWithSafeFiltering
{
    private $job;
    private $integrationMock;
    private $originalFacebookForWooCommerce;

    public function setUp(): void
    {
        parent::setUp();
        // Create a mock integration
        $this->integrationMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['delete_product_item', 'reset_single_product'])
            ->getMock();

        // Mock global function facebook_for_woocommerce
        $this->originalFacebookForWooCommerce = function_exists('facebook_for_woocommerce') ? \Closure::fromCallable('facebook_for_woocommerce') : null;
        if (!function_exists('WooCommerce\\Facebook\\Tests\\Unit\\Jobs\\facebook_for_woocommerce')) {
            eval('namespace WooCommerce\\Facebook\\Tests\\Unit\\Jobs; function facebook_for_woocommerce() { return DeleteProductsFromFBCatalogTest::$integrationInstance; }');
        }
        self::$integrationInstance = $this;
        $GLOBALS['facebook_for_woocommerce'] = function() { return $this->integrationMock; };

        // Create the job instance
        $this->job = $this->getMockBuilder(DeleteProductsFromFBCatalog::class)
            ->onlyMethods(['log'])
            ->getMock();
    }

    public function tearDown(): void
    {
        unset($GLOBALS['facebook_for_woocommerce']);
        parent::tearDown();
    }

    public function testGetName()
    {
        $this->assertSame('delete_products_from_FB_catalog', $this->job->get_name());
    }

    public function testGetPluginName()
    {
        $this->assertSame(WC_Facebookcommerce::PLUGIN_ID, $this->job->get_plugin_name());
    }

    public function testGetBatchSize()
    {
        $this->assertSame(25, $this->job->get_batch_size());
    }

    public function testHandleStartLogsMessage()
    {
        $this->job->expects($this->once())
            ->method('log')
            ->with($this->stringContains('Starting job'));
        $reflection = new \ReflectionClass($this->job);
        $method = $reflection->getMethod('handle_start');
        $method->setAccessible(true);
        $method->invoke($this->job);
    }

    public function testHandleEndLogsMessage()
    {
        $this->job->expects($this->once())
            ->method('log')
            ->with($this->stringContains('Finished job'));
        $reflection = new \ReflectionClass($this->job);
        $method = $reflection->getMethod('handle_end');
        $method->setAccessible(true);
        $method->invoke($this->job);
    }

    public function testGetItemsForBatchReturnsProductIds()
    {
        // Mock get_posts to return product IDs
        $product_ids = [101, 102, 103];
        \WP_Mock::userFunction('get_posts', [
            'times' => 1,
            'args' => $this->anything(),
            'return' => $product_ids,
        ]);
        $reflection = new \ReflectionClass($this->job);
        $method = $reflection->getMethod('get_items_for_batch');
        $method->setAccessible(true);
        $result = $method->invoke($this->job, 1, []);
        $this->assertSame($product_ids, $result);
    }

    public function testProcessItemsCallsIntegrationMethods()
    {
        $items = [201, 202];
        $this->integrationMock->expects($this->exactly(2))
            ->method('delete_product_item')
            ->withConsecutive([$items[0]], [$items[1]]);
        $this->integrationMock->expects($this->exactly(2))
            ->method('reset_single_product')
            ->withConsecutive([$items[0]], [$items[1]]);
        $reflection = new \ReflectionClass($this->job);
        $method = $reflection->getMethod('process_items');
        $method->setAccessible(true);
        $method->invoke($this->job, $items, []);
    }

    public function testProcessItemsWithEmptyArrayDoesNothing()
    {
        $this->integrationMock->expects($this->never())
            ->method('delete_product_item');
        $this->integrationMock->expects($this->never())
            ->method('reset_single_product');
        $reflection = new \ReflectionClass($this->job);
        $method = $reflection->getMethod('process_items');
        $method->setAccessible(true);
        $method->invoke($this->job, [], []);
    }

    public function testProcessItemIsNoOp()
    {
        $reflection = new \ReflectionClass($this->job);
        $method = $reflection->getMethod('process_item');
        $method->setAccessible(true);
        $this->assertNull($method->invoke($this->job, 123, []));
    }
} 