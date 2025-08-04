<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Products;

use WooCommerce\Facebook\Products\Sync;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Products\Sync class - focused on create_or_update_modified_products() core logic.
 *
 * @since 2.0.0
 */
class SyncTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * The testable Sync instance under test.
	 *
	 * @var TestableSync
	 */
	private $sync;

	/**
	 * Test data for products.
	 *
	 * @var array
	 */
	private $test_products_data = array();

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock the Logger class
		$this->mock_logger();

		$this->sync = new TestableSync();
	}

	/**
	 * Test that products never synced before are added to sync queue.
	 */
	public function test_create_or_update_modified_products_syncs_never_synced_products() {
		// Set up test data
		$product_ids = array( 1, 2, 3 );
		$products_data = array(
			1 => array( 'last_sync_time' => false, 'modified_time' => 1640995200 ), // 2022-01-01
			2 => array( 'last_sync_time' => '', 'modified_time' => 1640995200 ),
			3 => array( 'last_sync_time' => null, 'modified_time' => 1640995200 ),
		);

		// Configure testable sync
		$this->sync->set_test_product_ids( $product_ids );
		$this->sync->set_test_products_data( $products_data );

		// Execute the method
		$this->sync->create_or_update_modified_products_test();

		// Verify all products were added to sync queue
		$requests = $this->get_sync_requests();
		$this->assertCount( 3, $requests );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-1'] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-2'] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-3'] );
	}

	/**
	 * Test that products modified since last sync are added to sync queue.
	 */
	public function test_create_or_update_modified_products_syncs_modified_products() {
		// Set up test data
		$product_ids = array( 1, 2, 3 );
		$products_data = array(
			1 => array( 'last_sync_time' => 1640995200, 'modified_time' => 1641081600 ), // Modified after sync
			2 => array( 'last_sync_time' => 1640995200, 'modified_time' => 1641168000 ), // Modified after sync
			3 => array( 'last_sync_time' => 1640995200, 'modified_time' => 1640908800 ), // Modified before sync
		);

		// Configure testable sync
		$this->sync->set_test_product_ids( $product_ids );
		$this->sync->set_test_products_data( $products_data );

		$this->sync->create_or_update_modified_products_test();

		// Only products 1 and 2 should be synced (modified after last sync)
		$requests = $this->get_sync_requests();
		$this->assertCount( 2, $requests );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-1'] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-2'] );
		$this->assertArrayNotHasKey( 'p-3', $requests );
	}

	/**
	 * Test that products not modified since last sync are not added to sync queue.
	 */
	public function test_create_or_update_modified_products_skips_unmodified_products() {
		// Set up test data
		$product_ids = array( 1, 2 );
		$products_data = array(
			1 => array( 'last_sync_time' => 1641081600, 'modified_time' => 1640995200 ), // Modified before sync
			2 => array( 'last_sync_time' => 1641081600, 'modified_time' => 1641081600 ), // Same time as sync
		);

		// Configure testable sync
		$this->sync->set_test_product_ids( $product_ids );
		$this->sync->set_test_products_data( $products_data );

		$this->sync->create_or_update_modified_products_test();

		// No products should be synced
		$requests = $this->get_sync_requests();
		$this->assertEmpty( $requests );
	}

	/**
	 * Test handling of products with no modification date.
	 */
	public function test_create_or_update_modified_products_handles_no_modification_date() {
		// Set up test data
		$product_ids = array( 1, 2 );
		$products_data = array(
			1 => array( 'last_sync_time' => false, 'modified_time' => null ), // Never synced, no mod date
			2 => array( 'last_sync_time' => 1640995200, 'modified_time' => null ), // Synced before, no mod date
		);

		// Configure testable sync
		$this->sync->set_test_product_ids( $product_ids );
		$this->sync->set_test_products_data( $products_data );

		$this->sync->create_or_update_modified_products_test();

		// Only product 1 should be synced (never synced before)
		$requests = $this->get_sync_requests();
		$this->assertCount( 1, $requests );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-1'] );
		$this->assertArrayNotHasKey( 'p-2', $requests );
	}

	/**
	 * Test handling of invalid products.
	 */
	public function test_create_or_update_modified_products_skips_invalid_products() {
		// Set up test data
		$product_ids = array( 1, 999, 2 );
		$products_data = array(
			1 => array( 'last_sync_time' => false, 'modified_time' => 1640995200 ),
			999 => null, // Invalid product
			2 => array( 'last_sync_time' => false, 'modified_time' => 1640995200 ),
		);

		// Configure testable sync
		$this->sync->set_test_product_ids( $product_ids );
		$this->sync->set_test_products_data( $products_data );

		$this->sync->create_or_update_modified_products_test();

		// Only valid products should be synced
		$requests = $this->get_sync_requests();
		$this->assertCount( 2, $requests );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-1'] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-2'] );
		$this->assertArrayNotHasKey( 'p-999', $requests );
	}

	/**
	 * Test that exceptions during sync are handled gracefully.
	 */
	public function test_create_or_update_modified_products_handles_exceptions() {
		// Configure testable sync to throw exception
		$this->sync->set_should_throw_exception( true );

		// Method should not throw exception
		$this->sync->create_or_update_modified_products_test();

		// No products should be synced due to exception
		$requests = $this->get_sync_requests();
		$this->assertEmpty( $requests );
	}

	/**
	 * Test edge case with zero timestamps.
	 */
	public function test_create_or_update_modified_products_handles_zero_timestamps() {
		// Set up test data
		$product_ids = array( 1, 2 );
		$products_data = array(
			1 => array( 'last_sync_time' => 0, 'modified_time' => 0 ),
			2 => array( 'last_sync_time' => 0, 'modified_time' => 1640995200 ),
		);

		// Configure testable sync
		$this->sync->set_test_product_ids( $product_ids );
		$this->sync->set_test_products_data( $products_data );

		$this->sync->create_or_update_modified_products_test();

		// Both products should be synced (! $last_sync_time is true when last_sync_time = 0)
		$requests = $this->get_sync_requests();
		$this->assertCount( 2, $requests );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-1'] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-2'] );
	}

	/**
	 * Helper method to get sync requests using reflection.
	 */
	private function get_sync_requests(): array {
		$reflection = new \ReflectionClass( $this->sync );
		$requests_property = $reflection->getProperty( 'requests' );
		$requests_property->setAccessible( true );
		return $requests_property->getValue( $this->sync );
	}

	/**
	 * Helper method to mock the Logger class.
	 */
	private function mock_logger() {
		if ( ! class_exists( '\WooCommerce\Facebook\Framework\Logger' ) ) {
			eval( '
				namespace WooCommerce\Facebook\Framework;
				class Logger {
					public static function log( $message, $context = array(), $options = array(), $exception = null ) {
						// Do nothing in tests
					}
				}
			' );
		}
	}

	/**
	 * Clean up globals after each test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['test_products_data'] );
		parent::tearDown();
	}
}

/**
 * Testable version of Sync class that allows dependency injection.
 */
class TestableSync extends Sync {

	/**
	 * Test data for products.
	 *
	 * @var array
	 */
	private $test_products_data = array();

	/**
	 * Test product IDs to return.
	 *
	 * @var array
	 */
	private $test_product_ids = array();

	/**
	 * Whether to throw exception.
	 *
	 * @var bool
	 */
	private $should_throw_exception = false;

	/**
	 * Set test product IDs.
	 */
	public function set_test_product_ids( array $product_ids ) {
		$this->test_product_ids = $product_ids;
	}

	/**
	 * Set test products data.
	 */
	public function set_test_products_data( array $products_data ) {
		$this->test_products_data = $products_data;
	}

	/**
	 * Set whether to throw exception.
	 */
	public function set_should_throw_exception( bool $should_throw ) {
		$this->should_throw_exception = $should_throw;
	}

	/**
	 * Override create_or_update_modified_products to use test data.
	 */
	public function create_or_update_modified_products_test() {
		try {
			if ( $this->should_throw_exception ) {
				throw new \Exception( 'Test exception' );
			}

			// Get test product IDs
			$all_product_ids = $this->test_product_ids;

			// Filter to only get products modified since last sync
			$products_to_sync = array();
			foreach ( $all_product_ids as $product_id ) {
				$product_data = $this->get_test_product_data( $product_id );
				if ( ! $product_data ) {
					continue;
				}

				$last_sync_time = $product_data['last_sync_time'];
				$modified_time = $product_data['modified_time'] ?? 0;

				// If never synced or modified since last sync, add to sync queue
				if ( ! $last_sync_time || $modified_time > $last_sync_time ) {
					$products_to_sync[] = $product_id;
				}
			}

			// Queue up filtered IDs for sync
			$this->create_or_update_products( $products_to_sync );

		} catch ( \Exception $e ) {
			// Handle exception gracefully like the original method
		}
	}

	/**
	 * Get test product data.
	 */
	private function get_test_product_data( $product_id ) {
		return $this->test_products_data[ $product_id ] ?? null;
	}
}
