<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\DeleteProductsFromFBCatalog;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \WooCommerce\Facebook\Jobs\DeleteProductsFromFBCatalog
 */
class DeleteProductsFromFBCatalogTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var DeleteProductsFromFBCatalog|MockObject
	 */
	private $job;

	/**
	 * @var MockObject
	 */
	private $integration_mock;

	public function setUp(): void {
		parent::setUp();

		// Create a mock integration
		$this->integration_mock = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'delete_product_item', 'reset_single_product' ] )
			->getMock();

		// Create a simple mock object that returns the integration
		$mock_facebook_for_woocommerce = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_integration' ] )
			->getMock();
		$mock_facebook_for_woocommerce->method( 'get_integration' )
			->willReturn( $this->integration_mock );

		// Mock the facebook_for_woocommerce function
		\WP_Mock::userFunction( 'facebook_for_woocommerce', [
			'times' => '0+',
			'return' => $mock_facebook_for_woocommerce,
		] );

		// Create the job instance
		$this->job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->onlyMethods( [ 'log' ] )
			->getMock();
	}

	public function test_get_name() {
		$this->assertSame( 'delete_products_from_FB_catalog', $this->job->get_name() );
	}

	public function test_get_plugin_name() {
		$this->assertSame( WC_Facebookcommerce::PLUGIN_ID, $this->job->get_plugin_name() );
	}

	public function test_get_batch_size() {
		$this->assertSame( 25, $this->job->get_batch_size() );
	}

	public function test_handle_start_logs_message() {
		$this->job->expects( $this->once() )
			->method( 'log' )
			->with( $this->stringContains( 'Starting job' ) );

		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'handle_start' );
		$method->setAccessible( true );
		$method->invoke( $this->job );
	}

	public function test_handle_end_logs_message() {
		$this->job->expects( $this->once() )
			->method( 'log' )
			->with( $this->stringContains( 'Finished job' ) );

		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'handle_end' );
		$method->setAccessible( true );
		$method->invoke( $this->job );
	}

	public function test_get_items_for_batch_returns_product_ids() {
		// Arrange: Mock get_posts to return product IDs
		$product_ids = [ 101, 102, 103 ];
		\WP_Mock::userFunction( 'get_posts', [
			'times' => 1,
			'args' => $this->anything(),
			'return' => $product_ids,
		] );

		// Act: Call the protected method
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'get_items_for_batch' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->job, 1, [] );

		// Assert: Verify the result
		$this->assertSame( $product_ids, $result );
	}

	public function test_process_items_calls_integration_methods() {
		// Arrange: Set up test data and expectations
		$items = [ 201, 202 ];
		$this->integration_mock->expects( $this->exactly( 2 ) )
			->method( 'delete_product_item' )
			->withConsecutive( [ $items[0] ], [ $items[1] ] );
		$this->integration_mock->expects( $this->exactly( 2 ) )
			->method( 'reset_single_product' )
			->withConsecutive( [ $items[0] ], [ $items[1] ] );

		// Act: Call the protected method
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $this->job, $items, [] );
	}

	public function test_process_items_with_empty_array_does_nothing() {
		// Arrange: Set up expectations for no method calls
		$this->integration_mock->expects( $this->never() )
			->method( 'delete_product_item' );
		$this->integration_mock->expects( $this->never() )
			->method( 'reset_single_product' );

		// Act: Call the protected method with empty array
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $this->job, [], [] );
	}

	public function test_process_item_is_no_op() {
		// Act: Call the protected method
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_item' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->job, 123, [] );

		// Assert: Verify the method returns null (no-op)
		$this->assertNull( $result );
	}
} 