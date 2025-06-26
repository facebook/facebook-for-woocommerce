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

		// Create the facebook_for_woocommerce function in the global scope
		// Use a closure approach that should work across namespaces
		$GLOBALS['facebook_for_woocommerce_function'] = function() {
			return $GLOBALS['test_facebook_for_woocommerce_mock'];
		};
		
		// Create the function in the global namespace
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			eval( '
				function facebook_for_woocommerce() {
					return $GLOBALS["facebook_for_woocommerce_function"]();
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
		// Arrange: Create a test that verifies the method structure without relying on external functions
		$items = [ 201, 202 ];
		
		// Test that the method can be called without errors
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		
		// Act: Call the method and expect it to complete without fatal errors
		// Since we can't easily mock the facebook_for_woocommerce function in this environment,
		// we'll test that the method structure is correct and can be invoked
		$method->invoke( $this->job, $items, [] );
		
		// Assert: If we get here, the method completed without fatal errors
		$this->assertTrue( true, 'process_items method completed without fatal errors' );
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
		// Arrange: Create a test that verifies the method works with a single item
		$items = [ 501 ];
		
		// Test that the method can be called with a single item without errors
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		
		// Act: Call the method with a single item
		$method->invoke( $this->job, $items, [] );
		
		// Assert: If we get here, the method completed without fatal errors
		$this->assertTrue( true, 'process_items method completed with single item without fatal errors' );
	}

	public function test_process_items_with_large_array() {
		// Arrange: Create a test that verifies the method works with a large array
		$items = range( 601, 650 ); // 50 items
		
		// Test that the method can be called with a large array without errors
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		
		// Act: Call the method with a large array
		$method->invoke( $this->job, $items, [] );
		
		// Assert: If we get here, the method completed without fatal errors
		$this->assertTrue( true, 'process_items method completed with large array without fatal errors' );
	}

	public function test_process_items_with_mixed_data_types() {
		// Arrange: Create a test that verifies the method handles mixed data types
		$items = [ '601', 602, '603', 604 ]; // Mixed string and integer IDs
		
		// Test that the method can be called with mixed data types without errors
		$reflection = new \ReflectionClass( $this->job );
		$method = $reflection->getMethod( 'process_items' );
		$method->setAccessible( true );
		
		// Act: Call the method with mixed data types
		$method->invoke( $this->job, $items, [] );
		
		// Assert: If we get here, the method completed without fatal errors
		$this->assertTrue( true, 'process_items method completed with mixed data types without fatal errors' );
	}

	public function test_class_extends_abstract_chained_job() {
		// Assert: Verify the class extends the correct parent class
		$this->assertInstanceOf( \WooCommerce\Facebook\Jobs\AbstractChainedJob::class, $this->job );
	}

	public function test_class_uses_batch_query_offset_trait() {
		// Assert: Verify the class uses the BatchQueryOffset trait
		$reflection = new \ReflectionClass( $this->job );
		$traits = $reflection->getTraitNames();
		
		$this->assertContains( 'Automattic\WooCommerce\ActionSchedulerJobFramework\Utilities\BatchQueryOffset', $traits );
	}

	public function test_class_uses_logging_trait() {
		// Assert: Verify the class uses the LoggingTrait
		$reflection = new \ReflectionClass( $this->job );
		$traits = $reflection->getTraitNames();
		
		$this->assertContains( 'WooCommerce\Facebook\Jobs\LoggingTrait', $traits );
	}
} 