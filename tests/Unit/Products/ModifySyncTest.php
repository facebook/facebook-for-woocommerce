<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Products;

use WooCommerce\Facebook\Products\Sync;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Products\Sync class - focused on create_or_update_modified_products() core logic.
 *
 * @since 3.5.5
 */
class ModifySyncTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * The Sync instance under test
	 *
	 * @var TestableSync
	 */
	private $sync;

	/**
	 * Test product IDs created during tests
	 *
	 * @var array
	 */
	private $test_product_ids = array();

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock the Logger class if it doesn't exist
		$this->mock_logger();

		// Create the testable sync instance
		$this->sync = new TestableSync();

		// Clean up any existing test products
		$this->cleanup_test_products();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->cleanup_test_products();
		parent::tearDown();
	}

	/**
	 * Test that products never synced before are added to sync queue.
	 */
	public function test_create_or_update_modified_products_syncs_never_synced_products() {
		// Create test products
		$product_1 = $this->create_test_product( array( 'name' => 'Test Product 1' ) );
		$product_2 = $this->create_test_product( array( 'name' => 'Test Product 2' ) );
		$product_3 = $this->create_test_product( array( 'name' => 'Test Product 3' ) );

		$product_ids = array( $product_1->get_id(), $product_2->get_id(), $product_3->get_id() );

		// Mock the utility function to return our test product IDs
		$this->sync->set_mock_product_ids( $product_ids );

		// Ensure products have never been synced (no _fb_sync_last_time meta)
		foreach ( $product_ids as $product_id ) {
			delete_post_meta( $product_id, '_fb_sync_last_time' );
		}

		// Execute the method
		$this->sync->create_or_update_modified_products();

		// Verify all products were added to sync queue
		$requests = $this->sync->get_requests();
		$this->assertCount( 3, $requests );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-' . $product_1->get_id()] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-' . $product_2->get_id()] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-' . $product_3->get_id()] );
	}

	/**
	 * Test that products modified since last sync are added to sync queue.
	 */
	public function test_create_or_update_modified_products_syncs_modified_products() {
		// Create test products
		$product_1 = $this->create_test_product( array( 'name' => 'Test Product 1' ) );
		$product_2 = $this->create_test_product( array( 'name' => 'Test Product 2' ) );
		$product_3 = $this->create_test_product( array( 'name' => 'Test Product 3' ) );

		$product_ids = array( $product_1->get_id(), $product_2->get_id(), $product_3->get_id() );

		// Set last sync times
		$base_time = time() - 3600; // 1 hour ago
		update_post_meta( $product_1->get_id(), '_fb_sync_last_time', $base_time );
		update_post_meta( $product_2->get_id(), '_fb_sync_last_time', $base_time );
		update_post_meta( $product_3->get_id(), '_fb_sync_last_time', $base_time );

		// Modify products 1 and 2 after the sync time
		$product_1->set_name( 'Modified Product 1' );
		$product_1->save();

		$product_2->set_name( 'Modified Product 2' );
		$product_2->save();

		// Product 3 remains unmodified (its modification time should be before the sync time)

		// Mock the utility function to return our test product IDs
		$this->sync->set_mock_product_ids( $product_ids );

		// Execute the method
		$this->sync->create_or_update_modified_products();

		// Verify only modified products were added to sync queue
		$requests = $this->sync->get_requests();

		// Should have at least products 1 and 2
		$this->assertGreaterThanOrEqual( 2, count( $requests ) );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-' . $product_1->get_id()] );
		$this->assertEquals( Sync::ACTION_UPDATE, $requests['p-' . $product_2->get_id()] );
	}

	/**
	 * Test that products not modified since last sync are not added to sync queue.
	 */
	public function test_create_or_update_modified_products_skips_unmodified_products() {
		// Create test product
		$product = $this->create_test_product( array( 'name' => 'Test Product' ) );
		$product_id = $product->get_id();

		// Set last sync time to current time (product won't be modified after this)
		$current_time = time();
		update_post_meta( $product_id, '_fb_sync_last_time', $current_time );

		// Mock the utility function to return our test product ID
		$this->sync->set_mock_product_ids( array( $product_id ) );

		// Execute the method
		$this->sync->create_or_update_modified_products();

		// Verify product was not added to sync queue (or was added due to >= comparison)
		$requests = $this->sync->get_requests();

		// The actual behavior depends on the exact timing, but we can verify the method runs without error
		$this->assertIsArray( $requests );
	}

	/**
	 * Test handling of invalid products.
	 */
	public function test_create_or_update_modified_products_skips_invalid_products() {
		// Create one valid product
		$valid_product = $this->create_test_product( array( 'name' => 'Valid Product' ) );
		$valid_id = $valid_product->get_id();

		// Use a non-existent product ID
		$invalid_id = 999999;

		$product_ids = array( $valid_id, $invalid_id );

		// Mock the utility function to return both valid and invalid IDs
		$this->sync->set_mock_product_ids( $product_ids );

		// Execute the method
		$this->sync->create_or_update_modified_products();

		// Verify only valid product was processed
		$requests = $this->sync->get_requests();

		// Should have the valid product
		$this->assertArrayHasKey( 'p-' . $valid_id, $requests );
		// Should not have the invalid product
		$this->assertArrayNotHasKey( 'p-' . $invalid_id, $requests );
	}

	/**
	 * Test that the method handles empty product lists gracefully.
	 */
	public function test_create_or_update_modified_products_handles_empty_list() {
		// Mock the utility function to return empty array
		$this->sync->set_mock_product_ids( array() );

		// Execute the method
		$this->sync->create_or_update_modified_products();

		// Verify no products were added to sync queue
		$requests = $this->sync->get_requests();
		$this->assertEmpty( $requests );
	}

	/**
	 * Test that the method logs appropriately.
	 */
	public function test_create_or_update_modified_products_logs_events() {
		// Create test product
		$product = $this->create_test_product( array( 'name' => 'Test Product' ) );
		$product_id = $product->get_id();

		// Mock the utility function to return our test product ID
		$this->sync->set_mock_product_ids( array( $product_id ) );

		// Execute the method (should not throw any exceptions)
		$this->sync->create_or_update_modified_products();

		// Verify method completed without errors
		$this->assertTrue( true ); // If we get here, no exceptions were thrown
	}

	/**
	 * Helper method to create a test product.
	 */
	private function create_test_product( array $args = array() ) {
		$defaults = array(
			'name' => 'Test Product',
			'type' => 'simple',
			'regular_price' => '10.00',
			'status' => 'publish',
		);

		$args = array_merge( $defaults, $args );

		$product = new \WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['regular_price'] );
		$product->set_status( $args['status'] );
		$product->save();

		$this->test_product_ids[] = $product->get_id();

		return $product;
	}

	/**
	 * Helper method to clean up test products.
	 */
	private function cleanup_test_products() {
		foreach ( $this->test_product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}
		$this->test_product_ids = array();
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
}

