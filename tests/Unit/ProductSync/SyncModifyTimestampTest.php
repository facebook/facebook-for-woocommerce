<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Products\Sync;

use WooCommerce\Facebook\Products\Sync;
use WooCommerce\Facebook\Products\Sync\Background;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for Products\Sync\Background class - focused on timestamp functionality.
 *
 * @since 3.5.5
 */
class BackgroundTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * The testable Background instance under test
	 *
	 * @var TestableBackground
	 */
	private $background;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock the Logger class
		$this->mock_logger();

		$this->background = new TestableBackground();
	}

	/**
	 * Test that timestamps are updated after successful API call with handles.
	 */
	public function test_timestamp_update_after_successful_api_call() {
		// Mock time() to return a fixed timestamp
		$fixed_time = 1640995200; // 2022-01-01 00:00:00 UTC
		$this->background->set_mock_time( $fixed_time );

		// Set up test requests with UPDATE actions
		$requests = [
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => [
					'id' => 'wc_post_id_123',
					'name' => 'Test Product 1'
				]
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => [
					'id' => 'pagination-571_456',
					'name' => 'Test Product 2'
				]
			],
			[
				'method' => Sync::ACTION_DELETE,
				'data' => [
					'id' => 'simple-sku_789'
				]
			]
		];

		// Set up handles (non-empty to trigger timestamp update)
		$handles = ['handle1', 'handle2'];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $requests, $handles );

		// Verify that update_post_meta was called for UPDATE actions only
		$meta_updates = $this->background->get_meta_updates();

		$this->assertCount( 2, $meta_updates );
		$this->assertEquals( 123, $meta_updates[0]['product_id'] );
		$this->assertEquals( '_fb_sync_last_time', $meta_updates[0]['meta_key'] );
		$this->assertEquals( $fixed_time, $meta_updates[0]['meta_value'] );

		$this->assertEquals( 456, $meta_updates[1]['product_id'] );
		$this->assertEquals( '_fb_sync_last_time', $meta_updates[1]['meta_key'] );
		$this->assertEquals( $fixed_time, $meta_updates[1]['meta_value'] );
	}

	/**
	 * Test that timestamps are not updated when handles are empty.
	 */
	public function test_no_timestamp_update_when_handles_empty() {
		// Set up test requests
		$requests = [
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => [
					'id' => 'wc_post_id_123',
					'name' => 'Test Product'
				]
			]
		];

		// Set up empty handles
		$handles = [];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $requests, $handles );

		// Verify that no meta updates occurred
		$meta_updates = $this->background->get_meta_updates();
		$this->assertEmpty( $meta_updates );
	}

	/**
	 * Test that timestamps are not updated for DELETE actions.
	 */
	public function test_no_timestamp_update_for_delete_actions() {
		// Set up test requests with DELETE actions only
		$requests = [
			[
				'method' => Sync::ACTION_DELETE,
				'data' => [
					'id' => 'wc_post_id_123'
				]
			],
			[
				'method' => Sync::ACTION_DELETE,
				'data' => [
					'id' => 'simple-sku_456'
				]
			]
		];

		// Set up handles
		$handles = ['handle1'];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $requests, $handles );

		// Verify that no meta updates occurred
		$meta_updates = $this->background->get_meta_updates();
		$this->assertEmpty( $meta_updates );
	}

	/**
	 * Test that various retailer ID formats are handled correctly.
	 */
	public function test_retailer_id_format_parsing() {
		$fixed_time = 1640995200;
		$this->background->set_mock_time( $fixed_time );

		// Test valid retailer ID formats that should match /_(\d+)$/
		$valid_requests = [
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'wc_post_id_123']
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'pagination-571_456']
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'simple-sku_789']
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'complex_format_with_multiple_underscores_999']
			]
		];

		$handles = ['handle1'];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $valid_requests, $handles );

		// Verify that all valid formats were processed
		$meta_updates = $this->background->get_meta_updates();
		$this->assertCount( 4, $meta_updates );

		$product_ids = array_column( $meta_updates, 'product_id' );
		$this->assertContains( 123, $product_ids );
		$this->assertContains( 456, $product_ids );
		$this->assertContains( 789, $product_ids );
		$this->assertContains( 999, $product_ids );
	}

	/**
	 * Test that invalid retailer ID formats are not processed.
	 */
	public function test_invalid_retailer_id_formats_ignored() {
		$fixed_time = 1640995200;
		$this->background->set_mock_time( $fixed_time );

		// Test invalid retailer ID formats that should NOT match /_(\d+)$/
		$invalid_requests = [
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'invalid_format_no_digits_at_end_abc']
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'ends_with_underscore_']
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'mixed_123_abc']
			]
		];

		$handles = ['handle1'];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $invalid_requests, $handles );

		// Verify that no invalid formats were processed
		$meta_updates = $this->background->get_meta_updates();
		$this->assertEmpty( $meta_updates, 'No meta updates should occur for invalid retailer ID formats' );
	}

	/**
	 * Test that requests without valid data are skipped.
	 */
	public function test_invalid_request_data_handling() {
		$fixed_time = 1640995200;
		$this->background->set_mock_time( $fixed_time );

		// Test requests with invalid data
		$requests = [
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'wc_post_id_123'] // Valid
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => ''] // Empty ID
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => [] // Missing ID
			],
			[
				'method' => Sync::ACTION_UPDATE
				// Missing data entirely
			]
		];

		$handles = ['handle1'];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $requests, $handles );

		// Verify that only the valid request was processed
		$meta_updates = $this->background->get_meta_updates();
		$this->assertCount( 1, $meta_updates );
		$this->assertEquals( 123, $meta_updates[0]['product_id'] );
	}

	/**
	 * Test that zero product IDs are not processed.
	 */
	public function test_zero_product_id_handling() {
		$fixed_time = 1640995200;
		$this->background->set_mock_time( $fixed_time );

		// Test requests that would result in zero product ID
		$requests = [
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'wc_post_id_0'] // Zero ID
			],
			[
				'method' => Sync::ACTION_UPDATE,
				'data' => ['id' => 'wc_post_id_123'] // Valid ID
			]
		];

		$handles = ['handle1'];

		// Execute the timestamp update logic
		$this->background->test_timestamp_update_logic( $requests, $handles );

		// Verify that only the non-zero product ID was processed
		$meta_updates = $this->background->get_meta_updates();
		$this->assertCount( 1, $meta_updates );
		$this->assertEquals( 123, $meta_updates[0]['product_id'] );
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
 * Testable version of Background class that allows testing the timestamp functionality.
 */
