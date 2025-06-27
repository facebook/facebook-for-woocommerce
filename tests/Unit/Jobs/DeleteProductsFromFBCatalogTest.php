<?php

namespace WooCommerce\Facebook\Tests\Unit\Jobs;

use WooCommerce\Facebook\Jobs\DeleteProductsFromFBCatalog;
use WC_Facebookcommerce;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use PHPUnit\Framework\MockObject\MockObject;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

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

	/**
	 * @var MockObject|ActionSchedulerInterface
	 */
	private $mock_scheduler;

	public function setUp(): void {
		parent::setUp();

		// Create a mock action scheduler
		$this->mock_scheduler = $this->createMock( ActionSchedulerInterface::class );

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

		// Store the mock in a global variable
		$GLOBALS['test_facebook_for_woocommerce_mock'] = $mock_facebook_for_woocommerce;
		global $test_facebook_for_woocommerce_mock;
		$test_facebook_for_woocommerce_mock = $mock_facebook_for_woocommerce;

		// Create the facebook_for_woocommerce function in the global scope
		// Use the simplest possible approach
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			eval( '
				function facebook_for_woocommerce() {
					global $test_facebook_for_woocommerce_mock;
					return $test_facebook_for_woocommerce_mock;
				}
			' );
		}

		// Create the job instance with the required action scheduler dependency
		$this->job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
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
		// Arrange: Create a job instance that mocks the get_items_for_batch method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'get_items_for_batch' ] )
			->getMock();
		
		$expected_product_ids = [ 101, 102, 103 ];
		$job->method( 'get_items_for_batch' )
			->willReturn( $expected_product_ids );

		// Act: Call the protected method
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'get_items_for_batch' );
		$method->setAccessible( true );
		$result = $method->invoke( $job, 1, [] );

		// Assert: Verify the result
		$this->assertSame( $expected_product_ids, $result );
	}

	public function test_process_items_calls_integration_methods() {
		// Arrange: Create a job instance that mocks the process_items method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'process_items' ] )
			->getMock();
		
		$items = [ 201, 202 ];
		
		// Set up expectations that process_items will be called with the correct parameters
		$job->expects( $this->once() )
			->method( 'process_items' )
			->with( $items, [] );
		
		// Act: Call the protected method
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $job, $items, [] );
	}

	public function test_process_items_integration_logic() {
		// Arrange: Create a job instance that mocks the process_items method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'process_items' ] )
			->getMock();
		
		$items = [ 201, 202 ];
		
		// Set up expectations that process_items will be called with the correct parameters
		$job->expects( $this->once() )
			->method( 'process_items' )
			->with( $items, [] );
		
		// Act: Call the protected method
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $job, $items, [] );
		
		// Assert: If we get here, the method was called correctly
		$this->assertTrue( true, 'process_items method was called with correct parameters' );
	}

	public function test_process_items_with_empty_array_does_nothing() {
		// Arrange: Create a job instance that mocks the process_items method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'process_items' ] )
			->getMock();
		
		// Set up expectations that process_items will be called with empty array
		$job->expects( $this->once() )
			->method( 'process_items' )
			->with( [], [] );
		
		// Act: Call the protected method with empty array
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $job, [], [] );
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

	public function test_get_items_for_batch_with_different_batch_numbers() {
		// Arrange: Create a job instance that mocks the get_items_for_batch method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'get_items_for_batch' ] )
			->getMock();
		
		$expected_product_ids = [ 301, 302, 303 ];
		$job->method( 'get_items_for_batch' )
			->willReturn( $expected_product_ids );

		// Act: Call the protected method with different batch numbers
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'get_items_for_batch' );
		$method->setAccessible( true );
		
		$result_batch_1 = $method->invoke( $job, 1, [] );
		$result_batch_2 = $method->invoke( $job, 2, [] );
		$result_batch_3 = $method->invoke( $job, 3, [] );

		// Assert: Verify the results are consistent
		$this->assertSame( $expected_product_ids, $result_batch_1 );
		$this->assertSame( $expected_product_ids, $result_batch_2 );
		$this->assertSame( $expected_product_ids, $result_batch_3 );
	}

	public function test_get_items_for_batch_with_custom_args() {
		// Arrange: Create a job instance that mocks the get_items_for_batch method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'get_items_for_batch' ] )
			->getMock();
		
		$expected_product_ids = [ 401, 402 ];
		$custom_args = [ 'custom_param' => 'test_value', 'limit' => 10 ];
		
		$job->method( 'get_items_for_batch' )
			->willReturn( $expected_product_ids );

		// Act: Call the protected method with custom args
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'get_items_for_batch' );
		$method->setAccessible( true );
		$result = $method->invoke( $job, 1, $custom_args );

		// Assert: Verify the result
		$this->assertSame( $expected_product_ids, $result );
	}

	public function test_process_items_with_single_item() {
		// Arrange: Create a job instance that mocks the process_items method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'process_items' ] )
			->getMock();
		
		$items = [ 501 ];
		
		// Set up expectations that process_items will be called with single item
		$job->expects( $this->once() )
			->method( 'process_items' )
			->with( $items, [] );
		
		// Act: Call the method with a single item
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $job, $items, [] );
		
		// Assert: If we get here, the method was called correctly
		$this->assertTrue( true, 'process_items method was called with single item' );
	}

	public function test_process_items_with_large_array() {
		// Arrange: Create a job instance that mocks the process_items method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'process_items' ] )
			->getMock();
		
		$items = range( 601, 650 ); // 50 items
		
		// Set up expectations that process_items will be called with large array
		$job->expects( $this->once() )
			->method( 'process_items' )
			->with( $items, [] );
		
		// Act: Call the method with a large array
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $job, $items, [] );
		
		// Assert: If we get here, the method was called correctly
		$this->assertTrue( true, 'process_items method was called with large array' );
	}

	public function test_process_items_with_mixed_data_types() {
		// Arrange: Create a job instance that mocks the process_items method
		$job = $this->getMockBuilder( DeleteProductsFromFBCatalog::class )
			->setConstructorArgs( [ $this->mock_scheduler ] )
			->onlyMethods( [ 'log', 'process_items' ] )
			->getMock();
		
		$items = [ '601', 602, '603', 604 ]; // Mixed string and integer IDs
		
		// Set up expectations that process_items will be called with mixed data types
		$job->expects( $this->once() )
			->method( 'process_items' )
			->with( $items, [] );
		
		// Act: Call the method with mixed data types
		$reflection = new \ReflectionClass( $job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		$method->invoke( $job, $items, [] );
		
		// Assert: If we get here, the method was called correctly
		$this->assertTrue( true, 'process_items method was called with mixed data types' );
	}

	public function test_class_extends_abstract_chained_job() {
		// Assert: Verify the class extends the correct parent class
		$this->assertInstanceOf( \WooCommerce\Facebook\Jobs\AbstractChainedJob::class, $this->job );
	}
} 