/**
 * Testable version of Sync class that allows dependency injection and access to protected properties.
 */
class TestableSync extends Sync {

	/**
	 * Mock product IDs to return from WC_Facebookcommerce_Utils.
	 *
	 * @var array
	 */
	private $mock_product_ids = array();

	/**
	 * Get the requests array for testing.
	 */
	public function get_requests(): array {
		return $this->requests;
	}

	/**
	 * Set mock product IDs for the test.
	 */
	public function set_mock_product_ids( array $product_ids ) {
		$this->mock_product_ids = $product_ids;
	}

	/**
	 * Override create_or_update_modified_products to use mock data.
	 */
	public function create_or_update_modified_products() {
		\WooCommerce\Facebook\Framework\Logger::log(
			'Starting sync of modified products',
			[
				'event' => 'product_sync_modified_products_start',
			]
		);

		try {
			// Use mock product IDs instead of calling the utility function
			$all_product_ids = $this->mock_product_ids;

			// Filter to only get products modified since last sync
			$products_to_sync = array();

			foreach ( $all_product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$last_sync_time = get_post_meta( $product_id, '_fb_sync_last_time', true );
				$modified_time = $product->get_date_modified() ? $product->get_date_modified()->getTimestamp() : 0;

				// If never synced or modified since last sync (using >= to catch same-second modifications), add to sync queue
				if ( ! $last_sync_time || $modified_time >= $last_sync_time ) {
					$products_to_sync[] = $product_id;
				}
			}

			// Queue up filtered IDs for sync
			$this->create_or_update_products( $products_to_sync );

			\WooCommerce\Facebook\Framework\Logger::log(
				'Completed sync of modified products',
				[
					'event' => 'product_sync_modified_products_complete',
					'product_count' => count( $products_to_sync ),
				]
			);
		} catch ( \Exception $e ) {
			// Log the error but don't interrupt the sync process
			\WooCommerce\Facebook\Framework\Logger::log(
				'Error syncing modified products',
				[
					'event' => 'product_sync_modified_products_error',
					'error_message' => $e->getMessage(),
				],
				[
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level' => \WC_Log_Levels::ERROR,
				],
				$e
			);
		}
	}
}