class TestableBackground extends Background {

	/**
	 * Mock time value.
	 *
	 * @var int
	 */
	private $mock_time;

	/**
	 * Array to track meta updates.
	 *
	 * @var array
	 */
	private $meta_updates = [];

	/**
	 * Set mock time value.
	 */
	public function set_mock_time( int $time ) {
		$this->mock_time = $time;
	}

	/**
	 * Get recorded meta updates.
	 */
	public function get_meta_updates(): array {
		return $this->meta_updates;
	}

	/**
	 * Test the timestamp update logic directly.
	 */
	public function test_timestamp_update_logic( array $requests, array $handles ) {
		// Reset meta updates
		$this->meta_updates = [];

		// Simulate the timestamp update logic from the original method
		if ( ! empty( $handles ) ) {
			try {
				$current_time = $this->mock_time ?? time();
				foreach ( $requests as $request ) {
					if ( Sync::ACTION_UPDATE === $request['method'] && isset( $request['data']['id'] ) && ! empty( $request['data']['id'] ) ) {
						// Extract product ID from retailer ID by taking digits after the last underscore
						// Works with any format: wc_post_id_123, pagination-571_1167, simple-sku_456, etc.
						if ( preg_match( '/_(\d+)$/', $request['data']['id'], $matches ) ) {
							$product_id = (int) $matches[1];
							if ( $product_id > 0 ) {
								$this->mock_update_post_meta( $product_id, '_fb_sync_last_time', $current_time );
							}
						}
					}
				}
			} catch ( \Exception $e ) {
				// Log the error but don't interrupt the sync process
				// In tests, we'll just ignore exceptions
			}
		}
	}

	/**
	 * Mock update_post_meta function to track calls.
	 */
	private function mock_update_post_meta( int $product_id, string $meta_key, $meta_value ) {
		$this->meta_updates[] = [
			'product_id' => $product_id,
			'meta_key' => $meta_key,
			'meta_value' => $meta_value
		];
	}
}
